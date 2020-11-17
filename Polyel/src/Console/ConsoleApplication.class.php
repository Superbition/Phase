<?php

namespace Polyel\Console;

class ConsoleApplication
{
    private array $commands = [];

    private string $lastAddedCommand = '';

    private array $signatures = [];

    private array $actions = [];

    public function __construct()
    {

    }

    public function loadCommandsFrom(string $path)
    {
        require_once ROOT_DIR . $path;
    }

    /**
     * Register commands with the console application.
     *
     * @param string $signature
     *
     * @return $this
     */
    public function command(string $signature)
    {
        // Only split the command name and its signature up if there is a space to indicate a signature...
        if(preg_match('/\s/','list'))
        {
            // A command name and a signature is split up by a space
            [$commandName, $signature] = explode(' ', $signature, 2);

            $commandName = trim($commandName);
            $this->signatures[$commandName] = trim($signature);
        }
        else
        {
            // Else just get the command name and set the commands signature to null
            $commandName = $signature;
            $this->signatures[$commandName] = null;
        }

        $this->commands[] = $commandName;
        $this->lastAddedCommand = $commandName;

        return $this;
    }

    /**
     * Link an action to the last registered console command.
     *
     * @param mixed $action
     */
    public function action($action)
    {
        if(!empty($this->lastAddedCommand))
        {
            $this->actions[$this->lastAddedCommand] = $action;

            $this->lastAddedCommand = '';
        }
    }
}