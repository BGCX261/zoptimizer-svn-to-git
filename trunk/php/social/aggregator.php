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

require_once 'social/provider.php';

// Overall social provider aggregator
class Aggregator implements SocialProviderInterface {
  /**
   * @var SocialProviderInterface[int] An array of social networks
   */
  private $_providers = array();

  /**
   * @var IdentityManagerInterface The identity manager handles snuid mappings
   */
  private $_identityMgr = NULL;

  public function __construct($identityMgr, $providers = array()) {
    $this->_identityMgr = $identityMgr;
    $this->_providers = $providers;
  }

  /**
   * Add social provider to this aggregator
   *
   * @param int $snid The social network ID, should appear in SocialProviderConstant::$snList
   * @param SocialProviderInterface $snid The social network ID, should appear in SocialProviderConstant::$snList
   * @return SocialProviderInterface|bool The registered provider for this snid, or FALSE
   */
  public function addProvider($snid, $provider) {
    if (!isset(SocialProviderConstant::$snList[$snid])) throw new SocialException("Aggregator.addProvider: social provider ID $snid is not found.");
    $ret = isset($this->_providers[$snid]) ? $this->_providers[$snid] : FALSE;
    $this->_providers[$snid] = $provider;
    return $ret;
  }

  /**
   * Returns the users
   *
   * The userId format is snuid.
   *
   * @param string[] $snuids The social provider userIds
   * @param string $ego The user who init the query, snuid
   * @return User[string] The associated User array keyed with userId.
   *                       If a user can not be fetched, it will not show up in the results
   */
  public function getUsers($snuids, $ego = NULL) {
    if (is_null($this->_identityMgr)) throw new SocialException("Aggregator.getUsers: identity manager is not set.");

    // get social provider egos if possible
    $snEgos = NULL;
    if (!is_null($ego)) {
      $mappings = $this->_identityMgr->getMappings(array($ego));
      if (isset($mappings[$ego])) $snEgos = $mappings[$ego];
    }

    // categorize snuids based on snid
    $categorizedUids = array();
    foreach ($snuids as $snuid) {
      list($snid, $uid) = explode(':', $snuid);
      if (!isset($categorizedUids[$snid])) $categorizedUids[$snid] = array();
      $categorizedUids[$snid][] = $uid;
    }

    // remove the localUids
    $localUids = isset($categorizedUids[SocialProviderConstant::LOCAL]) ? $categorizedUids[SocialProviderConstant::LOCAL] : array();
    unset($categorizedUids[SocialProviderConstant::LOCAL]);

    // query each underlying snid except the local provider
    $ret = array();
    $missedSnuids = array();
    foreach ($categorizedUids as $snid => $uids) {
      if (!isset($this->_providers[$snid])) {  // the given snid provider is not registered
        foreach ($uids as $uid) {
          $missedSnuids[] = "$snid:$uid";
        }
        continue;
      }
      $snEgo = (!is_null($snEgos) && isset($snEgos[$snid])) ? $snEgos[$snid] : NULL;
      $snUsers = $this->_providers[$snid]->getUsers($uids, $snEgo);
      foreach ($uids as $uid) {
        $snuid = "$snid:$uid";
        if (!isset($snUsers[$uid])) {
          $missedSnuids[] = $snuid;
        } else {
          $ret[$snuid] = $snUsers[$uid];
          $ret[$snuid]->id = $snuid;  // output marshaling
        }
      }
    }

    $snid = SocialProviderConstant::LOCAL;
    if (!isset($this->_providers[$snid])) return $ret;

    // mapping missed user snuids into local uids
    $missedUids = array();
    if (!empty($missedSnuids)) $mappings = $this->_identityMgr->getMappings($missedSnuids);
    foreach ($missedSnuids as $snuid) {
      if (!isset($mappings[$snuid])) continue;
      $uid = $mappings[$snuid][$snid]->id;
      $missedUids[$snuid] = $uid;
    }

    // query local social provider to get the user
    $snEgo = (!is_null($snEgos) && isset($snEgos[$snid])) ? $snEgos[$snid] : NULL;
    $snUsers = $this->_providers[$snid]->getUsers(array_merge($localUids, array_values($missedUids)), $snEgo);
    foreach ($localUids as $uid) {
      if (isset($snUsers[$uid])) {
        $ret["$snid:$uid"] = $snUsers[$uid];
      }
    }
    foreach ($missedUids as $snuid => $uid) {
      if (isset($snUsers[$uid])) {
        $ret[$snuid] = $snUsers[$uid];
        $ret[$snuid]->id = $snuid;  // output marshaling
      }
    }

    return $ret;
  }

