<?php

namespace Polyel\Middleware;

use Polyel;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Middleware
{
    private $middlewareDirectory = ROOT_DIR . "/app/Middleware/";

    // Holds all registered Middlewares, in the format of [requestMethod][uri] = middleware
    private $middlewares = [];

    public function __construct()
    {

    }

    public function loadAllMiddleware()
    {
        $middlewareDir = new RecursiveDirectoryIterator($this->middlewareDirectory);
        $pathIterator = new RecursiveIteratorIterator($middlewareDir);

        // Search through the Middleware directory for .php files to preload as Middleware
        foreach($pathIterator as $middleware)
        {
            $middlewareFilePath = $middleware->getPathname();

            // Only match .php files
            if(preg_match('/^.+\.php$/i', $middlewareFilePath))
            {
                // Make the class available by declaring it
                require_once $middlewareFilePath;

                // The last declared class will be the above when it was required_once
                $listOfDefinedClasses = get_declared_classes();

                // Get the last class in the array of declared classes
                $definedClass = explode("\\", end($listOfDefinedClasses));
                $definedClass = end($definedClass);

                // Calling the Container to resolve the class into the container
                Polyel::resolveClass("App\Middleware\\" . $definedClass);
            }
        }
    }

    public function register($requestMethod, $uri, $middleware)
    {
        $this->middlewares[$requestMethod][$uri] = $middleware;
    }

    public function runGlobalMiddleware($applicationStage, $middlewareType)
    {
        $globalBeforeMiddleware = config("middleware.global." . $middlewareType);

        foreach($globalBeforeMiddleware as $middleware)
        {
            // Use config() to get the full namespace based on the middleware key
            $middleware = config("middleware.keys." . $middleware);

            // Call Polyel and get the middleware class from the container
            $middlewareToRun = Polyel::call($middleware);

            // Based on the passed in middleware type, execute if both types match
            if($middlewareToRun->middlewareType == $middlewareType)
            {
                // Process the middleware if the request types match up
                $middlewareToRun->process($applicationStage);
            }
        }
    }

    /*
     * Runs any middleware based on the type passed in and processes the stage of the application,
     * before or after. $applicationStage is the request or response service that gets passed in to
     * allow a middleware to process its correct type.
     */
    private function runMiddleware($applicationStage, $type, $requestMethod, $route)
    {
        // Check if a middleware exists for the request method, GET, POST etc.
        if(array_key_exists($requestMethod, $this->middlewares))
        {
            // Then check for a middleware inside that request method, for a route...
            if(array_key_exists($route, $this->middlewares[$requestMethod]))
            {
                // Get the middleware key(s) set for this request method and route
                $middlewareKeys = $this->middlewares[$requestMethod][$route];

                // Turn the middleware key into a array if its only one middleware
                if(!is_array($middlewareKeys))
                {
                    // An array makes it easier to process single and multiple middlewares, no duplicate code...
                    $middlewareKeys = [$middlewareKeys];
                }

                // Process each middleware and run process() from each middleware
                foreach($middlewareKeys as $middlewareKey)
                {
                    // Use config() to get the full namespace based on the middleware key
                    $middleware = config("middleware.keys." . $middlewareKey);

                    // Call Polyel and get the middleware class from the container
                    $middlewareToRun = Polyel::call($middleware);

                    // Based on the passed in middleware type, execute if both types match
                    if($middlewareToRun->middlewareType == $type)
                    {
                        // Process the middleware if the request types match up
                        $middlewareToRun->process($applicationStage);
                    }
                }
            }
        }
    }

    public function runAnyBefore($request, $requestMethod, $route)
    {
        $this->runGlobalMiddleware($request, "before");

        $this->runMiddleware($request, "before", $requestMethod, $route);
    }

    public function runAnyAfter($response, $requestMethod, $route)
    {
        $this->runGlobalMiddleware($response, "after");

        $this->runMiddleware($response, "after", $requestMethod, $route);
    }
}