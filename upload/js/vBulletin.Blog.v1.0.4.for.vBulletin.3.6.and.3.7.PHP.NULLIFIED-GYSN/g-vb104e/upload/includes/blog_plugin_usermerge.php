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

require_once(DIR . '/includes/blog_functions.php');

$blogs = array();

define('VBBLOG_PERMS', true);
$sourceinfo = fetch_userinfo($sourceinfo['userid'], 1);
$destinfo = fetch_userinfo($destinfo['userid'], 1);

if ($sourceinfo['bloguserid'])
{
	// ###################### Subscribed Blogs #######################
	// Update Subscribed Blogs - Move source's blogs to dest, skipping any that both have
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_subscribeuser AS su1
		LEFT JOIN " . TABLE_PREFIX . "blog_subscribeuser AS su2 ON (su2.bloguserid = su1.bloguserid AND su2.userid = $destinfo[userid])
		SET su1.userid = $destinfo[userid]
		WHERE su1.userid = $sourceinfo[userid]
			AND su1.bloguserid <> $destinfo[userid]
			AND su2.blogsubscribeuserid IS NULL
	");

	// Update Subscribed Blogs - Update everyone else who was subscribed to source to be subscribed to dest
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_subscribeuser AS su1
		LEFT JOIN " . TABLE_PREFIX . "blog_subscribeuser AS su2 ON (su2.bloguserid = $destinfo[userid] AND su2.userid = su1.userid)
		SET su1.bloguserid = $destinfo[userid]
		WHERE su1.bloguserid = $sourceinfo[userid]
			AND su1.userid <> $destinfo[userid]
			AND su2.blogsubscribeuserid IS NULL
	");

	// Update Subscribed Blogs - Remove the blogs that source and dest both have - hit index
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_subscribeuser
		WHERE bloguserid = $sourceinfo[userid]
	");

	// Update Subscribed Blogs - Remove the blogs that source and dest both have - hit index
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_subscribeuser
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Subscribed Entries #######################
	// Update Subscribed Entries - Move source's entries to dest, skipping any that both have
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_subscribeentry AS se1
		LEFT JOIN " . TABLE_PREFIX . "blog_subscribeentry AS se2 ON (se1.blogid = se2.blogid AND se2.userid = $destinfo[userid])
		SET se1.userid = $destinfo[userid]
		WHERE se1.userid = $sourceinfo[userid]
			AND se2.blogsubscribeentryid IS NULL
	");

	// Update Subscribed Entries - Remove the entries that source and dest both have
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_subscribeentry
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Attachments #######################
	if ($vbulletin->options['attachfile'])
	{
		$attachments = $db->query_read("
			SELECT attachmentid, userid, filename, filesize, filehash
			FROM " . TABLE_PREFIX . "blog_attachment
			WHERE userid = $sourceinfo[userid]
		");

		require_once(DIR . '/includes/functions_file.php');
		while ($attachment = $db->fetch_array($attachments))
		{
			$sourcefile = fetch_attachment_path($sourceinfo['userid'], $attachment['attachmentid'], false, $vbulletin->options['blogattachpath']);
			$sourcethumb = fetch_attachment_path($sourceinfo['userid'], $attachment['attachmentid'], true, $vbulletin->options['blogattachpath']);

			$attach =& datamanager_init('Attachment_Blog', $vbulletin, ERRTYPE_SILENT);
			$attach->set_existing($attachment);
			$attach->set('userid', $destinfo['userid']);
			$attach->set('filedata', @file_get_contents($sourcefile));
			$attach->set('thumbnail', @file_get_contents($sourcethumb));
			$attach->save();
			unset($attach);

			// CHEATER!
			@unlink($sourcefile);
			@unlink($sourcethumb);
		}
	}
	else
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_attachment
			SET userid = $destinfo[userid]
			WHERE userid = $sourceinfo[userid]
		");
	}

	// ###################### Comments #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_text
		SET userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_text
		SET bloguserid = $destinfo[userid]
		WHERE bloguserid = $sourceinfo[userid]
	");

	// ###################### Entries #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog
		SET userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Deletion Log #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_deletionlog
		SET userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Deletion Log #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_editlog
		SET userid = $destinfo[userid],
			username = '" . $db->escape_string($destinfo['username']) . "'
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Entry Ratings #######################
	$db->query_write("
		UPDATE IGNORE " . TABLE_PREFIX . "blog_rate SET
			userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");
	$blogratings = $db->query_read("SELECT blogid FROM " . TABLE_PREFIX . "blog_rate WHERE userid = $sourceinfo[userid]");
	while ($blograting = $db->fetch_array($blogratings))
	{
		$blogs["$blograting[blogid]"] = true;
	}
	if (!empty($blogs))
	{
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_rate WHERE userid = $sourceinfo[userid]");
	}

	// ###################### Read Blogs #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_read AS br1
		LEFT JOIN " . TABLE_PREFIX . "blog_read AS br2 ON (br2.userid = $destinfo[userid] AND br2.blogid = br1.blogid)
		SET br1.userid = $destinfo[userid]
		WHERE br1.userid = $sourceinfo[userid]
			AND br2.userid IS NULL
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_userread AS bu1
		LEFT JOIN " . TABLE_PREFIX . "blog_userread AS bu2 ON (bu2.userid = $destinfo[userid] AND bu2.bloguserid = bu2.bloguserid)
		SET bu1.userid = $destinfo[userid]
		WHERE bu1.userid = $sourceinfo[userid]
			AND bu2.userid IS NULL
	");
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_read
		WHERE userid = $sourceinfo[userid]
	");
	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "blog_userread
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Blog Moderator #######################
	$destmod = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "blog_moderator WHERE userid = $destinfo[userid]");
	$sourcemod = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "blog_moderator WHERE userid = $sourceinfo[userid]");

	if ($destmod)
	{
		if ($sourcemod)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "blog_moderator
				SET permissions = permissions | $sourceinfo[permissions]
				WHERE userid = $destinfo[userid]
			");
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "blog_moderator WHERE userid = $sourceinfo[userid]");
		}
	}
	else if ($sourcemod)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_moderator
			SET userid = $destinfo[userid]
			WHERE userid = $sourceinfo[userid]
		");
	}

	// ###################### Tachy Entry #######################
	if (!defined('MYSQL_VERSION'))
	{
		$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
		define('MYSQL_VERSION', $mysqlversion['version']);
	}

	if (version_compare(MYSQL_VERSION, '4.1.0', '>='))
	{
		$db->query_write("
			DELETE te2
			FROM " . TABLE_PREFIX . "blog_tachyentry AS te1, " . TABLE_PREFIX . "blog_tachyentry AS te2
			WHERE te1.userid = $sourceinfo[userid] AND te1.lastcomment > te2.lastcomment AND te1.blogid = te2.blogid AND te2.userid = $destinfo[userid]
		");
		$db->query_write("
			DELETE te1
			FROM " . TABLE_PREFIX . "blog_tachyentry AS te1, " . TABLE_PREFIX . "blog_tachyentry AS te2
			WHERE te1.userid = $sourceinfo[userid] AND te1.blogid = te2.blogid AND te2.userid = $destinfo[userid] AND te1.lastcomment <= te2.lastcomment
		");
	}
	else
	{
		$hash = $vbulletin->userinfo['userid'] . '_' . TIMENOW;
		$temptable = "b_temp_$hash";
		$vbulletin->db->query_write("
			CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$temptable (
				blogid INT UNSIGNED NOT NULL DEFAULT '0',
				KEY blogid (blogid)
			)
		");

		$vbulletin->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "$temptable
				SELECT te2.blogid
				FROM " . TABLE_PREFIX . "blog_tachyentry AS te1, " . TABLE_PREFIX . "blog_tachyentry AS te2
				WHERE te1.userid = $sourceinfo[userid] AND te1.lastcomment > te2.lastcomment AND te1.blogid = te2.blogid AND te2.userid = $destinfo[userid]
		");

		$vbulletin->db->query_write("
			DELETE " . TABLE_PREFIX . "blog_tachyentry, " . TABLE_PREFIX . "$temptable
			FROM " . TABLE_PREFIX . "blog_tachyentry
			INNER JOIN " . TABLE_PREFIX . "$temptable USING (blogid)
		");

		$vbulletin->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "$temptable
				SELECT te1.blogid
				FROM " . TABLE_PREFIX . "blog_tachyentry AS te1, " . TABLE_PREFIX . "blog_tachyentry AS te2
				WHERE te1.userid = $sourceinfo[userid] AND te1.blogid = te2.blogid AND te2.userid = $destinfo[userid] AND te1.lastcomment <= te2.lastcomment
		");

		$vbulletin->db->query_write("
			DELETE " . TABLE_PREFIX . "blog_tachyentry, " . TABLE_PREFIX . "$temptable
			FROM " . TABLE_PREFIX . "blog_tachyentry
			INNER JOIN " . TABLE_PREFIX . "$temptable USING (blogid)
		");

		$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $temptable);
	}

	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_tachyentry
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Trackbacks #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_trackback
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Trackback Log #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_trackbacklog
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Search #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_search
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	// ###################### Categories #######################
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_category
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_categoryuser
		SET userid = $destinfo[userid]
		WHERE userid = $sourceinfo[userid]
	");

	if ($destinfo['bloguserid'])
	{
		$userdm =& datamanager_init('Blog_User', $vbulletin, ERRTYPE_STANDARD);
		$userdm->set_existing($destinfo);
		$userdm->set('entries', "entries + $sourceinfo[entries]", false);
		if ($sourceinfo['blog_akismet_key'] AND !$destinfo['blog_akismet_key'])
		{
			$userdm->set('akismet_key', $sourceinfo['akismet_key'], false);
		}
		if ($sourceinfo['isblogmoderator'] AND !$destinfo['isblogmoderator'])
		{
			$userdm->set('isblogmoderator', 1, false);
		}
		$userdm->save();
		unset($userdm);
	}
	else
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_user
			SET bloguserid = $destinfo[userid]
			WHERE bloguserid = $sourceinfo[userid]
		");
	}

	// Update required blog entries
	foreach (array_keys($blogs) AS $blogid)
	{
		build_blog_entry_counters($blogid);
	}

	// Update counters for destination user
	build_blog_user_counters($destinfo['userid']);
}

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 17991 $
|| ####################################################################
\*======================================================================*/
?>