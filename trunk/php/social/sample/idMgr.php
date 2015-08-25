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
require_once 'social/user.php';

// Sample identity manager
class SampleIdMgr implements \Zopt\Social\IdentityManagerInterface {
  /**
   * Returns the social provider identities these user have
   *
   * As an sample, it returns the same uid for 2 snids: local and facebook, with NULL auth object
   *
   * @param string[] $snuids The social provider user ids
   * @return UserIdentity[int][string] The associated UserIdentity[int] array keyed with user id.
   *          If a user can not be found, it will not show up in the results
   */
  public function getMappings($snuids) {
    $ret = array();
    foreach ($snuids as $snuid) {
      list($snid, $uid) = explode(':', $snuid);
      if ($snid != \Zopt\Social\SocialProviderConstant::LOCAL && $snid != \Zopt\Social\SocialProviderConstant::FACEBOOK) continue;
      $ret[$snuid] = array(
          \Zopt\Social\SocialProviderConstant::LOCAL => new \Zopt\Social\UserIdentity($uid),
          \Zopt\Social\SocialProviderConstant::FACEBOOK => new \Zopt\Social\UserIdentity($uid),
      );
    }
    return $ret;
  }
}
