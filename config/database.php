<?php

return [

    "default" => env("Database.Default_DB_Name", "DefaultDatabase"),

    "connections" => [

        "mysql" => [

            "DefaultDatabase" => [

                "driver" => "mysql",
                "database" => env("Main_Database.DATABASE", "polyel1"),
                "host" => env("Main_Database.HOST", "127.0.0.1"),
                "port" => env("Main_Database.PORT", "3306"),
                "username" => env("Main_Database.USERNAME", "polyel"),
                "password" => env("Main_Database.PASSWORD", ""),

            ],

            "SecondDatabase" => [

                "driver" => "mysql",
                "database" => env("Second_Database.DATABASE", "polyel2"),
                "host" => env("Second_Database.HOST", "127.0.0.1"),
                "port" => env("Second_Database.PORT", "3306"),
                "username" => env("Second_Database.USERNAME", "polyel"),
                "password" => env("Second_Database.PASSWORD", ""),

            ],

        ],

    ],

];