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

function addDefine($varName, $varVal) {
  is_string($varName) or die("The definition of $varName is not a string.");
  if (!defined($varName)) {
    define($varName, $varVal);
    return true;
  }
  return false;
}

function getHashs($str, $count) {
  $ret = array();
  for ($i=0; $i<$count; $i++) {
    $ret[] = crc32($str);
    $str .= 'h@5hM0r3';  // magic string, 'hashmore'
  }
  return $ret;
}
