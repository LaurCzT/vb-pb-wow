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

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 25010 $');
define('NOZIP', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('vbblogadmin', 'cppermission', 'moderator', 'maintenance');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_init.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canblog'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################

$vbulletin->input->clean_array_gpc('r', array(
));

/*
log_admin_action(iif($vbulletin->GPC['moderatorid'] != 0, " moderator id = " . $vbulletin->GPC['moderatorid'],
					iif($vbulletin->GPC['calendarid'] != 0, "calendar id = " . $vbulletin->GPC['calendarid'], '')));

*/
// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'counters';
}

if (in_array($_REQUEST['do'], array('rebuildthumbs', 'emptycache', 'counters')))
{
	print_cp_header($vbphrase['maintenance']);
}
else if (in_array($_REQUEST['do'], array('list', 'dolist')))
{
	print_cp_header($vbphrase['view_blog_entries']);
}
else
{
	print_cp_header($vbphrase['blog_moderators']);
}

$vbulletin->input->clean_array_gpc('r', array(
	'perpage' => TYPE_UINT,
	'startat' => TYPE_UINT
));

// ##################### Start Index ###################################

if (empty($_REQUEST['do']) OR $_REQUEST['do'] == 'counters')
{
	print_form_header('blog_admin', 'updatepost');
	print_table_header($vbphrase['rebuild_blog_entry_information'], 2, 0);
	print_input_row($vbphrase['number_of_posts_to_process_per_cycle'], 'perpage', 2000);
	print_submit_row($vbphrase['rebuild_blog_entry_information']);

	print_form_header('blog_admin', 'updateuser');
	print_table_header($vbphrase['rebuild_blog_user_information'], 2, 0);
	print_input_row($vbphrase['number_of_users_to_process_per_cycle'], 'perpage', 2000);
	print_submit_row($vbphrase['rebuild_blog_user_information']);

	print_form_header('blog_admin', 'rebuildcounters');
	print_table_header($vbphrase['rebuild_blog_counters']);
	print_description_row($vbphrase['rebuild_blog_metadata']);
	print_submit_row($vbphrase['rebuild_blog_counters']);

	print_form_header('blog_admin', 'emptycache');
	print_table_header($vbphrase['clear_parsed_text_cache']);
	print_description_row($vbphrase['clear_cached_text_entries']);
	print_submit_row($vbphrase['clear_parsed_text_cache']);

	print_form_header('blog_admin', 'rebuildthumbs');
	print_table_header($vbphrase['rebuild_attachment_thumbnails'], 2, 0);
	print_description_row($vbphrase['function_rebuilds_thumbnails']);
	print_input_row($vbphrase['number_of_attachments_to_process_per_cycle'], 'perpage', 25);
	$quality = intval($vbulletin->options['thumbquality']);
	if ($quality <= 0 OR $quality > 100)
	{
		$quality = 75;
	}
	print_input_row($vbphrase['thumbnail_quality'], 'quality', $quality);
	print_yes_no_row($vbphrase['include_automatic_javascript_redirect'], 'autoredirect', 1);
	print_submit_row($vbphrase['rebuild_attachment_thumbnails']);

	print_form_header('blog_admin', 'rebuildprofilepic');
	print_table_header($vbphrase['rebuild_profile_picture_dimensions']);
	print_input_row($vbphrase['number_of_pictures_to_process_per_cycle'], 'perpage', 25);
	print_submit_row($vbphrase['rebuild_profile_picture_dimensions']);
}

if ($_REQUEST['do'] == 'updatepost')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 2000;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . $vbphrase['updating_blog_entries'] . '</p>';

	$blogs = $db->query_read("
		SELECT blogid
		FROM " . TABLE_PREFIX . "blog
		WHERE blogid >= " . $vbulletin->GPC['startat'] . " AND
		blogid < $finishat
		ORDER BY blogid
	");
	while ($blog = $db->fetch_array($blogs))
	{
		build_blog_entry_counters($blog['blogid']);
		echo construct_phrase($vbphrase['processing_x'], $blog['blogid']) . "<br />\n";
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT blogid FROM " . TABLE_PREFIX . "blog WHERE blogid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=updatepost&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=updatepost&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'blog_admin.php');
		print_stop_message('updated_blog_entries_successfully');
	}
}

