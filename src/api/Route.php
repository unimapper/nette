<?php

namespace UniMapper\Nette\Api;

use Nette\Http\Request as HttpRequest;
use Nette\Http\IRequest;
use Nette\Http\Url;
use Nette\Application\Request;
use Nette\InvalidStateException;
use Nette\Utils\Strings;

/**
 * @author Adam Štipák
 */
class Route extends \Nette\Application\Routers\Route
{

    protected $module;

    protected $actions = [
        HttpRequest::GET => "get",
        HttpRequest::POST => "post",
        HttpRequest::PUT => "put",
        HttpRequest::DELETE => "delete"
    ];

    public function __construct($module = null)
    {
        $this->module = $module;
    }

    public function match(IRequest $httpRequest)
    {
        $url = $httpRequest->getUrl();

        $basePath = Strings::replace($url->getBasePath(), '/\//', '\/');
        $cleanPath = Strings::replace($url->getPath(), "/^" . $basePath . "/");

        $path = Strings::replace($this->_getPath(), '/\//', '\/');
        $pathRexExp = empty($path) ? "/^.+$/" : "/^" . $path . "\/.*$/";
        if (!Strings::match($cleanPath, $pathRexExp)) {
            return null;
        }

        $params = $httpRequest->getQuery();

        // Get presenter action
        if (!isset($params['action']) || empty($params['action'])) {
            $params['action'] = $this->_detectAction($httpRequest);
        }

        $frags = explode('/', Strings::replace($cleanPath, '/^' . $path . '\//'));

        $presenterName = Strings::firstUpper($frags[0]);

        // Set 'id' parameter if not custom action
        if (isset($frags[1]) && $this->_isApiAction($params['action'])) {
            $params['id'] = $frags[1];
        }

        return new Request(
            empty($this->module) ? $presenterName : $this->module . ':' . $presenterName,
            $httpRequest->getMethod(),
            $params
        );
    }

    private function _detectAction(HttpRequest $request)
    {
        $method = $request->getMethod();
        if (isset($this->actions[$method])) {
            return $this->actions[$method];
        }

        throw new InvalidStateException('Method ' . $method . ' is not allowed.');
    }

    private function _getPath()
    {
        return (string) Strings::lower(
            implode('/', explode(':', $this->module))
        );
    }

    public function constructUrl(Request $appRequest, Url $refUrl)
    {
        // Module prefix not match.
        if ($this->module && !Strings::startsWith($appRequest->getPresenterName(), $this->module)) {
            return null;
        }

        $params = $appRequest->getParameters();

        $urlStack = [];

        // Module prefix
        $moduleFrags = explode(":", Strings::lower($appRequest->getPresenterName()));
        $resourceName = array_pop($moduleFrags);
        $urlStack += $moduleFrags;

        // Resource
        $urlStack[] = Strings::lower($resourceName);

        // Id
        if (isset($params['id']) && is_scalar($params['id'])) {
            $urlStack[] = $params['id'];
            unset($params['id']);
        }

        // Set custom action
        if (isset($params['action']) && $this->_isApiAction($params['action'])) {
            unset($params['action']);
        }

        $url = $refUrl->getBaseUrl() . implode('/', $urlStack);

        // Add query parameters
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }

        return $url;
    }

    private function _isApiAction($name)
    {
        return array_search($name, $this->actions);
    }

}