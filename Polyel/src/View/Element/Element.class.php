<?php

namespace Polyel\View\Element;

use Polyel;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class Element
{
    private $elementClassDir = ROOT_DIR . "/app/View/Elements";

    public function __construct()
    {

    }

    public function processElementsFor(&$mainResource, $elementTags)
    {
        if(exists($elementTags))
        {
            foreach($elementTags as $element)
            {
                $elementClass = Polyel::call("App\View\Elements\\" . $element);

                $renderedElement = $elementClass->build();

                $mainResource = str_replace("{{ @addElement($element) }}", $renderedElement, $mainResource);
            }
        }
    }

    public function loadClassElements()
    {
        $elementClassDir = new RecursiveDirectoryIterator($this->elementClassDir);
        $pathIterator = new RecursiveIteratorIterator($elementClassDir);

        foreach($pathIterator as $elementClass)
        {
            $elementClassFilePath = $elementClass->getPathname();

            if(preg_match('/^.+\.php$/i', $elementClassFilePath))
            {
                require_once $elementClassFilePath;

                $listOfDefinedClasses = get_declared_classes();
                $definedClass = explode("\\", end($listOfDefinedClasses));
                $definedClass = end($definedClass);

                Polyel::resolveClass("App\View\Elements\\" . $definedClass);
            }
        }
    }
}