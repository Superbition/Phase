<?php

namespace Polyel\Config;

class Config
{
    // Navigate to the config directory.
    private $configDir = ROOT_DIR . "/config/";

    private $main;
    private $database;
    private $path;
    private $template;

    private $envConfig;
    private $envPath = ROOT_DIR . "/env/.env";

    public function __construct()
    {

    }

    public function load()
    {
        if(file_exists($this->envPath))
        {
            // Main env config file.
            $this->envConfig = parse_ini_file($this->configDir . "/env/.env", true);
        }

        // Non .env config files, standard .php files.
        $this->main = require $this->configDir . "/main.php";
        $this->database = require $this->configDir . "/database.php";
        $this->path = require $this->configDir . "/path.php";
        $this->template = require $this->configDir . "/template.php";
    }

    public function reload()
    {
        $this->load();
    }

    public function get($configRequest)
    {
        $configRequest = explode(".", $configRequest);

        $configKey = $this->configMap[$configRequest[0]];

        switch($configKey)
        {
            case 0:

                return $this->main[$configRequest[1]];

                break;

            case 1:

                return $this->database[$configRequest[1]][$configRequest[2]];

                break;

            case 2:

                return $this->path[$configRequest[1]];

                break;

            case 3:

                return $this->template[$configRequest[1]];

                break;
        }
    }

    public function env($envRequest, $defaultValue)
    {
        // Split the incoming env request in the format of: Category.Parameter
        $envRequest = explode(".", $envRequest);

        // Check to see if the requested parameter exists and return it if true.
        if(isset($this->envConfig[$envRequest[0]][$envRequest[1]]) && !empty($this->envConfig[$envRequest[0]][$envRequest[1]]))
        {
            return $this->envConfig[$envRequest[0]][$envRequest[1]];
        }
        else
        {
            // Else return the default argument passed in.
            return $defaultValue;
        }
    }
}