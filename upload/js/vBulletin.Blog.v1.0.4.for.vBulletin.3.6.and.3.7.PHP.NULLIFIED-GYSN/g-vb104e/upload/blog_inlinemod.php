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
define('THIS_SCRIPT', 'blog_inlinemod');
define('VBBLOG_PERMS', 1);
define('VBBLOG_STYLE', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'vbblogglobal',
	'threadmanage',
	'postbit',
);

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'BLOG',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'deletetrackback'	=> array(
		'blog_inlinemod_delete_trackbacks',
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_sidebar_generic',
	),
	'deletecomment'   => array(
		'blog_inlinemod_delete_comments',
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_sidebar_generic',
	),
	'deleteentry'     => array(
		'blog_inlinemod_delete_entries',
		'blog_archive_link_li',
		'blog_sidebar_category_link',
		'blog_sidebar_comment_link',
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_sidebar_user',
		'blog_sidebar_generic',
	),
	'deletepcomment'   => array(
		'blog_inlinemod_delete_profile_comments',
	),
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_init.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$itemlimit = 200;

// This is a list of ids that were checked on the page we submitted from
$vbulletin->input->clean_array_gpc('p', array(
	'trackbacklist' => TYPE_ARRAY_KEYS_INT,
	'commentlist'   => TYPE_ARRAY_KEYS_INT,
	'pcommentlist'  => TYPE_ARRAY_KEYS_INT,
	'bloglist'      => TYPE_ARRAY_KEYS_INT,
	'userid'        => TYPE_UINT,
));

// If we have javascript, all ids should be in here
$vbulletin->input->clean_array_gpc('c', array(
	'vbulletin_inlinetrackback' => TYPE_STR,
	'vbulletin_inlinecomment'   => TYPE_STR,
	'vbulletin_inlineblog'      => TYPE_STR,
	'vbulletin_inlinepcomment'  => TYPE_STR,
));

// Combine ids sent from the form and what we have in the cookie
if (!empty($vbulletin->GPC['vbulletin_inlinetrackback']))
{
	$trackbacklist = explode('-', $vbulletin->GPC['vbulletin_inlinetrackback']);
	$trackbacklist = $vbulletin->input->clean($trackbacklist, TYPE_ARRAY_UINT);

	$vbulletin->GPC['trackbacklist'] = array_unique(array_merge($trackbacklist, $vbulletin->GPC['trackbacklist']));
}

if (!empty($vbulletin->GPC['vbulletin_inlinecomment']))
{
	$commentlist = explode('-', $vbulletin->GPC['vbulletin_inlinecomment']);
	$commentlist = $vbulletin->input->clean($commentlist, TYPE_ARRAY_UINT);

	$vbulletin->GPC['commentlist'] = array_unique(array_merge($commentlist, $vbulletin->GPC['commentlist']));
}

if (!empty($vbulletin->GPC['vbulletin_inlineblog']))
{
	$bloglist = explode('-', $vbulletin->GPC['vbulletin_inlineblog']);
	$bloglist = $vbulletin->input->clean($bloglist, TYPE_ARRAY_UINT);

	$vbulletin->GPC['bloglist'] = array_unique(array_merge($bloglist, $vbulletin->GPC['bloglist']));
}

if (!empty($vbulletin->GPC['vbulletin_inlinepcomment']))
{
	$pcommentlist = explode('-', $vbulletin->GPC['vbulletin_inlinepcomment']);
	$pcommentlist = $vbulletin->input->clean($pcommentlist, TYPE_ARRAY_UINT);

	$vbulletin->GPC['pcommentlist'] = array_unique(array_merge($pcommentlist, $vbulletin->GPC['pcommentlist']));
}

