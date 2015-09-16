<?php

namespace UniMapper\Nette\Api\Adapter;

class Query implements \UniMapper\Adapter\IQuery
{

    const GET = "GET",
          PUT = "PUT",
          POST = "POST",
          DELETE = "DELETE";

    public $data = [];
    public $url;
    public $method;
    public $parameters = [];
    public $resultCallback;

    public function __construct($url, $method = self::GET)
    {
        $this->url = $url;
        $this->method = $method;
    }

    public function setFilter(array $filter)
    {
        $this->parameters["where"] = $filter;
    }

    public function setAssociations(array $associations)
    {
        foreach ($associations as $association) {
            $this->parameters["associate"][] = $association->getPropertyName();
        }
    }

    public function getRaw()
    {
        $parameters = $this->parameters;
        if (isset($parameters["where"])) {
            $parameters["where"] = json_encode($this->parameters["where"]);
        }

        if ($parameters) {
            return $this->url . "?" . http_build_query($parameters);
        }
        return $this->url;
    }

}
