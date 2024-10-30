<?php
/*
Plugin Name: bpGroups - bbPress Plugin
Plugin URI: http://code.ourcommoninterest.org/
Description: Implements BP's group forums for bbPress. Public, Private and Hidden forums with group forum moderation. This is the bbPress plugin.
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
*  johnjamesjacoby for sharing and testing above and beyond
 *
*/

/**
* If a file named 'oci_bb_custom.php' exists in the same dir as this, load it up.
* 
*/
if ( file_exists( dirname( __FILE__ ) . '/oci_bb_custom.php' ) )
  require_once( dirname( __FILE__ ) . '/oci_bb_custom.php' );


require_once( BACKPRESS_PATH . '/class.ixr.php' );


define('BBGROUPSVER', 0.36);

/**
* globals
* 
*/
$oci_ro = array(); // public group forum_ids array(forum_id)
$oci_hid = array(); // private or hidden group forum_ids array(forum_id)
$oci_uhf = array(); // user's hidden forum_ids array(forum_id)
$oci_urof = array(); // user's readonly forums array(forum_id)
$oci_open = array(); // open forums not paid attention to at all
$oci_options = array(); // global configuration options

/**
 * oci_bp_group_forums_import()
 * 
 * xmlrpc client fn
 * 
 * Get all bp groups and users. For each bp group create bbpress forum meta data and user meta data.
 * Forum privacy and user access to group forums are controlled by this meta data. If forum meta data
 * does not exist for a forum then it will not be treated as a bp group forum and no restrictions
 * will take effect for that forum.
 * 
 * @return 
 */
function oci_bp_group_forums_import() {
  
  $options = oci_group_forums_options();
  $options['import_start'] = time();
  $options['users_imported'] = 0;
  $options['forums_updated'] = 0;
  $options['groups_enabled'] = 0;
    
  $group_ids = oci_bb_xmlrpc_query('bp.ociGetGroupIds');
  
  if ($group_ids){
    foreach($group_ids as $g){
      $groups_enabled++;
			$group = oci_bb_xmlrpc_query('bp.ociGetGroup', array($g));
			if ($group){
				if (oci_group_update($group))
					$forums_updated++;
			}
		}
  }

	$user_ids = oci_bb_xmlrpc_query('bp.ociGetUserIds');
  //var_dump($users); die;
  if ($user_ids){
    foreach($user_ids as $u){
			$user = oci_bb_xmlrpc_query('bp.ociGetUser', array($u));
      oci_user_update($user);
      $users_count++;
    }
  }

  $options['import_end'] = time();
  $options['users_imported'] = $users_count;
  $options['forums_updated'] = $forums_updated;
  $options['groups_enabled'] = $groups_enabled;

  oci_group_forums_options_update($options);
//  var_dump($options); die;
}

/**
 * oci_bp_user_login()
 * 
 * This function is to make sure that the current user has bp meta data. See oci_bp_group_forums.php oci_bb_user_login() for why.
 * 
 * @global <type> $bb_current_user
 * @param <type> $user_id 
 */
function oci_bp_user_login($user_id){
	global $bb_current_user;

	$user = oci_bb_xmlrpc_query('bp.ociGetUser', array($bb_current_user->ID));
  oci_user_update($user);
}
add_action('bb_user_login', 'oci_bp_user_login');

