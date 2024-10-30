<?php
/*
Plugin Name: bpGroups - BuddyPress Plugin
Plugin URI: http://wordpress.org/extend/plugins/bpgroups/
Description: Implements BP's group forums for bbPress. Public, Private and Hidden forums with group forum moderation. This is the BuddyPress plugin.
Author: Burt Adsit
Version: 0.36
Author URI: http://buddypress.org/developers/burtadsit/
License: GNU GENERAL PUBLIC LICENSE 3.0 http://www.gnu.org/licenses/gpl.txt
*/

/*
* Credits
* 
* apeatling for buddypress
* sambauers for bbpress and bbpress_live plugin for wp/bbpress 
* _ck_ for read-only-forums.php & hidden-forums.php for bbpress
* johnjamesjacoby for sharing and testing above and beyond
 * 
*/

/**
* If a file named 'oci_bp_custom.php' exists in the same dir as this, load it up.
* 
*/
if ( file_exists( dirname( __FILE__ ) . '/oci_bp_custom.php' ) )
  require_once( dirname( __FILE__ ) . '/oci_bp_custom.php' );

require_once(ABSPATH . WPINC . '/class-IXR.php');


define('BBGROUPSVER', 0.36);

/**
* oci_server_new_topic()
* 
* xmlrpc server fn. responds to bp.ociNewTopic from bbpress
* 
* Updates the bp activity stream.
* 
* @param mixed $args
*/
function oci_server_new_topic($args){
  global $group_obj;
  
  oci_bp_escape($args);
  
  $username = array_shift($args);
  $password = array_shift($args);
  
  $auth_ok = oci_authenticate($username, $password);
	if ($auth_ok !== true)
    return $auth_ok;
  
  extract($args);
/*  
  $topic = $args['topic'];
  $post = $args['post'];
  $group_id = $args['group_id'];
*/  
  
  $activity = array( 
    'item_id' => (int)$group_id, 
    'component_name' => 'groups', 
    'component_action' => 'new_forum_topic', 
    'is_private' => 0, 
    'secondary_item_id' => (int)$topic['topic_id'],
    'user_id' => (int)$topic['topic_poster'] );
  
  // create a group obj that the rest of bp can use, play nice
  $group_obj = new BP_Groups_Group($group_id);
  groups_record_activity($activity);
  
  do_action( 'groups_new_forum_topic', $group_id, $topic );
  return $activity;
}

/**
* oci_server_new_post()
* 
* xmlrpc server fn. Responds to bp.ociNewPost from bbpress
* 
* Updates the bp activity stream
* 
* @param mixed $args
*/
function oci_server_new_post($args){
  global $group_obj;
  
  oci_bp_escape($args);
  
  $username = array_shift($args);
  $password = array_shift($args);
  
  $auth_ok = oci_authenticate($username, $password);
	if ($auth_ok !== true)
    return $auth_ok;
  
  extract($args);
/*  
  $topic = $args['topic'];
  $post = $args['post'];
  $group_id = (int)$args['group_id'];
*/  
 
  $activity = array( 
    'item_id' => (int)$group_id, 
    'component_name' => 'groups', 
    'component_action' => 'new_forum_post', 
    'is_private' => 0, 
    'secondary_item_id' => (int)$post['post_id'],
    'user_id' => (int)$post['poster_id'] );
  
  // create a group obj that the rest of bp can use, play nice
  $group_obj = new BP_Groups_Group($group_id);
  groups_record_activity($activity);
  do_action( 'groups_new_forum_topic_post', $group_id, $post );

  return $args;
}

/**
 * oci_bb_group_new()
 * 
 * xmlrpc client fn
 * Triggers on a new group with a forum created. Tell bbpress about it by sending all the
 * appropriate info.
 * 
 * @return 
 * @param object $forum_id
 * @param object $group_id
 */
