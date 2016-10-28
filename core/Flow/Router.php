<?php

namespace Simples\Core\Flow;

use Simples\Controller;
use Simples\Core\Gateway\Request;
use Simples\Core\Gateway\Response;

/**
 * Class Router
 * @package Simples\Core\Flow
 */
class Router
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var array
     */
    private $routes = [];

    /**
     * @var object
     */
    private $route;

    /**
     * Router constructor.
     * @param Request $request
     * @param Response $response
     */
    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $this->on($name, $arguments[0], $arguments[1]);
    }

    /**
     * @param $method
     * @param $uri
     * @param $callback
     */
    public function on($method, $uri, $callback)
    {
        $methods = explode(',', $method);
        if ($method === '*') {
            $methods = ['get', 'post', 'put', 'patch', 'delete'];
        }

        foreach ($methods as $method) {

            $method = strtoupper($method);
            if (!isset($this->routes[$method])) {
                $this->routes[$method] = [];
            }
            $peaces = explode('/', $uri);
            foreach ($peaces as $key => $value) {
                $peaces[$key] = str_replace('*', '(.*)', $peaces[$key]);
                if (strpos($value, ':') === 0) {
                    $peaces[$key] = '(\w+)';
                }
            }
            if ($peaces[(count($peaces) - 1)]) {
                $peaces[] = '';
            }
            $pattern = str_replace('/', '\/', implode('/', $peaces));
            $route = '/^' . $pattern . '$/';

            $this->routes[$method][$route] = $callback;
        }
    }

    /**
     * @param $uri
     * @param $class
     */
    public function resource($uri, $class)
    {
        $resource = [
            ['method' => 'GET', 'uri' => 'index', 'callable' => 'index'],

            ['method' => 'GET', 'uri' => '', 'callable' => 'index'],
            ['method' => 'GET', 'uri' => 'create', 'callable' => 'create'],
            ['method' => 'GET', 'uri' => ':id', 'callable' => 'show'],
            ['method' => 'GET', 'uri' => ':id/edit', 'callable' => 'edit'],

            ['method' => 'POST', 'uri' => '', 'callable' => 'store'],
            ['method' => 'PUT,PATCH', 'uri' => ':id', 'callable' => 'update'],
            ['method' => 'DELETE', 'uri' => ':id', 'callable' => 'destroy'],
        ];
        foreach ($resource as $item) {
            $item = (object)$item;
            $this->on($item->method, $uri . '/' . $item->uri, $class . '@' . $item->callable);
        }
    }

    /**
     * @param $method
     * @param $callback
     */
    public function otherWise($method, $callback)
    {
        $this->on($method, '/(.*)', $callback);
    }

    /**
     * @return mixed
     */
    public function run()
    {
        $method = $this->request->getMethod();
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route => $callback) {
            if (preg_match($route, $this->request->getUri(), $params)) {

                array_shift($params);
                $this->route = (object)['method' => $method, 'uri' => $this->request->getUri(), 'route' => $route, 'callback' => $callback];

                return $this->resolve($callback, array_values($params));
            }
        }

        return null;
    }

    /**
     * @param $callback
     * @param $params
     * @return mixed
     */
    private function resolve($callback, $params)
    {
        if (!is_callable($callback)) {
            $peaces = explode('@', $callback);
            if (!isset($peaces[1])) {
                return null;
            }
            $class = $peaces[0];
            $method = $peaces[1];

            if (method_exists($class, $method)) {

                /** @var Controller $controller */
                $controller = new $class($this->request(), $this->response(), $this->route);

                $callback = [$controller, $method];
            }
        }
        return call_user_func_array($callback, $params);
    }

    /**
     * @return Request
     */
    public function request()
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * @return object
     */
    public function route()
    {
        return $this->route;
    }
}
