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

function isFuzzyExpired($cur, $expireUntil, $range = 10) {
  return !(($cur < $expireUntil - $range) || ($cur + rand(0, $range) < $expireUntil));
}

function getTtl($ttl, $maxTtl = 0) {
  if ($maxTtl === 0) return ($ttl === 0) ? 0 : $ttl;
  return ($ttl === 0) ? $maxTtl : min($ttl, $maxTtl);
}

class CacheException extends \Exception {
}

class CacheDetail {
  /**
   * @var mixed The cached data
   */
  public $val;

  /**
   * @var int The epoch time (in second) when this cache will expire
   *           0 means persistent caching
   */
  public $validUntil;

  public function __construct($val, $validUntil) {
    $this->val = $val;
    $this->validUntil = $validUntil;
  }

  /**
   * compare two cache details by its expire time.
   *
   * @param CacheDetail $lhs left-hand-side cache detail
   * @param CacheDetail $rhs right-hand-side cache detail
   * @return int 0 for equal, -1 for $lhs expires earlier than $rhs, vice versa.
   */
  public static function cmp($lhs, $rhs) {
    if ($lhs->validUntil === $rhs->validUntil) return 0;
    if ($lhs->validUntil === 0) return 1;
    if ($rhs->validUntil === 0) return -1;
    return ($lhs->validUntil < $rhs->validUntil) ? -1 : 1;
  }
}

interface CacheInterface {
  const MIN_EXPIRE = 2;  // 2 seconds

  /**
   * Set a value into cache with ttl
   *
   * @param string $key Key of the item
   *         array $values {$key: $val}.
   * @param mixed $val Value of the item, it's unused in the array mode
   * @param string $ttl Seconds that this cache will exist
   *                     0 means persistent cache if possible
   *                     Note that this is not guaranteed as the item may be evicted earlier due to the lack of resource
   * @return bool TRUE on success or FALSE on failure
   * @throws CacheException
   */
  public function set($key, $val, $ttl);

  /**
   * Set values into cache with ttl
   *
   * @param mixed[string] $values {$key: $val}.
   * @param string $ttl Seconds that this cache will exist
   *                     0 means persistent cache if possible
   *                     Note that this is not guaranteed as the item may be evicted earlier due to the lack of resource
   * @return string[] an array with error keys, or empty array if all success
   * @throws CacheException
   */
  public function setMulti($values, $ttl);

  /**
   * Get the value from cache
   *
   * @param string $key Key of the item that will be fetched
   * @param bool $returnDetail If returns the detail, default to false
   *
   * @return CacheDetail|mixed|bool The mixed value or CacheDetail if success, FALSE if it's not found or expired
   *          (above types)[string] if $key is an array. the item will not be included if it's not found or expired
   * @throws CacheException
   */
  public function get($key, $returnDetail);

  /**
   * Get values from cache
   *
   * @param string[] $keys Keys of the items that will be fetched
   * @param bool $returnDetail If returns the detail, default to false
   *
   * @return (CacheDetail|mixed)[string] An array of mixed value or CacheDetail if success, the item will not be inlcuded if it's not found or expired
   * @throws CacheException
   */
  public function getMulti($keys, $returnDetail);

  /**
   * Remove a possible existing item
   *
   * @param string $key Key of the item that will be removed
   *
   * @return bool TRUE on success or FALSE on failure
   * @throws CacheException
   */
  public function remove($key);

  /**
   * Remove possible existing items
   *
   * @param string[] $keys Keys of the items that will be removed
   *
   * @return string[] an array with error keys, or empty array if all success
   * @throws CacheException
   */
  public function removeMulti($keys);
}

class LayeredCache implements CacheInterface {
  private $_layers = array();

  /**
   * Add a new layer on the top of the existing cache
   *
   * @param CacheInterface $cache A new layer of the cache
   * @return int Returns the new number of layers
   * @throws CacheException
   */
  public function addLayer($cache) {
    return array_unshift($this->_layers, $cache);
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $val, $ttl = 0) {
    $ret = TRUE;
    foreach ($this->_layers as $cache) {
      $ret = $ret && $cache->set($key, $val, $ttl);
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function setMutli($values, $ttl = 0) {
    $ret = array();
    foreach ($this->_layers as $cache) {
      $ret = array_unique(array_merge($ret, $cache->setMulti($values, $ttl)));
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $returnDetail = FALSE) {
    for ($i=0, $count = count($this->_layers); $i<$count; $i++) {
      if (($detail = $this->_layers[$i]->get($key, TRUE)) !== FALSE) break;
    }
    if ($i === $count) return FALSE;
    $ttl = ($detail->validUntil === 0) ? 0 : $detail->validUntil - time();
    for ($i-=1; $i>=0; $i--) {
      if ($ttl > CacheInterface::MIN_EXPIRE) $this->_layers[$i]->set($key, $detail->val, $ttl);
    }
    return $returnDetail ? $detail : $detail->val;
  }

  /**
   * {@inheritdoc}
   */
  public function getMulti($keys, $returnDetail = FALSE) {
    $layeredDetails = array();
    $currentKeys = $keys;
    $count = count($this->_layers);
    for ($i=0; $i<$count; $i++) {
      $details = $this->_layers[$i]->getMulti($currentKeys, TRUE);
      $layeredDetails[] = $details;
      $currentKeys = array_diff($currentKeys, array_keys($details));
      if (empty($currentKeys)) break;
    }
    if ($i === $count) $i--;
    $details = array_pop($layeredDetails);
    $current = time();
    for ($i-=1; $i>=0; $i--) {
      uasort($details, 'CacheDetail::cmp');
      $batch = array();
      $ttl = 0;
      foreach ($details as $key => $detail) {
        if (($detail->validUntil !== 0) && (($detail->validUntil - $current) < CacheInterface::MIN_EXPIRE)) continue;
        if ((($ttl === 0) && ($detail->validUntil === 0)) ||
            (($ttl !== 0) && ($detail->validUntil !== 0) && ($detail->validUntil - $current - $ttl < CacheInterface::MIN_EXPIRE))) {
          $batch[$key] = $detail->val;
          continue;
        }
        if (!empty($batch)) {
          $this->_layers[$i]->setMulti($batch, $ttl);
          $batch = array($key => $detail);
          $ttl = ($detail->validUntil === 0) ? 0 : $detail->validUntil - $current;
        }
      }
      if (!empty($batch)) $this->_layers[$i]->setMulti($batch, $ttl);
      $details += array_pop($layeredDetails);
    }
    if ($returnDetail) return $details;
    $values = array();
    foreach ($details as $key => $detail) {
      $values[$key] = $detail->val;
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function remove($key) {
    $ret = TRUE;
    foreach ($this->_layers as $cache) {
      $ret = $ret && $cache->remove($key);
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function removesMulti($keys) {
    $ret = array();
    foreach ($this->_layers as $cache) {
      $ret = array_unique($ret + $cache->removeMulti($keys));
    }
    return $ret;
  }
}

