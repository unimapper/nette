<?php

namespace UniMapper\Nette\Api;

use Nette\Utils\ArrayHash;

// Nette 2.1 and earlier back compatibility
if (!class_exists('Nette\Utils\ArrayHash')) {
    class_alias('Nette\ArrayHash', 'Nette\Utils\ArrayHash');
}

class RepositoryList extends ArrayHash
{

    public function offsetSet($key, $value)
    {
        if (!$value instanceof \UniMapper\Repository) {
            throw new \Exception("Repository must be instance of UniMapper\Repository!");
        }
        parent::offsetSet($value->getName(), $value);
    }

}