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

require_once 'social/base.php';
require_once 'base/data.php';

class SocialService extends \Zopt\Api\Service {
  private $_aggregator;

  public function __construct($aggregator) {
    $this->_aggregator = $aggregator;
  }

  /**
   * Returns the users
   *
   * @param mixed $params the structure is as following:
   *               string[] $snuids The social provider userIds
   *               string[]|bool $fields The fields that the user info blob returns
   *                             TRUE: all possible fields
   *                             FALSE, array(): default fields (default)
   *                             array(field): given fields
   *               string $ego The user who init the query, snuid
   * @return mixed[string] The associated user blob array keyed with snuid.
   *                        If a user can not be fetched, it will not show up in the results
   */
  public function getUsers($params, $context) {
    list($snuids) = self::getArgs($params, array('snuids'));
    list($fields, $ego) = self::getOptArgs($params, array(
        'fields' => FALSE,
        'ego' => NULL,
    ));
    $users = $this->_aggregator->getUsers($snuids, $ego);
    $userBlobs = array();
    foreach ($users as $snuid => $user) {
      $userBlobs[$snuid] = \Zopt\Base\DataClazz::toArray($user, $fields);
    }
    return $userBlobs;
  }

  /**
   * Returns the friends
   *
   * @param mixed $params the structure is as following:
   *               string $snuid The social provider userId
   *               string[] $excludes The excluded snuids list
   *               string[] $includes The included snuids list
   *               mixed[string] $criteria The hints that are used to tune the ranking
   *               string[]|bool $fields The fields that the user info blob returns
   *                             FALSE: snuid only (default)
   *                             TRUE: all possible fields
   *                             array(): default fields
   *                             array(field): given fields
   *               int $offset The offset of the list, 0 (default)
   *               int $count The number of items returned, 40 (default), NULL (till the end)
   * @return mixed[string] The ranked associated user blob array keyed with snuid.
   */
  public function getFriends($params, $context) {
    list($snuid) = self::getArgs($params, array('snuid'));
    list($excludes, $includes, $criteria, $fields, $offset, $count) = self::getOptArgs($params, array(
        'excludes' => array(),
        'includes' => array(),
        'criteria' => array(),
        'fields' => FALSE,
        'offset' => 0,
        'count' => 40,
    ));
    $friends = $this->_aggregator->getFriends($snuid, $excludes, $includes, $criteria, $offset, $count);
    if (empty($friends)) return array();
    if ($fields === FALSE) return array_keys($friends);
    $results = $this->getUsers(array(
        'snuids' => array_keys($friends),
        'fields' => $fields,
        'ego' => $snuid,
    ), $context);
    foreach ($results as $friend => $blob) {
      $results[$friend]['criteria'] = $friends[$friend];
    }
    return $results;
  }
}
