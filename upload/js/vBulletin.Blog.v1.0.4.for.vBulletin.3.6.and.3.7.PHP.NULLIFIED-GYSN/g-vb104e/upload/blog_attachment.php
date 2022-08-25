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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
@ini_set('zlib.output_compression', 'Off');
@set_time_limit(0);
if (@ini_get('output_handler') == 'ob_gzhandler' AND @ob_get_length() !== false)
{	// if output_handler = ob_gzhandler, turn it off and remove the header sent by PHP
	@ob_end_clean();
	header('Content-Encoding:');
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'blog_attachment');
define('NOHEADER', 1);
define('NOZIP', 1);
define('NOCOOKIES', 1);
define('NOPMPOPUP', 1);

// attachment.php/$attachmentid/file.mp3 -- for podcast and confused clients that determine file type in <enclosure> by the url extension <iTunes, I'm looking in your direction>
if (!$_REQUEST['attachmentid'])
{
	$url_info = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];

	if ($url_info != '')
	{
		preg_match('#blog_attachment\.php/(\d+)/#si', $url_info, $matches);
		$_REQUEST['attachmentid'] = intval($matches[1]);
	}
}

if (empty($_REQUEST['attachmentid']))
{
	// return not found header
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 404 Not Found');
	}
	else
	{
		header('HTTP/1.1 404 Not Found');
	}
	exit;
}

if ($_REQUEST['stc'] == 1) // we were called as <img src=> from showthread.php
{
	define('NOSHUTDOWNFUNC', 1);
}

// Immediately send back the 304 Not Modified header if this image is cached, don't load global.php
// 3.5.x allows overwriting of attachments so we add the dateline to attachment links to avoid caching
if (!isset($_SERVER['HTTP_RANGE']) AND (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) OR !empty($_SERVER['HTTP_IF_NONE_MATCH'])))
{
	$sapi_name = php_sapi_name();
	if ($sapi_name == 'cgi' OR $sapi_name == 'cgi-fcgi')
	{
		header('Status: 304 Not Modified');
	}
	else
	{
		header('HTTP/1.1 304 Not Modified');
	}
	// remove the content-type and X-Powered headers to emulate a 304 Not Modified response as close as possible
	header('Content-Type:');
	header('X-Powered-By:');
	if (!empty($_REQUEST['attachmentid']))
	{
		header('ETag: "' . intval($_REQUEST['attachmentid']) . '"');
	}
	exit;
}

// #################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_functions.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$vbulletin->input->clean_array_gpc('r', array(
	'attachmentid' => TYPE_UINT,
	'thumb'        => TYPE_BOOL,
	'postid'       => TYPE_UINT,
));

$hook_query_fields = $hook_query_joins = $hook_query_where = '';
($hook = vBulletinHook::fetch_hook('blog_attachment_start')) ? eval($hook) : false;

$idname = $vbphrase['attachment'];

$imagetype = !empty($vbulletin->GPC['thumb']) ? 'thumbnail' : 'filedata';

if (!$attachmentinfo = $db->query_first_slave("
	SELECT filename, blog_attachment.blogid, blog_attachment.userid, attachmentid,
		" . ((!empty($vbulletin->GPC['thumb'])
			? 'blog_attachment.thumbnail AS filedata, thumbnail_dateline AS dateline, thumbnail_filesize AS filesize,'
			: 'blog_attachment.dateline, SUBSTRING(filedata, 1, 2097152) AS filedata, filesize,')) . "
		blog_attachment.visible, mimetype, blog.blogid, blog.state, blog.pending
		$hook_query_fields
	FROM " . TABLE_PREFIX . "blog_attachment AS blog_attachment
	LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype ON (attachmenttype.extension = blog_attachment.extension)
	LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog_attachment.blogid = blog.blogid)
	$hook_query_joins
	WHERE " . ($vbulletin->GPC['postid'] ? "blog_attachment.blogid = " . $vbulletin->GPC['blogid'] : "attachmentid = " . $vbulletin->GPC['attachmentid']) . "
		$hook_query_where
"))
{
	standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
}

