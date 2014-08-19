<?php

namespace UniMapper\Nette;

use UniMapper\Exception\PropertyException,
    Nette\Diagnostics\Helpers,
    Nette\Diagnostics\BlueScreen,
    Nette\DI\CompilerExtension,
    Nette\PhpGenerator\ClassType,
    Nette\DI\Compiler,
    Nette\Configurator;

// Nette 2.0 back compatibility
if (!class_exists('Nette\DI\CompilerExtension')) {
    class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
    class_alias('Nette\Config\Compiler', 'Nette\DI\Compiler');
    class_alias('Nette\Config\Helpers', 'Nette\DI\Config\Helpers');
    class_alias('Nette\Utils\PhpGenerator\ClassType', 'Nette\PhpGenerator\ClassType');
}

/**
 * Nette Framework extension.
 */
class Extension extends CompilerExtension
{

    /** @var array $defaults Default configuration */
    public $defaults = [
        "cache" => true,
        "namingConvention" => [
            "repository" => null,
            "entity" => null
        ],
        "api" => [
            "enabled" => false,
            "module" => "Api"
        ],
        "customQueries" => []
    ];

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

        if ($config["api"]["enabled"]) {
            $builder->addDefinition($this->prefix("repositories"))
                ->setClass("UniMapper\Nette\Api\RepositoryList");
            $builder->addDefinition($this->prefix("input"))
                ->setClass("UniMapper\Nette\Api\Input");
        }

        // Debug mode only
        if ($builder->parameters["debugMode"]) {

            // Create panel service
            $panel = $builder->addDefinition($this->prefix("panel"))
                ->setClass("UniMapper\Nette\Panel");

            if (class_exists("Tracy\Debugger")) {
                $panel->addSetup('Tracy\Debugger::getBar()->addPanel(?)', ['@self'])
                    ->addSetup('Tracy\Debugger::getBlueScreen()->addPanel(?)', ['UniMapper\Nette\Extension::renderException']);
            } else {
                $panel->addSetup('Nette\Diagnostics\Debugger::$bar->addPanel(?)', ['@self'])
                    ->addSetup('Nette\Diagnostics\Debugger::$blueScreen->addPanel(?)', ['UniMapper\Nette\Extension::renderException']);
            }
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
                    [[$this->prefix("@panel"), "getTab"]]
                );
        }

        $adapters = [];
        $repositories = [];

        // Iterate over services
        foreach ($builder->getDefinitions() as $serviceName => $serviceDefinition) {

            $class = $serviceDefinition->class !== null ? $serviceDefinition->class : $serviceDefinition->factory->entity;

            // Repositories only
            if (class_exists($class) && is_subclass_of($class, "UniMapper\Repository")) {

                $repositories[] = $serviceName;

                $repositoryDefinition = $builder->getDefinition($serviceName);

                // Set logger
                $repositoryDefinition->addSetup("setLogger", [new \UniMapper\Logger]);

                // Set repository cache
                if ($config["cache"]) {
                    $repositoryDefinition->addSetup("setCache", [$builder->getDefinition($this->prefix("cache"))]);
                }

                // Register custom queries
                foreach ($config["customQueries"] as $customQueryClass) {
                    $repositoryDefinition->addSetup("registerCustomQuery", [$customQueryClass]);
                }

                // Register repository into the panel
                if ($builder->parameters["debugMode"]) {
                    $builder->getDefinition($this->prefix("panel"))->addSetup('registerRepository', [$builder->getDefinition($serviceName)]);
                }
            }

            // Adapters only
            if (class_exists($class) && is_subclass_of($class, "UniMapper\Adapter")) {

                $adapters[] = $serviceName;

                // Set adapter cache
                if ($config["cache"]) {
                    $builder->getDefinition($serviceName)->addSetup("setCache", [$builder->getDefinition($this->prefix("cache"))]);
                }
            }
        }

        // Register all adapters
        foreach ($repositories as $repository) {

            foreach ($adapters as $adapter) {
                $builder->getDefinition($repository)->addSetup("registerAdapter", [$builder->getDefinition($adapter)]);
            }

            $builder->getDefinition($this->prefix("repositories"))
                ->addSetup('$service[] = $this->getService(?)', [$repository]);
        }

        // Generate API
        if ($config["api"]["enabled"]) {

            $builder->getDefinition("router")
                ->addSetup(
                    'UniMapper\Nette\Api\RouterFactory::prependTo($service, ?)',
                    [$config['api']['module']]
                );
        }
    }

    public function afterCompile(ClassType $class)
    {
        $config = $this->getConfig($this->defaults);
        $initialize = $class->methods['initialize'];

        // Naming convention
        if ($config["namingConvention"]["entity"]) {
            $initialize->addBody(
                'UniMapper\NamingConvention::$entityMask = ?;',
                [$config["namingConvention"]["entity"]]
            );
        }
        if ($config["namingConvention"]["repository"]) {
            $initialize->addBody(
                'UniMapper\NamingConvention::$repositoryMask = ?;',
                [$config["namingConvention"]["repository"]]
            );
        }
    }

    /**
     * Register extension
     */
    public static function register(Configurator $configurator)
    {
        $class = get_class();
        $configurator->onCompile[] = function ($config, Compiler $compiler) use ($class) {
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
            $link = Helpers::editorLink(
                $exception->getEntityPath(),
                $exception->getEntityLine()
            );
            $code = BlueScreen::highlightFile(
                $exception->getEntityPath(),
                $exception->getEntityLine()
            );
            return [
                "tab" => "Entity",
                "panel" =>  $link . "\n" . $code
            ];
        }
    }

}