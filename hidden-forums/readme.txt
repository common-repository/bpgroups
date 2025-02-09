=== Hidden Forums ===
Tags: hidden, hide, private, restrict, secret, forum,forums, _ck_
Contributors: _ck_
Requires at least: 0.9
Tested up to: trunk
Stable tag: trunk
Donate link: http://bbshowcase.org/donate/

Make selected forums completely hidden except to certain members or roles. Uses streamlined code and methods in  bbPress 0.9 to be faster than previous solutions without their quirks. Now 1.0 compatible.

== Description ==

Make selected forums completely hidden except to certain members or roles. Uses streamlined code and methods in  bbPress 0.9 to be faster than previous solutions without their quirks. Now 1.0 compatible.

== Installation ==

* This is a public beta release not yet intended for use on active forums. Please report bugs and other feedback.

* Until an admin menu is created, edit `hidden-forums.php` and change settings near the top as desired

* Add the `hidden-forums.php` file to bbPress' `my-plugins/` directory and activate

== Frequently Asked Questions ==

= How do I know the forum number / user number ? =

* administrators can do  your-forum.com/forums/?forumlist to get a list of forums by number (when the plugin is installed)

* user numbers can be found under forum.com/forums/bb-admin/users.php

= There are at least two other private/restricted/hidden forum plugins, why is this better? =

* Hidden Forums allow you to restrict by both role and specific user. It also PRE filters all results, instead of the old technique of removing results afterwards. This makes it faster as well as other optimizations when initializing.

= I am getting weird MySQL  errors at the top of pages? =

* Please report this immediately. Are you using older plugins? Please let me know what the are. Please make sure if you are using "My Views" that it is at least version 0.1.1

== License ==

* CC-GNU-GPL http://creativecommons.org/licenses/GPL/2.0/

== Donate ==

* http://bbshowcase.org/donate/

== History ==

= Version 0.0.1 (2008-04-21) =

* first public release for beta test

= Version 0.0.2 (2008-04-28) =

* bug fix for duplicate label when editing hidden topics

= Version 0.0.3 (2008-09-05) =

* workaround for get_topic caching issue with new bbPress 1.0 method

= Version 0.0.4 (2008-09-05) =

* fix to allow optional image as hidden forum indicator instead of just text

== To Do ==

* admin menu
