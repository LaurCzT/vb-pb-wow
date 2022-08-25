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
define('THIS_SCRIPT', 'blog_callback');
define('SKIP_SESSIONCREATE', 1);
define('VB_AREA', 'BlogCallback');
define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));
define('NOZIP', 1);
define('NOHEADER', 1);
define('NOCOOKIES', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once(CWD . '/includes/init.php');
require_once(DIR . '/includes/blog_functions.php');
require_once(DIR . '/includes/class_trackback.php');

$vbulletin->input->clean_array_gpc('p', array(
	'url'    => TYPE_STR,
));

$vbulletin->input->clean_array_gpc('r', array(
	'blogid' => TYPE_UINT,
));

// Came to the url directly
if ($vbulletin->GPC['blogid'])
{
	exec_header_redirect('blog.php?b=' . $vbulletin->GPC['blogid']);
}

($hook = vBulletinHook::fetch_hook('blog_callback_start')) ? eval($hook) : false;

$trackback = new vB_Trackback_Server($vbulletin);

if ($trackback->parse_blogid(SCRIPTPATH, $vbulletin->GPC['url']) AND $vbulletin->options['vbblog_trackback'])
{
	$trackback->send_xml_response();
}
else if (stristr($_SERVER['CONTENT_TYPE'], 'text/xml') === false OR $_SERVER['REQUEST_METHOD'] != 'POST' OR empty($GLOBALS['HTTP_RAW_POST_DATA']) OR !$vbulletin->options['vbblog_pingback'])
{	// Not an XML doc or was sent via GET so do nothing..
	exec_header_redirect($vbulletin->options['forumhome'] . '.php');
}

require_once(DIR . '/includes/class_xmlrpc_pingback.php');

// Pingback Server Instance
$xmlrpc_server = new vB_XMLRPC_Server_Pingback($vbulletin);
$xmlrpc_server->parse_xml($GLOBALS['HTTP_RAW_POST_DATA']);
$xmlrpc_server->parse_xmlrpc();
$xmlrpc_server->send_xml_response();

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 17991 $
|| ####################################################################
\*======================================================================*/
?>