/**
* oci_bp_new_post()
* 
* xmlrpc client fn
* 
* Tell bp about a new post. Can't trigger on action 'bb_new_topic' for topic because the first post
* hasn't been created at that time. Determine if this is a new topic/post or just a new post and
* call the appropriate fn in bp. Same data goes over no matter what anyway.
* 
* @param mixed $post_id
*/
function oci_bp_new_post($post_id){
  global $oci_ro;
  // noop for this
  if ( defined('XMLRPC_REQUEST') && XMLRPC_REQUEST ) 
    return;  
    
  $bb_post = bb_get_post($post_id);
  
  if (in_array((int)$bb_post->forum_id, (array)$oci_ro)){
    $new_topic = (int)$bb_post->post_position == 1 ? true : false;
    $bb_topic = get_topic((int)$bb_post->topic_id);
    $forum_data = oci_get_forumdata($bb_topic->forum_id);  

    // simulate what normally goes across to bp using bbpress_live
    $_topic = (array) $bb_topic;
    $_topic['topic_uri'] = get_topic_link( $_topic['topic_id'] );
    $_topic['topic_start_time_since'] = bb_since( $_topic['topic_start_time'] );
    $_topic['topic_time_since'] = bb_since( $_topic['topic_time'] );
    $_topic['topic_poster_display_name'] = get_user_display_name( $_topic['topic_poster'] );
    $_topic['topic_last_poster_display_name'] = get_user_display_name( $_topic['topic_last_poster'] );
    $_topic['topic_poster'];
    $_topic['topic_last_poster'];

    $_post = (array) $bb_post;
    $_post['post_uri'] = get_post_link( $_post['post_id'] );
    $_post['post_time_since'] = bb_since( $_post['post_time'] );
    $_post['poster_display_name'] = get_user_display_name( $_post['poster_id'] );
    $_post['poster_id'];
    $_post['poster_ip'];
    $_post['pingback_queued'];
    
    $args = array('topic' => $_topic, 'post' => $_post, 'group_id' => $forum_data['id']); 
    
    if ($new_topic)
      $results = oci_bb_xmlrpc_query('bp.ociNewTopic',$args);
    else
      $results = oci_bb_xmlrpc_query('bp.ociNewPost',$args);
    //var_dump($results); die;
  }
}
add_action('bb_new_post', 'oci_bp_new_post', 10, 1);

/**
 * oci_server_group_update()
 * 
 * xmlrpc server fn
 * 
 * Responds to client call from bp to update group meta data in bbpress.
 * 
 * @return 
 * @param object $args
 */
function oci_server_group_update($args){
  oci_bb_escape( $args ); 

  $username = array_shift($args);
  $password = (string) array_shift($args);

  $user = oci_bb_authenticate($username, $password, 'manage_forums' );

  if (oci_is_error($user))
    return $user;

	$bp_group = $args;
	$result = oci_group_update($bp_group);
		
	return $result;
}

/**
 * oci_server_user_update()
 * 
 * xmlrpc server fn
 * 
 * Called by client function in bp to update user meta data.
 * 
 * @return 
 * @param object $args
 */
function oci_server_user_update($args){
  oci_bb_escape( $args ); 

  $username = array_shift($args);
  $password = (string) array_shift($args);

  $user = oci_bb_authenticate($username, $password, 'manage_forums' );

  if (oci_is_error($user))
    return $user;
  
	$bp_user = $args;
	oci_user_update($bp_user);
}

/**
 * oci_server_group_new()
 * 
 * xmlrpc server fn
 * 
 * A new group was created in bp with forum enabled. We get group and user info. User is the group admin.
 * 
 * @return 
 * @param object $args
 */
function oci_server_group_new($args){

  oci_bb_escape( $args ); // acts on ref to, in place
  $username = array_shift($args);
  $password = (string) array_shift($args);

  $user = oci_bb_authenticate($username, $password, 'manage_forums' );

  if (oci_is_error($user))
    return $user;
    
  $bp_group = $args['group'];
  $bp_user = $args['user'];
  
  //var_dump($args, $user, $bp_group); die;
  oci_group_update($bp_group);
  oci_user_update($bp_user);
}

/**
 * oci_bb_server_methods()
 * 
 * Register the xmlrpc server functions that we implement.
 * 
 * @return 
 * @param object $methods
 */
function oci_bb_server_methods($methods) {

    $methods['bb.ociUserUpdate'] = 'oci_server_user_update';
    $methods['bb.ociGroupNew'] = 'oci_server_group_new';
    $methods['bb.ociGroupUpdate'] = 'oci_server_group_update';
    
    return $methods;
}
add_filter('bb_xmlrpc_methods', 'oci_bb_server_methods');

