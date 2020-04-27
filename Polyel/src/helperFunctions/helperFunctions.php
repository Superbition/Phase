<?php

function dump($input = NULL)
{
    Debug::dump($input);
}

function env($envRequest, $defaultValue)
{
    return Polyel::call(Polyel\Config\Config::class)->env($envRequest, $defaultValue);
}

function config($configRequest)
{
    return Polyel::call(Polyel\Config\Config::class)->get($configRequest);
}

function response($content = "", $status = 200)
{
    return new Polyel\Http\ResponseBuilder($content, $status);
}

function redirect($url, $status = 302)
{
    return new Polyel\Http\RedirectBuilder($url, $status);
}

function exists($var)
{
    if(isset($var) && !empty($var) || is_numeric($var))
    {
        return true;
    }

    return false;
}

/*
 * Packs a single dimensional array into one, multidimensional array and
 * sets the final value to the default of null or whatever is passed in as
 * a final value.
 */
function array_pack($array, $finalValue = null)
{
    $packedArray = [];
    foreach ($array as $key => $value)
    {
        /*
         * Delete the value we already have (from the foreach), we don't want to process
         * the value in the foreach loop again. This also stops an
         * infinite loop from happening.
         */
        unset($array[$key]);

        // Now process the next array element recursively...
        $element = array_pack($array, $finalValue);

        // When the element has no more array values to process...
        if(count($element) < 1)
        {
            // It's the last array to process so set the final outcome value.
            $packedArray[$value] = $finalValue;
        }
        else
        {
            // There are still more array elements to process, assign the array and continue...
            $packedArray[$value] = $element;
        }

        /*
         * Break on every loop because we only need to process one dimension.
         * If we did not break once every loop, we would start to access undefined
         * indexes as this function is for one dimensional arrays only.
         */
        break;
    }

    // Finally return the fully packed array, in one, one multidimensional format
    return $packedArray;
}