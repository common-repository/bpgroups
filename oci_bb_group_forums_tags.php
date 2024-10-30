<?php
/*
Name: bpGroups - Template tags
URI: http://code.ourcommoninterest.org/
Description: These functions do not constitute a plugin. Just template tags.
Author: Burt Adsit
Version: 0.32
Author URI: http://code.ourcommoninterest.org/
License: GNU GENERAL PUBLIC LICENSE 3.0 http://www.gnu.org/licenses/gpl.txt
*/

/**
 * oci_xprofile_field_value()
 *
 * Return the specified xprofile field value
 * Note: dates are in unix time format
 *
 * @param <int> $user
 * @param <string> $group name
 * @param <string> $field name
 * @return <type> unknown
 */
function oci_xprofile_field_value($user, $group, $field){
	$bp_user = oci_get_userdata($user);
	return $bp_user['xprofile_' . $group . '_' . $field]['value'];
}

/**
 * oci_xprofile_field()
 *
 * Return the specified xprofile field array
 * Note: dates are in unix time format
 *
 * The field array is composed of:
 * array(
 * 'group' => group name string,
 * 'name' => field name string,
 * 'value' => field value string,
 * 'type' => bp's name for the field type
 * )
 *
 * @param <int> $user
 * @param <string> $group name
 * @param <string> $field name
 * @return <type> array
 */
function oci_xprofile_field($user, $group, $field){
	$bp_user = oci_get_userdata($user);
	return $bp_user['xprofile_' . $group . '_' . $field];
}

/**
 * oci_is_users_forum()
 *
 * Returns true or false if the current $forum is in the user's list of forums
 *
 * @global <type> $forum
 * @global <type> $bb_current_user
 * @return <type> bool
 */
function oci_is_users_forum(){
  global $forum, $bb_current_user;
  
  return in_array((int)$forum->forum_id,(array)$bb_current_user->bbGroups['users_forums']);
}

/**
 * oci_user_avatar()
 *
 * Return the url to the user's avatar in bp
 *
 * @param <type> $u
 * @return <url> avatar thumbnail url
 */
function oci_user_avatar($u){
  $bp_user = oci_get_userdata($u);
  return $bp_user['avatar_thumb'];
}

/**
 * oci_user_link()
 *
 * Return the url to the user's profile
 *
 * @param <type> $u
 * @return <url> url to profile
 */
function oci_user_link($u){
  $bp_user = oci_get_userdata($u);
  return trailingslashit($bp_user['user_url']);
}

/**
 * oci_user_name()
 *
 * Return the user's full name in bp
 *
 * @param <type> $u
 * @return <string>
 */
function oci_user_name($u){
  $bp_user = oci_get_userdata($u);
  return $bp_user['fullname'];  
}

/**
 * oci_is_group_forum()
 *
 * Return true or false based on if the current $forum is a bp group forum
 *
 * @return <type> bool
 */
function oci_is_group_forum(){
  $bp_group = oci_get_forumdata();
  if (!$bp_group) return false; else return $bp_group['id'];
}

/**
 * oci_group_avatar()
 *
 * Return the url to the group's avatar
 *
 * @global <type> $forum
 * @return <type> url
 */
function oci_group_avatar(){
  global $forum;
  $bp_group = oci_get_forumdata();
  return $bp_group['avatar_thumb'];
}

/**
 * oci_group_link()
 *
 * Return the url to the current forum's group home page in bp
 *
 * @return <type> url
 */
function oci_group_link(){
  $bp_group = oci_get_forumdata();
  $options = oci_group_forums_options();
  return trailingslashit($options['buddypress_site_url']) . 'groups' . '/' . $bp_group['slug'];  
}

/**
 * oci_group_name()
 *
 * Return the name of the bp group for this forum
 *
 * @return <type> string
 */
function oci_group_name(){
  $bp_group = oci_get_forumdata();
  return $bp_group['name'];  
}

/**
 * oci_group_description()
 *
 * Return the group description for this forum
 *
 * @return <type> string
 */
