<?php

use Tester\Assert;
use UniMapper\Nette\Tests\Model\Entity;

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
        Assert::type("UniMapper\Nette\Tests\Model\Query\Custom", Entity\Simple::query()->custom());
    }

}

$testCase = new ExtensionTest($configurator->createContainer());
$testCase->run();