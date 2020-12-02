<?php

namespace Polyel\Console\Facade;

use Polyel;

/**
 * Class Command
 *
 * @method static command($signature)
 *
 */
class Console
{
    public static function __callStatic($method, $arguments)
    {
        return Polyel::call(Polyel\Console\ConsoleApplication::class)->$method(...$arguments);
    }
}