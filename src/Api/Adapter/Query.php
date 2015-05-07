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

    public function setConditions(array $conditions)
    {
        $this->parameters["where"] = $this->_formatConditions($conditions);
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

        return $this->url . "?" . http_build_query($parameters);
    }

    private function _formatConditions(array $conditions)
    {
        $result = [];

        foreach ($conditions as $condition) {

            if (is_array($condition[0])) {
                // Nested conditions

                list($nestedConditions, $joiner) = $condition;

                throw new \Exception("Nested conditions are not supported!");
            } else {
                // Simple condition

                list($name, $operator, $value, $joiner) = $condition;

                $operator = strtolower($operator);

                if (isset($operator[$name][$operator])) {
                    throw new \Exception("Duplicate condition found!");
                }

                if ($operator === "in" || $operator === "is") {
                    $operator = "=";
                }
                if ($operator === "not in" || $operator === "is not" || $operator === "!=") {
                    $operator = "!";
                }

                if (strtolower($joiner) === "or") {
                    $result[]["or"][][$name][$operator] = $value;
                } else {
                    $result[][$name][$operator] = $value;
                }
            }
        }

        return $result;
    }

}