if ($_REQUEST['do'] == 'updateuser')
{
	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 2000;
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	if ($vbulletin->GPC['pagenumber'] = 1)
	{
		echo '<p>' . $vbphrase['updating_category_counters'] . '</p>';

		if (!defined('MYSQL_VERSION'))
		{
			$mysqlversion = $vbulletin->db->query_first("SELECT version() AS version");
			define('MYSQL_VERSION', $mysqlversion['version']);
		}
		if (!defined('MYSQL_ENGINE'))
		{
			define('MYSQL_ENGINE', (version_compare(MYSQL_VERSION, '4.0.18', '<')) ? 'TYPE' : 'ENGINE');
		}
		if (!defined('MYSQL_TABLETYPE'))
		{
			define('MYSQL_TABLETYPE', (version_compare(MYSQL_VERSION, '4.1', '<')) ? 'HEAP' : 'MEMORY');
		}

		$hash = $vbulletin->userinfo['userid'] . '_' . TIMENOW;
		$aggtable = "baggregate_temp_$hash";

		$vbulletin->db->query_write("
			CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$aggtable (
				blogcategoryid INT UNSIGNED NOT NULL DEFAULT '0',
				entries INT UNSIGNED NOT NULL DEFAULT '0',
				KEY blogcategoryid (blogcategoryid)
			) " . MYSQL_ENGINE . " = " . MYSQL_TABLETYPE . "
		");

		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "$aggtable
				SELECT blogcategoryid, COUNT(*) AS entries
				FROM " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser
				INNER JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
				WHERE blog.dateline <= " . TIMENOW . " AND
					blog.pending = 0 AND
					blog.state = 'visible'
				GROUP BY blogcategoryid
		");

		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "blog_category AS blog_category
			LEFT JOIN " . TABLE_PREFIX . "$aggtable AS aggregate USING (blogcategoryid)
			SET entrycount = IF(aggregate.entries, aggregate.entries, 0)
		");

		$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $aggtable);
	}

	echo '<p>' . $vbphrase['updating_blog_users'] . '</p>';

	$users = $db->query_read("
		SELECT userid
		FROM " . TABLE_PREFIX . "user AS user
		INNER JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (user.userid = blog_user.bloguserid)
		WHERE userid >= " . $vbulletin->GPC['startat'] . "
		ORDER BY userid
		LIMIT " . $vbulletin->GPC['perpage']
	);
	while ($user = $db->fetch_array($users))
	{
		build_blog_user_counters($user['userid']);
		echo construct_phrase($vbphrase['processing_x'], $user['userid']) . "<br />\n";
		vbflush();

		$finishat = ($user['userid'] > $finishat ? $user['userid'] : $finishat);
	}

	$finishat++;

	if ($checkmore = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE userid >= $finishat LIMIT 1"))
	{
		print_cp_redirect("blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateuser&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
		echo "<p><a href=\"blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=updateuser&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'blog_admin.php');
		print_stop_message('updated_blog_users_successfully');
	}
}

if ($_POST['do'] == 'emptycache')
{
	$db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "blog_textparsed");
	define('CP_REDIRECT', 'blog_admin.php');
	print_stop_message('blog_cache_emptied');
}

if ($_POST['do'] == 'rebuildcounters')
{
	$mysqlversion = $db->query_first("SELECT version() AS version");
	define('MYSQL_VERSION', $mysqlversion['version']);
	$enginetype = (version_compare(MYSQL_VERSION, '4.0.18', '<')) ? 'TYPE' : 'ENGINE';
	$tabletype = (version_compare(MYSQL_VERSION, '4.1', '<')) ? 'HEAP' : 'MEMORY';

	// rebuild category counters
	$tablename = 'blog_category_count' . $vbulletin->userinfo['userid'];

	$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . "$tablename");
	$db->query_write("
	CREATE TABLE " . TABLE_PREFIX . "$tablename
		(
			bcid INT UNSIGNED NOT NULL DEFAULT '0',
			bctotal INT UNSIGNED NOT NULL DEFAULT '0',
			KEY blogcategoryid (blogcategoryid)
		) $enginetype = $tabletype
		SELECT blogcategoryid, COUNT(*) AS total
		FROM " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser
		INNER JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
		WHERE blog.state = 'visible'
		GROUP BY blogcategoryid
	");

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "blog_category AS blog_category, " . TABLE_PREFIX . "$tablename AS blog_category_count
		SET blog_category.entrycount = blog_category_count.bctotal
		WHERE blog_category.blogcategoryid = blog_category_count.bcid
	");
	$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . "$tablename");

	// rebuild attachment counters
	$tablename = 'blog_attachment_count' . $vbulletin->userinfo['userid'];

	$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . "$tablename");
	$db->query_write("
	CREATE TABLE " . TABLE_PREFIX . "$tablename
		(
			bid INT UNSIGNED NOT NULL DEFAULT '0',
			btotal INT UNSIGNED NOT NULL DEFAULT '0',
			KEY blogid (blogid)
		) $enginetype = $tabletype
		SELECT blogid, COUNT(*) AS total
		FROM " . TABLE_PREFIX . "blog_attachment
		WHERE visible = 'visible'
		GROUP BY blogid
	");

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "blog AS blog, " . TABLE_PREFIX . "$tablename AS blog_attachment_count
		SET blog.attach = blog_attachment_count.btotal
		WHERE blog.blogid = blog_attachment_count.bid
	");
	$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . "$tablename");

	// rebuild trackback counters
	$tablename = 'blog_trackback_count' . $vbulletin->userinfo['userid'];

	$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . "$tablename");
	$db->query_write("
	CREATE TABLE " . TABLE_PREFIX . "$tablename
		(
			bid INT UNSIGNED NOT NULL DEFAULT '0',
			bstate ENUM('moderation','visible') NOT NULL DEFAULT 'visible',
			btotal INT UNSIGNED NOT NULL DEFAULT '0',
			KEY blogid (bid, state)
		) $enginetype = $tabletype
		SELECT blog_trackback.blogid, blog_trackback.state AS state, COUNT(*) AS total
		FROM " . TABLE_PREFIX . "blog_trackback AS blog_trackback
		INNER JOIN " . TABLE_PREFIX . "blog AS blog USING (blogid)
		GROUP BY state, blog_trackback.blogid
	");

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "blog AS blog, " . TABLE_PREFIX . "$tablename AS blog_trackback_count
		SET blog.trackback_visible = blog_trackback_count.btotal
		WHERE blog.blogid = blog_trackback_count.bid AND blog_trackback_count.bstate = 'visible'
	");

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "blog AS blog, " . TABLE_PREFIX . "$tablename AS blog_trackback_count
		SET blog.trackback_moderation = blog_trackback_count.btotal
		WHERE blog.blogid = blog_trackback_count.bid AND blog_trackback_count.bstate = 'moderation'
	");
	$vbulletin->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . "$tablename");

	build_blog_stats();

	define('CP_REDIRECT', 'blog_admin.php');
	print_stop_message('blog_counters_rebuilt');
}

