<?php

namespace UniMapper\Nette\Api;

use Nette\Application\Responses\JsonResponse,
    Nette\Http\Response,
    Nette\Utils\Json,
    UniMapper\Nette\Api\RepositoryList;

abstract class Presenter extends \Nette\Application\UI\Presenter
{

    /** @var \UniMapper\Repository $repository */
    protected $repository;

    /** @var \UniMapper\Nette\Api\RepositoryList $repositories */
    private $repositories;

    /** @var \Nette\Http\Response $httpResponse */
    protected $httpResponse;

    /** @var integer $maxLimit */
    protected $maxLimit = 10;

    /** @var \UniMapper\Nette\Api\Resource $resource */
    protected $resource;

    /** @var \UniMapper\Nette\Api\Input $input */
    private $input;

    /** @var array $data Input data */
    protected $data;

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
     * Inject HTTP response
     *
     * @param \Nette\Http\Response $httpResponse
     */
    public function injectHttpResponse(Response $httpResponse)
    {
        $this->httpResponse = $httpResponse;
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
            $this->error(
                "Repository '" . $name . "' not found!",
                Response::S404_NOT_FOUND
            );
        }
        $this->repository = $this->repositories[$name];

        $this->data = Json::decode($this->input->getData(), Json::FORCE_ARRAY);
    }

    public function actionGet($id = null)
    {
        if ($id) {

            // @todo catch unsuccessfull convert
            $primaryValue = $this->repository->createEntity()
                ->getReflection()
                ->getPrimaryProperty()
                ->convertValue($id);

            $entity = $this->repository->findOne($primaryValue);

            if (!$entity) {
                $this->error("Resource not found!", Response::S404_NOT_FOUND);
            }

            $this->resource->body = $entity;
        } else {
            $this->resource->body = $this->repository->find([], [], $this->getLimit(), $this->getOffset());
        }
    }

    public function actionPost()
    {
        // @todo catch unsuccessfull convert
        $entity = $this->repository->createEntity($this->data);

        if (!$entity->getReflection()->hasPrimaryProperty()) {
            $this->error("Can not create record if entity has no primary property defined!", Response::S405_METHOD_NOT_ALLOWED);
        }

        // Prevent to primary value changes
        $primaryProperty = $entity->getReflection()->getPrimaryProperty();
        unset($entity->{$primaryProperty->getName()});

        // Perform save
        try {
            $this->repository->save($entity);
        } catch (\UniMapper\Exception\ValidatorException $exception) {

            // Validation failed
            $this->httpResponse->setCode(Response::S400_BAD_REQUEST);

            $this->resource->success = false;
            $this->resource->messages = $exception->getValidator()->getMessages();
            return;
        }

        // Success
        $this->httpResponse->setCode(201);

        $this->resource->success = true;
        $this->resource->link = $this->link("get", $entity->{$primaryProperty->getName()});
        $this->resource->body = $entity->toArray(true);
    }

    public function actionPut($id)
    {
        // @todo catch unsuccessfull convert
        $entity = $this->repository->createEntity($this->data);

        if (!$entity->getReflection()->hasPrimaryProperty()) {
            $this->error("Can not update record if entity has no primary property defined!", Response::S405_METHOD_NOT_ALLOWED);
        }
        $primaryProperty = $entity->getReflection()->getPrimaryProperty();
        $entity->{$primaryProperty->getName()} = $primaryProperty->convertValue($id);
        $this->repository->save($entity);

        $this->resource->success = true;
        $this->resource->link = $this->link("get", $entity->{$primaryProperty->getName()});
        $this->resource->body = $entity->toArray(true);
    }

    public function actionDelete($id)
    {
        $entity = $this->repository->createEntity();
        $primaryProperty = $entity->getReflection()->getPrimaryProperty();

        // @todo catch unsuccessfull convert
        $entity->import([$primaryProperty->getName() => $id]);

        $this->repository->delete($entity);

        $this->resource->success = true;
    }

    public function beforeRender()
    {
        parent::beforeRender();
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

}
