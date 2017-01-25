<?php

namespace UniMapper\Nette;

use Nette\Configurator;
use Nette\Diagnostics\BlueScreen;
use Nette\Diagnostics\Helpers;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;

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
        "panel" => [
            "enabled" => true,
            "ajax" => true
        ],
        "profiler" => true,
        "cache" => true,
        "convention" => [
            "repository" => null,
            "entity" => null
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

        // Create cache
        if ($config["cache"]) {
            $builder->addDefinition($this->prefix("cache"))->setClass("UniMapper\Nette\Cache");
        }

        // Create mapper
        $builder->addDefinition($this->prefix("mapper"))->setClass("UniMapper\Mapper");

        // Create query builder
        $builder->addDefinition($this->prefix("connection"))->setClass("UniMapper\Connection");

        // Debug mode
        if ($builder->parameters["debugMode"] && $config["panel"]["enabled"]) {

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

            // Register adapters
            $builder->getDefinition($this->prefix("connection"))
                ->addSetup("registerAdapter", array($adapterName, $adapterService));
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
    }

    public function afterCompile(ClassType $class)
    {
        $config = $this->getConfig($this->defaults);
        $initialize = $class->methods['initialize'];

        // Naming convention
        if ($config["convention"]["entity"]) {
            $initialize->addBody(
                'UniMapper\Convention::setMask(?, UniMapper\Convention::ENTITY_MASK);',
                [$config["convention"]["entity"]]
            );
        }
        if ($config["convention"]["repository"]) {
            $initialize->addBody(
                'UniMapper\Convention::setMask(?, UniMapper\Convention::REPOSITORY_MASK);',
                [$config["convention"]["repository"]]
            );
        }

        // Register custom queries
        if (is_array($config["customQueries"])) {
            foreach ($config["customQueries"] as $class) {
                $initialize->addBody('UniMapper\QueryBuilder::registerQuery(?);', array($class));
            }
        }

        // Set profiler
        if ($config["profiler"]) {
            $initialize->addBody('UniMapper\QueryBuilder::beforeRun(array(?, "beforeQueryCallback"));', array(get_class()));
            $initialize->addBody('UniMapper\QueryBuilder::afterRun(array(?, "afterQueryCallback"));', array(get_class()));
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
        if ($exception instanceof \UniMapper\Exception\EntityException
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
        } elseif ($exception instanceof \UniMapper\Exception\InvalidArgumentException
            && $exception->getValue() !== null
        ) {
            return [
                "tab" => "Value given",
                "panel" => self::dump($exception->getValue())
            ];
        } elseif ($exception instanceof \UniMapper\Exception\AdapterException
            && $exception->getQuery() !== null
        ) {
            return [
                "tab" => "Query",
                "panel" => self::dump($exception->getQuery())
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

    public static function dump($variable, $collapsed = false)
    {
        if (class_exists('Nette\Diagnostics\Dumper')) {
            return \Nette\Diagnostics\Dumper::toHtml(
                $variable,
                [\Nette\Diagnostics\Dumper::COLLAPSE => $collapsed]
            );
        }

        // Nette 2.0 back compatibility
        return \Nette\Diagnostics\Helpers::clickableDump($variable, $collapsed);
    }

}