// ################## Start rebuilding attachment thumbnails ################
if ($_REQUEST['do'] == 'rebuildthumbs')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'quality'      => TYPE_UINT,
		'autoredirect' => TYPE_BOOL,
	));

	@ini_set('memory_limit', -1);

	require_once(DIR . '/includes/class_image.php');
	$image =& vB_Image::fetch_library($vbulletin);

	//$validtypes = array('gif', 'jpg', 'jpe', 'jpeg', 'png', 'tif', 'tiff', 'psd', 'bmp');
	$validtypes =& $image->thumb_extensions;

	foreach ($vbulletin->attachmentcache AS $key => $value)
	{
		$key = strtolower($key);
		if ($key != 'extensions' AND !empty($validtypes["$key"]) AND $vbulletin->attachmentcache["$key"]['thumbnail'])
		{
			$extensions .= iif($extensions, ', ') . "'$key'";
		}
	}

	if (!$extensions)
	{
		print_stop_message('you_have_no_attachments_set_to_thumb');
	}

	if ($vbulletin->options['imagetype'] != 'Magick' AND !function_exists('imagetypes'))
	{
		print_stop_message('your_version_no_image_support');
	}

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 20;
	}

	if (!$vbulletin->GPC['startat'])
	{
		$firstattach = $db->query_first("SELECT MIN(attachmentid) AS min FROM " . TABLE_PREFIX . "blog_attachment");
		$vbulletin->GPC['startat'] = intval($firstattach['min']);
	}

	$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

	echo '<p>' . construct_phrase($vbphrase['building_attachment_thumbnails'], "blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildthumbs&startat=" . $vbulletin->GPC['startat'] . "&pp=" . $vbulletin->GPC['perpage'] . "&autoredirect=" . $vbulletin->GPC['autoredirect'] . "&quality=" . $vbulletin->GPC['quality']) . '</p>';

	if ($vbulletin->options['blogattachfile'])
	{
		require_once(DIR . '/includes/functions_file.php');
	}

	$attachments = $db->query_read("
		SELECT attachmentid, filedata, userid, blogid, filename, dateline
		FROM " . TABLE_PREFIX . "blog_attachment
		WHERE attachmentid >= " . $vbulletin->GPC['startat'] . "
			AND	attachmentid < $finishat
			AND	SUBSTRING_INDEX(filename, '.', -1) IN ($extensions)
		ORDER BY attachmentid
	");
	while ($attachment = $db->fetch_array($attachments))
	{
		if (!$vbulletin->options['blogattachfile'])
		{
			if ($vbulletin->options['safeupload'])
			{
				$filename = $vbulletin->options['tmppath'] . '/' . md5(uniqid(microtime()) . $vbulletin->userinfo['userid']);
			}
			else
			{
				$filename = tempnam(ini_get('upload_tmp_dir'), 'vbthumb');
			}
			$filenum = fopen($filename, 'wb');
			fwrite($filenum, $attachment['filedata']);
			fclose($filenum);
		}
		else
		{
			$attachmentids .= ",$attachment[attachmentid]";
			$filename = fetch_attachment_path($attachment['userid'], $attachment['attachmentid'], false, $vbulletin->options['blogattachpath']);
		}

		echo construct_phrase($vbphrase['processing_x'], "$vbphrase[attachment] : (" . file_extension($attachment['filename']) . ') ' .
			construct_link_code($attachment['attachmentid'], "../blog_attachment.php?" . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;d=$attachment[dateline]", 1) . " ($vbphrase[blog] : " .
			construct_link_code($attachment['blogid'], "../blog.php?" . $vbulletin->session->vars['sessionurl'] . "b=$attachment[blogid]", 1) . " )") . ' ';

		if (!is_readable($filename) OR !@filesize($filename))
		{
			echo '<b>' . $vbphrase['error_attachment_missing'] . '</b><br />';
			continue;
		}

		$labelimage = ($vbulletin->options['attachthumbs'] == 3 OR $vbulletin->options['attachthumbs'] == 4);
		$drawborder = ($vbulletin->options['attachthumbs'] == 2 OR $vbulletin->options['attachthumbs'] == 4);
		$thumbnail = $image->fetch_thumbnail($attachment['filename'], $filename, $vbulletin->options['attachthumbssize'], $vbulletin->options['attachthumbssize'], $vbulletin->GPC['quality'], $labelimage, $drawborder);

		// Remove temporary file we used to generate thumbnail
		if (!$vbulletin->options['blogattachfile'])
		{
			@unlink($filename);
		}

		if (!empty($thumbnail['filedata']))
		{
			// These are created each go around to insure memory has been freed
			require_once(DIR . '/includes/class_dm.php');
			require_once(DIR . '/includes/class_dm_attachment_blog.php');

			$attachdata =& vB_DataManager_Attachment_Blog::fetch_library($vbulletin, ERRTYPE_ARRAY);
			$attachdata->set_existing($attachment);
			$attachdata->setr('thumbnail', $thumbnail['filedata']);
			$attachdata->set('thumbnail_dateline', TIMENOW);
			if (!($result = $attachdata->save()))
			{
				if (!empty($attachdata->errors[0]))
				{
					echo $attacherror =& $attachdata->errors[0];
				}
			}
			unset($attachdata);
		}

		if (!empty($thumbnail['imageerror']))
		{
			echo '<b>' . $vbphrase["error_$thumbnail[imageerror]"] . '</b>';
		}
		else if (empty($thumbnail['filedata']))
		{
			echo '<b>' . $vbphrase['error'] . '</b>';
		}
		echo '<br />';
		vbflush();
	}

	if ($checkmore = $db->query_first("SELECT attachmentid FROM " . TABLE_PREFIX . "blog_attachment WHERE attachmentid >= $finishat AND SUBSTRING_INDEX(filename, '.', -1) IN ('gif', 'jpg', 'jpeg', 'jpe', 'png') LIMIT 1"))
	{
		if ($vbulletin->GPC['autoredirect'] == 1)
		{
			print_cp_redirect("blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildthumbs&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;quality=" . $vbulletin->GPC['quality'] . "&amp;autoredirect=1");
		}
		echo "<p><a href=\"blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildthumbs&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "&amp;quality=" . $vbulletin->GPC['quality'] . '">' . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
	}
	else
	{
		define('CP_REDIRECT', 'blog_admin.php');
		print_stop_message('rebuilt_attachment_thumbnails_successfully');
	}
}

// ##################### Show Moderators ##################################

if ($_REQUEST['do'] == 'moderators')
{
	if (!can_administer('canblogpermissions'))
	{
		print_cp_no_permission();
	}
	print_form_header('', '');
	print_table_header($vbphrase['last_online'] . ' - ' . $vbphrase['color_key']);
	print_description_row('
		<div class="darkbg" style="border: 2px inset"><ul class="darkbg">
		<li class="modtoday">' . $vbphrase['today'] . '</li>
		<li class="modyesterday">' . $vbphrase['yesterday'] . '</li>
		<li class="modlasttendays">' . construct_phrase($vbphrase['within_the_last_x_days'], '10') . '</li>
		<li class="modsincetendays">' . construct_phrase($vbphrase['more_than_x_days_ago'], '10') . '</li>
		<li class="modsincethirtydays"> ' . construct_phrase($vbphrase['more_than_x_days_ago'], '30') . '</li>
		</ul></div>
	');
	print_table_footer();

	// get the timestamp for the beginning of today, according to bbuserinfo's timezone
	require_once(DIR . '/includes/functions_misc.php');
	$unixtoday = vbmktime(0, 0, 0, vbdate('m', TIMENOW, false, false), vbdate('d', TIMENOW, false, false), vbdate('Y', TIMENOW, false, false));

	print_form_header('', '');
	print_table_header($vbphrase['super_moderators']);
	echo "<tr valign=\"top\">\n\t<td class=\"" . fetch_row_bgclass() . "\" colspan=\"2\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: $stylevar[left]\"><ul>";

	$countmods = 0;
	$supergroups = $db->query_read("
		SELECT user.*, usergroup.usergroupid
		FROM " . TABLE_PREFIX . "usergroup AS usergroup
		INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
		WHERE (usergroup.adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ")
		GROUP BY user.userid
		ORDER BY user.username
	");
	if ($db->num_rows($supergroups))
	{
		while ($supergroup = $db->fetch_array($supergroups))
		{
			$countmods++;
			if ($supergroup['lastactivity'] >= $unixtoday)
			{
				$onlinecolor = 'modtoday';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 86400))
			{
				$onlinecolor = 'modyesterday';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 864000))
			{
				$onlinecolor = 'modlasttendays';
			}
			else if ($supergroup['lastactivity'] >= ($unixtoday - 2592000))
			{
				$onlinecolor = 'modsincetendays';
			}
			else
			{
				$onlinecolor = 'modsincethirtydays';
			}

			$lastonline = vbdate($vbulletin->options['dateformat'] . ' ' .$vbulletin->options['timeformat'], $supergroup['lastactivity']);
			echo "\n\t<li><b><a href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$supergroup[userid]\">$supergroup[username]</a></b><span class=\"smallfont\"> (" . construct_link_code($vbphrase['edit_permissions'], "blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=editglobal&amp;u=$supergroup[userid]") . ") - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span></li>\n";
		}
	}
	else
	{
		echo $vbphrase['there_are_no_moderators'];
	}
	echo "</ul></div>\n";
	echo "</td>\n</tr>\n";

	if ($countmods)
	{
		print_table_footer(1, $vbphrase['total'] . ": <b>$countmods</b>");
	}
	else
	{
		print_table_footer();
	}

	print_form_header('', '');
	print_table_header($vbphrase['moderators']);
	echo "<tr valign=\"top\">\n\t<td class=\"" . fetch_row_bgclass() . "\" colspan=\"2\">";
	echo "<div class=\"darkbg\" style=\"padding: 4px; border: 2px inset; text-align: $stylevar[left]\">";

	$countmods = 0;
	$moderators = $db->query_read("
		SELECT blog_moderator.blogmoderatorid, user.userid, user.username, user.lastactivity
		FROM " . TABLE_PREFIX . "blog_moderator AS blog_moderator
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog_moderator.userid)
		WHERE blog_moderator.type = 'normal'
		ORDER BY user.username
	");
	if ($db->num_rows($moderators))
	{
		while ($moderator = $db->fetch_array($moderators))
		{
			if ($countmods++ != 0)
			{
				echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
			}

			if ($moderator['lastactivity'] >= $unixtoday)
			{
				$onlinecolor = 'modtoday';
			}
			else if ($moderator['lastactivity'] >= ($unixtoday - 86400))
			{
				$onlinecolor = 'modyesterday';
			}
			else if ($moderator['lastactivity'] >= ($unixtoday - 864000))
			{
				$onlinecolor = 'modlasttendays';
			}
			else if ($moderator['lastactivity'] >= ($unixtoday - 2592000))
			{
				$onlinecolor = 'modsincetendays';
			}
			else
			{
				$onlinecolor = 'modsincethirtydays';
			}
			$lastonline = vbdate($vbulletin->options['dateformat'] . ' ' .$vbulletin->options['timeformat'], $moderator['lastactivity']);
			echo "\n\t<ul>\n\t<li><b><a href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=$moderator[userid]&amp;redir=showlist\">$moderator[username]</a></b><span class=\"smallfont\"> - " . $vbphrase['last_online'] . " <span class=\"$onlinecolor\">" . $lastonline . "</span></span>\n";
			echo " <span class=\"smallfont\">(" .
				construct_link_code($vbphrase['edit'], "blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=editmod&blogmoderatorid=$moderator[blogmoderatorid]") .
				construct_link_code($vbphrase['remove'], "blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=removemod&blogmoderatorid=$moderator[blogmoderatorid]") .
			")</span>";
		}
		echo "\t\t</ul>\n\t\t</ul>\n\t</li>\n\t</ul>\n";
	}
	else
	{
		echo $vbphrase['there_are_no_moderators'];
	}
	echo "</div>\n";
	echo "</td>\n</tr>\n";

	if ($countmods)
	{
		print_table_footer(1, $vbphrase['moderators'] . ": <b>$countmods</b>");
	}
	else
	{
		print_table_footer();
	}

	print_form_header('blog_admin', 'addmod');
	print_table_header('<input type="submit" class="button" value="' . $vbphrase['add_new_moderator'] . '" style="font:bold 11px tahoma" />');
	print_table_footer();

}

// ##################### Start Add/Edit Moderator ##########

if ($_REQUEST['do'] == 'addmod' OR $_REQUEST['do'] == 'editmod' OR $_REQUEST['do'] == 'editglobal')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'blogmoderatorid'	=> TYPE_INT,
		'userid'          => TYPE_UINT,
	));

	if (!can_administer('canblogpermissions'))
	{
		print_cp_no_permission();
	}

	require_once(DIR . '/includes/class_bitfield_builder.php');
	if (vB_Bitfield_Builder::build(false) !== false)
	{
		$myobj =& vB_Bitfield_Builder::init();
		if (sizeof($myobj->data['misc']['vbblogmoderatorpermissions']) != sizeof($vbulletin->bf_misc_vbblogmoderatorpermissions))
		{
			$myobj->save($db);
			define('CP_REDIRECT', $vbulletin->scriptpath);
			print_stop_message('rebuilt_bitfields_successfully');
		}
	}
	else
	{
		echo "<strong>error</strong>\n";
		print_r(vB_Bitfield_Builder::fetch_errors());
	}

	if ($_REQUEST['do'] == 'editglobal')
	{
		$moderator = $db->query_first("
			SELECT user.username, user.userid,
			bm.permissions, bm.blogmoderatorid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "blog_moderator AS bm ON (bm.userid = user.userid AND bm.type = 'super')
			WHERE user.userid = " . $vbulletin->GPC['userid']
		);

		print_form_header('blog_admin', 'updatemod');
		construct_hidden_code('type', 'super');
		construct_hidden_code('modusername', $moderator['username'], false);
		$username = $moderator['username'];

		if (empty($moderator['blogmoderatorid']))
		{
			$moderator = array();
			foreach ($myobj->data['misc']['vbblogmoderatorpermissions'] AS $permission => $option)
			{
				$moderator["$permission"] = true;
			}

			// this user doesn't have a record for super mod permissions, which is equivalent to having them all
			$globalperms = array_sum($vbulletin->bf_misc_vbblogmoderatorpermissions);
			$moderator = convert_bits_to_array($globalperms, $vbulletin->bf_misc_vbblogmoderatorpermissions, 1);
			$moderator['username'] = $username;
		}
		else
		{
			construct_hidden_code('blogmoderatorid', $moderator['blogmoderatorid']);
			$perms = convert_bits_to_array($moderator['permissions'], $vbulletin->bf_misc_vbblogmoderatorpermissions, 1);
			$moderator = array_merge($perms, $moderator);
		}

		print_table_header($vbphrase['super_moderator_permissions'] . ' - <span class="normal">' . $moderator['username'] . '</span>');
	}
	else if (empty($vbulletin->GPC['blogmoderatorid']))
	{
		// add moderator - set default values
		$moderator = array();
		foreach ($myobj->data['misc']['vbblogmoderatorpermissions'] AS $permission => $option)
		{
			$moderator["$permission"] = $option['default'] ? 1 : 0;
		}

		print_form_header('blog_admin', 'updatemod');
		print_table_header($vbphrase['add_new_moderator_to_vbulletin_blog']);
		construct_hidden_code('type', 'normal');
	}
	else
	{
		// edit moderator - query moderator
		$moderator = $db->query_first("
			SELECT blogmoderatorid, bm.userid, permissions, user.username, bm.type
			FROM " . TABLE_PREFIX . "blog_moderator AS bm
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = bm.userid)
			WHERE blogmoderatorid = " . $vbulletin->GPC['blogmoderatorid']
		);

		$perms = convert_bits_to_array($moderator['permissions'], $vbulletin->bf_misc_vbblogmoderatorpermissions, 1);
		$moderator = array_merge($perms, $moderator);

		// delete link
		print_form_header('blog_admin', 'removemod');
		construct_hidden_code('blogmoderatorid', $vbulletin->GPC['blogmoderatorid']);
		construct_hidden_code('type', 'normal');
		print_table_header($vbphrase['if_you_would_like_to_remove_this_moderator'] . ' &nbsp; &nbsp; <input type="submit" class="button" value="' . $vbphrase['delete_moderator'] . '" style="font:bold 11px tahoma" />');
		print_table_footer();

		print_form_header('blog_admin', 'updatemod');
		construct_hidden_code('blogmoderatorid', $vbulletin->GPC['blogmoderatorid']);
		print_table_header($vbphrase['edit_moderator']);
	}

	if ($_REQUEST['do'] != 'editglobal')
	{
		if (empty($vbulletin->GPC['blogmoderatorid']))
		{
			print_input_row($vbphrase['moderator_username'], 'modusername', $moderator['username']);
		}
		else
		{
			print_label_row($vbphrase['moderator_username'], '<b>' . $moderator['username'] . '</b>');
		}
		print_table_header($vbphrase['blog_permissions']);
	}

	foreach ($myobj->data['misc']['vbblogmoderatorpermissions'] AS $permission => $option)
	{
		print_yes_no_row($vbphrase["$option[phrase]"], 'modperms[' . $permission . ']', $moderator["$permission"]);
	}

	print_submit_row(!empty($vbulletin->GPC['blogmoderatorid']) ? $vbphrase['update'] : $vbphrase['save']);
}

