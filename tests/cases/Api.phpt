<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * @httpCode 404
 */
class ApiTest extends Tester\TestCase
{

    /** @var \Nette\Application\UI\Presenter */
    private $presenter;

    /** @var \Mockery\mock */
    private $inputMock;

    /** @var \Mockery\mock */
    private $adapterMock;

    /** @var \Mockery\mock */
    private $adapterQueryMock;

    public function __construct(Nette\DI\Container $container)
    {
        // Mock input data
        $this->inputMock = Mockery::mock("UniMapper\Nette\Api\Input");
        $container->removeService("unimapper.input");
        $container->addService("unimapper.input", $this->inputMock);

        // Mock adapter
        $this->adapterMock = Mockery::mock("UniMapper\Nette\Tests\Model\Adapter\SimpleAdapter");
        $this->adapterMock->shouldReceive("getMapper")->once()->andReturn(new UniMapper\Adapter\Mapper);
        $container->removeService("simpleAdapter");
        $container->addService("simpleAdapter", $this->adapterMock);

        // Mock adapter query
        $this->adapterQueryMock = Mockery::mock("UniMapper\Adapter\IQuery");
        $this->adapterQueryMock->shouldReceive("getRaw")->once();

        // Create presenter
        $this->presenter = $container->getByType('Nette\Application\IPresenterFactory')->createPresenter('Api:Simple');
        $this->presenter->autoCanonicalize = false;
    }

    public function testPost()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn('{"text": "foo"}');
        $this->adapterMock->shouldReceive("createInsert")->once()->andReturn($this->adapterQueryMock);
        $this->adapterMock->shouldReceive("execute")->once()->with($this->adapterQueryMock)->andReturn(1);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::POST, ["action" => "post"]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());

        $payload = $response->getPayload();
        Assert::type("UniMapper\Nette\Api\Resource", $payload);
        Assert::true($payload->success);
        Assert::same('/api/simple/1', $payload->link);
        Assert::same(['id' => 1, 'text' => "foo"], $payload->body);
    }

    public function testPostInvalid()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn(null);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::POST, ["action" => "post"]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::isEqual(
            array(
                'success' => false,
                'messages' => array(
                    'properties' => array(
                        'text' => array(
                            new UniMapper\Validator\Message(
                                'Text is required!',
                                UniMapper\Validator\Rule::ERROR
                            )
                        )
                    )
                )
            ),
            $response->getPayload()
        );
    }

    /**
     * @throws Nette\Application\BadRequestException Repository 'NoRepository' not found!
     */
    public function testNoRepository()
    {
        $request = new Nette\Application\Request('Api:NoRepository', Nette\Http\Request::GET, ["action" => "get"]);
        $this->presenter->run($request);
    }

    public function testGetId()
    {
        $this->adapterMock->shouldReceive("createFindOne")->once()->with("test_resource", "id", 1)->andReturn($this->adapterQueryMock);
        $this->adapterMock->shouldReceive("execute")->once()->with($this->adapterQueryMock)->andReturn(["text" => "foo", "id" => 1]);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ['id' => 1, "action" => "get"]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());

        $payload = $response->getPayload();
        Assert::type("UniMapper\Nette\Tests\Model\Entity\Simple", $payload->body);
        Assert::same(1, $payload->body->id);
        Assert::same("foo", $payload->body->text);
    }

    public function testGetIdNotFound()
    {
        $this->adapterMock->shouldReceive("createFindOne")->once()->with()->andReturn($this->adapterQueryMock);
        $this->adapterMock->shouldReceive("execute")->once()->with($this->adapterQueryMock)->andReturn(false);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ['id' => 1, "action" => "get"]);
        Assert::type("Nette\Application\Responses\JsonResponse", $response = $this->presenter->run($request));
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
        $this->adapterMock->shouldReceive("createFind")
            ->with("test_resource", ["id", "text"], [], 10, 0)
            ->once()
            ->andReturn($this->adapterQueryMock);
        $this->adapterMock->shouldReceive("execute")
            ->with($this->adapterQueryMock)
            ->once()
            ->andReturn([["text" => "foo", "id" => 1], ["text" => "foo2", "id" => 2]]);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ["action" => "get"]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::type("UniMapper\EntityCollection", $response->getPayload()->body);
        Assert::count(2, $response->getPayload()->body);
        Assert::same("foo", $response->getPayload()->body[0]->text);
        Assert::same("foo2", $response->getPayload()->body[1]->text);
        Assert::same(1, $response->getPayload()->body[0]->id);
        Assert::same(2, $response->getPayload()->body[1]->id);
    }

    public function testPut()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn('{"text": "foo"}');
        $this->adapterMock->shouldReceive("createUpdateOne")->with("test_resource", "id", 1, ["text" => "foo"])->once()->andReturn($this->adapterQueryMock);
        $this->adapterMock->shouldReceive("execute")->with($this->adapterQueryMock)->once()->andReturn(true);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::PUT, ['id' => 1, "action" => "put"]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());

        $payload = $response->getPayload();
        Assert::type("UniMapper\Nette\Api\Resource", $payload);
        Assert::true($payload->success);
        Assert::same('/api/simple/1', $payload->link);
        Assert::same(['id' => 1, 'text' => "foo"], $payload->body);
    }

    public function testDelete()
    {
        $this->adapterMock->shouldReceive("createDeleteOne")->with("test_resource", "id", 1)->once()->andReturn($this->adapterQueryMock);
        $this->adapterMock->shouldReceive("execute")->with($this->adapterQueryMock)->once()->andReturn(true);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::DELETE, ['id' => 1, "action" => "delete"]);
        $response = $this->presenter->run($request);
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
        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ['id' => 1, "action" => "customGet"]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::same(["success" => true, "id" => 1], $response->getPayload());
    }

    public function testLink()
    {
        Assert::same("/api/simple", $this->presenter->link(":Api:Simple:get"));
        Assert::same("/api/simple/1", $this->presenter->link(":Api:Simple:get", 1));
        Assert::same("/api/simple", $this->presenter->link(":Api:Simple:post"));
        Assert::same("/api/simple", $this->presenter->link(":Api:Simple:put"));
        Assert::same("/api/simple", $this->presenter->link(":Api:Simple:delete"));
    }

}

$testCase = new ApiTest($configurator->createContainer());
$testCase->run();