/**
* oci_user_update()
* 
* utility fn
* 
* Takes an array of user meta data that we get from bp and create user meta data in bbpress.
* 
* @param mixed $bp_user
*/
function oci_user_update($bp_user){
  $result = bb_update_usermeta($bp_user['id'],'bbGroups',$bp_user);  
}

/**
* oci_group_update()
* 
* utility fn
* 
* Takes an array of group meta data that we get from bp and creates bbpress forum meta data if the
* forum exists.
* 
* @param mixed $bp_group
*/
function oci_group_update($bp_group){
  if ($forum = get_forum((int)$bp_group['forum_id'])){
    $result = bb_update_forummeta((int)$bp_group['forum_id'],'bbGroups',$bp_group);  
  }

  return $forum;
}

/**
* oci_bb_escape()
* 
* utility fn
* 
* Sanitize incoming xmlrpc data. Stolen from xmlrpc.php
* 
* @param mixed $array
*/
function oci_bb_escape( &$array )
{
  global $bbdb;

  if ( !is_array( $array ) ) {
    // Escape it
    $array = $bbdb->escape( $array );
  } elseif ( count( $array ) ) {
    foreach ( (array) $array as $k => $v ) {
      if ( is_array( $v ) ) {
        // Recursively sanitize arrays
        oci_bb_escape( $array[$k] );
      } elseif ( is_object( $v ) ) {
        // Don't sanitise objects - shouldn't happen anyway
      } else {
        // Escape it
        $array[$k] = $bbdb->escape( $v );
      }
    }
  }

  return $array;
}

/**
* oci_is_error()
* 
* utility fn
* 
* Checks to see if it's a wp or ixr error obj, stolen from wp
* 
*/
function oci_is_error($thing){
  if ( is_object($thing) && is_a($thing, 'WP_Error') || is_a($thing, 'IXR_Error') )
    return true;
      
  return false;
}

/**
* oci_bb_authenticate()
* 
* Check access credentials and capability. Stolen from xmlrpc.php
* 
* @param mixed $username
* @param mixed $password
*/
function oci_bb_authenticate($username, $password, $cap){
    $user = bb_check_login( $username, $password );
    if ( !$user || is_wp_error( $user ) ) {
      $error = new IXR_Error( 403, __( 'Authentication failed.' ) );
      return $error;
    }

    // Set the current user
    $user = bb_set_current_user( $user->ID );

    // Make sure they are allowed to do this
    if ( !bb_current_user_can( $cap ) ) {
      if ( !$message ) {
        $message = __( 'You do not have permission to do this.' );
      }
      $error = new IXR_Error( 403, $message );
      return $error;
    }  
    
    return $user;
}


/**
 * oci_bb_xmlrpc_query()
 * 
 * instantialte an xmlrpc client 
 * stuff username and password
 * make the fn call passed in $method with $args
 * return whatever wpmu/bp gave to us
 * 
*/
function oci_bb_xmlrpc_query( $method, $args = false, $username = false) {
	global $oci_options;
	
	if (!$method) {
		return false;
	}
	// why isn't my fav fn in backpress?
	$client = new IXR_Client( trailingslashit($oci_options['buddypress_site_url']) . 'xmlrpc.php' );
	$client->debug = false;
	$client->timeout = 3;
	$client->useragent .= ' -- bbgroupforums /0.2';

  if (empty($args))
    $args = array();
    
	$username = $oci_options['username'];	
	$password = $oci_options['password'];
	
	array_unshift($args, $username, $password );

  //var_dump($args); die;
	if ( !$client->query( $method, $args ) ) {
		//var_dump( "err: ",$client->message, $client->error ); die;
		return false;
	}
	return $client->getResponse();
}

// stolen from formatting.php in wpmu
if (!function_exists('trailingslashit')){
function trailingslashit($string) {
	return untrailingslashit($string) . '/';
}
}

