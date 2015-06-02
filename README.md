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
    namingConvention:
        entity: 'YourApp\Model\*'
        repository: 'YourApp\Repository\*Repository'
    api:
        enabled: false
        module: "Api"
    panel:
        enabled: true
        ajax: true # log queries in AJAX requests
    profiler: true
    customQueries:
        - CustomQueryClass
        - ...
```

## Api

Creating api is very easy, all you need is presenter for every entity you have. Remember that every API presenter should always extend **UniMapper\Nette\Api\Presenter**.

Example:
```php
namespace YourApp\ApiModule\Presenter;

class EntityPresenter extends \UniMapper\Nette\Api\Presenter
{
    ...
}
```

Now you can call standard API methods like:
### GET /api/entity
Get all records with following optional parameters:
- **where** [filter](http://unimapper.github.io/docs/reference/repository/#filtering-data) in valid JSON format
- **associate** list of associations to join as `array`, syntax should look like `?associate[]=property1&associate[]=property2`
- **count** default is `false`, if `true` set then items count number will be returned in body of response

### GET /api/entity/1
Get a single record with following optional parameters:
- **associate** list of associations to join as `array`, syntax should look like `?associate[]=property1&associate[]=property2`

### PUT /api/entity/1
Update record with JSON data stored in request body.

### POST /api/entity
Create a new record with JSON data stored in request body.

### Custom API methods
You can even define your custom method.

Example:
```php
namespace YourApp\ApiModule\Presenter;

class EntityPresenter extends \UniMapper\Nette\Api\Presenter
{
    public function actionCustom($id)
    {
        ...
    }
}
```
Then you can make a requests like **/api/entity/1?action=custom**.

### Generating links
In your templates just use standard Nette link macro.

- `{link :Api:Entity:get}`
- `{link :Api:Entity:get 1}`
- `{link :Api:Entity:put 1}`
- `{link :Api:Entity:post}`
- `{link :Api:Entity:action}`