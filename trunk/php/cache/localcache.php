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

class LocalCache implements CacheInterface {
  /**
   * @var int The max capacity
   */
  private static $_maxCapacity = 4096;  // 4096 slots

  /**
   * @var int The max expire time. 0 means never expire.
   */
  private $_maxExpire = 0;

  /**
   * @var bool Is fuzzy expire to avoid updating at the same time
   */
  private $_isFuzzy = FALSE;

  /**
   * @var CacheDetail[] The container for items
   */
  private $_items;

  public function __construct($maxExpire = 0, $isFuzzy = FALSE) {
    $this->_maxExpire = $maxExpire;
    $this->_isFuzzy = $isFuzzy;
  }

  private function _cleanBefore($time) {
    if (count($this->_items) < self::$_maxCapacity) return;
    uasort($this->_items, 'CacheDetail::cmp');
    if ($this->_maxExpire === 0) {
      $i = -1;
    } else {
      for ($count=count($this->_items), $i=$count-1; $i>=0; $i--) {
        $validUntil = $this->_items[$i]->validUntil;
        if (($validUntil !== 0) && ($validUntil < $time)) break;
      }
    }
    $remains = min($count - $i - 1, self::$_maxCapacity / 2);
    $this->_items = array_slice($this->_items, $count-$remains, $remains, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $val, $ttl = 0) {
    $current = time();
    $ttl = getTtl($ttl, $this->_maxExpire);
    $this->_items[$key] = new CacheDetail($val, ($ttl === 0) ? 0 : $ttl + $current);
    $this->_cleanBefore($current);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setMulti($values, $ttl = 0) {
    $current = time();
    foreach ($values as $key => $val) {
      $ttl = getTtl($ttl, $this->_maxExpire);
      $this->_items[$key] = new CacheDetail($val, ($ttl === 0) ? 0 : $ttl + $current);
    }
    $this->_cleanBefore($current);
    return array_keys($values);
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $returnDetail = FALSE) {
    if (!isset($this->_items[$key])) return FALSE;
    if ($this->_items[$key]->validUntil !== 0) {
      $current = time();
      if ($this->_items[$key]->validUntil < $current) {
        unset($this->_items[$key]);
        return FALSE;
      }
      if ($this->_isFuzzy && isFuzzyExpired($current, $this->_items[$key]->validUntil, $this->_maxExpire / 10)) return FALSE;
    }
    return ($returnDetail) ? $this->_items[$key] : $this->_items[$key]->val;
  }

  /**
   * {@inheritdoc}
   */
  public function getMulti($keys, $returnDetail = FALSE) {
    $current = time();
    $results = array();
    foreach ($keys as $key) {
      if (!isset($this->_items[$key])) continue;
      if ($this->_items[$key]->validUntil !== 0) {
        if ($this->_items[$key]->validUntil < $current) {
          unset($this->_items[$key]);
          continue;
        }
        if ($this->_isFuzzy && isFuzzyExpired($current, $this->_items[$key]->validUntil, $this->_maxExpire / 10)) continue;
      }
      $results[$key] = $returnDetail ? $this->_items[$key] : $this->_items[$key]->val;
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function remove($key) {
    unset($this->_items[$key]);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeMulti($keys) {
    foreach ($keys as $key) {
      unset($this->_items[$key]);
    }
    return $keys;
  }
}

class ApcCache implements CacheInterface {
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

  public function __construct($id = NULL, $maxExpire = 7200, $isFuzzy = TRUE) {  // 7200 seconds = 2 hrs
    $this->_id = (!is_null($id) && is_string($id)) ? $id : 'ac_' . dechex(crc32('AC' . time() . rand()));
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
    apc_store($internalKey, $detail, $ttl);
    return TRUE;
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
    apc_store($vals, NULL, $ttl);
    return array_keys($values);
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $returnDetail = FALSE) {
    $internalKey = $this->_id . $key;
    if (($detail = apc_fetch($internalKey)) === FALSE) return FALSE;
    if ($detail->validUntil !== 0) {
      $current = time();
      if ($detail->validUntil < $current) {
        apc_delete($internalKey);
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
    $removing = array();
    $internalKeys = array();
    foreach ($keys as $key) {
      $internalKeys[$this->_id . $key] = $key;
    }
    $details = apc_fetch(array_keys($internalKeys));
    $results = array();
    foreach ($details as $internalKey => $detail) {
      if ($detail->validUntil !== 0) {
        if ($detail->validUntil < $current) {
          $removing[] = $internalKey;
          continue;
        }
        if ($this->_isFuzzy && isFuzzyExpired($current, $detail->validUntil, $this->_maxExpire / 10)) continue;
      }
      $results[$internalKeys[$internalKey]] = $returnDetail ? $detail : $detail->val;
    }
    if (!empty($removing)) apc_delete($removing);
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function remove($key) {
    $internalKey = $this->_id . $key;
    return apc_delete($internalKey);
  }

  /**
   * {@inheritdoc}
   */
  public function removeMulti($keys) {
    $internalKeys = array();
    foreach ($keys as $key) {
      $internalKeys[] = $this->_id . $key;
    }
    return apc_delete($internalKeys);
  }
}