// stolen from formatting.php in wpmu
if (!function_exists('untrailingslashit')){
function untrailingslashit($string) {
	return rtrim($string, '/');
}
}

/**
 * oci_get_userdata()
 * 
 * Returns the specified user's group forum meta data.
 * 
 * @return array
 * @param int $u user_id
 */
function oci_get_userdata($u){
  $u = (int) $u;
	$user = bb_get_user($u);
	return stripslashes_deep((array)$user->bbGroups);
}

/**
 * oci_get_forumdata()
 * 
 * Gets the current forum's bp group meta data
 * 
 * @return array
 * @param object $f[optional]
 */
function oci_get_forumdata($forum_id = false){
	global $forum;
  
  // if passed a forum id get it and don't trash the global forum context
  $this_forum = $forum;
  if ($forum_id)
    $this_forum = get_forum($forum_id);

	return stripslashes_deep((array)$this_forum->bbGroups);
}

/**
 * oci_capability_filter()
 * 
 * This implements the group admin/moderator capabilities. It's queried from just about everywhere in bbpress.
 * Add the ability for group forum staff to moderate only in their forum without trashing the rest of the 
 * security mechanisms.
 * 
 */
function oci_capability_filter($retvalue, $capability, $args){
	global $bb_current_user, $forum,$topic_id;

	// if current user is bbpress staff don't play with them
	if ( !$bb_current_user || 
		in_array('keymaster',$bb_current_user->roles) || 
		in_array('administrator',$bb_current_user->roles) || 
		in_array('moderator',$bb_current_user->roles) ) 
		return $retvalue;
	
	// don't play with these either
	if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) return $retvalue;

	// turn on visibility of admin links in templates
	if (oci_is_user_staff() && oci_staff_has_capability($capability))	return true;
	
	// handle ajax maintenace stuff
	if (defined('DOING_AJAX') && DOING_AJAX && oci_staff_has_capability($capability)) return true;
	
	// when called from the admin functions that do the actual work, we lose forum_id context, get it back
	if (empty($forum)){
		switch($capability){
			case 'edit_post' :
			$bb_post = bb_get_post($args[1]);
			$forum_id = $bb_post->forum_id;
			break;
			case 'close_topic' || 'delete_topic' || 'move_topic' || 'edit_topic' :
			$bb_topic = get_topic($args[1]);
			$forum_id = $bb_topic->forum_id;
			break;
		}
		$forum = get_forum($forum_id);
		if (oci_is_user_staff() && oci_staff_has_capability($capability)) return true;
	}

	return $retvalue;
}
add_filter('bb_current_user_can','oci_capability_filter',10,3);

/**
 * oci_is_user_staff()
 * 
 * Determine if the current user is a group admin or mod in this forum
 * 
 * @param string forum_id
 * @return boolean
 */
function oci_is_user_staff(){
	global $forum, $bb_current_user;
  
	return in_array($forum->forum_id, (array)$bb_current_user->bbGroups['user_is_staff']);
	
}

/**
 * oci_staff_has_capability()
 * 
 * Validates if the user's capability is one of those we have designated as group forum moderator.
 * 
 * @return false or >0
 * @param object $cap
 */
function oci_staff_has_capability($cap){
	// these are the capabilities of a group forum moderator
	return in_array($cap, array(
		'ignore_edit_lock',
		
		'manage_tag',
		'edit_tag',
		'edit_tag_by_on',
		
		'delete_topic',
		'close_topic',
		'move_topic',
		'edit_topic',

		'edit_closed',
		'edit_deleted',
		'browse_deleted',

		'delete_post',
		'edit_post',
		
		'write_topic',
		'write_post'		
		)
	);
}


/**
 * oci_group_forums_options()
 * 
 * Get the options for bp group forums. If they've never been gotten, create defaults for them.
 * 
 * @return array $group_forums_options
 */
