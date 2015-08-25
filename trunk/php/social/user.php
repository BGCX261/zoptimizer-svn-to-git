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
namespace Zopt\Social;

require_once 'social/data.php';

class Auth {
  public $token;
  public $expireUntil;
  public function __construct($token, $expireUntil) {
    $this->token = $token;
    $this->expireUntil = $expireUntil;
  }
}

class UserIdentity {
  /**
   * @var Auth The authentication object that the underlying social network uses
   */
  private $_auth;

  /**
   * @var string The unique user ID this user has
   */
  public $id;

  public function __construct($id, $auth = NULL) {
    $this->id = $id;
    $this->setAuth($auth);
  }

  public function getAuth() {
    return $this->_auth;
  }

  public function setAuth($auth) {
    $this->_auth = $auth;
  }
}

interface IdentityManagerInterface {
  /**
   * Returns the social provider identities these user have
   *
   * @param string[] $snuids The social provider user ids
   * @return UserIdentity[int][string] The associated UserIdentity[int] array keyed with user id.
   *          If a user can not be found, it will not show up in the results
   */
  public function getMappings($snuids);
}
