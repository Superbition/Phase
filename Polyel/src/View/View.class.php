<?php

namespace Polyel\View;

use Polyel\View\Element\Element;
use Polyel\Storage\Facade\Storage;

class View
{
    use ViewTools, DisplaysErrors, DisplaysFlashMessages;

    private const RESOURCE_DIR = ROOT_DIR . "/app/resources";

    // Holds the template name and eventually the file path
    private $resource;

    // Holds the found tags from a template
    private $resourceTags;

    // Holds the extending view content if a main view is extending
    private $extendingView;

    // Holds the extending view tags from the template
    private $extendingViewTags;

    // Holds the template data to be added
    private $data;

    // Holds the Element service class
    private $element;

    // The request HttpKernel
    private $HttpKernel;

    public function __construct(Element $element)
    {
        $this->element = $element;
    }

    public function setHttpKernel($HttpKernel)
    {
        $this->HttpKernel = $HttpKernel;
    }

    // The main function used to perform the view rendering and data to template exchange
    public function render(ViewBuilder $resource)
    {
        // Give the class access to both resource and data
        $this->resource = $resource->resource;
        $this->data = $resource->data;

        // If the resource file was found and is valid...
        if($resource->isValid())
        {
            // Set the template type
            $type = $resource->type;

            /*
             * Format the resource file path and get the resource from the local disk
             * NOTE: The $this->resource file/path name is already converted from dot syntax in ViewBuilder
             */
            $viewLocation = "/${type}s/" . $this->resource . ".${type}.html";
            $this->resource = Storage::access('local', static::RESOURCE_DIR)->read($viewLocation);

            // Get all the tags from the resource template
            $this->resourceTags = $this->getStringsBetween($this->resource, "{{", "}}");

            // Process @include() calls for the main request view
            $this->processIncludes($this->resource, $this->resourceTags);

            if(exists($this->data))
            {
                // If data has been passed in, inject that into the resource
                $this->injectDataToView($this->resource, $this->resourceTags, $this->data);
            }

            // Check if an extending view has been set
            if(exists($resource->extendingView))
            {
                // Using the main view, inject it into the content tag from the view to extend...
                $this->resource = $this->extendView($resource->extendingView, $resource->extendingViewData, $this->resource);
            }

            // Search for any CSS file includes and insert any CSS links where the @css placeholder is
            $cssTags = $this->getStringsBetween($this->resource, "{{ @includeCSS(", ") }}");
            $this->processCssIncludes($cssTags);

            // Search for any JS file includes and insert any JS links where the @js placeholder is
            $jsTags = $this->getStringsBetween($this->resource, "{{ @includeJS(", ") }}");
            $this->processJsIncludes($jsTags);

            $elementTags = $this->getStringsBetween($this->resource, "{{ @addElement(", ") }}");
            $this->element->processElementsFor($this->resource, $elementTags, $this->HttpKernel);

            $this->addOldRequestData();

            // Process all error tags within the resource and inject any rendered errors...
            $this->processErrors();

            $this->processFlashMessages();

            $this->addCsrfTokens();

            return $this->resource;
        }

        // Return because the resource was not found or is invalid from the ViewBuilder
        return null;
    }

    private function processIncludes(&$resourceContent, &$resourceTags)
    {
        // Sort all the @includes into a single string for processing
        $includesAsString = '';
        foreach($resourceTags as $key => $tag)
        {
            // Grab only the @include tags
            if(strpos($tag, '@include') !== false)
            {
                // Sort the @include tags into a single string for easier processing
                $includesAsString .= ' ' . $tag;

                // Delete the @include tag because it will be processed here
                unset($resourceTags[$key]);
            }
        }

        if(!exists($includesAsString))
        {
            // Return early if there are no includes to process
            return;
        }

        // Reset the array index for resource tags
        $resourceTags = array_values($resourceTags);

        // Based on the string of includes, grab all the values from the include call between the ()
        $includes = $this->getStringsBetween($includesAsString, '@include(', ')');

        // Process each include value and inject any resource into the main template
        foreach($includes as $include)
        {
            // Split based on the resource name/path and the type, for example: header:view or common.header:view
            list($resourceName, $includeType) = explode(":", $include);

            // Using the dot notation convert dots to directory slashes in the resource name
            $resourceFileNamePath = str_replace('.', '/', $resourceName);

            // Build the include file location to check...
            $includeLocation = "/${includeType}s/" . $resourceFileNamePath . '.view.html';

            // Check if the include exists on local disk
            if(file_exists($includeLocation))
            {
                // Get the resource content include from file and inject it into the main template
                $includeContent = Storage::access('local', static::RESOURCE_DIR)->read($includeLocation);
                $resourceContent = str_replace("{{ @include($resourceName:$includeType) }}", $includeContent, $resourceContent);
            }
            else
            {
                // Else if no include resource is found, remove the include tags and replace them with nothing
                $resourceContent = str_replace("{{ @include($resourceName:$includeType) }}", '', $resourceContent);
            }
        }
    }

    private function processCssIncludes($cssTags)
    {
        // Foreach CSS include, build up the link, collect it and then insert them into @css
        $cssLinks = '';
        foreach($cssTags as $cssFileName)
        {
            // Convert dot syntax and build the full file path
            $cssFilePath = str_replace(".", "/", $cssFileName);
            $cssLocation = config('view.cssDirectory') . '/' . $cssFilePath . '.css';

            // Only include CSS link if the file actually exists
            if(file_exists(ROOT_DIR . '/public' . $cssLocation))
            {
                $cssLinks .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"${cssLocation}\">";
            }

            $this->resource = str_replace("{{ @includeCSS($cssFileName) }}", '', $this->resource);
        }

        $this->resource = str_replace("{{ @CSS }}", $cssLinks, $this->resource);
    }