function oci_bb_group_new($forum, $group_id){
	$bp_group = oci_get_group($group_id);
  $admin = $bp_group['admins'][0];
  $bp_user = oci_get_user($admin);
  
  $params = array('group' => $bp_group, 'user' => $bp_user);
  
	$results = oci_bp_xmlrpc_query('bb.ociGroupNew',$params);
}
add_action( 'groups_new_group_forum', 'oci_bb_group_new', 10, 2 );

/**
 * oci_bb_group_updated()
 * 
 * xmlrpc client fn
 * Group details such as title, desc, avatar may have changed. Tell bbpress.
 * 
 * @return 
 * @param object $group_id
 */
function oci_bb_group_updated($group_id){
	$bp_group = oci_get_group($group_id);
	$results = oci_bp_xmlrpc_query('bb.ociGroupUpdate',$bp_group);	
}
add_action( 'groups_settings_updated', 'oci_bb_group_updated', 10, 1 );
add_action( 'groups_details_updated', 'oci_bb_group_updated', 10, 1 );
add_action( 'groups_group_avatar_edited', 'oci_bb_group_updated', 10, 1 );

/**
* oci_bb_group_user_joinleave()
* 
* Adds or removes user access to a forum in bbpress. Updates user meta data in bbpress.
* 
* Responds to action groups_join_group, groups_leave_group
* 
* @param mixed $group_id
* @param mixed $user_id
*/
function oci_bb_group_user_joinleave($group_id, $user_id){
  $bp_user = oci_get_user($user_id);
  $results = oci_bp_xmlrpc_query('bb.ociUserUpdate',$bp_user);
}
add_action( 'groups_join_group', 'oci_bb_group_user_joinleave', 10, 2);
add_action( 'groups_leave_group', 'oci_bb_group_user_joinleave', 10, 2);

/**
* oci_bb_group_user_status_change()
* 
* Responds to promotion, demotion, ban, unban, invite acceptance and membership acceptance.
* Updates user meta data in bbpress.
* 
* @param mixed $user_id
* @param mixed $group_id
*/
function oci_bb_group_user_status_change($user_id, $group_id){
  $bp_user = oci_get_user($user_id);
  $results = oci_bp_xmlrpc_query('bb.ociUserUpdate',$bp_user);
}
add_action( 'groups_promoted_member', 'oci_bb_group_user_status_change', 10, 2);
add_action( 'groups_demoted_member', 'oci_bb_group_user_status_change', 10, 2);
add_action( 'groups_banned_member', 'oci_bb_group_user_status_change', 10, 2);
add_action( 'groups_unbanned_member', 'oci_bb_group_user_status_change', 10, 2);
add_action( 'groups_accept_invite', 'oci_bb_group_user_status_change', 10, 2);
add_action( 'groups_membership_accepted', 'oci_bb_group_user_status_change', 10, 2);

/**
 * oci_bb_user_login()
 * 
 * The purpose of this function is to try and eliminate some problems associated with the user registration and account activation process.
 * It's possible to register and go directly to bbpress without activating the user account. 'wpmu_new_user' event isn't triggered in 
 * that case. Weird things happen then. The user *has* to login sometime so make sure the bbpress meta data is updated.
 * 
 * @global <type> $bp
 * @param <type> $username 
 */
function oci_bb_user_login($username){
	global $bp;
	// wp passes the username not the user id
  $bp_user = oci_get_user($bp->loggedin_user->id);
  $results = oci_bp_xmlrpc_query('bb.ociUserUpdate',$bp_user);
}
add_action( 'wp_login', 'oci_bb_user_login');

/**
* oci_bb_group_user_change()
* 
* Responds to various actions in bp that relate to users but we don't get passed a user_id
* 
*/
function oci_bb_group_user_change($dummy1 = false, $dummy2 = false, $dummy3 = false){
  global $bp;
  
  $bp_user = oci_get_user($bp->loggedin_user->id);
  $results = oci_bp_xmlrpc_query('bb.ociUserUpdate',$bp_user);
}
add_action( 'bp_core_delete_avatar', 'oci_bb_group_user_change', 10, 2);
add_action( 'bp_core_avatar_save', 'oci_bb_group_user_change', 10, 3);
add_action( 'xprofile_updated_profile', 'oci_bb_group_user_change', 10, 1);

