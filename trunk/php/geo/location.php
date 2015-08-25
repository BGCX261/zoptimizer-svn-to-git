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

require_once 'base/data.php';

class Location extends \Zopt\Base\DataClazz {
  private static $_defaultOptions = array(
      CURLOPT_HEADER => FALSE,
      CURLOPT_SSL_VERIFYPEER => FALSE,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_POST => FALSE,
  );

  protected static $_metaBase = array();
  protected static $_metaAll = array('name', 'street', 'city', 'state', 'country', 'zip', 'latitude', 'longitude');

  public $name;
  public $street;
  public $city;
  public $state;
  public $country;
  public $zip;
  public $latitude;
  public $longitude;

  public static function fromNeighborId($nbId) {
    $options = array(
        CURLOPT_URL => "http://search.romio.com/search/init-api/getNbhId?nbhId=$nbId",
    );
    $ch = curl_init();
    curl_setopt_array($ch, ($options + self::$_defaultOptions));
    $result = curl_exec($ch);
    curl_close($ch);
    if ($result === FALSE) return NULL;
    $result = json_decode($result, TRUE);
    if ($result === FALSE) return NULL;
    if (!isset($result['lat']) || !isset($result['lon'])) return NULL;
    $blob = array(
        'latitude' => $result['lat'],
        'longitude' => $result['lon'],
    );
    return self::fromArray('\Zopt\Geo\Location', $blob);
  }

  public function getNeighborId() {
    $options = array(
        CURLOPT_URL => "http://search.romio.com/search/init-api/getNbhMappings?lat=$this->latitude&lon=$this->longitude",
    );
    $ch = curl_init();
    curl_setopt_array($ch, ($options + self::$_defaultOptions));
    $result = curl_exec($ch);
    curl_close($ch);
    if ($result === FALSE) return NULL;
    $result = json_decode($result, TRUE);
    if ($result === FALSE) return NULL;
    if (!is_array($result) || empty($result)) return NULL;
    return $result[0]['key'];
  }
}
