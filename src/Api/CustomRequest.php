<?php

namespace UniMapper\Nette\Api;

use Eset\Utils\ArrayUtils;
use Nette\Http\Url;
use UniMapper\Exception;

class CustomRequest
{

    const GET = Adapter\Query::GET,
        PUT = Adapter\Query::PUT,
        POST = Adapter\Query::POST,
        DELETE = Adapter\Query::DELETE;

    /** @var string */
    private $resource;

    /** @var string */
    private $action;

    /** @var Adapter */
    private $adapter;

    /** @var array  */
    private $values = [];

    /** @var string  */
    private $method = self::GET;

    /**
     * @param Adapter $adapter Api adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @return string
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param string $resource
     */
    public function setResource($resource)
    {
        $this->resource = $resource;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $action Action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param array $values
     */
    public function setValues($values)
    {
        $this->values = $values;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * Send request
     *
     * @param string|null $resource Optional resource
     * @param string|null $action   Optional action
     * @param array|null  $values   Optional values
     * @param string|null $method   Optional method
     *
     * @return array
     */
    public function send($resource = null, $action = null, $values = null, $method = null)
    {
        if ($resource) {
            $this->setResource($resource);
        }

        if ($action) {
            $this->setAction($action);
        }

        if ($method) {
            $this->setMethod($method);
        }

        if ($values) {
            $this->setValues($values);
        }

        $url = new Url;
        $url->setPath($this->getResource());
        $url->setQuery(
            array_merge(['action' => $this->getAction()], $this->getValues())
        );

        $result = $this->adapter->query($url->getRelativeUrl(), $this->method);
        if ($result->success === false) {
            throw new Exception\AdapterException(
                json_encode($result->messages),
                (string) $url
            );
        }

        if (!isset($result->body)) {
            throw new Exception\AdapterException(
                'Body not provided in response!',
                (string) $url
            );
        }

        return ArrayUtils::objectToArray($result->body);
    }

}