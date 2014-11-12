<?php

namespace UniMapper\Nette;

use UniMapper\QueryBuilder,
    Nette\Diagnostics\Dumper,
    Nette\Diagnostics\IBarPanel,
    Nette\Http\Response,
    Nette\Application as NA;

class Panel implements IBarPanel
{

    const HEADER_PREFIX = "UniMapper-Nette";
    const UML_CACHE_KEY = "uml.schema";

    /** @var \UniMapper\PlantUml\Genarator */
    private $umlGenerator;

    /** @var array */
    private $config;

    /** @var Cache */
    private $cache;

    /** @var Response */
    private $response;

    /** @var QueryBuilder */
    private $queryBuilder;

    public function __construct(array $config, Response $response, QueryBuilder $queryBuilder, Cache $cache = null)
    {
        $this->config = $config;
        $this->response = $response;
        $this->queryBuilder = $queryBuilder;
        $this->cache = $cache;
        $this->umlGenerator = new \UniMapper\PlantUml\Generator;
    }

    private function _getClickable($variable, $collapsed = false)
    {
        if (class_exists('Nette\Diagnostics\Dumper')) {
            return Dumper::toHtml($variable, [Dumper::COLLAPSE => $collapsed]);
        }

        // Nette 2.0 back compatibility
        return \Nette\Diagnostics\Helpers::clickableDump($variable, $collapsed);
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

        foreach ($this->queryBuilder->getCreated() as $query) {
            if ($query->executed) {
                $elapsed[] = $query->elapsed;
            }
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
        if ($this->cache) {

            $umlUrl = $this->cache->load(self::UML_CACHE_KEY);
            if (!$umlUrl) {

                $umlUrl = $this->_generateUmlUrl();
                $this->cache->save(self::UML_CACHE_KEY, $umlUrl, []);
            }
        } else {
            $umlUrl = $this->_generateUmlUrl();
        }

        ob_start();
        include __DIR__ . "/panel/templates/panel.phtml";
        return ob_get_clean();
    }

    public function onResponse(NA\Application $application, NA\IResponse $response)
    {
        if ($application->getPresenter()->isAjax()) {

            $debug = ["count" => count($this->_getElapsed())];
            if ($debug["count"]) {

                ob_start();
                include __DIR__ . "/panel/templates/queries.phtml";
                $debug["template"] = ob_get_clean();
            }
            $this->response->setHeader(self::HEADER_PREFIX, base64_encode(json_encode($debug)));
        }
    }

    private function _generateUmlUrl()
    {
        return "http://www.plantuml.com/plantuml/img/"
            . $this->umlGenerator->getUrlCode($this->umlGenerator->generate());
    }

}