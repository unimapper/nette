<?php

namespace UniMapper\Nette;

use Nette\Application as NA;
use Nette\Diagnostics\IBarPanel;
use Nette\Http\Response;

class Panel implements IBarPanel
{

    const HEADER_PREFIX = "UniMapper-Nette";

    /** @var array */
    private $config;

    /** @var Response */
    private $response;

    public function __construct(array $config, Response $response)
    {
        $this->config = $config;
        $this->response = $response;
    }

    private function _getQueryLevel(array $elapsed, $time)
    {
        $max = max($elapsed);
        if ($max) {
            return round($time / $max * 100);
        }
        return 100;
    }

    private function _getElapsed()
    {
        $elapsed = [];

        foreach (\UniMapper\Profiler::getResults() as $result) {
            $elapsed[] = $result->elapsed;
        }

        return $elapsed;
    }

    public function getTab()
    {
        $elapsed = $this->_getElapsed();

        ob_start();
        include __DIR__ . "/panel/templates/tab.phtml";
        return ob_get_clean();
    }

    public function getPanel()
    {
        ob_start();
        include __DIR__ . "/panel/templates/panel.phtml";
        return ob_get_clean();
    }

    public function onResponse(NA\Application $application, NA\IResponse $response)
    {
        if ($this->config["panel"]["ajax"] && $application->getPresenter()->isAjax()) {

            $debug = ["count" => count($this->_getElapsed())];
            if ($debug["count"]) {

                ob_start();
                include __DIR__ . "/panel/templates/results.phtml";
                $debug["template"] = ob_get_clean();
            }
            $this->response->setHeader(self::HEADER_PREFIX, base64_encode(json_encode($debug)));
        }
    }

}