<?php

namespace UniMapper\Nette\Api;

class Input
{

    public function getData()
    {
        return file_get_contents("php://input");
    }

}