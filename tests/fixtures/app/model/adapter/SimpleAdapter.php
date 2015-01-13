<?php

namespace UniMapper\Nette\Tests\Model\Adapter;

class SimpleAdapter extends \UniMapper\Adapter
{

    public function createCount($resource)
    {
        throw new \Exception("You should mock here!");
    }

    public function createDelete($resource)
    {
        throw new \Exception("You should mock here!");
    }

    public function createSelectOne($resource, $primaryName, $primaryValue, array $associations = [])
    {
        throw new \Exception("You should mock here!");
    }

    public function createSelect($resource, array $selection = [], array $orderBy = null, $limit = 0, $offset = 0, array $associations = [])
    {
        throw new \Exception("You should mock here!");
    }

    public function createInsert($resource, array $values)
    {
        throw new \Exception("You should mock here!");
    }

    public function createUpdate($resource, array $values)
    {
        throw new \Exception("You should mock here!");
    }

    public function createUpdateOne($resource, $primaryName, $primaryValue, array $values)
    {
        throw new \Exception("You should mock here!");
    }

    public function createModifyManyToMany(\UniMapper\Association\ManyToMany $association, $primaryValue, array $keys, $action = self::ASSOC_ADD)
    {
        throw new \Exception("You should mock here!");
    }

    public function onExecute(\UniMapper\Adapter\IQuery $query)
    {
        throw new \Exception("You should mock here!");
    }

}