/*
add_action( 'groups_remove_data', $user_id ); // kills all data for user on wpmu delete user
don't think anything has to be done with this action. 
*/

/**
 * oci_get_group()
 * 
 * utility fn
 * 
 * Builds a new BP_Groups_Group obj for the specified group_id
 * Package it up for use in xmlrpc to bbpress
 * 
 * @return array $good_results
 * @param array $args array(group_id)
 */
function oci_get_group($group_id){
  
	$group = new BP_Groups_Group($group_id);

	$good_results = array(
		'id' => (int)$group->id,
		'name' => $group->name,
		'slug' => $group->slug,
		'description' => $group->description,
		'status' => $group->status,
		'enable_forum' => $group->enable_forum,
		'avatar_thumb' => $group->avatar_thumb,
		'admins' => oci_make_good_admins($group->id),
		'forum_id' => (int)groups_get_groupmeta($group->id,'forum_id')
		);
		
	return apply_filters('oci_get_group', $good_results);
}

/**
* oci_make_good_admins()
* 
* utility fn
* 
* Builds a list of admins and mods. Get sent along to bbpress with group info.
* 
* @param mixed $group
*/
function oci_make_good_admins($group){
	$mods = groups_get_group_mods($group);
	$admins = groups_get_group_admins($group);
	
	if($admins){
		foreach($admins as $admin){
			$good_results[] = (int)$admin->user_id;
		}
	}
	if($mods){
		foreach($mods as $mods){
			$good_results[] = (int)$mods->user_id;
		}
	}

	return $good_results;
}

/**
 * oci_get_user()
 * 
 * utility fn
 * 
 * Builds a new BP_Core_User obj for the specified user_id. Packages it up for xmlrpc use.
 * 
 * @return array $good_results array()
 * @param int $user_id
 */
function oci_get_user($user_id){
	$user_id = (int) $user_id;
	$user = new BP_Core_User($user_id);
	
	$good_results = array(
		'id' => (int)$user->id,
		'avatar_thumb' => $user->avatar_thumb,
		'fullname' => $user->fullname,
		'email' => $user->email,
		'user_url' => $user->user_url
		);
		
	return apply_filters('oci_get_user', $good_results);
}

/**
 * oci_get_user_filter()
 * 
 * Live example of how to use the filter mechanism to add info to the data going across to bbpress
 * This filter adds the user's forums and forums where the user is staff into the $user array
 * 
 * @return 
 * @param associative array $user from oci_get_user()
 */
function oci_get_user_filter($user){
	$users_group_info = oci_get_users_group_info($user['id']);
	
	$user['users_forums'] = $users_group_info['forum_ids'];
	$user['user_is_staff'] = $users_group_info['staff_ids'];
	return $user;
}
add_filter('oci_get_user','oci_get_user_filter',10,1);

/**
 * oci_get_xprofile_filter()
 *
 * This filter adds all xprofile groups and group fields to bbpress
 * 
 * @param <array> $user
 * @return <array> $user array for xmlrpc transport
 */
function oci_get_xprofile_filter($user){
	$xprofile_groups = BP_XProfile_Group:: get_all(true); // all except empty groups
	if ($xprofile_groups){
		foreach($xprofile_groups as $group){
			foreach($group->fields as $group_field){ // all fields for group, id and type obj from sql
				$field_obj = new BP_XProfile_Field($group_field->id, $user['id'],true); // this field
				$field_obj->data->value = bp_unserialize_profile_field( $field_obj->data->value);
				$field_obj->data->value = xprofile_filter_format_field_value( $field_obj->data->value, $field_obj->type );
				// xprofile_groupname_fieldname to prevent conflicts
				$user['xprofile_' . $group->name . '_' . $field_obj->name] = array('group' => $group->name, 'name' => $field_obj->name, 'value' => $field_obj->data->value, 'type' => $field_obj->type);
			}
		}
	}
	return $user;
}
add_filter('oci_get_user','oci_get_xprofile_filter',10,1);

