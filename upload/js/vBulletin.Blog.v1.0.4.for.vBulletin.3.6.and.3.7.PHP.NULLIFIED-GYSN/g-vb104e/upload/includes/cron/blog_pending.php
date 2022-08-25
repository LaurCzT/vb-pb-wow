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
if (!is_object($vbulletin->db))
{
	exit;
}

require_once(DIR . '/includes/blog_functions.php');

$blogman =& datamanager_init('Blog_Firstpost', $vbulletin, ERRTYPE_SILENT, 'blog');

$blogids = array();
$pendingposts = $vbulletin->db->query_read_slave("
	SELECT blog.*, blog_text.pagetext, blog_user.bloguserid
	FROM " . TABLE_PREFIX . "blog AS blog
	INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
	LEFT JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog.firstblogtextid = blog_text.blogtextid)
	WHERE blog.pending = 1
		AND blog.dateline <= " . TIMENOW . "
");
while ($blog = $vbulletin->db->fetch_array($pendingposts))
{
	$blogman->set_existing($blog);

	// This sets bloguserid for the post_save_each_blogtext() function
	$blogman->set_info('user', $blog);
	$blogman->set_info('send_notification', true);
	$blogman->set_info('skip_build_category_counters', true);
	$blogman->save();

	if ($blog['state'] == 'visible')
	{
		$blogids[] = $blog['blogid'];
		$userids["$blog[userid]"] = $blog['userid'];
	}
}

if (!empty($blogids))
{
	// Update Counters
	foreach ($userids AS $userid)
	{
		build_blog_user_counters($userid);
	}

	$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
	define('MYSQL_VERSION', $mysqlversion['version']);
	$enginetype = (version_compare(MYSQL_VERSION, '4.0.18', '<')) ? 'TYPE' : 'ENGINE';
	$tabletype = (version_compare(MYSQL_VERSION, '4.1', '<')) ? 'HEAP' : 'MEMORY';

	$aggtable = "aaggregate_temp_$nextitem[nextrun]";

	$vbulletin->db->query_write("
		CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$aggtable (
			blogcategoryid INT UNSIGNED NOT NULL DEFAULT '0',
			entrycount INT UNSIGNED NOT NULL DEFAULT '0',
			KEY blogcategoryid (blogcategoryid)
		) $enginetype = $tabletype
	");

	if ($vbulletin->options['usemailqueue'] == 2)
	{
		$vbulletin->db->lock_tables(array(
			$aggtable           => 'WRITE',
			'blog_category'     => 'WRITE',
			'blog_categoryuser' => 'WRITE'
		));
	}

	$vbulletin->db->query_read("
		INSERT INTO " . TABLE_PREFIX . "$aggtable
			SELECT blogcategoryid, COUNT(*) AS totalposts
			FROM " . TABLE_PREFIX . "blog_categoryuser
			WHERE blogid IN (" . implode(",", $blogids) . ")
			GROUP BY blogcategoryid
	");

	if ($vbulletin->options['usemailqueue'] == 2)
	{
		$vbulletin->db->unlock_tables();
	}

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_category AS blog_category," . TABLE_PREFIX . "$aggtable AS aggregate
		SET blog_category.entrycount = blog_category.entrycount + aggregate.entrycount
		WHERE blog_category.blogcategoryid = aggregate.blogcategoryid
	");

	$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $aggtable);
}

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $Revision: 17991 $
|| ####################################################################
\*======================================================================*/
?>