<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class ApiRouteTest extends Tester\TestCase
{

    public function testGet()
    {
        $url = new Nette\Http\UrlScript("/api/simple");
        $httpRequest = new Nette\Http\Request($url, null, null, null, null, null, Nette\Http\Request::GET);

        $route = new UniMapper\Nette\Api\Route("Api");
        Assert::type("Nette\Application\Request", $appRequest = $route->match($httpRequest));
        Assert::same("///api/simple", $route->constructUrl($appRequest, $url));
    }

    public function testGetUndefinedEntity()
    {
        $url = new Nette\Http\UrlScript("/api/undefined");
        $httpRequest = new Nette\Http\Request($url, null, null, null, null, null, Nette\Http\Request::GET);

        $route = new UniMapper\Nette\Api\Route("Api");
        Assert::null($appRequest = $route->match($httpRequest));
    }

    public function testPut()
    {
        $url = new Nette\Http\UrlScript("/api/simple/1");
        $httpRequest = new Nette\Http\Request($url, null, null, null, null, null, Nette\Http\Request::PUT);

        $route = new UniMapper\Nette\Api\Route("Api");
        Assert::type("Nette\Application\Request", $appRequest = $route->match($httpRequest));
        Assert::same("///api/simple/1", $route->constructUrl($appRequest, $url));
    }

    public function testPost()
    {
        $url = new Nette\Http\UrlScript("/api/simple");
        $httpRequest = new Nette\Http\Request($url, null, null, null, null, null, Nette\Http\Request::POST);

        $route = new UniMapper\Nette\Api\Route("Api");
        Assert::type("Nette\Application\Request", $appRequest = $route->match($httpRequest));
        Assert::same("///api/simple", $route->constructUrl($appRequest, $url));
    }

    public function testDelete()
    {
        $url = new Nette\Http\UrlScript("/api/simple/1");
        $httpRequest = new Nette\Http\Request($url, null, null, null, null, null, Nette\Http\Request::DELETE);

        $route = new UniMapper\Nette\Api\Route("Api");
        Assert::type("Nette\Application\Request", $appRequest = $route->match($httpRequest));
        Assert::same("///api/simple/1", $route->constructUrl($appRequest, $url));
    }

    public function testCustom()
    {
        $url = new Nette\Http\UrlScript("/api/simple");
        $url->setQueryParameter("action", "custom");

        $httpRequest = new Nette\Http\Request($url, null, null, null, null, null, Nette\Http\Request::GET);

        $route = new UniMapper\Nette\Api\Route("Api");
        Assert::type("Nette\Application\Request", $appRequest = $route->match($httpRequest));
        Assert::same("///api/simple?action=custom", $route->constructUrl($appRequest, $url));
    }

}

$configurator->createContainer();

$testCase = new ApiRouteTest;
$testCase->run();