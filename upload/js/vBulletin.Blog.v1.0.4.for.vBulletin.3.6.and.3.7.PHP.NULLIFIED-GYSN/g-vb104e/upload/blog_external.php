<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 1.0.4
|| # ---------------------------------------------------------------- # ||
|| # Copyright ?2000?2008 Jelsoft Enterprises Ltd. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('NOSHUTDOWNFUNC', 1);
define('SKIP_SESSIONCREATE', 1);
define('DIE_QUIETLY', 1);
define('THIS_SCRIPT', 'blog_external');
define('VBBLOG_PERMS', 1);
define('VBBLOG_STYLE', 1);
define('VBBLOG_SKIP_PERMCHECK', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('vbblogglobal', 'postbit');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'bbcode_code_printable',
	'bbcode_html_printable',
	'bbcode_php_printable',
	'bbcode_quote_printable',
	'blog_entry_category',
	'blog_entry_attachment',
	'blog_entry_attachment_image',
	'blog_entry_attachment_thumbnail',
	'blog_entry_external',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_init.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// We don't want no stinkin' sessionhash
$vbulletin->session->vars['sessionurl'] =
$vbulletin->session->vars['sessionurl_q'] =
$vbulletin->session->vars['sessionurl_js'] =
$vbulletin->session->vars['sessionhash'] = '';

$vbulletin->input->clean_array_gpc('r', array(
	'bloguserid'  => TYPE_UINT,
	'lastcomment' => TYPE_BOOL,
	'nohtml'      => TYPE_BOOL
));

($hook = vBulletinHook::fetch_hook('blog_external_start')) ? eval($hook) : false;

$description = $vbulletin->options['description'];

if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
{	// no access to view blogs
	require_once(DIR . '/includes/class_xml.php');
	$rsstitle = construct_phrase($vbphrase['blog_rss_title'], $vbulletin->options['bbtitle']);
	$xml = new vB_XML_Builder($vbulletin);
	$rsstag = array(
		'version'       => '2.0',
		'xmlns:dc'      => 'http://purl.org/dc/elements/1.1/',
		'xmlns:content' => 'http://purl.org/rss/1.0/modules/content/'
	);
	$xml->add_group('rss', $rsstag);
		$xml->add_group('channel');
			$xml->add_tag('title', $rsstitle);
			$xml->add_tag('link', $vbulletin->options['bburl'] . "/blog.php", array(), false, true);
			$xml->add_tag('description', $description);
			$xml->add_tag('language', $stylevar['languagecode']);
			$xml->add_tag('lastBuildDate', gmdate('D, d M Y H:i:s') . ' GMT');
			#$xml->add_tag('pubDate', gmdate('D, d M Y H:i:s') . ' GMT');
			$xml->add_tag('generator', 'vBulletin');
		$xml->close_group('channel');
	$xml->close_group('rss');
	header('Content-Type: text/xml' . ($stylevar['charset'] != '' ? '; charset=' .  $stylevar['charset'] : ''));
	echo '<?xml version="1.0" encoding="' . $stylevar['charset'] . '"?>' . "\r\n\r\n";
	echo $xml->output();
	exit;
}

if (!$vbulletin->options['externalcount'])
{
	$vbulletin->options['externalcount'] = 15;
}
$count = $vbulletin->options['externalcount'];

if (!intval($vbulletin->options['externalcache']) OR $vbulletin->options['externalcache'] > 1440)
{
	$externalcache = 60;
}
else
{
	$externalcache = $vbulletin->options['externalcache'];
}

$cachetime = $externalcache * 60;
$cachehash = md5(
	'blog|' .
	$vbulletin->options['externalcutoff'] . '|' .
	$externalcache . '|' .
	$count . '|' .
	$vbulletin->GPC['bloguserid'] . '|' .
	$vbulletin->GPC['nohtml']
);

if ($_SERVER['HTTP_IF_NONE_MATCH'] == "\"$cachehash\"" AND !empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
{
	$timediff = strtotime(gmdate('D, d M Y H:i:s') . ' GMT') - strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
	if ($timediff <= $cachetime)
	{
		$db->close();
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: 304 Not Modified');
		}
		else
		{
			header('HTTP/1.1 304 Not Modified');
		}
		exit;
	}
}

if ($foundcache = $db->query_first_slave("
	SELECT text, headers, dateline
	FROM " . TABLE_PREFIX . "externalcache
	WHERE cachehash = '" . $db->escape_string($cachehash) . "' AND
		 dateline >= " . (TIMENOW - $cachetime) . "
"))
{
	$db->close();
	if (!empty($foundcache['headers']))
	{
		$headers = unserialize($foundcache['headers']);
		if (!empty($headers))
		{
			foreach($headers AS $header)
			{
				header($header);
			}
		}
	}
	echo $foundcache['text'];
	exit;
}

$cutoff = (!$vbulletin->options['externalcutoff']) ? 0 : TIMENOW - $vbulletin->options['externalcutoff'] * 86400;

// build the where clause
if ($vbulletin->GPC['bloguserid'])
{
	$userinfo = fetch_userinfo($vbulletin->GPC['bloguserid']);
	$condition = "blog.userid = " . $vbulletin->GPC['bloguserid'];
}
else
{
	$condition = '1=1';
}

$globalignore = '';
if (trim($vbulletin->options['globalignore']) != '')
{
	require_once(DIR . '/includes/functions_bigthree.php');
	if ($Coventry = fetch_coventry('string'))
	{
		$globalignore = "AND blog.userid NOT IN ($Coventry) AND blog_text.userid NOT IN ($Coventry)";
	}
}

$hook_query_fields = $hook_query_joins = $hook_query_where = '';
($hook = vBulletinHook::fetch_hook('blog_external_query')) ? eval($hook) : false;
$blog_posts = $db->query_read_slave("
	SELECT blog.*, blog_text.*, user.*
	$hook_query_fields
	FROM " . TABLE_PREFIX . "blog AS blog
	INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog.firstblogtextid = blog_text.blogtextid)
	INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
	LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog_text.userid)
	$hook_query_joins
	WHERE $condition
		AND blog.state = 'visible'
		AND blog.dateline <= " . TIMENOW . "
		AND blog.pending = 0
		AND blog_user.options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . "
		$globalignore
		$hook_query_where
	ORDER BY blog.dateline DESC
	LIMIT $count
");

$expires = TIMENOW + $cachetime;

$output = '';
$headers = array();

// RSS output
// setup the board title
if ($vbulletin->GPC['bloguserid'])
{
	if ($userinfo['blog_title'] != $userinfo['username'])
	{
		$rsstitle = construct_phrase($vbphrase['blog_rss_title_with_blogtitle'], $vbulletin->options['bbtitle'], $userinfo['blog_title'], $userinfo['username']);
	}
	else
	{
		$rsstitle = construct_phrase($vbphrase['blog_rss_title_without_blogtitle'], $vbulletin->options['bbtitle'], $userinfo['username']);
	}
}
else
{
	$rsstitle = construct_phrase($vbphrase['blog_rss_title'], $vbulletin->options['bbtitle']);
}
$rssicon = create_full_url($stylevar['imgdir_misc'] . '/rss.jpg');

$headers[] = 'Cache-control: max-age=' . $expires;
$headers[] = 'Expires: ' . gmdate("D, d M Y H:i:s", $expires) . ' GMT';
//$headers[] = 'Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT';
$headers[] = 'ETag: "' . $cachehash . '"';
$headers[] = 'Content-Type: text/xml' . ($stylevar['charset'] != '' ? '; charset=' .  $stylevar['charset'] : '');

$output = '<?xml version="1.0" encoding="' . $stylevar['charset'] . '"?>' . "\r\n\r\n";

require_once(DIR . '/includes/class_xml.php');
$xml = new vB_XML_Builder($vbulletin);
$rsstag = array(
	'version'       => '2.0',
	'xmlns:dc'      => 'http://purl.org/dc/elements/1.1/',
	'xmlns:content' => 'http://purl.org/rss/1.0/modules/content/'
);
$xml->add_group('rss', $rsstag);
	$xml->add_group('channel');
		$xml->add_tag('title', $rsstitle);
		$xml->add_tag('link', $vbulletin->options['bburl'] . "/blog.php", array(), false, true);
		$xml->add_tag('description', $description);
		$xml->add_tag('language', $stylevar['languagecode']);
		$xml->add_tag('lastBuildDate', gmdate('D, d M Y H:i:s') . ' GMT');
		#$xml->add_tag('pubDate', gmdate('D, d M Y H:i:s') . ' GMT');
		$xml->add_tag('generator', 'vBulletin');
		$xml->add_tag('ttl', $externalcache);
		$xml->add_group('image');
			$xml->add_tag('url', $rssicon);
			$xml->add_tag('title', $rsstitle);
			$xml->add_tag('link', $vbulletin->options['bburl'] . "/blog.php", array(), false, true);
		$xml->close_group('image');

require_once(DIR . '/includes/class_bbcode_alt.php');

$postattach = array();
$attachments = $db->query_read("
	SELECT blog_attachment.dateline, blog_attachment.thumbnail_dateline, blog_attachment.filename,
		blog_attachment.filesize, blog_attachment.visible, blog_attachment.attachmentid, blog_attachment.counter,
		IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, blog_attachment.thumbnail_filesize,
		blog.blogid, blog.firstblogtextid, attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
	FROM " . TABLE_PREFIX . "blog AS blog
	INNER JOIN " . TABLE_PREFIX . "blog_attachment AS blog_attachment ON (blog_attachment.blogid = blog.blogid)
	INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
	INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog.firstblogtextid = blog_text.blogtextid)
	LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype  ON (blog_attachment.extension = attachmenttype.extension)
	WHERE $condition
		AND blog.state = 'visible'
		AND blog.dateline <= " . TIMENOW . "
		AND blog.pending = 0
		AND blog_user.options_everyone & " . $vbulletin->bf_misc_vbblogsocnetoptions['canviewmyblog'] . "
		$globalignore
	ORDER BY attachmentid
");
while ($attachment = $db->fetch_array($attachments))
{
	if (!$attachment['build_thumbnail'])
	{
		$attachment['hasthumbnail'] = false;
	}
	$postattach["$attachment[firstblogtextid]"]["$attachment[attachmentid]"] = $attachment;
}

require_once(DIR . '/includes/class_blog_entry.php');
require_once(DIR . '/includes/class_bbcode_blog.php');
$bbcode =& new vB_BbCodeParser_Blog($vbulletin, fetch_tag_list());

$i = 0;
$viewattachedimages = $vbulletin->options['viewattachedimages'];
$attachthumbs = $vbulletin->options['attachthumbs'];

// list returned blog entries
$perm_cache = array();
while ($blog_post = $db->fetch_array($blog_posts))
{
	$xml->add_group('item');
		$xml->add_tag('title', unhtmlspecialchars($blog_post['title']));
		$xml->add_tag('link', $vbulletin->options['bburl'] . "/blog.php?b=$blog_post[blogid]", array(), false, true);
		$xml->add_tag('pubDate', gmdate('D, d M Y H:i:s', $blog_post['dateline']) . ' GMT');

	if (!isset($perm_cache["$blog_post[userid]"]))
	{
		$perm_cache["$blog_post[userid]"] = cache_permissions($blog_post, false);
	}
	$plaintext_parser =& new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());
	$plaintext_parser->set_parse_userinfo($blog_post, $perm_cache["$blog_post[userid]"]);

	$plainmessage = $plaintext_parser->parse($blog_post['pagetext'], 'blog_comment');
	unset($plaintext_parser);

	if ($vbulletin->GPC['fulldesc'])
	{
		$xml->add_tag('description', $plainmessage);
	}
	else
	{
		$xml->add_tag('description', fetch_trimmed_title($plainmessage, $vbulletin->options['threadpreview']));
	}

	if (!$vbulletin->GPC['nohtml'])
	{
		$entry_factory =& new vB_Blog_EntryFactory($vbulletin, $bbcode, $entry_categories);
		$entry_handler =& $entry_factory->create($blog_post, 'external');
		$entry_handler->attachments = $postattach["$blog_post[firstblogtextid]"];
		$xml->add_tag('content:encoded', $entry_handler->construct());
	}

	$xml->add_tag('dc:creator', unhtmlspecialchars($blog_post['username']));
	$xml->add_tag('guid', $vbulletin->options['bburl'] . "/blog.php?b=$blog_post[blogid]", array('isPermaLink' => 'true'));

	$xml->close_group('item');
}

	$xml->close_group('channel');
$xml->close_group('rss');
$output .= $xml->output();
unset($xml);

$db->query_write("
	REPLACE INTO " . TABLE_PREFIX . "externalcache
		(cachehash, dateline, text, headers, forumid)
	VALUES
		(
			'" . $db->escape_string($cachehash) . "',
			" . TIMENOW . ",
			'" . $db->escape_string($output) . "',
			'" . $db->escape_string(serialize($headers)) . "',
			0
		)
");
$db->close();

($hook = vBulletinHook::fetch_hook('blog_external_complete')) ? eval($hook) : false;

foreach ($headers AS $header)
{
	header($header);
}
echo $output;

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 24083 $
|| ####################################################################
\*======================================================================*/
?>