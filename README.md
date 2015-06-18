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
        route: true
    panel:
        enabled: true
        ajax: true # log queries in AJAX requests
    profiler: true
    customQueries:
        - CustomQueryClass
        - ...
```

## API

Creating new API for your application is very easy, all you need is presenter for every entity you have.

Remember that every API presenter should always extend **UniMapper\Nette\Api\Presenter**.

```php
namespace YourApp\ApiModule\Presenter;

class EntityPresenter extends \UniMapper\Nette\Api\Presenter
{
    ...
}
```

Now you can call standard API methods like:

### GET

- associate - common parameter used to tell which association should be included in response. Syntax should be like `?associate[]=property1&associate[]=property2` or `?associate=property1,property2`.

** Response**

```json
{
    "body": {..}
}
```

#### /api/entity/id
Get a single record.

#### /api/entity
Get all records.

- count - optional parameter, if `?count=true` set then items count number will be returned in response body instead data.
- limit - maximum limit is  set to `10`. You can change it by overriding property `$maxLimit` in your API presenter descendant.
- offset
- [where](#filtering-data)

### PUT

#### /api/entity
Update all records with JSON data stored in request body. [Filtering](#filtering-data) can be set and response body contains number of affected records.

- [where](#filtering-data)

** Response**

```json
{
    "body": 3,
    "success": true
}
```

#### /api/entity/id
Update single record with JSON data stored in request body.

** Response**

```json
{
    "success": true
}
```

### POST
Create new record with JSON data stored in request body and primary value of new entity returned in response body.

#### /api/entity

** Response**

```json
{
    "success": true,
    "link": "url to created entity",
    "body": "id of created entity"
}
```

### DELETE

#### /api/entity
Delete all records returned body contains number of deleted records.

- [where](#filtering-data)

** Response**

```json
{
    "body": {..}
    "success": true
}
```

#### /api/entity/id
Delete single record.

** Response**

```json
{
    "success": true
}
```

### Custom API methods
You can even define your custom method.

```php
namespace YourApp\ApiModule\Presenter;

class EntityPresenter extends \UniMapper\Nette\Api\Presenter
{
    public function actionYourCustomMethod($id)
    {
        ...
    }
}
```
Then you can make a requests like `/api/entity/1?action=yourCustomMehod`.

### Filtering data
Filter can be set as a GET parameter `where` in URL. It  should be here a valid JSON format as described [here](http://unimapper.github.io/docs/reference/repository/#filtering-data).

### Error response
If some bad request detected or an error occurred the returned response can be like this:

```json
{
    "success": false
    "code": 405,
    "messages": []
}
```

### Generating links
In your templates just use standard Nette link macro.

- `{link :Api:Entity:get}`
- `{link :Api:Entity:get 1}`
- `{link :Api:Entity:put 1}`
- `{link :Api:Entity:post}`
- `{link :Api:Entity:action}`

### Usage
You can even build another applications using this API, just register an official API adapter class `UniMapper\Nette\Api\Adapter` in your **config.neon**.

#### Custom request factory

For easier API queries you can register factory interface as a dynamic service in your **config.neon**.

```yml
services:
    - UniMapper\Nette\Api\ICustomRequestFactory
```

Usage in your reopistory can look like this:

```php
class SomeRepository extends \UniMapper\Repository
{
    private $requestFactory;

    public function __construct(
        \UniMapper\Connection $connection,
        \UniMapper\Nette\Api\ICustomRequestFactory $requestFactory
    ) {
        parent::__construct($connection);
        $this->requestFactory;
    }

    public function getSomethingFromApi()
    {
        $this->requestFactory()->setResource("apiResource")->setAction("custom")->send();
    }
}
```