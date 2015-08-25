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

require_once 'rank/base.php';

class ScoreEvent extends Event {
  public $score = 0;
  public function __construct($timestamp = 0, $score = 0.0) {
    parent::__construct($timestamp);
    $this->score = $score;
  }
}

/**
 * Sum score is as simple as just sum the score of all the events together.
 * It has been used in Delicious, etc.
 */
class SumScorer implements ScorerInterface {
  private $_current = NULL;

  public function __construct($current = NULL) {
    if (is_null($current)) $current = time();
    $this->_current = $current;
  }

  public function setCurrent($current = NULL) {
    if (is_null($current)) $current = time();
    $this->_current = $current;
  }

  /**
   * Calculate the overall score based on a time series and the given time point
   *
   * @param ScoreEvent[] $events a time series of Events, from old to new.
   * @return float the overall score
   */
  public function score($events) {
    $score = 0.0;
    foreach ($events as $event) {
      if (($timeDiff = $this->_current - $event->timestamp) < 0) continue;
      $score += $event->score;
    }
    return $score;
  }
}

/**
 * AnnealScorer score is based on the equation:
 *
 * - score = (rawScore + scoreStablizer) / anneal ^ (timeDiff + timeStablizer)
 *
 * It has been used in HackerNews, etc.
 */
class AnnealScorer implements ScorerInterface {
  private $_scoreStablizer = 0.0;
  private $_timeStablizer = 0.0;
  private $_anneal = 1.0;

  public function __construct($anneal = 1.0, $scoreStablizer = 0.0, $timeStablizer = 0.0, $current = NULL) {
    $this->_scoreStablizer = $scoreStablizer;
    $this->_timeStablizer = $timeStablizer;
    $this->_anneal = max($anneal, 1.0);  // the anneal should be larger than 1 to make the score fade out
    if (is_null($current)) $current = time();
    $this->_current = $current;
  }

  public function setCurrent($current = NULL) {
    if (is_null($current)) $current = time();
    $this->_current = $current;
  }

  /**
   * Calculate the overall score based on a time series and the given time point
   *
   * @param ScoreEvent[] $events a time series of Events, from old to new
   * @return float the overall score
   */
  public function score($events) {
    foreach ($events as $event) {
      if (($timeDiff = $this->_current - $event->timestamp) < 0) continue;
      $sgn = ($event->score > 0) ? 1 : -1;
      $rawScore = abs($event->score);
      $score += $sgn * ($rawScore + $this->_scoreStablizer) / pow($this->_anneal, $timeDiff + $this->_timeStablizer);
    }
    return $score;
  }
}
