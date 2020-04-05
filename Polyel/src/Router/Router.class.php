<?php

namespace Polyel\Router;

use Polyel;
use Polyel\View\View;
use Polyel\Debug\Debug;
use Polyel\Http\Request;
use Polyel\Http\Response;
use Polyel\Middleware\Middleware;

class Router
{
    use RouteVerbs;
    use RouteUtilities;

    // The URI pattern the route responds to.
    private $uriSplit;

    // Holds the main route name/page
    private $requestedRawRoute;

    // Holds the current matched controller from the registered route
    private $currentController;

    // Holds the current matched route action for the controller
    private $currentRouteAction;

    // Holds the current parameters matched from a registered route
    private $currentRouteParams;

    // Holds the request method sent by the client
    private $requestMethod;

    // Holds all the request routes to respond to
    private $routes;

    private $lastAddedRoute;

    // Holds the requested view template file name
    private $requestedView;

    private $view;

    private $debug;

    private $middleware;

    private $request;

    private $response;

    public function __construct(View $view, Debug $debug, Middleware $middleware, Request $request, Response $response)
    {
        $this->view = $view;
        $this->debug = $debug;
        $this->middleware = $middleware;
        $this->request = $request;
        $this->response = $response;
    }

    public function handle(&$request)
    {
        // Get the full URL from the clients request
        $this->requestedRawRoute = $this->uriSplit = $request->server["request_uri"];

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

        // Continue routing if there is a URL
        if(!empty($this->requestedRawRoute))
        {
            // Check if the route matches any registered routes
            if($this->routeExists($this->requestMethod, $this->requestedRawRoute))
            {
                $this->requestedView = null;

                // Get the current matched controller and route action
                $controller = $this->currentController;
                $controllerAction = $this->currentRouteAction;

                //The controller namespace and getting its instance from the container using ::call
                $controllerName = "App\Controllers\\" . $controller;
                $controller = Polyel::call($controllerName);

                // Check that the controller exists
                if(isset($controller) && !empty($controller))
                {
                    $this->middleware->runAnyBefore($this->request, $this->requestMethod, $this->requestedRawRoute);

                    // Resolve and perform method injection when calling the controller action
                    $methodDependencies = Polyel::resolveMethod($controllerName, $controllerAction);

                    // Method injection for any services first, then route parameters
                    $controller->$controllerAction(...$methodDependencies, ...$this->currentRouteParams);

                    $this->middleware->runAnyAfter($this->response, $this->requestMethod, $this->requestedRawRoute);
                }
            }
            else
            {
                // Error 404 route not found
                $this->requestedView = __DIR__ . "/../../../app/views/errors/404.html";
            }
        }
    }

    public function deliver(&$response)
    {
        if($this->debug->doDumpsExist())
        {
            // The rendered response but with the debug dumps at the start.
            $response->end($this->debug->getDumps() . "<br>" . Template::render($this->requestedView));

            // Resets the last amount of dumps so duplicates are not shown upon next request.
            $this->debug->cleanup();
        }
        else
        {
            $response->end($this->view->render($this->requestedView));
        }
    }

    public function addRoute($requestMethod, $route, $action)
    {
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
        $this->shiftAllParamsToTheEnd($this->routes[$requestMethod]);
        $this->lastAddedRoute[$requestMethod] = $route;
    }

    public function routeExists($requestMethod, $requestedRoute)
    {
        // For when the route requested is more than one char, meaning its not the index `/` route
        if(strlen($requestedRoute) > 1)
        {
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
        }

        // Try and match the requested route to a registered route, false is returned when no match is found
        $routeRequested = $this->matchRoute($this->routes[$requestMethod], $segmentedRequestedRoute);

        // If a route is found, the controller and action is returned, along with any set params
        if($routeRequested)
        {
            // Extract the controller and action and set them so the class has access to them
            $routeRequested["controller"] = explode("@", $routeRequested["controller"]);
            $this->currentController = $routeRequested["controller"][0];
            $this->currentRouteAction = $routeRequested["controller"][1];

            // Give the class access to any route parameters if they were found
            $this->currentRouteParams = $routeRequested["params"];

            return true;
        }

        // If no route can be matched to a registered route
        return false;
    }

    public function getCurrentRawRoute()
    {
        return $this->requestedRawRoute;
    }

    public function getCurrentRouteAction()
    {
        return $this->currentRouteAction;
    }

    public function middleware($middlewareKeys)
    {
        $requestMethod = array_key_first($this->lastAddedRoute);
        $routeUri = $this->lastAddedRoute[$requestMethod];
        $this->middleware->register($requestMethod, $routeUri, $middlewareKeys);
    }

    public function loadRoutes()
    {
        $this->initialiseHttpVerbs();

        require __DIR__ . "/../../../app/routes.php";
    }
}