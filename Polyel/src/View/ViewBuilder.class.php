<?php

namespace Polyel\View;

class ViewBuilder
{
    public $resource;

    private $resourceDir = ROOT_DIR . '/app/resources';

    public $validResource = false;

    public $type;

    public $data;

    public function __construct($resource, $data)
    {
        $resourceAndType = explode(":", $resource);
        $this->resource = $resourceAndType[0];
        $this->type = $resourceAndType[1];

        if($this->type === 'view' && file_exists($this->resourceDir . '/views/' . $this->resource . '.view.html'))
        {
            $this->validResource = true;
        }
        else if($this->type === 'error' && file_exists($this->resourceDir . '/errors/' . $this->resource . '.error.html'))
        {
            $this->validResource = true;
        }

        if(exists($data) && is_array($data))
        {
            $this->data = $data;
        }
    }
}