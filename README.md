Unimapper Nette extension
=========================

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
```