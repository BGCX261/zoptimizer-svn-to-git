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

require_once 'social/provider.php';
require_once 'social/facebook/io.php';
require_once 'social/facebook/data.php';
require_once 'geo/location.php';

class Provider implements \Zopt\Social\SocialProviderInterface {
  /**
   * @var The facebook application id
   */
  private $_appId = NULL;

  /**
   * @var The facebook application secret
   */
  private $_appSecret = NULL;

  /**
   * @var The facebook graph API Url
   */
  private $_graphUrl = 'https://graph.facebook.com';

  /**
   * @var Facebook limits the number of batch requests
   */
  private $_maxBatch = 50;

  public function __construct($appId = NULL, $appSecret = NULL, $graphUrl = 'https://graph.facebook.com', $maxBatch = 50) {
    $this->_appId = $appId;
    $this->_appSecret = $appSecret;
    $this->_graphUrl = $graphUrl;
    $this->_maxBatch = $maxBatch;
  }

  /**
   * Returns the raw user info blobs
   *
   * The userId format is uid.
   *
   * @param string[] $uids The social provider user ids
   * @param string $egoToken The auth token of user who init the query
   * @return array[string] The associated user blob array keyed with user id.
   *                       If a user can not be fetched, it will not show up in the results
   */
  private function _getUsers($uids, $egoToken) {
    $batch = new FacebookBatchedRequests();
    $userFields = User::getFields();
    foreach ($uids as $uid) {
      $batch->addRequest('GET', "/$uid?fields=" . $userFields, NULL, $uid);
    }
    $batchResp = $batch->send($this->_graphUrl, $egoToken, $this->_maxBatch);

    $results = array();
    foreach ($uids as $uid) {
      if (isset($batchResp[$uid])) $results[$uid] = $batchResp[$uid];
    }
    return $results;
  }

  /**
   * Returns the users
   *
   * The userId format is uid.
   *
   * @param string[] $uids The social provider userIds
   * @param UserIdentity $ego The user who init the query, UserIdentity
   * @return User[string] The associated User array keyed with userId.
   *                       If a user can not be fetched, it will not show up in the results
   */
  public function getUsers($uids, $ego = NULL) {
    $userBatch = array();
    if (!is_null($ego) && !is_null($auth = $ego->getAuth())) {
      // use the user authToken to fetch users
      $token = $auth->token;
      $userBatch = $this->_getUsers($uids, $token);
      $missed = array();
      foreach ($uids as $uid) {
        if (!isset($userBatch[$uid])) $missed[] = $uid;
      }
    } else {
      $missed = $uids;
    }

    $appBatch = array();
    if (!empty($missed) && !is_null($this->_appId) && !is_null($this->_appSecret)) {
      $token = "$this->_appId|$this->_appSecret";
      $appBatch = $this->_getUsers($uids, $token);
    }

    $raw = $userBatch + $appBatch;
    if (empty($raw)) return array();

    // parse the raw blob strings into User object and filter with fields
    $users = array();
    foreach ($raw as $uid => $blob) {
      $users[$uid] = User::parse($blob);
    }
    return $users;
  }

