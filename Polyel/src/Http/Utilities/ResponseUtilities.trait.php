<?php

namespace Polyel\Http\Utilities;

trait ResponseUtilities
{
    private function convertArrayToJson($content)
    {
        $jsonOptions = JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT;
        return json_encode($content, $jsonOptions, 1024);
    }
}