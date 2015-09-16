<?php

use Tester\Assert;
use UniMapper\Nette\Tests;

require __DIR__ . '/../bootstrap.php';

/**
 * @httpCode xxx
 * @testCase
 */
class ApiTest extends Tester\TestCase
{

    /** @var \Mockery\mock */
    private $inputMock;

    /** @var \Mockery\mock */
    private $repositoryMock;

    /** @var \SystemContainer */
    private $container;

    public function __construct(Nette\DI\Container $container)
    {
        $this->container = $container;
    }

    public function setUp()
    {
        parent::setUp();

        // Mock input data
        $this->inputMock = Mockery::mock("UniMapper\Nette\Api\Input");
        $this->container->removeService("unimapper.input");
        $this->container->addService("unimapper.input", $this->inputMock);

        $this->repositoryMock = Mockery::mock("UniMapper\Nette\Tests\Model\Repository\SimpleRepository");
        $this->repositoryMock->shouldReceive("getName")->once()->andReturn("Simple");
        $this->container->removeService("simpleRepository");
        $this->container->addService("simpleRepository", $this->repositoryMock);
    }

    public function testPost()
    {
        $savedEntity = new Tests\Model\Entity\Simple(["id" => 1, "text" => "foo"]);

        $this->inputMock->shouldReceive("getData")->once()->andReturn('{"text": "foo"}');
        $this->repositoryMock->shouldReceive("getEntityName")->once()->andReturn("Simple");
        $this->repositoryMock->shouldReceive("save")->once()->andReturn($savedEntity);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::POST, ["action" => "post"]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());