function oci_group_forums_options(){
	$group_forums_defaults = array(
		'buddypress_site_url' => bb_get_option('wp_siteurl'),
	  'deny_access_message' => __('Join this group to participate.','oci'),
		'username' => '',
		'password' => '',
		'groups_enabled' => 0,
		'forums_updated' => 0,
		'users_imported' => 0,
		'import_start' => 0,
		'import_end' => 0,
    'all_users' => 0
   );

	$group_forums_options=bb_get_option('oci_group_forums');

  if (!$group_forums_options) {
      oci_group_forums_options_update($group_forums_defaults);
			$group_forums_options=$group_forums_defaults;
	}

	return $group_forums_options;
}

function oci_group_forums_admin_import(){
  if (empty($_POST) || $_POST['action'] != "import") 
    return;

  if ( !bb_current_user_can('use_keys') )
    bb_die(__('Cheatin&#8217; uh?'));

  bb_check_admin_referer('oci-group-forums-admin-import');

  $options = oci_group_forums_options();
  
  oci_group_forums_options_update($options);

  oci_bp_group_forums_import();
  oci_init_group_forums();
  
}
add_action('bb_admin-header.php', 'oci_group_forums_admin_import');

/**
 * oci_group_forums_admin_update()
 * 
 * This is the fn that gets the settings info from bbpress admin, validate and save.
 * 
 * @return 
 */
function oci_group_forums_admin_update(){
  if (empty($_POST) || $_POST['action'] != "update") 
    return;

  if ( !bb_current_user_can('use_keys') )
    bb_die(__('Cheatin&#8217; uh?'));

  bb_check_admin_referer('oci-group-forums-admin-update');
	
	$options = oci_group_forums_options();
  $options["buddypress_site_url"] = $_POST['buddypress-site-url'];
  $options["deny_access_message"] = $_POST['deny-access-message'];
  $options["username"] = $_POST['username'];

  // let the crude validation routines begin
  if ($_POST["pass1"] == $_POST["pass2"]){
    // do something
    $options["password"] = $_POST["pass1"];
  }
  else $msg = __("Sorry: passwords do not match.",'oci');

  if ($options["buddypress_site_url"] == ''){
    $msg = __("Sorry: BuddyPress site url can not be blank.",'oci');
  }
    
  if ($options["username"] == ''){
    $msg = __("Sorry: BuddyPress username can not be blank.",'oci');
  }


  if (!$msg){
    $msg = __("Settings updated.",'oci');
    oci_group_forums_options_update($options);
  } 
  bb_admin_notice($msg); 
  
}
add_action('bb_admin-header.php', 'oci_group_forums_admin_update');

/**
 * oci_group_forums_options_update()
 * 
 * Update the options for bp group forums.
 * 
 * @return
 */
function oci_group_forums_options_update($options){

	bb_update_option('oci_group_forums', $options);
}

/**
 * oci_group_forums_settings()
 * 
 * bbpress back end admin options screen for bp group forums.
 * 
 * @return 
 */