if ($attachmentinfo['blogid'] == 0)
{	// Attachment that is in progress but hasn't been finalized
	if ($vbulletin->userinfo['userid'] != $attachmentinfo['userid'] AND !can_moderate_blog('caneditentries'))
	{	// Person viewing did not upload it
		standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
	}
	// else allow user to view the attachment (from the attachment manager for example)
}
else
{
	# Block attachments belonging to soft deleted entries
	if (!can_moderate_blog() AND $attachmentinfo['state'] == 'deleted')
	{
		standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
	}

	# Block attachments belonging to moderated entries
	if (!can_moderate_blog('canmoderateentries') AND $attachmentinfo['state'] == 'moderated')
	{
		standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
	}

	# Block attachments belonging to draft entires if your not the user
	if (($attachmentinfo['state'] == 'draft' OR $attachmentinfo['pending']) AND $vbulletin->userinfo['userid'] != $attachmentinfo['userid'])
	{
		standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
	}

	if (
		!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_cangetattach'])
			OR
		(
			!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown'])
				AND
			$attachmentinfo['userid'] == $vbulletin->userinfo['userid']
		)
			OR
		(
			!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers'])
				AND
			$attachmentinfo['userid'] != $vbulletin->userinfo['userid']
		)
	)
	{
		print_no_permission();
	}

	if (!$attachmentinfo['visible'] == 'moderation' AND !can_moderate_blog('canmoderateentries') AND $attachmentinfo['userid'] != $vbulletin->userinfo['userid'])
	{
		standard_error(fetch_error('invalidid', $idname, $vbulletin->options['contactuslink']));
	}
}

$extension = strtolower(file_extension($attachmentinfo['filename']));

if ($vbulletin->options['blogattachfile'])
{
	require_once(DIR . '/includes/functions_file.php');
	if ($vbulletin->GPC['thumb'])
	{
		$attachpath = fetch_attachment_path($attachmentinfo['userid'], $attachmentinfo['attachmentid'], true, $vbulletin->options['blogattachpath']);
	}
	else
	{
		$attachpath = fetch_attachment_path($attachmentinfo['userid'], $attachmentinfo['attachmentid'], false, $vbulletin->options['blogattachpath']);
	}

	if ($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	{
		if (!($fp = fopen($attachpath, 'rb')))
		{
			exit;
		}
	}
	else if (!($fp = @fopen($attachpath, 'rb')))
	{
		$filedata = base64_decode('R0lGODlhAQABAIAAAMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
		$filesize = strlen($filedata);
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');             // Date in the past
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
		header('Cache-Control: no-cache, must-revalidate');           // HTTP/1.1
		header('Pragma: no-cache');                                   // HTTP/1.0
		header("Content-disposition: inline; filename=clear.gif");
		header('Content-transfer-encoding: binary');
		header("Content-Length: $filesize");
		header('Content-type: image/gif');
		echo $filedata;
		exit;
	}
}

$startbyte = 0;
$lastbyte = $attachmentinfo['filesize'] - 1;

if (isset($_SERVER['HTTP_RANGE']))
{
	preg_match('#^bytes=(-?([0-9]+))(-([0-9]*))?$#', $_SERVER['HTTP_RANGE'], $matches);

	if (intval($matches[1]) < 0)
	{ // its negative so we want to take this value from last byte
		$startbyte = $attachmentinfo['filesize'] - $matches[2];
	}
	else
	{
		$startbyte = intval($matches[2]);
		if ($matches[4])
		{
			$lastbyte = $matches[4];
		}
	}

	if ($startbyte < 0 OR $startbyte >= $attachmentinfo['filesize'])
	{
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: 416 Requested Range Not Satisfiable');
		}
		else
		{
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
		}
		header('Accept-Ranges: bytes');
		header('Content-Range: bytes */'. $attachmentinfo['filesize']);
		exit;
	}
}

// send jpeg header for PDF, BMP, TIF, TIFF, and PSD thumbnails as they are jpegs
if ($vbulletin->GPC['thumb'] AND in_array($extension, array('bmp', 'tif', 'tiff', 'psd', 'pdf')))
{
	$attachmentinfo['filename'] = preg_replace('#.(bmp|tiff?|psd|pdf)$#i', '.jpg', $attachmentinfo['filename']);
	$mimetype = array('Content-type: image/jpeg');
}
else
{
	$mimetype = unserialize($attachmentinfo['mimetype']);
}

header('Cache-control: max-age=31536000');
header('Expires: ' . gmdate("D, d M Y H:i:s", TIMENOW + 31536000) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $attachmentinfo['dateline']) . ' GMT');
header('ETag: "' . $attachmentinfo['attachmentid'] . '"');
header('Accept-Ranges: bytes');

// look for entities in the file name, and if found try to convert
// the filename to UTF-8
$filename = $attachmentinfo['filename'];
if (preg_match('~&#([0-9]+);~', $filename))
{
	if (function_exists('iconv'))
	{
		$filename_conv = @iconv($stylevar['charset'], 'UTF-8//IGNORE', $filename);
		if ($filename_conv !== false)
		{
			$filename = $filename_conv;
		}
	}

	$filename = preg_replace(
		'~&#([0-9]+);~e',
		"convert_int_to_utf8('\\1')",
		$filename
	);
	$filename_charset = 'utf-8';
}
else
{
	$filename_charset = $stylevar['charset'];
}

