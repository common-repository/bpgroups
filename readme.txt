=== BuddyPress Groups for bbPress ===
Contributors: burtadsit
Tags: buddypress, bbpress, group, forums
Requires at least: 2.7
Tested up to: 2.7.1
Stable tag: trunk

Enable BuddyPress group forum features in bbPress including group forum moderation.

== Description ==

BuddyPress has three types of groups. Public, Private and Hidden. In all three cases users must be members of a group to participate in group forum conversations. 

These two plugins modify a standard bbPress forum installation to allow BuddyPress group members to carry on discussions in bbPress.

BuddyPress public groups will be read only in bbPress. Anyone wishing to participate in a public group discussion must be a member of that group in bp.

BuddyPress private and hidden groups will be hidden in bbPress. Only members of these groups will see the forums and be able to participate.

BuddyPress group admins and mods have moderator privileges in bbPress. The moderator role is restricted to their group forum.

== Installation ==

* You must have WordPress MU, BuddyPress and bbPress installed and operating correctly.
 
* You must have BuddyPress group forums installed and operating correctly.

* Only user integration is needed between bbPress and WordPress Mu.

1) Place oci_bp_group_forums.php in your /mu-plugins folder. You do not need to activate it.

2) Place the read-only-forums and hidden-forums plugins in your /my-plugins folder in bbPress and activate them. You will need to make a few minor template mods for read-only-forums to run correctly. See the read-only-forums readme.txt for details.

3) Place the oci_bb_group_forums.php file in your /my-plugins folder in bbPress and activate it.

4) Configure bpGroups by visiting the bbPress admin area and go to Settings > bpGroups

BuddyPress Site URL - this is the url to the root of your WordPress MU install. The mu file xmlrpc.php and your wp-config.php live there. This url will be used to find the xmlrpc server in mu. bpGroups guesses as to the correct value of this setting and it defaults to whatever bbPress thinks is your 'wp_siteurl'. Under the WordPress Integration settings it should be the same as 'WordPress address (URL)' in the Cookies area of that screen.

Authorized BuddyPress Username and Password - this must be the same as you haveset in the bbPress Forums options in BuddyPress. In mu Site Admin > bbPress Forums, bbPress username and bbPress password. This user and password must match that in those settings or things will not work.

Save these options by pushing the 'Save Changes' button.

5) Once you've told bpGroups how to communcate with bp then you can import all your existing groups and group users.


Choose 'Import Groups' and you are done. It will report how many groups had forums enabled in bp and how many forums in bbPress existed and were updated. It will also tell you how many users were imported and updated.

All wpmu users will be imported except the utility user that is used for access control between bp and bbpress.

That's it.

== How It Works ==

bpGroups relies on two plugins by _ck_ to create the read only and hidden features for all bp groups in bbPress.

XMLRPC is used to communicate with your bp site. The same mechanism BuddyPress uses to implement group forums. You don't need 'deep integration' to run this. The only integration between bbPress and WordPress MU is basic user integration.

You must initially import all the bp groups and group users. There is a back end admin form in bbPress that allows this. The import talks to bp and asks for all groups that have forums enabled. It pulls this and the group users across to bbPress and creates meta data for each group and each group user. The meta data is used to configure the forums for privacy and access restriciton. The user meta data contains information about each group user, such as the forums they belong to and if they are considered 'staff' in the group.

On every page load bpGroups loads meta data information for all bbPress forums. bpGroups dynamically configures the read-only-forums and hidden-forums plugins for all forums that have the required bpGroups meta data. It also gets meta data for the currently logged in user. If the user is a member of a group then they are given access to that group forum.

If a group member is an administrator or moderator of a group, they will have moderation rights in bbPress for that forum.

Once the initial import of group information is done then we rely on periodic communication with bp to keep the forum and user meta data updated. These updates are triggered by actions in bp that relate to changes in groups and users. bpGroups hooks a variety of bp actions and responds to changes by using XMLRPC to send new or updated information to bbPress.

The following events are hooked in bp and cause updated configuration info to be sent to bbPress:

Group events:

'groups_new_group_forum'
'groups_details_updated'
'groups_group_avatar_edited'

User events:

'groups_join_group'
'groups_leave_group'
'groups_promoted_member'
'groups_demoted_member'
'groups_banned_member'
'groups_unbanned_member'
'groups_accept_invite'
'groups_membership_accepted'
'bp_core_delete_avatar'
'bp_core_avatar_save'
'xprofile_updated_profile'

bbPress also talks to bp whenever a new group forum topic or post is created in bbPress. This updates the sitewide activity in bp.

bbPress events:

'bb_new_post'

That action is hooked in bpGroups for new topic and post creation.
 

== Components ==

hidden-forums.php
read-only-forums.php 

These are plugins that have been created by _ck_ and they implement the restrictions needed for group forum functionality in bbPress. These plugins run without modification and are distributed with bpGroups as a convenience. Thank you _ck_.

oci_bp_group_forums.php

This plugin acts as both a client and a server in wpmu for XMLRPC. It responds to requests from the bbPress side for group and user information. It lives on the bp/mu side. 

oci_bb_group_forums.php

This lives on the bbPress side and acts as both a XMLRPC client and server. It talks to bp and configures the reaonly and hidden forum plugins.

oci_bb_group_forums_tags.php

Template tags that you can include in your bbpress theme that take advantage of the data being transferred from bp. Explained below.

This is the current data being pulled from bp for use in bbPress:

Groups:

array(
		'id' => (int)$group->id,
		'name' => $group->name,
		'slug' => $group->slug,
		'description' => $group->description,
		'status' => $group->status,
		'enable_forum' => $group->enable_forum,
		'avatar_thumb' => $group->avatar_thumb,
		'admins' => oci_make_good_admins($group->id), // user ids for group admins and mods
		'forum_id' => (int)groups_get_groupmeta($group->id,'forum_id')
		);

Users:

array(
		'id' => (int)$user->id,
		'avatar_thumb' => $user->avatar_thumb,
		'fullname' => $user->fullname,
		'email' => $user->email,
		'user_url' => $user->user_url,
		'users_forums' => $users_group_info['forum_ids'],
		'user_is_staff' => $users_group_info['staff_ids']
		'xprofile_groupname_fieldname' => all xprofile data for the user
    );
    
These are some of the template functions that I have so far for use in bbpress
themes:


oci_xprofile_field_value() - returns xprofile field by user, xprofile group, field name
oci_xprofile_field() - returns the full array of field data: groupname, fieldname, field value, field type
oci_is_users_forum() - is the current $forum obj a user forum?
oci_user_avatar() - returns the user's bp avatar
oci_user_link() - link to the user's profile page in bp
oci_is_group_forum() - is the current $forum obj a bp group forum?
oci_group_avatar() - returns the bp group avatar
oci_group_link() - link to the bp group home page
oci_group_name() - returns the name of the bp group
oci_group_description() - that too
oci_get_group_staff() - returns the user ids for group admins and mods

If you'd like to see other info flowing back and forth between bbPress and bp let me know on the bp forums or my site. For a demo of bpGroups in action go to: 

http://ourcommoninterest.org/bbpress

The usernames ocitest1, ocitest2 and hellome are active and available.
 
The passwords are the same as the usernames. They all belong to various groups in bp and have different capabilities in different forums.


Have fun. Do good.
Burt
