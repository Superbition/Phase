<?php

/*
 * An array to hold all the core services which need to be resolved into the
 * container. The fully qualified namespace is needed to resolve a class.
 *
 * This service list is mainly used to auto load classes into the container
 * that are not directly defined inside a constructor, meaning they won't get
 * resolved for use.
 */
$coreServices = [

    Polyel\Config\Config::class,
    Polyel\Controller\Controller::class,
    Polyel\Debug\Debug::class,
    Polyel\Http\Server::class,
    Polyel\Http\Middleware\Middleware::class,
    Polyel\Router\Router::class,
    Polyel\Storage\Storage::class,
    Polyel\Database\Database::class,
    Polyel\View\View::class,
    Polyel\Encryption\EncryptionManager::class,
    Polyel\Hashing\HashManager::class,

];