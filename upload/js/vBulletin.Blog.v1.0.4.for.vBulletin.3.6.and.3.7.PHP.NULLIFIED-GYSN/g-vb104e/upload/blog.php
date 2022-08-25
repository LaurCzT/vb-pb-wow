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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'blog');
define('VBBLOG_PERMS', 1);
define('VBBLOG_STYLE', 1);
define('GET_EDIT_TEMPLATES', 'blog');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'posting',
	'vbblogglobal',
	'postbit',
);

// $actionphrases is broken in 3.6.7 so simulate
if (in_array($_REQUEST['do'], array('sendtofriend', 'dosendtofriend')))
{
	$phrasegroups[] = 'messaging';
}

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'blogstats',
	'blogfeatured',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'BLOG'
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'blog'				=>	array(
		'blog_entry_category',
		'blog_entry_with_userinfo',
		'blog_entry_attachment',
		'blog_entry_attachment_image',
		'blog_entry_attachment_thumbnail',
		'blog_entry_deleted',
		'blog_show_entry',
		'blog_show_entry_nav',
		'blog_show_entry_recent_entry_link',
		'blog_comment',
		'blog_trackback',
		'blog_comment_deleted',
		'showthread_quickreply',
		'blog_sidebar_user',
		'blog_sidebar_comment_link',
		'blog_sidebar_category_link',
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_bbcode_code',
		'blog_bbcode_html',
		'blog_bbcode_php',
		'blog_bbcode_quote',
	),
	'comments'				=> array(
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_list_comments',
		'blog_comment',
		'blog_comment_deleted',
		'blog_sidebar_category_link',
		'blog_sidebar_generic',
		'blog_sidebar_user',
		'blog_sidebar_comment_link',
		'blog_bbcode_code',
		'blog_bbcode_html',
		'blog_bbcode_php',
		'blog_bbcode_quote',
	),
	'none'				=> array(
		'blog_list_entries',
	),
	'list'            => array(
		'blog_list_entries',
		'blog_sidebar_generic',
		'blog_entry_with_userinfo',
		'blog_entry_without_userinfo',
		'blog_entry_attachment',
		'blog_entry_attachment_image',
		'blog_entry_attachment_thumbnail',
		'blog_entry_deleted',
		'blog_entry_category',
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_sidebar_user',
		'blog_sidebar_comment_link',
		'blog_sidebar_category_link',
		'blog_bbcode_code',
		'blog_bbcode_html',
		'blog_bbcode_php',
		'blog_bbcode_quote',
	),
	'sendtofriend'   => array(
		'blog_send_to_friend',
		'imagereg',
		'humanverify',
		'newpost_errormessage',
		'newpost_usernamecode',
		'blog_sidebar_user',
		'blog_sidebar_comment_link',
		'blog_sidebar_category_link',
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
	),
	'viewip'         => array(
		'blog_entry_ip',
	),
	'intro'          => array(
		'blog_home',
		'blog_entry_featured',
		'blog_entry_category',
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_sidebar_generic',
		'blog_home_list_entry',
		'blog_home_list_comment',
		'blog_home_list_blog',
		'blog_bbcode_code',
		'blog_bbcode_html',
		'blog_bbcode_php',
		'blog_bbcode_quote',
	),
	'bloglist'       => array(
		'blog_blog_row',
		'blog_list_blogs_all',
		'blog_list_blogs_best',
		'blog_list_blogs_blog',
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_sidebar_generic',
		'forumdisplay_sortarrow',
	),
);

$actiontemplates['dosendtofriend'] =& $actiontemplates['sendtofriend'];

