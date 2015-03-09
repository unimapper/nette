<?php

namespace UniMapper\Nette\Api;

use Nette\Application\Responses\JsonResponse;
use Nette\Utils\Json;
use UniMapper\Reflection;
use UniMapper\NamingConvention as UNC;
use UniMapper\Nette\Api\RepositoryList;

abstract class Presenter extends \Nette\Application\UI\Presenter
{

    /** @var \UniMapper\Repository $repository */
    protected $repository;

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

    public function startup()
    {
        parent::startup();

        $this->resource = new Resource;

        $name = $this->getPresenterName();
        if (!isset($this->repositories[$name])) {
            $this->error("Repository '" . $name . "' not found!");
        }

        $this->repository = $this->repositories[$name];
        $this->data = (array) Json::decode($this->input->getData(), Json::FORCE_ARRAY);
    }

    public function actionGet($id = null, array $associate = [], $where = null)
    {
        if ($id) {

            // @todo catch unsuccessfull convert
            $primaryValue = Reflection\Loader::load($this->repository->getEntityName())
                ->getPrimaryProperty()
                ->convertValue($id);

            $entity = $this->repository->findOne($primaryValue, $associate);

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

                $this->resource->body = $this->repository->find(
                    $filter,
                    [],
                    $this->getLimit(),
                    $this->getOffset(),
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
        $inputEntity = $this->beforePost($this->data);

        if (!$inputEntity->getReflection()->hasPrimary()) {

            $this->resource->success = false;
            $this->resource->code = 405;
            $this->resource->messages[] = "Method is not allowed on entities without primary property!";
            return;
        }

        try {

            $resultEntity = $this->post($inputEntity);

            $this->resource->code = 201;
            $this->resource->success = true;
            $this->resource->link = $this->link("get", $resultEntity->{$inputEntity->getReflection()->getPrimaryProperty()->getName()});
            $this->resource->body = $resultEntity->toArray(true);
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

        $this->afterPost($resultEntity);
    }

    public function actionPut($id)
    {
        if (empty($id)) {

            $this->resource->success = false;
            $this->resource->code = 400;
            $this->resource->messages[] = "Primary value required!";
            return;
        }

        $inputEntity = $this->beforePut($this->data);

        if (!$inputEntity->getReflection()->hasPrimary()) {

            $this->resource->success = false;
            $this->resource->code = 405;
            $this->resource->messages[] = "Method is not allowed on entities without primary property!";
            return;
        }

        $primaryProperty = $inputEntity->getReflection()->getPrimaryProperty();
        $inputEntity->{$primaryProperty->getName()} = $primaryProperty->convertValue($id); // @todo catch unsuccessfull convert

        try {

            $resultEntity = $this->put($inputEntity);

            $this->resource->success = true;
            $this->resource->link = $this->link("get", $resultEntity->{$primaryProperty->getName()});
            $this->resource->body = $inputEntity->toArray(true);
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

        $this->afterPut($resultEntity);
    }

    public function actionDelete($id)
    {
        $this->beforeDelete();

        $entity = $this->_createEntity($this->repository->getEntityName());
        $entity->{$entity->getReflection()->getPrimaryProperty()->getName()} = $id;  // @todo catch unsuccessfull convert

        $this->resource->success = (bool) $this->delete($entity);

        $this->afterDelete($entity);
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

    protected function beforeDelete()
    {

    }

    /**
     * @todo catch unsuccessfull conversion
     *
     * @param array $data Input data
     *
     * @return \UniMapper\Entity
     */
    protected function beforePost(array $data)
    {
        return $this->_createEntity($this->repository->getEntityName(), $data);
    }

    /**
     * @todo catch unsuccessfull conversion
     *
     * @param array $data Input data
     *
     * @return \UniMapper\Entity
     */
    protected function beforePut(array $data)
    {
        return $this->_createEntity($this->repository->getEntityName(), $data);
    }

    protected function delete(\UniMapper\Entity $entity)
    {
        return $this->repository->destroy($entity);
    }

    protected function post(\UniMapper\Entity $entity)
    {
        return $this->repository->save($entity);
    }

    protected function put(\UniMapper\Entity $entity)
    {
        return $this->repository->save($entity);
    }

    protected function afterDelete(\UniMapper\Entity $entity)
    {

    }

    protected function afterPost(\UniMapper\Entity $entity)
    {

    }

    protected function afterPut(\UniMapper\Entity $entity)
    {

    }

    private function _createEntity($name, array $values = [])
    {
        $class = UNC::nameToClass($name, UNC::ENTITY_MASK);
        return new $class($values);
    }

}