  /**
   * Returns the friends
   *
   * The userId format is snuid.
   *
   * @param string $snuid The social provider userId
   * @param string[] $excludes The excluded userId list
   * @param string[] $includes The included userId list
   * @param mixed[string] $criteria The hints that are used to tune the ranking
   * @param int $offset The offset of the list, 0 (default)
   * @param int $count The number of items returned, 40 (default), NULL (till the end)
   * @return float[string][string] The returned friends list ranked with overall score.
   *          and if a user has multiple snuids as he connected to many remote social providers, normalize to the local one.
   *          if $criteria is empty, an associated array {userId: overallScore} will be returned.
   *          if $criteria is set, an associated array {userId: {criterion: score}} will be returned.
   */
  public function getFriends($snuid, $excludes = array(), $includes = array(), $criteria = NULL, $offset = 0, $count = 40) {
    if (is_null($this->_identityMgr)) throw new SocialException("Aggregator.getUsers: identity manager is not set.");
    // user ID mapping for all local uids
    $snuids = array_merge(array($snuid), $excludes, $includes);
    $mappings = $this->_identityMgr->getMappings($snuids);
    if (!isset($mappings[$snuid])) throw new SocialException("Aggregator.getFriends: Identity is not found for snuid $snuid.");
    $identities = $mappings[$snuid];  // fetched all identities that this $localIdentity has

    // fetch friends in all the networks
    $snFriends = array();
    foreach ($identities as $snid => $identity) {
      if (!isset($this->_providers[$snid])) continue;  // the given snid is not registered

      // build excludes and includes based on specific snid
      $snExcludes = array();
      foreach ($excludes as $exclude) {
        if (isset($mappings[$exclude][$snid])) {
          $excludes[] = $mappings[$exclude][$snid]->id;
          continue;
        }
        list($exSnid, $exUid) = explode(':', $exclude);
        if ($exSnid == $snid) $excludes[] = $exUid;  // '==' instead of '===' as snid is int
      }
      $snIncludes = array();
      foreach ($includes as $include) {
        if (isset($mappings[$include][$snid])) {
          $includes[] = $mappings[$include][$snid]->id;
          continue;
        }
        list($inSnid, $inUid) = explode(':', $include);
        if ($inSnid == $snid) $includes[] = $inUid;  // '==' instead of '===' as snid is int
      }
      // TODO: optimize the return value to a range
      $snCount = is_null($count) ? NULL : $offset + $count;
      $snFriends[$snid] = $this->_providers[$snid]->getFriends($identity, $excludes, $includes, $criteria, 0, $snCount);
    }

    // user ID mapping for all the fetched friends
    $snuids = array();
    foreach ($snFriends as $snid => $friends) {
      foreach ($friends as $snuid => $scores) {
        $snuids[] = "$snid:$snuid";
      }
    }
    if (!empty($snuids)) {
      $mappings = $this->_identityMgr->getMappings($snuids);
    } else {
      return array();
    }

    // merge the friends list
    $mergedFriends = array();
    foreach ($snFriends as $snid => $friends) {
      foreach ($friends as $uid => $scores) {
        $snuid = "$snid:$uid";
        if (!isset($mappings[$snuid][SocialProviderConstant::LOCAL])) {  // for the user who has not registered in identity manager
          $mergedFriends[$snuid] = $scores;
          continue;
        }
        $localSnuid = SocialProviderConstant::LOCAL . ':' . $mappings[$snuid][SocialProviderConstant::LOCAL]->id;
        if (!isset($friends[$localSnuid])) {  // new entry
          $mergedFriends[$localSnuid] = $scores;
        } elseif (is_null($criteria)) {  // already found, merge for simple criteria, TODO: strategy for overall
          $mergedFriends[$localSnuid] += $scores;
        } else {
          foreach ($criteria as $criterion => $hints) {  // already found, merge for complex criteria, TODO: strategy for individual
            $mergedFriends[$localSnuid][$criterion] += $scores[$criterion];
          }
        }
      }
    }

    // calculate the overall score and sort, TODO: strategy for mapping individual scores to overall
    $overallScores = array();
    foreach ($mergedFriends as $friend => $scores) {
      if (is_null($criteria)) {
        $overallScores[$friend] = $scores;
        continue;
      }
      $overallScores[$friend] = 0;
      foreach ($criteria as $criterion => $hints) {
        $overallScores[$friend] += $scores[$criterion];
      }
    }
    asort($overallScores);
    $sortedFriends = array();
    foreach ($overallScores as $friend => $score) {
      $sortedFriends[$friend] = $mergedFriends[$friend];
    }

    return array_slice($sortedFriends, $offset, $count, TRUE);
  }

  /**
   * Returns users posts
   *
   * The userId format is snuid.
   *
   * @param string[] $snuids The social provider snuids
   * @param UserIdentity|string $ego The user who init the query, UserIdentity or userId
   * @return Post[string] The associated User array keyed with userId.
   *                       If a user's posts can not be fetched, it will not show up in the results
   */
  public function getUserPosts($snuids, $ego = NULL) {
    if (is_null($this->_identityMgr)) throw new SocialException("Aggregator.getUserPosts: identity manager is not set.");
    return array();  // TODO: biz logic - what does "aggregator of posts" mean?
  }

  /**
   * Returns users posts
   *
   * The userId format is snuid.
   *
   * @param string[] $snuids The social provider snuids
   * @param UserIdentity|string $ego The user who init the query, UserIdentity or userId
   * @return Post[string] The associated User array keyed with userId.
   *                       If a user's posts can not be fetched, it will not show up in the results
   */
  // public function getUserPosts($snuids, $ego = NULL) {
  //  if (is_null($this->_identityMgr)) throw new SocialException("Aggregator.getUserPosts: identity manager is not set.");
  //  return array();  // TODO: biz logic - what does "aggregator of posts" mean?
  //}

  /**
   * Publish post to users
   *
   * The userId format is snuid.
   *
   * @param Post $post The post body
   * @param string[] $snuids The social provider snuids
   * @param UserIdentity|string $ego The user who init the query, UserIdentity or userId
   * @return string[string] The associated postId array keyed with userId.
   *                         If post failed, it will not show up in the results
   */
  public function publishPosts($post, $snuids, $ego = NULL) {
    if (is_null($this->_identityMgr)) throw new SocialException("Aggregator.getUserPosts: identity manager is not set.");
    return array();  // TODO: biz logic - what does "aggregator of posts" mean?
  }
}
