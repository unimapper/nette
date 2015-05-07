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

        if ($response === false) {
            throw new Exception\AdapterException(curl_getinfo($ch), curl_error($ch));
        }

        // Detect result errors
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200
            && curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201
        ) {
            throw new Exception\AdapterException(
                "API returned HTTP code " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "!",
                curl_getinfo($ch)
            );
        }

        curl_close($ch);

        return json_decode($response);
    }

    public function createDelete($resource)
    {

    }

    public function createDeleteOne($resource, $column, $primaryValue)
    {

    }

    public function createSelectOne($resource, $column, $primaryValue)
    {

    }

    public function createSelect($resource, array $selection = [], array $orderBy = [], $limit = 0, $offset = 0)
    {
        $url = $this->host . "/" . $resource;
        $query = new Adapter\Query($url);
        $query->parameters["limit"] = $limit;
        $query->parameters["offset"] = $offset;

        $query->resultCallback = function ($result, Adapter\Query $query) use ($resource) {

            if (empty($result)) {
                return false;
            }
            return $result;
        };

        return $query;
    }

    public function createCount($resource)
    {

    }

    public function createModifyManyToMany(Association\ManyToMany $association, $primaryValue, array $refKeys, $action = self::ASSOC_ADD)
    {

    }

    public function createInsert($resource, array $values)
    {

    }

    public function createUpdate($resource, array $values)
    {

    }

    public function createUpdateOne($resource, $column, $primaryValue, array $values)
    {

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

}