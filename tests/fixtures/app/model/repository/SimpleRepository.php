<?php

namespace UniMapper\Nette\Tests\Model\Repository;

class SimpleRepository extends \UniMapper\Repository
{

    public function save(\UniMapper\Entity $entity)
    {
        $entity->getValidator()
            ->on("text")
                ->addRule(\UniMapper\Validator::FILLED, "Text is required!");

        parent::save($entity);
    }

}