Unimapper Nette extension
=========================

[![Build Status](https://secure.travis-ci.org/unimapper/nette.png?branch=master)](http://travis-ci.org/unimapper/nette)

Official Unimapper extension for Nette framework.

## Install

```sh
$ composer require unimapper/nette:@dev
```

### Nette 2.1 and higher

Register extension in `config.neon`.

```yml
extensions:
    unimapper: UniMapper\Nette\Extension
```

### Nette 2.0

Register extension in `app/bootstrap.php`.

```php
UniMapper\Nette\Extension::register($configurator);

return $configurator->createContainer();
```

## Configuration

```yml
unimapper:
    adapters:
        Mongo: @service
        MySQL: @anotherService
        ...
    cache: true
    convention:
        entity: 'YourApp\Model\*'
        repository: 'YourApp\Repository\*Repository'
    panel:
        enabled: true
        ajax: true # log queries in AJAX requests
    profiler: true
    customQueries:
        - CustomQueryClass
        - ...
```