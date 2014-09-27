<?php

namespace UniMapper\Nette;

use Nette\Diagnostics\Dumper,
    Nette\Diagnostics\IBarPanel;

class Panel implements IBarPanel
{

    /** @var array */
    private $repositories = [];

    /** @var \UniMapper\PlantUml\Genarator */
    private $umlGenerator;

    public function __construct(\UniMapper\PlantUml\Generator $umlGenerator = null)
    {
        $this->umlGenerator = $umlGenerator;
    }

    public function registerRepository(\UniMapper\Repository $repository)
    {
        $this->repositories[] = $repository;

        if ($this->umlGenerator) {
            $this->umlGenerator->add($repository->createEntity()->getReflection());
        }
    }

    private function _getUmlUrl()
    {

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
                if ($query->getResult() !== null) {
                    $elapsed[] = $query->getElapsed();
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
        $elapsed = $this->_getElapsed();

        ob_start();
        include __DIR__ . "/templates/Panel.panel.phtml";
        return ob_get_clean();
    }

}