        $payload = $response->getPayload();
        Assert::type("UniMapper\Nette\Api\Resource", $payload);
        Assert::true($payload->success);
        Assert::same('/api/simple/1', $payload->link);
        Assert::same($savedEntity->id, $payload->body);
    }

    public function testPostWithFailedValidation()
    {
        $validator = new UniMapper\Validator(new Tests\Model\Entity\Simple);
        $validator->on("text")
                ->addRule(UniMapper\Validator::FILLED, "Text is required!");
        $validator->validate();

        $this->inputMock->shouldReceive("getData")->once()->andReturn(null);
        $this->repositoryMock->shouldReceive("getEntityName")->once()->andReturn("Simple");
        $this->repositoryMock->shouldReceive("save")->once()->andThrow(new UniMapper\Exception\ValidatorException($validator));

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::POST, ["action" => "post"]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::same(
            json_encode($validator->getMessages()),
            json_encode($response->getPayload()->messages)
        );
    }

    public function testNoRepositoryWithCustomAction()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn(null);
        $request = new Nette\Application\Request(
            'Api:NoRepository',
            Nette\Http\Request::GET, ["action" => "test"]
        );
        Assert::type(
            "Nette\Application\Responses\JsonResponse",
            $this->_createPresenter()->run($request)
        );
    }

    /**
     * @throws Nette\Application\BadRequestException Repository 'NoRepository' not found!
     */
    public function testNoRepository()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn(null);
        $request = new Nette\Application\Request('Api:NoRepository', Nette\Http\Request::GET, ["action" => "get"]);
        $this->_createPresenter()->run($request);
    }

    public function testGetId()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn(null);
        $this->repositoryMock->shouldReceive("getEntityName")->once()->andReturn("Simple");
        $this->repositoryMock->shouldReceive("findOne")
            ->once()
            ->with(1, [])
            ->andReturn(new Tests\Model\Entity\Simple(["id" => 1, "text" => "foo"]));

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ['id' => 1, "action" => "get"]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());

        $payload = $response->getPayload();
        Assert::type("UniMapper\Nette\Tests\Model\Entity\Simple", $payload->body);
        Assert::same(1, $payload->body->id);
        Assert::same("foo", $payload->body->text);
    }

    public function testGetIdNotFound()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn(null);
        $this->repositoryMock->shouldReceive("getEntityName")->once()->andReturn("Simple");
        $this->repositoryMock->shouldReceive("findOne")
            ->once()
            ->with(1, [])
            ->andReturn(false);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ['id' => 1, "action" => "get"]);
        Assert::type("Nette\Application\Responses\JsonResponse", $response = $this->_createPresenter()->run($request));
        Assert::type("UniMapper\Nette\Api\Resource", $response->getPayload());
        Assert::same(
            array(
                'success' => NULL,
                'link' => NULL,
                'code' => 404,
                'body' => array(),
                'messages' => array('Record not found!'),
            ),
            (array) $response->getPayload()
        );
    }

    public function testGet()
    {
        $collection = Tests\Model\Entity\Simple::createCollection(
            [["text" => "foo", "id" => 1], ["text" => "foo2", "id" => 2]]
        );
        $this->inputMock->shouldReceive("getData")->once();
        $this->repositoryMock->shouldReceive("find")
            ->with([], [], 10, 0, [])
            ->once()
            ->andReturn($collection);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ["action" => "get"]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::type("UniMapper\Entity\Collection", $response->getPayload()->body);
        Assert::count(2, $response->getPayload()->body);
        Assert::same("foo", $response->getPayload()->body[0]->text);
        Assert::same("foo2", $response->getPayload()->body[1]->text);
        Assert::same(1, $response->getPayload()->body[0]->id);
        Assert::same(2, $response->getPayload()->body[1]->id);
    }

    public function testGetCount()
    {
        $this->inputMock->shouldReceive("getData")->once();
        $this->repositoryMock->shouldReceive("count")
            ->with([])
            ->once()
            ->andReturn(3);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ["action" => "get", "count" => true]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::same(3, $response->getPayload()->body);
    }

    public function testGetWithFilter()
    {
        $this->inputMock->shouldReceive("getData")->once();
        $this->repositoryMock->shouldReceive("find")
            ->with(["id" => ["=" => 1]], [], 10, 0, [])
            ->once()
            ->andReturn(Tests\Model\Entity\Simple::createCollection());

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ["action" => "get", "where" => '{"id": {"=": 1}}']);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::type("UniMapper\Entity\Collection", $response->getPayload()->body);
        Assert::count(0, $response->getPayload()->body);
    }

    public function testGetWithInvalidJsonFilter()
    {
        $this->inputMock->shouldReceive("getData")->once();
        $this->repositoryMock->shouldReceive("find")
            ->with(["id" => ["=" => 1]], [], 10, 0, [])
            ->once()
            ->andReturn(Tests\Model\Entity\Simple::createCollection());

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ["action" => "get", "where" => 'foo']);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::same(["Invalid where parameter. Must be a valid JSON but 'foo' given!"], $response->getPayload()->messages);
    }

    public function testPutId()
    {
        $savedEntity = new Tests\Model\Entity\Simple(["id" => 1, "text" => "foo"]);

        $this->inputMock->shouldReceive("getData")->once()->andReturn('{"text": "foo"}');
        $this->repositoryMock->shouldReceive("getEntityName")->once()->andReturn("Simple");
        $this->repositoryMock->shouldReceive("save")->once()->andReturn($savedEntity);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::PUT, ['id' => 1, "action" => "put"]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());

        $payload = $response->getPayload();
        Assert::type("UniMapper\Nette\Api\Resource", $payload);
        Assert::true($payload->success);
        Assert::same([], $payload->body);
    }

    public function testPutFilter()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn('{"text": "foo"}');
        $this->repositoryMock->shouldReceive("getEntityName")->once()->andReturn("Simple");
        $this->repositoryMock->shouldReceive("updateBy")
            ->with(
                Mockery::type("UniMapper\Nette\Tests\Model\Entity\Simple"),
                ["id" => ["=" => 3]]
            )
            ->once()
            ->andReturn(3);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::PUT, ["action" => "put", "where" => '{"id": {"=": 3}}']);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());

        $payload = $response->getPayload();
        Assert::type("UniMapper\Nette\Api\Resource", $payload);
        Assert::true($payload->success);
    }

    public function testPut()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn('{"text": "foo"}');
        $this->repositoryMock->shouldReceive("getEntityName")->once()->andReturn("Simple");
        $this->repositoryMock->shouldReceive("updateBy")
            ->with(
                Mockery::type("UniMapper\Nette\Tests\Model\Entity\Simple"),
                []
            )
            ->once()
            ->andReturn(3);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::PUT, ["action" => "put"]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());

        $payload = $response->getPayload();
        Assert::type("UniMapper\Nette\Api\Resource", $payload);
        Assert::true($payload->success);
    }

    public function testDelete()
    {
        $this->inputMock->shouldReceive("getData")->once();
        $this->repositoryMock->shouldReceive("getEntityName")->once()->andReturn("Simple");
        $this->repositoryMock->shouldReceive("destroy")->once()->andReturn(true);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::DELETE, ['id' => 1, "action" => "delete"]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::type("UniMapper\Nette\Api\Resource", $response->getPayload());
        Assert::same(
            array(
                'success' => true,
                'link' => NULL,
                'code' => NULL,
                'body' => array(),
                'messages' => array(),
            ),
            (array) $response->getPayload()
        );
    }

    public function testCustomGetAction()
    {
        $this->inputMock->shouldReceive("getData")->once();
        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ['id' => 1, "action" => "customGet"]);
        $response = $this->_createPresenter()->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::same(["success" => true, "id" => 1], $response->getPayload());
    }

    public function testLink()
    {
        $this->inputMock->shouldReceive("getData")->once();

        $presenter = $this->_createPresenter();
        $presenter->run(new Nette\Application\Request('Api:Simple'));

        Assert::same("/api/simple", $presenter->link(":Api:Simple:get"));
        Assert::same("/api/simple/1", $presenter->link(":Api:Simple:get", 1));
        Assert::same("/api/simple", $presenter->link(":Api:Simple:post"));
        Assert::same("/api/simple", $presenter->link(":Api:Simple:put"));
        Assert::same("/api/simple", $presenter->link(":Api:Simple:delete"));
    }

    /**
     * @return \Nette\Application\UI\Presenter
     */
    private function _createPresenter()
    {
        $presenter = $this->container->getByType('Nette\Application\IPresenterFactory')->createPresenter('Api:Simple');
        $presenter->autoCanonicalize = false;
        return $presenter;
    }

}

$testCase = new ApiTest($configurator->createContainer());
$testCase->run();