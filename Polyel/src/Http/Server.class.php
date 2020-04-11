<?php

namespace Polyel\Http;

use Polyel\Router\Router;
use Polyel\Config\Config;
use Swoole\Coroutine as Swoole;
use Polyel\Controller\Controller;
use Polyel\Middleware\Middleware;
use Swoole\HTTP\Server as SwooleHTTPServer;

class Server
{
    // The Swoole server class variable
    private $server;

    // The config instance from the container
    private $config;

    // The Route instance from the container
    private $router;

    // The Controller instance from the container
    private $controller;

    private $middleware;

    public function __construct(Config $config, Router $router, Controller $controller, Middleware $middleware)
    {
        cli_set_process_title("Polyel-HTTP-Server");

        $this->config = $config;
        $this->router = $router;
        $this->controller = $controller;
        $this->middleware = $middleware;
    }

    public function boot()
    {
        // Load all configuration files
        $this->config->load();

        // Preload all application routes
        $this->router->loadRoutes();

        // Run initial Routing setup tasks
        $this->router->setup();

        // Preload all applications Controllers
        $this->controller->loadAllControllers();

        $this->middleware->loadAllMiddleware();

        // Create a new Swoole HTTP server and set server IP and listening port
        $this->server = new SwooleHTTPServer(
            $this->config->get("main.serverIP"),
            $this->config->get("main.serverPort"),
            SWOOLE_PROCESS
        );

        $this->server->set([
            'worker_num' => swoole_cpu_num(),
            ]);
    }

    public function registerReactors()
    {
        $this->server->on("WorkerStart", function($server, $workerId)
        {

        });

        $this->server->on("start", function($server)
        {
            echo "\n";

            echo "######################################################################\n";
            echo " Swoole: " . swoole_version() . "\n";
            echo " PHP Version: " . phpversion() . "\n";
            echo " \e[36mPolyel HTTP server started at http://" .
                $this->config->get("main.serverIP") . ":" .
                $this->config->get("main.serverPort") . "\e[30m\e[0m";
            echo "\n######################################################################\n";
        });

        $this->server->on("request", function($request, $response)
        {
            $this->setDefaultResponseHeaders($response);

            $this->runDebug();

            $this->router->handle($request);
            $this->router->deliver($response);
        });
    }

    public function run()
    {
        $this->server->start();
    }

    private function runDebug()
    {
        Swoole::create(function ()
        {
            $debugFile = __DIR__ . "/../../../debug.php";

            if(file_exists($debugFile))
            {
                require $debugFile;
            }
        });
    }

    private function setDefaultResponseHeaders(&$response)
    {
        $response->header("Server", "Polyel/Swoole-HTTP-Server");
        $response->header("X-Powered-By", "Polyel-PHP");
        $response->header("Content-Type", "text/html; charset=utf-8");
    }
}