<?php

namespace UniMapper\Nette;

use Nette\Diagnostics\Dumper,
    Nette\Diagnostics\IBarPanel;

class Panel implements IBarPanel
{

    const UML_CACHE_KEY = "uml.schema";

    /** @var array */
    private $repositories = [];

    /** @var \UniMapper\PlantUml\Genarator */
    private $umlGenerator;

    /** @var array */
    private $config;

    /** @var Cache */
    private $cache;

    public function __construct(array $config, Cache $cache = null)
    {
        $this->config = $config;
        $this->cache = $cache;
        $this->umlGenerator = new \UniMapper\PlantUml\Generator;
    }

    public function registerRepository(\UniMapper\Repository $repository)
    {
        $this->repositories[] = $repository;
        $this->umlGenerator->add($repository->createEntity()->getReflection());
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
        return round($time / max($elapsed) * 100);
    }

    private function _getElapsed()
    {
        $elapsed = [];
        foreach ($this->repositories as $repository) {

            foreach ($repository->getLogger()->getQueries() as $query) {
                if ($query->executed) {
                    $elapsed[] = $query->elapsed;
                }
            }
        }
        return $elapsed;
    }

    public function getTab()
    {
        $elapsed = $this->_getElapsed();

        ob_start();
        include __DIR__ . "/templates/Panel.tab.phtml";
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

        $elapsed = $this->_getElapsed();

        ob_start();
        include __DIR__ . "/templates/Panel.panel.phtml";
        return ob_get_clean();
    }

    private function _generateUmlUrl()
    {
        return "http://www.plantuml.com/plantuml/img/"
            . $this->umlGenerator->getUrlCode($this->umlGenerator->generate());
    }

}