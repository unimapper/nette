<?php

namespace UniMapper\Nette\Tests\ApiModule;

class SimplePresenter extends \UniMapper\Nette\Api\Presenter
{

    public function actionCustomGet()
    {
        $this->sendJson(["success" => true]);
    }

}