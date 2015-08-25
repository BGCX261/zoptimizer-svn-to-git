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

require_once 'base/data.php';
require_once 'geo/location.php';

class User extends \Zopt\Base\DataClazz {
  protected static $_metaBase = array('id', 'fullName', 'picture');
  protected static $_metaAll = array(
      'id', 'fullName', 'picture', 'gender', 'locale', 'profileUrl',
      'email', 'hometown', 'location', 'education', 'work');

  /**
   * The public information
   *
   * @var string The unique user ID.
   * @var string User's (full) name, like "Jacky Wang"
   * @var string The url represent the user icon
   * @var string The gender: female or male
   * @var string The locale the user are in
   * @var string User's profileUrl
   */
  public $id;
  public $fullName;
  public $picture;
  public $gender;
  public $locale;
  public $profileUrl;

  /**
   * The social channel
   *
   * @var string Email address, like "chaowang@zoptimizer.com"
   */
  public $email;

  /**
   * The geo information
   *
   * @var Location The user's hometown
   * @var Location The user's current city
   */
  public $hometown;
  public $location;

  /**
   * The history information
   *
   * TODO
   */
  public $education;
  public $work;
}

class Post extends \Zopt\Base\DataClazz {
  protected static $_metaBase = array('id');
  protected static $_metaAll = array(
      'id', 'from', 'to', 'message', 'messageTags',
      'links', 'places', 'withTags', 'icon', 'app',
      'actions', 'likes', 'comments', 'created', 'updated');

  /**
   * @var string post's unique ID
   */
  public $id;

  /**
   * Social section
   *
   * @var string from userId
   * @var string to userId
   */
  public $from;
  public $to;

  /**
   * Content section
   *
   * @var string message: post's message body
   * @var string[] messageTags: objectIds
   */
  public $message;
  public $messageTags;

  /**
   * Rich content section
   *
   * @var Link[] links: array of [url, name, caption, description, type (pic, link, video)]
   * @var Location[] places: places this post related
   * @var string[] withTags: objectIds that this post happened with
   */
  public $links;
  public $places;
  public $withTags;

  /**
   * Post source
   *
   * @var string icon: URL to an icon representing the type of this post
   * @var string app: Application ID (objectId)
   */
  public $icon;
  public $app;

  /**
   * Action / Social connections
   *
   * @var string[string] actions: actions can perform to this post - {name: link}
   * @var string[] likes: array of userIds
   * @var string[] comments: objectIds for comments
   */
  public $actions;
  public $likes;
  public $comments;

  /**
   * Timestamps
   *
   * @var int created: UNIX timestamp for this post's creation (in second)
   * @var int updated: UNIX timestamp for this post's update (in second)
   */
  public $created;
  public $updated;
}
