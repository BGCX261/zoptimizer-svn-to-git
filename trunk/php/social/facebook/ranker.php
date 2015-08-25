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
namespace Zopt\Social\Facebook;

require_once 'social/facebook/io.php';
require_once 'social/base.php';
require_once 'geo/location.php';
require_once 'geo/globe.php';
require_once 'rank/scorers.php';

class GeoRanker implements \Zopt\Social\RankerInterface {
  public $curLoc;
  public $cutOff;

  public function __construct($hint) {
    if (!is_array($hint) || !isset($hint['latitude']) || !isset($hint['longitude'])) throw new \Zopt\Social\SocialException("Facebook\GeoRanker.__construct: hint malformat.");
    $this->curLoc = new \Zopt\Geo\Location('', '', '', '', '', $hint['latitude'], $hint['longitude']);
    $this->cutOff = isset($hint['cutOff']) ? $hint['cutOff'] : 9000;
  }

  /**
   * {@inheritdoc}
   */
  public function score($uid) {
  }
}
