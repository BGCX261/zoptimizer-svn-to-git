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
namespace Zopt\Geo;

require_once 'geo/location.php';

class Globe {
  const RADIUS = 6371004; // meters

  /**
   * Calculate the distance arc between two locations.
   *
   * http://en.wikipedia.org/wiki/Great-circle_distance
   * http://en.wikipedia.org/wiki/Haversine_formula
   *
   * @param Location $loc1 Location from
   * @param Location $loc2 Location to
   * @param bool $useHaversine Use Haversine Formula
   * @return float the arc of two locations
   */
  public static function distArc($loc1, $loc2, $useHaversine = FALSE) {
    $lati1 = deg2rad($loc1->latitude);
    $lati2 = deg2rad($loc2->latitude);
    $latiDelta = $lati2 - $lati1;
    $longiDelta = deg2rad($loc2->longitude - $loc1->longitude);
    if ($useHaversine) {
      $sinHalfLatiDelta = sin($latiDelta * 0.5);
      $sinHalfLongiDelta = sin($longiDelta * 0.5);
      return 2 * asin(sqrt($sinHalfLatiDelta * $sinHalfLatiDelta + cos($lati1) * cos($lati2) * $sinHalfLongiDelta * $sinHalfLongiDelta));
    } else {
      return acos(sin($lati1) * sin($lati2) + cos($lati1) * cos($lati2) * cos($longiDelta));
    }
  }

  /**
   * Calculate the distance between two locations.
   *
   * http://en.wikipedia.org/wiki/Great-circle_distance
   * http://en.wikipedia.org/wiki/Haversine_formula
   *
   * @param Location $loc1 Location from
   * @param Location $loc2 Location to
   * @param bool $useHaversine Use Haversine Formula
   * @return float the distance between two locations (in meter)
   */
  public static function dist($loc1, $loc2, $useHaversine = FALSE) {
    $arc = self::distArc($loc1, $loc2, $useHaversine);
    return self::RADIUS * $arc;
  }
}