    private function processJsIncludes($jsTags)
    {
        // Foreach JS include, build up the link, collect it and then insert them into @js
        $jsLinks = '';
        foreach($jsTags as $jsFileName)
        {
            // Convert dot syntax and build the full file path
            $jsFilePath = str_replace(".", "/", $jsFileName);
            $jsLocation = config('view.jsDirectory') . '/' . $jsFilePath . '.js';

            // Only include JS link if the file actually exists
            if(file_exists(ROOT_DIR . '/public' . $jsLocation))
            {
                $jsLinks .= "<script src=\"${jsLocation}\"></script>";
            }

            $this->resource = str_replace("{{ @includeJS($jsFileName) }}", '', $this->resource);
        }

        $this->resource = str_replace("{{ @JS }}", $jsLinks, $this->resource);
    }

    private function addOldRequestData()
    {
        if($this->HttpKernel->request->type !== 'api')
        {
            $oldRequestDataTags = $this->getStringsBetween($this->resource, "{{ @old(", ") }}");

            foreach($oldRequestDataTags as $oldRequestDataTag)
            {
                // Get the parameters from each old data request, set to null if missing
                [$oldField, $defaultData] = array_pad(
                    explode(',', $oldRequestDataTag, 2), 2, null);

                $oldFieldPath = $oldField;

                // Check if the old field is part of a group
                if(strpos($oldFieldPath, ':') !== false)
                {
                    // Convert the group name to work with dot syntax to get the filed from the session later
                    $oldFieldPath = str_replace(':', '.', $oldField);
                }

                $oldData = $this->HttpKernel->session->get("old.$oldFieldPath");

                // Only when old data exists in the session, we inject old data
                if(exists($oldData))
                {
                    // Don't output if old data is an array and not a string
                    if(is_array($oldData))
                    {
                        $oldData = '';
                    }

                    $defaultData = exists($defaultData) ? ",$defaultData" : '';

                    $this->resource = str_replace("{{ @old($oldField$defaultData) }}", $oldData, $this->resource);

                    continue;
                }

                // If a default is set and no old data is found, we inject the default value
                if(exists($defaultData))
                {
                    $this->resource = str_replace("{{ @old($oldField,$defaultData) }}", trim($defaultData), $this->resource);

                    continue;
                }

                // Remove the tag when no old data or default value is set
                $this->resource = str_replace("{{ @old($oldField) }}", '', $this->resource);
            }

            // Remove any old data that may be left, so it isn't stored for any longer than needed
            $this->HttpKernel->session->remove('old');
        }
    }

    private function injectDataToView(&$resourceContent, &$resourceTags, $data)
    {
        if(!exists($resourceTags))
        {
            // Return early if no tags are found in the resource
            return;
        }

        foreach($data as $key => $value)
        {
            if(in_array($key, $resourceTags, true))
            {
                // Automatically filter data tags for XSS prevention
                $xssEscapedData = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $resourceContent = str_replace("{{ $key }}", $xssEscapedData, $resourceContent);
            }
            else if(in_array("!$key!", $resourceTags, true))
            {
                // Else raw input has been requested by using {{ !data! }}
                $resourceContent = str_replace("{{ !$key! }}", $value, $resourceContent);
            }
        }
    }

    private function addCsrfTokens()
    {
        // CSRF Tokens are not needed when handling a API request...
        if($this->HttpKernel->request->type !== 'api')
        {
            // Only inject CSRF tokens during a web request
            $csrfToken = '<input type="hidden" name="csrf_token" value="' . $this->HttpKernel->session->get('CSRF-TOKEN') . '">';
            $this->resource = str_replace("{{ @csrfToken }}", $csrfToken, $this->resource);
        }
        else
        {
            // Remove the CSRF Token tag from the HTML view if no token is needed or not found
            $this->resource = str_replace("{{ @csrfToken }}", '', $this->resource);
        }
    }

    private function extendView($resourceToExtend, $extendingViewData, $resourceContent)
    {
        // Sort the view name and type into single variables
        list($extViewName, $extType) = explode(":", $resourceToExtend);

        // Convert dot syntax into file slashes
        $extViewName = str_replace(".", "/", $extViewName);

        // Build up the extending view file path and grab the content from disk
        $extViewFilePath = "/${extType}s/" . $extViewName . ".$extType.html";
        $this->extendingView = Storage::access('local', static::RESOURCE_DIR)->read($extViewFilePath);

        // Collect any extending view tags
        $this->extendingViewTags = $this->getStringsBetween($this->extendingView, "{{", "}}");

        // First, process any includes from the extending view
        $this->processIncludes($this->extendingView, $this->extendingViewTags);

        // If data exists, process and inject it into the extending view
        if(exists($extendingViewData))
        {
            $this->injectDataToView($this->extendingView, $this->extendingViewTags, $extendingViewData);
        }

        // Finally, replace the content tag in the extending view with the content from the main view and return it
        return str_replace("{{ @content }}", $resourceContent, $this->extendingView);
    }

    public static function exists($viewNameAndType): bool
    {
        // Sort the view name and type as they are stored like viewName:viewType
        list($viewName, $viewType) = explode(":", $viewNameAndType);

        // Convert dot syntax to file slashes, build a full file path to the view, using the type as well
        $viewName = str_replace(".", "/", $viewName);
        $viewFilePath = static::RESOURCE_DIR . "/${viewType}s/" . $viewName . ".$viewType.html";

        if(file_exists($viewFilePath))
        {
            return true;
        }

        return false;
    }
}