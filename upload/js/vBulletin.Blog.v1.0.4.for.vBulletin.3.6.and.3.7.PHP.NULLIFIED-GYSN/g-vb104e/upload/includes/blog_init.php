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

// Check if blog is disabled, if so send off to forum home. Alternatively, show a "Blog is disabled" error message?
if (!$vbulletin->products['vbblog'])
{
	exec_header_redirect($vbulletin->options['forumhome'] . '.php');
}

// this will no doubt be called much earlier in a future version, header / navbar won't be able to see this
define('VBBLOG_SCRIPT', true);

// get core functions
if (!empty($db->explain))
{
	$db->timer_start('Including blog_functions.php');
	require_once(DIR . '/includes/blog_functions.php');
	$db->timer_stop(false);
}
else
{
	require_once(DIR . '/includes/blog_functions.php');
}

// Init vbblog array into the registry
$vbulletin->vbblog = array();
$blogtextinfo = array();
$bloginfo = array();
$onload = '';

$vbulletin->input->clean_array_gpc('r', array(
	'blogid'     => TYPE_UINT,
	'blogtextid' => TYPE_UINT,
));

if ($vbulletin->GPC['blogtextid'] AND $blogtextinfo = fetch_blog_textinfo($vbulletin->GPC['blogtextid'], false, false))
{
	$blogtextid =& $blogtextinfo['blogtextid'];
	$vbulletin->GPC['blogid'] =& $blogtextinfo['blogid'];
}

if ($vbulletin->GPC['blogid'] AND $bloginfo = verify_blog($vbulletin->GPC['blogid'], false, false))
{
	$blogid =& $bloginfo['blogid'];
}

if (!$vbulletin->options['enablehooks'] OR defined('DISABLE_HOOKS'))
{
	standard_error(fetch_error('product_requires_plugin_system'));
}

// Check that the user can use the blog
if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
{
	if (!defined('VBBLOG_SKIP_PERMCHECK') AND (!$vbulletin->userinfo['userid'] OR !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])))
	{
		if (defined('DIE_QUIETLY'))
		{
			exit;
		}
		else
		{
			print_no_permission();
		}
	}
}

// overwrite the messagearea stylevar with the smaller messagearea_usercp stylevar
$stylevar['messagewidth'] = $stylevar['messagewidth_blog'];

// 3.7+ forward compatability
if (!empty($vbulletin->options['vb_antispam_key']) AND empty($vbulletin->options['akismet_key']))
{
	$vbulletin->options['akismet_key'] = $vbulletin->options['vb_antispam_key'];
}

if (version_compare($vbulletin->options['templateversion'], '3.7.0 Alpha 1', '>='))
{
	$show['blog_37_compatible'] = true;
}

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25159 $
|| ####################################################################
\*======================================================================*/
?>