if (empty($_REQUEST['do']))
{
	if (!empty($_REQUEST['blogid']) OR !empty($_REQUEST['blogtextid']) OR !empty($_REQUEST['b']) OR !empty($_REQUEST['bt']))
	{
		$_REQUEST['do'] = 'blog';

	}
	else if (!empty($_REQUEST['userid']) OR !empty($_REQUEST['u']) OR !empty($_REQUEST['username']))
	{
		$_REQUEST['do'] = 'list';
	}
	else
	{
		$_REQUEST['do'] = 'intro';
	}
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/blog_init.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ### STANDARD INITIALIZATIONS ###
$checked = array();
$blog = array();
$postattach = array();
$bloginfo = array();
$show['moderatecomments'] = (!$vbulletin->options['blog_commentmoderation'] AND $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_followcommentmoderation'] ? true : false);
$show['pingback'] = ($vbulletin->options['vbblog_pingback'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'] ? true : false);
$show['trackback'] = ($vbulletin->options['vbblog_trackback'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'] ? true : false);
$show['notify'] = ($vbulletin->options['vbblog_notifylinks'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansendpingback'] ? true : false);
$navbits = array();

/* Check they can view a blog, any blog */
if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
{
	if (!$vbulletin->userinfo['userid'] OR !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}
}

($hook = vBulletinHook::fetch_hook('blog_start')) ? eval($hook) : false;

// #######################################################################
if ($_REQUEST['do'] == 'blog')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber' => TYPE_UINT,
		'goto'       => TYPE_STR,
	));

	$bloginfo = verify_blog($blogid);

	$wheresql = array();
	$state = array('visible');

	($hook = vBulletinHook::fetch_hook('blog_entry_start')) ? eval($hook) : false;

	if (can_moderate_blog('canmoderateentries') OR $vbulletin->userinfo['userid'] == $bloginfo['userid'])
	{
		$state[] = 'moderation';
	}

	if (can_moderate_blog() OR $vbulletin->userinfo['userid'] == $bloginfo['userid'])
	{
		$state[] = 'deleted';
		$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog.blogid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogid')";
	}

	if ($vbulletin->userinfo['userid'] == $bloginfo['userid'])
	{
		$state[] = 'draft';
	}
	else
	{
		$wheresql[] = "blog.dateline <= " . TIMENOW;
		$wheresql[] = "blog.pending = 0";
	}

	$wheresql[] = "blog.userid = $bloginfo[userid]";
	$wheresql[] = "blog.state IN ('" . implode("','", $state) . "')";

	// remove blog entries that don't interest us
	if ($coventry = fetch_coventry('string') AND !can_moderate_blog())
	{
		$wheresql[] = "blog.userid NOT IN ($coventry)";
	}

	switch($vbulletin->GPC['goto'])
	{
		case 'next':
			$wheresql[] = "blog.dateline > $bloginfo[dateline]";
			if ($next = $db->query_first_slave("
				SELECT blogid
				FROM " . TABLE_PREFIX . "blog AS blog
				WHERE " . implode(" AND ", $wheresql) . "
				ORDER BY blog.dateline
				LIMIT 1
			"))
			{
				$blogid = $next['blogid'];
			}
			else
			{
				standard_error(fetch_error('nonextnewest_blog'));
			}
			break;
		case 'prev':
			$wheresql[] = "blog.dateline < $bloginfo[dateline]";
			if ($prev = $db->query_first_slave("
				SELECT blogid
				FROM " . TABLE_PREFIX . "blog AS blog
				WHERE " . implode(" AND ", $wheresql) . "
				ORDER BY blog.dateline DESC
				LIMIT 1
			"))
			{
				$blogid = $prev['blogid'];
			}
			else
			{
				standard_error(fetch_error('nonextoldest_blog'));
			}
			break;
	}

	$bloginfo = verify_blog($blogid);

	if ($vbulletin->options['vbblog_nextprevlinks'])
	{
		$show['nextprevtitle'] = true;
		if ($next = $db->query_first_slave("
			SELECT blogid, title
			FROM " . TABLE_PREFIX . "blog AS blog
			WHERE " . implode(" AND ", $wheresql) . "
				AND blog.dateline > $bloginfo[dateline]
			ORDER BY blog.dateline
			LIMIT 1
		"))
		{
			$show['nexttitle'] = true;
		}
		if ($prev = $db->query_first_slave("
			SELECT blogid, title
			FROM " . TABLE_PREFIX . "blog AS blog
			WHERE " . implode(" AND ", $wheresql) . "
				AND blog.dateline < $bloginfo[dateline]
			ORDER BY blog.dateline DESC
			LIMIT 1
		"))
		{
			$show['prevtitle'] = true;
		}
		$show['blognav'] = ($show['prevtitle'] OR $show['nexttitle']);
	}
	else
	{
		$show['blognav'] = true;
	}

	// this fetches permissions for the user who created the blog
	cache_permissions($bloginfo, false);

	$displayed_dateline = 0;

	$show['quickcomment'] =
	(
		$vbulletin->userinfo['userid']
		AND
		$bloginfo['cancommentmyblog']
		AND
		($bloginfo['allowcomments'] OR $vbulletin->userinfo['userid'] == $bloginfo['userid'] OR can_moderate_blog())
		AND
		(
			(($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentown']) AND $bloginfo['userid'] == $vbulletin->userinfo['userid'])
			OR
			(($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentothers']) AND $bloginfo['userid'] != $vbulletin->userinfo['userid'])
		)
		AND
		(
			($bloginfo['state'] == 'moderation' AND can_moderate_blog('canmoderateentries')) OR $bloginfo['state'] == 'visible'
		)
		AND !$bloginfo['pending']
	);

	$show['postcomment'] =
	(
		$bloginfo['cancommentmyblog']
		AND
		($bloginfo['allowcomments'] OR $vbulletin->userinfo['userid'] == $bloginfo['userid'] OR can_moderate_blog())
		AND
		(
			(($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentown']) AND $bloginfo['userid'] == $vbulletin->userinfo['userid'])
			OR
			(($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentothers']) AND $bloginfo['userid'] != $vbulletin->userinfo['userid'])
		)
		AND
		(
			($bloginfo['state'] == 'moderation' AND can_moderate_blog('canmoderateentries')) OR $bloginfo['state'] == 'visible'
		)
		AND !$bloginfo['pending']
	);

	// *********************************************************************************

	// display ratings
	if ($bloginfo['ratingnum'] >= $vbulletin->options['vbblog_ratingpost'])
	{
		$bloginfo['ratingavg'] = vb_number_format($bloginfo['ratingtotal'] / $bloginfo['ratingnum'], 2);
		$bloginfo['rating'] = intval(round($bloginfo['ratingtotal'] / $bloginfo['ratingnum']));
		$show['rating'] = true;
	}
	else
	{
		$show['rating'] = false;
	}

	// this is for a guest
	$rated = intval(fetch_bbarray_cookie('blog_rate', $bloginfo['blogid']));

	// voted already
	if ($bloginfo['vote'] OR $rated)
	{
		$rate_index = $rated;
		if ($bloginfo['vote'])
		{
			$rate_index = $bloginfo['vote'];
		}
		$voteselected["$rate_index"] = 'selected="selected"';
		$votechecked["$rate_index"] = 'checked="checked"';
	}
	else
	{
		$voteselected[0] = 'selected="selected"';
		$votechecked[0] = 'checked="checked"';
	}

	// *********************************************************************************
	// update views counter
	if ($vbulletin->options['blogviewslive'])
	{
		// doing it as they happen; for optimization purposes
		$db->shutdown_query("
			UPDATE " . TABLE_PREFIX . "blog
			SET views = views + 1
			WHERE blogid = " . intval($bloginfo['blogid'])
		);
	}
	else
	{
		// or doing it once an hour
		$db->shutdown_query("
			INSERT INTO " . TABLE_PREFIX . "blog_views (blogid)
			VALUES (" . intval($bloginfo['blogid']) . ')'
		);
	}

	require_once(DIR . '/includes/class_bbcode_blog.php');
	require_once(DIR . '/includes/class_blog_response.php');

	$bbcode =& new vB_BbCodeParser_Blog($vbulletin, fetch_tag_list());

	$factory =& new vB_Blog_ResponseFactory($vbulletin, $bbcode, $bloginfo);

	$responsebits = '';
	$saveparsed = '';
	$trackbackbits = '';
	$pagetext_cachable = true;
	$oldest_comment = TIMENOW;

	$entrylink = array();
	// Recent Entries
	$posts = $db->query_read_slave("
		SELECT blog.blogid, blog.title, blog.dateline, blog.state, blog.pending
		" . ($deljoinsql ? ",blog_deletionlog.primaryid" : "") . "
		FROM " . TABLE_PREFIX . "blog AS blog
		$deljoinsql
		WHERE " . implode(" AND ", $wheresql) . "
		ORDER BY blog.dateline DESC
		LIMIT 5
	");
	while ($post = $db->fetch_array($posts))
	{
		if ($post['dateline'] > TIMENOW OR $post['pending'])
		{
			$status['phrase'] = $vbphrase['pending_blog_entry'];
			$status['image'] = "$stylevar[imgdir_misc]/blog/pending_small.gif";
			$show['status'] = true;
		}
		else if ($post['state'] == 'deleted')
		{
			$status['image'] = "$stylevar[imgdir_misc]/blog/trashcan.gif";
			$status['phrase'] = $vbphrase['deleted_blog_entry'];
			$show['status'] = true;
		}
		else if ($post['state'] == 'moderation')
		{
			$status['phrase'] = $vbphrase['moderated_blog_entry'];
			$status['image'] = "$stylevar[imgdir_misc]/blog/moderated.gif";
			$show['status'] = true;
		}
		else if ($post['state'] == 'draft')
		{
			$status['phrase'] = $vbphrase['draft_blog_entry'];
			$status['image'] = "$stylevar[imgdir_misc]/blog/draft_small.gif";
			$show['status'] = true;
		}
		else
		{
			$show['status'] = false;
		}

		$post['date'] = vbdate($vbulletin->options['dateformat'], $post['dateline']);
		$post['time'] = vbdate($vbulletin->options['timeformat'], $post['dateline']);
		eval('$entrylink[] = "' . fetch_template('blog_show_entry_recent_entry_link') . '";');
	}
	$entrylinks = implode("\r\n", $entrylink);

	// Comments
	$deljoinsql = '';
	$state = array('visible');
	if (can_moderate_blog('canmoderatecomments') OR $vbulletin->userinfo['userid'] == $bloginfo['userid'])
	{
		$state[] = 'moderation';
	}
	if (can_moderate_blog() OR $vbulletin->userinfo['userid'] == $bloginfo['userid'])
	{
		$state[] = 'deleted';
		$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog_text.blogtextid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogtextid')";
	}
	else
	{
		$deljoinsql = '';
	}

	// Get our page
	if ($blogtextid)
	{
		$getpagenum = $db->query_first("
			SELECT COUNT(*) AS comments
			FROM " . TABLE_PREFIX . "blog_text AS blog_text
			WHERE blogid = $blogid
				AND state IN ('" . implode("','", $state) . "')
				AND blogtextid <> $bloginfo[firstblogtextid]
				AND dateline <= $blogtextinfo[dateline]
		");
		$vbulletin->GPC['pagenumber'] = ceil($getpagenum['comments'] / $vbulletin->options['blog_commentsperpage']);
	}
	if ($coventry = fetch_coventry('string') AND !can_moderate_blog())
	{
		$globalignore = "AND blog_text.userid NOT IN ($coventry)";
	}
	else
	{
		$globalignore = '';
	}

	cache_ordered_categories($bloginfo['userid']);
	$categories = array();
	// Get categories
	$cats = $db->query_read_slave("
		SELECT blogcategoryid, userid
		FROM " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser
		WHERE blogid = $bloginfo[blogid]
	");
	while ($category = $db->fetch_array($cats))
	{
		$category['title'] = $vbulletin->vbblog['categorycache']["$bloginfo[userid]"]["$category[blogcategoryid]"]['title'];
		$entry_categories["$bloginfo[blogid]"][] = $category;
		eval('$categories[] = "' . fetch_template('blog_entry_category') . '";');
	}
	$categorybits = implode(', ', $categories);

	// load attachments
	if ($bloginfo['attach'])
	{
		$attachments = $db->query_read("
			SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
				IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
				blogid, attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
			FROM " . TABLE_PREFIX . "blog_attachment AS blog_attachment
			LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
			WHERE blog_attachment.blogid = $bloginfo[blogid]
			ORDER BY attachmentid
		");
		// future proofing this and assuming in the future we might support comment attachments
		// if you are the said coder adding this on vbulletin.org then hi from Scott!
		$postattach = array();
		while ($attachment = $db->fetch_array($attachments))
		{
			if (!$attachment['build_thumbnail'])
			{
				$attachment['hasthumbnail'] = false;
			}
			$postattach["$bloginfo[firstblogtextid]"]["$attachment[attachmentid]"] = $attachment;
			($hook = vBulletinHook::fetch_hook('blog_entry_attachmentbit')) ? eval($hook) : false;
		}
	}

	if ($vbulletin->options['vbblog_pingback'] AND $bloginfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'])
	{
		$show['pingbacklink'] = true;
		$pingbackurl = $vbulletin->options['bburl'] . '/blog_callback.php';
		header("X-Pingback: $pingbackurl");
	}

	if ($vbulletin->options['vbblog_trackback'] AND $bloginfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'])
	{
		$show['trackbackrdf'] = true;
		$trackbackurl = $vbulletin->options['bburl'] . '/blog_callback.php?b=' . $bloginfo['blogid'];
		$abouturl = $vbulletin->options['bburl'] . '/blog.php?b=' . $bloginfo['blogid'];
	}


	// Load trackbacks
	if ($show['pingbacklink'] OR $show['trackbackrdf'])
	{
		$canmoderation = (can_moderate_blog('canmoderatecomments') OR $vbulletin->userinfo['userid'] == $bloginfo['userid']);
		if ($bloginfo['trackback_visible'] OR ($bloginfo['trackback_moderation'] AND $canmoderation))
		{
			$inlinemodfound = false;
			$bgclass = 'alt2';
			$trackbacks = $db->query_read("
				SELECT blog_trackback.*
				FROM " . TABLE_PREFIX . "blog_trackback AS blog_trackback
				WHERE blogid = $bloginfo[blogid]
					" . (!$canmoderation ? "AND state = 'visible'" : "") . "
			");
			while ($trackback = $db->fetch_array($trackbacks))
			{
				$response_handler =& $factory->create($trackback);
				$response_handler->cachable = false;
				// we deliberately ignore the returned value since its been templated, we're not really interested in that :)
				$trackbackbits .= $response_handler->construct();
				if ($response_handler->inlinemod)
				{
					$tb_inlinemodfound = true;
				}
			}
			$show['inlinemod_trackback'] = $tb_inlinemodfound;
		}
	}

	if (!empty($bloginfo['blog_description']))
	{
		$bbcode->set_parse_userinfo($bloginfo, $bloginfo['permissions']);
		$description = $bbcode->parse($bloginfo['blog_description'], 'blog_user', $bloginfo['blog_allowsmilie'] ? 1 : 0);
		$show['description'] = true;
	}
	else
	{
		$show['description'] = false;
	}

	if (!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_cangetattach']))
	{
		$vbulletin->options['viewattachedimages'] = 0;
		$vbulletin->options['attachthumbs'] = 0;
	}

	/* Handle the blog entry now */
	require_once(DIR . '/includes/class_blog_entry.php');
	$entry_factory =& new vB_Blog_EntryFactory($vbulletin, $bbcode, $entry_categories);
	$entry_handler =& $entry_factory->create($bloginfo);
	$entry_handler->attachments = $postattach["$bloginfo[firstblogtextid]"];
	$entry_handler->construct();
	$blog =& $entry_handler->blog;
	$status =& $entry_handler->status;

	// *********************************************************************************
	// save parsed post HTML
	if (!empty($saveparsed))
	{
		$db->shutdown_query("
			REPLACE INTO " . TABLE_PREFIX . "blog_textparsed (blogtextid, dateline, hasimages, pagetexthtml, styleid, languageid)
			VALUES $saveparsed
		");
		unset($saveparsed);
	}

	// quick comment
	if ($show['quickcomment'])
	{
		require_once(DIR . '/includes/functions_editor.php');
		$stylevar['messagewidth'] = $stylevar['messagewidth_usercp'];
		$editorid = construct_edit_toolbar(
			'',
			false,
			'blog_comment',
			$vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowsmilies'],
			true,
			false,
			'qr'
		);
	}

	// Comments
	do
	{
		if (!$vbulletin->GPC['pagenumber'])
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}
		$start = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->options['blog_commentsperpage'];
		$pagenumber = $vbulletin->GPC['pagenumber'];

		$state_or = array(
			"blog_text.state IN ('" . implode("','", $state) . "')"
		);

		// Get the viewing user's moderated entries
		if ($vbulletin->userinfo['userid'] AND $bloginfo['comments_moderation'] > 0 AND !can_moderate_blog('canmoderatecomments') AND $vbulletin->userinfo['userid'] != $bloginfo['userid'])
		{
			$state_or[] = "(blog_text.userid = " . $vbulletin->userinfo['userid'] . " AND state = 'moderation')";
		}

		$show['approve'] = $show['delete'] = $show['undelete'] = false;
		$comments = $db->query_read("
			SELECT SQL_CALC_FOUND_ROWS blog_text.*, blog_text.ipaddress AS blogipaddress,
				blog_textparsed.pagetexthtml, blog_textparsed.hasimages,
				user.*, userfield.*, blog_text.username AS postusername,
				blog_editlog.userid AS edit_userid, blog_editlog.dateline AS edit_dateline, blog_editlog.reason AS edit_reason, blog_editlog.username AS edit_username
				" . ($deljoinsql ? ",blog_deletionlog.userid AS del_userid, blog_deletionlog.username AS del_username, blog_deletionlog.reason AS del_reason" : "") . "
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "blog_text AS blog_text
			LEFT JOIN " . TABLE_PREFIX . "blog_textparsed AS blog_textparsed ON(blog_textparsed.blogtextid = blog_text.blogtextid AND blog_textparsed.styleid = " . intval(STYLEID) . " AND blog_textparsed.languageid = " . intval(LANGUAGEID) . ")
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog_text.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = blog_text.userid)
			LEFT JOIN " . TABLE_PREFIX . "blog_editlog AS blog_editlog ON (blog_editlog.blogtextid = blog_text.blogtextid)
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			$deljoinsql
			WHERE blogid = $bloginfo[blogid]
				AND blog_text.blogtextid <> " . $bloginfo['firstblogtextid'] . "
				AND (" . implode(" OR ", $state_or) . ")
				$globalignore
			ORDER BY blog_text.dateline ASC
			LIMIT $start, " . $vbulletin->options['blog_commentsperpage']
		);
		list($comment_count) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $comment_count)
		{
			$vbulletin->GPC['pagenumber'] = ceil($comment_count / $vbulletin->options['blog_commentsperpage']);
		}
	}
	while ($start >= $comment_count AND $comment_count);

	$pagenav = construct_page_nav(
		$vbulletin->GPC['pagenumber'],
		$vbulletin->options['blog_commentsperpage'],
		$comment_count,
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]"
	);

	while ($comment = $db->fetch_array($comments))
	{
		$response_handler =& $factory->create($comment);
		$response_handler->cachable = $pagetext_cachable;
		$responsebits .= $response_handler->construct();

		if ($pagetext_cachable AND $comment['pagetexthtml'] == '')
		{
			if (!empty($saveparsed))
			{
				$saveparsed .= ',';
			}
			$saveparsed .= "($comment[blogtextid], " . intval($bloginfo['lastcomment']) . ', ' . intval($response_handler->parsed_cache['has_images']) . ", '" . $db->escape_string($response_handler->parsed_cache['text']) . "', " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ")";
		}

		if ($comment['dateline'] > $displayed_dateline)
		{
			$displayed_dateline = $comment['dateline'];
		}

		if ($response_handler->inlinemod)
		{
			$c_inlinemodfound = true;
		}
		$oldest_comment = $comment['dateline'];
	}
	// This is only used by Quick Comment but init it either way
	$effective_lastcomment = max($displayed_dateline, $bloginfo['lastcomment']);

	// Only allow AJAX QC on the last page
	$allow_ajax_qc = ($comment_count == 0 OR ($vbulletin->GPC['pagenumber'] == ceil($comment_count / $vbulletin->options['blog_commentsperpage']))) ? 1 : 0;

	if ($vbulletin->userinfo['userid'])
	{
		mark_blog_read($bloginfo, $vbulletin->userinfo['userid'], $oldest_comment);
	}

	// Todo: allow ratings option or permission, hardcoded but we may want to add this
	$show['blograting'] = ($bloginfo['state'] == 'visible');
	$show['rateblog'] =
	(
		$show['blograting']
		AND
		(
			(
				(!$bloginfo['vote'] AND $vbulletin->userinfo['userid'])
			OR
				!$rated
			)
			OR
				$vbulletin->options['votechange']
		)
	);

	$show['trackbacks'] = ($vbulletin->GPC['pagenumber'] <= 1);
	$show['titlefirst'] = true;

	$show['inlinemod'] = $c_inlinemodfound;

	if (empty($responsebits))
	{
		$show['inlinemod_comment_select'] = false;
	}
	else
	{
		$show['inlinemod_comment_select'] = $show['inlinemod'];
	}

	if ($show['inlinemod'] OR $show['quickcomment'])
	{
		$vbphrase['delete_comments_js'] = addslashes_js($vbphrase['delete_comments']);
		$vbphrase['undelete_comments_js'] = addslashes_js($vbphrase['undelete_comments']);
		$vbphrase['approve_comments_js'] = addslashes_js($vbphrase['approve_comments']);
		$vbphrase['unapprove_comments_js'] = addslashes_js($vbphrase['unapprove_comments']);
	}

	$show['entryonly'] = ($bloginfo['pending'] OR $bloginfo['state'] == 'draft' OR ((!$bloginfo['allowcomments'] AND $vbulletin->userinfo['userid'] != $bloginfo['userid'] AND !can_moderate_blog() AND empty($responsebits))));

	$perform_floodcheck = (
		!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
		AND $vbulletin->options['emailfloodtime']
		AND $vbulletin->userinfo['userid']
	);

	$sidebar =& build_user_sidebar($bloginfo);

	// navbar and output
	$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$bloginfo[userid]"] = $bloginfo['blog_title'];
	$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]"] = $bloginfo['title'];

	($hook = vBulletinHook::fetch_hook('blog_entry_complete')) ? eval($hook) : false;

	eval('$blognavbit = "' . fetch_template('blog_show_entry_nav') . '";');
	eval('$content = "' . fetch_template('blog_show_entry') . '";');
}

// #######################################################################
if ($_REQUEST['do'] == 'list')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber'     => TYPE_UINT,
		'perpage'        => TYPE_UINT,
		'month'          => TYPE_UINT,
		'year'           => TYPE_UINT,
		'day'            => TYPE_UINT,
		'blogtype'       => TYPE_NOHTML,
		'commenttype'    => TYPE_NOHTML,
		'type'           => TYPE_STR,
		'blogcategoryid' => TYPE_INT,
		'userid'         => TYPE_UINT,
		'username'       => TYPE_NOHTML,
	));

	require_once(DIR . '/includes/class_bbcode_blog.php');

	if ($vbulletin->GPC['username'])
	{
		$user = $db->query_first_slave("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '" . $db->escape_string($vbulletin->GPC['username']) . "'");
		$vbulletin->GPC['userid'] = $user['userid'];
	}

	if ($vbulletin->GPC['userid'])
	{
		$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1, 10);
		cache_permissions($userinfo, false);
		$show['entry_userinfo'] = false;

		if ($vbulletin->userinfo['userid'] != $userinfo['userid'] AND empty($userinfo['bloguserid']))
		{
			standard_error(fetch_error('blog_noblog', $userinfo['username']));
		}

		if (!$userinfo['canviewmyblog'])
		{
			print_no_permission();
		}
		if (in_coventry($userinfo['userid']) AND !can_moderate_blog())
		{
			standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
		}

		if ($vbulletin->userinfo['userid'] == $userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
		{
			print_no_permission();
		}

		if ($vbulletin->userinfo['userid'] != $userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
		{
			// Can't view other's entries so off you go to your own blog.
			exec_header_redirect("blog.php?$session[sessionurl]u=" . $vbulletin->userinfo['userid']);
		}
	}
	else
	{
		$userinfo = array();
		$show['entry_userinfo'] = true;
	}

	$blogtype = $type = '';
	$month = $year = $day = 0;

	$sql1 = array();
	$sql2 = array();
	$sql1join = array();

	($hook = vBulletinHook::fetch_hook('blog_list_entries_start')) ? eval($hook) : false;

	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$sql1[] = "blog.userid = " . $vbulletin->userinfo['userid'];
	}
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		$sql1[] = "blog.userid <> " . $vbulletin->userinfo['userid'];
	}

	$state = array('visible');
	if (can_moderate_blog('canmoderateentries') OR ($userinfo['userid'] AND $vbulletin->userinfo['userid'] == $userinfo['userid']))
	{
		$state[] = 'moderation';
	}

	$deljoinsql = '';
	if (can_moderate_blog() OR $vbulletin->userinfo['userid'])
	{
		if (can_moderate_blog() OR $vbulletin->userinfo['userid'] == $userinfo['userid'])
		{
			$state[] = 'deleted';
			$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog.blogid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogid')";
		}
		else if ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['blog_deleted'])
		{
			$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog.blogid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogid')";
		}
	}

	if ($vbulletin->GPC['month'] AND $vbulletin->GPC['year'])
	{
		$month = ($vbulletin->GPC['month'] < 1 OR $vbulletin->GPC['month'] > 12) ? vbdate('n', TIMENOW, false, false) : $vbulletin->GPC['month'];
		$year = ($vbulletin->GPC['year'] > 2037 OR $vbulletin->GPC['year'] < 1970) ? vbdate('Y', TIMENOW, false, false) : $vbulletin->GPC['year'];
		if ($day = $vbulletin->GPC['day'])
		{
			if ($day > gmdate('t', gmmktime(12, 0, 0, $month, $day, $year)))
			{	// Invalid day, toss it out
				$day = 0;
			}
		}

		require_once(DIR . '/includes/functions_misc.php');
		if ($day)
		{
			$starttime = vbmktime(0, 0, 0, $month, $day, $year);
			$endtime = vbmktime(0, 0, 0, $month, $day + 1, $year);
		}
		else
		{
			$starttime = vbmktime(0, 0, 0, $month, 1, $year);
			$endtime = vbmktime(0, 0, 0, $month + 1, 1, $year);
		}

		$sql1[] = "dateline >= $starttime";
		$sql1[] = "dateline < $endtime";
		$orderby = "dateline DESC";
	}
	else
	{
		switch($vbulletin->GPC['blogtype'])
		{
			case 'best':
				$blogtype = 'best';
				$sql1[] = "blog.ratingnum >= " . intval($vbulletin->options['vbblog_ratingpost']);
				if (!$userinfo)
				{
					$sql2[] = "blog.ratingnum >= " . intval($vbulletin->options['vbblog_ratingpost']);
				}
				$orderby = "rating DESC, blogid";
				break;
			default:
				$blogtype = 'recent';
				$orderby = "dateline DESC";
		}
	}

	if ($vbulletin->GPC['type'])
	{
		$type = $vbulletin->GPC['type'];
		switch ($vbulletin->GPC['type'])
		{
			case 'draft':
				if ($vbulletin->userinfo['userid'] == $userinfo['userid'])
				{
					$sql1[] = 'blog.state = "draft"';
				}
				break;
			case 'pending':
				if ($vbulletin->userinfo['userid'] == $userinfo['userid'])
				{
					$sql1[] = "(blog.dateline > " . TIMENOW . " OR blog.pending = 1)";
				}
				break;
			case 'moderated':
				if ($vbulletin->userinfo['userid'] == $userinfo['userid'] OR can_moderate_blog('canmoderateentries'))
				{
					$sql1[] = "blog.state = 'moderation'";
					if (!$userinfo)
					{
						$sql2[] = "blog.state = 'moderation'";
					}
				}
				break;
			case 'deleted':
				if ($vbulletin->userinfo['userid'] == $userinfo['userid'] OR can_moderate_blog())
				{
					$sql1[] = "blog.state = 'deleted'";
					if (!$userinfo)
					{
						$sql2[] = "blog.state = 'deleted'";
					}
				}
				break;
			default:
				$type = '';
		}
	}

	$categoryinfo = array();
	if ($userinfo)
	{
		cache_ordered_categories($userinfo['userid']);
		if ($vbulletin->GPC['blogcategoryid'])
		{
			if ($vbulletin->GPC['blogcategoryid'] > 0)
			{
				if ($categoryinfo = $db->query_first_slave("
					SELECT title, description, blogcategoryid
					FROM " . TABLE_PREFIX . "blog_category
					WHERE blogcategoryid = " . $vbulletin->GPC['blogcategoryid'] . "
						AND userid = $userinfo[userid]
				"))
				{
					$sql1join[] = "INNER JOIN " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser ON (blog_categoryuser.blogid = blog.blogid AND blog_categoryuser.userid = $userinfo[userid] AND blog_categoryuser.blogcategoryid = $categoryinfo[blogcategoryid])";
				}
			}
			else
			{
				$sql1join[] = "LEFT JOIN " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser ON (blog_categoryuser.blogid = blog.blogid)";
				$sql1[] = "blog_categoryuser.userid IS NULL";
				$categoryinfo  = array(
					'title'          => $vbphrase['uncategorized'],
					'blogcategoryid' => -1,
					'description'    => $vbphrase['uncategorized_description'],
				);
			}
		}
		$sql1[] = "blog.userid = $userinfo[userid]";

		$blogtitle =& $userinfo['blog_title'];
		if (!empty($userinfo['blog_description']))
		{
			$bbcode =& new vB_BbCodeParser_Blog($vbulletin, fetch_tag_list());
			$bbcode->set_parse_userinfo($userinfo, $userinfo['permissions']);
			$description = $bbcode->parse($userinfo['blog_description'], 'blog_user', $userinfo['blog_allowsmilie'] ? 1 : 0);
			$show['description'] = true;
		}
		else
		{
			$show['description'] = false;
		}
		$sidebar =& build_user_sidebar($userinfo, $month, $year);
		$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]"] = $blogtitle;
	}
	else
	{
		if (!can_moderate_blog())
		{
			if ($coventry = fetch_coventry('string'))
			{
				$sql1[] = "blog.userid NOT IN ($coventry)";
			}

			if ($vbulletin->userinfo['userid'])
			{
				$userlist_sql = array();
				$userlist_sql[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
				$userlist_sql[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
				$userlist_sql[] = "(options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
				$sql1[] = "(" . implode(" OR ", $userlist_sql) . ")";
			}
			else
			{
				$sql1[] = "options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			}
		}

		$sql2[] = "userid = " . $vbulletin->userinfo['userid'];
		if ($vbulletin->GPC['month'] AND $vbulletin->GPC['year'])
		{
			$sql2[] = "dateline >= $starttime";
			$sql2[] = "dateline < $endtime";
		}

		// Limit results to 31 days when we are viewing "All Entries"
		if ((!$vbulletin->GPC['month'] OR !$vbulletin->GPC['year']) AND !$type AND !$blogtype)
		{
			$sql1[] = "dateline >= " . (TIMENOW - 2678400);
			$sql2[] = "dateline >= " . (TIMENOW - 2678400);
		}

		$sidebar =& build_overview_sidebar($month, $year);
	}

	if ($vbulletin->userinfo['userid'])
	{
		$sql1join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
		$sql1join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";
	}

	if (!$userinfo OR $userinfo['userid'] != $vbulletin->userinfo['userid'])
	{
		$sql1[] = "state IN('" . implode("', '", $state) . "')";
		$sql1[] = "blog.pending = 0";
		$sql1[] = "dateline <= " . TIMENOW;
	}

	($hook = vBulletinHook::fetch_hook('blog_list_entries_blog_query')) ? eval($hook) : false;

	// Clear SQL2 since we can't use it.
	if (!$vbulletin->userinfo['userid'] OR !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		$sql2 = array();
	}

	$selectedfilter = array(
		$type => 'selected="selected"'
	);

	if ($vbulletin->options['vbblog_perpage'] > $vbulletin->options['vbblog_maxperpage'])
	{
		$vbulletin->options['vbblog_perpage'] = $vbulletin->options['vbblog_maxperpage'];
	}
	// Set Perpage .. this limits it to 10. Any reason for more?
	if ($vbulletin->GPC['perpage'] == 0)
	{
		$perpage = $vbulletin->options['vbblog_perpage'];
	}
	else if ($vbulletin->GPC['perpage'] > $vbulletin->options['vbblog_maxperpage'])
	{
		$perpage = $vbulletin->options['vbblog_maxperpage'];
	}
	else
	{
		$perpage = $vbulletin->GPC['perpage'];
	}

	$totalposts = 0;
	do
	{
		if (!$vbulletin->GPC['pagenumber'])
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}
		$start = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;

		$blogs = $db->query_read_slave("
			" . (!empty($sql2) ? "(" : "") . "
				SELECT SQL_CALC_FOUND_ROWS attach, blog.blogid, dateline, blog.rating
				FROM " . TABLE_PREFIX . "blog AS blog
				" . (!empty($sql1join) ? implode("\r\n", $sql1join) : "") . "
				LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
				WHERE " . implode(" AND ", $sql1) . "
			" . (!empty($sql2) ? ") UNION (
				SELECT attach, blog.blogid, dateline, rating
				FROM " . TABLE_PREFIX . "blog AS blog
				WHERE " . implode(" AND ", $sql2) . "
			)" : "") . "
			ORDER BY $orderby
			LIMIT $start, $perpage
		");
		list($totalposts) = $db->query_first_slave("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $totalposts)
		{
			$vbulletin->GPC['pagenumber'] = ceil($totalposts / $perpage);
		}
	}
	while ($start >= $totalposts AND $totalposts);

	if ($userinfo)
	{
		$pagenavurl = array("u=$userinfo[userid]");
		if (!empty($categoryinfo))
		{
			$pagenavurl[] = "blogcategoryid=$categoryinfo[blogcategoryid]";
		}
	}
	else
	{
		$pagenavurl = array('do=list');
	}
	if ($vbulletin->GPC['month'] AND $vbulletin->GPC['year'])
	{
		$pagenavurl[] = "m=$month";
		$pagenavurl[] = "y=$year";
		if ($day)
		{
			$pagenavurl[] = "d=$day";
		}
	}
	if ($blogtype)
	{
		$pagenavurl[] = "blogtype=$blogtype";
	}
	if ($type)
	{
		$pagenavurl[] = "type=$type";
	}
	if ($perpage != $vbulletin->options['vbblog_perpage'])
	{
		$pagenavurl[] = "pp=$perpage";
	}

	$pagenav = construct_page_nav(
		$vbulletin->GPC['pagenumber'],
		$perpage,
		$totalposts,
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavurl)
	);

	$postattach = array();
	$blogids = array();
	$attachcount = 0;

	while ($blog = $db->fetch_array($blogs))
	{
		$blogids[] = $blog['blogid'];
		$attachcount += $blog['attach'];
	}

	$categorytitle = '';
	$categories = array();
	if (!empty($blogids))
	{
		$cats = $db->query_read_slave("
			SELECT blogid, title, blog_category.blogcategoryid, blog_categoryuser.userid
			FROM " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser
			LEFT JOIN " . TABLE_PREFIX . "blog_category AS blog_category ON (blog_category.blogcategoryid = blog_categoryuser.blogcategoryid)
			WHERE blogid IN (" . implode(',', $blogids) . ")
			ORDER BY blogid, displayorder
		");
		while ($cat = $db->fetch_array($cats))
		{
			$categories["$cat[blogid]"][] = $cat;
		}

		// Query Attachments
		if ($attachcount)
		{
			$attachments = $db->query_read("
				SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
					IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
					blogid, attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
				FROM " . TABLE_PREFIX . "blog_attachment AS blog_attachment
				LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
				WHERE blog_attachment.blogid IN (" . implode(',', $blogids) . ")
				ORDER BY attachmentid
			");
			// future proofing this and assuming in the future we might support comment attachments
			// if you are the said coder adding this on vbulletin.org then hi from Scott!
			$postattach = array();
			while ($attachment = $db->fetch_array($attachments))
			{
				if (!$attachment['build_thumbnail'])
				{
					$attachment['hasthumbnail'] = false;
				}
				$postattach["$attachment[blogid]"]["$attachment[attachmentid]"] = $attachment;
				($hook = vBulletinHook::fetch_hook('blog_list_entries_attachmentbit')) ? eval($hook) : false;
			}
		}

		if (!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_cangetattach']))
		{
			$vbulletin->options['viewattachedimages'] = 0;
			$vbulletin->options['attachthumbs'] = 0;
		}

		require_once(DIR . '/includes/class_blog_entry.php');

		$bbcode =& new vB_BbCodeParser_Blog_Snippet($vbulletin, fetch_tag_list());
		$factory =& new vB_Blog_EntryFactory($vbulletin, $bbcode, $categories);

		$blogbits = '';

		$blogs = $db->query_read("
			SELECT blog.*, blog_text.pagetext, blog_text.ipaddress AS blogipaddress, blog_text.allowsmilie, user.*, blog_user.title AS blogtitle,
				blog_textparsed.pagetexthtml, blog_textparsed.hasimages, blog_user.options_everyone, blog_user.options_buddy,
				blog_editlog.userid AS edit_userid, blog_editlog.dateline AS edit_dateline, blog_editlog.reason AS edit_reason, blog_editlog.username AS edit_username
			" . ($deljoinsql ? ",blog_deletionlog.userid AS del_userid, blog_deletionlog.username AS del_username, blog_deletionlog.reason AS del_reason" : "") . "
			" . ($vbulletin->userinfo['userid'] ? ", IF(blog_subscribeentry.blogsubscribeentryid, 1, 0) AS entrysubscribed" : "") . "
			" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? ", blog_read.readtime AS blogread, blog_userread.readtime  AS bloguserread" : "") . "
			" . ($vbulletin->userinfo['userid'] ? ", ignored.relationid AS ignoreid, buddy.relationid AS buddyid" : "") . "
			FROM " . TABLE_PREFIX . "blog AS blog
			INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog.firstblogtextid = blog_text.blogtextid)
			LEFT JOIN " . TABLE_PREFIX . "blog_editlog AS blog_editlog ON (blog_editlog.blogtextid = blog.firstblogtextid)
			LEFT JOIN " . TABLE_PREFIX . "blog_textparsed AS blog_textparsed ON(blog_textparsed.blogtextid = blog_text.blogtextid AND blog_textparsed.styleid = " . intval(STYLEID) . " AND blog_textparsed.languageid = " . intval(LANGUAGEID) . ")
			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? "
			LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON (blog_read.blogid = blog.blogid AND blog_read.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON (blog_userread.bloguserid = blog.userid AND blog_userread.userid = " . $vbulletin->userinfo['userid'] . ")
			" : "") . "
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = user.userid)
			" . ($vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "blog_subscribeentry AS blog_subscribeentry ON (blog.blogid = blog_subscribeentry.blogid AND blog_subscribeentry.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			" . (!empty($sql1join) ? implode("\r\n", $sql1join) : "") . "
			$deljoinsql
			WHERE blog.blogid IN (" . implode(',', $blogids) . ")
			ORDER BY $orderby
		");

		while ($blog = $db->fetch_array($blogs))
		{
			$entry_handler =& $factory->create($blog, $userinfo ? '_User' : '');
			$entry_handler->attachments = $postattach["$blog[blogid]"];
			$blogbits .= $entry_handler->construct();
			if ($entry_handler->inlinemod)
			{
				$inlinemodfound = true;
			}
			if ($entry_handler->delete)
			{
				$inlinemoddelete = true;
			}
			if ($entry_handler->undelete)
			{
				$inlinemodundelete = true;
			}
		}
		$show['inlinemod'] = $inlinemodfound;
		$show['delete'] = $inlinemoddelete;
		$show['undelete'] = $inlinemodundelete;
	}

	if ($vbulletin->GPC['month'] AND $vbulletin->GPC['year'])
	{
		if (!empty($categoryinfo))
		{
			$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]&amp;blogcategoryid=$categoryinfo[blogcategoryid]"] = $categoryinfo['title'];
		}
		$monthname = $vbphrase[strtolower(gmdate('F', gmmktime(12, 0, 0, $month, 1, $year)))];
		if ($type)
		{
			$navbits[] = $day ? construct_phrase($vbphrase[$type . '_entries_for_x_y_z'], $monthname, $day, $year) : construct_phrase($vbphrase[$type . '_entries_for_x_y'], $monthname, $year);
		}
		else
		{
			$navbits[] = $day ? construct_phrase($vbphrase['entries_for_x_y_z'], $monthname, $day, $year) : construct_phrase($vbphrase['entries_for_x_y'], $monthname, $year);
		}
	}
	else if ($type)
	{
		if (!empty($categoryinfo))
		{
			$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]&amp;blogcategoryid=$categoryinfo[blogcategoryid]"] = $categoryinfo['title'];
		}
		if ($blogtype != 'recent')
		{
			$navbits[] = $vbphrase[$blogtype . '_' . $type . '_blog_entries'];
		}
		else
		{
			$navbits[] = $vbphrase[$type . '_blog_entries'];
		}
	}
	else if ($blogtype != 'recent')
	{
		if (!empty($categoryinfo))
		{
			$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]&amp;blogcategoryid=$categoryinfo[blogcategoryid]"] = $categoryinfo['title'];
		}
		$navbits[] = $vbphrase[$blogtype . '_blog_entries'];
	}
	else if (!empty($categoryinfo))
	{
		$navbits[] = $categoryinfo['title'];
	}

	$show['filter'] = (can_moderate_blog() OR ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['userid'] == $userinfo['userid']));
	$show['filter_moderation'] = (can_moderate_blog('canmoderateentries') OR $vbulletin->userinfo['userid'] == $userinfo['userid']);
	$show['filter_owner'] = ($vbulletin->userinfo['userid'] == $userinfo['userid']);
	$show['category_description'] = (!empty($categoryinfo));

	if (empty($navbits))
	{
		$navbits = array('' => $vbphrase['blog_entries']);
	}

	($hook = vBulletinHook::fetch_hook('blog_list_entries_complete')) ? eval($hook) : false;

	eval('$content = "' . fetch_template('blog_list_entries') . '";');
}

// #######################################################################
if ($_REQUEST['do'] == 'bloglist')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'pagenumber'     => TYPE_UINT,
		'perpage'        => TYPE_UINT,
		'blogtype'       => TYPE_NOHTML,
		'sortorder'      => TYPE_NOHTML,
		'sortfield'      => TYPE_NOHTML,
	));

	$type = '';

	$sql = array();
	$sqljoin = array();
	$sqlfields = array();

	($hook = vBulletinHook::fetch_hook('blog_list_start')) ? eval($hook) : false;

	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$sql[] = "blog.userid = " . $vbulletin->userinfo['userid'];
	}
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		$sql[] = "blog.userid <> " . $vbulletin->userinfo['userid'];
	}
	if ($coventry = fetch_coventry('string') AND !can_moderate_blog())
	{
		$sql[] = "blog.userid NOT IN ($coventry)";
	}

	if (!can_moderate_blog())
	{
		if ($vbulletin->userinfo['userid'])
		{
			$userlist_sql = array();
			$userlist_sql[] = "blog_user.bloguserid = " . $vbulletin->userinfo['userid'];
			$userlist_sql[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignored.relationid IS NOT NULL)";
			$userlist_sql[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddy.relationid IS NOT NULL)";
			$userlist_sql[] = "(options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddy.relationid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignored.relationid IS NULL))";
			$sql[] = "(" . implode(" OR ", $userlist_sql) . ")";
		}
		else
		{
			$sql[] = "options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
		}
	}

	if ($vbulletin->userinfo['userid'])
	{
		$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog_user.bloguserid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
		$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog_user.bloguserid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";
		$sqlfields[] = "ignored.relationid AS ignoreid, buddy.relationid AS buddyid";
	}

	$sqljoin[] = $vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "";

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
	{
		$sqlfields[] = "IF(blog_tachyentry.userid IS NULL, blog.lastcomment, blog_tachyentry.lastcomment) AS lastcomment";
		$sqlfields[] = "IF(blog_tachyentry.userid IS NULL, blog.lastcommenter, blog_tachyentry.lastcommenter) AS lastcommenter";
		$sqlfields[] = "IF(blog_tachyentry.userid IS NULL, blog.lastblogtextid, blog_tachyentry.lastblogtextid) AS lastblogtextid";

		$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "blog_tachyentry AS blog_tachyentry ON (blog_tachyentry.blogid = blog_user.lastblogid AND blog_tachyentry.userid = " . $vbulletin->userinfo['userid'] . ")";
		$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = IF(blog_tachyentry.userid IS NULL, blog.lastblogtextid, blog_tachyentry.lastblogtextid))";
	}
	else
	{
		$sqljoin[] = "LEFT JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = blog_user.lastblogtextid)";
	}

	$sidebar =& build_overview_sidebar($month, $year);

	switch($vbulletin->GPC['blogtype'])
	{
		case 'best':
			$blogtype = 'best';
			break;
		default:
			$blogtype = 'all';
	}

	$pagenavurl = array('do=bloglist');
	// Set Perpage .. this limits it to 10. Any reason for more?
	if ($vbulletin->options['vbblog_perpage'] > $vbulletin->options['vbblog_maxperpage'])
	{
		$vbulletin->options['vbblog_perpage'] = $vbulletin->options['vbblog_maxperpage'];
	}
	if ($vbulletin->GPC['perpage'] == 0)
	{
		if ($blogtype == 'all')
		{
			$perpage = 15;
		}
		else
		{
			$perpage = $vbulletin->options['vbblog_perpage'];
		}
	}
	else if ($vbulletin->GPC['perpage'] > $vbulletin->options['vbblog_maxperpage'] AND $blogtype == 'best')
	{
		$perpage = $vbulletin->options['vbblog_maxperpage'];
		$pagenavurl[] = "pp=$perpage";
	}
	else if ($vbulletin->GPC['perpage'] > 20 AND $blogtype == 'all')
	{
		$perpage = 20;
		$pagenavurl[] = "pp=$perpage";
	}
	else
	{
		$perpage = $vbulletin->GPC['perpage'];
		$pagenavurl[] = "pp=$perpage";
	}

	switch($blogtype)
	{
		case 'best':
			$sql[] = "blog_user.ratingnum >= " . intval($vbulletin->options['vbblog_ratinguser']);
			$orderby = "blog_user.rating DESC";
			$pagenavurl[] = "blogtype=$blogtype";
			break;
		case 'all':
			// This uses the lastblog,entries index which avoids the filesort! filesort + limit == BAD
			$sql[] = "blog_user.lastblog > 0";
			$sql[] = "blog_user.entries > 0";

			$sortfield  =& $vbulletin->GPC['sortfield'];
			if ($vbulletin->GPC['sortorder'] != 'asc')
			{
				$vbulletin->GPC['sortorder'] = 'desc';
				$sqlsortorder = 'DESC';
				$order = array('desc' => 'selected="selected"');
			}
			else
			{
				$sqlsortorder = '';
				$order = array('asc' => 'selected="selected"');
			}

			switch ($sortfield)
			{
				case 'username':
					$sqlsortfield = 'user.' . $sortfield;
					break;
				case 'title':
					$sqlsortfield = 'order_title';
					break;
				case 'entries':
					$sqlsortfield = 'blog_user.entries';
					break;
				case 'comments':
				case 'lastblog':
					$sqlsortfield = 'blog_user.' . $sortfield;
					break;
				case 'rating':
					$sqlsortfield = 'order_rating';
					break;
				default:
					$sqlsortfield = 'blog_user.lastblog';
					$sortfield = 'lastblog';
			}

			if ($sortfield != 'lastblog')
			{
				$pagenavurl[] = "sort=$sortfield";
			}
			if ($vbulletin->GPC['sortorder'] != 'desc')
			{
				$pagenavurl[] = 'order=asc';
			}

			$orderby = $sqlsortfield . ' ' . $sqlsortorder;

			$sort = array($sortfield => 'selected="selected"');
			$sorturl = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . 'do=bloglist' . ($perpage != 15 ? "&amp;pp=$perpage" : '');

			$oppositesort = ($vbulletin->GPC['sortorder'] == 'asc' ? 'desc' : 'asc');
			eval('$sortarrow[' . $sortfield . '] = "' . fetch_template('forumdisplay_sortarrow') . '";');

	}

	$totalblogs = 0;
	($hook = vBulletinHook::fetch_hook('blog_list_blog_query')) ? eval($hook) : false;
	do
	{
		if (!$vbulletin->GPC['pagenumber'])
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}
		$start = ($vbulletin->GPC['pagenumber'] - 1) * $perpage;

		$blogs = $db->query_read_slave("
			SELECT SQL_CALC_FOUND_ROWS
				user.*,
				blog.firstblogtextid,
				blog_text.pagetext,
				IF (blog_user.title <> '', blog_user.title, user.username) AS order_title,
				IF (blog_user.ratingnum >= " . intval($vbulletin->options['vbblog_ratinguser']) . ", blog_user.rating, 0) AS order_rating,
				blog_user.lastblog, blog_user.lastblogid AS lastblogid, blog_user.lastblogtitle,
				blog_user.lastcomment, blog_user.lastblogtextid AS lastblogtextid, blog_user.lastcommenter,
				blog_user.ratingnum, blog_user.ratingtotal, blog_user.title, blog_user.entries, blog_user.comments, blog_user.title,
				IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid, options_ignore, options_buddy, options_everyone
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				" . ($vbulletin->userinfo['userid'] ? ", IF(blog_subscribeuser.blogsubscribeuserid, 1, 0) AS blogsubscribed" : "") . "
				" . (!empty($sqlfields) ? ", " . implode(", ", $sqlfields) : "") . "
			FROM " . TABLE_PREFIX . "blog_user AS blog_user
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog_user.bloguserid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_user.lastblogid)
			" . ($vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "blog_subscribeuser AS blog_subscribeuser ON (blog.userid = blog_subscribeuser.bloguserid AND blog_subscribeuser.userid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
			" . implode("\r\n", $sqljoin) . "
			WHERE " . implode("\r\n\tAND ", $sql) . "
			ORDER BY $orderby
			LIMIT $start, $perpage
		");
		list($totalblogs) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $totalblogs)
		{
			$vbulletin->GPC['pagenumber'] = ceil($totalblogs / $perpage);
		}
	}
	while ($start >= $totalblogs AND $totalblogs);

	$pagenav = construct_page_nav(
		$vbulletin->GPC['pagenumber'],
		$perpage,
		$totalblogs,
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavurl)
	);

	while ($blog = $db->fetch_array($blogs))
	{
		$blog = array_merge($blog, convert_bits_to_array($blog['options'], $vbulletin->bf_misc_useroptions));
		$blog = array_merge($blog, convert_bits_to_array($blog['adminoptions'], $vbulletin->bf_misc_adminoptions));

		$show['private'] = false;
		if (can_moderate() AND $blog['userid'] != $vbulletin->userinfo['userid'])
		{
			$everyoneelsecanview = $blog['options_everyone'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $blog['options_buddy'] & $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			if (!$everyoneelsecanview AND (!$blog['buddyid'] OR !$buddiescanview))
			{
				$show['private'] = true;
			}
		}

		if ($blog['ratingnum'] > 0 AND $blog['ratingnum'] >= $vbulletin->options['vbblog_ratinguser'])
		{
			$blog['ratingavg'] = vb_number_format($blog['ratingtotal'] / $blog['ratingnum'], 2);
			$blog['rating'] = intval(round($blog['ratingtotal'] / $blog['ratingnum']));
			$show['rating'] = true;
		}
		else
		{
			$blog['ratingavg'] = 0;
			$blog['rating'] = 0;
			$shw['rating'] = false;
		}

		$blog['entries'] = vb_number_format($blog['entries']);
		$blog['comments'] = vb_number_format($blog['comments']);

		$blog['lastentrydate'] = vbdate($vbulletin->options['dateformat'], $blog['lastblog'], true);
		$blog['lastentrytime'] = vbdate($vbulletin->options['timeformat'], $blog['lastblog']);

		if ($blogtype == 'all')
		{
			$blog['entrytitle'] = fetch_trimmed_title($blog['lastblogtitle'], 20);
			if ($blog['title'])
			{
				$blog['title'] = fetch_trimmed_title($blog['title'], 50);
			}
			eval('$blogbits .= "' . fetch_template('blog_blog_row') . '";');
		}
		else
		{
			fetch_musername($blog);
			fetch_avatar_html($blog);
			$blog['onlinestatus'] = 0;
			$blog['commentexcerpt'] = htmlspecialchars_uni(fetch_trimmed_title($blog['pagetext'], 50));

			// now decide if we can see the user or not
			if ($blog['lastactivity'] > (TIMENOW - $vbulletin->options['cookietimeout']) AND $blog['lastvisit'] != $blog['lastactivity'])
			{
				if ($blog['invisible'])
				{
					if (($vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehidden']) OR $blog['userid'] == $vbulletin->userinfo['userid'])
					{
						// user is online and invisible BUT bbuser can see them
						$blog['onlinestatus'] = 2;
					}
				}
				else
				{
					// user is online and visible
					$blog['onlinestatus'] = 1;
				}
			}

			$blog['commentdate'] = vbdate($vbulletin->options['dateformat'], $blog['lastcomment'], true);
			$blog['commenttime'] = vbdate($vbulletin->options['timeformat'], $blog['lastcomment']);
			$show['lastcomment'] = ($blog['lastblogtextid'] AND $blog['lastblogtextid'] != $blog['firstblogtextid']);

			if (!$blog['title'])
			{
				$blog['title'] = $blog['username'];
			}

			eval('$blogbits .= "' . fetch_template('blog_list_blogs_blog') . '";');
		}
	}

	($hook = vBulletinHook::fetch_hook('blog_list_complete')) ? eval($hook) : false;

	if ($blogtype == 'all')
	{
		$navbits[] = $vbphrase['blogs'];
		eval('$content = "' . fetch_template('blog_list_blogs_all') . '";');
	}
	else
	{
		$navbits[] = $vbphrase['best_blogs'];
		eval('$content = "' . fetch_template('blog_list_blogs_best') . '";');
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'intro')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		// Can't view other's entries so off you go to your own blog.
		exec_header_redirect("blog.php?$session[sessionurl]u=" . $vbulletin->userinfo['userid']);
	}

	$month = vbdate('n', TIMENOW, false, false);
	$year = vbdate('Y', TIMENOW, false, false);

	($hook = vBulletinHook::fetch_hook('blog_intro_start')) ? eval($hook) : false;

	$featured = @unserialize($vbulletin->options['vbblog_featured']);
	if ($featured['type'] != 'manual')	// Random
	{
		$getnewblog = false;
		$wheresql = array(
			"dateline <= " . TIMENOW,
			"blog.pending = 0",
			"state = 'visible'",
			"options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'],
			"options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'],
		);

		if ($featured['userid'])
		{
			$wheresql[] = "userid = $featured[userid]";
		}
		if ($featured['startstamp'] AND $featured['startstamp'] <= TIMENOW)
		{
			$wheresql[] = "dateline >= $featured[startstamp]";
		}
		if ($featured['endstamp'] AND $featured['endstamp'] <= TIMENOW)
		{
			$wheresql[] = "dateline < $featured[endstamp]";
		}

		if ($vbulletin->blogfeatured === NULL OR $vbulletin->blogfeatured['dateline'] < (TIMENOW - $featured['refresh']))
		{
			$getnewblog = true;

		}
		else
		{
			$bloginfo = fetch_bloginfo($vbulletin->blogfeatured['blogid']);
			if (!$bloginfo OR $bloginfo['state'] != 'visible' OR $bloginfo['dateline'] > TIMENOW OR !$bloginfo['everyone_canviewmyblog'] OR !$bloginfo['buddy_canviewmyblog'])
			{
				$getnewblog = true;
			}
			else if ($vbulletin->userinfo['userid'] AND !$bloginfo['canviewmyblog'] AND !$featured['userid'])	// user is being ignored and can't view blog, they need their own random blog
			{
				$getrandomblog = true;
				$wheresql[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR userlist.relationid IS NULL)";
				$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist ON userlist.userid = blog.userid AND relationid = " . $vbulletin->userinfo['userid'] . " AND type = 'ignore'";
			}
		}

		if ($getnewblog OR $getrandomblog)
		{
			// Can't use fetch_coventry as if the current user is in coventry and triggers the cache their blog could be picked
			if (trim($vbulletin->options['globalignore']) != '')
			{
				if ($coventry = preg_split('#\s+#s', $vbulletin->options['globalignore'], -1, PREG_SPLIT_NO_EMPTY))
				{
					$wheresql[] = "blog.userid NOt IN (" . implode(',', $coventry) . ")";
				}
			}
			$randomblog = $db->query_first("
				SELECT blogid
				FROM " . TABLE_PREFIX . "blog AS blog
				LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
				" . (!empty($joinsql) ? implode("\r\n", $joinsql) : "") . "
				WHERE " . implode(" AND ", $wheresql) . "
				ORDER BY RAND()
				LIMIT 1
			");

			$blogfeatured = array(
				'blogid'   => intval($randomblog['blogid']), // this can be 0, that is ok
				'dateline' => TIMENOW,
			);

			if ($getnewblog)
			{
				// Generate new blogid. Holy Query, Batman!
				build_datastore('blogfeatured', serialize($blogfeatured), 1);
			}

			$featured['blogid'] = $blogfeatured['blogid'];
		}
		else
		{
			$featured['blogid'] = $vbulletin->blogfeatured['blogid'];
		}
	}

	if ($featured['blogid'] AND $bloginfo = fetch_bloginfo($featured['blogid']) AND $bloginfo['state'] == 'visible' AND $bloginfo['canviewmyblog'])
	{
		$show['featured'] = true;

		$categories = array();
		// Get categories
		$cats = $db->query_read_slave("
			SELECT title, blog_category.blogcategoryid, blog_categoryuser.userid
			FROM " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser
			LEFT JOIN " . TABLE_PREFIX . "blog_category AS blog_category ON (blog_category.blogcategoryid = blog_categoryuser.blogcategoryid)
			WHERE blogid = $bloginfo[blogid]
			ORDER BY displayorder
		");
		while ($category = $db->fetch_array($cats))
		{
			$categories["$bloginfo[blogid]"][] = $category;
		}

		// load attachments - currently we don't display attachments for featured entries so don't run the queries, 'vbblog_attach' doesn't exist,
		// set it with a plugin and play with the featured entry attachments if you want them
		if ($bloginfo['attach'] AND $vbulletin->options['vbblog_attach'])
		{
			$attachments = $db->query_read("
				SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
					IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
					blogid, attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
				FROM " . TABLE_PREFIX . "blog_attachment AS blog_attachment
				LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
				WHERE blog_attachment.blogid = $bloginfo[blogid]
				ORDER BY attachmentid
			");
			$postattach = array();
			while ($attachment = $db->fetch_array($attachments))
			{
				if (!$attachment['build_thumbnail'])
				{
					$attachment['hasthumbnail'] = false;
				}
				$postattach["$attachment[attachmentid]"] = $attachment;
			}
		}

		if (!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_cangetattach']))
		{
			$vbulletin->options['viewattachedimages'] = 0;
			$vbulletin->options['attachthumbs'] = 0;
		}

		require_once(DIR . '/includes/class_bbcode_blog.php');
		require_once(DIR . '/includes/class_blog_entry.php');

		$bbcode =& new vB_BbCodeParser_Blog_Snippet_Featured($vbulletin, fetch_tag_list());
		$factory =& new vB_Blog_EntryFactory($vbulletin, $bbcode, $categories);

		$bloginfo['blogtitle'] =& $bloginfo['blog_title'];

		$entry_handler =& $factory->create($bloginfo, '_Featured');
		$entry_handler->excerpt = true;
		$entry_handler->cachable = false;
		$entry_handler->attachments = $postattach;
		$entry_handler->random = ($featured['type'] != 'manual');
		$blogbit = $entry_handler->construct();
	}

	switch($vbulletin->GPC['blogtype'])
	{
		case 'rating':
		case 'blograting':
			$blogtype = $vbulletin->GPC['blogtype'];
			break;

		default:
			$blogtype = 'latest';
	}

	if ($blogtype == 'latest')
	{
		$display = array(
			'latest'          => '',
			'latest_link'     => 'none',
			'rating'          => 'none',
			'rating_link'     => '',
			'blograting'      => 'none',
			'blograting_link' => '',
		);
	}
	else if ($blogtype == 'rating')
	{
		$display = array(
			'latest'          => 'none',
			'latest_link'     => '',
			'rating'          => '',
			'rating_link'     => 'none',
			'blograting'      => 'none',
			'blograting_link' => '',
		);
	}
	else
	{
		$display = array(
			'latest'          => 'none',
			'latest_link'     => '',
			'rating'          => 'none',
			'rating_link'     => '',
			'blograting'      => '',
			'blograting_link' => 'none',
		);
	}

	$recentblogbits =& fetch_latest_blogs($blogtype);
	$recentcommentbits =& fetch_latest_comments('latest');

	$show['entryfindmore'] = ($recentblogbits);
	$show['commentfindmore'] = ($recentcommentbits);

	if (!$recentblogbits)
	{
		$recentblogbits = fetch_error('blog_no_entries');
	}
	if (!$recentcommentbits)
	{
		$recentcommentbits = fetch_error('blog_no_comments');
	}

	($hook = vBulletinHook::fetch_hook('blog_intro_complete')) ? eval($hook) : false;

	$sidebar =& build_overview_sidebar($month, $year);
	eval('$content = "' . fetch_template('blog_home') . '";');
}

// #######################################################################
if ($_REQUEST['do'] == 'comments')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'    => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'userid'     => TYPE_UINT,
		'type'       => TYPE_STR,
	));

	$sql_and = array();
	$having_or = array();

	($hook = vBulletinHook::fetch_hook('blog_comments_start')) ? eval($hook) : false;

	// Set Perpage .. this limits it to 10. Any reason for more?
	if ($vbulletin->GPC['perpage'] == 0 OR $vbulletin->GPC['perpage'] > $vbulletin->options['blog_commentsperpage'])
	{
		$perpage = $vbulletin->options['blog_commentsperpage'];
	}
	else
	{
		$perpage = $vbulletin->GPC['perpage'];
	}

	if ($vbulletin->GPC['userid'])
	{
		$userinfo = verify_id('user', $vbulletin->GPC['userid'], 1, 1, 10);
		cache_permissions($userinfo, false);
		$show['entry_userinfo'] = false;
		if (!$userinfo['canviewmyblog'])
		{
			print_no_permission();
		}
		if (in_coventry($userinfo['userid']) AND !can_moderate_blog())
		{
			standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
		}

		if ($vbulletin->userinfo['userid'] == $userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
		{
			print_no_permission();
		}

		if ($vbulletin->userinfo['userid'] != $userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
		{
			if ($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
			{
				// Can't view other's entries so off you go to your own blog.
				exec_header_redirect("blog.php?$session[sessionurl]u=" . $vbulletin->userinfo['userid']);
			}
			else
			{
				print_no_permission();
			}
		}
		$sql_and[] = "blog_text.bloguserid = $userinfo[userid]";
	}
	else
	{
		$userinfo = array();
		$show['entry_userinfo'] = true;
	}

	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$sql_and[] = "blog.userid = " . $vbulletin->userinfo['userid'];
	}
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $vbulletin->userinfo['userid'])
	{
		$sql_and[] = "blog.userid <> " . $vbulletin->userinfo['userid'];
	}

	$state = array('visible');
	$commentstate = array('visible');

	if (can_moderate_blog('canmoderatecomments') OR ($userinfo['userid'] AND $vbulletin->userinfo['userid'] == $userinfo['userid']))
	{
		$commentstate[] = 'moderation';
	}
	if (can_moderate_blog('canmoderateentries') OR ($userinfo['userid'] AND $vbulletin->userinfo['userid'] == $userinfo['userid']))
	{
		$state[] = 'moderation';
	}
	if (can_moderate_blog() OR ($userinfo['userid'] AND $vbulletin->userinfo['userid'] == $userinfo['userid']))
	{
		$state[] = 'deleted';
		$commentstate[] = 'deleted';
		$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog_text.blogtextid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogtextid')";
	}

	$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
	$sql_and[] = "blog.dateline <= " . TIMENOW;
	$sql_and[] = "blog.pending = 0";
	$sql_and[] = "blog_text.state IN('" . implode("', '", $commentstate) . "')";
	$sql_and[] = "blog.firstblogtextid <> blog_text.blogtextid";

	$type = '';
	if ($vbulletin->GPC['type'])
	{
		$type = $vbulletin->GPC['type'];
		switch ($vbulletin->GPC['type'])
		{
			case 'moderated':
				if ($vbulletin->userinfo['userid'] == $userinfo['userid'] OR can_moderate_blog('canmoderateentries'))
				{
					$sql_and[] = "blog_text.state = 'moderation'";
				}
				break;
			case 'deleted':
				if ($vbulletin->userinfo['userid'] == $userinfo['userid'] OR can_moderate_blog())
				{
					$sql_and[] = "blog_text.state = 'deleted'";
				}
				break;
			default:
				$type = '';
		}
	}

	if (!$userinfo)
	{
		// Limit results to 14 days when we are viewing "All Comments", from the "Find More" link on blog home
		if (!$type)
		{
			$sql_and[] = "blog_text.dateline >= " . (TIMENOW - 1209600);
		}
	}

	$selectedfilter = array(
		$type => 'selected="selected"'
	);

	if (!can_moderate_blog())
	{
		if ($vbulletin->userinfo['userid'])
		{
			if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
			{
				$having_or[] = "(options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND ignoreid IS NOT NULL)";
				$having_or[] = "(options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND buddyid IS NOT NULL)";
				$having_or[] = "(options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " AND (options_buddy & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR buddyid IS NULL) AND (options_ignore & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . " OR ignoreid IS NULL))";
			}
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

	($hook = vBulletinHook::fetch_hook('blog_comments_comments_query')) ? eval($hook) : false;

	$comment_count = 0;
	$responsebits = '';
	$pagetext_cachable = true;

	require_once(DIR . '/includes/class_bbcode_blog.php');
	require_once(DIR . '/includes/class_blog_response.php');

	$bbcode =& new vB_BbCodeParser_Blog($vbulletin, fetch_tag_list());
	$factory =& new vB_Blog_ResponseFactory($vbulletin, $bbcode, $bloginfo);

	// Add union query here so blog owners can see comments attached to deleted entries of their own
	do
	{
		if (!$vbulletin->GPC['pagenumber'])
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}
		$start = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->options['blog_commentsperpage'];
		$pagenumber = $vbulletin->GPC['pagenumber'];
		$comments = $db->query_read("
			SELECT SQL_CALC_FOUND_ROWS
				blog_text.username AS postusername, blog_text.ipaddress AS blogipaddress, blog_text.state, blog.blogid, blog_text.blogtextid, blog_text.title, blog.title AS entrytitle, blog_text.dateline, blog_text.pagetext, blog_text.allowsmilie, user.*, blog_user.title AS blogtitle,
				IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid, options_ignore, options_buddy, options_everyone, blog.userid AS blog_userid,
				blog_editlog.userid AS edit_userid, blog_editlog.dateline AS edit_dateline, blog_editlog.reason AS edit_reason, blog_editlog.username AS edit_username,
				blog.state AS blog_state, blog.firstblogtextid
			" . (!empty($having_join) ? ", ignored.relationid AS ignoreid, buddy.relationid AS buddyid" : "") . "
			" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			" . ($deljoinsql ? ",blog_deletionlog.userid AS del_userid, blog_deletionlog.username AS del_username, blog_deletionlog.reason AS del_reason" : "") . "
			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? ", blog_read.readtime AS blogread, blog_userread.readtime AS bloguserread" : "") . "
			FROM " . TABLE_PREFIX . "blog_text AS blog_text
			LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_text.blogid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog_text.userid)
			LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
			LEFT JOIN " . TABLE_PREFIX . "blog_editlog AS blog_editlog ON (blog_editlog.blogtextid = blog_text.blogtextid)
			" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? "
			LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON (blog_read.blogid = blog.blogid AND blog_read.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON (blog_userread.bloguserid = blog.userid AND blog_userread.userid = " . $vbulletin->userinfo['userid'] . ")
			" : "") . "
			$deljoinsql
			" . (!empty($having_join) ? implode("\r\n", $having_join) : "") . "
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE " . implode("\r\n\tAND ", $sql_and) . "
			" . (!empty($having_or) ? "HAVING " . implode("\r\n\tOR ", $having_or) : "") . "
			ORDER BY blog_text.dateline DESC
			LIMIT $start, $perpage
		");
		list($comment_count) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

		if ($start >= $comment_count)
		{
			$vbulletin->GPC['pagenumber'] = ceil($comment_count / $perpage);
		}
	}
	while ($start >= $comment_count AND $comment_count);

	$pagenavurl = array('do=comments');
	if ($userinfo)
	{
		$pagenavurl[] = "u=$userinfo[userid]";
	}
	if ($type)
	{
		$pagenavurl[] = "type=$type";
	}
	if ($perpage != $vbulletin->options['blog_commentsperpage'])
	{
		$pagenavurl[] = "pp=$perpage";
	}

	$pagenav = construct_page_nav(
		$vbulletin->GPC['pagenumber'],
		$perpage,
		$comment_count,
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavurl)
	);

	while ($comment = $db->fetch_array($comments))
	{
		$bloginfo = array(
			'userid'          => $comment['blog_userid'],
			'state'           => $comment['blog_state'],
			'firstblogtextid' => $comment['firstblogtextid'],
			'blogread'        => $comment['blogread'],
			'bloguserread'    => $comment['bloguserread'],
		);
		$response_handler->bloginfo =& $bloginfo;

		$response_handler =& $factory->create($comment);
		$response_handler->cachable = $pagetext_cachable;
		$response_handler->linkblog = true;
		$responsebits .= $response_handler->construct();

		if ($pagetext_cachable AND $comment['pagetexthtml'] == '')
		{
			if (!empty($saveparsed))
			{
				$saveparsed .= ',';
			}
			$saveparsed .= "($comment[blogtextid], " . intval($bloginfo['lastcomment']) . ', ' . intval($response_handler->parsed_cache['has_images']) . ", '" . $db->escape_string($response_handler->parsed_cache['text']) . "', " . intval(STYLEID) . ", " . intval(LANGUAGEID) . ")";
		}

		if ($comment['dateline'] > $displayed_dateline)
		{
			$displayed_dateline = $comment['dateline'];
		}

		if ($response_handler->inlinemod)
		{
			$inlinemodfound = true;
		}
	}

	$show['inlinemod'] = $inlinemodfound;

	if ($userinfo)
	{
		cache_ordered_categories($userinfo['userid']);
		$blogtitle =& $userinfo['blog_title'];
		if (!empty($userinfo['blog_description']))
		{
			$bbcode =& new vB_BbCodeParser_Blog($vbulletin, fetch_tag_list());
			$bbcode->set_parse_userinfo($userinfo, $userinfo['permissions']);
			$description = $bbcode->parse($userinfo['blog_description'], 'blog_user', $userinfo['blog_allowsmilie'] ? 1 : 0);
			$show['description'] = true;
		}
		else
		{
			$show['description'] = false;
		}
		$sidebar =& build_user_sidebar($userinfo, $month, $year);

		$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]"] = $blogtitle;
	}
	else
	{
		$sidebar =& build_overview_sidebar();
	}

	if ($type)
	{
		$navbits[] = $vbphrase[$type . '_comments'];
	}
	else
	{
		$navbits[] = $vbphrase['comments'];
	}

	$show['filter'] = (can_moderate_blog() OR ($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['userid'] == $userinfo['userid']));
	$show['filter_moderation'] = (can_moderate_blog('canmoderatecomments') OR $vbulletin->userinfo['userid'] == $userinfo['userid']);

	($hook = vBulletinHook::fetch_hook('blog_comments_complete')) ? eval($hook) : false;

	eval('$content = "' . fetch_template('blog_list_comments') . '";');
}

// #######################################################################
if ($_REQUEST['do'] == 'sendtofriend' OR $_POST['do'] == 'dosendtofriend')
{
	$bloginfo = verify_blog($blogid);

	if ($bloginfo['state'] != 'visible' OR $bloginfo['pending'])
	{
		print_no_permission();
	}

	if (!$vbulletin->options['enableemail'])
	{
		standard_error(fetch_error('emaildisabled'));
	}

	if (!$bloginfo OR
		(!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND $bloginfo['userid'] == $vbulletin->userinfo['userid']) OR
		(!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']) AND $bloginfo['userid'] != $vbulletin->userinfo['userid']))
	{
		print_no_permission();
	}

	$perform_floodcheck = (
		!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
		AND $vbulletin->options['emailfloodtime']
		AND $vbulletin->userinfo['userid']
	);

	if ($perform_floodcheck AND ($timepassed = TIMENOW - $vbulletin->userinfo['emailstamp']) < $vbulletin->options['emailfloodtime'])
	{
		standard_error(fetch_error('emailfloodcheck', $vbulletin->options['emailfloodtime'], ($vbulletin->options['emailfloodtime'] - $timepassed)));
	}
}

// ############################### start do send to friend ###############################
if ($_POST['do'] == 'dosendtofriend')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'sendtoname'   => TYPE_STR,
		'sendtoemail'  => TYPE_STR,
		'emailsubject' => TYPE_STR,
		'emailmessage' => TYPE_STR,
		'username'     => TYPE_STR,
		'imagestamp'   => TYPE_STR,
		'imagehash'    => TYPE_STR,
		'humanverify'  => TYPE_ARRAY,
	));

	// Values that are used in phrases or error messages
	$sendtoname =& $vbulletin->GPC['sendtoname'];
	$emailmessage =& $vbulletin->GPC['emailmessage'];
	$errors = array();

	if ($sendtoname == '' OR !is_valid_email($vbulletin->GPC['sendtoemail']) OR $vbulletin->GPC['emailsubject'] == '' OR $emailmessage == '')
	{
		$errors[] = fetch_error('requiredfields');
	}

	if ($perform_floodcheck)
	{
		require_once(DIR . '/includes/class_floodcheck.php');
		$floodcheck =& new vB_FloodCheck($vbulletin, 'user', 'emailstamp');
		$floodcheck->commit_key($vbulletin->userinfo['userid'], TIMENOW, TIMENOW - $vbulletin->options['emailfloodtime']);
		if ($floodcheck->is_flooding())
		{
			$errors[] = fetch_error('emailfloodcheck', $vbulletin->options['emailfloodtime'], $floodcheck->flood_wait());
		}
	}

	if (!$vbulletin->userinfo['userid'])
	{
		if ($show['blog_37_compatible'])
		{
			if ($vbulletin->options['hvcheck_contactus'])
			{
				require_once(DIR . '/includes/class_humanverify.php');
				$verify =& vB_HumanVerify::fetch_library($vbulletin);
				if (!$verify->verify_token($vbulletin->GPC['humanverify']))
				{
					$errors[] = fetch_error($verify->fetch_error());
				}
			}
		}
		else if ($vbulletin->options['regimagetype'])
		{
			require_once(DIR . '/includes/functions_regimage.php');
			if (!verify_regimage_hash($vbulletin->GPC['imagehash'], $vbulletin->GPC['imagestamp']))
			{
				$errors[] = fetch_error('register_imagecheck');
			}
		}
	}

	($hook = vBulletinHook::fetch_hook('blog_dosendtofriend_start')) ? eval($hook) : false;

	if ($vbulletin->GPC['username'] != '')
	{
		if ($userinfo = $db->query_first_slave("
			SELECT user.*, userfield.*
			FROM " . TABLE_PREFIX . "user AS user," . TABLE_PREFIX . "userfield AS userfield
			WHERE username='" . $db->escape_string(htmlspecialchars_uni($vbulletin->GPC['username'])) . "'
				AND user.userid = userfield.userid"
		))
		{
			$errors[] = fetch_error('usernametaken', $vbulletin->GPC['username'], $vbulletin->session->vars['sessionurl']);
		}
		else
		{
			$postusername = htmlspecialchars_uni($vbulletin->GPC['username']);
		}
	}
	else
	{
		$postusername = $vbulletin->userinfo['username'];
	}

	if (empty($errors))
	{
		eval(fetch_email_phrases('sendtofriend'));

		vbmail($vbulletin->GPC['sendtoemail'], $vbulletin->GPC['emailsubject'], $message);

		($hook = vBulletinHook::fetch_hook('blog_dosendtofriend_complete')) ? eval($hook) : false;

		$sendtoname = htmlspecialchars_uni($sendtoname);

		$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
		eval(print_standard_redirect('redirect_blog_sentemail'));
	}
	else
	{
		$_REQUEST['do'] = 'sendtofriend';
		$show['errors'] = true;
		foreach ($errors AS $errormessage)
		{
			eval('$errormessages .= "' . fetch_template('newpost_errormessage') . '";');
		}
	}
}

// ############################### start send to friend ###############################
if ($_REQUEST['do'] == 'sendtofriend')
{
	($hook = vBulletinHook::fetch_hook('blog_sendtofriend_start')) ? eval($hook) : false;

	$bloginfo['title'] = fetch_word_wrapped_string($bloginfo['title'], $vbulletin->options['blog_wordwrap']);

	if ($show['errors'])
	{
		$stf = array(
			'name'    => htmlspecialchars_uni($vbulletin->GPC['sendtoname']),
			'email'   => htmlspecialchars_uni($vbulletin->GPC['sendtoemail']),
			'title'   => htmlspecialchars_uni($vbulletin->GPC['emailsubject']),
			'message' => htmlspecialchars_uni($vbulletin->GPC['emailmessage']),
		);
	}
	else
	{
		$stf = array(
			'name'    => '',
			'email'   => '',
			'title'   => $bloginfo['title'],
			'message' => construct_phrase($vbphrase['blog_thought_might_be_interested'], $vbulletin->options['bburl'], $bloginfo['blogid'], $vbulletin->userinfo['userid'], $vbulletin->userinfo['username']),
		);
	}

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// image verification
	$imagereg = '';
	$human_verify = '';
	if (!$vbulletin->userinfo['userid'])
	{
		if ($show['blog_37_compatible'])
		{	// vBulletin 3.7.x
			if ($vbulletin->options['hvcheck_contactus'])
			{
				require_once(DIR . '/includes/class_humanverify.php');
				$verification =& vB_HumanVerify::fetch_library($vbulletin);
				$human_verify = $verification->output_token();
			}
		}
		else if ($vbulletin->options['regimagetype'])
		{	// vBulletin 3.6.x
			require_once(DIR . '/includes/functions_regimage.php');
			$imagehash = fetch_regimage_hash();
			eval('$imagereg = "' . fetch_template('imagereg') . '";');
		}
	}

	cache_ordered_categories($bloginfo['userid']);
	$sidebar =& build_user_sidebar($bloginfo);

	$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$bloginfo[userid]"] = $bloginfo['blog_title'];
	$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]" > $bloginfo['title']];
	$navbits[] = $vbphrase['email_to_friend'];

	$bloginfo['title_trimmed'] = fetch_trimmed_title($bloginfo['title']);

	($hook = vBulletinHook::fetch_hook('blog_sendtofriend_complete')) ? eval($hook) : false;

	$url =& $vbulletin->url;

	eval('$content = "' . fetch_template('blog_send_to_friend') . '";');
}

// #######################################################################
if ($_POST['do'] == 'rate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'vote'       => TYPE_UINT,
		'ajax'       => TYPE_BOOL,
		'blogid'     => TYPE_UINT,
	));

	$bloginfo = fetch_bloginfo($vbulletin->GPC['blogid']);

	if ($vbulletin->GPC['vote'] < 1 OR $vbulletin->GPC['vote'] > 5)
	{
		standard_error(fetch_error('invalidvote'));
	}

	if ($bloginfo['state'] !== 'visible')
	{
		print_no_permission();
	}

	$rated = intval(fetch_bbarray_cookie('blog_rate', $bloginfo['blogid']));

	//($hook = vBulletinHook::fetch_hook('threadrate_start')) ? eval($hook) : false;

	$update = false;
	if ($vbulletin->userinfo['userid'])
	{
		if ($rating = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "blog_rate
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND blogid = $bloginfo[blogid]
		"))
		{
			if ($vbulletin->options['votechange'])
			{
				if ($vbulletin->GPC['vote'] != $rating['vote'])
				{
					$blograte =& datamanager_init('Blog_Rate', $vbulletin, ERRTYPE_STANDARD);
					$blograte->set_info('blog', $bloginfo);
					$blograte->set_existing($rating);
					$blograte->set('vote', $vbulletin->GPC['vote']);

					//($hook = vBulletinHook::fetch_hook('threadrate_update')) ? eval($hook) : false;

					$blograte->save();
				}
				$update = true;
				if (!$vbulletin->GPC['ajax'])
				{
					$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
					eval(print_standard_redirect('redirect_blog_rate_add'));
				}
			}
			else if (!$vbulletin->GPC['ajax'])
			{
				standard_error(fetch_error('blog_rate_voted'));
			}
		}
		else
		{
			$blograte =& datamanager_init('Blog_Rate', $vbulletin, ERRTYPE_STANDARD);
			$blograte->set_info('blog', $bloginfo);
			$blograte->set('blogid', $bloginfo['blogid']);
			$blograte->set('userid', $vbulletin->userinfo['userid']);
			$blograte->set('vote', $vbulletin->GPC['vote']);

			//($hook = vBulletinHook::fetch_hook('threadrate_add')) ? eval($hook) : false;

			$blograte->save();
			$update = true;

			if (!$vbulletin->GPC['ajax'])
			{
				$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
				eval(print_standard_redirect('redirect_blog_rate_add'));
			}
		}
	}
	else
	{
		// Check for cookie on user's computer for this blogid
		if ($rated AND !$vbulletin->options['votechange'])
		{
			if (!$vbulletin->GPC['ajax'])
			{
				standard_error(fetch_error('blog_rate_voted'));
			}
		}
		else
		{
			// Check for entry in Database for this Ip Addr/blogid
			if ($rating = $db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "blog_rate
				WHERE ipaddress = '" . $db->escape_string(IPADDRESS) . "'
					AND blogid = $bloginfo[blogid]
			"))
			{
				if ($vbulletin->options['votechange'])
				{
					if ($vbulletin->GPC['vote'] != $rating['vote'])
					{
						$blograte =& datamanager_init('Blog_Rate', $vbulletin, ERRTYPE_STANDARD);
						$blograte->set_info('blog', $bloginfo);
						$blograte->set_existing($rating);
						$blograte->set('vote', $vbulletin->GPC['vote']);

						//($hook = vBulletinHook::fetch_hook('threadrate_update')) ? eval($hook) : false;

						$blograte->save();
					}
					$update = true;

					if (!$vbulletin->GPC['ajax'])
					{
						$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
						eval(print_standard_redirect('redirect_blog_rate_add'));
					}
				}
				else if (!$vbulletin->GPC['ajax'])
				{
					set_bbarray_cookie('blog_rate', $rating['blogid'], $rating['vote'], 1);
					standard_error(fetch_error('blog_rate_voted'));
				}
			}
			else
			{
				$blograte =& datamanager_init('Blog_Rate', $vbulletin, ERRTYPE_STANDARD);
				$blograte->set_info('blog', $bloginfo);
				$blograte->set('blogid', $bloginfo['blogid']);
				$blograte->set('userid', 0);
				$blograte->set('vote', $vbulletin->GPC['vote']);
				$blograte->set('ipaddress', IPADDRESS);

				//($hook = vBulletinHook::fetch_hook('threadrate_add')) ? eval($hook) : false;

				$blograte->save();
				$update = true;

				if (!$vbulletin->GPC['ajax'])
				{
					$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
					eval(print_standard_redirect('redirect_blog_rate_add'));
				}
			}
		}
	}

	require_once(DIR . '/includes/class_xml.php');
	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	$xml->add_group('threadrating');
	if ($update)
	{
		$blog = $db->query_first_slave("
			SELECT ratingtotal, ratingnum
			FROM " . TABLE_PREFIX . "blog
			WHERE blogid = $bloginfo[blogid]
		");

		if ($blog['ratingnum'] > 0 AND $blog['ratingnum'] >= $vbulletin->options['vbblog_ratingpost'])
		{	// Show Voteavg
			$blog['ratingavg'] = vb_number_format($blog['ratingtotal'] / $blog['ratingnum'], 2);
			$blog['rating'] = intval(round($blog['ratingtotal'] / $blog['ratingnum']));
			$xml->add_tag('voteavg', "<img class=\"inlineimg\" src=\"$stylevar[imgdir_rating]/rating_$blog[rating].gif\" alt=\"" . construct_phrase($vbphrase['rating_x_votes_y_average'], $blog['ratingnum'], $blog['ratingavg']) . "\" border=\"0\" />");
		}
		else
		{
			$xml->add_tag('voteavg', '');
		}

		if (!function_exists('fetch_phrase'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}
		$xml->add_tag('message', fetch_phrase('redirect_blog_rate_add', 'frontredirect', 'redirect_'));
	}
	else	// Already voted error...
	{
		if (!empty($rating['blogid']))
		{
			set_bbarray_cookie('blog_rate', $rating['blogid'], $rating['vote'], 1);
		}
		$xml->add_tag('error', fetch_error('blog_rate_voted'));
	}
	$xml->close_group();
	$xml->print_xml();
}

// ############################### start random blog ###############################
if ($_REQUEST['do'] == 'random')
{
	$sql = array(
		"state = 'visible'",
		"dateline <= " . TIMENOW,
		"blog.pending = 0",
	);

	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		$sql[] = "blog.userid <> " . $vbulletin->userinfo['userid'];
	}
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$sql[] = "blog.userid = " . $vbulletin->userinfo['userid'];
	}

	if ($coventry = fetch_coventry('string') AND !can_moderate_blog())
	{
		$sql[] = "blog.userid NOT IN ($coventry)";
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
			$sql[] = "(" . implode(" OR ", $userlist_sql) . ")";

			$sql1join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS buddy ON (buddy.userid = blog.userid AND buddy.relationid = " . $vbulletin->userinfo['userid'] . " AND buddy.type = 'buddy')";
			$sql1join[] = "LEFT JOIN " . TABLE_PREFIX . "userlist AS ignored ON (ignored.userid = blog.userid AND ignored.relationid = " . $vbulletin->userinfo['userid'] . " AND ignored.type = 'ignore')";
		}
		else
		{
			$sql[] = "options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'];
		}
	}

	($hook = vBulletinHook::fetch_hook('blog_random_query')) ? eval($hook) : false;

	$blog = $db->query_first_slave("
		SELECT * FROM " . TABLE_PREFIX . "blog AS blog
		" . (!empty($sql1join) ? implode("\r\n", $sql1join) : "") . "
		WHERE " . implode("\r\nAND ", $sql) . "
		ORDER BY RAND() LIMIT 1
	");

	if ($blog)
	{
		exec_header_redirect('blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$blog[blogid]");
	}
	else
	{
		standard_error(fetch_error('blog_no_blogs'));
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'viewip')
{

	if (!can_moderate_blog('canviewips'))
	{
		print_no_permission();
	}

	if ($blogtextid)
	{
		$blogtextinfo = fetch_blog_textinfo($vbulletin->GPC['blogtextid']);
		if ($blogtextinfo === false)
		{
			standard_error(fetch_error('invalidid', $vbphrase['comment'], $vbulletin->options['contactuslink']));
		}
		$ipaddress = ($blogtextinfo['ipaddress'] ? htmlspecialchars_uni(long2ip($blogtextinfo['ipaddress'])) : '');
	}
	else
	{
		$bloginfo = verify_blog($blogid);
		$ipaddress = ($bloginfo['blogipaddress'] ? htmlspecialchars_uni(long2ip($bloginfo['blogipaddress'])) : '');
	}

	$hostname = htmlspecialchars_uni(gethostbyaddr($ipaddress));

	($hook = vBulletinHook::fetch_hook('blog_viewip_complete')) ? eval($hook) : false;

	standard_error(fetch_error('thread_displayip', $ipaddress, $hostname, '', 0));
}

// #######################################################################
if ($_REQUEST['do'] == 'markread')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'userid'   => TYPE_UINT,
		'readhash' => TYPE_STR
	));

	// verify the userid exists, don't want useless entries in our table.
	if (!($userinfo = fetch_userinfo($vbulletin->GPC['userid'])))
	{
		standard_error(fetch_error('invalidid', $vbphrase['user'], $vbulletin->options['contactuslink']));
	}

	if ($vbulletin->userinfo['userid'] != 0 AND $vbulletin->GPC['readhash'] != $vbulletin->userinfo['logouthash'])
	{
		standard_error(fetch_error('blog_markread_error', $vbulletin->session->vars['sessionurl'], $userinfo['userid'], $vbulletin->userinfo['logouthash'], $userinfo['username']));
	}

	mark_user_blog_read($userinfo['userid'], $vbulletin->userinfo['userid'], TIMENOW);

	require_once(DIR . '/includes/functions_login.php');
	$vbulletin->url = fetch_replaced_session_url($vbulletin->url);
	if (strpos($vbulletin->url, 'do=markread') !== false)
	{
		$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$userinfo[userid]";
	}
	eval(print_standard_redirect('blog_markread', true, true));
}

($hook = vBulletinHook::fetch_hook('blog_complete')) ? eval($hook) : false;

// build navbar
if (empty($navbits))
{
	$navbits[] = $vbphrase['blogs'];
}
else
{
	$navbits = array_merge(array('blog.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['blogs']), $navbits);
}
$navbits = construct_navbits($navbits);

eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('BLOG') . '");');

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25228 $
|| ####################################################################
\*======================================================================*/
?>