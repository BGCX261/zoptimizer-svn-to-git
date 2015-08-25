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
namespace Zopt\Social\Sample;

require_once 'social/base.php';
require_once 'social/provider.php';
require_once 'social/user.php';

// Sample local provider
class SampleProvider implements \Zopt\Social\SocialProviderInterface {
  public function getUsers($uids, $ego = NULL) {
    $ret = array();
    foreach ($uids as $uid) {
      $ret[$uid] = \Zopt\Social\User::fromArray(array(
          'id' => '$uid',
          'name' => 'Dummy User',
          'picture' => 'http://dummy.com/avatar.jpg',
      ));
    }
    return $ret;
  }

  public function getFriends($identity, $excludes = array(), $includes = array(), $criteria = NULL, $offset = 0, $count = 40) {
    return array();
  }

  public function getUserPosts($userIds, $ego = NULL) {
    return array();
  }
}