$filename = preg_replace('#[\r\n]#', '', $filename);

// Opera and IE have not a clue about this, mozilla puts on incorrect extensions.
if (is_browser('mozilla'))
{
	$filename = "filename*=" . $filename_charset . "''" . rawurlencode($filename);
	//$filename = "filename==?$stylevar[charset]?B?" . base64_encode($filename) . "?=";
}
else
{
	// other browsers seem to want names in UTF-8
	if ($filename_charset != 'utf-8' AND function_exists('iconv'))
	{
		$filename_conv = iconv($filename_charset, 'UTF-8//IGNORE', $filename);
		if ($filename_conv !== false)
		{
			$filename = $filename_conv;
		}
	}

	if (is_browser('opera') OR is_browser('konqueror') OR is_browser('safari'))
	{
		// Opera / Konqueror does not support encoded file names
		$filename = 'filename="' . str_replace('"', '', $filename) . '"';
	}
	else
	{
		// encode the filename to stay within spec
		$filename = 'filename="' . rawurlencode($filename) . '"';
	}
}

if (in_array($extension, array('jpg', 'jpe', 'jpeg', 'gif', 'png')))
{
	header("Content-disposition: inline; $filename");
	header('Content-transfer-encoding: binary');
}
else
{
	// force files to be downloaded because of a possible XSS issue in IE
	header("Content-disposition: attachment; $filename");
}

if ($startbyte != 0 OR $lastbyte != ($attachmentinfo['filesize'] - 1))
{
	if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
	{
		header('Status: 206 Partial Content');
	}
	else
	{
		header('HTTP/1.1 206 Partial Content');
	}
	header('Content-Range: bytes '. $startbyte .'-'. $lastbyte .'/'. $attachmentinfo['filesize']);
}

header('Content-Length: ' . (($lastbyte + 1) - $startbyte));

if (is_array($mimetype))
{
	foreach ($mimetype AS $header)
	{
		if (!empty($header))
		{
			header($header);
		}
	}
}
else
{
	header('Content-type: unknown/unknown');
}

($hook = vBulletinHook::fetch_hook('blog_attachment_display')) ? eval($hook) : false;

// update views counter
if (!$vbulletin->GPC['thumb'] AND connection_status() == 0 AND $lastbyte == ($attachmentinfo['filesize'] - 1))
{
	if ($vbulletin->options['attachmentviewslive'])
	{
		// doing it as they happen; not using a DM to avoid overhead
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_attachment SET
				counter = counter + 1
			WHERE attachmentid = $attachmentinfo[attachmentid]
		");
	}
	else
	{
		// or doing it once an hour
		$query = "INSERT INTO " . TABLE_PREFIX . "blog_attachmentviews (attachmentid)
			VALUES ($attachmentinfo[attachmentid])
		";
		defined('NOSHUTDOWNFUNC') ? $db->query_write($query) : $db->shutdown_query($query);
	}
}

if ($vbulletin->options['blogattachfile'])
{
	if (defined('NOSHUTDOWNFUNC'))
	{
		if ($_GET['stc'] == 1)
		{
			$db->close();
		}
		else
		{
			exec_shut_down();
		}
	}

	if ($startbyte > 0)
	{
		fseek($fp, $startbyte);
	}

	while (connection_status() == 0 AND $startbyte <= $lastbyte)
	{	// You can limit bandwidth by decreasing the values in the read size call, they must be equal.
		$size = $lastbyte - $startbyte;
		$readsize = ($size > 1048576) ? 1048576 : $size + 1;
		echo @fread($fp, $readsize);
		$startbyte += $readsize;
		flush();
	}
	@fclose($fp);
}
else
{

	// start grabbing the filedata in batches of 2mb
	while (connection_status() == 0 AND $startbyte <= $lastbyte)
	{
		$size = $lastbyte - $startbyte;
		$readsize = ($size > 2097152) ? 2097152 : $size + 1;
		$attachmentinfo = $db->query_first_slave("
			SELECT attachmentid, SUBSTRING(" . ((!empty($vbulletin->GPC['thumb']) ? 'thumbnail' : 'filedata')) . ", $startbyte + 1, $readsize) AS filedata
			FROM " . TABLE_PREFIX . "blog_attachment
			WHERE attachmentid = $attachmentinfo[attachmentid]
		");
		echo $attachmentinfo['filedata'];
		$startbyte += $readsize;
		flush();
	}

	if (defined('NOSHUTDOWNFUNC'))
	{
		if ($_GET['stc'] == 1)
		{
			$db->close();
		}
		else
		{
			exec_shut_down();
		}
	}
}

($hook = vBulletinHook::fetch_hook('blog_attachment_complete')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 23789 $
|| ####################################################################
\*======================================================================*/
?>