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
define('THIS_SCRIPT', 'blog_usercp');
define('VBBLOG_PERMS', 1);
define('VBBLOG_STYLE', 1);
define('GET_EDIT_TEMPLATES', 'editprofile,updateprofile');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'vbblogglobal',
	'user',
	'posting',
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'BLOG',
	'blog_sidebar_category_link',
	'blog_sidebar_comment_link',
	'blog_sidebar_user',
	'blog_sidebar_calendar',
	'blog_sidebar_calendar_day',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'editoptions' => array(
		'blog_cp_modify_options',
	),
	'editprofile' => array(
		'blog_cp_modify_profile',
		'blog_cp_modify_profile_preview',
		'blog_rules',
	),
	'manageaccess' => array(
		'blog_modifyaccess',
		'modifylistbit',
		'updateaccess',
	),
	'editcat' => array(
		'blog_cp_manage_categories',
		'blog_cp_manage_categories_category',
	),
	'modifycat' => array(
		'blog_cp_new_category',
	),
	'updatecat' => array(
		'blog_cp_new_category'
	),
	'managetrackback' => array(
		'blog_cp_manage_trackbacks',
		'blog_cp_manage_trackbacks_trackback',
	),
);

$actiontemplates['updateprofile'] =& $actiontemplates['editprofile'];
$actiontemplates['none'] =& $actiontemplates['editoptions'];

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_init.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

$bloguserinfo = array();
$checked = array();

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'editoptions';
}

