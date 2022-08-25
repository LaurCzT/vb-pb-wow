<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 1.0.4
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2008 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/blog_functions_shared.php');

/**
* Fetches information about the selected blog.
*
* @param	integer	The blog entry we want info about
* @param	boolean	If we want to use a cached copy
*
* @return	array|false	Array of information about the blog or false if it doesn't exist
*/
function fetch_bloginfo($blogid, $usecache = true)
{
	global $vbulletin, $show;
	static $blogcache;

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
	{
		$lastpost_info = ",IF(blog_tachyentry.userid IS NULL, blog.lastcomment, blog_tachyentry.lastcomment) AS lastcomment, " .
			"IF(blog_tachyentry.userid IS NULL, blog.lastcommenter, blog_tachyentry.lastcommenter) AS lastcommenter, " .
			"IF(blog_tachyentry.userid IS NULL, blog.lastblogtextid, blog_tachyentry.lastblogtextid) AS lastblogtextid";

		$tachyjoin = "LEFT JOIN " . TABLE_PREFIX . "blog_tachyentry AS blog_tachyentry ON " .
			"(blog_tachyentry.blogid = blog.blogid AND blog_tachyentry.userid = " . $vbulletin->userinfo['userid'] . ')';
	}
	else
	{
		$lastpost_info = "";
		$tachyjoin = "";
	}

	$blogid = intval($blogid);
	if (!isset($blogcache["$blogid"]))
	{
		$deljoinsql = '';
		if (can_moderate_blog() OR $vbulletin->userinfo['userid'])
		{
			$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog.blogid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogid')";
		}

		$blogcache["$blogid"] = $vbulletin->db->query_first("
			SELECT blog.*, blog.options AS blogoptions, blog_text.pagetext, blog_text.allowsmilie, blog_text.ipaddress, blog_text.reportthreadid,
				blog_text.ipaddress AS blogipaddress,
				blog_editlog.userid AS edit_userid, blog_editlog.dateline AS edit_dateline, blog_editlog.reason AS edit_reason, blog_editlog.username AS edit_username,
				blog_textparsed.pagetexthtml, blog_textparsed.hasimages,
				user.*, userfield.*, usertextfield.*,
				blog.userid AS userid,
				blog_user.title AS blog_title, blog_user.description AS blog_description, blog_user.allowsmilie AS blog_allowsmilie, blog_user.akismet_key AS akismet_key,
				blog_user.options_everyone, blog_user.options_buddy, blog_user.options_ignore, blog_user.entries, blog_user.isblogmoderator, blog_user.comments_moderation AS blog_comments_moderation,
				blog_user.draft AS blog_draft, blog_user.pending AS blog_pending, blog_user.uncatentries, blog_user.moderation AS blog_moderation, blog_user.deleted AS blog_deleted,
				customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight,
				IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
				" . ($vbulletin->userinfo['userid'] ? ",ignored.relationid AS ignoreid, buddy.relationid AS buddyid, IF(blog_subscribeuser.blogsubscribeuserid, 1, 0) AS blogsubscribed, IF(blog_subscribeentry.blogsubscribeentryid, 1, 0) AS entrysubscribed, blog_subscribeentry.type AS emailupdate" : "") . "
				" . ($vbulletin->options['avatarenabled'] ? ", avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width AS avwidth, customavatar.height AS avheight" : "") . "
				" . (!($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']) ? $vbulletin->profilefield['hidden'] : "") . "
				" . (($vbulletin->options['threadvoted'] AND $vbulletin->userinfo['userid']) ? ', blog_rate.vote' : '') . "
				" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? ", blog_read.readtime AS blogread, blog_userread.readtime  AS bloguserread" : "") . "
				" . ($deljoinsql ? ",blog_deletionlog.userid AS del_userid, blog_deletionlog.username AS del_username, blog_deletionlog.reason AS del_reason" : "") . "
				$lastpost_info
			FROM " . TABLE_PREFIX . "blog AS blog
			INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = blog.firstblogtextid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "blog_editlog AS blog_editlog ON (blog_editlog.blogtextid = blog.firstblogtextid)
			LEFT JOIN " . TABLE_PREFIX . "blog_textparsed AS blog_textparsed ON (blog_textparsed.blogtextid = blog.firstblogtextid AND blog_textparsed.styleid = " . intval(STYLEID) . " AND blog_textparsed.languageid = " . intval(LANGUAGEID) . ")
			LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid)
			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? "
			LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON (blog_read.blogid = blog.blogid AND blog_read.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON (blog_userread.bloguserid = blog.userid AND blog_userread.userid = " . $vbulletin->userinfo['userid'] . ")
			" : "") . "
			" . ($vbulletin->userinfo['userid'] ? "
			LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')
			LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')
			" : "") . "
			" . ($vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "blog_subscribeentry AS blog_subscribeentry ON (blog.blogid = blog_subscribeentry.blogid AND blog_subscribeentry.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
			" . ($vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "blog_subscribeuser AS blog_subscribeuser ON (blog.userid = blog_subscribeuser.bloguserid AND blog_subscribeuser.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) " : "") . "
			" . (($vbulletin->options['threadvoted'] AND $vbulletin->userinfo['userid']) ? "LEFT JOIN " . TABLE_PREFIX . "blog_rate AS blog_rate ON (blog_rate.blogid = blog.blogid AND blog_rate.userid = " . $vbulletin->userinfo['userid'] . ")" : '') . "
			$deljoinsql
			$tachyjoin
			WHERE blog.blogid = " . intval($blogid)
		);

		if (!$blogcache["$blogid"])
		{
			return false;
		}

		if (!$blogcache["$blogid"]['blog_title'])
		{
			$blogcache["$blogid"]['blog_title'] = $blogcache["$blogid"]['username'];
		}

		$blogcache["$blogid"] = array_merge($blogcache["$blogid"], convert_bits_to_array($blogcache["$blogid"]['blogoptions'], $vbulletin->bf_misc_vbblogoptions));

		foreach ($vbulletin->bf_misc_vbblogsocnetoptions AS $optionname => $optionval)
		{
			$blogcache["$blogid"]["everyone_$optionname"] = ($blogcache["$blogid"]['options_everyone'] & $optionval ? 1 : 0);
			$blogcache["$blogid"]["buddy_$optionname"] = ($blogcache["$blogid"]['options_buddy'] & $optionval ? 1 : 0);
			$blogcache["$blogid"]["ignore_$optionname"] = ($blogcache["$blogid"]['options_ignore'] & $optionval ? 1 : 0);

			$blogcache["$blogid"]["$optionname"] = ((($blogcache["$blogid"]['buddyid'] AND !$blogcache["$blogid"]["buddy_$optionname"])
				OR ($blogcache["$blogid"]['ignoreid'] AND !$blogcache["$blogid"]["ignore_$optionname"])
				OR !$blogcache["$blogid"]["everyone_$optionname"]) AND
					(!$blogcache["$blogid"]["ignore_$optionname"] OR !$blogcache["$blogid"]['ignoreid']) AND
					(!$blogcache["$blogid"]["buddy_$optionname"] OR !$blogcache["$blogid"]['buddyid']) AND
					$blogcache["$blogid"]['userid'] != $vbulletin->userinfo['userid'] AND
					!can_moderate_blog()) ? false : true;
		}

		if (can_moderate() AND $vbulletin->userinfo['userid'] != $blogcache["$blogid"]['userid'])
		{
			$everyoneelsecanview = $blogcache["$blogid"]['options_everyone'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $blogcache["$blogid"]['options_buddy'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			if (!$everyoneelsecanview AND (!$blogcache["$blogid"]['buddyid'] OR !$buddiescanview))
			{
				$blogcache["$blogid"]['privateblog'] = true;
			}
		}

		$blogcache["$blogid"] = array_merge($blogcache["$blogid"], convert_bits_to_array($blogcache["$blogid"]['options'], $vbulletin->bf_misc_useroptions));
		$blogcache["$blogid"] = array_merge($blogcache["$blogid"], convert_bits_to_array($blogcache["$blogid"]['adminoptions'], $vbulletin->bf_misc_adminoptions));

		fetch_musername($blogcache["$blogid"]);
	}

	($hook = vBulletinHook::fetch_hook('blog_fetch_bloginfo')) ? eval($hook) : false;

	return $blogcache["$blogid"];
}

/**
* Fetches information about the selected blog with permission checks, almost identical to fetch_bloginfo
*
* @param	integer	The blog post we want info about
* @param	mixed		Should a permission check be performed as well
*
* @return	array	Array of information about the blog or prints an error if it doesn't exist / permission problems
*/
function verify_blog($blogid, $alert = true, $perm_check = true)
{
	global $vbulletin, $vbphrase;

	$bloginfo = fetch_bloginfo($blogid);
	if (!$bloginfo)
	{
		if ($alert)
		{
			standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
		}
		else
		{
			return 0;
		}
	}

	if ($perm_check)
	{
		if ((!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $bloginfo['userid'] == $vbulletin->userinfo['userid']) OR (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']) AND $bloginfo['userid'] != $vbulletin->userinfo['userid']))
		{
			print_no_permission();
		}

		if ($bloginfo['state'] == 'deleted' AND !can_moderate_blog())
		{
			if ($bloginfo['userid'] != $vbulletin->userinfo['userid'] OR $perm_check === 'modifychild')
			{
				// the blog entry is deleted
				standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
			}
		}
		else if (($bloginfo['pending'] OR $bloginfo['state'] == 'draft') AND $bloginfo['userid'] != $vbulletin->userinfo['userid'])
		{
			// can't view a pending/draft if you aren't the author
			standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
		}
		else if ($bloginfo['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			// the blog entry is awaiting moderation
			if ($bloginfo['userid'] != $vbulletin->userinfo['userid'] OR $perm_check == 'modifychild')
			{
				standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
			}
		}
		else if (in_coventry($bloginfo['userid']) AND !can_moderate_blog())
		{
			standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
		}
		else if (!$bloginfo['canviewmyblog'])	// Check Socnet permissions
		{
			print_no_permission();
		}
	}

	return $bloginfo;
}

/**
* Fetches information about the selected blog text entry
*
* @param	integer	Blogtextid of requested
*
* @return	array|false	Array of information about the blog text or false if it doesn't exist
*/
function fetch_blog_textinfo($blogtextid)
{
	global $vbulletin;
	static $blogtextcache;

	$blogtextid = intval($blogtextid);
	if (!isset($blogtextcache["$blogtextid"]))
	{
		$blogtextcache["$blogtextid"] = $vbulletin->db->query_first("
			SELECT blog_text.*,
				blog_editlog.userid AS edit_userid, blog_editlog.dateline AS edit_dateline, blog_editlog.reason AS edit_reason, blog_editlog.username AS edit_username
			FROM " . TABLE_PREFIX . "blog_text AS blog_text
			LEFT JOIN " . TABLE_PREFIX . "blog_editlog AS blog_editlog ON (blog_editlog.blogtextid = blog_text.blogtextid)
			WHERE blog_text.blogtextid = $blogtextid
		");
	}

	if (!$blogtextcache["$blogtextid"])
	{
		return false;
	}
	else
	{
		return $blogtextcache["$blogtextid"];
	}
}

/**
* Converts the newpost_attachmentbit template for use with javascript/construct_phrase
*
* @return	string
*/
function prepare_blog_newpost_attachmentbit()
{
	// do not globalize $session or $attach!

	$attach = array(
		'imgpath'      => '%1$s',
		'attachmentid' => '%3$s',
		'dateline'     => '%4$s',
		'filename'     => '%5$s',
		'filesize'     => '%6$s'
	);
	$session['sessionurl'] = '%2$s';

	eval('$template = "' . fetch_template('blog_entry_editor_attachment') . '";');

	return addslashes_js($template, "'");
}

/**
* Marks a blog as read using the appropriate method.
*
* @param	array	Array of data for the blog being marked
* @param	integer	User ID this thread is being marked read for
* @param	integer	Unix timestamp that the thread is being marked read
*
* @return	void
*/
function mark_blog_read(&$bloginfo, $userid, $time)
{
	global $vbulletin, $db;

	$userid = intval($userid);
	$time = intval($time);

	if ($vbulletin->options['threadmarking'] AND $userid)
	{
		// can't be shutdown as we do a read query below on this table
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "blog_read
				(userid, blogid, readtime)
			VALUES
				($userid, $bloginfo[blogid], $time)
		");
	}
	else
	{
		set_bbarray_cookie('blog_lastview', $bloginfo['blogid'], $time);
	}

	// now if applicable search to see if this was the last thread requiring marking in this forum
	if ($vbulletin->options['threadmarking'] == 2 AND $userid)
	{
		$cutoff = TIMENOW - ($vbulletin->options['markinglimit'] * 86400);
		$unread = $db->query_first("
			SELECT COUNT(*) AS count
 			FROM " . TABLE_PREFIX . "blog AS blog
 			LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON (blog_read.blogid = blog.blogid AND blog_read.userid = $userid)
			LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON (blog_userread.bloguserid = blog.userid AND blog_userread.userid = $userid)
			WHERE blog.userid = $bloginfo[userid]
	      		AND blog.state = 'visible'
				AND blog.lastcomment > IF(blog_read.readtime IS NULL, $cutoff, blog_read.readtime)
				AND blog.lastcomment > IF(blog_userread.readtime IS NULL, $cutoff, blog_userread.readtime)
				AND blog.lastcomment > $cutoff
		");
		if ($unread['count'] == 0)
		{
			mark_user_blog_read($bloginfo['userid'], $userid, TIMENOW);
		}
	}
}

/**
* Marks a forum as read using the appropriate method.
*
* @param	integer	User ID of the blog owner
* @param	integer	User ID that is being marked read for
* @param	integer	Unix timestamp that the thread is being marked read
*
* @return	array	Returns an array of forums that were marked as read
*/
function mark_user_blog_read($bloguserid, $userid, $time)
{
	global $vbulletin, $db;

	$bloguserid = intval($bloguserid);
	$userid = intval($userid);
	$time = intval($time);

	if (empty($bloguserid))
	{
		// sanity check -- wouldn't work anyway
		return false;
	}

	if ($vbulletin->options['threadmarking'] AND $userid)
	{

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "blog_userread
				(userid, bloguserid, readtime)
			VALUES
				($userid, $bloguserid, $time)
		");
	}
	else
	{
		set_bbarray_cookie('blog_userread', $bloguserid, $time);
	}

	return true;
}

/**
* Sends the notifications for new threads / posts
*
* @param	string	Type of notification, either post or comment
* @param	integer	ID of the blog entry
* @param	integer	Userid of the blog entry
* @param	integer	Userid who made the post / comment
*
* @return	void
*/
function exec_blog_notification($type = 'entry', $blogid, $bloguserid, $userid)
{
	global $vbulletin;

	if ($type == 'entry')
	{
		$useremails = $vbulletin->db->query_read_slave("
			SELECT user.*, blog_subscribeuser.emailupdate, blog_subscribeuser.bloguserid
			FROM " . TABLE_PREFIX . "blog_subscribeuser AS blog_subscribeuser
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (blog_subscribeuser.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
			WHERE blog_subscribeuser.bloguserid = $bloguserid AND
				blog_subscribeuser.emailupdate = 1 AND
				user.usergroupid <> 3 AND
				user.userid <> " . intval($userid) . " AND
				user.lastactivity >= " . intval($lastposttime['dateline']) . " AND
				(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
		");
	}
	else
	{
		$useremails = $vbulletin->db->query_read_slave("
			SELECT user.*, blog_subscribeentry.emailupdate, blog_subscribeentry.blogid
			FROM " . TABLE_PREFIX . "blog_subscribeentry AS blog_subscribeentry
			INNER JOIN " . TABLE_PREFIX . "user AS user ON (blog_subscribeentry.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
			LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
			WHERE blog_subscribeentry.blogid = $blogid AND
				blog_subscribeentry.emailupdate = 1 AND
				user.usergroupid <> 3 AND
				user.userid <> " . intval($userid) . " AND
				user.lastactivity >= " . intval($lastposttime['dateline']) . " AND
				(usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
		");
		// hook or assume its a comment
	}

	require_once(DIR . '/includes/class_bbcode_alt.php');
	$plaintext_parser =& new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());
	$pagetext_cache = array(); // used to cache the results per languageid for speed

	vbmail_start();

	$evalemail = array();
	while ($touser = $vbulletin->db->fetch_array($useremails))
	{
		if (!($vbulletin->usergroupcache["$touser[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
		{
			continue;
		}

		$touser['username'] = unhtmlspecialchars($touser['username']);
		$touser['languageid'] = iif($touser['languageid'] == 0, $vbulletin->options['languageid'], $touser['languageid']);
		$touser['auth'] = md5($touser['userid'] . $blogid . $touser['salt'] . COOKIE_SALT);
		// Do we need to implement unsubscribing through links? (Scott)
		// I think so - if vB does it, the blog should do it. Let's start out with all the little touches now, not get around to them years later

		if (empty($evalemail))
		{
			$email_texts = $vbulletin->db->query_read_slave("
				SELECT text, languageid, fieldname
				FROM " . TABLE_PREFIX . "phrase
				WHERE fieldname IN ('emailsubject', 'emailbody') AND varname = 'blog_notify'
			");

			while ($email_text = $vbulletin->db->fetch_array($email_texts))
			{
				$emails["$email_text[languageid]"]["$email_text[fieldname]"] = $email_text['text'];
			}

			require_once(DIR . '/includes/functions_misc.php');

			foreach ($emails AS $languageid => $email_text)
			{
				// lets cycle through our array of notify phrases
				$text_message = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailbody']), $emails['-1']['emailbody'], $email_text['emailbody'])));
				$text_message = replace_template_variables($text_message);
				$text_subject = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailsubject']), $emails['-1']['emailsubject'], $email_text['emailsubject'])));
				$text_subject = replace_template_variables($text_subject);

				$evalemail["$languageid"] = '
					$message = "' . $text_message . '";
					$subject = "' . $text_subject . '";
				';
			}
		}

		// parse the page text into plain text, taking selected language into account
		if (!isset($pagetext_cache["$touser[languageid]"]))
		{
			$plaintext_parser->set_parsing_language($touser['languageid']);
			//todo: this should not be using nonforum but probably 'blog_entry' with proper permissions being set
			$pagetext_cache["$touser[languageid]"] = $plaintext_parser->parse($pagetext_orig, 'nonforum');
		}
		$pagetext = $pagetext_cache["$touser[languageid]"];

		eval(iif(empty($evalemail["$touser[languageid]"]), $evalemail["-1"], $evalemail["$touser[languageid]"]));

		vbmail($touser['email'], $subject, $message);
	}

	unset($plaintext_parser, $pagetext_cache);

	$vbulletin->userinfo['username'] = $temp;

	vbmail_end();

}

/**
* Order Categories
*
* @param	integer		Userid
* @param	bool		Force cache to be rebuilt, ignoring copy that may already exist
*
* @return	void
*/
function cache_ordered_categories($userid = 0, $force = false)
{
	global $vbulletin;

	if (!intval($userid))
	{
		$userid = $vbulletin->userinfo['userid'];
	}

	if (isset($vbulletin->vbblog['categorycache']["$userid"]) AND !$force)
	{
		return;
	}

	$vbulletin->vbblog['categorycache']["$userid"] = array();
	$vbulletin->vbblog['icategorycache']["$userid"] = array();
	$categorydata = array();

	$cats = $vbulletin->db->query_read_slave("
		SELECT blog_category.*
		FROM " . TABLE_PREFIX . "blog_category AS blog_category
		WHERE userid = $userid
		ORDER BY displayorder
	");
	while ($cat = $vbulletin->db->fetch_array($cats))
	{
		$vbulletin->vbblog['icategorycache']["$userid"]["$cat[parentid]"]["$cat[blogcategoryid]"] = $cat['blogcategoryid'];
		$categorydata["$cat[blogcategoryid]"] = $cat;
	}

	$vbulletin->vbblog['categoryorder']["$userid"] = array();
	fetch_category_order($userid);

	foreach ($vbulletin->vbblog['categoryorder']["$userid"] AS $blogcategoryid => $depth)
	{
		$vbulletin->vbblog['categorycache']["$userid"]["$blogcategoryid"] =& $categorydata["$blogcategoryid"];
		$vbulletin->vbblog['categorycache']["$userid"]["$blogcategoryid"]['depth'] = $depth;
	}
}

/**
* Recursive function to build category order
*
* @param	integer	Userid
* @param	integer	Initial parent forum ID to use
* @param	integer	Initial depth of categories
*
* @return	void
*/
function fetch_category_order($userid, $parentid = 0, $depth = 0)
{
	global $vbulletin;

	if (is_array($vbulletin->vbblog['icategorycache']["$userid"]["$parentid"]))
	{
		foreach ($vbulletin->vbblog['icategorycache']["$userid"]["$parentid"] AS $blogcategoryid)
		{
			$vbulletin->vbblog['categoryorder']["$userid"]["$blogcategoryid"] = $depth;
			fetch_category_order($userid, $blogcategoryid, $depth + 1);
		}
	}
}

/**
* Function to output checkbox bits
*
* @param	array	categories
8 @param	integer	User
*
* @return	void
*/
function construct_category_checkbox(&$categories, $userid = 0)
{
	global $vbulletin;

	if (!intval($userid))
	{
		$userid = $vbulletin->userinfo['userid'];
	}

	if (!isset($vbulletin->vbblog['categorycache']["$userid"]))
	{
		cache_ordered_categories($userid);
	}

	if (empty($vbulletin->vbblog['categorycache']["$userid"]))
	{
		return;
	}

	$prevdepth = $beenhere = 0;
	foreach ($vbulletin->vbblog['categorycache']["$userid"] AS $blogcategoryid => $category)
	{
		$show['ul'] = false;

		if (is_array($categories))
		{
			$checked = (in_array($blogcategoryid, $categories)) ? 'checked="checked"' : '';
		}

		if ($category['depth'] == $prevdepth AND $beenhere)
		{
			$jumpcategorybits .= '</li>';
		}
		else if ($category['depth'] > $prevdepth)
		{
			// Need an UL
			$show['ul'] = true;
		}
		else if ($category['depth'] < $prevdepth)
		{
			for ($x = ($prevdepth - $category['depth']); $x > 0; $x--)
			{
				$jumpcategorybits .= '</li></ul>';
			}
			$jumpcategorybits .= '</li>';
		}

		eval('$jumpcategorybits .= "' . fetch_template('blog_entry_editor_category') . '";');

		$prevdepth = $category['depth'];
		$beenhere = true;
	}

	if ($jumpcategorybits)
	{
		for ($x = $prevdepth; $x > 0; $x--)
		{
			$jumpcategorybits .= '</li></ul>';
		}
		$jumpcategorybits .= '</li>';
	}

	return $jumpcategorybits;
}

/**
* Function to output select bits
*
* @param integer	The category parent id to select by default
* @param integer	Userid
*
* @return	void
*/
function construct_category_select($parentid = 0, $userid = 0)
{
	global $vbulletin;

	if (!intval($userid))
	{
		$userid = $vbulletin->userinfo['userid'];
	}

	if (!isset($vbulletin->vbblog['categorycache']["$userid"]))
	{
		cache_ordered_categories($userid);
	}

	if (empty($vbulletin->vbblog['categorycache']["$userid"]))
	{
		return;
	}

	foreach ($vbulletin->vbblog['categorycache']["$userid"] AS $blogcategoryid => $category)
	{
		$optionvalue = $blogcategoryid;
		$optiontitle = str_pad("$category[title]", strlen(FORUM_PREPEND) * ($category['depth'] + 1) + strlen("$category[title]"), FORUM_PREPEND, STR_PAD_LEFT);
		$optionclass = 'fjdpth' . ($category['depth'] > 4) ? 4 : $category['depth'];
		$optionselected = ($blogcategoryid == $parentid) ? 'selected="selected"' : '';

		eval('$jumpcategorybits .= "' . fetch_template('option') . '";');
	}

	return $jumpcategorybits;
}

/**
* Function to output select bits
*
* @param integer	Userid
*
* @return	void
*/
function build_category_genealogy($userid)
{
	global $vbulletin;

	if (!intval($userid))
	{
		$userid = $vbulletin->userinfo['userid'];
	}

	cache_ordered_categories($userid, true);

	// build parent/child lists
	foreach ($vbulletin->vbblog['categorycache']["$userid"] AS $blogcategoryid => $category)
	{
		// parent list
		$i = 0;
		$curid = $blogcategoryid;

		$vbulletin->vbblog['categorycache']["$userid"]["$blogcategoryid"]['parentlist'] = '';

		while ($curid != 0 AND $i++ < 1000)
		{
			if ($curid)
			{
				$vbulletin->vbblog['categorycache']["$userid"]["$blogcategoryid"]['parentlist'] .= $curid . ',';
				$curid = $vbulletin->vbblog['categorycache']["$userid"]["$curid"]['parentid'];
			}
			else
			{
				global $vbphrase;
				if (!isset($vbphrase['invalid_category_parenting']))
				{
					$vbphrase['invalid_category_parenting'] = 'Invalid category parenting setup. Contact vBulletin support.';
				}
				trigger_error($vbphrase['invalid_category_parenting'], E_USER_ERROR);
			}
		}

		$vbulletin->vbblog['categorycache']["$userid"]["$blogcategoryid"]['parentlist'] .= '0';

		// child list
		$vbulletin->vbblog['categorycache']["$userid"]["$blogcategoryid"]['childlist'] = $blogcategoryid;
		fetch_category_child_list($blogcategoryid, $blogcategoryid, $userid);
		$vbulletin->vbblog['categorycache']["$userid"]["$blogcategoryid"]['childlist'] .= ',0';
	}

	$parentsql = '';
	$childsql = '';
	foreach ($vbulletin->vbblog['categorycache']["$userid"] AS $blogcategoryid => $category)
	{
		$parentsql .= "	WHEN $blogcategoryid THEN '$category[parentlist]'
		";
		$childsql .= "	WHEN $blogcategoryid THEN '$category[childlist]'
		";
	}

	if (!empty($vbulletin->vbblog['categorycache']["$userid"]))
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_category SET
				parentlist = CASE blogcategoryid
					$parentsql
					ELSE parentlist
				END,
				childlist = CASE blogcategoryid
					$childsql
					ELSE childlist
				END
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
		");
	}
}

/**
* Recursive function to populate categorycache with correct child list fields
*
* @param	integer		Category ID to be updated
* @param	integer		Parent forum ID
* @param	interger	Userid
*
* @return	void
*/
function fetch_category_child_list($maincategoryid, $parentid, $userid)
{
	global $vbulletin;

	if (is_array($vbulletin->vbblog['icategorycache']["$userid"]["$parentid"]))
	{
		foreach ($vbulletin->vbblog['icategorycache']["$userid"]["$parentid"] AS $blogcategoryid => $categoryparentid)
		{
			$vbulletin->vbblog['categorycache']["$userid"]["$maincategoryid"]['childlist'] .= ',' . $blogcategoryid;
			fetch_category_child_list($maincategoryid, $blogcategoryid, $userid);
		}
	}
}

/**
* Parse message content for preview
*
* @param	array		Message and disablesmilies options
* @param	string	Parse Type (user, post or comment)
*/
function process_blog_preview($blog, $type, $attachments = NULL)
{
	global $vbulletin, $vbphrase, $stylevar, $show;

	require_once(DIR . '/includes/class_bbcode_blog.php');
	$bbcode_parser =& new vB_BbCodeParser_Blog($vbulletin, fetch_tag_list());
	$bbcode_parser->set_parse_userinfo($vbulletin->userinfo, $vbulletin->userinfo['permissions']);
	$bbcode_parser->attachments = $attachments;

	$postpreview = '';
	if ($previewmessage = $bbcode_parser->parse($blog['message'], 'blog_' . $type, $blog['disablesmilies'] ? 0 : 1))
	{
		switch ($type)
		{
			case 'user':
				eval('$postpreview = "' . fetch_template('blog_cp_modify_profile_preview')."\";");
				break;
			case 'entry':
			case 'comment':
			case 'usercomment':
				eval('$postpreview = "' . fetch_template('blog_entry_editor_preview')."\";");
				break;
		}
	}

	return $postpreview;
}

/**
* Returns list of URLs from text
*
* @param	string	Message text
*
* @return	array
*/
function fetch_urls($messagetext)
{
	preg_match_all('#\[url=("|\'|)?(.*)\\1\](?:.*)\[/url\]|\[url\](.*)\[/url\]#siU', $messagetext, $matches);

	if (!empty($matches))
	{
		$matches = array_merge($matches[2], $matches[3]);
	}

	$urls = array();
	foreach($matches AS $url)
	{
		if (!empty($url))
		{
			if ($temp = @parse_url($url))
			{
				if ($temp['port'] == 80)
				{
					unset($temp['port']);
				}
				if (!$temp['scheme'])
				{
					$temp['scheme'] = 'http';
				}
				$urls[] = "$temp[scheme]://$temp[host]" . ($temp['port']	? ":$temp[port]" : '') . "$temp[path]" . ($temp['query'] ? "?$temp[query]" : '');
			}
		}
	}

	return array_unique($urls);
}

/**
* Function for writing to the trackback log
*
* @param string		Pingback, Trackback or None (none is failure before system is established)
* @param string		'in' or 'out' (incoming or outgoing)
* @param integer	Error Code
* @param string		Message from remote server
* @param array		bloginfo
* @param string		URL
*
* @return	mixed	error string on failure, true on success or apparent success
*/
function write_trackback_log($system = 'pingback', $type = 'in', $status = 0, $message = '', $bloginfo = array(), $url = '')
{
	global $vbulletin;

	$vbulletin->db->query_write("
		INSERT INTO " . TABLE_PREFIX . "blog_trackbacklog
		(
			system,
			type,
			status,
			message,
			blogid,
			userid,
			dateline,
			url,
			ipaddress
			)
		VALUES
		(
			'" . (!in_array($system, array('trackback', 'pingback')) ? 'none' : $system) . "',
			'" . ($type == 'in' ? 'in' : 'out') . "',
			" . intval($status) . ",
			'" . $vbulletin->db->escape_string(serialize($message)) . "',
			" . intval($bloginfo['blogid']) . ",
			" . intval($bloginfo['userid']) . ",
			" . TIMENOW . ",
			'" . $vbulletin->db->escape_string(htmlspecialchars_uni($url)) . "',
			" . intval(sprintf('%u', ip2long(IPADDRESS))) . "
		)
	");
}

/**
* Send a pingback / trackback request
*
* @param	array	Bloginfo
* @param	string	Destination URL
* @param	string	Title of the blog
*
* @return	mixed	error string on failure, true on success or apparent success
*/
function send_ping_notification(&$bloginfo, $desturl, $blogtitle)
{
	global $vbulletin;

	if (!intval($bloginfo['blogid']))
	{
		return false;
	}

	$ourblogurl = $vbulletin->options['bburl'] . '/blog.php?blogid=' . $bloginfo['blogid'];
	$pingback_dest = '';
	$trackback_dest = $desturl;

	require_once(DIR . '/includes/functions_file.php');
	if ($headresult = fetch_head_request($desturl))
	{
		if (!empty($headresult['x-pingback']))
		{
			$pingback_dest = $headresult['x-pingback'];
		}
		else if ($headresult['http-response']['statuscode'] == 200 AND preg_match('#text\/html#si', $headresult['content-type']))
		{
			// Limit to 5KB
			// Consider adding the ability to Kill the transfer on </head>\s+*<body to class_vurl.php
			if ($bodyresult = fetch_body_request($desturl, 5120))
			{
				// search head for <link rel="pingback" href="pingback server">
				if (preg_match('<link rel="pingback" href="([^"]+)" ?/?>', $bodyresult, $matches))
				{
					$pingback_dest = $matches[0];
				}
				else	if (preg_match('#<rdf:Description((?!<\/rdf:RDF>).)*dc:identifier="' . preg_quote($desturl, '#') . '".*<\/rdf:RDF>#siU', $bodyresult))
				{
					if (preg_match('#<rdf:Description(?:(?!<\/rdf:RDF>).)*trackback:ping="([^"]+)".*<\/rdf:RDF>#siU', $bodyresult, $matches))
					{
						$trackback_dest = trim($matches[1]);
					}
				}
			}
		}

		if (!empty($pingback_dest))
		{
			// Client
			require_once(DIR . '/includes/class_xmlrpc.php');
			$xmlrpc = new vB_XMLRPC_Client($vbulletin);
			$xmlrpc->build_xml_call('pingback.ping', $ourblogurl, $desturl);
			if ($pingresult = $xmlrpc->send_xml_call($pingback_dest))
			{
				require_once(DIR . '/includes/class_xmlrpc.php');
				$xmlrpc_server = new vB_XMLRPC_Server($vbulletin);
				$xmlrpc_server->parse_xml($pingresult['body']);
				$xmlrpc_server->parse_xmlrpc();
			}

			// NOT FINSIHED
			write_trackback_log('pingback', 'out', 0, $pingresult, $bloginfo, $desturl);
			// Not always a success but we can't know for sure
			return true;
		}
		else
		{
			// Client
			require_once(DIR . '/includes/class_trackback.php');
			$tb = new vB_Trackback_Client($vbulletin);
			$excerpt = fetch_censored_text(fetch_trimmed_title(strip_bbcode(strip_quotes($bloginfo['pagetext']), false, true), 255));
			if ($result = $tb->send_ping($trackback_dest, $ourblogurl, $bloginfo['title'], $excerpt, $blogtitle))
			{
				require_once(DIR . '/includes/class_xml.php');
				$xml_object = new vB_XML_Parser($result['body']);
				$xml_object->include_first_tag = true;
				if ($xml_object->parse_xml() AND $xml_object->parseddata['response']['error'] === '0')
				{
					write_trackback_log('trackback', 'out', 0, $result, $bloginfo, $desturl);
					return true;
				}
			}

			write_trackback_log('trackback', 'out', 3, $result, $bloginfo, $desturl);
			// Not always a success but we can't know for sure
			return true;
		}
	}

	write_trackback_log('none', 'out', 1, '', $bloginfo, $desturl);

	return false;
}

/**
* Build the metadata for a blog entry
*
* @param	integer	ID of the blog entry
*
* @return	void
*/
function build_blog_entry_counters($blogid)
{
	global $vbulletin;

	if (!($blogid = intval($blogid)))
	{
		return;
	}

	$comments = $vbulletin->db->query_first("
		SELECT
			SUM(IF(blog_text.state = 'visible', 1, 0)) AS visible,
			SUM(IF(blog_text.state = 'moderation', 1, 0)) AS moderation,
			SUM(IF(blog_text.state = 'deleted', 1, 0)) AS deleted
		FROM " . TABLE_PREFIX . "blog_text AS blog_text
		INNER JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
		WHERE blog_text.blogid = $blogid
			AND blog_text.blogtextid <> blog.firstblogtextid
	");

	$trackback = $vbulletin->db->query_first("
		SELECT
			SUM(IF(state = 'visible', 1, 0)) AS visible,
			SUM(IF(state = 'moderation', 1, 0)) AS moderation
		FROM " . TABLE_PREFIX . "blog_trackback
		WHERE blogid = $blogid
	");

	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_tachyentry
		WHERE blogid = $blogid
	");

	// read the last posts out of the blog, looking for tachy'd users.
	// if we find one, give them that as the last post but continue looking
	// for the displayed last post.
	$offset = 0;
	$users_processed = array();
	do
	{
		$lastposts = $vbulletin->db->query_first("
			SELECT user.username, blog_text.userid, blog_text.username AS bloguser, blog_text.dateline, blog_text.blogtextid
			FROM " . TABLE_PREFIX . "blog_text AS blog_text
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = blog_text.userid
			WHERE blog_text.blogid = $blogid AND
				blog_text.state = 'visible'
			ORDER BY dateline DESC
			LIMIT $offset, 1
		");

		if (in_coventry($lastposts['userid'], true))
		{
			$offset++;

			if (!isset($users_processed["$lastposts[userid]"]))
			{
				$vbulletin->db->query_write("
					REPLACE INTO " . TABLE_PREFIX . "blog_tachyentry
						(userid, lastblogtextid, blogid, lastcomment, lastcommenter)
					VALUES
						($lastposts[userid],
						$lastposts[blogtextid],
						$blogid,
						" . intval($lastposts['dateline']) . ",
						'" . $vbulletin->db->escape_string(empty($lastposts['username']) ? $lastposts['bloguser'] : $lastposts['username']) . "')
				");
				$users_processed["$lastposts[userid]"] = true;
			}
		}
		else
		{
			break;
		}
	}
	while ($lastposts);

	$firstpost = $vbulletin->db->query_first("
		SELECT blog_text.blogtextid, blog_text.userid, user.username, blog_text.username AS bloguser, blog_text.dateline
		FROM " . TABLE_PREFIX . "blog_text AS blog_text
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON user.userid = blog_text.userid
		WHERE blog_text.blogid = $blogid AND
			blog_text.state = 'visible'
		ORDER BY dateline, blogid
		LIMIT 1
	");

	if ($lastposts)
	{
		$lastcommenter = (empty($lastposts['username']) ? $lastposts['bloguser'] : $lastposts['username']);
		$lastcomment = intval($lastposts['dateline']);
		$lastblogtextid = intval($lastposts['blogtextid']);
	}
	else
	{
		// this will occur on a blog posted by a tachy user.
		// since only they will see the blog, the lastpost info can say their name
		$lastcommenter = (empty($firstpost['username']) ? $firstpost['bloguser'] : $firstpost['username']);
		$lastcomment = intval($firstpost['dateline']);
		$lastblogtextid = intval($firstpost['blogtextid']);
	}

	$ratings = $vbulletin->db->query_first("
		SELECT
			COUNT(*) AS ratingnum,
			SUM(vote) AS ratingtotal
		FROM " . TABLE_PREFIX . "blog_rate
		WHERE blogid = $blogid
	");

	$bloginfo = array('blogid' => $blogid);
	$blogman =& datamanager_init('Blog', $vbulletin, ERRTYPE_SILENT);
	$blogman->set_existing($bloginfo);

	$blogman->set('lastcomment', $lastcomment);
	$blogman->set('lastcommenter', $lastcommenter);
	$blogman->set('lastblogtextid', $lastblogtextid);
	$blogman->set('comments_visible', $comments['visible'], true, false);
	$blogman->set('comments_moderation', $comments['moderation'], true, false);
	$blogman->set('comments_deleted', $comments['deleted'], true, false);
	$blogman->set('trackback_visible', $trackback['visible'], true, false);
	$blogman->set('trackback_moderation', $trackback['moderation'], true, false);
	$blogman->set('ratingnum', $ratings['ratingnum'], true, false);
	$blogman->set('ratingtotal', $ratings['ratingtotal'], true, false);
	$blogman->set('rating', $ratings['ratingnum'] ? $ratings['ratingtotal'] / $ratings['ratingnum'] : 0, true, false);
	$blogman->save();
}

/**
* Build the metadata for a user's blog
*
* @param	integer	ID of the user
*
* @return	void
*/
function build_blog_user_counters($userid)
{
	global $vbulletin;

	if (!($userid = intval($userid)))
	{
		return;
	}

	$posts = $vbulletin->db->query_first("
		SELECT
			SUM(IF(blog.state = 'visible' AND dateline <= " . TIMENOW . " AND pending = 0, comments_visible, 0)) AS commentcount,
			SUM(IF(blog.state = 'visible' AND dateline <= " . TIMENOW . ", 1, 0) AND pending = 0) AS visible,
			SUM(IF(blog.state = 'moderation', 1, 0)) AS moderation,
			SUM(IF(blog.state = 'deleted', 1, 0)) AS deleted,
			SUM(IF(blog.state = 'draft', 1, 0)) AS draft,
			SUM(IF(blog.state = 'visible' AND dateline <= " . TIMENOW . " AND pending = 0, ratingnum, 0)) AS ratingnum,
			SUM(IF(blog.state = 'visible' AND dateline <= " . TIMENOW . " AND pending = 0, ratingtotal, 0)) AS ratingtotal,
			SUM(IF(blog.dateline > " . TIMENOW . " OR pending = 1, 1, 0)) AS pending,
			SUM(IF(blog.state = 'visible' AND dateline <= " . TIMENOW . " AND pending = 0, comments_moderation, 0)) AS comments_moderation,
			SUM(IF(blog.state = 'visible' AND dateline <= " . TIMENOW . " AND pending = 0, comments_deleted, 0)) AS comments_deleted
		FROM " . TABLE_PREFIX . "blog AS blog
		WHERE blog.userid = $userid
	");

	$lastpost = $vbulletin->db->query_first("
		SELECT title, blogid, dateline, lastcomment, lastcommenter, lastblogtextid
		FROM " . TABLE_PREFIX . "blog AS blog
		WHERE userid = $userid AND
			state = 'visible' AND
			dateline <= " . TIMENOW . " AND
			pending = 0
		ORDER BY dateline DESC
		LIMIT 1
	");

	$uncats = $vbulletin->db->query_first("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "blog AS blog
		LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser USING (blogid)
		WHERE blog.userid = $userid AND
			state = 'visible' AND
			dateline <= " . TIMENOW . " AND
			pending = 0 AND
			blogcategoryid IS NULL
	");

	if ($vbulletin->userinfo['userid'] != $userid)
	{
		$userinfo = $vbulletin->db->query_first("
			SELECT bloguserid
			FROM " . TABLE_PREFIX . "blog_user
			WHERE bloguserid = $userid
		");
	}
	else
	{
		$userinfo = array('bloguserid' => $userid);
	}

	$blogman =& datamanager_init('Blog_User', $vbulletin, ERRTYPE_SILENT);
	if ($userinfo['bloguserid'])
	{
		$blogman->set_existing($userinfo);
	}
	else
	{
		$blogman->set('bloguserid', $userid);
	}

	$blogman->set('lastcomment', $lastpost['lastcomment']);
	$blogman->set('lastcommenter', $lastpost['lastcommenter']);
	$blogman->set('lastblogtextid', $lastpost['lastblogtextid']);

	$blogman->set('lastblog', $lastpost['dateline']);
	$blogman->set('lastblogid', $lastpost['blogid']);
	$blogman->set('lastblogtitle', $lastpost['title']);

	$blogman->set('entries', $posts['visible'], true, false);
	$blogman->set('moderation', $posts['moderation'], true, false);
	$blogman->set('deleted', $posts['deleted'], true, false);
	$blogman->set('draft', $posts['draft'], true, false);
	$blogman->set('comments', $posts['commentcount'], true, false);
	$blogman->set('pending', $posts['pending'], true, false);

	$blogman->set('ratingnum', $posts['ratingnum'], true, false);
	$blogman->set('ratingtotal', $posts['ratingtotal'], true, false);
	$blogman->set('rating', $posts['ratingnum'] ? $posts['ratingtotal'] / $posts['ratingnum'] : 0, true, false);

	$blogman->set('comments_moderation', $posts['comments_moderation'], true, false);
	$blogman->set('comments_deleted', $posts['comments_deleted'], true, false);

	$blogman->set('uncatentries', $uncats['total'], true, false);
	$blogman->save();
}

/**
* Construct the 'publish on' select menu
*
* @param	array			Bloginfo array for the entry
* @param	interger|null	Unixtime stamp to use for the date, if null it will use the current time
*
* @return	void
*/
function construct_publish_select($blog, $dateline = NULL)
{
	global $publish_selected;
	$publish_selected = array();

	if ($dateline == NULL)
	{
		$dateline = TIMENOW;
	}
	$date = getdate($dateline);

	$publish_selected = array(
		'hour'		=> vbdate('H', $dateline, false, false),
		'minute'	=> vbdate('i', $dateline, false, false),
		'month'		=> vbdate('n', $dateline, false, false),
		'date'		=> vbdate('d', $dateline, false, false),
		'year'		=> vbdate('Y', $dateline, false, false),
	);

	$publish_selected["$date[mon]"] = ' selected="selected"';

	// check blog status in case we're already processing a preview
	if ($blog['state'] == 'draft' OR $blog['status'] == 'draft')
	{
		$publish_selected['draft'] = ' selected="selected"';
	}
	else if ($dateline > TIMENOW OR $blog['status'] == 'publish_on')
	{
		$publish_selected['publish_on'] = ' selected="selected"';
	}
	else
	{
		$publish_selected['publish_now'] = ' selected="selected"';
	}
}

/**
* Build Blog permission query for search
*
* @param	array	Userinfo array that must at least contain permissions
*
* @return	array	An array containing the 'joins' and 'where' conditions to enforce permissions correctly
*/
function build_blog_permissions_query($user)
{
	global $vbulletin;
	$permissions =& $user['permissions'];
	$joins = array();

	$state = array("'visible'");

	/* this is for the current user, do we expect this to come from another user? */
	if (can_moderate_blog('canmoderateentries'))
	{
		$state[] = "'moderation'";
	}
	if (can_moderate_blog('candeleteentries'))
	{
		$state[] = "'deleted'";
	}

	$text = "blog.state IN (" . implode(',', $state) . ") AND blog.pending = 0 AND blog.dateline <= " . TIMENOW;
	if (!($permissions['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$text .= " AND blog.userid = $user[userid]";
	}

	if (!($permissions['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $vbulletin->userinfo['userid'])
	{
		$text .= " AND blog.userid <> $user[userid]";
	}

	if (!can_moderate_blog())
	{
		$joins[] = "LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)";

		if ($user['userid'])
		{
			$userlist_sql = array();
			$userlist_sql[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
			$userlist_sql[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
			$userlist_sql[] = "(options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
			$text .= " AND (" . implode(" OR ", $userlist_sql) . ")";

			$joins[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $user['userid'] . " AND buddy.type = 'buddy')";
			$joins[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $user['userid'] . " AND ignored.type = 'ignore')";
		}
		else
		{
			$sql1[] = "options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
		}
	}

	$return = array();
	$return['join'] = implode("\n", $joins);
	$return['where'] = "($text)";

	return $return;
}

/**
* Update Category Counters in mass
*
* @param	array		Userids
*
* @return	void
*/
function build_category_counters_mass($userids)
{
	global $vbulletin;

	if (empty($userids) OR !is_array($userids))
	{
		return;
	}
	$userids = $vbulletin->input->clean($userids, TYPE_ARRAY_UINT);

	if (!defined('MYSQL_VERSION'))
	{
		$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
		define('MYSQL_VERSION', $mysqlversion['version']);
	}
	if (!defined('MYSQL_ENGINE'))
	{
		define('MYSQL_ENGINE', (version_compare(MYSQL_VERSION, '4.0.18', '<')) ? 'TYPE' : 'ENGINE');
	}
	if (!defined('MYSQL_TABLETYPE'))
	{
		define('MYSQL_TABLETYPE', (version_compare(MYSQL_VERSION, '4.1', '<')) ? 'HEAP' : 'MEMORY');
	}

	$hash = $vbulletin->userinfo['userid'] . '_' . TIMENOW;
	$aggtable = "baggregate_temp_$hash";

	$vbulletin->db->query_write("
		CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$aggtable (
			blogcategoryid INT UNSIGNED NOT NULL DEFAULT '0',
			entrycount INT UNSIGNED NOT NULL DEFAULT '0',
			KEY blogcategoryid (blogcategoryid)
		) " . MYSQL_ENGINE . " = " . MYSQL_TABLETYPE . "
	");

	if ($vbulletin->options['usemailqueue'] == 2)
	{
		$vbulletin->db->query_write("LOCK TABLES " . TABLE_PREFIX . "$aggtable WRITE, " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser WRITE, " . TABLE_PREFIX . "blog AS blog READ");
		$vbulletin->db->locked = true;
	}

	$vbulletin->db->query_write("
		INSERT INTO " . TABLE_PREFIX . "$aggtable
			SELECT blogcategoryid, COUNT(*) AS totalposts
			FROM " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser
			INNER JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
			WHERE blog_categoryuser.userid IN(" . implode(',', $userids) . ") AND
				blog.dateline <= " . TIMENOW . " AND
				blog.pending = 0 AND
				blog.state = 'visible'
			GROUP BY blogcategoryid
	");

	if ($vbulletin->options['usemailqueue'] == 2)
	{
		$vbulletin->db->unlock_tables();
	}

	$vbulletin->db->query_write(
		"UPDATE " . TABLE_PREFIX . "blog_category AS blog_category, " . TABLE_PREFIX . "$aggtable AS aggregate
		SET blog_category.entrycount = IF(aggregate.entrycount, aggregate.entrycount, 0)
		WHERE blog_category.blogcategoryid = aggregate.blogcategoryid
	");

	$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $aggtable);
}

/**
* Construct a calendar table for the sidebar
*
* @param	integer		Month
* @param	integer		Year
* @param	integer		Userid of user, if provicded entries on days will be marked
*
* @return	string		HTML output
*/
function construct_calendar($month, $year, $userid = 0)
{
	global $vbulletin, $vbphrase, $stylevar, $vbcollapse;

	require_once(DIR . '/includes/functions_misc.php');

	$months = array(
		1  => 'january',
		2  => 'february',
		3  => 'march',
		4  => 'april',
		5  => 'may',
		6  => 'june',
		7  => 'july',
		8  => 'august',
		9  => 'september',
		10 => 'october',
		11 => 'november',
		12 => 'december'
	);

	$days = array(
		1 => 'sunday',
		2 => 'monday',
		3 => 'tuesday',
		4 => 'wednesday',
		5 => 'thursday',
		6 => 'friday',
		7 => 'saturday',
	);

	$monthname = $vbphrase["$months[$month]"];
	$nextmonth = ($month == 12) ? 1 : $month + 1;
	$prevmonth = ($month == 1) ? 12 : $month - 1;
	$nextyear = ($month == 12) ? ($year == 2037 ? 1970 : $year + 1) : $year;
	$prevyear = ($month == 1) ? ($year == 1970 ? 2037 : $year - 1) : $year;

	$startdate = getdate(gmmktime(12, 0, 0, $month, 1, $year));

	$calendarrows = '';
	// set up which days will be shown
	$vbulletin->userinfo['startofweek'] = ($vbulletin->userinfo['startofweek'] < 1 OR $vbulletin->userinfo['startofweek'] > 7) ? 1 : $vbulletin->userinfo['startofweek'];
	$weekstart = $vbulletin->userinfo['startofweek'];
	for ($i = 0; $i < 7; $i++)
	{
		$dayvarname = 'day' . ($i + 1);
		$$dayvarname = $vbphrase[ $days[$weekstart] . '_short'];
		$weekstart++;
		if ($weekstart == 8)
		{
			$weekstart = 1;
		}
	}

	$curday = 1;
	while (gmdate('w', gmmktime(0, 0, 0, $month, $curday, $year)) + 1 != $vbulletin->userinfo['startofweek'])
	{
		$curday--;
	}
	$totaldays = gmdate('t', gmmktime(0, 0, 0, $month, 1, $year));

	if (
			($totaldays != 30 OR (gmdate('w', gmmktime(0, 0, 0, $month, 30, $year)) + 1) != $vbulletin->userinfo['startofweek'])
		AND
			(
				($totaldays != 31 OR
					(
						gmdate('w', gmmktime(0, 0, 0, $month, 31, $year)) != $vbulletin->userinfo['startofweek']
						 AND
						(gmdate('w', gmmktime(0, 0, 0, $month, 31, $year)) + 1) != $vbulletin->userinfo['startofweek']
					)
				)
			)
		)
	{
		$curday = $curday - 7;
		if ($totaldays == 28 AND gmdate('w', gmmktime(0, 0, 0, $month, 1, $year)) == ($vbulletin->userinfo['startofweek'] - 1))
		{
			$curday = $curday - 7;
		}
	}

	$sql1 = array();
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$sql[] = "userid = " . $vbulletin->userinfo['userid'];
	}
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $vbulletin->userinfo['userid'])
	{
		if (!empty($sql))
		{	// can't view own blog or others' blog
			// This condition should not be reachable
			$sql1[] = "1 <> 1";
		}
		else
		{
			$sql1[] = "blog.userid <> " . $vbulletin->userinfo['userid'];
		}
	}

	$state = array('visible');
	if (can_moderate_blog('canmoderateentries'))
	{
		$state[] = 'moderation';
	}
	if (can_moderate_blog())
	{
		$state[] = 'deleted';
	}

	$sql1join = array();
	if (!can_moderate_blog())
	{
		$sql1join[] = "LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)";

		if ($vbulletin->userinfo['userid'])
		{
			$userlist_sql = array();
			$userlist_sql[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
			$userlist_sql[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
			$userlist_sql[] = "(options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
			$sql1[] = "(" . implode(" OR ", $userlist_sql) . ")";

			$sql1join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
			$sql1join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";
		}
		else
		{
			$sql1[] = "options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
		}
	}

	$prevdays = 1;
	while (gmdate('w', gmmktime(0, 0, 0, $month + 1, $prevdays, $year)) + 1 != $vbulletin->userinfo['startofweek'])
	{
		$prevdays--;
	}

	$adddays = 0;
	if ($prevdays <= 0)
	{
		$adddays = $prevdays + 6;
	}

	require_once(DIR . '/includes/functions_misc.php');
	$starttime = vbmktime(0, 0, 0, $month, $curday, $year);
	$endtime = vbmktime(0, 0, 0, $month + 1, 1 + $adddays, $year);
	$endtime = ($endtime > TIMENOW) ? TIMENOW : $endtime;

	$sql1[] = "state IN('" . implode("', '", $state) . "')";
	$sql1[] = "dateline >= $starttime";
	$sql1[] = "dateline < $endtime";
	if ($userid)
	{
		$sql1[] = "blog.userid = $userid";
	}

	$sql2 = array();
	if ((($userid AND $userid == $vbulletin->userinfo['userid']) OR (!$userid AND $vbulletin->userinfo['userid'])) AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
	{
		$sql2[] = "blog.userid = " . $vbulletin->userinfo['userid'];
		$sql2[] = "dateline >= $starttime";
		$sql2[] = "dateline < " . vbmktime(0, 0, 0, $month + 1, 1 + $adddays, $year);
	}

	$blogcache = array();
	$blogs = $vbulletin->db->query_read_slave("
		" . (!empty($sql2) ? "(" : "") . "
			SELECT COUNT(*) AS total,
			FROM_UNIXTIME(dateline - " . $vbulletin->options['hourdiff'] . ", '%c-%e-%Y') AS period
			FROM " . TABLE_PREFIX . "blog AS blog
			" . (!empty($sql1join) ? implode("\r\n", $sql1join) : "") . "
			WHERE " . implode(" AND ", $sql1) . "
			GROUP BY period
		" . (!empty($sql2) ? ") UNION (
			SELECT COUNT(*) AS total,
			FROM_UNIXTIME(dateline - " . $vbulletin->options['hourdiff'] . ", '%c-%e-%Y') AS period
			FROM " . TABLE_PREFIX . "blog AS blog
			WHERE " . implode(" AND ", $sql2) . "
			GROUP BY period
		)" : "") . "
	");
	while ($blog = $vbulletin->db->fetch_array($blogs))
	{
		$blogcache["$blog[period]"] += $blog['total'];
	}
	$today = getdate(TIMENOW - $vbulletin->options['hourdiff']);
	while (!$monthcomplete)
	{
		$calendarrows .= '<tr>';
		for ($i = 0; $i < 7; $i++)
		{
			if ($curday <= 0)
			{
				$currentmonth = ($month - 1 == 0) ? 12 : $month - 1;
				$currentyear = ($currentmonth == 12) ? $year - 1 : $year;
			}
			else if ($curday > $totaldays)
			{
				$currentmonth = ($month + 1 > 12) ? 1 : $month + 1;
				$currentyear = ($currentmonth == 1) ? $year + 1 : $year;
			}
			else
			{
				$currentmonth = $month;
				$currentyear = $year;
			}

			$day = gmdate('j', gmmktime(0, 0, 0, $month, $curday, $year));
			$show['thismonth'] = ($curday > 0 AND $curday <= $totaldays) ? true : false;
			$show['highlighttoday'] = ($currentmonth == $today['mon'] AND $currentyear == $today['year'] AND $day == $today['mday'] AND $show['thismonth']) ? true : false;

			$show['daylink'] = false;
			if (!empty($blogcache["$currentmonth-$day-$currentyear"]))
			{
				$total = $blogcache["$currentmonth-$day-$currentyear"];
				$show['daylink'] = true;
			}

			$curday++;
			eval('$calendarrows .= "' . fetch_template('blog_sidebar_calendar_day') . '";');
		}
		$calendarrows .= '</tr>';

		if ($curday > $totaldays)
		{
			$monthcomplete = true;
		}
	}

	eval('$calendar = "' . fetch_template('blog_sidebar_calendar') . '";');

	return $calendar;
}

/**
* Build the blog statistics for sidebar
*
* @return	void
*/
function build_blog_stats()
{
	global $vbulletin;

	$blogstats = array();

	$total_blog_users = $vbulletin->db->query_first_slave("
		SELECT COUNT(DISTINCT userid) AS total
		FROM " . TABLE_PREFIX . "blog WHERE state = 'visible'
	");
	$total_blog_entries = $vbulletin->db->query_first_slave("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "blog
		WHERE state = 'visible'
			AND dateline < " . TIMENOW
	);
	$entries_in_24hours = $vbulletin->db->query_first_slave("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "blog
		WHERE state = 'visible'
			AND (dateline > " . (TIMENOW - (24 * 3600)) . "
			AND dateline < " . TIMENOW . ")
	");

	$blogstats['total_blog_users'] = $total_blog_users['total'];
	$blogstats['total_blog_entries'] = $total_blog_entries['total'];
	$blogstats['entries_in_24hours'] = $entries_in_24hours['total'];

	build_datastore('blogstats', serialize($blogstats), 1);

	return $blogstats;
}

/**
* Constructs the avatar code for display on the blog page
*
* @param	array	vBulletin userinfo array
*
* @return	void
*/
function fetch_avatar_html(&$userinfo)
{
	global $vbulletin, $show;

	// get avatar
	if ($userinfo['avatarid'])
	{
		$userinfo['avatarurl'] = $userinfo['avatarpath'];
	}
	else
	{
		if ($userinfo['hascustomavatar'] AND $vbulletin->options['avatarenabled'])
		{
			if ($vbulletin->options['usefileavatar'])
			{
				$userinfo['avatarurl'] = $vbulletin->options['avatarurl'] . '/avatar' . $userinfo['userid'] . '_' . $userinfo['avatarrevision'] . '.gif';
			}
			else
			{
				$userinfo['avatarurl'] = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . '&amp;dateline=' . $userinfo['avatardateline'];
			}

			$userinfo['avwidthpx'] = intval($userinfo['avwidth']);
			$userinfo['avheightpx'] = intval($userinfo['avheight']);

			if ($userinfo['avwidth'] AND $userinfo['avheight'])
			{
				$userinfo['avwidth'] = 'width="' . $userinfo['avwidth'] . '"';
				$userinfo['avheight'] = 'height="' . $userinfo['avheight'] . '"';
			}
			else
			{
				$userinfo['avwidth'] = '';
				$userinfo['avheight'] = '';
			}
		}
		else
		{
			$userinfo['avatarurl'] = '';
		}
	}

	if (empty($userinfo['permissions']))
	{
		cache_permissions($userinfo, false);
	}

	if ( // no avatar defined for this user
		empty($userinfo['avatarurl'])
		OR // visitor doesn't want to see avatars
		($vbulletin->userinfo['userid'] > 0 AND !$vbulletin->userinfo['showavatars'])
		OR // user has a custom avatar but no permission to display it
		(!$userinfo['avatarid'] AND !($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canuseavatar']) AND !$userinfo['adminavatar']) //
	)
	{
		$show['avatar'] = false;
	}
	else
	{
		$show['avatar'] = true;
	}
}

/**
* Constructs the profile pic code for display on the blog page
*
* @param	array	vBulletin userinfo array
*
* @return	void
*/
function fetch_profilepic_html(&$userinfo)
{
	global $vbulletin, $show;

	if (empty($userinfo['permissions']))
	{
		cache_permissions($userinfo, false);
	}

	if ($vbulletin->options['profilepicenabled'] AND $userinfo['profilepic'] AND ($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseeprofilepic'] OR $vbulletin->userinfo['userid'] == $userinfo['userid']) AND ($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canprofilepic'] OR $userinfo['adminprofilepic']))
	{
		if ($vbulletin->options['usefileavatar'])
		{
			$userinfo['profilepicurl'] = $vbulletin->options['profilepicurl'] . '/profilepic' . $userinfo['userid'] . '_' . $userinfo['profilepicrevision'] . '.gif';
		}
		else
		{
			$userinfo['profilepicurl'] = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'u=' . $userinfo['userid'] . "&amp;dateline=$userinfo[profilepicdateline]&amp;type=profile";
		}

		$userinfo['ppwidthpx'] = intval($userinfo['ppwidth']);
		$userinfo['ppheightpx'] = intval($userinfo['ppheight']);

		if ($userinfo['ppwidthpx'] AND $userinfo['ppheightpx'])
		{
			$userinfo['ppwidth'] = 'width="' . $userinfo['ppwidthpx'] . '"';
			$userinfo['ppheight'] = 'height="' . $userinfo['ppheightpx'] . '"';
		}
		else
		{
			$userinfo['ppwidth'] = '';
			$userinfo['ppheight'] = '';
		}
		$show['profilepic'] = true;
	}
	else
	{
		$userinfo['profilepicurl'] = '';
		$show['profilepic'] = false;
	}
}

/**
* Fetch the latest blog entries for the intro page
*
* @param	string	Type to sort on, valid entries are 'latest', 'blograting' and 'rating'
*
* @return	string	HTML for the latest blog entries
*/
function &fetch_latest_blogs($type = 'latest')
{
	global $vbulletin, $show, $stylevar, $vbphrase;

	$sql_and = array();
	$having_or = array();
	$recentblogbits = '';

	switch($type)
	{
		case 'rating':
			$sql_and[] = "blog.ratingnum >= " . intval($vbulletin->options['vbblog_ratingpost']);
			// blogid is needed because mysql is ordering this result different than when just 'rating' is used with a union (do=bloglist)
			$orderby = "blog.rating DESC, blogid";
			break;
		case 'blograting':
			return fetch_rated_blogs();
		default:
			$orderby = "blog.dateline DESC";
	}

	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$sql_and[] = "blog.userid = " . $vbulletin->userinfo['userid'];
	}
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $vbulletin->userinfo['userid'])
	{
		$sql_and[] = "blog.userid <> " . $vbulletin->userinfo['userid'];
	}

	if (trim($vbulletin->options['globalignore']) != '')
	{
		require_once(DIR . '/includes/functions_bigthree.php');
		if ($coventry = fetch_coventry('string') AND !can_moderate_blog())
		{
			$sql_and[] = "blog.userid NOT IN ($coventry)";
		}
	}

	$state = array('visible');
	if (can_moderate_blog('canmoderateentries'))
	{
		$state[] = 'moderation';
	}

	$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
	$sql_and[] = "blog.dateline <= " . TIMENOW;
	$sql_and[] = "blog.pending = 0";

	// Limit results to 31 days when we are viewing "All Entries"
	if ($type == 'latest')
	{
		$sql_and[] = "blog.dateline >= " . (TIMENOW - 2678400);
	}

	$having_or = array();
	if (!can_moderate_blog())
	{
		if ($vbulletin->userinfo['userid'])
		{
			$having_or[] = "userid = " . $vbulletin->userinfo['userid'];
			$having_or[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignoreid IS NOT NULL)";
			$having_or[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddyid IS NOT NULL)";
			$having_or[] = "(options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddyid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignoreid IS NULL))";
		}
		else
		{
			$having_or[] = "options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
		}
	}

	$having_join = array();
	$having_select = array();
	if ($vbulletin->userinfo['userid'])
	{
		$having_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
		$having_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";
		$having_select[] = "ignored.relationid AS ignoreid, buddy.relationid AS buddyid";
	}

	// Recently Updated
	$recentupdates = $vbulletin->db->query_read_slave("
		SELECT user.username, blogid, blog.title, blog.dateline, blog.state, user.*,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid, options_ignore, options_buddy, options_everyone
			" . (!empty($having_select) ? ", " . implode(", ", $having_select) : "") . "

		" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
		FROM " . TABLE_PREFIX . "blog AS blog " . ($index ? "USE INDEX ($index)" : "") . "
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog.userid)
		LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
		" . (!empty($having_join) ? implode("\r\n", $having_join) : "") . "
		" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
		WHERE " . implode("\r\n\tAND ", $sql_and) . "
		" . (!empty($having_or) ? "HAVING " . implode("\r\n\tOR ", $having_or) : "") . "
		ORDER BY $orderby
		LIMIT 10
	");
	while ($updated = $vbulletin->db->fetch_array($recentupdates))
	{
		$updated = array_merge($updated, convert_bits_to_array($updated['options'], $vbulletin->bf_misc_useroptions));
		$updated = array_merge($updated, convert_bits_to_array($updated['adminoptions'], $vbulletin->bf_misc_adminoptions));
		fetch_musername($updated);
		fetch_avatar_html($updated);
		$updated['title'] = fetch_word_wrapped_string($updated['title'], $vbulletin->options['blog_wordwrap']);
		$updated['postdate'] = vbdate($vbulletin->options['dateformat'], $updated['dateline'], true);
		$updated['posttime'] = vbdate($vbulletin->options['timeformat'], $updated['dateline']);
		$show['moderation'] = ($updated['state'] == 'moderation');
		$show['private'] = false;
		if (can_moderate() AND $updated['userid'] != $vbulletin->userinfo['userid'])
		{
			$everyoneelsecanview = $updated['options_everyone'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $updated['options_buddy'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			if (!$everyoneelsecanview AND (!$updated['buddyid'] OR !$buddiescanview))
			{
				$show['private'] = true;
			}
		}
		eval('$recentblogbits .= "' . fetch_template('blog_home_list_entry') . '";');
	}

	return $recentblogbits;
}

/**
* Fetch the latest blog comments
*
* @param	string	Type to sort on, valid values is 'latest'
*
* @return	string	HTML for the latest blog comments
*/
function &fetch_latest_comments($type = 'latest')
{
	global $vbulletin, $show, $stylevar, $vbphrase;

	$sql_and = array();
	$having_or = array();
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$sql_and[] = "blog.userid = " . $vbulletin->userinfo['userid'];
	}
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $vbulletin->userinfo['userid'])
	{
		$sql_and[] = "blog.userid <> " . $vbulletin->userinfo['userid'];
	}

	if (trim($vbulletin->options['globalignore']) != '')
	{
		require_once(DIR . '/includes/functions_bigthree.php');
		if ($coventry = fetch_coventry('string') AND !can_moderate_blog())
		{
			$sql_and[] = "blog.userid NOT IN ($coventry)";
			$sql_and[] = "blog_text.userid NOT IN ($coventry)";
		}
	}

	$state = array('visible');
	if (can_moderate_blog('canmoderateentries'))
	{
		$state[] = 'moderation';
	}

	$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
	$sql_and[] = "blog.dateline <= " . TIMENOW;
	$sql_and[] = "blog.pending = 0";
	$sql_and[] = "blog_text.state IN('" . implode("', '", $state) . "')";
	$sql_and[] = "blog.firstblogtextid <> blog_text.blogtextid";

	// Limit results to 14 days when we are viewing "All Comments"
	if ($type == 'latest')
	{
		$sql_and[] = "blog_text.dateline >= " . (TIMENOW - 1209600);
	}

	if (!can_moderate_blog())
	{
		if ($vbulletin->userinfo['userid'])
		{
			$having_or[] = "userid = " . $vbulletin->userinfo['userid'];
			$having_or[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignoreid IS NOT NULL)";
			$having_or[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddyid IS NOT NULL)";
			$having_or[] = "(options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddyid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignoreid IS NULL))";
		}
		else
		{
			$having_or[] = "options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
		}
	}

	if ($vbulletin->userinfo['userid'])
	{
		$having_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
		$having_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";
	}

	// Recently Updated
	$recentupdates = $vbulletin->db->query_read_slave("
		SELECT blog.blogid, user.username, blogtextid, blog.title, blog_text.dateline, blog_text.pagetext, user.*, blog_user.title AS blogtitle, blog_text.title AS commenttitle,
			blog_text.state,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid, options_ignore, options_buddy, options_everyone
		" . (!empty($having_join) ? ", ignored.relationid AS ignoreid, buddy.relationid AS buddyid" : "") . "
		" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
		FROM " . TABLE_PREFIX . "blog_text AS blog_text
		LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_text.blogid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog_text.userid)
		LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
		" . (!empty($having_join) ? implode("\r\n", $having_join) : "") . "
		" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
		WHERE " . implode("\r\n\tAND ", $sql_and) . "
		" . (!empty($having_or) ? "HAVING " . implode("\r\n\tOR ", $having_or) : "") . "
		ORDER BY blog_text.dateline DESC
		LIMIT 10
	");
	while ($updated = $vbulletin->db->fetch_array($recentupdates))
	{
		$updated = array_merge($updated, convert_bits_to_array($updated['options'], $vbulletin->bf_misc_useroptions));
		$updated = array_merge($updated, convert_bits_to_array($updated['adminoptions'], $vbulletin->bf_misc_adminoptions));
		fetch_musername($updated);
		fetch_avatar_html($updated);
		$updated['postdate'] = vbdate($vbulletin->options['dateformat'], $updated['dateline'], true);
		$updated['posttime'] = vbdate($vbulletin->options['timeformat'], $updated['dateline']);
		$updated['title'] = fetch_word_wrapped_string($updated['title'], $vbulletin->options['blog_wordwrap']);
		if ($updated['commenttitle'])
		{
			$updated['excerpt'] = fetch_word_wrapped_string(fetch_trimmed_title($updated['commenttitle'], 50), $vbulletin->options['blog_wordwrap']);
		}
		else
		{
			$updated['excerpt'] = htmlspecialchars_uni(
				fetch_word_wrapped_string(
					fetch_trimmed_title(
						strip_bbcode(
							preg_replace(
								array('#\[img\].*\[/img\]#siU', '#\[url\].*\[/url\]#siU'),
								array($vbphrase['picture_replacement'], $vbphrase['link_replacement']),
								$updated['pagetext'])
							, true, true),
						50
					),
				$vbulletin->options['blog_wordwrap'])
			);
		}
		if (!$updated['blogtitle'])
		{
			$updated['blogtitle'] = $updated['username'];
		}
		$show['moderation'] = ($updated['state'] == 'moderation');
		$show['private'] = false;
		if (can_moderate() AND $updated['userid'] != $vbulletin->userinfo['userid'])
		{
			$everyoneelsecanview = $updated['options_everyone'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $updated['options_buddy'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			if (!$everyoneelsecanview AND (!$updated['buddyid'] OR !$buddiescanview))
			{
				$show['private'] = true;
			}
		}

		eval('$recentcommentbits .= "' . fetch_template('blog_home_list_comment') . '";');
	}

	return $recentcommentbits;
}

/**
* Fetch the blogs sorted by rating in descending order
*
* @return	string	HTML for the latest blogs
*/
function &fetch_rated_blogs()
{
	global $vbulletin, $show, $stylevar, $vbphrase;

	$sql_and = array();
	$having_or = array();
	$recentblogbits = '';

	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$sql_and[] = "blog_user.bloguserid = " . $vbulletin->userinfo['userid'];
	}
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $vbulletin->userinfo['userid'])
	{
		$sql_and[] = "blog_user.bloguserid <> " . $vbulletin->userinfo['userid'];
	}

	if (trim($vbulletin->options['globalignore']) != '')
	{
		require_once(DIR . '/includes/functions_bigthree.php');
		if ($coventry = fetch_coventry('string') AND !can_moderate_blog())
		{
			$sql_and[] = "blog_user.bloguserid NOT IN ($coventry)";
		}
	}

	$sql_and[] = "blog_user.ratingnum >= " . intval($vbulletin->options['vbblog_ratinguser']);

	$having_or = array();
	$having_join = array();
	if (!can_moderate_blog())
	{
		if ($vbulletin->userinfo['userid'])
		{
			$having_or[] = "userid = " . $vbulletin->userinfo['userid'];
			$having_or[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignoreid IS NOT NULL)";
			$having_or[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddyid IS NOT NULL)";
			$having_or[] = "(options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddyid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignoreid IS NULL))";
		}
		else
		{
			$having_or[] = "options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
		}
	}

	if ($vbulletin->userinfo['userid'])
	{
		$having_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog_user.bloguserid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
		$having_join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog_user.bloguserid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";
	}

	// Highest Rated
	$recentupdates = $vbulletin->db->query_read_slave("
		SELECT user.*, blog_user.ratingnum, blog_user.ratingtotal, blog_user.title,
			IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid, options_ignore, options_buddy, options_everyone
		" . (!empty($having_join) ? ", ignored.relationid AS ignoreid, buddy.relationid AS buddyid" : "") . "
		" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
		FROM " . TABLE_PREFIX . "blog_user AS blog_user " . ($index ? "USE INDEX ($index)" : "") . "
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog_user.bloguserid)
		" . (!empty($having_join) ? implode("\r\n", $having_join) : "") . "
		" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
		WHERE " . implode("\r\n\tAND ", $sql_and) . "
		" . (!empty($having_or) ? "HAVING " . implode("\r\n\tOR ", $having_or) : "") . "
		ORDER BY blog_user.rating DESC
		LIMIT 10
	");
	while ($updated = $vbulletin->db->fetch_array($recentupdates))
	{
		$updated = array_merge($updated, convert_bits_to_array($updated['options'], $vbulletin->bf_misc_useroptions));
		$updated = array_merge($updated, convert_bits_to_array($updated['adminoptions'], $vbulletin->bf_misc_adminoptions));
		fetch_musername($updated);
		fetch_avatar_html($updated);
		if ($updated['ratingnum'] > 0)
		{
			$updated['voteavg'] = vb_number_format($updated['ratingtotal'] / $updated['ratingnum'], 2);
			$updated['rating'] = intval(round($updated['ratingtotal'] / $updated['ratingnum']));
		}
		else
		{
			$updated['voteavg'] = 0;
			$updated['rating'] = 0;
		}
		$updated['title'] = $updated['title'] ? $updated['title'] : $updated['username'];

		$show['private'] = false;
		if (can_moderate() AND $vbulletin->userinfo['userid'] != $updated['userid'])
		{
			$everyoneelsecanview = $updated['options_everyone'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $updated['options_buddy'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			if (!$everyoneelsecanview AND (!$updated['buddyid'] OR !$buddiescanview))
			{
				$show['private'] = true;
			}
		}

		eval('$recentblogbits .= "' . fetch_template('blog_home_list_blog') . '";');
	}

	return $recentblogbits;
}

/**
* Constructs the blog overview sidebar
*
* @param	integer	The month to show the calendar for
* @param	integer	The year to show the calendar for
*
* @return	string	HTML for sidebar
*/
function &build_overview_sidebar($month = 0, $year = 0)
{
	global $vbulletin, $show, $stylevar, $vbphrase, $vbcollapse;

	$month = ($month < 1 OR $month > 12) ? vbdate('n', TIMENOW, false, false) : $month;
	$year = ($year > 2037 OR $year < 1970) ? vbdate('Y', TIMENOW, false, false) : $year;

	if ($vbulletin->blogstats === NULL)
	{
		$vbulletin->blogstats = build_blog_stats();
	}

	$blogstats = $vbulletin->blogstats;
	foreach ($blogstats AS $key => $value)
	{
		$blogstats["$key"] = vb_number_format($value);
	}

	$calendar = construct_calendar($month, $year);

	$show['postblog'] = ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpost'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']);
	$show['gotoblog'] = ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']);
	$show['rssfeed'] = ($vbulletin->usergroupcache['1']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']) ? true : false;

	eval('$sidebarbit = "' . fetch_template('blog_sidebar_generic') . '";');

	return $sidebarbit;
}

/**
* Constructs the blog sidebar specific for a user's blog
*
* @param	array	userinfo array
* @param	integer	The month to show the calendar for
* @param	integer	The year to show the calendar for
* @param	boolean	Should posting rules be shown in the sidebar
*
* @return	string	HTML for sidebar
*/
function &build_user_sidebar(&$userinfo, $month = 0, $year = 0, $rules = false)
{
	global $vbulletin, $show, $stylevar, $vbphrase, $vbcollapse;

	$sidebar = array();

	$month = ($month < 1 OR $month > 12) ? vbdate('n', TIMENOW, false, false) : $month;
	$year = ($year > 2037 OR $year < 1970) ? vbdate('Y', TIMENOW, false, false) : $year;

	$calendar = construct_calendar($month, $year, $userinfo['userid']);

	fetch_avatar_html($userinfo);
	fetch_profilepic_html($userinfo);
	$userinfo['joindate'] = vbdate($vbulletin->options['registereddateformat'], $userinfo['joindate']);
	$userinfo['posts'] = vb_number_format($userinfo['posts']);
	$userinfo['entries'] = vb_number_format($userinfo['entries']);

	//########################### Get Recent Comment Bits #####################################
	$commentbits = '';
	$blogtextstate = array('visible');
	if (can_moderate_blog('canmoderatecomments') OR $vbulletin->userinfo['userid'] == $userinfo['userid'])
	{
		$blogtextstate[] = 'moderation';
	}
	if (can_moderate_blog() OR $vbulletin->userinfo['userid'] == $userinfo['userid'])
	{
		$blogtextstate[] = 'deleted';
	}

	$blogstate = array('visible');
	if (can_moderate_blog('canmoderateentries') OR $vbulletin->userinfo['userid'] == $userinfo['userid'])
	{
		$blogstate[] = 'moderation';
	}

	$comments = $vbulletin->db->query_read("
		SELECT blog.blogid, lastblogtextid AS blogtextid, blog_text.userid, blog_text.state, IF(blog_text.userid = 0, blog_text.username, user.username) AS username, blog.blogid, blog.title
		FROM " . TABLE_PREFIX . "blog AS blog
		LEFT JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog.lastblogtextid = blog_text.blogtextid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog_text.userid = user.userid)
		WHERE blog.userid = $userinfo[userid]
			AND blog_text.blogtextid <> blog.firstblogtextid
			AND blog_text.state IN ('" . implode("','", $blogtextstate) . "')
			AND blog.state IN ('" . implode("','", $blogstate) . "')
			AND blog.dateline <= " . TIMENOW . "
			AND blog.pending = 0
		ORDER BY blog.lastcomment DESC
		LIMIT 5
	");
	while ($comment = $vbulletin->db->fetch_array($comments))
	{
		$show['deleted'] = ($comment['state'] == 'deleted') ? true : false;
		$show['moderation'] = ($comment['state'] == 'moderation') ? true : false;
		eval('$sidebar[\'commentbits\'] .= "' . fetch_template('blog_sidebar_comment_link') . '";');
	}

	//########################### Get Category Bits #####################################
	$blog = array('userid' => $userinfo['userid']);
	$categorybits = '';
	if (!empty($vbulletin->vbblog['categorycache']["$userinfo[userid]"]))
	{
		foreach ($vbulletin->vbblog['categorycache']["$userinfo[userid]"] AS $blogcategoryid => $category)
		{
			$show['catbold'] = ($vbulletin->GPC['blogcategoryid'] == $blogcategoryid) ? true : false;
			$show['catlink'] = ($category['entrycount'] AND $vbulletin->GPC['blogcategoryid'] != $blogcategoryid) ? true : false;
			eval('$sidebar[\'categorybits\'] .= "' . fetch_template('blog_sidebar_category_link') . '";');
		}
	}

	if ($userinfo['uncatentries'])
	{
		$blogcategoryid = -1;
		$category = array(
			'title'          => $vbphrase['uncategorized'],
			'entrycount'     => $userinfo['uncatentries'],
			'blogcategoryid' => $blogcategoryid,
		);
		$show['catbold'] = ($vbulletin->GPC['blogcategoryid'] == $blogcategoryid) ? true : false;
		$show['catlink'] = ($category['entrycount'] AND $vbulletin->GPC['blogcategoryid'] != $blogcategoryid) ? true : false;
		eval('$sidebar[\'categorybits\'] = "' . fetch_template('blog_sidebar_category_link') . '" . $sidebar[\'categorybits\'];');
	}

	$show['subscribelink'] = ($userinfo['userid'] != $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['userid']);
	$show['blogsubscribed'] = $userinfo['blogsubscribed'];
	$show['pending'] = ($userinfo['userid'] == $vbulletin->userinfo['userid'] AND $userinfo['blog_pending']);
	$show['draft'] = ($userinfo['userid'] == $vbulletin->userinfo['userid'] AND $userinfo['blog_draft']);
	$show['approvecomments'] = ($userinfo['userid'] == $vbulletin->userinfo['userid'] AND $userinfo['blog_comments_moderation']);

	if ($userinfo['blogid'])
	{
		$canremove = ($vbulletin->userinfo['userid'] == $userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry']);
		$canundelete = $candelete = (
			$vbulletin->userinfo['userid'] == $userinfo['userid']
				AND
			$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry']
				AND
			(
				$userinfo['state'] != 'deleted'
					OR
				$userinfo['del_userid'] == $vbulletin->userinfo['userid']
			)
		);

		$canedit = (
			$vbulletin->userinfo['userid'] == $userinfo['userid']
				AND
			$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_caneditentry']
				AND
			(
				$userinfo['state'] != 'deleted'
					OR
				$userinfo['del_userid'] == $vbulletin->userinfo['userid']
			)
		);

		if ($userinfo['state'] == 'deleted')
		{
			$show['editentry'] = (($canedit OR can_moderate_blog('caneditentries')) AND ($candelete OR can_moderate_blog('candeleteentries')));
		}
		else if ($userinfo['state'] == 'moderation')
		{
			$show['editentry'] = (can_moderate_blog('canmoderateentries') AND can_moderate_blog('caneditentries'));
		}
		else if (($userinfo['state'] == 'draft' OR $userinfo['pending']) AND $vbulletin->userinfo['userid'] == $userinfo['userid'])
		{
			$show['editentry'] = true;
		}
		else
		{
			$show['editentry'] = (can_moderate_blog('caneditentries') OR $canedit);
		}
		$perform_floodcheck = (
			!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
			AND $vbulletin->options['emailfloodtime']
			AND $vbulletin->userinfo['userid']
		);

		$show['emailentry'] = ($userinfo['state'] != 'visible' OR $userinfo['pending'] OR !$vbulletin->options['enableemail'] OR ($perform_floodcheck AND ($timepassed = TIMENOW - $vbulletin->userinfo['emailstamp']) < $vbulletin->options['emailfloodtime'])) ? false : true;
	}

	$show['emaillink'] = (
		$userinfo['showemail'] AND $vbulletin->options['displayemails'] AND (
			!$vbulletin->options['secureemail'] OR (
				$vbulletin->options['secureemail'] AND $vbulletin->options['enableemail']
			)
		) AND $vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canemailmember']
	);
	$show['homepage'] = ($userinfo['homepage'] != '' AND $userinfo['homepage'] != 'http://');
	$show['pmlink'] = ($vbulletin->options['enablepms'] AND $vbulletin->userinfo['permissions']['pmquota'] AND ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
 					OR ($userinfo['receivepm'] AND $vbulletin->perm_cache["{$userinfo['userid']}"]['pmquota'])
 				)) ? true : false;
 	$show['postblog'] = ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpost'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']);
 	$show['gotoblog'] = ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']);
	$show['rssfeed'] = ($vbulletin->usergroupcache['1']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']) ? true : false;
	$show['canpostitems'] = ($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']);

	$userinfo['onlinestatus'] = 0;
	// now decide if we can see the user or not
	if ($userinfo['lastactivity'] > (TIMENOW - $vbulletin->options['cookietimeout']) AND $userinfo['lastvisit'] != $userinfo['lastactivity'])
	{
		if ($userinfo['invisible'])
		{
			if (($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden']) OR $vbulletin->userinfo['userid'] == $userinfo['userid'])
			{
				// user is online and invisible BUT bbuser can see them
				$userinfo['onlinestatus'] = 2;
			}
		}
		else
		{
			// user is online and visible
			$userinfo['onlinestatus'] = 1;
		}
	}

	$blogrules = $rules ? construct_blog_rules($rules) : '';

	eval('$sidebarbit = "' . fetch_template('blog_sidebar_user') . '";');

	return $sidebarbit;
}

/**
* Fetches the permission value for a specific blog comment
*
* @param	string	The permission to check
* @param	array	An array of information about the blog entry
* @param	array	An array of information about the blog comment
*
* @return	boolean	Returns true if they have the permission else false
*/
function fetch_comment_perm($perm, &$bloginfo, &$blogtextinfo)
{
	global $vbulletin;

	// Only moderator can manage a comment that is in a moderated/deleted post, not even the owner of the post can manage in this situation.

	if (
		// Deleted Post
			($bloginfo['state'] == 'deleted' AND !can_moderate_blog('canmoderateentries') AND ($perm != 'canviewcomments' OR $vbulletin->userinfo['userid'] != $bloginfo['userid']))
			 OR
		// Moderated Post
			($bloginfo['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries') AND ($perm != 'canviewcomments' OR $vbulletin->userinfo['userid'] != $bloginfo['userid']))
		)
	{
		return false;
	}

	switch ($perm)
	{
		case 'canviewcomments':
			return
			(
				(
					($blogtextinfo['state'] != 'deleted' OR can_moderate_blog('candeletecomments') OR $bloginfo['userid'] == $vbulletin->userinfo['userid'])
				 	 AND
				 	($blogtextinfo['state'] != 'moderation' OR $bloginfo['userid'] == $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == $blogtextinfo['userid'] OR fetch_comment_perm('canmoderatecomments', $bloginfo, $blogtextinfo))
				)
			);

		case 'caneditcomments':
			return
			(
				(
					$bloginfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']
				)
				 OR
				(
					$blogtextinfo['state'] == 'visible'
					 AND
					$blogtextinfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_caneditowncomment']
				)
				 OR
				(
					can_moderate_blog('caneditcomments')
					 AND
					(
						$blogtextinfo['state'] != 'moderation' OR fetch_comment_perm('canmoderatecomments', $bloginfo, $blogtextinfo)
					)
					 AND
					(
						$blogtextinfo['state'] != 'deleted' OR fetch_comment_perm('candeletecomments', $bloginfo, $blogtextinfo)
					)
				)
			);

		case 'canmoderatecomments':
			return
			(
				(
					$bloginfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']
				)
				 OR
				(
					can_moderate_blog('canmoderatecomments')
				)
			);

		case 'candeletecomments':
			return
			(
				(
					$bloginfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']
				)
				 OR
				(
					can_moderate_blog('candeletecomments')
				)
				 OR
				(
					can_moderate_blog('canremovecomments')
				)
				 OR
				(
					$blogtextinfo['state'] == 'visible'
					 AND
					$blogtextinfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_candeleteowncomment']
				)
			);

		default:
			trigger_error('fetch_comment_perm(): Argument #1; Invalid permission specified');
	}
}

/**
* Verifies that an akismet key is valid
*
* @param	string	The akismet key to check for validity
* @param	string	The URL that the key is going to be used on
* @param	fields	Extra information that should be submitted to akismet
*
* @return	boolean	Returns true if the key is valid else false
*/
function verify_akismet_status($key, $url, $fields = array())
{
	global $vbulletin;

	require_once(DIR . '/includes/class_akismet_blog.php');
	$akismet = new vB_Akismet($vbulletin);

	$akismet->akismet_key = $key;
	$akismet->akismet_board = $url;

	return $akismet->verify_text($fields);
}

/**
* Construct the blog rules table
*
* @param	string	The area the table will be shown, 'comment', 'usercomment', 'entry' or 'user' are valid values
*
* @return	string	HTML for the blog rules tbale
*/
function construct_blog_rules($area = 'entry')
{
	global $vbulletin, $stylevar, $vbphrase, $vbcollapse, $show;

	switch ($area)
	{
		case 'comment':
			$bbcodeon = $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowbbcode'] ? $vbphrase['on'] : $vbphrase['off'];
			$imgcodeon = $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowimages'] ? $vbphrase['on'] : $vbphrase['off'];
			$htmlcodeon = $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowhtml'] ? $vbphrase['on'] : $vbphrase['off'];
			$smilieson = $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowsmilies'] ? $vbphrase['on'] : $vbphrase['off'];
			break;
		case 'usercomment':
			$bbcodeon = $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_allowbbcode'] ? $vbphrase['on'] : $vbphrase['off'];
			$imgcodeon = $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_allowimages'] ? $vbphrase['on'] : $vbphrase['off'];
			$htmlcodeon = $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_allowhtml'] ? $vbphrase['on'] : $vbphrase['off'];
			$smilieson = $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowsmilies'] ? $vbphrase['on'] : $vbphrase['off'];
			break;
		case 'entry':
		case 'user':
			$bbcodeon = $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode'] ? $vbphrase['on'] : $vbphrase['off'];
			$imgcodeon = $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowimages'] ? $vbphrase['on'] : $vbphrase['off'];
			$htmlcodeon = $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowhtml'] ? $vbphrase['on'] : $vbphrase['off'];
			$smilieson = $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowsmilies'] ? $vbphrase['on'] : $vbphrase['off'];
			break;
	}

	($hook = vBulletinHook::fetch_hook('blog_rules')) ? eval($hook) : false;

	eval('$blogrules = "' . fetch_template('blog_rules') . '";');

	return $blogrules;
}

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25314 $
|| ####################################################################
\*======================================================================*/
?>