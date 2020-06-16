<?php

namespace Polyel\Http;

use Polyel\View\View;
use Polyel\Session\SessionManager;
use Polyel\Http\Utilities\ResponseUtilities;

class Response
{
    use CookieHandler;
    use ResponseUtilities;

    // The View service object
    private $view;

    // Holds the final response content for the request
    private $response;

    // Holds the headers which need to be set for the response before replying to client
    private $headers;

    // Holds the HTTP status code for the response
    private $httpStatusCode;

    // Used to store a redirect
    private $redirection;

    // Holds a filepath and if set, the file will be sent back to the client
    private $file;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    public function send($response)
    {
        // If a redirection is set, redirect to the destination
        if(isset($this->redirection))
        {
            // Call a redirect and end the response
            $response->redirect($this->redirection, $this->httpStatusCode);

            // Clear the redirection url so an infinite redirect does not happen
            $this->redirection = null;

            return;
        }

        // Queue any global headers for every response
        $this->queueGlobalHeaders();

        // Set all response headers before returning a response to client
        $this->setAllHeadersFor($response);

        $this->setCookiesFor($response);

        $response->status($this->httpStatusCode);

        /*
         * Main response is ended here!
         * Either send back a file or end the response with content.
         * A response cannot contain a file and content at the same time!
         */
        if(exists($this->file))
        {
            // Send back a file using a local file path and reset the file queue
            $response->sendfile($this->file);
            $this->file = null;
        }
        else
        {
            // End with content from the application
            $response->end($this->response);
        }
    }

    public function setStatusCode(int $code)
    {
        $this->httpStatusCode = $code;
    }

    private function setAllHeadersFor($response)
    {
        if(is_array($this->headers) && count($this->headers))
        {
            // All headers that were set during the request being handled...
            foreach($this->headers as $header => $value)
            {
                // Set headers for this request only
                $response->header($header, $value);
            }
        }

        // Reset headers so they don't show up on other/next requests
        $this->headers = [];
    }

    private function setCookiesFor($response)
    {
        // Check for any queued cookies waiting to be sent out
        if(exists($this->queuedCookies))
        {
            foreach($this->queuedCookies as $cookie)
            {
                // Calling the Swoole cookie function to set them
                $response->cookie(...$cookie);
            }
        }

        // Reset the cookie queue
        $this->queuedCookies = [];
    }

    public function queueHeader($headerName, $headerValue)
    {
        // Queue headers, they will be set later just before sending the response to the client
        $this->headers[$headerName] = $headerValue;
    }

    public function redirect($url, $statusCode = 302)
    {
        // Setup a redirection happen when send() is called
        $this->redirection = $url;
        $this->httpStatusCode = $statusCode;
    }

    /*
     * Builds up the response to send back to the client, based on the response type
     * sent over to this build function. Supports a raw string, converts PHP arrays into JSON.
     */
    public function build($response)
    {
        // Make sure a response type is set
        if(exists($response))
        {
            // Send back a raw string response
            if(is_string($response) || is_numeric($response))
            {
                $this->response = $response;
                return;
            }

            // Convert a PHP array into a JSON formatted response for the client
            if(is_array($response))
            {
                $this->response = $this->convertArrayToJson($response);
                $this->queueHeader("Content-Type", "application/json");
                return;
            }

            if(is_object($response) && $response instanceof \Polyel\Http\ResponseBuilder)
            {
                $this->response = $this->construct($response);
                return;
            }

            if(is_object($response) && $response instanceof \Polyel\Http\RedirectBuilder)
            {
                if(exists($response->url))
                {
                    $this->redirect($response->url, $response->status);
                    return;
                }
            }
        }
    }

    private function construct($response)
    {
        // Set the status code from the ResponseBuilder instance
        $this->setStatusCode($response->status);

        // Set any headers requested by the ResponseBuilder instance
        if(is_array($response->headers) && count($response->headers) > 0)
        {
            foreach($response->headers as $name => $value)
            {
                // Will be set just before the response is sent
                $this->queueHeader($name, $value);
            }
        }

        if(exists($response->cookies))
        {
            // If cookies exist, queue them for the response
            $this->queueCookieForResponse($response->cookies);
        }

        if(exists($response->content))
        {
            // If the content is just a string, return the content to be sent back
            if(is_string($response->content) || is_numeric($response->content))
            {
                return $response->content;
            }

            // Automatically convert PHP arrays to JSON formatted responses
            if(is_array($response->content))
            {
                $this->queueHeader("Content-Type", "application/json");
                return $this->convertArrayToJson($response->content);
            }

            // Check if the content set for response is of type ViewBuilder
            if(is_object($response->content) && $response->content instanceof \Polyel\View\ViewBuilder)
            {
                // Return the rendered view
                return $this->view->render($response->content);
            }
        }

        // Detect if a file has been queued to be sent
        if(exists($response->file))
        {
            // A file has been queued for the response. Return null because no $content will be sent, only a file
            $this->file = $response->file;
            return null;
        }
    }
}