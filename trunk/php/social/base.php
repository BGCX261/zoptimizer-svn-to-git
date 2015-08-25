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

class SocialException extends \Exception {
  protected $partialResult = NULL;

  public function setPartialResult($partialResult) {
    $this->_partialResult = $partialResult;
  }

  public function getPartialResult() {
    return $this->_partialResult;
  }
}

/**
 * Define some of the constants
 *
 * int snid, e.g. 0
 * string sntag, e.g. 'local'
 * string uid, e.g. a4db653d205v
 * string snuid, e.g.: 0:a4db653d205v
 */
class SocialProviderConstant {
  const LOCAL = 0;  // const 0 is reserved for the "local"/"inhouse" provider
  const FACEBOOK = 1;
  const FOURSQUARE = 2;
  const TWITTER = 3;

  public static $snList = array(
      self::LOCAL => 'local',
      self::FACEBOOK => 'fb',
      self::FOURSQUARE => 'fsq',
      self::TWITTER => 'tw',
  );

  private function __construct() {}
}

interface RankerInterface {
  /**
   * Returns a score for a given user
   *
   * @param string $userId The social provider userId
   * @return float The score.
   */
  public function score($userId);
}