function oci_group_description(){
  $bp_group = oci_get_forumdata();
  return $bp_group['description'];  
}

/**
 * oci_get_group_staff()
 *
 * Return an array of user_id's for admins/mods for this group forum
 * 
 * @return <type> array of user_id for group admins/mods
 */
function oci_get_group_staff(){
  $bp_group = oci_get_forumdata();

  return $bp_group['admins'];
}

function oci_topic($t){
  global $topic;
?>
<tr<?php topic_class(); ?>>
  <td><?php bb_topic_labels(); ?>
  <a href="<?php topic_link(); ?>"><?php topic_title(); ?></a><br />

  <?php topic_page_links(); ?>
  </td>
  <td class="num"><?php topic_posts(); ?></td>
  <td class="num"><?php topic_last_poster(); ?></td>
  <td class="num"><a href="<?php topic_last_post_link(); ?>"><?php topic_time(); ?></a></td>
</tr>
<?php
}

function oci_voices_options_front(){
?>
  <div id="oci-options">
  <span class="oci-title"><?php _e('Voices'); ?></span>
  <form action="" method="post" class="oci-options-form" >
      <INPUT  TYPE  = "checkbox" NAME  = "group"  VALUE  = "Open" Checked /> Open
      <INPUT  TYPE  = "checkbox" NAME  = "group"  VALUE  = "Groups" Checked /> Groups
      <INPUT  TYPE  = "checkbox" NAME  = "group"  VALUE  = "Lonely" Checked /> Lonely
      <INPUT  TYPE  = "checkbox" NAME  = "group"  VALUE  = "Mine" Checked /> Mine
      <input type="hidden" name="oci-options-forums" id="oci-options-forums" value="save" />
  </form>
  </div>
<?php
}

function oci_voices_options_forum(){
?>
  <div id="oci-options">
  <span class="oci-title"><?php _e('Voices'); ?></span>
  <form action="" method="post" class="oci-options-form" >
      <INPUT  TYPE  = "checkbox" NAME  = "group"  VALUE  = "All" Checked /> All
      <INPUT  TYPE  = "checkbox" NAME  = "group"  VALUE  = "Lonely" Checked /> Lonely
      <INPUT  TYPE  = "checkbox" NAME  = "group"  VALUE  = "Mine" Checked /> Mine
      <input type="hidden" name="oci-options-forums" id="oci-options-forums" value="save" />
  </form>
  </div>
<?php
}

function oci_forum_list($type, $title, $avatars = true){
  global $oci_ro, $oci_hid, $oci_open, $oci_urof, $oci_uhf, $forum;

  $forum_save = $forum;
  $forum_list = array();
  switch ($type){
    case 'open':
      $forum_list = $oci_open;
    break;
    case 'readonly':
      $forum_list = $oci_ro;
    break;
    case 'hidden':
      $forum_list = $oci_uhf;
    break;
    case 'all':
      $forum_list = array_merge($oci_open, $oci_ro, $oci_hid);
    break;
  }
  if (!$forum_list) return;
?>
<table id="forumlist">
<tr>
  <th><?php _e($title); ?></th>
  <th><?php _e('Topics'); ?></th>
  <th><?php _e('Posts'); ?></th>
</tr>
<?php

  foreach( $forum_list as $f ) :
  $forum = get_forum($f);

?>
<tr>
  <td>
  <div class="oci-vcard">
  <?php if($avatars && oci_is_users_forum()) : ?>
    <img class="oci-avatar" src="<?php echo oci_group_avatar() ?>">
  <?php endif; ?>
    <a class="oci-link" href="<?php forum_link(); ?>">
    <?php forum_name(); ?></a><br />
    <div class="oci-description"><?php forum_description('before='); ?></div>
  </div>
  </td>
  <td class="num"><?php forum_topics(); ?></td>
  <td class="num"><?php forum_posts(); ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php
  $forum = $forum_save;
}

?>
