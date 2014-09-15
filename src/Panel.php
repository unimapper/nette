<?php

namespace UniMapper\Nette;

use Nette\Diagnostics\Dumper,
    Nette\Diagnostics\IBarPanel;

class Panel implements IBarPanel
{

    private $repositories = [];
    private $elapsed = [];

    public function registerRepository(\UniMapper\Repository $repository)
    {
        $this->repositories[] = $repository;
    }

    private function _getClickable($variable, $collapsed = false)
    {
        if (class_exists('Nette\Diagnostics\Dumper')) {
            return Dumper::toHtml($variable, [Dumper::COLLAPSE => $collapsed]);
        }

        // Nette 2.0 back compatibility
        return \Nette\Diagnostics\Helpers::clickableDump($variable, $collapsed);
    }

    private function _getQueryLevel($time)
    {
        return round($time / max($this->elapsed) * 100);
    }

    public function getTab()
    {
        ob_start();

        foreach ($this->repositories as $repository) {

            $this->elapsed += array_map(function(\UniMapper\Query $query) {
                if ($query->getResult() !== null) {
                    return $query->getElapsed();
                }
            }, $repository->getLogger()->getQueries());
        }

        include __DIR__ . "/templates/Panel.tab.phtml";

        return ob_get_clean();
    }

    public function getPanel()
    {
        ob_start();
        include __DIR__ . "/templates/Panel.panel.phtml";
        return ob_get_clean();
    }

}