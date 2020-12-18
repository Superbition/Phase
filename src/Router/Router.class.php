<?php

namespace Polyel\Router;

use Closure;
use RuntimeException;
use Polyel\Debug\Debug;
use Polyel\Http\Kernel;
use Polyel\Http\Request;
use Polyel\Http\Response;
use Polyel\Session\SessionManager;
use Polyel\Http\Middleware\MiddlewareManager;

class Router
{
    use RouteVerbs;
    use RouteUtilities;

    // Holds all the request routes to respond to
    private $routes;

    // Flag to detect when API routes are being added
    private $registeringApiRoutes = false;

    // The last added registered route and its request method
    private $lastAddedRoute;

    // Full list of registered routes that were added
    private $listOfAddedRoutes;

    private $groupStack = [];

    // The Session Manager service
    private $sessionManager;

    // The Debug service
    private $debug;

    // The Middleware service
    private $middleware;

    public function __construct(SessionManager $sessionManager, Debug $debug, MiddlewareManager $middleware)
    {
        $this->sessionManager = $sessionManager;
        $this->debug = $debug;
        $this->middleware = $middleware;
    }

    public function handle(Request $request, Kernel $HttpKernel): Response
    {
        // Check for a HEAD request
        if($request->method === "HEAD")
        {
            // Because HEAD and GET are basically the same, switch a HEAD to act like a GET request
            $request->method = "GET";
        }

        // Get the response from the HTTP Kernel that will be sent back to the client
        $response = $HttpKernel->response;

        // Continue routing if there is a URL
        if(!empty($request->uri))
        {
            // Check if a redirection has been set...
            if(isset($this->routes["REDIRECT"][$request->uri]))
            {
                // Set a redirection to happen when responding
                $redirection = $this->routes["REDIRECT"][$request->uri];
                $response->redirect($redirection["url"], $redirection["statusCode"]);

                // Returning progresses the request to skip to responding directly
                return $response;
            }

            if($httpSpoofMethod = $request->data('http_method'))
            {
                if(in_array(strtolower($httpSpoofMethod), ['put', 'patch', 'delete']))
                {
                    $request->method = strtoupper($httpSpoofMethod);
                }
            }

            /*
             * Search for a registered route based on the request method and URI.
             * If a route is found, route information is returned, controller, action, parameters and URL.
             * False is returned is no match can be made for the requested route.
             */
            $matchedRoute = $this->getRegisteredRouteFor($request->method, $request->uri);

            // Check if the requested route exists, we continue further into the application...
            if($matchedRoute !== false)
            {
                // Check if the request is an API registered route...
                if(is_array($matchedRoute['action']))
                {
                    if(isset($matchedRoute['action'][1]) && $matchedRoute['action'][1] === 'API')
                    {
                        /*
                         * When the route is an API request it will contain the API flag as part of the action.
                         * The first thing to do is replace the action index with only the route action.
                         * Then set the route type to API because this will let the Router know it is dealing
                         * with an API registered route.
                         */
                        $matchedRoute['action'] = $matchedRoute['action'][0];
                        $matchedRoute['type'] = 'API';
                    }
                }
                else
                {
                    // Else no API flag is set, meaning we are handling a normal WEB registered route.
                    $matchedRoute['type'] = 'WEB';

                    $request->type = 'web';
                }

                // Only operate the session system if it is a WEB route
                if($matchedRoute['type'] !== 'API')
                {
                    $this->startSessionSystem($HttpKernel);
                    $response->setSession($HttpKernel->session);
                }
                else
                {
                    $request->type = 'api';

                    $HttpKernel->session->disable();
                }

                // Set the default HTTP status code, might change throughout the request cycle
                $response->setStatusCode(200);

                // Get a response either from middleware or the core route action (closure or controller)
                $response = $HttpKernel->executeMiddlewareWithCoreAction($request->method, $matchedRoute);

                if($matchedRoute['type'] !== 'API')
                {
                    $HttpKernel->session->store('previousUrl', $request->uri);
                }
            }
            else
            {
                $this->startSessionSystem($HttpKernel);
                $response->setSession($HttpKernel->session);

                // Error 404 route not found
                $response->build(response(view('404:error'), 404));
            }
        }

        return $response;
    }

