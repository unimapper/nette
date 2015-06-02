<?php

namespace UniMapper\Nette\Api;

class Resource extends \Nette\Object implements \JsonSerializable
{

    public $success;
    public $link;
    public $code;
    public $body = [];
    public $messages = [];

    public function jsonSerialize()
    {
        $data = ["body" => $this->body];
        if ($this->link !== null) {
            $data["link"] = $this->link;
        }
        if ($this->success !== null) {
            $data["success"] = $this->success;
        }
        if ($this->messages) {
            $data["messages"] = $this->messages;
        }
        return $data;
    }

}