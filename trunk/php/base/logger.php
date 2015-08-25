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

require_once 'Log.php';
require_once 'base/base.php';

addDefine('ZOPT_LOG_LEVEL', PEAR_LOG_INFO);

class Logger extends \Log {
  private static $_defaultConfig = array(
      'timeFormat' => '%Y-%m-%d %H:%M:%S'
  );

  private static function normalizeClassName($className) {
    is_string($className) or die("The definition of $className is not a string.");
    return str_replace('\\', '::', $className);
  }

  public static function getLogger($obj = '', $conf = NULL) {
    $className = is_string($obj) ? $obj : get_class($obj);
    $className = self::normalizeClassName($className);
    $conf = is_null($conf) ? self::$_defaultConfig : $conf;
    $logger = self::singleton('console', $className, $className, $conf, ZOPT_LOG_LEVEL);
    return $logger;
  }
}

