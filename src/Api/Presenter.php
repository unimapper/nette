<?php

namespace UniMapper\Nette\Api;

use Nette\Application\Responses\JsonResponse;
use Nette\Utils\Json;
use UniMapper\Entity\Reflection;
use UniMapper\Nette\Api\RepositoryList;

abstract class Presenter extends \Nette\Application\UI\Presenter
{

    /** @var \UniMapper\Nette\Api\RepositoryList $repositories */
    private $repositories;

    /** @var integer $maxLimit */
    protected $maxLimit = 10;

    /** @var \UniMapper\Nette\Api\Resource $resource */
    protected $resource;

    /** @var \UniMapper\Nette\Api\Input $input */
    private $input;

    /** @var array $data Input data */
    protected $data = [];

    /**
     * Inject repositories
     *
     * @param \UniMapper\Nette\Api\RepositoryList $repositories
     */
    public function injectRepositories(RepositoryList $repositories)
    {
        $this->repositories = $repositories;
    }

    /**
     * Inject input
     *
     * @param \UniMapper\Nette\Api\Input $input
     */
    public function injectInput(Input $input)
    {
        $this->input = $input;
    }

    protected function getRepository()
    {
        $name = $this->getPresenterName();
        if (!isset($this->repositories[$name])) {
            $this->error("Repository '" . $name . "' not found!");
        }

        return $this->repositories[$name];
    }

    protected function getEntityReflection()
    {
        return Reflection::load($this->getRepository()->getEntityName());
    }

    public function startup()
    {
        parent::startup();
        $this->resource = new Resource;
        $this->data = (array) Json::decode($this->input->getData(), Json::FORCE_ARRAY);
    }

    public function actionGet($id = null, $associate = null, $where = null, $count = false)
    {
        if ($associate) {
            if (is_string($associate)) {
                $associate = explode(',', $associate);
            }
        } else {
            $associate = [];
        }

        if ($id) {

            $reflection = $this->getEntityReflection();
            if (!$reflection->hasPrimary()) {

                $this->resource->success = false;
                $this->resource->code = 405;
                $this->resource->messages[] = "Method is not allowed on entities without primary property!";
                return;
            }

            $entity = $this->getOne(
                $reflection->getPrimaryProperty()->convertValue($id),
                $associate
            );

            if (!$entity) {

                $this->resource->messages[] = "Record not found!";
                $this->resource->code = 404;
                return;
            }

            $this->resource->body = $entity;
        } else {

            if ($where) {

                try {
                    $filter = Json::decode($where, Json::FORCE_ARRAY);
                } catch (\Nette\Utils\JsonException $e) {

                    $this->resource->messages[] = "Invalid where parameter. Must be a valid JSON but '" . $where . "' given!";
                    $this->resource->code = 400;
                    return;
                }
            } else {
                $filter = [];
            }

            try {

                $this->resource->body = $count ? $this->count($filter) : $this->get(
                    $filter,
                    $associate
                );
            } catch (\UniMapper\Exception\RepositoryException $e) {

                $this->resource->messages[] = $e->getMessage();
                $this->resource->code = 400;
                return;
            }
        }
    }

    public function actionPost()
    {
        $reflection = $this->getEntityReflection();

        if (!$reflection->hasPrimary()) {

            $this->resource->success = false;
            $this->resource->code = 405;
            $this->resource->messages[] = "Method is not allowed on entities without primary property!";
            return;
        }

        $inputEntity = $reflection->createEntity($this->data); // @todo catch unsuccessfull conversion

        try {

            $resultEntity = $this->post($inputEntity);

            $this->resource->code = 201;
            $this->resource->success = true;
            $this->resource->link = $this->link("get", $resultEntity->{$reflection->getPrimaryProperty()->getName()});
            $this->resource->body = $resultEntity->{$reflection->getPrimaryProperty()->getName()};
        } catch (\UniMapper\Exception $e) {

            if ($e instanceof \UniMapper\Exception\ValidatorException) {
                $this->resource->messages = $e->getValidator()->getMessages();
            } elseif ($e instanceof \UniMapper\Exception\RepositoryException) {
                $this->resource->messages[] = $e->getMessage();
            } else {
                throw $e;
            }

            $this->resource->code = 400;
            $this->resource->success = false;
            return;
        }

        return $resultEntity;
    }

