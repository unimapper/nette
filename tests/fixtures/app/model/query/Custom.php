<?php

namespace UniMapper\Nette\Tests\Model\Query;

class Custom extends \UniMapper\Query\Custom
{

    public function onExecute(\UniMapper\Adapter\IAdapter $adapter)
    {
        return "foo";
    }

}