// ###################### Start insert / update moderator #######################
if ($_POST['do'] == 'updatemod')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'modusername'		=> TYPE_NOHTML,
		'moderator'			=> TYPE_ARRAY,
		'modperms'			=> TYPE_ARRAY,
		'blogmoderatorid' => TYPE_UINT,
		'type'            => TYPE_NOHTML,
	));

	$vbulletin->GPC['type'] = ($vbulletin->GPC['type'] == 'super') ? 'super' : 'normal';

	if (!can_administer('canblogpermissions'))
	{
		print_cp_no_permission();
	}

	require_once(DIR . '/includes/functions_misc.php');
	$vbulletin->GPC['moderator']['permissions'] = convert_array_to_bits($vbulletin->GPC['modperms'], $vbulletin->bf_misc_vbblogmoderatorpermissions, 1);
	if ($vbulletin->GPC['blogmoderatorid'])
	{ // update
		$db->query_write(fetch_query_sql($vbulletin->GPC['moderator'], 'blog_moderator', "WHERE blogmoderatorid=" . $vbulletin->GPC['blogmoderatorid']));

		define('CP_REDIRECT', 'blog_admin.php?do=moderators');
		print_stop_message('saved_moderator_x_successfully', $vbulletin->GPC['modusername']);
	}
	else
	{ // insert

		if ($userinfo = $db->query_first("
			SELECT user.userid, bloguserid, blog_moderator.userid AS bmuserid
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (user.userid = blog_user.bloguserid)
			LEFT JOIN " . TABLE_PREFIX . "blog_moderator AS blog_moderator ON (user.userid = blog_moderator.userid AND type = '" . $vbulletin->GPC['type'] . "')
			WHERE username = '" . $db->escape_string($vbulletin->GPC['modusername']) . "'"
		))
		{
			if ($userinfo['bmuserid'])
			{
				print_stop_message('user_already_moderator');
			}

			$vbulletin->GPC['moderator']['userid'] = $userinfo['userid'];
			$vbulletin->GPC['moderator']['type'] = $vbulletin->GPC['type'];
			$db->query_write(fetch_query_sql($vbulletin->GPC['moderator'], 'blog_moderator'));

			if ($vbulletin->GPC['type'] == 'normal')
			{
				$dataman =& datamanager_init('Blog_User', $vbulletin, ERRTYPE_CP);
				if ($userinfo['bloguserid'])
				{
					$dataman->set_existing($userinfo);
				}
				else
				{
					$dataman->set('bloguserid', $userinfo['userid']);
				}

				$dataman->set('isblogmoderator', 1);
				$dataman->save();
			}
		}

		define('CP_REDIRECT', 'blog_admin.php?do=moderators');
		print_stop_message('saved_moderator_x_successfully', $vbulletin->GPC['modusername']);
	}
}

