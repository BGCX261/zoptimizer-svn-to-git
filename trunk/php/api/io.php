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

// Individual request to an service.method
class Request {
  /**
   * @var string Request ID, unique for requests from the same source
   */
  public $id = NULL;

  /**
   * @var string Endpoint of the request, with format "service.method"
   */
  public $handler = NULL;

  /**
   * @var mixed Key-value pairs serve the method
   */
  public $params = NULL;

  public function __construct($id, $handler, $params) {
    $this->id = $id;
    $this->handler = $handler;
    $this->params = $params;
  }

  /**
   * parse from an string
   *
   * @param string $input The request data in JSON format
   * @return Request parsed request
   */
  public static function parse($input) {
    if (($input = json_decode($input, true)) === FALSE ||
        !isset($input['id']) || !is_scalar($input['id']) ||
        !isset($input['handler']) || (substr_count($input['handler'], '.') !== 1)) {
      throw new ServerException('Request.parse: malformat request.');
    }
    $request = new Request(
      $input['id'],
      $input['handler'],
      isset($input['params']) ? $input['params'] : array()
    );
    return $request;
  }
}

class Response {
  public $data = NULL;
  public $error = NULL;

  public function __construct($data, $error) {
    $this->data = $data;
    $this->error = $error;
  }

  public static function failResponse($error) {
    return new Response(NULL, $error);
  }

  public static function successResponse($data) {
    return new Response($data, NULL);
  }

  /**
   * stringify to a string
   *
   * @param Response $response The response will be serialized
   * @return string The serialized response
   */
  public static function stringify($response) {
    $output = array();
    if (!is_null($response->data)) $output['data'] = $response->data;
    if (!is_null($response->error)) $output['error'] = $response->error;
    return json_encode($output);
  }
}

class AuthToken {
  public static function parse($input) {
    return new AuthToken();
    throw new ServerException('AuthToken.parse: malformat token.');
  }
}

// A query to the api endpoint, and it may includes multiple requests to service.method
class Query {
  /**
   * @var Request[] Batched requests array
   */
  public $requests = NULL;

  /**
   * @var AuthToken The authentication token, as the context
   */
  public $authToken = NULL;

  public static function parse($input) {
    if (($input = json_decode($input, true)) === FALSE ||
        !isset($input['requests']) || !is_array($input['requests']) ||
        !isset($input['authToken'])) {
      throw new ServerException('Query.parse: malformat query.');
    }
    $query = new Query();
    $query->authToken = AuthToken::parse($input['authToken']);
    $query->requests = array();
    foreach ($input['requests'] as $request) {
      $query->requests[] = Request::parse($request);
    }
    return $query;
  }
}

class Result {
  /**
   * @var Request[string] Responses keyed by their ID.
   */
  public $responses = NULL;
  public $error = NULL;

  /**
   * stringify to a string
   *
   * @param Result $result The result will be serialized
   * @return string The serialized result
   */
  public static function stringify($result) {
    $output = array();
    if (!is_null($result->error)) $output['error'] = $result->error;
    if (is_null($result->responses)) return json_encode($output);
    $output['responses'] = array();
    foreach ($result->responses as $id => $response) {
      $output['responses'][$id] = Response::stringify($response);
    }
    return json_encode($output);
  }
}