if (!($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']) OR !$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

$show['pingback'] = ($vbulletin->options['vbblog_pingback'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'] ? true : false);
$show['trackback'] = ($vbulletin->options['vbblog_trackback'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'] ? true : false);

($hook = vBulletinHook::fetch_hook('blog_usercp_start')) ? eval($hook) : false;

// ############################################################################
// ############################### UPDATED PROFILE ############################
// ############################################################################
if ($_POST['do'] == 'updateprofile')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'wysiwyg'        => TYPE_BOOL,
		'title'          => TYPE_STR,
		'message'        => TYPE_STR,
		'parseurl'       => TYPE_BOOL,
		'disablesmilies' => TYPE_BOOL,
		'preview'        => TYPE_STR,
	));

	$errors = array();
	// unwysiwygify the incoming data
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$vbulletin->GPC['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowhtml']);
	}

	require_once(DIR . '/includes/functions_newpost.php');
	// parse URLs in message text
	if ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode'] AND $vbulletin->GPC['parseurl'])
	{
		$vbulletin->GPC['message'] = convert_url_to_bbcode($vbulletin->GPC['message']);
	}

	$dataman =& datamanager_init('Blog_User', $vbulletin, ERRTYPE_ARRAY);
	if ($vbulletin->userinfo['bloguserid'])
	{
		$foo = array('bloguserid' => $vbulletin->userinfo['bloguserid']);
		$dataman->set_existing($foo);
	}
	else
	{
		$dataman->set('bloguserid', $vbulletin->userinfo['userid']);
	}

	$dataman->set('description', $vbulletin->GPC['message']);
	$dataman->set('title', $vbulletin->GPC['title']);
	$dataman->set('allowsmilie', $vbulletin->GPC['disablesmilies'] ? 0 : 1);

	$dataman->pre_save();

	$bloguserinfo = array();
	if (!empty($dataman->errors))
	{	### DESCRIPTION HAS ERRORS ###
		define('PREVIEW', 1);
		$postpreview = construct_errors($dataman->errors);
		$_REQUEST['do'] = 'editprofile';
	}
	else if ($vbulletin->GPC['preview'])
	{
		define('PREVIEW', 1);
		require_once(DIR . '/includes/class_bbcode.php');
		$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
		$bbcode_parser->set_parse_userinfo($vbulletin->userinfo, $vbulletin->userinfo['permissions']);
		$previewmessage = $bbcode_parser->parse($vbulletin->GPC['message'], 'blog_user', $vbulletin->GPC['disablesmilies'] ? 0 : 1);

		if ($previewmessage != '')
		{
			eval('$postpreview = "' . fetch_template('blog_cp_modify_profile_preview')."\";");
		}
		else
		{
			$postpreview = '';
		}

		$postpreview = process_blog_preview(array('message' =>& $vbulletin->GPC['message'], 'disablesmilies' =>& $vbulletin->GPC['disablesmilies']), 'user');
		$_REQUEST['do'] = 'editprofile';
	}
	else
	{
		$dataman->save();

		$vbulletin->url = 'blog_usercp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editprofile';
		eval(print_standard_redirect('redirect_blog_profileupdate', true, true));
	}

}

// ############################################################################
// ############################### EDIT PROFILE ###############################
// ############################################################################
if ($_REQUEST['do'] == 'editprofile')
{
	($hook = vBulletinHook::fetch_hook('blog_editprofile_start')) ? eval($hook) : false;

	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}

	if ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode'])
	{
		$show['parseurl'] = true;
		$show['miscoptions'] = true;
	}

	if (defined('PREVIEW'))
	{
		$bloguserinfo['message'] = htmlspecialchars_uni($vbulletin->GPC['message']);
		$bloguserinfo['title'] = htmlspecialchars_uni($vbulletin->GPC['title']);
		$checked['disablesmilies'] = $vbulletin->GPC['disablesmilies'] ? 'checked="checked"' : '';
		$checked['parseurl'] = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode'] AND $vbulletin->GPC['parseurl']) ? 'checked="checked"' : '';
	}
	else
	{
		$bloguserinfo['message'] = $vbulletin->userinfo['blog_description'];
		$bloguserinfo['title'] = ($vbulletin->userinfo['blog_title'] == $vbulletin->userinfo['username']) ? '' : $vbulletin->userinfo['blog_title'];
		$checked['parseurl'] = 'checked="checked"';
		if (!$vbulletin->userinfo['blog_allowsmilie'] AND $vbulletin->userinfo['blog_description'])
		{
			$checked['disablesmilies'] = 'checked="checked"';
		}

		$postpreview = '';
		if ($bloguserinfo['message'])
		{
			require_once(DIR . '/includes/class_bbcode.php');
			$bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());
			$bbcode_parser->set_parse_userinfo($vbulletin->userinfo);
			$previewmessage = $bbcode_parser->parse($bloguserinfo['message'], 'blog_user', $vbulletin->userinfo['blog_allowsmilie']);

			if ($previewmessage != '')
			{
				eval('$postpreview = "' . fetch_template('blog_cp_modify_profile_preview')."\";");
			}
		}
	}

	// get decent textarea size for user's browser
	require_once(DIR . '/includes/functions_editor.php');

	$editorid = construct_edit_toolbar($bloguserinfo['message'], false, 'blog_user', $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowsmilies']);

	// build forum rules
	$bbcodeon = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode']) ? $vbphrase['on'] : $vbphrase['off'];
	$imgcodeon = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowimages']) ? $vbphrase['on'] : $vbphrase['off'];
	$htmlcodeon = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowhtml']) ? $vbphrase['on'] : $vbphrase['off'];
	$smilieson = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowsmilies']) ? $vbphrase['on'] : $vbphrase['off'];

	if ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowsmilies'])
	{
		eval('$disablesmiliesoption = "' . fetch_template('newpost_disablesmiliesoption') . '";');
		$show['miscoptions'] = true;
	}

	// only show posting code allowances in forum rules template
	$show['codeonly'] = true;

	eval('$forumrules = "' . fetch_template('forumrules') . '";');

	$navbits = array('' => $vbphrase['blog_title_and_description']);

	($hook = vBulletinHook::fetch_hook('blog_editprofile_complete')) ? eval($hook) : false;

	eval('$content = "' . fetch_template('blog_cp_modify_profile') . '";');
}

