<?php
/***
 * Copyright (c) 2012, Zoptimizer, LightyBolt
 * All rights reserved.
 *
 * This work is licensed under
 * the Creative Commons Attribution-NonCommercial-NoDerivs 3.0 Unported License.
 *
 * To view a copy of this license, visit
 *
 *   http://creativecommons.org/licenses/by-nc-nd/3.0/
 *
 * or send a letter to
 *
 *   Creative Commons, 444 Castro Street, Suite 900,
 *   Mountain View, California, 94041, USA.
 */
namespace Zopt\Api;

require_once 'api/io.php';

class ServerException extends \Exception {
}

class Server {
  private $_services = array();

  /**
   * Register a service to the server.
   *
   * @param string $name The service name that will be referred to
   * @param ServiceInterface $service The service object that handles the request
   * @return ServiceInterface|bool ServiceInterface if a previous service exists, FALSE if not
   */
  public function register($name, $service) {
    $ret = isset($this->_services[$name]) ? $this->_services[$name] : FALSE;
    $this->_services[$name] = $service;
    return $ret;
  }

  /**
   * Run the server with a query, which may includes multiple requests
   *
   * @param Query $query The input query object
   * @return Result the output result object
   */
  public function run($query) {
    $result = new Result();
    $result->responses = array();
    foreach ($query->requests as $request) {
      try {
        $handler = explode('.', $request->handler);
        if (!isset($this->_services[$handler[0]])) throw new ServerException("Service $handler[0] is not registered");
        $response = call_user_func(
          array($this->_services[$handler[0]], $handler[1]),
          $request->params,
          $query->authToken
        );
        $result->responses[$request->id] = Response::successResponse($response);
      } catch (ServerException $e) {
        $result->responses[$request->id] = Response::failResponse($e->getMessage());
      }
    }
    return $result;
  }
}

class Service {
  public static function getArgs($params, $list) {
    $ret = array();
    foreach ($list as $item) {
      if (!isset($params[$item])) throw new ServerException("The must-have argument $item is not found.");
      $ret[] = $params[$item];
    }
    return $ret;
  }

  public static function getOptArgs($params, $list) {
    $ret = array();
    foreach ($list as $item => $default) {
      $ret[] = isset($params[$item]) ? $params[$item] : $default;
    }
    return $ret;
  }
}
