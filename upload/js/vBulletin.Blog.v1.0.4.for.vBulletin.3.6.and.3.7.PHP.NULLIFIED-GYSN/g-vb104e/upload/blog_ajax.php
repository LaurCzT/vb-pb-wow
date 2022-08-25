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
define('THIS_SCRIPT', 'blog_ajax');
define('LOCATION_BYPASS', 1);
define('NOPMPOPUP', 1);
define('VBBLOG_PERMS', 1);
define('VBBLOG_STYLE', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('cprofilefield', 'user', 'vbblogglobal');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'calendar'       => array(
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
	),
	'loadupdated' => array(
		'blog_overview_recentblogbit',
		'blog_overview_recentcommentbit',
		'blog_overview_ratedblogbit',
	),
);

$_POST['ajax'] = 1;

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_init.php');
require_once(DIR . '/includes/class_xml.php');
require_once(DIR . '/includes/functions_user.php');
require_once(DIR . '/includes/blog_functions.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (empty($_POST['do']))
{
	$_POST['do'] = 'fetchuserfield';
}

($hook = vBulletinHook::fetch_hook('blog_ajax_start')) ? eval($hook) : false;

// #############################################################################
// retrieve a calendar
if ($_POST['do'] == 'calendar')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'month'  => TYPE_UINT,
		'year'   => TYPE_UINT,
		'userid' => TYPE_UINT,
	));

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	// can't view any blogs, no need for a calendar
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$xml->add_tag('error', 'nopermission');
		$xml->print_xml();
		exit;
	}

	if (!($month = $vbulletin->GPC['month']) OR $month < 1 OR $month > 12)
	{
		$month = vbdate('n', TIMENOW, false, false);
	}
	if (!($year = $vbulletin->GPC['year']) OR $year > 2037 OR $year < 1970)
	{
		$year = vbdate('Y', TIMENOW, false, false);
	}

	$calendar = construct_calendar($month, $year, $vbulletin->GPC['userid']);

	$xml->add_tag('calendar', $calendar);
	$xml->print_xml();
}

// #############################################################################
// fetch latest blogs
if ($_POST['do'] == 'loadupdated')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'type'  => TYPE_NOHTML,
		'which' => TYPE_NOHTML,
	));

	$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
	// can't view any blogs
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
	{
		$xml->add_tag('error', 'nopermission');
		$xml->print_xml();
		exit;
	}

	$noresults = 0;
	if (!($data =& fetch_latest_blogs($vbulletin->GPC['which'])))
	{
		if ($vbulletin->GPC['which'] == 'rating' OR $vbulletin->GPC['which'] == 'blograting')
		{
			$data = fetch_error('blog_no_rated_entries', $stylevar['left'], $stylevar['imgdir_rating']);
		}
		else
		{
			$data = fetch_error('blog_no_entries');
		}
		$noresults = 1;
	}


	$xml->add_tag('updated', '', array(
		'which'       => $vbulletin->GPC['which'],
		'type'        => $vbulletin->GPC['type'],
		'data'        => $data,
		'noresults'   => $noresults,
	));
	$xml->print_xml();
}

($hook = vBulletinHook::fetch_hook('blog_ajax_complete')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25414 $
|| ####################################################################
\*======================================================================*/