// ############################################################################
// ############################### EDIT OPTIONS ###############################
// ############################################################################
if ($_REQUEST['do'] == 'editoptions')
{
	($hook = vBulletinHook::fetch_hook('blog_editoptions_start')) ? eval($hook) : false;

	foreach($vbulletin->bf_misc_vbbloguseroptions AS $optionname => $optionval)
	{
		$checked["$optionname"] = $vbulletin->userinfo['blog_' . $optionname] ? 'checked="checked"' : '';
	}

	foreach ($vbulletin->bf_misc_vbblogsocnetoptions AS $optionname => $optionvalue)
	{
		$checked["everyone_$optionname"] = $vbulletin->userinfo["everyone_$optionname"] ? 'checked="checked"' : '';
		$checked["buddy_$optionname"] = $vbulletin->userinfo["buddy_$optionname"] ? 'checked="checked"' : '';
		$checked["ignore_$optionname"] = $vbulletin->userinfo["ignore_$optionname"] ? 'checked="checked"' : '';
	}

	$subscribeownchecked = array($vbulletin->userinfo['blog_subscribeown'] => 'selected="selected"');
	$subscribeotherschecked = array($vbulletin->userinfo['blog_subscribeothers'] => 'selected="selected"');
	$blog_akismet_key = htmlspecialchars_uni($vbulletin->userinfo['blog_akismet_key']);

	$show['moderatecomments'] = (!$vbulletin->options['blog_commentmoderation'] AND $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_followcommentmoderation'] ? true : false);
	$show['akismet_key'] = (empty($vbulletin->options['akismet_key']) AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']);
	$show['allowcomments'] = ($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']);
	$show['privacy'] = ($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']);

	$navbits = array('' => $vbphrase['blog_options']);

	($hook = vBulletinHook::fetch_hook('blog_editoptions_complete')) ? eval($hook) : false;

	eval('$content = "' . fetch_template('blog_cp_modify_options') . '";');
}

// ############################################################################
// ############################### UPDATED OPTIONS ############################
// ############################################################################
if ($_POST['do'] == 'updateoptions')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'options'          => TYPE_ARRAY_BOOL,
		'set_options'      => TYPE_ARRAY_BOOL,
		'options_everyone' => TYPE_ARRAY_BOOL,
		'options_buddy'    => TYPE_ARRAY_BOOL,
		'options_ignore'   => TYPE_ARRAY_BOOL,
		'title'            => TYPE_STR,
		'description'      => TYPE_STR,
		'subscribeown'     => TYPE_STR,
		'subscribeothers'  => TYPE_STR,
		'akismet_key'      => TYPE_STR
	));

	$dataman =& datamanager_init('Blog_User', $vbulletin, ERRTYPE_STANDARD);

	if ($vbulletin->userinfo['bloguserid'])
	{
		$foo = array('bloguserid' => $vbulletin->userinfo['bloguserid']);
		$dataman->set_existing($foo);
	}
	else
	{
		$dataman->set('bloguserid', $vbulletin->userinfo['userid']);
	}

	// options bitfield
	foreach ($vbulletin->bf_misc_vbbloguseroptions AS $key => $val)
	{
		if (isset($vbulletin->GPC['options']["$key"]) OR isset($vbulletin->GPC['set_options']["$key"]))
		{
			$value = intval($vbulletin->GPC['options']["$key"]);
			$dataman->set_bitfield('options', $key, $value);
		}
	}

	// options bitfield
	foreach ($vbulletin->bf_misc_vbblogsocnetoptions AS $key => $val)
	{
		if (isset($vbulletin->GPC['set_options']["options_everyone_$key"]) OR isset($vbulletin->GPC['options_everyone']["$key"]))
		{
			$dataman->set_bitfield('options_everyone', $key, intval($vbulletin->GPC['options_everyone']["$key"]));
		}
		if (isset($vbulletin->GPC['set_options']["options_buddy_$key"]) OR isset($vbulletin->GPC['options_buddy']["$key"]))
		{
			$dataman->set_bitfield('options_buddy', $key, intval($vbulletin->GPC['options_buddy']["$key"]));
		}
		if (isset($vbulletin->GPC['set_options']["options_ignore_$key"]) OR isset($vbulletin->GPC['options_ignore']["$key"]))
		{
			$dataman->set_bitfield('options_ignore', $key, intval($vbulletin->GPC['options_ignore']["$key"]));
		}
	}

	if (isset($vbulletin->GPC['set_options']['subscribeown']) OR $vbulletin->GPC_exists['subscribeown'])
	{
		$dataman->set('subscribeown', $vbulletin->GPC['subscribeown']);
	}
	if (isset($vbulletin->GPC['set_options']['subscribeothers']) OR $vbulletin->GPC_exists['subscribeothers'])
	{
		$dataman->set('subscribeothers', $vbulletin->GPC['subscribeothers']);
	}

	if ((isset($vbulletin->GPC['set_options']['akismet_key']) OR $vbulletin->GPC_exists['akismet_key']) AND empty($vbulletin->options['akismet_key']))
	{
		$dataman->set('akismet_key', $vbulletin->GPC['akismet_key']);
	}

	$dataman->save();

	$vbulletin->url = 'blog_usercp.php' . $vbulletin->session->vars['sessionurl_q'];
	eval(print_standard_redirect('redirect_blog_profileupdate'));

}


// ############################################################################
// ############################### EDIT CATEGORIES ############################
// ############################################################################
if ($_REQUEST['do'] == 'editcat')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}

	cache_ordered_categories();

	$catbits = '';
	foreach ($vbulletin->vbblog['categorycache']["{$vbulletin->userinfo['userid']}"] AS $blogcategoryid => $category)
	{
		$depthmark = str_pad('', strlen(FORUM_PREPEND) * $category['depth'], FORUM_PREPEND, STR_PAD_LEFT);
		eval('$catbits .= "' . fetch_template('blog_cp_manage_categories_category')."\";");
	}

	$categorycount = count($vbulletin->vbblog['categorycache']["{$vbulletin->userinfo['userid']}"]);

	$navbits = array('' => $vbphrase['blog_categories']);
	eval('$content = "' . fetch_template('blog_cp_manage_categories') . '";');
}

