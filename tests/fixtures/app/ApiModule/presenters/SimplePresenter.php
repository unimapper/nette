<?php

namespace UniMapper\Nette\Tests\ApiModule;

class SimplePresenter extends \UniMapper\Nette\Api\Presenter
{

    public function actionCustomGet($id)
    {
        $this->sendJson(["success" => true, "id" => $id]);
    }

}