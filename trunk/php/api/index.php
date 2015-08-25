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

/**
 * Sample request:
 * - api.foo.bar.com/?payload=%7B"requests"%3A%5B"%7B%5C"id%5C"%3A1%2C%5C"handler%5C"%3A%5C"sample.hello%5C"%7D"%5D%2C"authToken"%3A""%7D
 *
 * Sample response:
 * - {"responses":{"1":"{\"data\":\"hello world!\",\"error\":null}"},"error":null}
 */

// some setups
error_reporting(E_ALL);
// $include_path = get_include_path() . ':_MYLOCAL_/zoptimizer/php';  // TODO: load the correct php_include location
// set_include_path($include_path);

// start
ob_start();

require_once 'api/api.php';

// CORS support
$origin = (isset($_SERVER['HTTP_ORIGIN'])) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: $origin", true);
header('Access-Control-Allow-Methods: POST, GET', true);
header('Access-Control-Allow-Headers: Content-Type', true);
header('Access-Control-Allow-Credentials: true', true);

function errorResult($error) {
  $result = new Result();
  $result->error = $error;
  echo Result::stringify($result);
  ob_flush();
  die();
}

// Die if the request is an option method.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  errorResult('Request method OPTION is not supported.');
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (($input = @file_get_contents('php://input')) === FALSE) errorResult('POST query without postbody.');
} elseif (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
  if (!isset($_GET['payload']) || ($input = $_GET['payload']) === FALSE) errorResult('GET query without "payload" param.');
} else {
  errorResult('Can not detect the HTTP method of incoming query.');
}

try {
  $query = Query::parse($input);
} catch (ServerException $e) {
  errorResult($e->getMessage());
}

$server = new Server();

// services
require_once 'api/sample.php';
$sampleService = new SampleService();
$server->register('sample', $sampleService);

// run
$result = $server->run($query);
echo Result::stringify($result);
ob_flush();
