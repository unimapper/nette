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
        "adapters" => [],
        "panel" => true,
        "profiler" => true,
        "cache" => true,
        "namingConvention" => [
            "repository" => null,
            "entity" => null
        ],
        "api" => [
            "enabled" => false,
            "module" => "Api"
        ],
        "entityFactory" => "UniMapper\EntityFactory",
        "customQueries" => []
    ];

    /**
     * Processes configuration data
     */
    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();
        $config = $this->getConfig($this->defaults);

        // Create cache
        if ($config["cache"]) {
            $builder->addDefinition($this->prefix("cache"))->setClass("UniMapper\Nette\Cache");
        }

        // Create entity factory
        $builder->addDefinition($this->prefix("entityFactory"))->setClass($config["entityFactory"]);

        // Create query builder
        $builder->addDefinition($this->prefix("queryBuilder"))->setClass("UniMapper\QueryBuilder");

        foreach ($config["customQueries"] as $customQueryClass) {

            $builder->getDefinition($this->prefix("queryBuilder"))
                ->addSetup("registerQuery", array($customQueryClass));
        }

        // Setup API
        if ($config["api"]["enabled"]) {
            $builder->addDefinition($this->prefix("repositories"))
                ->setClass("UniMapper\Nette\Api\RepositoryList");
            $builder->addDefinition($this->prefix("input"))
                ->setClass("UniMapper\Nette\Api\Input");
        }

        // Debug mode
        if ($builder->parameters["debugMode"] && $config["panel"]) {

            // Create panel
            $builder->addDefinition($this->prefix("panel"))
                ->setClass("UniMapper\Nette\Panel", [$config]);

            // Register on presenter events
            $builder->getDefinition('application')
                ->addSetup('?->onResponse[] = ?', array('@self', array($this->prefix('@panel'), 'onResponse')));
        }
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        $config = $this->getConfig($this->defaults);

        if (!is_array($config["adapters"])) {
            throw new \Exception("Adapters must be array of adapters!");
        }
        foreach ($config["adapters"] as $adapterName => $adapterService) {

            // Register profiler events
            if ($config["profiler"]) {
                $builder->getDefinition($builder->getServiceName($adapterService))
                    ->addSetup('$service->afterExecute(array(?, "adapterCallback"))', array(get_class()));
            }

            // Register adapters on query builder
            $builder->getDefinition($this->prefix("queryBuilder"))
                ->addSetup("registerAdapter", array($adapterName, $adapterService));
        }

        if ($config["profiler"]) {
            $builder->getDefinition($this->prefix("queryBuilder"))
                ->addSetup('$service->beforeQuery(array(?, "beforeQueryCallback"))', array(get_class()))
                ->addSetup('$service->afterQuery(array(?, "afterQueryCallback"))', array(get_class()));
        }

        // Back compatibility
        if (class_exists("Tracy\Debugger")) {
            $panelDef = 'Tracy\Debugger::getBar()->addPanel(?)';
            $bluescreenDef = 'Tracy\Debugger::getBlueScreen()->addPanel(?)';
        } else {
            $panelDef = 'Nette\Diagnostics\Debugger::$bar->addPanel(?)';
            $bluescreenDef = 'Nette\Diagnostics\Debugger::$blueScreen->addPanel(?)';
        }

        // Add bluescreen panel
        $builder->getDefinition("application")->addSetup(
            $bluescreenDef,
            ['UniMapper\Nette\Extension::renderException']
        );

        // Setup panel in debug mode
        if ($builder->parameters["debugMode"] && $config["panel"]) {

            $builder->getDefinition($this->prefix("panel"))
                ->addSetup($panelDef, ['@self']);

            // Register panel
            $builder->getDefinition("application")
                ->addSetup(
                    '$service->onStartup[] = ?',
                    [[$this->prefix("@panel"), "getTab"]]
                );
        }

        // Generate API
        if ($config["api"]["enabled"]) {

            // Iterate over services
            foreach ($builder->getDefinitions() as $serviceName => $serviceDefinition) {

                $class = $serviceDefinition->class !== null ? $serviceDefinition->class : $serviceDefinition->factory->entity;

                // Register repository to API's repository list
                if (class_exists($class) && is_subclass_of($class, "UniMapper\Repository")) {

                    $builder->getDefinition($this->prefix("repositories"))
                        ->addSetup('$service[] = $this->getService(?)', [$serviceName]);
                }
            }

            // Prepend API route
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

    public static function adapterCallback(\UniMapper\Adapter\IQuery $adapterQuery, $result)
    {
        \UniMapper\Profiler::log($adapterQuery, $result);
    }

    public static function beforeQueryCallback(\UniMapper\Query $query)
    {
        \UniMapper\Profiler::startQuery($query);
    }

    public static function afterQueryCallback(\UniMapper\Query $query, $result, $elapsed)
    {
        \UniMapper\Profiler::endQuery($result, $elapsed);
    }

}