// ###################### Start Remove moderator #######################

if ($_REQUEST['do'] == 'removemod')
{
	$vbulletin->input->clean_array_gpc('r', array('blogmoderatorid' => TYPE_UINT));

	if (!can_administer('canblogpermissions'))
	{
		print_cp_no_permission();
	}

	print_delete_confirmation('blog_moderator', $vbulletin->GPC['blogmoderatorid'], 'blog_admin', 'killmod', 'moderator');
}

// ###################### Start Kill moderator #######################

$vbulletin->input->clean_array_gpc('p', array('blogmoderatorid' => TYPE_UINT));

if ($_POST['do'] == 'killmod')
{

	if (!can_administer('canblogpermissions'))
	{
		print_cp_no_permission();
	}

	$getuserid = $db->query_first("
		SELECT user.userid, usergroupid
		FROM " . TABLE_PREFIX . "blog_moderator AS blog_moderator
		LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		WHERE blogmoderatorid = " . $vbulletin->GPC['blogmoderatorid']
	);
	if (!$getuserid)
	{
		print_stop_message('user_no_longer_moderator');
	}
	else
	{
		$userinfo = array('bloguserid' => $getuserid['userid']);
		$dataman =& datamanager_init('Blog_User', $vbulletin, ERRTYPE_SILENT);
		$dataman->set_existing($userinfo);
		$dataman->set('isblogmoderator', 0);
		$dataman->save();

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "blog_moderator
			WHERE blogmoderatorid = " . $vbulletin->GPC['blogmoderatorid']
		);

		define('CP_REDIRECT', 'blog_admin.php?do=moderators');
		print_stop_message('deleted_moderator_successfully');
	}
}


