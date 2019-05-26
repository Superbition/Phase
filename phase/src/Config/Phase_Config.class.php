<?php

class Phase_Config
{
    // Navigate to the app config directory.
    private static $configDir = __DIR__ . "/../../../config/";

    private static $main;
    private static $database;
    private static $path;
    private static $template;

    private static $configMap = [
      "main" => 0,
      "database" => 1,
      "path" => 2,
      "template" => 3,
    ];

    private static $envConfig;

    public static function load()
    {
        // Main env config file.
        self::$envConfig = parse_ini_file(self::$configDir . "/env/.env", true);

        // Non .env config files, standard .php files.
        self::$main = require self::$configDir . "main.php";
        self::$database = require self::$configDir . "database.php";
        self::$path = require self::$configDir . "path.php";
        self::$template = require self::$configDir . "template.php";
    }

    public static function reload()
    {
        self::load();
    }

    public static function get($configRequest)
    {
        $configRequest = explode(".", $configRequest);

        $configKey = self::$configMap[$configRequest[0]];

        switch($configKey)
        {
            case 0:

                return self::$main[$configRequest[1]];

                break;

            case 1:

                return self::$database[$configRequest[1]][$configRequest[2]];

                break;

            case 2:

                return self::$path[$configRequest[1]];

                break;

            case 3:

                return self::$template[$configRequest[1]];

                break;
        }
    }

    public static function env($envRequest, $defaultValue)
    {
        // Split the incoming env request in the format of: Category.Parameter
        $envRequest = explode(".", $envRequest);

        // Check to see if the requested parameter exists and return it if true.
        if(isset(self::$envConfig[$envRequest[0]][$envRequest[1]]) && !empty(self::$envConfig[$envRequest[0]][$envRequest[1]]))
        {
            return self::$envConfig[$envRequest[0]][$envRequest[1]];
        }
        else
        {
            // Else return the default argument passed in.
            return $defaultValue;
        }
    }
}