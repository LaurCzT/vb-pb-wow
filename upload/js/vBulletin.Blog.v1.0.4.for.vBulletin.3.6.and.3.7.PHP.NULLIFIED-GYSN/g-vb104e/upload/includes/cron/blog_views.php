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

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
define('MYSQL_VERSION', $mysqlversion['version']);
$enginetype = (version_compare(MYSQL_VERSION, '4.0.18', '<')) ? 'TYPE' : 'ENGINE';
$tabletype = (version_compare(MYSQL_VERSION, '4.1', '<')) ? 'HEAP' : 'MEMORY';

$aggtable = "blog_aggregate_temp_$nextitem[nextrun]";

$vbulletin->db->query_write("
	CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$aggtable (
		blogid INT UNSIGNED NOT NULL DEFAULT '0',
		views INT UNSIGNED NOT NULL DEFAULT '0',
		KEY blogid (blogid)
	) $enginetype = $tabletype");

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->lock_tables(array(
		$aggtable    => 'WRITE',
		'blog_views' => 'WRITE'
	));
}

$vbulletin->db->query_write("
	INSERT INTO " . TABLE_PREFIX . "$aggtable
		SELECT blogid, COUNT(*) AS views
		FROM " . TABLE_PREFIX . "blog_views
		GROUP BY blogid
");

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->unlock_tables();
}
/* Small race condition but better than lots of IO wait for a DELETE query */
$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "blog_views");

$vbulletin->db->query_write(
	"UPDATE " . TABLE_PREFIX . "blog AS blog," . TABLE_PREFIX . "$aggtable AS aggregate
	SET blog.views = blog.views + aggregate.views
	WHERE blog.blogid = aggregate.blogid
");

$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $aggtable);

/* Start of attachment views */

$aggtable = "blog_aaggregate_temp_$nextitem[nextrun]";

$vbulletin->db->query_write("
	CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$aggtable (
		attachmentid INT UNSIGNED NOT NULL DEFAULT '0',
		views INT UNSIGNED NOT NULL DEFAULT '0',
		KEY attachmentid (attachmentid)
	) $enginetype = $tabletype");

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->lock_tables(array(
		$aggtable              => 'WRITE',
		'blog_attachmentviews' => 'WRITE'
	));
}

$vbulletin->db->query_write("
	INSERT INTO " . TABLE_PREFIX . "$aggtable
		SELECT attachmentid, COUNT(*) AS views
		FROM " . TABLE_PREFIX . "blog_attachmentviews
		GROUP BY attachmentid
");

if ($vbulletin->options['usemailqueue'] == 2)
{
	$vbulletin->db->unlock_tables();
}
/* Small race condition but better than lots of IO wait for a DELETE query */
$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "blog_attachmentviews");

$vbulletin->db->query_write(
	"UPDATE " . TABLE_PREFIX . "blog_attachment AS blog_attachment," . TABLE_PREFIX . "$aggtable AS aggregate
	SET blog_attachment.counter = blog_attachment.counter + aggregate.views
	WHERE blog_attachment.attachmentid = aggregate.attachmentid
");

$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $aggtable);

log_cron_action('', $nextitem, 1);

/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile: threadviews.php,v $ - $Revision: 17991 $
|| ####################################################################
\*======================================================================*/
?>