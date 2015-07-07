<?php

namespace UniMapper\Nette\Api;

use UniMapper\Association;
use UniMapper\Adapter\IQuery;
use UniMapper\Exception;

class Adapter extends \UniMapper\Adapter
{

    /** @var string */
    private $host;

    /** @var integer */
    private $sslVersion;

    public function __construct(array $config)
    {
        $this->host = $config["host"];
        $this->sslVersion = isset($config["ssl_version"]) ? (int) $config["ssl_version"] : null;
    }

    protected function send(
        $url,
        $method = Adapter\Query::GET,
        array $content = []
    ) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Set SSL version
        if ($this->sslVersion !== null) {
            curl_setopt($ch, CURLOPT_SSLVERSION, $this->sslVersion);
        }

        // Set content
        if ($content) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($content));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $response = curl_exec($ch);

        if (empty($response)) {
            throw new Exception\AdapterException(curl_getinfo($ch), curl_error($ch));
        }

        $response = json_decode($response);

        // Detect unexpected response
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200
            && curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201
            && !isset($response->api)
        ) {
            throw new Exception\AdapterException(
                "API returned an unexpected response with HTTP code "
                    . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "!",
                curl_getinfo($ch)
            );
        }

        curl_close($ch);

        return $response;
    }

    public function createDelete($resource)
    {
        $query = new Adapter\Query(
            $this->host . "/" . $resource,
            Adapter\Query::DELETE
        );
        $query->resultCallback = function ($result, Adapter\Query $query) {

            if (empty($result->body)) {
                return false;
            }
            return $result;
        };

        return $query;
    }

    public function createDeleteOne($resource, $column, $primaryValue)
    {
        $query = new Adapter\Query(
            $this->host . "/" . $resource . "/" . $primaryValue,
            Adapter\Query::DELETE
        );

        $query->resultCallback = function ($result, Adapter\Query $query) {

            if ((isset($result->code) && $result->code === 404)
                || empty($result->body)
            ) {
                return false;
            }

            return $result->body;
        };

        return $query;
    }

    public function createSelectOne($resource, $column, $primaryValue)
    {
        $query = new Adapter\Query(
            $this->host . "/" . $resource . "/" . $primaryValue
        );

        $query->resultCallback = function ($result, Adapter\Query $query) {

            if ((isset($result->code) && $result->code === 404)
                || empty($result->body)
            ) {
                return false;
            }

            return $result->body;
        };

        return $query;
    }

    public function createSelect($resource, array $selection = [], array $orderBy = [], $limit = 0, $offset = 0)
    {
        $url = $this->host . "/" . $resource;
        $query = new Adapter\Query($url);
        $query->parameters["limit"] = $limit;
        $query->parameters["offset"] = $offset;

        $query->resultCallback = function ($result, Adapter\Query $query) {

            if (empty($result->body)) {
                return false;
            }
            return $result;
        };

        return $query;
    }

    public function createCount($resource)
    {
        $query = new Adapter\Query($this->host . "/" . $resource);
        $query->parameters["count"] = true;
        $query->resultCallback = function ($result, Adapter\Query $query) {
            return $result->body;
        };

        return $query;
    }

    public function createModifyManyToMany(Association\ManyToMany $association, $primaryValue, array $refKeys, $action = self::ASSOC_ADD)
    {
        throw new \Exception("Not implemented!");
    }

    public function createInsert($resource, array $values, $primaryName = null)
    {
        $query = new Adapter\Query(
            $this->host . "/" . $resource,
            Adapter\Query::POST
        );
        $query->data = $values;
        $query->resultCallback = function ($result, Adapter\Query $query) {

            if ($result->success === false) {
                throw new Exception\AdapterException(
                    json_encode($result->messages),
                    $query
                );
            }
            return $result->body;
        };

        return $query;
    }

    public function createUpdate($resource, array $values)
    {
        $query = new Adapter\Query(
            $this->host . "/" . $resource,
            Adapter\Query::PUT
        );
        $query->data = $values;
        $query->resultCallback = function ($result, Adapter\Query $query) {

            if ($result->success === false) {
                throw new Exception\AdapterException(
                    json_encode($result->messages),
                    $query
                );
            }
            return $result->body;
        };

        return $query;
    }

    public function createUpdateOne($resource, $column, $primaryValue, array $values)
    {
        $query = new Adapter\Query(
            $this->host . "/" . $resource . "/" . $primaryValue,
            Adapter\Query::PUT
        );
        $query->data = $values;
        $query->resultCallback = function ($result, Adapter\Query $query) {
            return $result->success;
        };

        return $query;
    }

    protected function onExecute(IQuery $query)
    {
        $result = $this->send($query->getRaw(), $query->method, $query->data);

        $callback = $query->resultCallback;
        if ($callback) {
            return $callback($result, $query);
        }

        return $result;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function query(
        $url,
        $method = Adapter\Query::GET,
        array $content = []
    ) {
        return $this->send($this->host . "/" . $url, $method, $content);
    }

}