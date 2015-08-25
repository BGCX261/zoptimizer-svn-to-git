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

require_once 'social/data.php';

class User extends \Zopt\Social\User {
  public static $fields = array(
      'id', 'name', 'picture', 'gender', 'locale', 'link', 'username',
      'email', 'hometown', 'location', 'education', 'work');

  private static $_fields = NULL;

  public static function getFields() {
    if (is_null(self::$_fields)) self::$_fields = implode(',', self::$fields);
    return self::$_fields;
  }

  /**
   * Parse a raw facebook user info json string to generic User object
   *
   * @param string $input the facebook user info json string
   * @return User parsed user object
   */
  public static function parse($input) {
    $raw = json_decode($input, TRUE);
    if ($raw === FALSE || !isset($raw['id']) || !isset($raw['name']) || !isset($raw['picture'])) throw new \Zopt\Social\SocialException("Facebook\User.parse: invalid input string: $input");

    // mash up
    $mash = array();
    $mash['id'] = $raw['id'];
    $mash['fullName'] = $raw['name'];
    $mash['picture'] = $raw['picture'];
    if (isset($raw['gender'])) $mash['gender'] = $raw['gender'];
    if (isset($raw['locale'])) $mash['locale'] = $raw['locale'];
    if (isset($raw['link'])) $mash['profileUrl'] = $raw['link'];
    if (isset($raw['email'])) $mash['email'] = $raw['email'];
    if (isset($raw['hometown'])) $mash['hometown'] = $raw['hometown'];
    if (isset($raw['location'])) $mash['location'] = $raw['location'];
    if (isset($raw['education'])) $mash['education'] = $raw['education'];
    if (isset($raw['work'])) $mash['work'] = $raw['work'];

    // feed to the user object
    return self::fromArray('\Zopt\Social\User', $mash);
  }
}

class Post extends \Zopt\Social\Post {
  public static $fields = array(
      'id', 'from', 'to', 'message', 'message_tags',
      'picture', 'link', 'name', 'caption', 'description', 'source',
      'place', 'with_tags', 'icon', 'application',
      'actions', 'likes', 'comments', 'created_time', 'updated_time');

  private static $_fields = NULL;

  public static function getFields() {
    if (is_null(self::$_fields)) self::$_fields = implode(',', self::$fields);
    return self::$_fields;
  }

  /**
   * Parse a raw facebook post info json string to generic Post object
   *
   * @param string $input the facebook post info json string
   * @return User parsed post object
   */
  public static function parse($input) {
    $raw = json_decode($input, TRUE);
    if ($raw === FALSE || !isset($raw['id'])) throw new \Zopt\Social\SocialException("Facebook\Post.parse: invalid input string: $input");

    // mash up
    $mash = array();
    $mash['id'] = $raw['id'];
    if (isset($raw['from'])) $mash['from'] = $raw['from'];
    if (isset($raw['to'])) $mash['to'] = $raw['to'];
    if (isset($raw['message'])) $mash['message'] = $raw['message'];
    if (isset($raw['message_tags'])) $mash['messageTags'] = $raw['message_tags'];

    // links in the post - picture/link/video
    if (isset($raw['picture']) || isset($raw['link']) || isset($raw['source'])) $mash['links'] = array();
    if (isset($raw['picture'])) {
      $pic = array(
          'url' => $raw['picture'],
          'type' => 'pic',
      );
      $mash['links'][] = $pic;
    }
    if (isset($raw['link'])) {
      $link = array(
          'url' => $raw['link'],
          'type' => 'link',
      );
      if (isset($raw['name'])) $link['name'] = $raw['name'];
      if (isset($raw['caption'])) $link['caption'] = $raw['caption'];
      if (isset($raw['description'])) $link['description'] = $raw['description'];
      $mash['links'][] = $link;
    }
    if (isset($raw['source'])) {
      $video = array(
          'url' => $raw['source'],
          'type' => 'video',
      );
      $mash['links'][] = $video;
    }

    // location
    if (isset($raw['place']['location'])) {
      $place = $raw['place']['location'];
      $place['name'] = $raw['place']['name'];
      if (!is_null($location = self::fromArray('\Zopt\Geo\Location', $place))) $mash['places'] = array($location);
    }

    if (isset($raw['with_tags'])) $mash['withTags'] = $raw['with_tags'];
    if (isset($raw['icon'])) $mash['icon'] = $raw['icon'];
    if (isset($raw['application'])) $mash['app'] = $raw['application'];

    if (isset($raw['actions'])) $mash['actions'] = $raw['actions'];
    if (isset($raw['likes'])) $mash['likes'] = $raw['likes'];
    if (isset($raw['comments'])) $mash['comments'] = $raw['comments'];
    if (isset($raw['created_time'])) $mash['created'] = strtotime($raw['created_time']);
    if (isset($raw['updated_time'])) $mash['updated'] = strtotime($raw['updated_time']);

    // feed to the Post object
    return self::fromArray('\Zopt\Social\Post', $mash);
  }

  public static function stringify($post) {
    $raw = self::toArray($post, TRUE);

    // mash up
    $mash = array();
    if (isset($raw['id'])) $mash['id'] = $raw['id'];
    if (isset($raw['from'])) $mash['from'] = $raw['from'];
    if (isset($raw['to'])) $mash['to'] = $raw['to'];
    if (isset($raw['message'])) $mash['message'] = $raw['message'];
    if (isset($raw['messageTags'])) $mash['message_tags'] = $raw['messageTags'];

    // links in the post - picture/link/video
    if (!isset($raw['links']) || !is_array($raw['links'])) $raw['links'] = array();
    foreach ($raw['links'] as $link) {
      if ($link['type'] === 'pic') {
        $mash['picture'] = $link['url'];
      } elseif ($link['type'] === 'link') {
        $mash['link'] = $link['url'];
        if (isset($link['name'])) $mash['name'] = $link['name'];
        if (isset($link['caption'])) $mash['caption'] = $link['caption'];
        if (isset($link['description'])) $mash['description'] = $link['description'];
      } elseif ($link['type'] === 'video') {
        $mash['source'] = $link['url'];
      }
    }

    // location
    foreach ($raw['places'] as $location) {
      $loc = self::toArray($location, TRUE);
      $mash['place'] = array();
      if (isset($loc['name'])) $mash['place']['name'] = $loc['name'];
      unset($loc['name']);
      $mash['place']['location'] = $loc;
    }

    if (isset($raw['withTags'])) $mash['with_tags'] = $raw['withTags'];
    if (isset($raw['icon'])) $mash['icon'] = $raw['icon'];
    if (isset($raw['application'])) $mash['app'] = $raw['application'];

    if (isset($raw['actions'])) $mash['actions'] = $raw['actions'];
    if (isset($raw['likes'])) $mash['likes'] = $raw['likes'];
    if (isset($raw['comments'])) $mash['comments'] = $raw['comments'];
    if (isset($raw['created'])) $mash['created_time'] = strtotime($raw['created']);
    if (isset($raw['updated'])) $mash['updated_time'] = strtotime($raw['updated']);
    return json_encode($mash);
  }
}