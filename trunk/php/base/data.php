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
namespace Zopt\Base;

class DataClazz {
  protected static $_metaBasic = array();
  protected static $_metaAll = array();

  /**
   * Protected constructor to prevent "new" a sub data class.  However, the
   * subclass can overwrite it by re-define the constructor in public scope.
   */
  protected function __construct() {}

  /**
   * Returns the data blob
   *
   * @param DataClazz $obj the object will be output
   * @param string[]|bool $fields The fields that the user info blob returns
   *                       TRUE: all possible fields
   *                       FALSE, array(): default fields (default)
   *                       array(field): given fields
   * @return array the parsed blob
   */
  public static function fromArray($clazz, $blob) {
    if (!is_array($blob)) return NULL;
    foreach ($clazz::$_metaBasic as $field) {
      if (!isset($blob[$field])) return NULL;
    }
    $ret = new $clazz();
    foreach ($clazz::$_metaAll as $field) {
      if (isset($blob[$field])) $ret->$field = $blob[$field];
    }
    return $ret;
  }

  /**
   * Returns the data blob
   *
   * @param DataClazz $obj the object will be output
   * @param string[]|bool $fields The fields that the user info blob returns
   *                       TRUE: all possible fields
   *                       FALSE, array(): default fields (default)
   *                       array(field): given fields\
   * @return array the parsed blob
   */
  public static function toArray($obj, $fields = FALSE) {
    if ($fields === TRUE) return (array)$obj;
    $ret = array();
    if (is_array($fields) && !empty($fields)) {
      foreach ($fields as $field) {
        if (in_array($field, $obj::$_metaAll) && !is_null($obj->$field)) $ret[$field] = $obj->$field;
      }
      return $ret;
    }
    foreach ($obj::$_metaBasic as $field) {
      if (!is_null($obj->$field)) $ret[$field] = $obj->$field;
    }
    return $ret;
  }
}