// ############################################################################
// ############################### MANAGE CATEGORIES ##########################
// ############################################################################

if ($_POST['do'] == 'addcat')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'title'          => TYPE_STR,
		'description'    => TYPE_STR,
		'parentid'       => TYPE_UINT,
		'displayorder'   => TYPE_UINT,
		'blogcategoryid' => TYPE_UINT,
		'dbutton'        => TYPE_STR,
		'delete'         => TYPE_BOOL,
	));

	$errors = array();

	cache_ordered_categories();

	$dataman =& datamanager_init('Blog_Category', $vbulletin, ERRTYPE_ARRAY);

	if ($vbulletin->GPC['blogcategoryid'])
	{
		if ($categoryinfo = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "blog_category
			WHERE blogcategoryid = " . $vbulletin->GPC['blogcategoryid'] . "
				AND userid = " . $vbulletin->userinfo['userid'] . "
		"))
		{
			$dataman->set_existing($categoryinfo);
			if ($vbulletin->GPC['dbutton'])
			{
				if ($vbulletin->GPC['delete'])
				{
					$dataman->set_condition("FIND_IN_SET('" . $vbulletin->GPC['blogcategoryid'] . "', parentlist)");
					$dataman->delete();
					$vbulletin->url = 'blog_usercp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editcat';
					eval(print_standard_redirect('redirect_blog_profileupdate'));
				}
				else
				{
					define('PREVIEW', 1);
					$_REQUEST['do'] = 'modifycat';
				}
			}
		}
		else
		{
			standard_error(fetch_error('invalidid', 'blogcategoryid', $vbulletin->options['contactuslink']));
		}
	}
	else
	{
		if (sizeof($vbulletin->vbblog['categorycache'][$vbulletin->userinfo['userid']]) >= $vbulletin->options['blog_catusertotal'])
		{
			standard_error(fetch_error('blog_category_limit', $vbulletin->options['blog_catusertotal']));
		}
		$dataman->set('userid', $vbulletin->userinfo['userid']);
	}

	if (empty($errors) AND !defined('PREVIEW'))
	{
		$dataman->set('description', $vbulletin->GPC['description']);
		$dataman->set('title', $vbulletin->GPC['title']);
		$dataman->set('parentid', $vbulletin->GPC['parentid']);
		$dataman->set('displayorder', $vbulletin->GPC['displayorder']);

		$dataman->pre_save();

		if (!empty($dataman->errors))
		{
			define('PREVIEW', 1);
			$_REQUEST['do'] = 'modifycat';
			require_once(DIR . '/includes/functions_newpost.php');
			$errorlist = construct_errors($dataman->errors);
		}
		else
		{
			$dataman->save();

			$vbulletin->url = 'blog_usercp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editcat';
			eval(print_standard_redirect('redirect_blog_profileupdate'));
		}
	}
}

