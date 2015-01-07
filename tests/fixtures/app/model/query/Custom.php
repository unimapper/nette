<?php

namespace UniMapper\Nette\Tests\Model\Query;

class Custom extends \UniMapper\Query
{

    public function onExecute(\UniMapper\Connection $connection)
    {
        return "foo";
    }

}