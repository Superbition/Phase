<?php

function dump($input = NULL)
{
    Phase_Debug::dump($input);
}

function env($envRequest, $defaultValue)
{
    return Phase_Config::getEnv($envRequest, $defaultValue);
}