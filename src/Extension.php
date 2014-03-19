<?php

namespace UniMapper\Nette;

use UniMapper\Exceptions\PropertyException;

/**
 * Nette Framework extension.
 */
class Extension extends \Nette\Config\CompilerExtension
{

    /** @var array $defaults Default configuration */
    public $defaults = array(
        "cache" => true
    );

    /**
     * Processes configuration data
     */
    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();
        $config = $this->getConfig($this->defaults);

        // Cache service
        if ($config["cache"]) {
            $builder->addDefinition($this->prefix("cache"))->setClass("UniMapper\Nette\Cache");
        }

        // Debug mode only
        if ($builder->parameters["debugMode"]) {

            // Create panel service
            $builder->addDefinition($this->prefix("panel"))
                ->setClass("UniMapper\Nette\Panel")
                ->addSetup(
                    'Nette\Diagnostics\Debugger::$bar->addPanel(?)',
                    array('@self')
                )
                ->addSetup(
                    'Nette\Diagnostics\Debugger::$blueScreen->addPanel(?)',
                    array('UniMapper\Nette\Extension::renderException')
                );
        }
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        $config = $this->getConfig($this->defaults);

        // Debug mode only
        if ($builder->parameters["debugMode"]) {

            // Register panel
            $builder->getDefinition("application")
                ->addSetup(
                    '$service->onStartup[] = ?',
                    array(array($this->prefix("@panel"), "getTab"))
                );
        }

        // Iterate over services
        foreach ($builder->getDefinitions() as $serviceName => $serviceDefinition) {

            $class = $serviceDefinition->class !== NULL ? $serviceDefinition->class : $serviceDefinition->factory->entity;

            // Repositories only
            if (class_exists($class) && is_subclass_of($class, "UniMapper\Repository")) {

                $builder->getDefinition($serviceName)->addSetup("setLogger", new \UniMapper\Logger);

                // Set repository cache
                if ($config["cache"]) {
                    $builder->getDefinition($serviceName)->addSetup("setCache", $builder->getDefinition($this->prefix("cache")));
                }

                // Register repository into the panel
                if ($builder->parameters["debugMode"]) {
                    $builder->getDefinition($this->prefix("panel"))->addSetup('registerRepository', "@" . $serviceName);
                }
            }

            // Mappers only
            if (class_exists($class) && is_subclass_of($class, "UniMapper\Mapper")) {

                // Set repository cache
                if ($config["cache"]) {
                    $builder->getDefinition($serviceName)->addSetup("setCache", $builder->getDefinition($this->prefix("cache")));
                }
            }
        }
    }

    /**
     * Register extension
     *
     * @param \Nette\Configurator $configurator
     */
    public static function register(\Nette\Configurator $configurator)
    {
        $class = get_class();
        $configurator->onCompile[] = function ($config, \Nette\Config\Compiler $compiler) use ($class) {
            $compiler->addExtension("unimapper", new $class);
        };
    }

    /**
     * Extend debugger bluescreen
     *
     * @param mixed $exception Exception
     *
     * @return array
     */
    public static function renderException($exception)
    {
        if ($exception instanceof PropertyException
            && $exception->getEntityPath() !== false
        ) {
            $link = \Nette\Diagnostics\Helpers::editorLink(
                $exception->getEntityPath(),
                $exception->getEntityLine()
            );
            $code = \Nette\Diagnostics\BlueScreen::highlightFile(
                $exception->getEntityPath(),
                $exception->getEntityLine()
            );
            return array(
                "tab" => "Entity",
                "panel" =>  $link . "\n" . $code
            );
	}
    }

}