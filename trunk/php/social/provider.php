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
require_once 'social/data.php';
require_once 'social/user.php';

interface SocialProviderInterface {
  /**
   * Returns the users
   *
   * The userId format could be either snuid or uid.
   * - for individual social provider, it's uid format;
   * - for aggregator social provider, it's snuid format.
   *
   * For the UserIdentity|string case, the aggregator accepts string only, but underlying providers accept UserIdentity.
   *
   * @param string[] $userIds The social provider userIds
   * @param UserIdentity|string $ego The user who init the query, UserIdentity or userId
   * @return User[string] The associated User array keyed with userId.
   *                       If a user can not be fetched, it will not show up in the results
   */
  public function getUsers($userIds, $ego = NULL);

  /**
   * Returns the friends
   *
   * The userId format could be either snuid or uid.
   * - for individual social provider, it's uid format;
   * - for aggregator social provider, it's snuid format.
   *
   * For the UserIdentity|string case, the aggregator accepts string only, but underlying providers accept UserIdentity.
   *
   * @param UserIdentity|string $userId The social provider user, UserIdentity or userId
   * @param string[] $excludes The excluded userId list
   * @param string[] $includes The included userId list
   * @param mixed[string] $criteria The hints that are used to tune the ranking
   * @param int $offset The offset of the list, NULL (default)
   * @param int $count The number of items returned, 40 (default), NULL (till the end)
   * @return float[string][string] The returned friends list ranked with overall score.
   *          if $criteria is empty, an associated array {userId: overallScore} will be returned.
   *          if $criteria is set, an associated array {userId: {criterion: score}} will be returned.
   */
  public function getFriends($userId, $excludes = array(), $includes = array(), $criteria = array(), $offset = 0, $count = NULL);

  /**
   * Returns users posts
   *
   * The userId format could be either snuid or uid.
   * - for individual social provider, it's uid format;
   * - for aggregator social provider, it's snuid format.
   *
   * For the UserIdentity|string case, the aggregator accepts string only, but underlying providers accept UserIdentity.
   *
   * @param string[] $userIds The social provider userIds
   * @param UserIdentity|string $ego The user who init the query, UserIdentity or userId
   * @return Post[string] The associated User array keyed with userId.
   *                       If a user's posts can not be fetched, it will not show up in the results
   */
  // public function getUserPosts($userIds, $ego = NULL);

  /**
   * Publish post to users
   *
   * The userId format could be either snuid or uid.
   * - for individual social provider, it's uid format;
   * - for aggregator social provider, it's snuid format.
   *
   * For the UserIdentity|string case, the aggregator accepts string only, but underlying providers accept UserIdentity.
   *
   * @param Post $post The post body
   * @param string[] $userIds The social provider userIds
   * @param UserIdentity|string $ego The user who init the query, UserIdentity or userId
   * @return string[string] The associated postId array keyed with userId.
   *                         If post failed, it will not show up in the results
   */
  // public function publishPosts($post, $userIds, $ego = NULL);
}
