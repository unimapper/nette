<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

class ApiQuery extends Tester\TestCase
{
    private $filter;

    protected function setUp()
    {
        parent::setUp();
        $this->filter = ['one' => [\UniMapper\Entity\Filter::EQUAL => 1]];
    }


    public function testSetFilter()
    {
        $query = new UniMapper\Nette\Api\Adapter\Query("url");
        $query->setFilter($this->filter);
        Assert::same($this->filter, $query->parameters["where"]);
    }

    public function testGetRaw()
    {
        $query = new UniMapper\Nette\Api\Adapter\Query("url");
        Assert::same("url", $query->getRaw());

        $query->setFilter($this->filter);
        Assert::same("url?where=%7B%22one%22%3A%7B%22%3D%22%3A1%7D%7D", $query->getRaw());
    }

}

$testCase = new ApiQuery;
$testCase->run();