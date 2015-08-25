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
namespace Zopt\Rank;

class Event {
  /**
   * @var int the UNIX timestamp (in second) this event happened
   */
  public $timestamp = 0;

  public function __construct($timestamp = 0) {
    $this->timestamp = $timestamp;
  }
}

interface EventGeneratorInterface {
  /**
   * Generate a event based on input object
   *
   * @param mixed $input the input object
   * @return Event the output time event, NULL if this is not a time event
   */
  public function generateEvent($input);
}

interface ScorerInterface {
  /**
   * Calculate the overall score based on a time series and the given time point
   *
   * The other related parameters, such like current time etc., could be passed in the constructor, or other function.
   *
   * @param Event[] $events a time series of Events, from old to new
   * @return float the overall score
   */
  public function score($events);
}

interface MergerInterface {
  /**
   * Merge the scores from different sources
   *
   * @param float[string] An associate array {scoreName: score}
   * @return float the overall score
   */
  public function merge($scores);
}