if ($_REQUEST['do'] == 'list' OR $_REQUEST['do'] == 'dolist')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'user'              => TYPE_NOHTML,
		'userid'            => TYPE_UINT,
		'pagenumber'        => TYPE_UINT,
		'perpage'           => TYPE_UINT,
		'orderby'           => TYPE_NOHTML,
		'start'             => TYPE_ARRAY_UINT,
		'end'               => TYPE_ARRAY_UINT,
		'startstamp'        => TYPE_UINT,
		'endstamp'          => TYPE_UINT,
		'status'            => TYPE_NOHTML,
	));

	$vbulletin->GPC['start'] = iif($vbulletin->GPC['startstamp'], $vbulletin->GPC['startstamp'], $vbulletin->GPC['start']);
	$vbulletin->GPC['end'] = iif($vbulletin->GPC['endstamp'], $vbulletin->GPC['endstamp'], $vbulletin->GPC['end']);

	if ($userinfo = verify_id('user', $vbulletin->GPC['userid'], 0, 1))
	{
		$vbulletin->GPC['user'] = $userinfo['username'];
	}
	else
	{
		$vbulletin->GPC['userid'] = 0;
	}

	// Default View Values

	if (!$vbulletin->GPC['start'])
	{
		$vbulletin->GPC['start'] = TIMENOW - 3600 * 24 * 30;
	}

	if (!$vbulletin->GPC['end'])
	{
		$vbulletin->GPC['end'] = TIMENOW;
	}

	if (!$vbulletin->GPC['status'])
	{
		$vbulletin->GPC['status'] = 'all';
	}

	$statusoptions = array(
		'all'        => $vbphrase['all_entries'],
		'deleted'    => $vbphrase['deleted_entries'],
		'draft'      => $vbphrase['draft_entries'],
		'moderation' => $vbphrase['moderated_entries'],
		'pending'    => $vbphrase['pending_entries'],
		'visible'    => $vbphrase['visible_entries'],
	);

	print_form_header('blog_admin', 'dolist');
	print_table_header($vbphrase['view_blog_entries']);
	print_input_row($vbphrase['user'], 'user', $vbulletin->GPC['user'], 0);
	print_select_row($vbphrase['status'], 'status', $statusoptions, $vbulletin->GPC['status']);
	print_time_row($vbphrase['start_date'], 'start', $vbulletin->GPC['start'], false);
	print_time_row($vbphrase['end_date'], 'end', $vbulletin->GPC['end'], false);
	print_submit_row($vbphrase['go']);
}

