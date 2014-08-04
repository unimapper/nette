Unimapper Nette extension
=========================

[![Build Status](https://secure.travis-ci.org/bauer01/unimapper-nette.png?branch=master)](http://travis-ci.org/bauer01/unimapper-nette)

Official Unimapper extension for Nette framework.

## Install

```sh
$ composer require bauer01/unimapper-nette:@dev
```

### Nette 2.1.x

Register extension in `config.neon`.

```yml
extensions:
    unimapper: UniMapper\Nette\Extension
```

### Nette 2.0.x

Register extension in `app/bootstrap.php`.

```php
UniMapper\Nette\Extension::register($configurator);

return $configurator->createContainer();
```

## Configuration

```yml
unimapper:
    cache: false # (default = true)
    namingConvention:
        entity: 'YourApp\Model\*'
        repository: 'YourApp\Repository\*Repository'
    api:
        enabled: true
        module: "Api"
```