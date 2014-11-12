<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

class ExtensionTest extends Tester\TestCase
{

    /** @var \Nette\DI\Container */
    private $container;

    public function __construct(Nette\DI\Container $container)
    {
        $this->container = $container;
    }

    public function testCustomQueries()
    {
        Assert::same("foo", $this->container->getService("unimapper.queryBuilder")->custom("Simple")->execute());
    }

}

$testCase = new ExtensionTest($configurator->createContainer());
$testCase->run();