// ###################### Start list #######################
if ($_REQUEST['do'] == 'dolist')
{
	require_once(DIR . '/includes/functions_misc.php');
	if ($vbulletin->GPC['startstamp'])
	{
		$vbulletin->GPC['start'] = $vbulletin->GPC['startstamp'];
	}
	else
	{
		$vbulletin->GPC['start'] = vbmktime(0, 0, 0, $vbulletin->GPC['start']['month'], $vbulletin->GPC['start']['day'], $vbulletin->GPC['start']['year']);
	}

	if ($vbulletin->GPC['endstamp'])
	{
		$vbulletin->GPC['end'] = $vbulletin->GPC['endstamp'];
	}
	else
	{
		$vbulletin->GPC['end'] = vbmktime(23, 59, 59, $vbulletin->GPC['end']['month'], $vbulletin->GPC['end']['day'], $vbulletin->GPC['end']['year']);
	}

	if ($vbulletin->GPC['start'] >= $vbulletin->GPC['end'])
	{
		print_stop_message('start_date_after_end');
	}

	if (!$vbulletin->GPC['userid'] AND $vbulletin->GPC['user'])
	{
		if (!$user = $db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
				WHERE username = '" . $db->escape_string($vbulletin->GPC['user']) . "'
		"))
		{
			print_stop_message('could_not_find_user_x', $vbulletin->GPC['user']);
		}
		$vbulletin->GPC['userid'] = $user['userid'];
	}

	$wheresql = array();
	if ($vbulletin->GPC['userid'])
	{
		$wheresql[] = "blog.userid = " . $vbulletin->GPC['userid'];
	}

	if ($vbulletin->GPC['start'])
	{
		$wheresql[] = "dateline >= " . $vbulletin->GPC['start'];
	}
	if ($vbulletin->GPC['end'])
	{
		$wheresql[] = "dateline <= " . $vbulletin->GPC['end'];
	}

	switch($vbulletin->GPC['orderby'])
	{
		case 'title':
			$orderby = 'title ASC';
			break;
		case 'username':
			$orderby = 'username ASC';
			break;
		default:
			$orderby = 'dateline DESC';
			$vbulletin->GPC['orderby'] = '';
	}

	switch ($vbulletin->GPC['status'])
	{
		case 'pending':
			$wheresql[] = "pending = 1";
			break;
		case 'draft':
			$wheresql[] = "state = 'draft'";
			break;
		case 'deleted':
			$wheresql[] = "state = 'deleted'";
			break;
		case 'moderation':
			$wheresql[] = "state = 'moderation'";
			break;
		case 'visible':
			$wheresql[] = "state = 'visible'";
			$wheresql[] = "pending = 0";
			break;
	}

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 15;
	}

	$totalentries = 0;
	do
	{
		if (!$vbulletin->GPC['pagenumber'])
		{
			$vbulletin->GPC['pagenumber'] = 1;
		}
		$start = ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'];

		$entries = $db->query_read_slave("
			SELECT SQL_CALC_FOUND_ROWS blogid, dateline, title, blog.userid, user.username, state, pending
			FROM " . TABLE_PREFIX . "blog AS blog
			LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
			WHERE " . implode(" AND ", $wheresql) . "
			ORDER BY $orderby
			LIMIT $start, " . $vbulletin->GPC['perpage'] . "
		");
		list($totalentries) = $db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);
		if ($start >= $totalentries)
		{
			$vbulletin->GPC['pagenumber'] = ceil($totalentries / $vbulletin->GPC['perpage']);
		}
	}
	while ($start >= $totalentries AND $totalentries);

	$args =
		 '&status=' . $vbulletin->GPC['status'] .
		 '&u=' . $vbulletin->GPC['userid'] .
		 '&startstamp=' . $vbulletin->GPC['start'] .
		 '&endstamp=' . $vbulletin->GPC['end'] .
		 '&pp=' . $vbulletin->GPC['perpage'] .
		 '&page=' . $vbulletin->GPC['pagenumber'] .
		 '&orderby=';


	$totalpages = ceil($totalentries / $vbulletin->GPC['perpage']);

	if ($db->num_rows($entries))
	{
		if ($vbulletin->GPC['pagenumber'] != 1)
		{
			$prv = $vbulletin->GPC['pagenumber'] - 1;
			$firstpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"&laquo; " . $vbphrase['first_page'] . "\" onclick=\"window.location='blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=dolist" . $args . $vbulletin->GPC['orderby'] . "&page=1'\">";
			$prevpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"&lt; " . $vbphrase['prev_page'] . "\" onclick=\"window.location='blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=dolist" . $args . $vbulletin->GPC['orderby'] . "&page=$prv'\">";
		}

		if ($vbulletin->GPC['pagenumber'] != $totalpages)
		{
			$nxt = $vbulletin->GPC['pagenumber'] + 1;
			$nextpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"" . $vbphrase['next_page'] . " &gt;\" onclick=\"window.location='blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=dolist" . $args . $vbulletin->GPC['orderby'] . "&page=$nxt'\">";
			$lastpage = "<input type=\"button\" class=\"button\" tabindex=\"1\" value=\"" . $vbphrase['last_page'] . " &raquo;\" onclick=\"window.location='blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=dolist" . $args . $vbulletin->GPC['orderby'] . "&page=$totalpages'\">";
		}

		print_form_header('blog_admin', 'remove');
		print_table_header(construct_phrase($vbphrase['blog_entry_viewer_page_x_y_there_are_z_total_log_entries'], vb_number_format($vbulletin->GPC['pagenumber']), vb_number_format($totalpages), vb_number_format($totalentries)), 5);

		$headings = array();
		$headings[] = "<a href=\"blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=dolist" . $args . "username\" title=\"" . $vbphrase['order_by_username'] . "\">" . $vbphrase['user_name'] . "</a>";
		$headings[] = "<a href=\"blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=dolist" . $args . "title\" title=\"" . $vbphrase['order_by_title'] . "\">" . $vbphrase['title'] . "</a>";
		$headings[] = "<a href=\"blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=dolist" . $args . "\" title=\"" . $vbphrase['order_by_date'] . "\">" . $vbphrase['date'] . "</a>";
		$headings[] = $vbphrase['type'];
		$headings[] = $vbphrase['controls'];
		print_cells_row($headings, 1);

		while ($entry = $db->fetch_array($entries))
		{
			$cell = array();
			$cell[] = "<a href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&amp;u=$entry[userid]\"><b>$entry[username]</b></a>";
			if ($entry['state'] != 'draft' AND !$entry['pending'])
			{
				$cell[] = "<a href=\"../blog.php?" . $vbulletin->session->vars['sessionurl'] . "b=$entry[blogid]\"><b>$entry[title]</b></a>";
			}
			else
			{
				$cell[] = $entry['title'];
			}
			$cell[] = '<span class="smallfont">' . vbdate($vbulletin->options['logdateformat'], $entry['dateline']) . '</span>';
			switch($entry['state'])
			{
				case 'visible':
					if ($entry['pending'])
					{
						$cell[] = $vbphrase['pending'];
					}
					else
					{
						$cell[] = $vbphrase['visible'];
					}
					break;
				case 'deleted':
					$cell[] = $vbphrase['deleted'];
					break;
				case 'draft':
					$cell[] = $vbphrase['draft'];
					break;
				case 'moderation':
					$cell[] = $vbphrase['moderated'];
					break;
				default:
					$cell[] = '&nbsp;';
			}

			$cell[] = construct_link_code($vbphrase['delete'], "blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=deleteentry&blogid=$entry[blogid]" . $args . $vbulletin->GPC['orderby'], false, '', true);

			print_cells_row($cell);
		}

		print_table_footer(5, "$firstpage $prevpage &nbsp; $nextpage $lastpage");
	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

if ($_REQUEST['do'] == 'deleteentry')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'blogid'     => TYPE_UINT,
		'userid'     => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'perpage'    => TYPE_UINT,
		'orderby'    => TYPE_NOHTML,
		'startstamp' => TYPE_UINT,
		'endstamp'   => TYPE_UINT,
		'status'     => TYPE_NOHTML,
	));

	if ($bloginfo = fetch_bloginfo($vbulletin->GPC['blogid']))
	{
		print_form_header('blog_admin', 'killentry');
		construct_hidden_code('blogid', $vbulletin->GPC['blogid']);
		construct_hidden_code('userid', $vbulletin->GPC['userid']);
		construct_hidden_code('pagenumber', $vbulletin->GPC['pagenumber']);
		construct_hidden_code('perpage', $vbulletin->GPC['perpage']);
		construct_hidden_code('orderby', $vbulletin->GPC['orderby']);
		construct_hidden_code('startstamp', $vbulletin->GPC['startstamp']);
		construct_hidden_code('endstamp', $vbulletin->GPC['endstamp']);
		construct_hidden_code('status', $vbulletin->GPC['status']);
		print_table_header(construct_phrase($vbphrase['confirm_deletion_x'], $bloginfo['title']));
		print_description_row($vbphrase['are_you_sure_that_you_want_to_delete_this_blog_entry']);
		print_submit_row($vbphrase['yes'], '', 2, $vbphrase['no']);
	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

if ($_POST['do'] == 'killentry')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'blogid'     => TYPE_UINT,
		'userid'     => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'perpage'    => TYPE_UINT,
		'orderby'    => TYPE_NOHTML,
		'startstamp' => TYPE_UINT,
		'endstamp'   => TYPE_UINT,
		'status'     => TYPE_NOHTML,
	));

	if ($bloginfo = fetch_bloginfo($vbulletin->GPC['blogid']))
	{
		$blogman =& datamanager_init('Blog', $vbulletin, ERRTYPE_CP, 'blog');
		$blogman->set_existing($bloginfo);
		$blogman->set_info('hard_delete', true);
		$blogman->delete();
		unset($blogman);
		build_blog_user_counters($bloginfo['userid']);

		$args =
			 '&status=' . $vbulletin->GPC['status'] .
			 '&u=' . $vbulletin->GPC['userid'] .
			 '&startstamp=' . $vbulletin->GPC['startstamp'] .
			 '&endstamp=' . $vbulletin->GPC['endstamp'] .
			 '&pp=' . $vbulletin->GPC['perpage'] .
			 '&page=' . $vbulletin->GPC['pagenumber'] .
			 '&orderby=' . $vbulletin->GPC['orderby'];

		define('CP_REDIRECT', 'blog_admin.php?do=dolist' . $args);
		print_stop_message('deleted_entry_successfully');
	}
	else
	{
		print_stop_message('no_matches_found');
	}
}