if (!$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

switch ($_POST['do'])
{
	// ######################### POSTS ############################
	case 'deleteentry':
	case 'approveentry':
	case 'unapproveentry':
	case 'undeleteentry':

		if (empty($vbulletin->GPC['bloglist']))
		{
			standard_error(fetch_error('you_did_not_select_any_valid_entries'));
		}

		if (count($vbulletin->GPC['bloglist']) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_entries', $itemlimit));
		}

		if ($vbulletin->GPC['userid'])
		{
			$userinfo = fetch_userinfo($vbulletin->GPC['userid'], 1);
		}

		$blogids = implode(', ', $vbulletin->GPC['bloglist']);
		break;

	case 'dodeleteentry':

		$vbulletin->input->clean_array_gpc('p', array(
			'blogids' => TYPE_STR,
		));
		$blogids = explode(',', $vbulletin->GPC['blogids']);
		$blogids = $vbulletin->input->clean($blogids, TYPE_ARRAY_UINT);

		if (count($blogids) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_entries', $itemlimit));
		}
		break;

	// ######################### COMMENTS ############################
	case 'deletecomment':
	case 'approvecomment':
	case 'unapprovecomment':
	case 'undeletecomment':

		if (empty($vbulletin->GPC['commentlist']))
		{
			standard_error(fetch_error('you_did_not_select_any_valid_comments'));
		}

		if (count($vbulletin->GPC['commentlist']) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_comments', $itemlimit));
		}

		if ($vbulletin->GPC['userid'])
		{
			$userinfo = fetch_userinfo($vbulletin->GPC['userid'], 1);
		}

		$blogtextids = implode(', ', $vbulletin->GPC['commentlist']);
		break;

	case 'dodeletecomment':

		$vbulletin->input->clean_array_gpc('p', array(
			'blogtextids' => TYPE_STR,
		));
		$blogtextids = explode(',', $vbulletin->GPC['blogtextids']);
		$blogtextids = $vbulletin->input->clean($blogtextids, TYPE_ARRAY_UINT);

		if (count($blogtextids) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_comments', $itemlimit));
		}
		break;

	// ######################### PROFILE COMMENTS ############################
	case 'deletepcomment':
	case 'approvepcomment':
	case 'unapprovepcomment':
	case 'undeletepcomment':

		if (empty($vbulletin->GPC['pcommentlist']))
		{
			standard_error(fetch_error('you_did_not_select_any_valid_comments'));
		}

		if (count($vbulletin->GPC['pcommentlist']) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_comments', $itemlimit));
		}

		if ($vbulletin->GPC['userid'])
		{
			$userinfo = fetch_userinfo($vbulletin->GPC['userid'], 1);
		}

		$commentids = implode(', ', $vbulletin->GPC['pcommentlist']);
		break;

	case 'dodeletepcomment':

		$vbulletin->input->clean_array_gpc('p', array(
			'commentids' => TYPE_STR,
		));
		$commentids = explode(',', $vbulletin->GPC['commentids']);
		$commentids = $vbulletin->input->clean($commentids, TYPE_ARRAY_UINT);

		if (count($commentids) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_comments', $itemlimit));
		}
		break;

	// ######################### TRACKBACKS ############################
	case 'deletetrackback':
	case 'approvetrackback':
	case 'unapprovetrackback':

		if (empty($vbulletin->GPC['trackbacklist']))
		{
			standard_error(fetch_error('you_did_not_select_any_valid_trackbacks'));
		}

		if (count($vbulletin->GPC['trackbacklist']) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_trackbacks', $itemlimit));
		}

		if ($vbulletin->GPC['userid'])
		{
			$userinfo = fetch_userinfo($vbulletin->GPC['userid'], 1);
		}

		$trackbackids = implode(', ', $vbulletin->GPC['trackbacklist']);
		break;

	case 'dodeletetrackback':

		$vbulletin->input->clean_array_gpc('p', array(
			'trackbackids' => TYPE_STR,
		));
		$trackbackids = explode(',', $vbulletin->GPC['trackbackids']);
		$trackbackids = $vbulletin->input->clean($trackbackids, TYPE_ARRAY_UINT);

		if (count($trackbackids) > $itemlimit)
		{
			standard_error(fetch_error('you_are_limited_to_working_with_x_trackbacks', $itemlimit));
		}
		break;

	case 'cleartrackback':
	case 'clearpcomment':
	case 'clearcomment':
	case 'clearentry':

		break;

	default:
		$handled_do = false;
		($hook = vBulletinHook::fetch_hook('blog_inlinemod_action_switch')) ? eval($hook) : false;
		if (!$handled_do)
		{
			standard_error(fetch_error('invalid_action'));
		}
}

// set forceredirect for IIS
$forceredirect = (strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false);

$userarray = array();
$blogarray = array();
$trackbackarray = array();
$commentarray = array();
$bloglist = array();
$userlist = array();

if ($_POST['do'] == 'clearcomment')
{
	setcookie('vbulletin_inlinecomment', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_clearcomment')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_commentlist_cleared', true, $forceredirect));
}

if ($_POST['do'] == 'clearpcomment')
{
	setcookie('vbulletin_inlinepcomment', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_clearpcomment')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_commentlist_cleared', true, $forceredirect));
}

if ($_POST['do'] == 'cleartrackback')
{
	setcookie('vbulletin_inlinetrackback', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_cleartrackback')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_trackbacklist_cleared', true, $forceredirect));
}