/**
* oci_authenticate()
* 
* utility fn
* 
* 
* 
* @param mixed $username
* @param mixed $password
*/
function oci_authenticate($username, $password){
  
 if (!user_pass_ok($username, $password)){
		$error = new IXR_Error( 403, __( 'Authentication failed.' ) );
      return $error;
 }

  return true;  
}

/**
* oci_bp_escape()
* 
* Sanitize incoming xmlrpc data. Stolen from one of the xmlrpc.php files.
* 
* @param mixed $array
*/
function oci_bp_escape( &$array )
{
  global $wpdb;

  if ( !is_array( $array ) ) {
    // Escape it
    $array = $wpdb->escape( $array );
  } elseif ( count( $array ) ) {
    foreach ( (array) $array as $k => $v ) {
      if ( is_array( $v ) ) {
        // Recursively sanitize arrays
        oci_bp_escape( $array[$k] );
      } elseif ( is_object( $v ) ) {
        // Don't sanitise objects - shouldn't happen anyway
      } else {
        // Escape it
        $array[$k] = $wpdb->escape( $v );
      }
    }
  }

  return $array;
}

/**
 * oci_get_users_group_info()
 * 
 * utility fn
 * 
 * get all forum_ids for the user and forum_ids where the user is staff
 * 
 */
function oci_get_users_group_info($user_id) {
  global $wpdb, $bp;
  $forum_ids = $staff_ids = array();
  
  // get user's groups where they have been confirmed and they aren't banned
  $group_sql = $wpdb->prepare( "SELECT group_id FROM " . $bp->groups->table_name_members . " WHERE user_id = %d AND is_confirmed = 1 AND is_banned = 0", $user_id );  
  $group_results = $wpdb->get_results( $group_sql );
  
  if ($group_results) {
    foreach($group_results as $group) {
      if ($forum_id = groups_get_groupmeta($group->group_id,'forum_id')) {
        $forum_ids[] = (int)$forum_id;
        if (BP_Groups_Member::check_is_admin($user_id, $group->group_id))
          $staff_ids[] = (int)$forum_id;
        if (BP_Groups_Member::check_is_mod($user_id, $group->group_id))
          $staff_ids[] = (int)$forum_id;
      }
    }
  }
  return array('forum_ids' => $forum_ids, 'staff_ids' => $staff_ids);
}

/**
 * oci_server_get_all_groups()
 * 
 * xmlrpc server method - responds to bp.ociGetAllGroups from bbpress
 * 
 * Return all groups that have forums enabled and all group users without dups.
 * 
*/
function oci_server_get_group_ids($args) {
	global $wpdb,$bp;
	
  oci_bp_escape($args);
  
  $username = array_shift($args);
  $password = array_shift($args);

  $auth_ok = oci_authenticate($username, $password);
	if ($auth_ok !== true)
    return $auth_ok;

	$sql = $wpdb->prepare( "SELECT id as group_id FROM " . $bp->groups->table_name . " WHERE enable_forum = 1"); 	
	$results = $wpdb->get_results($sql);

	if ($results) {
		foreach($results as $g) {      
      $group_ids[] = $g->group_id;
		}
	}
	
  return $group_ids;
}

function oci_server_get_group($args){
	global $wpdb,$bp;

  oci_bp_escape($args);

  $username = array_shift($args);
  $password = array_shift($args);

  $auth_ok = oci_authenticate($username, $password);
	if ($auth_ok !== true)
    return $auth_ok;

	return oci_get_group($args[0]);
}

