<?php

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

class ApiQuery extends Tester\TestCase
{


    public function testSetConditions()
    {
        $query = new UniMapper\Nette\Api\Adapter\Query("url");
        $query->setConditions(
            [
                ['one', '>', 1, 'OR'],
                ['two', '<', 2, 'OR'],
                ['equal', '=', 3, 'AND'],
                ['notequal', '!=', 3, 'AND'],
                ['in', 'IN', [1, 2], 'AND'],
                ['notin', 'NOT IN', [1, 2], 'AND'],
                ['is', 'IS', 1, 'AND'],
                ['isnot', 'IS NOT', 1, 'AND'],
                ['like', 'LIKE', "%a%", 'AND']
            ]
        );

        Assert::same(
            array(
                array('or' => array(array('one' => array('>' => 1)))),
                array('or' => array(array('two' => array('<' => 2)))),
                array('equal' => array('=' => 3)),
                array('notequal' => array('!' => 3)),
                array('in' => array('=' => array(1, 2))),
                array('notin' => array('!' => array(1, 2))),
                array('is' => array('=' => 1)),
                array('isnot' => array('!' => 1)),
                array('like' => array('like' => '%a%'))
            ),
            $query->parameters["where"]
        );
    }

    /**
     * @throws Exception Nested conditions are not supported!
     */
    public function testSetConditionsUnsupportedNestedConditions()
    {
        $query = new UniMapper\Nette\Api\Adapter\Query("url");
        $query->setConditions(
            [
                [
                    ['one', '>', 1, 'OR'],
                    ['two', '<', 2, 'OR'],
                    ['three', '=', 3, 'AND'],
                ],
                "AND"
            ]
        );
    }

    public function testGetRaw()
    {
        $query = new UniMapper\Nette\Api\Adapter\Query("url");
        $query->setConditions(
            [
                ['one', '=', 1, 'AND']
            ]
        );
        Assert::same("url?where=%5B%7B%22one%22%3A%7B%22%3D%22%3A1%7D%7D%5D", $query->getRaw());
    }

}

$testCase = new ApiQuery;
$testCase->run();