function oci_group_forums_admin_screen() {
	global $oci_ro, $oci_hid, $oci_open;
	
	$group_forums_options	 = oci_group_forums_options();
?>  
<div class="wrap">
		<h3><?php _e( 'bpGroups Settings','oci' ) ?></h3>

		<form action="<?php echo bb_get_admin_tab_link("oci_group_forums_admin_screen"); ?>" method="post" class="settings" id="oci-group-forums-admin-update">
		<fieldset>

    <div>
  	  <label for="buddypress-site-url">
    				<?php _e('BuddyPress Site URL','oci') ?>
  		</label>
  		<div>
        <input class="text long" name="buddypress-site-url" id="buddypress-site-url"  value="<?php echo $group_forums_options['buddypress_site_url'] ?>" />
        <p><?php _e('Example: http://mysite.org/','oci') ?></p>
      </div>
    </div>		

    <div>
  	  <label for="deny-access-message">
 				<?php _e('Deny Access Message') ?>
  		</label>
  		<div>
  			<input class="text long" name="deny-access-message" id="deny-access-message"  value="<?php echo $group_forums_options['deny_access_message'] ?>" />
  			<p><?php _e('Message to give users if the forum is readonly and they are not a member of the group','oci') ?></p>
      </div>
    </div>		

    <div>
  	  <label for="username">
 				<?php _e('Authorized BuddyPress Username','oci') ?>
  		</label>
  		<div>
  			<input class="text" name="username" id="username"  value="<?php echo $group_forums_options['username'] ?>" />
  			<p><?php _e('The user that BuddyPress uses to access bbPress','oci') ?></p>
      </div>
    </div>		

    <div>
  	  <label for="pass1">
 				<?php _e('Password'); ?>
  		</label>
  		<div>
  			<input name="pass1" type="password" id="pass1" autocomplete="off" />
      </div>
  		<div>
  			<input name="pass2" type="password" id="pass2" autocomplete="off" />
      </div>
    </div>		
		</fieldset>

    <fieldset class="submit">
    	<?php bb_nonce_field( 'oci-group-forums-admin-update' ); ?>
    	<input type="hidden" name="action" value="update" />
    	<input class="submit" type="submit" name="submit" value="<?php _e('Save Changes','oci') ?>" />
    </fieldset>

    </form>

	<h3><?php _e( 'bpGroups Import','oci' ) ?></h3>
	<form action="<?php echo bb_get_admin_tab_link("oci_group_forums_admin_screen"); ?>" method="post" class="settings" id="oci-group-forums-admin-import">
    <fieldset>
    <div>
        <label>
            <?php _e('Last import','oci') ?>
        </label>
        <div>
            <p><?php echo date("l dS \of F Y h:i:s A", $group_forums_options['import_end']); ?>, <?php echo $group_forums_options['import_end'] - $group_forums_options['import_start']; ?> seconds</p>
        </div>
    </div>
    <div>
        <label>
            <?php _e('Statistics','oci') ?>
        </label>
        <div>
          <p><?php _e('Groups with forums enabled','oci'); echo ' - ' . $group_forums_options['groups_enabled'] . ', '; ?>
          <?php _e('Forums updated','oci'); echo ' - ' . $group_forums_options['forums_updated'] . ', '; ?>
          <?php _e('Users updated','oci'); echo ' - ' . $group_forums_options['users_imported']; ?></p>
        </div>
    </div>
    </fieldset>

      <fieldset class="submit">
    	<?php bb_nonce_field( 'oci-group-forums-admin-import' ); ?>
    	<input type="hidden" name="action" value="import" />
    	<input class="submit" type="submit" name="submit" value="<?php _e('Import Groups') ?>" />
	    </fieldset>
		</form>		

</div>

<?php	
}

/**
 * oci_group_forums_admin()
 * 
 * Registers the back end admin screen with bbpress.
 * 
 * @return 
 */
function oci_group_forums_admin_menu() {
	global $bb_submenu; 
	$bb_submenu['options-general.php'][] = array(__('bpGroups'), 'use_keys', 'oci_group_forums_admin_screen');
}
add_action( 'bb_admin_menu_generator', 'oci_group_forums_admin_menu');

/**
 * oci_group_forums_enable()
 * 
 * Get the meta data for each forum and build a list of bp group public and private/hidden forums.
 * 
 * 
*/
function oci_init_group_forums() {
  global $oci_ro, $oci_hid, $oci_open;
  
  if (!$options = oci_group_forums_options())
    return;
  
  $get_forums_args = array(
    'child_of' => 0,
    'hierarchical' => 0,
    'depth' => 0
  );
  if (!$forums = get_forums($get_forums_args))
    return;

  $oci_ro = $oci_hid = array();
  foreach($forums as $f){
    $bp_forum_info = (array)$f->bbGroups;
    // build a list of readonly, hidden and open forum ids
    if ($bp_forum_info){
      if ($bp_forum_info['status'] == 'public') 
        $oci_ro[] = (int)$f->forum_id;
      else
        $oci_hid[] = (int)$f->forum_id;
    }
    else
      $oci_open[] = (int)$f->forum_id;
  }
    //var_dump($oci_open, $oci_ro, $oci_hid);
}