    private function startSessionSystem($HttpKernel)
    {
        // Check for a valid session and update the session data, create one if one doesn't exist
        $this->sessionManager->startSession($HttpKernel);

        if($oldData = $HttpKernel->request->data())
        {
            $HttpKernel->session->store('old', $oldData);
        }

        // Create the CSRF token if it is missing in the clients session data
        $csrfToken = $HttpKernel->session->createCsrfToken();

        // False means that a token has already been created and queued as a cookie before
        if($csrfToken !== false)
        {
            // Use the same lifetime as the session cookie
            $csrfTokenLifetime = config('session.lifetime');

            if($csrfTokenLifetime !== 0 && is_numeric($csrfTokenLifetime))
            {
                $csrfTokenLifetime *= 60;
            }

            $csrfTokenCookie = [
                $name = config('session.xsrfCookieName'),
                $value = $csrfToken,
                $expire = $csrfTokenLifetime,
                $path = config('session.cookiePath'),
                $domain = config('session.domain'),
                $secure = config('session.secure'),
                $httpOnly = false,
                $sameSite = 'Strict',
            ];

            // The CSRF cookie can be used to allow JavaScript requests to make valid HTTP requests with a CSRF token
            $HttpKernel->response->queueCookie(...$csrfTokenCookie);
        }
    }

    private function addRoute($requestMethod, $route, $action)
    {
        // If the group stack exists, it means we are adding routes from a group Closure
        if(exists($this->groupStack))
        {
            // Use a foreach to support nested route groups, reverse the stack so everything is added in correct order
            foreach(array_reverse($this->groupStack) as $stack)
            {
                // Add the prefix if one was set
                if(isset($stack['prefix']))
                {
                    $route = $stack['prefix'] . $route;
                }

                // Get the middlewares if some were set, they are set once the route is registered
                if(isset($stack['middleware']))
                {
                    // Gather all the middleware from the stack to register to the route at the end
                    $groupMiddleware[] = $stack['middleware'];
                }
            }
        }

        // Throw an error if trying to add a route that already exists...
        if(in_array($route, $this->listOfAddedRoutes[$requestMethod], true))
        {
            throw new RuntimeException("\e[41m Trying to add a route that already exists: " . $route . " \e[0m");
        }

        /*
         * Validate that the new route is a valid route and if it is using parameters correctly.
         * Routes must be separated with forward slashes and params must not touch each other.
         */
        if(preg_match_all("/^(\/([a-zA-Z-0-9]*|\{[a-z-0-9]+\}))+$/m", $route) === 0)
        {
            throw new RuntimeException("\e[41mInvalid route at:\e[0m '" . $route . "'");
        }

        // Only pack the route when it has more than one parameter
        if(strlen($route) > 1)
        {
            // If set to true, it means we are currently loading API routes from '/app/routing/api.php'
            if($this->registeringApiRoutes)
            {
                // So we attach API to the route action to indicate this route is an API request
                $action = [$action, 'API'];
            }

            /*
             * Convert a route into a single multidimensional array, making it easier to handle parameters later...
             * The route becomes the multidimensional array where the action is stored.
             */
            $packedRoute = $this->packRoute($route, $action);
        }
        else
        {
            // Support the single index route `/`
            $packedRoute[$route] = $action;
        }

        // Finally the single multidimensional route array is merged into the main routes array
        $this->routes[$requestMethod] = array_merge_recursive($packedRoute, $this->routes[$requestMethod]);

        // All params are moved to the end of their array level because static routes take priority
        $this->shiftAllParamsToTheEnd($this->routes[$requestMethod]);

        // Reset the last added route and store the most recently added route
        $this->lastAddedRoute = null;
        $this->lastAddedRoute[$requestMethod] = $route;

        // Keep a list of all the added routes
        $this->listOfAddedRoutes[$requestMethod][] = $route;

        if(!$this->registeringApiRoutes)
        {
            // Assign the web middleware group to web only routes
            $this->middleware(['web']);
        }
        else
        {
            // Assign the api middleware group to api only routes
            $this->middleware(['api']);
        }

        // Register any group middleware if they exist, they will set on the last added route
        if(isset($groupMiddleware))
        {
            $middlewareList = [];

            // Support adding nested grouped middleware
            foreach($groupMiddleware as $middleware)
            {
                // An array here means we have a group with more than one middleware to add
                if(is_array($middleware))
                {
                    /*
                     * When registering middleware inside a route group and the
                     * group is registering multiple middleware at a time, an array
                     * is required so that a list of middleware can be defined. However,
                     * this means we will end up with an array that is 1 level too deep.
                     * To fix the array from being 1 level too deep, we extract the values
                     * only of the middleware group array and merge them at a single
                     * dimensional level.
                     */
                    $middlewareList = array_merge(array_column($middleware, null), $middlewareList);
                }
                else
                {
                    // Build up all the keys from the group stack
                    $middlewareList[] = $middleware;
                }
            }

            // Add all middleware to the route which is apart of a group(s)
            $this->middleware($middlewareList);
        }
    }

