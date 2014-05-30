<?php

namespace UniMapper\Nette\Api;

use Nette\Application\Responses\JsonResponse,
    Nette\Http\Request,
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
    private $httpResponse;

    /** @var \UniMapper\Nette\Api\Input $input */
    private $input;

    /** @var integer $maxLimit */
    protected $maxLimit = 10;

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

    /**
     * Inject HTTP response
     *
     * @param \Nette\Http\Response $httpResponse
     */
    public function injectHttpResponse(Response $httpResponse)
    {
        $this->httpResponse = $httpResponse;
    }

    public function startup()
    {
        parent::startup();
        $name = $this->getPresenterName();
        if (!isset($this->repositories[$name])) {
            $this->error(
                "Repository '" . $name . "' not found!",
                Response::S404_NOT_FOUND
            );
        }
        $this->repository = $this->repositories[$name];
    }

    public function actionDefault($id = null)
    {
        $method = $this->getRequest()->getMethod();
        if ($method === Request::GET && !$id) {
            $result = $this->find(
                $this->getParameter("limit"),
                $this->getParameter("offset")
            );
        } elseif ($method === Request::GET && $id) {
            $result = $this->findOne($id);
        } elseif ($method === Request::POST && !$id) {
            $result = $this->create($this->getJsonData());
        } elseif ($method === Request::PUT && $id) {
            $result = $this->update($id, $this->getJsonData());
        } elseif ($method === Request::DELETE && $id) {
            $result = $this->destroy($id);
        } else {
            $this->error("Invalid request", Response::S400_BAD_REQUEST);
        }

        $this->sendJsonData($result);
    }

    protected function find($limit = 0, $offset = 0)
    {
        $limit = (int) $limit;
        if ($limit > $this->maxLimit || $limit < 1) {
            $limit = $this->maxLimit;
        }

        return ["body" => $this->repository->find([], [], $limit, (int) $offset)];
    }

    protected function findOne($id)
    {
        // @todo catch unsuccessfull convert
        $primaryValue = $this->repository->createEntity()
            ->getReflection()
            ->getPrimaryProperty()
            ->convertValue($id);

        $entity = $this->repository->query()
            ->findOne($primaryValue)
            ->execute();

        $result = [];
        if (!$entity) {
            $this->error("Resource not found!", Response::S404_NOT_FOUND);
        } else {
            $result["body"] = $entity;
        }
        return $result;
    }

    protected function create(array $data)
    {
        // @todo catch unsuccessfull convert
        $entity = $this->repository->createEntity($data);

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
            return [
                "success" => false,
                "messages" => $exception->getValidator()->getMessages()
            ];
        }

        // Success
        $this->httpResponse->setCode(201);
        return [
            "success" => true,
            "link" => $this->link("this", $entity->{$primaryProperty->getName()}),
            "body" => $entity->toArray(true)
        ];
    }

    protected function update($id, array $data)
    {
        // @todo catch unsuccessfull convert
        $entity = $this->repository->createEntity($data);

        if (!$entity->getReflection()->hasPrimaryProperty()) {
            $this->error("Can not update record if entity has no primary property defined!", Response::S405_METHOD_NOT_ALLOWED);
        }
        $primaryProperty = $entity->getReflection()->getPrimaryProperty();
        $entity->{$primaryProperty->getName()} = $primaryProperty->convertValue($id);
        $this->repository->save($entity);

        return [
            "success" => true,
            "link" => $this->link("this", $entity->{$primaryProperty->getName()}),
            "body" => $entity->toArray(true)
        ];
    }

    protected function destroy($id)
    {
        $entity = $this->repository->createEntity();
        $primaryProperty = $entity->getReflection()->getPrimaryProperty();

        // @todo catch unsuccessfull convert
        $entity->import([$primaryProperty->getName() => $id]);

        $this->repository->delete($entity);

        return [
            "success" => true
        ];
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

    /**
     * Get request input json data
     *
     * @return array
     */
    public function getJsonData()
    {
        $data = Json::decode($this->input->getData(), Json::FORCE_ARRAY);
        if (!$data) {
            return [];
        }
        return $data;
    }

    private function getPresenterName()
    {
       return explode(":", $this->getName())[1];
    }

}