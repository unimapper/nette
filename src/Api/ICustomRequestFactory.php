<?php

namespace UniMapper\Nette\Api;

interface ICustomRequestFactory
{

    /**
     * Create custom request
     *
     * @return \UniMapper\Nette\Api\CustomRequest
     */
    public function create();

}