    private function getRegisteredRouteFor($requestMethod, $requestedRoute)
    {
        // For when the route requested is more than one char, meaning its not the index `/` route
        if(strlen($requestedRoute) > 1)
        {
            $requestedRoute = urldecode($requestedRoute);

            /*
             * Because the route requested is more than one char, it means we have a route that is not the
             * index `route` so it needs to be processed and matched to a registered route in order to
             * process further into the application. Here we prepare the requested route into segments and trim any
             * right `/` chars. Only trim the right side of the route because we don't want to fix invalid routes
             * like '//admin' to '/admin' for the client and cause invalid routes to still match correctly.
             *
             * The requested route is segmented so its easy to loop through and find a match...
             */
            $segmentedRequestedRoute = explode("/", rtrim($requestedRoute, "/"));

            /*
             * Remove the first proceeding forward slash from the URL as explode treats '/' as empty.
             * We remove the first '/' to compensate for the first slash from the URL but not anymore. If a route
             * contains two or more slashes, these will not process or match properly.
             */
            array_shift($segmentedRequestedRoute);
        }
        else
        {
            // Else we check if the index route has been requested
            if($requestedRoute === "/")
            {
                // Index route requested, no need process a one char route, perform it manually instead
                $segmentedRequestedRoute[] = "/";
            }

            // Catch undefined requests
            if(!isset($requestedRoute))
            {
                // The requested route is null
                return false;
            }
        }

        // Try and match the requested route to a registered route, false is returned when no match is found
        $routeRequested = $this->matchRoute($this->routes[$requestMethod], $segmentedRequestedRoute);

        // If a route is found, the controller and action is returned, along with any set params
        if($routeRequested)
        {
            $matchedRoute['url'] = $routeRequested["regURL"];
            $matchedRoute['action'] = $routeRequested["action"];
            $matchedRoute['params'] = $routeRequested["params"];

            // A route match was made...
            return $matchedRoute;
        }

        // If no route can be matched to a registered route
        return false;
    }

    /*
     * Group a set of routes using specific attributes like prefix and middleware
     */
    public function group($attributes, Closure $routes)
    {
        /*
         * Using a closure, set the attributes first and then
         * run the closure which should be a group of routes. Each route in
         * the closure will use the attributes set in the group stack. The group
         * stack is cleared once the group call has finished.
         */
        if($routes instanceof Closure)
        {
            // Set the group stack of attributes for the group to use when registering new routes in the group
            $this->groupStack[] = $attributes;

            // Run the set of grouped rotues
            $routes();
        }

        // Clear the most recent group from the stack
        array_pop($this->groupStack);
    }

    public function middleware(array $middlewareKeys)
    {
        $requestMethod = array_key_first($this->lastAddedRoute);
        $routeUri = $this->lastAddedRoute[$requestMethod];
        $this->middleware->register($requestMethod, $routeUri, $middlewareKeys);
    }

    public function redirect($src, $des, $statusCode = 302)
    {
        // Register a new redirection with its URL and status code
        $this->routes["REDIRECT"][$src]["url"] = $des;
        $this->routes["REDIRECT"][$src]["statusCode"] = $statusCode;
    }

    public function addAuthRoutes()
    {
        registerAuthRoutes();
    }

    public function loadRoutes()
    {
        $this->initialiseHttpVerbs();

        // Load web routes...
        require APP_DIR . "/routing/web.php";

        // Load api routes...
        $this->registeringApiRoutes = true;
        require APP_DIR . "/routing/api.php";
        $this->registeringApiRoutes = false;

        $this->middleware->optimiseRegisteredMiddleware();
    }
}
