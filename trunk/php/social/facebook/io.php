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
namespace Zopt\Social\Facebook;

require_once 'social/base.php';

// TODO: Future 2 optimizations:
// - multiCurl call
// - Facebook RTU
class FacebookBatchedRequests {
  private static $_defaultOptions = array(
      CURLOPT_HEADER => FALSE,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_POST => TRUE,
  );

  private $_batch = array();

  public function addRequest($method, $relativeUrl, $body = NULL, $reqId = NULL) {
    if (!is_null($reqId) && !is_string($reqId) && !is_numeric($reqId)) throw new \Zopt\Social\SocialException('FacebookBatchRequest.addRequest: wrong reqId format, should be string');
    if (!is_null($reqId) && isset($this->_batch[$reqId])) throw new \Zopt\Social\SocialException("FacebookBatchRequest.addRequest: reqId $reqId is already set.");
    if (is_null($reqId)) {
      $reqId = count($this->_batch);
      while (isset($this->_batch[$reqId])) $reqId++;
    }
    $method = strtoupper($method);
    if ($method !== 'GET' && $method !== 'POST' && $method !== 'DELETE') throw new \Zopt\Social\SocialException("FacebookBatchRequest.addRequest: wrong HTTP method $method.");
    $this->_batch[$reqId] = array(
        'method' => $method,
        'relative_url' => $relativeUrl,
    );
    if ($method === 'POST') $this->_batch[$reqId]['body'] = is_null($body) ? NULL : $body;
    return $reqId;
  }

  public function send($url, $authToken, $maxPerBatch) {
    $ret = array();
    $count = count($this->_batch);
    $offset = 0;
    while ($offset < $count) {
      // prepare query
      $queries = array_slice($this->_batch, $offset, $maxPerBatch, TRUE);
      $offset += $maxPerBatch;
      $body = array(
          'access_token' => $authToken,
          'batch' => json_encode(array_values($queries)),
      );

      // send query out
      $options = array(
          CURLOPT_URL => $url,
          CURLOPT_POSTFIELDS => http_build_query($body),
      );
      $ch = curl_init();
      curl_setopt_array($ch, ($options + self::$_defaultOptions));
      $results = curl_exec($ch);
      curl_close($ch);

      // parse the result
      if ($results === FALSE) continue;
      $results = json_decode($results, TRUE);
      if (isset($results['error'])) continue;
      $idx = 0;
      foreach ($queries as $reqId => $query) {
        $result = $results[$idx++];
        if ($result['code'] === 200) {
          $ret[$reqId] = $result['body'];
        }
      }
    }

    return $ret;
  }
}

