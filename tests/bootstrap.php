<?php

$loader = @include __DIR__ . '/../vendor/autoload.php';
if (!$loader) {
    echo 'Install Nette Tester using `composer update --dev`';
    exit(1);
}

// @todo
//$loader->addPsr4("UniMapper\Tests\Fixtures\\", __DIR__ . "/fixtures");

Tester\Environment::setup();

date_default_timezone_set('Europe/Prague');

$configurator = new \Nette\Configurator;
$configurator->setDebugMode(false);
$configurator->setTempDirectory(__DIR__ . '/temp');
$configurator->createRobotLoader()
    ->addDirectory(__DIR__ . '/fixtures/app')
    ->register();
$configurator->addConfig(__DIR__ . '/fixtures/app/config/config.neon');