function oci_server_get_user($args){
	global $wpdb,$bp;

  oci_bp_escape($args);

  $username = array_shift($args);
  $password = array_shift($args);

  $auth_ok = oci_authenticate($username, $password);
	if ($auth_ok !== true)
    return $auth_ok;

	$user = oci_get_user($args[0]);

	if ($user)
		return $user;
	else
		return false;
}

function oci_server_get_user_ids($args){
	global $wpdb,$bp;

  oci_bp_escape($args);

  $username = array_shift($args);
  $password = array_shift($args);

  $auth_ok = oci_authenticate($username, $password);
	if ($auth_ok !== true)
    return $auth_ok;

	$bbpress_live_options = get_blog_option(1, 'bbpress_live_fetch');
	
	$users = $wpdb->get_results("SELECT ID, user_login FROM $wpdb->users ORDER BY ID");
	foreach($users as $u){
		 if ($bbpress_live_options['username'] != $u->user_login) // skip the buddypress user
				$all_users[] = $u->ID;
	}

	return $all_users;
}

/**
 * oci_bp_server_methods()
 * 
 * Register the xmlrpc methods we are imlementing.
 * 
 * @return array $methods
 * @param array $methods
 */
function oci_bp_server_methods($methods) {

    $methods['bp.ociGetGroupIds'] = 'oci_server_get_group_ids';
		$methods['bp.ociGetGroup'] = 'oci_server_get_group';

		$methods['bp.ociGetUserIds'] = 'oci_server_get_user_ids';
		$methods['bp.ociGetUser'] = 'oci_server_get_user';
		
		$methods['bp.ociNewTopic'] = 'oci_server_new_topic';
		$methods['bp.ociNewPost'] = 'oci_server_new_post';
		
    return $methods;
}
add_filter('xmlrpc_methods', 'oci_bp_server_methods');

/**
 * oci_bp_xmlrpc_query()
 * 
 * instantialte an xmlrpc client 
 * stuff the appropriate username and password
 * make the fn call passed in $method with $args
 * return whatever we get back
 * 
*/
function oci_bp_xmlrpc_query( $method, $args = false) {
  
  if (!$method)
    return;

  // get_option() doesn't work in a bp DOING_AJAX context
  $bbpress_live_options = get_blog_option(1, 'bbpress_live_fetch');
  
  $site = $bbpress_live_options['target_uri'];
    
  $client = new IXR_Client( trailingslashit($site) . 'xmlrpc.php' );
  $client->debug = false;
  $client->timeout = 3;
  $client->useragent .= ' -- bpgroupforums /0.2';

  if (empty($args))
    $args = array();

  $username = $bbpress_live_options['username'];
  $password = $bbpress_live_options['password'];
  
  array_unshift( $args, $username, $password );
    
  if ( !$client->query( $method, $args ) ) {
    //var_dump( "err: ",$client->message, $client->error ); die;
    return false;
  }
  return $client->getResponse();
}

/**
 * oci_bp_group_forums()
 * 
 * Catch wp's 'init' hook to init some things we need from bp on an xmlrpc request
 * xmlrpc does a wp-load.php which ends with do_action('init')
 * Fire up enough of bp to get things done.
 * 
 * Wake Andy up just enough to get things done. Nudge, nudge.
 * 
 */
function oci_bp_group_forums() {
	
	// only do this if the xmlrpc server is firing up and we are servicing a request
	if ( defined('XMLRPC_REQUEST') && XMLRPC_REQUEST ){
    // bp's globals don't get set until a 'wp' action occurs. this seems to be all that is needed.
		// missing forums component globals for the moment
		bp_core_setup_globals();
		groups_setup_globals();
    bp_activity_setup_globals();
		bp_blogs_setup_globals();
		xprofile_setup_globals();
		friends_setup_globals();
		messages_setup_globals();
		bp_wire_setup_globals();
	}
}
add_action('init','oci_bp_group_forums');


?>