if ($_POST['do'] == 'updatecat')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', array(
		'addcat'       => TYPE_STR,
		'displayorder' => TYPE_ARRAY_UINT,
	));

	if ($vbulletin->GPC['addcat'])
	{	// Add New Category
		$_REQUEST['do'] = 'modifycat';
	}
	else
	{	// Update Display Order and Rebuild Category Cache
		$casesql = array();
		foreach ($vbulletin->GPC['displayorder'] AS $blogcategoryid => $displayorder)
		{
			$casesql[] = " WHEN blogcategoryid = " . intval($blogcategoryid) . " THEN $displayorder";
		}

		if (!empty($casesql))
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "blog_category
				SET displayorder =
				CASE
					" . implode("\r\n", $casesql) . "
					ELSE displayorder
				END
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
			");
		}

		$vbulletin->url = 'blog_usercp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editcat';
		eval(print_standard_redirect('redirect_blog_profileupdate'));
	}
}

if ($_REQUEST['do'] == 'modifycat')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'blogcategoryid' => TYPE_UINT
	));

	$categoryinfo = array('displayorder' => 1);

	if ($vbulletin->GPC['blogcategoryid'])
	{
		if (!($categoryinfo = $db->query_first("
			SELECT *, title AS realtitle
			FROM " . TABLE_PREFIX . "blog_category
			WHERE blogcategoryid = " . $vbulletin->GPC['blogcategoryid'] . "
				AND userid = " . $vbulletin->userinfo['userid'] . "
		")))
		{
			standard_error(fetch_error('invalidid', 'blogcategoryid', $vbulletin->options['contactuslink']));
		}
	}
	else
	{ // make sure they have less than the limit
		if (!isset($vbulletin->vbblog['categorycache'][$vbulletin->userinfo['userid']]))
		{
			cache_ordered_categories($vbulletin->userinfo['userid']);
		}

		if (sizeof($vbulletin->vbblog['categorycache'][$vbulletin->userinfo['userid']]) >= $vbulletin->options['blog_catusertotal'])
		{
			standard_error(fetch_error('blog_category_limit', $vbulletin->options['blog_catusertotal']));
		}
	}

	if (defined('PREVIEW'))
	{
		$categoryinfo = array(
			'realtitle'      => $categoryinfo['title'],
			'title'          => htmlspecialchars_uni($vbulletin->GPC['title']),
			'description'    => htmlspecialchars_uni($vbulletin->GPC['description']),
			'parentid'       => $vbulletin->GPC['parentid'],
			'displayorder'   => $vbulletin->GPC['displayorder'],
			'blogcategoryid' => $vbulletin->GPC['blogcategoryid'],
		);
	}

	$selectbits = construct_category_select($categoryinfo['parentid']);

	$navbits = array(
		'blog_usercp.php?' . $vbulletin->session->vars['sessionurl'] . 'do=editcat' => $vbphrase['blog_categories'],
		'' => ($categoryinfo['blogcategoryid'] ? $vbphrase['edit_blog_category'] : $vbphrase['add_new_blog_category'])
	);
	eval('$content = "' . fetch_template('blog_cp_new_category') . '";');
}

if ($_REQUEST['do'] == 'managetrackback')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}

	if ($show['pingback'] OR $show['trackback'])
	{
		$vbulletin->input->clean_array_gpc('r', array(
			'type'       => TYPE_STR,
			'pagenumber' => TYPE_UINT,
			'perpage'    => TYPE_UINT,
		));

		if (!$vbulletin->userinfo['userid'])
		{
			print_no_permission();
		}

		$canmoderateall = (can_moderate_blog('canmoderatecomments'));

		switch ($vbulletin->GPC['type'])
		{
			case 'fa';
			case 'fm';
					if (!$canmoderateall)
					{
						$type = 'oa';
					}
					else
					{
						$type = $vbulletin->GPC['type'];
					}
				break;
			case 'oa':
			case 'om':
				$type = $vbulletin->GPC['type'];
				break;
			default:
				$type = 'oa';
		}

		$selected = array(
			$type => 'selected="selected"'
		);

		$wheresql = array();
		if ($type == 'oa' OR $type == 'om')
		{
			if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
			{
				print_no_permission();
			}
			$wheresql[] = "blog_trackback.userid = " . $vbulletin->userinfo['userid'];
		}
		else
		{	// Moderator View
			$wheresql[] = "blog_trackback.userid <> " . $vbulletin->userinfo['userid'];
			$wheresql[] = "blog.dateline <= " . TIMENOW;
			$wheresql[] = "blog.pending = 0";

			$state = array('visible', 'deleted');

			if (can_moderate_blog('canmoderateentries'))
			{
				$state[] = 	'moderation';
			}

			$wheresql[] = "blog.state IN('" . implode("', '", $state) . "')";
		}
		if ($type == 'fm' OR $type == 'om')
		{
			$wheresql[] = "blog_trackback.state = 'moderation'";
		}

		// Set Perpage .. this limits it to 50. Any reason for more?
		if ($vbulletin->GPC['perpage'] == 0)
		{
			$perpage = 20;
		}
		else if ($vbulletin->GPC['perpage'] > 50)
		{
			$perpage = 50;
		}
		else
		{
			$perpage = $vbulletin->GPC['perpage'];
		}

		do
		{
			if ($vbulletin->GPC['pagenumber'] < 1)
			{
				$pagenumber = 1;
			}
			else if ($vbulletin->GPC['pagenumber'] > 10)
			{
				$pagenumber = 10;
			}
			else
			{
				$pagenumber = $vbulletin->GPC['pagenumber'];
			}
			$start = ($pagenumber - 1) * $perpage;

			$trackbacks = $db->query_read_slave("
				SELECT SQL_CALC_FOUND_ROWS blog_trackback.*, blog.title AS blogtitle, blog.state AS blog_state
				FROM " . TABLE_PREFIX . "blog_trackback AS blog_trackback
				LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_trackback.blogid)
				WHERE " . implode(" AND ", $wheresql) . "
				ORDER BY blog_trackback.dateline DESC
				LIMIT $start, $perpage
			");
			list($count) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

			if ($start >= $post_count)
			{
				$vbulletin->GPC['pagenumber'] = ceil($count / $perpage);
			}
		}
		while ($start >= $count AND $count);

		$pagenavurl = array('do=managetrackback');
		if ($type != 'oa')
		{
			$pagenavurl[] = "type=$type";
		}
		if ($perpage != 20)
		{
			$pagenavurl[] = "pp=$perpage";
		}

		$pagenav = construct_page_nav(
			$pagenumber,
			$perpage,
			$count,
			'blog_usercp.php?' . $vbulletin->session->vars['sessionurl'] . implode('&amp;', $pagenavurl)
		);

		$colspan = 3;
		while ($trackback = $db->fetch_array($trackbacks))
		{
			if ($trackback['state'] == 'moderation')
			{
				$show['edit'] = ((can_moderate_blog('caneditcomments') AND can_moderate_blog('canmoderatecomments')) OR ($trackback['blog_state'] == 'visible' AND $vbulletin->userinfo['userid'] == $trackback['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
				$show['approve'] = (can_moderate_blog('canmoderatecomments') OR ($trackback['blog_state'] == 'visible' AND $vbulletin->userinfo['userid'] == $trackback['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
				$show['delete'] = ((can_moderate_blog('canmoderatecomments') AND can_moderate_blog('candeletecomments')) OR ($trackback['blog_state'] == 'visible' AND $vbulletin->userinfo['userid'] == $trackback['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
			}
			else
			{
				$show['edit'] = (can_moderate_blog('caneditcomments') OR ($trackback['blog_state'] == 'visible' AND $vbulletin->userinfo['userid'] == $trackback['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
				$show['approve'] = (can_moderate_blog('canmoderatecomments') OR ($trackback['blog_state'] == 'visible' AND $vbulletin->userinfo['userid'] == $trackback['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
				$show['delete'] = (can_moderate_blog('candeletecomments') OR ($trackback['blog_state'] == 'visible' AND $vbulletin->userinfo['userid'] == $trackback['userid'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
			}
			$show['inlinemod_trackback'] = ($show['delete'] OR $show['approve']) ? true : false;
			if ($show['inlinemod_trackback'])
			{
				$show['inlinemod'] = true;
			}
			if ($show['delete'])
			{
				$show['inlinemod_delete'] = true;
			}
			if ($show['approve'])
			{
				$show['inlinemod_approve'] = true;
			}
			$show['moderation'] = ($trackback['state'] == 'moderation');

			$trackback['date'] = vbdate($vbulletin->options['dateformat'], $trackback['dateline'], true);
			$trackback['time'] = vbdate($vbulletin->options['timeformat'], $trackback['dateline'], true);
			eval('$trackbackbits .= "' . fetch_template('blog_cp_manage_trackbacks_trackback') . '";');
		}
		if ($show['inlinemod'])
		{
			$colspan++;
		}

		$navbits = array($vbphrase['manage_trackbacks']);
		eval('$content = "' . fetch_template('blog_cp_manage_trackbacks') . '";');
	}
	else
	{	// Shouldn't be here
		print_no_permission();
	}
}

// #############################################################################
// spit out final HTML if we have got this far

// Sidebar
$show['blogcp'] = true;
cache_ordered_categories($vbulletin->userinfo['userid']);
$sidebar =& build_user_sidebar($vbulletin->userinfo, 0, 0, $_REQUEST['do'] == 'editprofile' ? 'user' : '');

// build navbar
if (empty($navbits))
{
	$navbits = array('blog.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['blogs'], '' => $vbphrase['blog_control_panel']);
}
else
{
	$navbits = array_merge(array('blog.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['blogs'], 'blog_usercp.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['blog_control_panel']), $navbits);
}
$navbits = construct_navbits($navbits);

eval('$navbar = "' . fetch_template('navbar') . '";');

($hook = vBulletinHook::fetch_hook('blog_usercp_complete')) ? eval($hook) : false;

// shell template
eval('print_output("' . fetch_template('BLOG') . '");');

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25532 $
|| ####################################################################
\*======================================================================*/
?>