  /**
   * Returns the friends
   *
   * The userId format is uid.
   *
   * @param UserIdentity $identity The social provider user identity
   * @param string[] $excludes The excluded user id list
   * @param string[] $includes The included user id list
   * @param mixed[string] $criteria The hints that are used to tune the ranking
   * @param int $offset The offset of the list, 0 (default)
   * @param int $count The number of items returned, 40 (default), NULL (all)
   * @return float[string][string] The returned friends list ranked with overall score.
   *          and if a user has multiple snuids as he connected to many remote social providers, normalize to the local one.
   *          if $criteria is empty, an associated array {userId: overallScore} will be returned.
   *          if $criteria is set, an associated array {userId: {criterion: score}} will be returned.
   */
  public function getFriends($identity, $excludes = array(), $includes = array(), $criteria = NULL, $offset = 0, $count = 40) {
    if (is_null($identity)) return array();
    $uid = $identity->id;
    $query = "/$uid/friends?offset=$offset";
    if (!is_null($count)) $query = $query . "&limit=$count";

    $fbResp = NULL;
    if (!is_null($auth = $identity->getAuth())) {
      // use the user authToken to fetch friends
      $batch = new FacebookBatchedRequests();
      $batch->addRequest('GET', $query, NULL, 'friends');
      $batchResp = $batch->send($this->_graphUrl, $auth->token, $this->_maxBatch);
      $fbResp = isset($batchResp['friends']) ? $batchResp['friends'] : NULL;
    }

    if (is_null($fbResp)) {
      // user application authToken to fetch friends
      if (is_null($this->_appId) || is_null($this->_appSecret)) return array();
      $batch = new FacebookBatchedRequests();
      $batch->addRequest('GET', $query, NULL, 'friends');
      $batchResp = $batch->send($this->_graphUrl, "$this->_appId|$this->_appSecret", $this->_maxBatch);
      $fbResp = isset($batchResp['friends']) ? $batchResp['friends'] : NULL;
    }

    if (is_null($fbResp)) return array();
    $fbResp = json_decode($fbResp, TRUE);
    if ($fbResp === FALSE || !isset($fbResp['data'])) return array();

    // rank the friends
    $friends = array();
    foreach ($fbResp['data'] as $userBlob) {
      $friends[$userBlob['id']] = 0.0;
    }
    foreach ($excludes as $exclude) {
      unset($friends[$exclude]);
    }
    foreach ($includes as $include) {
      $friends[$include] = 0.0;
    }

    // neighborhood
    if (isset($criteria['neighbor'])) {
      $neighborId = $criteria['neighbor'];
      $neighbor = \Zopt\Geo\Location::fromNeighborId($neighborId);
      if (is_null($neighbor)) return $friends;
      $batch = new FacebookBatchedRequests();
      foreach ($friends as $friend => $score) {
        $batch->addRequest('GET', "/$friend/feed?limit=40", NULL, $friend);
      }
      $token = is_null($auth) ? "$this->_appId|$this->_appSecret" : $auth->token;
      $batchResp = $batch->send($this->_graphUrl, $token, $this->_maxBatch);
      $friendsNeighbor = array();
      foreach ($batchResp as $friend => $postsResp) {
        $posts = json_decode($postsResp, TRUE);
        if ($posts === FALSE || !isset($posts['data'])) continue;
        $posts = $posts['data'];
        $representNeighborCounter = array();
        foreach ($posts as $post) {
          $post = \Zopt\Social\Facebook\Post::fromArray('\Zopt\Social\Facebook\Post', $post);
          if (is_null($post->places) || empty($post->places)) continue;
          $friendNeighborId = NULL;
          foreach ($post->places as $location) {
            $friendNeighborId = $location->getNeighborId();
            if (!is_null($friendNeighborId)) break;
          }
          if (is_null($friendNeighborId)) continue;
          $representNeighborCounter[$friendNeighborId] = isset($representNeighborCounter[$friendNeighborId]) ? $representNeighborCounter[$friendNeighborId]+1 : 1;
        }
        if (empty($representNeighborCounter)) {
          $friendsNeighbor[$friend] = NULL;
        } else {
          asort($representNeighborCounter);
          $friendsNeighbor[$friend] = \Zopt\Geo\Location::fromNeighborId(array_pop($representNeighborCounter));
        }
      }
      $friends = array();
      foreach ($friendsNeighbor as $uid => $friendNeighbor) {
        $friends[$uid] = is_null($friendNeighbor) ? 40000000 : \Zopt\Geo\Globe::dist($friendNeighbor, $neighbor);
      }
      asort($friends);
      foreach ($friendsNeighbor as $uid => $friendNeighbor) {
        $friends[$uid] = array(
            'neighbor' => is_null($friendNeighbor) ? NULL : $friendNeighbor->getNeighborId(),
        );
      }
    }

    return array_slice($friends, $offset, $count, TRUE);;
  }

  /**
   * Returns users posts
   *
   * The userId format is uid.
   *
   * @param string[] $uids The social provider user ids
   * @param UserIdentity|string $ego The user who init the query, UserIdentity or userId
   * @return Post[string] The associated User array keyed with userId.
   *                       If a user's posts can not be fetched, it will not show up in the results
   */
  // public function getUserPosts($uids, $ego = NULL) {
  //   return array();
  // }

  /**
   * Publish post to users
   *
   * The userId format is uid.
   *
   * @param Post $post The post body
   * @param string[] $uids The social provider uids
   * @param UserIdentity|string $ego The user who init the query, UserIdentity or userId
   * @return string[string] The associated postId array keyed with userId.
   *                         If post failed, it will not show up in the results
   */
  public function publishPosts($post, $uids, $ego = NULL) {
  }
}