if ($_POST['do'] == 'clearentry')
{
	setcookie('vbulletin_inlineblog', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('inlinemod_clearblog')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_entrylist_cleared', true, $forceredirect));
}

if ($_POST['do'] == 'approvetrackback' OR $_POST['do'] == 'unapprovetrackback')
{
	$insertrecords = array();

	$approve = $_POST['do'] == 'approvetrackback' ? true : false;

	// Validate Trackbacks
	$trackbacks = $db->query_read_slave("
		SELECT blogtrackbackid, blog_trackback.state, blog_trackback.blogid, blog_trackback.userid,
			blog.state AS blog_state, blog.userid AS blog_userid, blog.pending
		FROM " . TABLE_PREFIX . "blog_trackback AS blog_trackback
		LEFT JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
		WHERE blogtrackbackid IN ($trackbackids)
		 AND blog_trackback.state = '" . ($approve ? 'moderation' : 'visible') . "'
	");
	while ($trackback = $db->fetch_array($trackbacks))
	{
		// Check permissions.....
		if (($trackback['blog_userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($trackback['blog_userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}
		$canmanage = ($trackback['blog_userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']);
		$canmoderatecomments = (can_moderate_blog('canmoderatecomments') OR $canmanage);

		if ($trackback['blog_state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($trackback['blog_state'] == 'deleted' AND !can_moderate_blog('candeleteentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}
		else if ($trackback['blog_state'] == 'draft' OR $trackback['pending'])
		{
			standard_error(fetch_error('you_can_not_manage_items_within_pending_or_draft_entries'));
		}
		else if (!$canmoderatecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_comments'));
		}

		$trackbackarray["$trackback[blogtrackbackid]"] = $trackback;
		$bloglist["$trackback[blogid]"] = true;

		if (!$approve)
		{
			$insertrecords[] = "($trackback[blogtrackbackid], 'blogtrackbackid', " . TIMENOW . ")";
		}
	}

	if (empty($trackbackarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_trackbacks'));
	}

	// Set trackback state
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_trackback
		SET state = '" . ($approve ? 'visible' : 'moderation') . "'
		WHERE blogtrackbackid IN (" . implode(',', array_keys($trackbackarray)) . ")
	");

	if ($approve)
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_moderation
			WHERE primaryid IN(" . implode(',', array_keys($trackbackarray)) . ")
				AND type = 'blogtrackback'
		");
	}
	else
	{
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "blog_moderation
				(primaryid, type, dateline)
			VALUES
				" . implode(',', $insertrecords) . "
		");
	}

	$modlog = array();
	foreach(array_keys($trackbackarray) AS $blogtrackbackid)
	{
		$modlog[] = array(
			'userid'   =>& $vbulletin->userinfo['userid'],
			'id1'      =>& $trackbackarray["$blogtrackbackid"]['blog_userid'],
			'id2'      =>& $trackbackarray["$blogtrackbackid"]['blogid'],
			'id5'      =>  $blogtrackbackid,
		);
	}

	require_once(DIR . '/includes/blog_functions_log_error.php');
	blog_moderator_action($modlog, $approve ? 'trackback_approved' : 'trackback_unapproved');

	foreach (array_keys($bloglist) AS $blogid)
	{
		build_blog_entry_counters($blogid);
	}

	setcookie('vbulletin_inlinetrackback', '', TIMENOW - 3600, '/');

	// hook

	if ($approve)
	{
		eval(print_standard_redirect('redirect_inline_approvedtrackbacks', true, $forceredirect));
	}
	else
	{
		eval(print_standard_redirect('redirect_inline_unapprovedtrackbacks', true, $forceredirect));
	}
}

if ($_POST['do'] == 'approvecomment' OR $_POST['do'] == 'unapprovecomment')
{
	$insertrecords = array();

	$approve = $_POST['do'] == 'approvecomment' ? true : false;

	// Validate records
	$comments = $db->query_read_slave("
		SELECT blog_text.blogtextid, blog_text.state, blog_text.blogid, blog_text.userid, blog_text.dateline,
			blog.state AS blog_state, blog.userid AS blog_userid,
			blog_user.lastcomment, blog_user.lastblogtextid, blog.pending
		FROM " . TABLE_PREFIX . "blog_text AS blog_text
		LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_text.blogid)
		LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
		WHERE blogtextid IN ($blogtextids)
		 AND blog_text.state IN (" . ($approve ? "'moderation'" : "'visible', 'deleted'") . ")
		 AND blogtextid <> firstblogtextid
	");
	while ($comment = $db->fetch_array($comments))
	{
		// Check permissions.....
		if (($comment['blog_userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($comment['blog_userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}
		$canmanage = ($comment['blog_userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']);
		$canmoderatecomments = (can_moderate_blog('canmoderatecomments') OR $canmanage);
		$candeletecomments = (can_moderate_blog('candeletecomments') OR $canmanage);

		if ($comment['blog_state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($comment['blog_state'] == 'deleted' AND !can_moderate_blog('candeleteentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}
		else if ($comment['state'] == 'deleted' AND !$candeletecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_comments'));
		}
		else if (!$canmoderatecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_moderate_comments'));
		}
		else if (($comment['blog_state'] == 'draft' OR $comment['pending']) AND $vbulletin->userinfo['userid'] != $comment['blog_userid'])
		{
			standard_error(fetch_error('you_can_not_manage_items_within_pending_or_draft_entries'));
		}

		$commentarray["$comment[blogtextid]"] = $comment;
		$bloglist["$comment[blogid]"] = true;
		$userlist["$comment[blog_userid]"] = true;

		if (!$approve)
		{
			$insertrecords[] = "($comment[blogtextid], 'blogtextid', " . TIMENOW . ")";
		}
	}

	if (empty($commentarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_comments'));
	}

	// Set comment state
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_text
		SET state = '" . ($approve ? 'visible' : 'moderation') . "'
		WHERE blogtextid IN (" . implode(',', array_keys($commentarray)) . ")
	");

	if ($approve)
	{
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_moderation
			WHERE primaryid IN(" . implode(',', array_keys($commentarray)) . ")
				AND type = 'blogtextid'
		");
	}
	else	// Unapprove
	{
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "blog_moderation
				(primaryid, type, dateline)
			VALUES
				" . implode(',', $insertrecords) . "
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_deletionlog
			WHERE type = 'blogtextid' AND
				primaryid IN(" . implode(',', array_keys($commentarray)) . ")
		");
	}

	// Logging?

	foreach (array_keys($bloglist) AS $blogid)
	{
		build_blog_entry_counters($blogid);
	}

	foreach (array_keys($userlist) AS $userid)
	{
		build_blog_user_counters($userid);
	}

	setcookie('vbulletin_inlinecomment', '', TIMENOW - 3600, '/');

	// hook

	if ($approve)
	{
		eval(print_standard_redirect('redirect_inline_approvedcomments', true, $forceredirect));
	}
	else
	{
		eval(print_standard_redirect('redirect_inline_unapprovedcomments', true, $forceredirect));
	}
}

if ($_POST['do'] == 'approveentry' OR $_POST['do'] == 'unapproveentry')
{
	$insertrecords = array();

	$approve = $_POST['do'] == 'approveentry' ? true : false;
	$visibleposts = array();
	$invisibleposts = array();

	// Validate records
	$posts = $db->query_read_slave("
		SELECT blogid, userid, state, pending
		FROM " . TABLE_PREFIX . "blog AS blog
		WHERE blogid IN ($blogids)
		 AND state IN (" . ($approve ? "'moderation'" : "'visible', 'deleted'") . ")
	");
	while ($post = $db->fetch_array($posts))
	{
		// Check permissions.....
		if (($post['userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($post['userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}

		if (!can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($post['state'] == 'deleted' AND !can_moderate_blog('candeleteentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}
		else if ($post['pending'] AND $vbulletin->userinfo['userid'] != $post['userid'])
		{
			standard_error(fetch_error('you_can_not_manage_other_users_pending_posts'));
		}

		$blogarray["$post[blogid]"] = $post;
		if ($post['userid'])
		{
			if (!empty($userlist["$post[userid]"]))
			{
				$userlist["$post[userid]"]++;
			}
			else
			{
				$userlist["$post[userid]"] = 1;
			}
		}

		if (!$approve)
		{
			$insertrecords[] = "($post[blogid], 'blogid', " . TIMENOW . ")";
		}

		if ($post['state'] == 'visible')
		{
			$visibleposts[] = $post['blogid'];
		}
		else
		{
			$invisibleposts[] = $post['blogid'];
		}
	}

	if (empty($blogarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_entries'));
	}

	if (!empty($userlist))
	{
		$bycount = array();
		foreach ($userlist AS $userid => $total)
		{
			$bycount["$total"][] = $userid;
		}

		$casesql = array();
		foreach ($bycount AS $total => $userids)
		{
			$casesql[] = " WHEN bloguserid IN (" . implode(',', $userids) . ") THEN $total";
		}

		if (!empty($casesql))
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "blog_user
				SET entries = entries " . ($approve ? "+" : "-") . "
				CASE
					" . implode("\n", $casesql) . "
					ELSE 0
				END
				WHERE bloguserid IN (" . implode(',', array_keys($userlist)) . ")
			");
		}
	}

	if ($approve)
	{
		// Set post state
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog AS blog
			SET blog.state = 'visible'
			WHERE blog.blogid IN (" . implode(',', array_keys($blogarray)) . ")
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_moderation
			WHERE primaryid IN(" . implode(',', array_keys($blogarray)) . ")
				AND type = 'blogid'
		");
	}
	else
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog AS blog
			SET blog.state = 'moderation'
			WHERE blog.blogid IN (" . implode(',', array_keys($blogarray)) . ")
		");

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "blog_moderation
				(primaryid, type, dateline)
			VALUES
				" . implode(',', $insertrecords) . "
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_deletionlog
			WHERE type = 'blogid' AND
				primaryid IN(" . implode(',', array_keys($blogarray)) . ")
		");
	}

	build_category_counters_mass(array_keys($userlist));

	foreach (array_keys($blogarray) AS $blogid)
	{
		build_blog_entry_counters($blogid);
		$modlog[] = array(
			'userid' =>& $vbulletin->userinfo['userid'],
			'id1'    =>& $blogarray["$blogid"]['userid'],
			'id2'    =>  $blogid,
		);
	}

	require_once(DIR . '/includes/blog_functions_log_error.php');
	blog_moderator_action($modlog, $approve ? 'blogentry_approved' : 'blogentry_unapproved');

	foreach(array_keys($userlist) AS $userid)
	{
		build_blog_user_counters($userid);
	}
	build_blog_stats();
	setcookie('vbulletin_inlineblog', '', TIMENOW - 3600, '/');

	// hook

	if ($approve)
	{
		eval(print_standard_redirect('redirect_inline_approvedposts', true, $forceredirect));
	}
	else
	{
		eval(print_standard_redirect('redirect_inline_unapprovedposts', true, $forceredirect));
	}
}

if ($_POST['do'] == 'deletetrackback')
{
	// Trackbacks might need a soft deletion option

	// Validate Trackbacks
	$trackbacks = $db->query_read_slave("
		SELECT blogtrackbackid, blog_trackback.state, blog_trackback.blogid, blog_trackback.userid,
		blog.state AS blog_state, blog.userid AS blog_userid, pending
		FROM " . TABLE_PREFIX . "blog_trackback AS blog_trackback
		LEFT JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
		WHERE blogtrackbackid IN ($trackbackids)
	");
	while ($trackback = $db->fetch_array($trackbacks))
	{
		// Check permissions.....
		// Since there currently isn't a soft delete option for trackbacks, a mod must have 'canremovecomments' permission to delete them
		if (($trackback['blog_userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($trackback['blog_userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}
		$canmanage = ($trackback['blog_userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']);
		$canmoderatecomments = (can_moderate_blog('canmoderatecomments') OR $canmanage);
		$candeletecomments = (can_moderate_blog('canremovecomments') OR $canmanage);

		if ($trackback['blog_state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($trackback['blog_state'] == 'deleted' AND !can_moderate_blog('candeleteentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}
		else if (($trackback['blog_state'] == 'draft' OR $trackback['pending']) AND $vbulletin->userinfo['userid'] != $trackback['blog_userid'])
		{
			standard_error(fetch_error('you_can_not_manage_items_within_pending_or_draft_entries'));
		}
		else if ($trackback['state'] == 'moderation' AND !$canmoderatecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_comments'));
		}
		else if (!$candeletecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_delete_comments'));
		}

		$trackbackarray["$trackback[blogtrackbackid]"] = $trackback;
		$bloglist["$trackback[blogid]"] = true;
	}

	if (empty($trackbackarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_trackbacks'));
	}

	$trackbackcount = count($trackbackarray);
	$blogcount = count($bloglist);

	// hook
	// draw navbar

	$navbits = array();

	$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]"] = $bloginfo['title'];
	$navbits[''] = $vbphrase['delete_trackbacks'];

	$url =& $vbulletin->url;
	eval('$content = "' . fetch_template('blog_inlinemod_delete_trackbacks') . '";');
}

if ($_POST['do'] == 'dodeletetrackback')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'pinghistory' => TYPE_BOOL,
	));

	// Validate Trackbacks
	$trackbacks = $db->query_read_slave("
		SELECT blogtrackbackid, blog_trackback.state, blog_trackback.blogid, blog_trackback.userid, url,
		blog.state AS blog_state, blog.userid AS blog_userid, pending
		FROM " . TABLE_PREFIX . "blog_trackback AS blog_trackback
		LEFT JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
		WHERE blogtrackbackid IN (" . implode(',', $trackbackids) . ")
	");
	while ($trackback = $db->fetch_array($trackbacks))
	{
		// Check permissions.....
		if (($trackback['blog_userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($trackback['blog_userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}
		$canmanage = ($trackback['blog_userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']);
		$canmoderatecomments = (can_moderate_blog('canmoderatecomments') OR $canmanage);
		$candeletecomments = (can_moderate_blog('canremovecomments') OR $canmanage);

		if ($trackback['blog_state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($trackback['blog_state'] == 'deleted' AND !can_moderate_blog('candeleteentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}
		else if (($trackback['blog_state'] == 'draft' OR $trackback['pending']) AND $vbulletin->userinfo['userid'] != $trackback['blog_userid'])
		{
			standard_error(fetch_error('you_can_not_manage_items_within_pending_or_draft_entries'));
		}
		else if ($trackback['state'] == 'moderation' AND !$canmoderatecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_comments'));
		}
		else if (!$candeletecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_delete_comments'));
		}

		$trackbackarray["$trackback[blogtrackbackid]"] = $trackback;
		$bloglist["$trackback[blogid]"] = true;
	}

	if (empty($trackbackarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_trackbacks'));
	}

	foreach($trackbackarray AS $trackbackid => $trackback)
	{
		$dataman =& datamanager_init('Blog_Trackback', $vbulletin, ERRTYPE_SILENT);
		$dataman->set_existing($trackback);
		$dataman->set_info('skip_build_blog_counters', true);
		if ($vbulletin->GPC['pinghistory'])
		{
			$dataman->set_info('delete_ping_history', true);
		}
		$dataman->delete();
		unset($dataman);
	}

	foreach(array_keys($bloglist) AS $blogid)
	{
		build_blog_entry_counters($blogid);
	}


	// empty cookie
	setcookie('vbulletin_inlinetrackback', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('blog_inlinemod_dodeletetrackbacks')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_deletedtrackbacks', true, $forceredirect));
}

if ($_POST['do'] == 'deletecomment')
{
	$show['removecomments'] = false;
	$show['deletecomments'] = false;
	$show['deleteoption'] = false;
	$checked = array('delete' => 'checked="checked"');

	// Validate Comments
	$comments = $db->query_read_slave("
		SELECT blogtextid, blog_text.state, blog_text.blogid, blog_text.userid,
			blog.state AS blog_state, blog.userid AS blog_userid, pending
		FROM " . TABLE_PREFIX . "blog_text AS blog_text
		LEFT JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
		WHERE blogtextid IN ($blogtextids)
			AND blogtextid <> firstblogtextid
	");
	while ($comment = $db->fetch_array($comments))
	{
		// Check permissions.....
		if (($comment['blog_userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($comment['blog_userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}
		$canmanage = ($comment['blog_userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']);
		$canmoderatecomments = (can_moderate_blog('canmoderatecomments') OR $canmanage);
		$candeletecomments = (can_moderate_blog('candeletecomments') OR $canmanage OR ($comment['state'] == 'visible' AND $comment['userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_candeleteowncomment']));
		$canremovecomments = (can_moderate_blog('canremovecomments'));

		if ($comment['blog_state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($comment['blog_state'] == 'deleted' AND !can_moderate_blog('candeleteentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}
		else if (($comment['pending'] OR $comment['blog_state'] == 'draft') AND $vbulletin->userinfo['userid'] != $comment['blog_userid'])
		{
			standard_error(fetch_error('you_can_not_manage_items_within_pending_or_draft_entries'));
		}
		else if ($comment['state'] == 'moderation' AND !$canmoderatecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_comments'));
		}
		else if ($comment['state'] == 'deleted' AND !$candeletecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_comments'));
		}
		else
		{
			if ($candeletecomments)
			{
				$show['deletecomments'] = true;
			}
			if ($canremovecomments)
			{
				$show['removecomments'] = true;
				if (!$candeletecomments)
				{
					$checked = array('remove' => 'checked="checked"');
				}
			}

			if (!$candeletecomments AND !$canremovecomments)
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_comments'));
			}
			else if ($candeletecomments AND $canremovecomments)
			{
				$show['deleteoption'] = true;
			}
		}

		$commentarray["$comment[blogtextid]"] = $comment;
		$bloglist["$comment[blogid]"] = true;
	}

	if (empty($commentarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_comments'));
	}

	$commentcount = count($commentarray);
	$blogcount = count($bloglist);

	// hook
	// draw navbar

	$navbits = array(
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]" => $bloginfo['title'],
		'' => $vbphrase['delete_comments']
	);

	$url =& $vbulletin->url;
	eval('$content = "' . fetch_template('blog_inlinemod_delete_comments') . '";');
}

if ($_POST['do'] == 'dodeletecomment')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'   => TYPE_UINT, // 1 - Soft Deletion, 2 - Physically Remove
		'deletereason' => TYPE_NOHTMLCOND,
	));

	$physicaldel = ($vbulletin->GPC['deletetype'] == 2) ? true : false;

	// Validate Comments
	$comments = $db->query_read_slave("
		SELECT blogtextid, blog_text.state, blog_text.blogid, blog_text.userid, blog.pending, blog_text.title,
			blog.state AS blog_state, blog.userid AS blog_userid, blog_user.lastcomment, blog_user.lastblogtextid,
			user.username
		FROM " . TABLE_PREFIX . "blog_text AS blog_text
		LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_text.blogid)
		LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog_text.userid = user.userid)
		WHERE blogtextid IN (" . implode(',', $blogtextids) . ")
			AND blogtextid <> firstblogtextid
	");
	while ($comment = $db->fetch_array($comments))
	{
		// Check permissions.....
		if (($comment['blog_userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($comment['blog_userid'] == !($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}
		$canmanage = ($comment['blog_userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']);
		$canmoderatecomments = (can_moderate_blog('canmoderatecomments') OR $canmanage);
		$candeletecomments = (can_moderate_blog('candeletecomments') OR $canmanage OR ($comment['state'] == 'visible' AND $comment['userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_candeleteowncomment']));
		$canremovecomments = (can_moderate_blog('canremovecomments'));

		if ($comment['blog_state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($comment['blog_state'] == 'deleted' AND !can_moderate_blog('candeleteentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}
		else if (($comment['pending'] OR $comment['blog_state'] == 'draft') AND $vbulletin->userinfo['userid'] != $comment['blog_userid'])
		{
			standard_error(fetch_error('you_can_not_manage_items_within_pending_or_draft_entries'));
		}
		else if ($comment['state'] == 'moderation' AND !$canmoderatecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_comments'));
		}
		else if ($comment['state'] == 'deleted' AND !$candeletecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_comments'));
		}
		else
		{
			if (($physicaldel AND !$canremovecomments) OR (!$physicaldel AND !$candeletecomments))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_delete_comments'));
			}
		}

		$commentarray["$comment[blogtextid]"] = $comment;
		$bloglist["$comment[blogid]"] = true;
		$userlist["$comment[blog_userid]"] = true;
	}

	if (empty($commentarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_comments'));
	}

	foreach($commentarray AS $blogtextid => $comment)
	{
		$dataman =& datamanager_init('BlogText', $vbulletin, ERRTYPE_SILENT, 'blog');
		$dataman->set_existing($comment);
		$dataman->set_info('skip_build_blog_counters', true);
		$dataman->set_info('hard_delete', $physicaldel);
		$dataman->set_info('reason', $vbulletin->GPC['deletereason']);
		$dataman->delete();
		unset($dataman);
	}

	foreach(array_keys($bloglist) AS $blogid)
	{
		build_blog_entry_counters($blogid);
	}

	foreach(array_keys($userlist) AS $userid)
	{
		build_blog_user_counters($userid);
	}

	// empty cookie
	setcookie('vbulletin_inlinecomment', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('blog_inlinemod_dodeletecomments')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_deletedcomments', true, $forceredirect));
}

if ($_POST['do'] == 'undeletecomment')
{
	// Validate Comments
	$comments = $db->query_read_slave("
		SELECT blogtextid, blog_text.state, blog_text.blogid, blog_text.userid, blog_text.dateline,
			blog.state AS blog_state, blog.userid AS blog_userid, blog_user.lastcomment, blog.pending,
			user.username
		FROM " . TABLE_PREFIX . "blog_text AS blog_text
		LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_text.blogid)
		LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog.userid = blog_user.bloguserid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog_text.userid = user.userid)
		WHERE blogtextid IN ($blogtextids)
			AND blogtextid <> firstblogtextid
			AND blog_text.state = 'deleted'
	");
	while ($comment = $db->fetch_array($comments))
	{
		// Check permissions.....
		if (($comment['blog_userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($comment['blog_userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}
		$canmanage = ($comment['blog_userid'] == $vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']);
		$candeletecomments = (can_moderate_blog('candeletecomments') OR $canmanage);

		if ($comment['blog_state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($comment['blog_state'] == 'deleted' AND !can_moderate_blog('candeleteentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}
		else if (($comment['pending'] OR $comment['blog_state'] == 'draft') AND $vbulletin->userinfo['userid'] != $comment['blog_userid'])
		{
			standard_error(fetch_error('you_can_not_manage_items_within_pending_or_draft_entries'));
		}
		else if (!$candeletecomments)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_comments'));
		}

		$commentarray["$comment[blogtextid]"] = $comment;
		$bloglist["$comment[blogid]"] = true;

		if ($comment['dateline'] >= $comment['lastcomment'])
		{
			$userlist["$comment[blog_userid]"] = true;
		}

	}

	if (empty($commentarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_comments'));
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_deletionlog
		WHERE type = 'blogtextid' AND
			primaryid IN(" . implode(',', array_keys($commentarray)) . ")
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_text
		SET state = 'visible'
		WHERE blogtextid IN(" . implode(',', array_keys($commentarray)) . ")
	");

	foreach(array_keys($bloglist) AS $blogid)
	{
		build_blog_entry_counters($blogid);
	}

	foreach(array_keys($userlist) AS $userid)
	{
		build_blog_user_counters($userid);
	}

	$modlog = array();
	foreach(array_keys($commentarray) AS $blogtextid)
	{
		$modlog[] = array(
			'userid'   =>& $vbulletin->userinfo['userid'],
			'id1'      =>& $commentarray["$blogtextid"]['blog_userid'],
			'id2'      =>& $commentarray["$blogtextid"]['blogid'],
			'id3'      =>  $blogtextid,
			'username' =>& $commentarray["$blogtextid"]['username'],
		);
	}

	require_once(DIR . '/includes/blog_functions_log_error.php');
	blog_moderator_action($modlog, 'comment_x_by_y_undeleted');

	// empty cookie
	setcookie('vbulletin_inlinecomment', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('blog_inlinemod_undeletecomments')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_undeletedcomments', true, $forceredirect));
}

if ($_POST['do'] == 'deleteentry')
{
	$show['removeentries'] = false;
	$show['deleteentries'] = false;
	$show['deleteoption'] = false;
	$checked = array('delete' => 'checked="checked"');

	// Validate Posts
	$posts = $db->query_read_slave("
		SELECT blogid, state, userid, pending
		FROM " . TABLE_PREFIX . "blog AS blog
		WHERE blogid IN ($blogids)
	");
	while ($post = $db->fetch_array($posts))
	{
		// Check permissions.....
		if (($post['userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($post['userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}

		$canremove = (can_moderate_blog('canremoveentries') OR ($vbulletin->userinfo['userid'] == $post['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry']));
		$canundelete = $candelete = (can_moderate_blog('candeleteentries') OR ($vbulletin->userinfo['userid'] == $post['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry']));

		if ($post['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($post['state'] == 'deleted' AND !$canremove)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_remove_blog_entries'));
		}
		else if (($post['pending'] OR $post['state'] == 'draft') AND $post['userid'] != $vbulletin->userinfo['userid'])
		{
			standard_error(fetch_error('you_can_not_manage_other_users_pending_or_draft_posts'));
		}
		else if ($canremove OR $candelete)
		{
			$show['deleteentries'] = ($candelete);
			if ($canremove)
			{
				$show['removeentries'] = true;
				if (!$candelete)
				{
					$checked = array('remove' => 'checked="checked"');
				}
			}
			$show['deleteoption'] = ($candelete AND $canremove);
			$show['delete'] = true;
		}
		else if (
			$vbulletin->userinfo['userid'] != $post['userid']
				OR
			(
				$post['state'] != 'draft'
					AND
				!$post['pending']
			)
		)
		{
			print_no_permission();
		}

		$blogarray["$post[blogid]"] = $post;
		$userarray["$post[userid]"] = true;
	}

	if (empty($blogarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_entries'));
	}

	$blogcount = count($blogarray);
	$usercount = count($userarray);

	// hook
	// draw navbar

	$navbits = array(
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]" => $bloginfo['title'],
		'' => $vbphrase['delete_blog_entries']
	);

	$url =& $vbulletin->url;
	eval('$content = "' . fetch_template('blog_inlinemod_delete_entries') . '";');
}

if ($_POST['do'] == 'dodeleteentry')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'deletetype'   => TYPE_UINT, // 1 - Soft Deletion, 2 - Physically Remove
		'deletereason' => TYPE_NOHTMLCOND,
	));

	$physicaldel = ($vbulletin->GPC['deletetype'] == 2) ? true : false;
	$visibleposts = array();
	$visibleuserlist = array();
	$invisibleuserlist = array();

	// Validate Posts
	$posts = $db->query_read_slave("
		SELECT blogid, state, userid, pending
		FROM " . TABLE_PREFIX . "blog AS blog
		WHERE blogid IN (" . implode(',', $blogids) . ")
	");
	while ($post = $db->fetch_array($posts))
	{
		// Check permissions.....
		if (($post['userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($post['userid'] == !($vbulletin->userinfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}

		$canremove = (can_moderate_blog('canremoveentries') OR ($vbulletin->userinfo['userid'] == $post['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry']));
		$canundelete = $candelete = (can_moderate_blog('candeleteentries') OR ($vbulletin->userinfo['userid'] == $post['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry']));

		if ($post['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_entries'));
		}
		else if ($post['state'] == 'deleted' AND !$canremove)
		{
			standard_error(fetch_error('you_do_not_have_permission_to_remove_blog_entries'));
		}
		else if (($post['pending'] OR $post['state'] == 'draft') AND $post['userid'] != $vbulletin->userinfo['userid'])
		{
			standard_error(fetch_error('you_can_not_manage_other_users_pending_or_draft_posts'));
		}
		else if ($canremove OR $candelete)
		{
			if (($physicaldel AND !$canremove) OR (!$physicaldel AND !$candelete))
			{
				standard_error(fetch_error('you_do_not_have_permission_to_remove_blog_entries'));
			}
		}
		else if (
			$vbulletin->userinfo['userid'] != $post['userid']
				OR
			(
				$post['state'] != 'draft'
					AND
				!$post['pending']
			)
		)
		{
			print_no_permission();
		}

		$blogarray["$post[blogid]"] = $post;

		if ($post['state'] == 'visible')
		{
			if (empty($visibleuserlist["$post[userid]"]))
			{
				$visibleuserlist["$post[userid]"] = 1;
			}
			else
			{
				$visibleuserlist["$post[userid]"]++;
			}
		}
		else
		{
			if (empty($invisibleuserlist["$post[userid]"]))
			{
				$invisibleuserlist["$post[userid]"] = 1;
			}
			else
			{
				$invisibleuserlist["$post[userid]"]++;
			}
		}
	}

	if (empty($blogarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_entries'));
	}

	foreach($blogarray AS $blogid => $blog)
	{
		$dataman =& datamanager_init('Blog', $vbulletin, ERRTYPE_SILENT, 'blog');
		$dataman->set_existing($blog);
		$dataman->set_info('skip_build_blog_counters', true);
		$dataman->set_info('skip_build_category_counters', true);
		if ($blog['state'] == 'draft' OR $blog['pending'])
		{	// Always perm delete drafts - only the owner can do this
			$dataman->set_info('hard_delete', true);
		}
		else
		{
			$dataman->set_info('hard_delete', $physicaldel);
		}
		$dataman->set_info('reason', $vbulletin->GPC['deletereason']);
		$dataman->delete();
		unset($dataman);
	}

	if (!empty($visibleuserlist))
	{
		$bycount = array();
		foreach ($visibleuserlist AS $userid => $total)
		{
			$bycount["$total"][] = $userid;
		}

		$casesql = array();
		foreach ($bycount AS $total => $userids)
		{
			$casesql[] = " WHEN bloguserid IN (" . implode(',', $userids) . ") THEN $total";
		}

		if (!empty($casesql))
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "blog_user
				SET entries = entries -
				CASE
					" . implode("\n", $casesql) . "
					ELSE 0
				END
				WHERE bloguserid IN (" . implode(',', array_keys($visibleuserlist)) . ")
			");
		}
	}

	build_category_counters_mass(array_keys($visibleuserlist));

	foreach (array_unique(array_merge(array_keys($invisibleuserlist),  array_keys($visibleuserlist))) AS $userid)
	{
		build_blog_user_counters($userid);
	}
	build_blog_stats();

	// empty cookie
	setcookie('vbulletin_inlineblog', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('blog_inlinemod_dodeleteetries')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_deletedentries', true, $forceredirect));
}

if ($_POST['do'] == 'undeleteentry')
{
	// Validate Entries
	$posts = $db->query_read_slave("
		SELECT blog.blogid, blog.userid, blog.state, blog_deletionlog.userid AS del_userid
		FROM " . TABLE_PREFIX . "blog AS blog
		LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog.blogid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogid')
		WHERE blogid IN ($blogids)
			AND state = 'deleted'
	");
	while ($post = $db->fetch_array($posts))
	{
		// Check permissions.....
		if (($post['userid'] != $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])) OR
			($post['userid'] == $vbulletin->userinfo['userid'] AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
		{
			print_no_permission();
		}

		if (
			!can_moderate_blog('candeleteentries')
				AND
			(
				$vbulletin->userinfo['userid'] != $post['userid']
					OR
				!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry'])
					OR
				$post['del_userid'] != $vbulletin->userinfo['userid']
		))
		{
			standard_error(fetch_error('you_do_not_have_permission_to_manage_deleted_entries'));
		}

		$blogarray["$post[blogid]"] = $post;

		if (empty($userlist["$post[userid]"]))
		{
			$userlist["$post[userid]"] = 1;
		}
		else
		{
			$userlist["$post[userid]"]++;
		}
	}

	if (empty($blogarray))
	{
		standard_error(fetch_error('you_did_not_select_any_valid_entries'));
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_deletionlog
		WHERE type = 'blogid' AND
			primaryid IN(" . implode(',', array_keys($blogarray)) . ")
	");

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog
		SET state = 'visible'
		WHERE blogid IN(" . implode(',', array_keys($blogarray)) . ")
	");

	if (!empty($userlist))
	{
		$bycount = array();
		foreach ($userlist AS $userid => $total)
		{
			$bycount["$total"][] = $userid;
		}

		$casesql = array();
		foreach ($bycount AS $total => $userids)
		{
			$casesql[] = " WHEN bloguserid IN (" . implode(',', $userids) . ") THEN $total";
		}

		if (!empty($casesql))
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "blog_user
				SET entries = entries +
				CASE
					" . implode("\n", $casesql) . "
					ELSE 0
				END
				WHERE bloguserid IN (" . implode(',', array_keys($userlist)) . ")
			");
		}
	}

	build_category_counters_mass(array_keys($userlist));

	$modlog = array();

	foreach(array_keys($blogarray) AS $blogid)
	{
		build_blog_entry_counters($blogid);
		$modlog[] = array(
			'userid' =>& $vbulletin->userinfo['userid'],
			'id1'    =>& $blogarray["$blogid"]['userid'],
			'id2'    =>  $blogid,
		);
	}

	require_once(DIR . '/includes/blog_functions_log_error.php');
	blog_moderator_action($modlog, 'blogentry_undeleted');

	foreach (array_keys($userlist) AS $userid)
	{
		build_blog_user_counters($userid);
	}
	build_blog_stats();

	// empty cookie
	setcookie('vbulletin_inlineblog', '', TIMENOW - 3600, '/');

	($hook = vBulletinHook::fetch_hook('blog_inlinemod_undeleteentries')) ? eval($hook) : false;

	eval(print_standard_redirect('redirect_inline_undeletedentries', true, $forceredirect));
}

if ($userinfo)
{
	cache_ordered_categories($userinfo['userid']);
	$sidebar =& build_user_sidebar($userinfo);
}
else
{
	$sidebar =& build_overview_sidebar();
}

$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');

($hook = vBulletinHook::fetch_hook('blog_inlinemod_complete')) ? eval($hook) : false;

eval('print_output("' . fetch_template('BLOG') . '");');

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 24613 $
|| ####################################################################
\*======================================================================*/
?>