<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

class ApiTest extends Tester\TestCase
{

    /** @var \Nette\Application\UI\Presenter */
    private $presenter;

    /** @var \Mockery\mock */
    private $inputMock;

    /** @var \Mockery\mock */
    private $mapperMock;

    public function __construct(Nette\DI\Container $container)
    {
        // Mock input data
        $this->inputMock = Mockery::mock("UniMapper\Nette\Api\Input");
        $container->removeService("unimapper.input");
        $container->addService("unimapper.input", $this->inputMock);

        // Mock mapper
        $this->mapperMock = Mockery::mock("UniMapper\Nette\Tests\Model\Mapper\SimpleMapper")->makePartial();
        $this->mapperMock->shouldReceive("getName")->once()->andReturn("SimpleMapper");
        $container->removeService("simpleMapper");
        $container->addService("simpleMapper", $this->mapperMock);

        // Create presenter
        $this->presenter = $container->getByType('Nette\Application\IPresenterFactory')->createPresenter('Api:Simple');
        $this->presenter->autoCanonicalize = false;
    }

    public function testCreate()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn('{"text": "foo"}');
        $this->mapperMock->shouldReceive("insert")->once()->andReturn(1);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::POST, []);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::same(
            array(
                'success' => true,
                'link' => '/api/simple/1?action=default',
                'body' => array('id' => 1, 'text' => "foo")
            ),
            $response->getPayload()
        );
    }

    public function testCreateInvalid()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn(null);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::POST, []);
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
        $request = new Nette\Application\Request('Api:NoRepository', Nette\Http\Request::GET, []);
        $this->presenter->run($request);
    }

    public function testFindOne()
    {
        $this->mapperMock->shouldReceive("findOne")->once()->andReturn(["text" => "foo", "id" => 1]);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ['id' => 1]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::type("UniMapper\Nette\Tests\Model\Entity\Simple", $response->getPayload()["body"]);
        Assert::same(1, $response->getPayload()["body"]->id);
        Assert::same("foo", $response->getPayload()["body"]->text);
    }

    /**
     * @throws Nette\Application\BadRequestException Resource not found!
     */
    public function testFindOneNotFound()
    {
        $this->mapperMock->shouldReceive("findOne")->once()->andReturn(false);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, ['id' => 1]);
        $this->presenter->run($request);
    }

    public function testFind()
    {
        $this->mapperMock->shouldReceive("findAll")
            ->with("test_resource", ["id", "text"], [], [], 10, 0, [])
            ->once()
            ->andReturn([["text" => "foo", "id" => 1], ["text" => "foo2", "id" => 2]]);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::GET, []);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::type("UniMapper\EntityCollection", $response->getPayload()["body"]);
        Assert::count(2, $response->getPayload()["body"]);
        Assert::same("foo", $response->getPayload()["body"][0]->text);
        Assert::same("foo2", $response->getPayload()["body"][1]->text);
        Assert::same(1, $response->getPayload()["body"][0]->id);
        Assert::same(2, $response->getPayload()["body"][1]->id);
    }

    public function testUpdate()
    {
        $this->inputMock->shouldReceive("getData")->once()->andReturn('{"text": "foo"}');
        $this->mapperMock->shouldReceive("updateOne")->with("test_resource", "id", 1, ["text" => "foo"])->once()->andReturn(null);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::PUT, ['id' => 1]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::same(
            array(
                'success' => true,
                'link' => '/api/simple/1?action=default',
                'body' => array('id' => 1, 'text' => "foo")
            ),
            $response->getPayload()
        );
    }

    public function testDestroy()
    {
        $this->mapperMock->shouldReceive("delete")->with("test_resource", [["id", "=", 1, "AND"]])->once()->andReturn(null);

        $request = new Nette\Application\Request('Api:Simple', Nette\Http\Request::DELETE, ['id' => 1]);
        $response = $this->presenter->run($request);
        Assert::type("Nette\Application\Responses\JsonResponse", $response);
        Assert::same("application/json", $response->getContentType());
        Assert::same(['success' => true], $response->getPayload());
    }

}

$testCase = new ApiTest($configurator->createContainer());
$testCase->run();