    public function actionPut($id = null, $where = null)
    {
        $reflection = $this->getEntityReflection();

        $entity = $reflection->createEntity($this->data); // @todo catch unsuccessfull convert

        if ($id) {

            if (!$reflection->hasPrimary()) {

                $this->resource->success = false;
                $this->resource->code = 405;
                $this->resource->messages[] = "Method is not allowed on entities without primary property!";
                return;
            }

            $entity->{$reflection->getPrimaryProperty()->getName()} = $reflection->getPrimaryProperty()->convertValue($id); // @todo catch unsuccessfull convert

            try {
                $this->putOne($entity);
            } catch (\UniMapper\Exception $e) {

                if ($e instanceof \UniMapper\Exception\ValidatorException) {
                    $this->resource->messages = $e->getValidator()->getMessages();
                } elseif ($e instanceof \UniMapper\Exception\RepositoryException) {
                    $this->resource->messages[] = $e->getMessage();
                } else {
                    throw $e;
                }

                $this->resource->code = 400;
                $this->resource->success = false;
                return;
            }
        } else {

            if ($where) {

                try {
                    $filter = Json::decode($where, Json::FORCE_ARRAY);
                } catch (\Nette\Utils\JsonException $e) {

                    $this->resource->messages[] = "Invalid where parameter. Must be a valid JSON but '" . $where . "' given!";
                    $this->resource->code = 400;
                    return;
                }
            } else {
                $filter = [];
            }

            $this->resource->body = $this->put($entity, $filter);
        }

        $this->resource->success = true;
    }

    public function actionDelete($id = null, $where = null)
    {
        if ($id) {
            // Delete one

            $reflection = $this->getEntityReflection();
            if (!$reflection->hasPrimary()) {

                $this->resource->success = false;
                $this->resource->code = 405;
                $this->resource->messages[] = "Method is not allowed on entities without primary property!";
                return;
            }
            $this->resource->success = (bool) $this->deleteOne(
                $reflection->createEntity(
                    [$reflection->getPrimaryProperty()->getName() => $id]
                )
            );
        } else {
            // Delete

            if ($where) {

                try {
                    $filter = Json::decode($where, Json::FORCE_ARRAY);
                } catch (\Nette\Utils\JsonException $e) {

                    $this->resource->messages[] = "Invalid where parameter. Must be a valid JSON but '" . $where . "' given!";
                    $this->resource->code = 400;
                    return;
                }
            } else {
                $filter = [];
            }

            $this->resource->body = $this->delete($filter);
        }
    }

    public function beforeRender()
    {
        parent::beforeRender();
        if ($this->resource->code) {
            $this->getHttpResponse()->setCode($this->resource->code);
        }
        $this->sendJsonData($this->resource);
    }

    /**
     * Back compatibility with 2.0
     *
     * @param mixed $data
     */
    public function sendJsonData($data)
    {
        $this->sendResponse(new JsonResponse($data));
    }

    protected function getPresenterName()
    {
        return explode(":", $this->getName())[1];
    }

    protected function getLimit()
    {
        $limit = (int) $this->getParameter("limit");
        if ($limit > $this->maxLimit || $limit < 1) {
            $limit = $this->maxLimit;
        }
        return $limit;
    }

    protected function getOffset()
    {
        return (int) $this->getParameter("offset");
    }

    protected function deleteOne(\UniMapper\Entity $entity)
    {
        return $this->getRepository()->destroy($entity);
    }

    protected function delete(array $where = [])
    {
        return $this->getRepository()->destroyBy($where);
    }

    protected function post(\UniMapper\Entity $entity)
    {
        return $this->getRepository()->save($entity);
    }

    protected function put(\UniMapper\Entity $entity, array $filter = [])
    {
        return $this->getRepository()->updateBy($entity, $filter);
    }

    protected function putOne(\UniMapper\Entity $entity)
    {
        return $this->getRepository()->save($entity);
    }

    protected function count(array $filter = [])
    {
        return $this->getRepository()->count($filter);
    }

    protected function get(array $filter = [], array $associate = [])
    {
        return $this->getRepository()->find(
            $filter,
            [],
            $this->getLimit(),
            $this->getOffset(),
            $associate
        );
    }

    protected function getOne($primary, array $associate = [])
    {
        return $this->getRepository()->findOne($primary, $associate);
    }

}