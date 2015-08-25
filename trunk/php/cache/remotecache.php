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
namespace Zopt\Cache;

require_once 'cache/cache.php';

class MemCache implements CacheInterface {
  /**
   * @var Memcached the memcache client
   */
  private $_client = NULL;

  /**
   * @var int The max expire time. 0 means never expire.
   */
  private $_maxExpire = 0;

  /**
   * @var bool Is fuzzy expire to avoid updating at the same time
   */
  private $_isFuzzy = FALSE;

  /**
   * @var string The identifier of this cache to avoid collision
   */
  private $_id = NULL;

  public function __construct($client, $id = NULL, $maxExpire = 43200, $isFuzzy = TRUE) {  // 43200 seconds = 12 hrs
    $this->_client = $client;
    $this->_id = (!is_null($id) && is_string($id)) ? $id : 'mc_' . dechex(crc32('MC' . time() . rand()));
    $this->_maxExpire = $maxExpire;
    $this->_isFuzzy = $isFuzzy;
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $val, $ttl = 0) {
    $internalKey = $this->_id . $key;
    $ttl = getTtl($ttl, $this->_maxExpire);
    $current = time();
    $detail = new CacheDetail($val, ($ttl === 0) ? 0 : $ttl + $current);
    return $this->_client->set($internalKey, $detail, $ttl);
  }

  /**
   * {@inheritdoc}
   */
  public function setMulti($values, $ttl = 0) {
    $current = time();
    $ttl = getTtl($ttl, $this->_maxExpire);
    $vals = array();
    foreach ($values as $key => $val) {
      $internalKey = $this->_id . $key;
      $detail = new CacheDetail($val, ($ttl === 0) ? 0 : $ttl + $current);
      $vals[$internalKey] = $detail;
    }
    return $this->_client->setMulti($vals, $ttl);
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $returnDetail = FALSE) {
    $internalKey = $this->_id . $key;
    if (($detail = $this->_client->get($internalKey)) === FALSE) return FALSE;
    if ($detail->validUntil !== 0) {
      $current = time();
      if ($detail->validUntil < $current) {
        $this->_client->delete($internalKey);
        return FALSE;
      }
      if ($this->_isFuzzy && isFuzzyExpired($current, $detail->validUntil, $this->_maxExpire / 10)) return FALSE;
    }
    return ($returnDetail) ? $detail : $detail->val;
  }

  /**
   * {@inheritdoc}
   */
  public function getMulti($keys, $returnDetail = FALSE) {
    $current = time();
    $internalKeys = array();
    foreach ($keys as $key) {
      $internalKeys[$this->_id . $key] = $key;
    }
    $details = $this->_client->getMulti(array_keys($internalKeys));
    $results = array();
    foreach ($details as $internalKey => $detail) {
      if ($detail->validUntil !== 0) {
        if ($detail->validUntil < $current) {
          $this->_client->delete($internalKey);
          continue;
        }
        if ($this->_isFuzzy && isFuzzyExpired($current, $detail->validUntil, $this->_maxExpire / 10)) continue;
      }
      $results[$internalKeys[$internalKey]] = $returnDetail ? $detail : $detail->val;
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function remove($key) {
    $internalKey = $this->_id . $key;
    return $this->_client->delete($internalKey);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMulti($keys) {
    $res = array();
    foreach ($keys as $key) {
      $internalKey = $this->_id . $key;
      if (!$this->_client->delete($internalKey)) $res[] = $key;
    }
    return $res;
  }
}
