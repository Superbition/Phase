<?php

namespace Polyel\Http;

use Polyel\View\View;
use Polyel\Session\Session;
use Polyel\Container\Container;

class Kernel
{
    // The kernel service container for this HTTP request
    public $container;

    // Session service for the HttpKernel
    public $session;

    // The request service for the duration of this HTTP request
    public $request;

    // The response service for the duration of this HTTP request
    public $response;

    public function __construct(Session $session, Request $request, Response $response)
    {
        $this->session = $session;
        $this->request = $request;
        $this->response = $response;
    }

    public function setup()
    {
        $view = $this->container->get(View::class);
        $view->setHttpKernel($this);
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    public function setSessionID($sessionID)
    {
        $this->session->setID($sessionID);
    }
}