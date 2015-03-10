<?php

$loader = @include __DIR__ . '/../vendor/autoload.php';
if (!$loader) {
    echo 'Install Nette Tester using `composer update --dev`';
    exit(1);
}

Tester\Environment::setup();

date_default_timezone_set('Europe/Prague');

// create temporary directory
$tempDir = __DIR__ . '/temp/' . getmypid();
@mkdir(dirname($tempDir));
Tester\Helpers::purge($tempDir);

$configurator = new \Nette\Configurator;
$configurator->setDebugMode(false);
$configurator->setTempDirectory($tempDir);
$configurator->createRobotLoader()
    ->addDirectory(__DIR__ . '/fixtures/app')
    ->register();
$configurator->addConfig(__DIR__ . '/fixtures/app/config/config.neon');