if ($_REQUEST['do'] == 'rebuildprofilepic')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'perpage'      => TYPE_UINT,
		'startat'      => TYPE_UINT,
	));

	@ini_set('memory_limit', -1);

	if ($vbulletin->options['imagetype'] != 'Magick' AND !function_exists('imagetypes'))
	{
		print_stop_message('your_version_no_image_support');
	}

	if (empty($vbulletin->GPC['perpage']))
	{
		$vbulletin->GPC['perpage'] = 20;
	}

	if (!$vbulletin->GPC['startat'])
	{
		$firstpic = $db->query_first("SELECT MIN(userid) AS min FROM " . TABLE_PREFIX . "customprofilepic WHERE width = 0 OR height = 0");
		$vbulletin->GPC['startat'] = intval($firstpic['min']);
	}

	if ($vbulletin->GPC['startat'])
	{
		$finishat = $vbulletin->GPC['startat'] + $vbulletin->GPC['perpage'];

		echo '<p>' . construct_phrase($vbphrase['calculating_profile_pic_dimensions'], "blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildprofilepic&startat=" . $vbulletin->GPC['startat'] . "&pp=" . $vbulletin->GPC['perpage']) . '</p>';

		require_once(DIR . '/includes/class_image.php');
		$image =& vB_Image::fetch_library($vbulletin);

		$pictures = $db->query_read("
			SELECT cpp.userid, cpp.filedata, u.profilepicrevision, u.username
			FROM " . TABLE_PREFIX . "customprofilepic AS cpp
			LEFT JOIN " . TABLE_PREFIX . "user AS u USING (userid)
			WHERE cpp.userid >= " . $vbulletin->GPC['startat'] . "
				AND (cpp.width = 0 OR cpp.height = 0)
			ORDER BY cpp.userid
			LIMIT " . $vbulletin->GPC['perpage'] . "
		");

		while ($picture = $db->fetch_array($pictures))
		{
			if (!$vbulletin->options['usefileavatar'])	// Profilepics are in the database
			{
				if ($vbulletin->options['safeupload'])
				{
					$filename = $vbulletin->options['tmppath'] . '/' . md5(uniqid(microtime()) . $vbulletin->userinfo['userid']);
				}
				else
				{
					$filename = tempnam(ini_get('upload_tmp_dir'), 'vbthumb');
				}
				$filenum = fopen($filename, 'wb');
				fwrite($filenum, $picture['filedata']);
				fclose($filenum);
			}
			else
			{
				$filename = $vbulletin->options['profilepicurl'] . '/profilepic' . $picture['userid'] . '_' . $picture['profilepicrevision'] . '.gif';
			}

			echo construct_phrase($vbphrase['processing_x'], "$vbphrase[profile_picture] : $picture[username] ");

			if (!is_readable($filename) OR !@filesize($filename))
			{
				echo '<b>' . $vbphrase['error_file_missing'] . '</b><br />';
				continue;
			}

			$imageinfo = $image->fetch_image_info($filename);
			if ($imageinfo[0] AND $imageinfo[1])
			{
				$dataman =& datamanager_init('Userpic_Profilepic', $vbulletin, ERRTYPE_SILENT, 'userpic');
				$dataman->set_existing($picture);
				$dataman->set('width', $imageinfo[0]);
				$dataman->set('height', $imageinfo[1]);
				$dataman->save();
				unset($dataman);
			}
			else
			{
				echo $vbphrase[error];
			}

			// Remove temporary file
			if (!$vbulletin->options['usefileavatar'])
			{
				@unlink($filename);
			}

			echo '<br />';
			vbflush();
			$finishat = ($picture['userid'] > $finishat ? $picture['userid'] : $finishat);
		}

		$finishat++;

		if ($checkmore = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "customprofilepic WHERE userid >= $finishat LIMIT 1"))
		{
			print_cp_redirect("blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildprofilepic&startat=$finishat&pp=" . $vbulletin->GPC['perpage']);
			echo "<p><a href=\"blog_admin.php?" . $vbulletin->session->vars['sessionurl'] . "do=rebuildprofilepic&amp;startat=$finishat&amp;pp=" . $vbulletin->GPC['perpage'] . "\">" . $vbphrase['click_here_to_continue_processing'] . "</a></p>";
		}
		else
		{
			define('CP_REDIRECT', 'blog_admin.php');
			print_stop_message('updated_profile_pictures_successfully');
		}
	}
	else
	{
		define('CP_REDIRECT', 'blog_admin.php');
		print_stop_message('updated_profile_pictures_successfully');
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25010 $
|| ####################################################################
\*======================================================================*/
?>