/**
 * oci_init_user()
 * 
 * Build a list of the currently logged in user's hidden, private and readonly group forums
 * based on the meta data imported from bp.
 * 
 * @return 
 */
function oci_init_user(){
  global $bb_current_user,$oci_uhf, $oci_urof, $oci_hid, $oci_ro;
  
  $oci_uhf = $oci_urof = array();
  
  if ($bb_current_user){
    $bp_user_info = oci_get_userdata($bb_current_user->ID);
      
    $oci_uhf = array_intersect((array)$bp_user_info['users_forums'],$oci_hid);
    $oci_urof = array_intersect((array)$bp_user_info['users_forums'],$oci_ro);
  }
  //var_dump((array)$bp_user_info['users_forums'],$oci_uhf, $oci_urof);
}

/**
 * oci_make_forums_hidden()
 * 
 * set all group forums with 'private' or 'hidden' status to hidden
 * make allowances for current user
 * 
 * modifies the hidden-forums plugin global vars
 * 
*/
function oci_make_forums_hidden(){
  global $hidden_forums,$bb_current_user,$oci_hid,$oci_uhf;

  unset($hidden_forums['allow_roles']);
  unset($hidden_forums['allow_users']);
  $hidden_forums['allow_roles']['all_forums']=array('keymaster');    // these roles can always see ALL forums regardless
  $hidden_forums['allow_users']['all_forums']=array(1);    // these users can always see ALL forums regardless
  
  if ($oci_hid){
    $hidden_forums['hidden_forums']=$oci_hid;
  
    // make exceptions for all user's hidden forum_ids
    if ($oci_hid){
      foreach((array)$oci_uhf as $f){
        $hidden_forums['allow_users'][$f] = array($bb_current_user->ID);
      }
    }
  }
}

/**
 * oci_make_forums_readonly()
 * 
 * set all group forums with 'public' status to readonly
 * make allowances for current user
 * 
 * modifies the read-only-forums plugin global vars
 * 
*/
function oci_make_forums_readonly() {
  global $read_only_forums, $bb_current_user;
  global $oci_ro, $oci_urof;
  global $oci_options;
  
  $oci_deny_topic = $oci_deny_post = $oci_options['deny_access_message'];
  
  // messages configured at the top of this file to deny topic/reply start
  $read_only_forums['message_deny_start_topic']=$oci_deny_topic;
  $read_only_forums['message_deny_reply'] = $oci_deny_post;


  // configure public group forums as readonly, all of them initially
  $read_only_forums['deny_forums_start_topic'] = $oci_ro;
  $read_only_forums['deny_forums_reply'] = $oci_ro; 

  if ($bb_current_user){
    // override readonly for current user's forums
    $read_only_forums['allow_members_start_topic'] = array($bb_current_user->ID => (array)$oci_urof); 
    $read_only_forums['allow_members_reply'] = array($bb_current_user->ID => (array)$oci_urof); 
  }
//  var_dump($oci_ro,'<br />',$oci_urof);
}

/**
 * oci_group_forums()
 * 
 * triggered by 'bb_init' action, this has to run before _ck_'s readonly
 * and hidden forum plugins fire up. has to run at a priority < 200
 * 
 * modifies the read-only-forums and hidden-forums plugin global vars for bp groups
 * 
*/
function oci_group_forums(){
	global $oci_options;
	$oci_options = oci_group_forums_options();

	// do nothing if the xmlrpc server is firing up servicing a request
	if ( defined('XMLRPC_REQUEST') && XMLRPC_REQUEST ) 
		return;

	if ( defined('BB_IS_ADMIN') && BB_IS_ADMIN) {		
		oci_init_group_forums();
		return; 
	}

	// do something
	oci_init_group_forums();
	oci_init_user();
	oci_make_forums_hidden();	
	oci_make_forums_readonly();

}
add_action('bb_init','oci_group_forums',199);

?>
