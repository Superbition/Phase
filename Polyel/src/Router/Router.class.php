<?php

namespace Polyel\Router;

use Polyel;
use Exception;
use Polyel\Debug\Debug;
use Polyel\Http\Kernel;
use Polyel\Http\Request;
use Polyel\Http\Response;
use Polyel\Middleware\Middleware;
use Polyel\Session\SessionManager;

class Router
{
    use RouteVerbs;
    use RouteUtilities;

    // Holds all the request routes to respond to
    private $routes;

    // The last added registered route and its request method
    private $lastAddedRoute;

    // Full list of registered routes that were added
    private $listOfAddedRoutes;

    // The Session Manager service
    private $sessionManager;

    // The Debug service
    private $debug;

    // The Middleware service
    private $middleware;

    private $routeParamPattern;

    public function __construct(SessionManager $sessionManager, Debug $debug, Middleware $middleware)
    {
        $this->sessionManager = $sessionManager;
        $this->debug = $debug;
        $this->middleware = $middleware;
    }

    public function handle(Request $request, Kernel $HttpKernel): Response
    {
        // Get the full URL from the clients request
        $this->requestedRoute = $request->server["request_uri"];

        /*
         * Split the URI into an array based on the delimiter
         * Remove empty array values from the URI because of the delimiters
         * Reindex the array back to 0
         */
        $this->uriSplit = explode("/", $request->server["request_uri"]);
        $this->uriSplit = array_filter($this->uriSplit);
        $this->uriSplit = array_values($this->uriSplit);

        // Get the request method: GET, POST, PUT etc.
        $this->requestMethod = $request->server["request_method"];

        // Check for a HEAD request
        if($this->requestMethod === "HEAD")
        {
            // Because HEAD and GET are basically the same, switch a HEAD to act like a GET request
            $this->requestMethod = "GET";
        }

        // Continue routing if there is a URL
        if(!empty($this->requestedRoute))
        {
            // Check if a redirection has been set...
            if(isset($this->routes["REDIRECT"][$this->requestedRoute]))
            {
                // Set a redirection to happen when responding
                $redirection = $this->routes["REDIRECT"][$this->requestedRoute];
                $this->response->redirect($redirection["url"], $redirection["statusCode"]);

                // Returning progresses the request to skip to responding directly
                return;
            }

            $this->request->capture($request);

            // Check if the route matches any registered routes
            if($this->routeExists($this->requestMethod, $this->requestedRoute))
            {
                // Only operate the session system if set to active
                if(config('session.active'))
                {
                    // Grab the session cookie and check for a valid session, create one if one doesn't exist
                    $sessionCookie = $this->request->cookie(config('session.cookieName'));
                    $this->sessionManager->startSession($sessionCookie);
                }

                // Set the default HTTP status code, might change throughout the request cycle
                $this->response->setStatusCode(200);

                // Get the current matched controller and route action
                $controller = $this->currentController;
                $controllerAction = $this->currentRouteAction;

                //The controller namespace and getting its instance from the container using ::call
                $controllerName = "App\Controllers\\" . $controller;
                $controller = Polyel::call($controllerName);

                // Check that the controller exists
                if(isset($controller) && !empty($controller))
                {
                    // Capture a response from a before middleware if one returns a response
                    $beforeMiddlewareResponse = $this->middleware->runAnyBefore($this->request, $this->requestMethod, $this->currentRegURL);

                    // If a before middleware wants to return a response early in the app process...
                    if(exists($beforeMiddlewareResponse))
                    {
                        // Build the response from a before middleware and return to halt execution of the app
                        $this->response->build($beforeMiddlewareResponse);
                        return;
                    }

                    // Resolve and perform method injection when calling the controller action
                    $methodDependencies = Polyel::resolveMethod($controllerName, $controllerAction);

                    // Method injection for any services first, then route parameters and get the controller response
                    $controllerResponse = $controller->$controllerAction(...$methodDependencies, ...$this->currentRouteParams);

                    // Capture a response returned from any after middleware if one returns a response...
                    $afterMiddlewareResponse = $this->middleware->runAnyAfter($this->request, $this->response, $this->requestMethod, $this->currentRegURL);

                    // After middleware takes priority over the controller when returning a response
                    if(exists($afterMiddlewareResponse))
                    {
                        // If a after middleware wants to return a response, send it off to get built...
                        $this->response->build($afterMiddlewareResponse);
                    }
                    else
                    {
                        /*
                         * Execution reaches this level when no before or after middleware wants to return a response,
                         * meaning the controller action can return its response for the request that was sent.
                         * Give the response service the response the controller wants to send back to the client
                         */
                        $this->response->build($controllerResponse);
                    }
                }
            }
            else
            {
                // Error 404 route not found
                $this->response->build(response(view('404:error'), 404));
            }
        }
    }

    private function addRoute($requestMethod, $route, $action)
    {
        // Throw an error if trying to add a route that already exists...
        if(in_array($route, $this->listOfAddedRoutes[$requestMethod]))
        {
            throw new Exception("\e[41m Trying to add a route that already exists: " . $route . " \e[0m");
        }

        // Only pack the route when it has more than one parameter
        if(strlen($route) > 1)
        {
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
    }

    private function routeExists($requestMethod, $requestedRoute)
    {
        // For when the route requested is more than one char, meaning its not the index `/` route
        if(strlen($requestedRoute) > 1)
        {
            $requestedRoute = urldecode($requestedRoute);

            /*
             * Because the route requested is more than one char, it means we have a route that is not the
             * index `route` so it needs to be processed and matched to a registered route in order to
             * process further into the application. Here we prepare the requested route into segments and trim any
             * left or right `/` chars which would cause an empty element in an array during the matching process of
             * the matching logic. The requested route is segmented so its easy to loop through and find a match...
             */
            $segmentedRequestedRoute = explode("/", rtrim(ltrim($requestedRoute, "/"), "/"));
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
            // Get the built up registered URL that was matched
            $this->currentRegURL = $routeRequested["regURL"];

            // Extract the controller and action and set them so the class has access to them
            $routeRequested["controller"] = explode("@", $routeRequested["controller"]);
            $this->currentController = $routeRequested["controller"][0];
            $this->currentRouteAction = $routeRequested["controller"][1];

            // Give the class access to any route parameters if they were found
            $this->currentRouteParams = $routeRequested["params"];

            // A route match was made...
            return true;
        }

        // If no route can be matched to a registered route
        return false;
    }

    public function middleware($middlewareKeys)
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

    public function loadRoutes()
    {
        $this->initialiseHttpVerbs();

        require ROOT_DIR . "/app/routing/web.php";
    }

    public function setup()
    {
        // Use the param tag from the Router config file, used when detecting params in routes
        $paramTag = explode(" ", config("router.routeParameterTag"));
        $this->routeParamPattern = "/(\\" . $paramTag[0] . "[a-zA-Z_0-9]*\\" . $paramTag[1] . ")/";
    }
}