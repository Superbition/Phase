<?php

namespace Polyel\View;

use Exception;

class ViewBuilder
{
    // The template name, which is also the location and type
    public $resource;

    private $resourceDir = ROOT_DIR . '/app/resources';

    // Used to set if the view requested is valid
    private $valid = false;

    // The template type
    public $type;

    // The data which needs to be exchanged with the template
    public $data;

    public function __construct($resource, $data)
    {
        // Get the type from the resource name and set the name and type to the class
        $resourceAndType = explode(":", $resource);

        try
        {
            if(!array_key_exists(1, $resourceAndType))
            {
                throw new Exception("\n \e[41mResource type not set when using view(), you need to set a type like :view or :error\e[0m\n");
            }

            $this->resource = $resourceAndType[0];
            $this->type = $resourceAndType[1];
        }
        catch(Exception $e)
        {
            echo $e->getMessage();
        }

        // Using the dot notation convert dots to directory slashes in the resource name
        $this->resource = str_replace(".", "/", $this->resource);

        /*
         * The template is either a view or an error.
         * Work out based on the type if the resource is a view or and error and check if they exist on file.
         */
        if($this->type === 'view' && file_exists($this->resourceDir . '/views/' . $this->resource . '.view.html'))
        {
            $this->valid = true;
        }
        else if($this->type === 'error' && file_exists($this->resourceDir . '/errors/' . $this->resource . '.error.html'))
        {
            $this->valid = true;
        }

        // If data is passed and not empty and is of type array
        if(is_array($data) && exists($data))
        {
            $this->data = $data;
        }
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}