<?php

namespace Polyel\Storage;

class Storage
{
    // Holds all the configured drives for the Storage System
    private static $drives;

    // All the supported drivers that a drive can use
    private array $supportedDrivers = [
        'local',
    ];

    public function __construct()
    {

    }

    public static function setup()
    {
        self::$drives = config('filesystem.drives');
    }

    // The drive function is the gateway to all the configured storage drives
    public function drive($drive)
    {
        if($this->driveExists($drive))
        {
            $drive = self::$drives[$drive];

            if(exists($drive['driver']) && $this->storageDriverIsValid($drive['driver']))
            {
                return $this->connectToDrive($drive);
            }
        }

        // Return null when no storage drive is found
        return null;
    }

    private function driveExists($drive)
    {
        return array_key_exists($drive, self::$drives);
    }

    private function storageDriverIsValid($driver)
    {
        return array_key_exists($driver, $this->supportedDrivers);
    }

    private function connectToDrive($driveConfig)
    {
        switch($driveConfig['driver'])
        {
            case 'local':

                return new LocalStorageDriver($driveConfig['root']);

            break;
        }
    }
}