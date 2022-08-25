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
define('THIS_SCRIPT', 'blog_post');
define('VBBLOG_PERMS', 1);
define('VBBLOG_STYLE', 1);
define('GET_EDIT_TEMPLATES', 'newblog,editblog,updateblog,comment,editcomment,postcomment');
if ($_POST['do'] == 'postcomment')
{
	if (isset($_POST['ajax']))
	{
		define('NOPMPOPUP', 1);
		define('NOSHUTDOWNFUNC', 1);
	}
	if (isset($_POST['fromquickcomment']))
	{	// Don't update Who's Online for Quick Comments since it will get stuck on that until the user goes somewhere else
		define('LOCATION_BYPASS', 1);
	}
}

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'posting',
	'vbblogglobal',
	'postbit',
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
	'comment'			=>	array(
		'blog_comment_editor',
		'blog_entry_editor_preview',
		'blog_rules',
		'imagereg',
		'humanverify',
	),
	'editcomment'     => array(
		'blog_comment_editor',
		'blog_rules',
	),
	'newblog'			=> array(
		'blog_entry_editor_category',
		'blog_entry_editor_attachment',
		'blog_entry_editor_attachments',
		'blog_entry_editor',
		'blog_entry_editor_preview',
		'blog_entry_editor_draft',
		'blog_rules',
	),
	'editblog'        => array(
		'blog_entry_editor_category',
		'blog_entry_editor',
		'blog_entry_editor_attachments',
		'blog_entry_editor_attachment',
		'blog_entry_editor_preview',
		'blog_entry_editor_draft',
		'blog_rules',
	),
	'notify'				=> array(
		'blog_notify_urls',
		'blog_notify_urls_url',
		'newpost_preview',
		'newpost_errormessage',
	),
	'edittrackback'	=> array(
		'blog_edit_trackback',
	),
);
$actiontemplates['postcomment'] =& $actiontemplates['comment'];
$actiontemplates['updateblog'] =& $actiontemplates['editblog'];
$actiontemplates['donotify'] =& $actiontemplates['notify'];
$actiontemplates['updatetrackback'] =& $actiontemplates['edittrackback'];

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'newblog';
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_init.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ### STANDARD INITIALIZATIONS ###
$checked = array();
$blog = array();
$postattach = array();
$show['moderatecomments'] = (!$vbulletin->options['blog_commentmoderation'] AND $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_followcommentmoderation'] ? true : false);
$show['pingback'] = ($vbulletin->options['vbblog_pingback'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'] ? true : false);
$show['trackback'] = ($vbulletin->options['vbblog_trackback'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canreceivepingback'] ? true : false);
$show['notify'] = ($vbulletin->options['vbblog_notifylinks'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansendpingback'] AND $vbulletin->userinfo['everyone_canviewmyblog'] ? true : false);

/* Check they can view a blog, any blog */
if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) AND !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
{
	print_no_permission();
}

($hook = vBulletinHook::fetch_hook('blog_post_start')) ? eval($hook) : false;

// #######################################################################
if ($_POST['do'] == 'donotify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'blogid'    => TYPE_UINT,
		'notifyurl' => TYPE_ARRAY_BOOL,
	));

	// can we edit this blog? We need answers!
	$bloginfo = fetch_bloginfo($vbulletin->GPC['blogid']);

	if ($bloginfo['state'] !== 'visible' OR $vbulletin->userinfo['userid'] != $bloginfo['userid'])
	{
		standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
	}

	if (!empty($vbulletin->GPC['notifyurl']) AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansendpingback'] AND $vbulletin->options['vbblog_notifylinks'])
	{
		if (count($vbulletin->GPC['notifyurl']) > $vbulletin->options['vbblog_notifylinks'])
		{
			$_REQUEST['do'] = 'notify';
			require_once(DIR . '/includes/functions_newpost.php');
			$errors = construct_errors(array(fetch_error('blog_too_many_links'))); // this will take the preview's place
		}
		else
		{
			if ($urls = fetch_urls($bloginfo['pagetext']))
			{
				$counter = 0;
				foreach($urls AS $url)
				{
					if (isset($vbulletin->GPC['notifyurl']["$counter"]))
					{
						send_ping_notification($bloginfo, $url, $vbulletin->userinfo['blog_title'] ? $vbulletin->userinfo['blog_title'] : $vbulletin->userinfo['userid']);
					}
					$counter++;
				}
			}
		}
	}

	$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
	eval(print_standard_redirect('redirect_blog_entrythanks'));
}

// #######################################################################
if ($_REQUEST['do'] == 'notify')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'blogid'    => TYPE_UINT
	));

	$bloginfo = fetch_bloginfo($vbulletin->GPC['blogid']);

	if ($bloginfo['state'] !== 'visible' OR $vbulletin->userinfo['userid'] != $bloginfo['userid'])
	{
		standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
	}

	if ($vbulletin->options['vbblog_notifylinks'] AND $vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansendpingback'])
	{
		if ($urls = fetch_urls($bloginfo['pagetext']))
		{
			$urlbits = '';
			if (count($urls) > $vbulletin->options['vbblog_notifylinks'])
			{
				$show['urllimit'] = true;
			}
			$counter = 0;
			foreach($urls AS $url)
			{
				$url = htmlspecialchars($url);
				$checked = (isset($vbulletin->GPC['notifyurl']["$counter"]) ? 'checked="checked"' : '');
				eval('$urlbits .= "' . fetch_template('blog_notify_urls_url') . '";');
				$counter++;
			}

			cache_ordered_categories($bloginfo['userid']);
			$sidebar =& build_user_sidebar($bloginfo);

			// navbar and output
			$navbits = array(
				'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$bloginfo[userid]" => $bloginfo['blog_title'],
				'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]" => $bloginfo['title'],
			);

			eval('$content = "' . fetch_template('blog_notify_urls') . '";');
		}
	}
}

// #######################################################################
if ($_POST['do'] == 'updateblog')
{
	// Variables reused in templates
	$posthash = $vbulletin->input->clean_gpc('p', 'posthash', TYPE_NOHTML);
	$poststarttime = $vbulletin->input->clean_gpc('p', 'poststarttime', TYPE_UINT);

	$vbulletin->input->clean_array_gpc('p', array(
		'blogid'           => TYPE_UINT,
		'title'            => TYPE_NOHTML,
		'message'          => TYPE_STR,
		'wysiwyg'          => TYPE_BOOL,
		'preview'          => TYPE_STR,
		'draft'            => TYPE_STR,
		'disablesmilies'   => TYPE_BOOL,
		'parseurl'         => TYPE_BOOL,
		'status'           => TYPE_STR,
		'categories'       => TYPE_ARRAY_UINT,
		'reason'           => TYPE_NOHTML,
		'allowcomments'    => TYPE_BOOL,
		'moderatecomments' => TYPE_BOOL,
		'allowpingback'    => TYPE_BOOL,
		'notify'           => TYPE_BOOL,
		'publish'          => TYPE_ARRAY_UINT,
		'emailupdate'      => TYPE_STR,
	));

	if (!$vbulletin->userinfo['userid']) // Guests can not make entries
	{
		print_no_permission();
	}

	($hook = vBulletinHook::fetch_hook('blog_post_updateentry_start')) ? eval($hook) : false;

	// unwysiwygify the incoming data
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$vbulletin->GPC['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowhtml']);
	}

	// parse URLs in message text
	if ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode'] AND $vbulletin->GPC['parseurl'])
	{
		require_once(DIR . '/includes/functions_newpost.php');
		$vbulletin->GPC['message'] = convert_url_to_bbcode($vbulletin->GPC['message']);
	}

	// handle clicks on the 'save draft' button
	if (!empty($vbulletin->GPC['draft']))
	{
		$vbulletin->GPC['status'] = 'draft';
	}

	if ($vbulletin->GPC['status'] == 'publish_on')
	{
		require_once(DIR . '/includes/functions_misc.php');
		$blog['dateline'] = vbmktime($vbulletin->GPC['publish']['hour'], $vbulletin->GPC['publish']['minute'], 0, $vbulletin->GPC['publish']['month'], $vbulletin->GPC['publish']['day'], $vbulletin->GPC['publish']['year']);
	}

	$blog['message']          =& $vbulletin->GPC['message'];
	$blog['title']            =& $vbulletin->GPC['title'];
	$blog['disablesmilies']   =& $vbulletin->GPC['disablesmilies'];
	$blog['parseurl']         = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode'] AND $vbulletin->GPC['parseurl']);
	$blog['status']           =& $vbulletin->GPC['status'];
	$blog['categories']       =& $vbulletin->GPC['categories'];
	$blog['reason']           =& $vbulletin->GPC['reason'];
	$blog['allowcomments']    =& $vbulletin->GPC['allowcomments'];
	$blog['moderatecomments'] =& $vbulletin->GPC['moderatecomments'];
	$blog['notify']           =& $vbulletin->GPC['notify'];
	$blog['allowpingback']    =& $vbulletin->GPC['allowpingback'];

	$blogman =& datamanager_init('Blog_Firstpost', $vbulletin, ERRTYPE_ARRAY, 'blog');

	if ($vbulletin->GPC['blogid'])
	{	// Editing
		$bloginfo = verify_blog($blogid);
		/* Check they edit their blog */
		if ($bloginfo['state'] == 'deleted' AND ((!can_moderate_blog('caneditentries') OR !can_moderate_blog('candeleteentries')) AND ($vbulletin->userinfo['userid'] != $bloginfo['userid'] OR !($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_caneditentry'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry']))))
		{
			print_no_permission();
		}
		else if ($bloginfo['state'] == 'moderation' AND (!can_moderate_blog('canmoderateentries') OR !can_moderate_blog('caneditentries')))
		{
			print_no_permission();
		}
		else if (!can_moderate_blog('caneditentries') AND ($vbulletin->userinfo['userid'] != $bloginfo['userid'] OR (!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_caneditentry']) AND $bloginfo['state'] != 'draft' AND !$bloginfo['pending'])))
		{
			print_no_permission();
		}
		$show['edit'] = true;
		$blogman->set_existing($bloginfo);
	}
	else
	{
		$blogman->set('userid', $vbulletin->userinfo['userid']);
		$blogman->set('bloguserid', $vbulletin->userinfo['userid']);
	}
	$blogman->set('title', $vbulletin->GPC['title']);
	$blogman->set('pagetext', $vbulletin->GPC['message']);
	$blogman->set('allowsmilie', !$blog['disablesmilies']);

	if ($blog['status'] == 'publish_now' AND ($bloginfo['dateline'] > TIMENOW OR $bloginfo['state'] == 'draft'))
	{
		$blog['dateline'] = TIMENOW;
	}

	// if we have a dateline then set it
	if ($blog['dateline'])
	{
		$blogman->set('dateline', $blog['dateline']);
	}

	/* Drafts are exempt from initial moderation */
	if ($blog['status'] == 'draft')
	{
		$blogman->set('state', 'draft');
	}
	/* moderation is on, usergroup permissions are following the scheme and its not a moderator who can simply moderate */
	else if (($vbulletin->options['vbblog_postmoderation'] OR !($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_followpostmoderation'])) AND !can_moderate_blog('canmoderateentries'))
	{
		$blogman->set('state', 'moderation');
	}
	else if ($bloginfo['state'] == 'draft' AND $blog['status'] != 'draft')
	{
		$blogman->set('state', 'visible');
	}

	if ($show['moderatecomments'])
	{
		$blogman->set_bitfield('options', 'moderatecomments', $blog['moderatecomments']);
	}
	if ($show['pingback'] OR $show['trackback'])
	{
		$blogman->set_bitfield('options', 'allowpingback', $blog['allowpingback']);
	}
	$blogman->set_bitfield('options', 'allowcomments', $blog['allowcomments']);

	$blogman->set_info('categories', $blog['categories']);
	$blogman->set_info('posthash', $posthash);
	$blogman->set_info('emailupdate', $vbulletin->GPC['emailupdate']);

	$blogman->pre_save();

	$errors = $blogman->errors;

	if (!empty($errors))
	{
		define('POSTPREVIEW', true);
		$preview = construct_errors($errors); // this will take the preview's place
		$_REQUEST['do'] = $bloginfo ? 'editblog' : 'newblog';
	}
	else if ($vbulletin->GPC['preview'] != '')
	{
		define('POSTPREVIEW', true);

		// <-- This is done in two queries since Mysql will not use an index on an OR query which gives a full table scan of the attachment table
		// A poor man's UNION
		// Attachments that existed before the edit began.
		$start = 2;
		if ($bloginfo)
		{
			$currentattaches1 = $db->query_read_slave("
				SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid,
				IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
				attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
				FROM " . TABLE_PREFIX . "blog_attachment
				LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
				WHERE blogid = $bloginfo[blogid]
				ORDER BY attachmentid
			");
			$start = 1;
		}

		// Attachments added since the edit began. Used when editentry is reloaded due to an error on the user side
		$currentattaches2 = $db->query_read_slave("
			SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid,
			IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
			attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
			FROM " . TABLE_PREFIX . "blog_attachment
			LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
			WHERE posthash = '" . $db->escape_string($posthash) . "'
				AND userid = " . $vbulletin->userinfo['userid'] . "
			ORDER BY attachmentid
		");
		$attachcount = 0;
		for ($x = $start; $x <= 2; $x++)
		{
			$currentattaches =& ${currentattaches . $x};
			while ($attach = $db->fetch_array($currentattaches))
			{
				$postattach["$attach[attachmentid]"] = $attach;
			}
		}

		$preview = process_blog_preview($blog, 'entry', $postattach);

		$_REQUEST['do'] = $bloginfo ? 'editblog' : 'newblog';
	}
	else
	{
		if ($bloginfo)
		{
			$blogman->save();

			$update_edit_log = true;

			($hook = vBulletinHook::fetch_hook('blog_post_updateentry_edit')) ? eval($hook) : false;

			if ($bloginfo['state'] == 'draft' OR $bloginfo['pending'] == 1 OR (!($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showeditedby']) AND ($blog['reason'] === '' OR $blog['reason'] == $bloginfo['edit_reason'])))
			{
				$update_edit_log = false;
			}

			if ($update_edit_log)
			{
				if ($bloginfo['dateline'] < (TIMENOW - ($vbulletin->options['noeditedbytime'] * 60)) OR !empty($blog['reason']))
				{
					/*insert query*/
					$db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "blog_editlog (blogtextid, userid, username, dateline, reason)
						VALUES ($bloginfo[firstblogtextid], " . $vbulletin->userinfo['userid'] . ", '" . $db->escape_string($vbulletin->userinfo['username']) . "', " . TIMENOW . ", '" . $db->escape_string($blog['reason']) . "')
					");
				}
			}

			// if this is a mod edit, then log it
			if ($vbulletin->userinfo['userid'] != $bloginfo['userid'] AND can_moderate('caneditentries'))
			{
				require_once(DIR . '/includes/blog_functions_log_error.php');
				blog_moderator_action($bloginfo, 'blogentry_x_edited', array($bloginfo['title']));
			}

			build_blog_user_counters($bloginfo['userid']);

			if ($show['notify'] AND $blog['notify'])
			{
				if ($urls = fetch_urls($vbulletin->GPC['message']))
				{
					$vbulletin->url = 'blog_post.php?' . $vbulletin->session->vars['sessionurl'] . "do=notify&amp;b=$bloginfo[blogid]";
					eval(print_standard_redirect('blog_editthanks_notify'));
				}
			}

			$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
			eval(print_standard_redirect('redirect_blog_editthanks'));
		}
		else
		{
			// ### DUPE CHECK ###
			$dupehash = md5($blog['categoryid'] . $blog['title'] . $blog['message'] . $vbulletin->userinfo['userid'] . 'blog');

			($hook = vBulletinHook::fetch_hook('blog_post_updateentry_new')) ? eval($hook) : false;

			if ($prevcomment = $vbulletin->db->query_first("
				SELECT blogid
				FROM " . TABLE_PREFIX . "blog_hash
				WHERE userid = " . $vbulletin->userinfo['userid'] . " AND
					dupehash = '" . $vbulletin->db->escape_string($dupehash) . "' AND
					dateline > " . (TIMENOW - 300) . "
			"))
			{
				$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$prevcomment[blogid]";
				eval(print_standard_redirect('blog_duplicate_comment', true, true));
			}
			else
			{
				if ($blogcomment = $blogman->save())
				{	// Parse Notify Links
					if ($show['notify'] AND $blog['notify'])
					{
						if ($urls = fetch_urls($vbulletin->GPC['message']))
						{
							$vbulletin->url = 'blog_post.php?' . $vbulletin->session->vars['sessionurl'] . "do=notify&amp;b=$blogcomment";
							eval(print_standard_redirect('blog_entrythanks_notify'));
						}
					}
				}

				$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$blogcomment";
				eval(print_standard_redirect('redirect_blog_entrythanks'));
			}
		}
	}

	unset($blogman);
}

// #######################################################################
if ($_REQUEST['do'] == 'newblog')
{
	/* Blog posting check, no guests! */
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']) OR !($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpost']) OR !$vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	// falls down from preview post and has already been sent through htmlspecialchars() in build_new_post()
	$title = $blog['title'];

	require_once(DIR . '/includes/functions_editor.php');
	require_once(DIR . '/includes/functions_newpost.php');
	// get attachment options
	require_once(DIR . '/includes/functions_file.php');
	$inimaxattach = fetch_max_upload_size();
	$maxattachsize = vb_number_format($inimaxattach, 1, true);
	$attachcount = 0;
	$attachment_js = '';

	cache_ordered_categories();

	($hook = vBulletinHook::fetch_hook('blog_post_newentry_start')) ? eval($hook) : false;

	if ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpostattach'])
	{
		if (!$posthash OR !$poststarttime)
		{
			$poststarttime = TIMENOW;
			$posthash = md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);
		}
		else
		{
			if (empty($postattach))
			{
				$currentattaches = $db->query_read("
					SELECT dateline, filename, filesize, attachmentid
					FROM " . TABLE_PREFIX . "blog_attachment
					WHERE posthash = '" . $db->escape_string($posthash) . "'
						AND userid = " . $vbulletin->userinfo['userid']
				);
				while ($attach = $db->fetch_array($currentattaches))
				{
					$postattach["$attach[attachmentid]"] = $attach;
				}
			}

			if (!empty($postattach))
			{
				foreach($postattach AS $attachmentid => $attach)
				{
					$attach['extension'] = strtolower(file_extension($attach['filename']));
					$attach['filename'] = htmlspecialchars_uni($attach['filename']);
					$attach['filesize'] = vb_number_format($attach['filesize'], 1, true);
					$attach['imgpath'] = "$stylevar[imgdir_attach]/$attach[extension].gif";
					$show['attachmentlist'] = true;
					eval('$attachments .= "' . fetch_template('blog_entry_editor_attachment') . '";');

					$attachment_js .= construct_attachment_add_js($attachmentid, $attach['filename'], $attach['filesize'], $attach['extension']);

					$attach_editor["$attachmentid"] = $attach['filename'];
				}
			}
		}

		$newpost_attachmentbit = prepare_blog_newpost_attachmentbit();
		eval('$attachmentoption = "' . fetch_template('blog_entry_editor_attachments') . '";');

	}
	else
	{
		$attachmentoption = '';
	}

	$draft_options = '';
	$blog_drafts = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "blog WHERE userid = " . $vbulletin->userinfo['userid'] . " AND state = 'draft'");
	while ($blog_draft = $db->fetch_array($blog_drafts))
	{
		$blog_draft['date_string'] = vbdate($vbulletin->options['dateformat'], $blog_draft['dateline']);
		$blog_draft['time_string'] = vbdate($vbulletin->options['timeformat'], $blog_draft['dateline']);
		$radiochecked = (!isset($radiochecked) ? ' checked="checked"' : '');
		eval('$draft_options .= "' . fetch_template('blog_entry_editor_draft') . '";');
	}
	$show['drafts'] = !empty($draft_options);

	if (defined('POSTPREVIEW'))
	{
		$postpreview =& $preview;
		$blog['message'] = htmlspecialchars_uni($blog['message']);
		construct_checkboxes($blog, array('allowcomments', 'allowpingback', 'moderatecomments', 'notify'));
		construct_publish_select($blog, $blog['dateline']);

		$notification = array($vbulletin->GPC['emailupdate'] => 'selected="selected"');
	}
	else
	{ // defaults in here if we're doing a quote etc
		construct_checkboxes(
			array(
				'allowcomments'    => $vbulletin->userinfo['blog_allowcomments'],
				'allowpingback'    => $vbulletin->userinfo['blog_allowpingback'],
				'moderatecomments' => $vbulletin->userinfo['blog_moderatecomments'],
				'parseurl'         => true,
			),
			array('allowcomments', 'allowpingback', 'moderatecomments'
		));
		construct_publish_select($blog);

		$notification = array($vbulletin->userinfo['blog_subscribeown'] => 'selected="selected"');
	}

	$editorid = construct_edit_toolbar(
		$blog['message'],
		false,
		'blog_entry',
		$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowsmilies'],
		true,
		$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpostattach'] AND !empty($vbulletin->userinfo['attachmentextensions'])
	);

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');
	$sidebar =& build_user_sidebar($vbulletin->userinfo, 0, 0, 'entry');

	// draw nav bar
	// navbar and output
	$navbits = array(
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u={$vbulletin->userinfo['userid']}" => $vbulletin->userinfo['blog_title'],
		'' => $vbphrase['post_to_your_blog']
	);

	$show['category'] = ($categorybits = construct_category_checkbox($blog['categories']));
	$show['parseurl'] = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode']);
	$show['misc_options'] = ($show['parseurl'] OR !empty($disablesmiliesoption));
	$show['additional_options'] = ($show['misc_options'] OR !empty($attachmentoption));
	$show['post_options'] = true;
	$show['datepicker'] = true;
	$show['draftpublish'] = true;

	($hook = vBulletinHook::fetch_hook('blog_post_newentry_complete')) ? eval($hook) : false;

	// complete
	eval('$content = "' . fetch_template('blog_entry_editor') . '";');
}

// ############################### start delete post ###############################
if ($_POST['do'] == 'deleteblog')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'deleteblog'      => TYPE_STR,
		'reason'          => TYPE_NOHTML,
		'keepattachments' => TYPE_BOOL,
		'blogid'          => TYPE_UINT,
		'blogtextid'      => TYPE_UINT,
	));

	if (!can_moderate_blog('candeleteentries'))
	{	// Keep attachments for non moderator deletes (blog owner)
		$vbulletin->GPC['keepattachments'] = true;
	}

	$bloginfo = verify_blog($blogid);

	if ($bloginfo === false)
	{
		standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
	}

	if (!$vbulletin->userinfo['userid']) // Guests can not make entries
	{
		print_no_permission();
	}

	$canremove = (can_moderate_blog('canremoveentries') OR ($vbulletin->userinfo['userid'] == $bloginfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry']));
	$canundelete = $candelete = (
		can_moderate_blog('candeleteentries')
			OR
		(
			$vbulletin->userinfo['userid'] == $bloginfo['userid']
				AND
			$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry']
				AND
			(
				$bloginfo['state'] != 'deleted'
					OR
				$bloginfo['del_userid'] == $vbulletin->userinfo['userid']
			)
		)
	);

	$canedit = (
		can_moderate_blog('caneditentries')
			OR
		(
			$vbulletin->userinfo['userid'] == $bloginfo['userid']
				AND
			$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_caneditentry']
				AND
			(
				$bloginfo['state'] != 'deleted'
					OR
				$bloginfo['del_userid'] == $vbulletin->userinfo['userid']
			)
		)
	);

	if ($bloginfo['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries'))
	{
		standard_error(fetch_error('you_do_not_have_permission_to_manage_moderated_blog_entries'));
	}
	else if ($bloginfo['state'] == 'deleted' AND !$canremove)
	{
		standard_error(fetch_error('you_do_not_have_permission_to_remove_blog_entries'));
	}
	else if (($bloginfo['pending'] OR $bloginfo['state'] == 'draft') AND $bloginfo['userid'] != $vbulletin->userinfo['userid'])
	{
		standard_error(fetch_error('you_can_not_manage_other_users_pending_or_draft_entries'));
	}

	if ($vbulletin->GPC['deleteblog'] != '')
	{
		if ($vbulletin->GPC['blogtextid'] == $bloginfo['blogtextid'] OR empty($vbulletin->GPC['blogtextid']))
		{
			if (
				!can_moderate_blog('candeleteentries')
					AND
				!can_moderate_blog('canremoveentries')
					AND
				(
					$vbulletin->userinfo['userid'] != $bloginfo['userid']
						OR
					(
						!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry'])
							AND
						!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry'])
							AND
						$bloginfo['state'] != 'draft'
							AND
						!$bloginfo['pending']
					)
				))
			{
				print_no_permission();
			}
			if (
					(
						$vbulletin->GPC['deleteblog'] == 'remove'
							AND
						(
							can_moderate_blog('canremoveentries')
								OR
							(
								$vbulletin->userinfo['userid'] == $bloginfo['userid']
									AND
								$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry']
							)
						)
					)
						OR
					$bloginfo['pending']
						OR
					$bloginfo['state'] == 'draft'
				)
			{
				$hard_delete = true;
			}
			else
			{
				$hard_delete = false;
			}

			$blogman =& datamanager_init('Blog', $vbulletin, ERRTYPE_ARRAY, 'blog');
			$blogman->set_existing($bloginfo);
			$blogman->set_info('hard_delete', $hard_delete);
			$blogman->set_info('keep_attachments', $vbulletin->GPC['keepattachments']);
			$blogman->set_info('reason', $vbulletin->GPC['reason']);

			$blogman->delete();
			unset($blogman);

			build_blog_user_counters($bloginfo['userid']);

			$url = unhtmlspecialchars($vbulletin->url);
			if (preg_match('/\?([^#]*)(#.*)?$/s', $url, $match))
			{
				parse_str($match[1], $parts);

				if ($parts['blogid'] == $bloginfo['blogid'] OR $parts['b'] == $bloginfo['blogid'])
				{
					// we've deleted the entry that we came into this blog from
					// blank the redirect as it will be set below
					$vbulletin->url = '';
				}
			}

			if (!stristr($vbulletin->url, 'blog.php')) // no referring url?
			{
				$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$bloginfo[userid]";
			}
			eval(print_standard_redirect('redirect_blog_delete'));
		}
		else
		{ // just deleting a comment
			$blogtextinfo = fetch_blog_textinfo($vbulletin->GPC['blogtextid']);
			if ($blogtextinfo === false)
			{
				standard_error(fetch_error('invalidid', $vbphrase['comment'], $vbulletin->options['contactuslink']));
			}

			if (fetch_comment_perm('candeletecomments', $bloginfo, $blogtextinfo))
			{
				if ($vbulletin->GPC['deleteblog'] == 'remove' AND can_moderate_blog('canremovecomments'))
				{
					$hard_delete = true;
				}
				else
				{
					$hard_delete = false;
				}

				$blogman =& datamanager_init('BlogText', $vbulletin, ERRTYPE_ARRAY, 'blog');
				$blogman->set_existing($blogtextinfo);
				$blogman->set_info('hard_delete', $hard_delete);
				$blogman->set_info('reason', $vbulletin->GPC['reason']);

				$blogman->delete();
				unset($blogman);

				if (!stristr($vbulletin->url, 'blog.php')) // no referring url?
				{
					$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
				}
				eval(print_standard_redirect('redirect_blog_deletecomment'));
			}
			else
			{
				print_no_permission();
			}
		}
	}
	else
	{
		$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]";
		eval(print_standard_redirect('redirect_blog_entry_nodelete'));
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'editblog')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'blogid'	=> TYPE_UINT
	));

	$bloginfo = verify_blog($blogid);

	if (!$vbulletin->userinfo['userid']) // Guests can not make entries
	{
		print_no_permission();
	}

	$canremove = (can_moderate_blog('canremoveentries') OR ($vbulletin->userinfo['userid'] == $bloginfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry']));
	$canundelete = $candelete = (
		can_moderate_blog('candeleteentries')
			OR
		(
			$vbulletin->userinfo['userid'] == $bloginfo['userid']
				AND
			$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry']
				AND
			(
				$bloginfo['state'] != 'deleted'
					OR
				$bloginfo['del_userid'] == $vbulletin->userinfo['userid']
			)
		)
	);

	$canedit = (
		can_moderate_blog('caneditentries')
			OR
		(
			$vbulletin->userinfo['userid'] == $bloginfo['userid']
				AND
			$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_caneditentry']
				AND
			(
				$bloginfo['state'] != 'deleted'
					OR
				$bloginfo['del_userid'] == $vbulletin->userinfo['userid']
			)
		)
	);

	if ($bloginfo['state'] == 'deleted' AND (!$canedit OR !$candelete))
	{
		print_no_permission();
	}
	else if ($bloginfo['state'] == 'moderation' AND (!can_moderate_blog('canmoderateentries') OR !can_moderate_blog('caneditentries')))
	{
		print_no_permission();
	}
	else if (!can_moderate_blog('caneditentries') AND ($vbulletin->userinfo['userid'] != $bloginfo['userid'] OR (!($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_caneditentry']) AND $bloginfo['state'] != 'draft' AND !$bloginfo['pending'])))
	{
		print_no_permission();
	}

	require_once(DIR . '/includes/functions_editor.php');
	require_once(DIR . '/includes/functions_newpost.php');
	// get attachment options
	require_once(DIR . '/includes/functions_file.php');
	$inimaxattach = fetch_max_upload_size();
	$maxattachsize = vb_number_format($inimaxattach, 1, true);
	$attachcount = 0;
	$attach_editor = array();
	$attachment_js = '';

	($hook = vBulletinHook::fetch_hook('blog_post_editentry_start')) ? eval($hook) : false;

	// Use our permission to attach or the person who owns the post? check what vB does in this situation
	if ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpostattach'])
	{
		if (!$posthash OR !$poststarttime)
		{
			$poststarttime = TIMENOW;
			$posthash = md5($poststarttime . $vbulletin->userinfo['userid'] . $vbulletin->userinfo['salt']);
		}

		if (empty($postattach))
		{
			// <-- This is done in two queries since Mysql will not use an index on an OR query which gives a full table scan of the attachment table
			// A poor man's UNION
			// Attachments that existed before the edit began.
			$currentattaches1 = $db->query_read_slave("
				SELECT dateline, filename, filesize, attachmentid
				FROM " . TABLE_PREFIX . "blog_attachment
				WHERE blogid = $bloginfo[blogid]
				ORDER BY attachmentid
			");
			// Attachments added since the edit began. Used when editentry is reloaded due to an error on the user side
			$currentattaches2 = $db->query_read_slave("
				SELECT dateline, filename, filesize, attachmentid
				FROM " . TABLE_PREFIX . "blog_attachment
				WHERE posthash = '" . $db->escape_string($posthash) . "'
					AND userid = " . $vbulletin->userinfo['userid'] . "
				ORDER BY attachmentid
			");
			$attachcount = 0;
			for ($x = 1; $x <= 2; $x++)
			{
				$currentattaches =& ${currentattaches . $x};
				while ($attach = $db->fetch_array($currentattaches))
				{
					$postattach["$attach[attachmentid]"] = $attach;
				}
			}
		}

		if (!empty($postattach))
		{
			foreach($postattach AS $attachmentid => $attach)
			{
				$attachcount++;
				$attach['extension'] = strtolower(file_extension($attach['filename']));
				$attach['filename'] = htmlspecialchars_uni($attach['filename']);
				$attach['filesize'] = vb_number_format($attach['filesize'], 1, true);
				$attach['imgpath'] = "$stylevar[imgdir_attach]/$attach[extension].gif";
				$show['attachmentlist'] = true;
				eval('$attachments .= "' . fetch_template('blog_entry_editor_attachment') . '";');

				$attachment_js .= construct_attachment_add_js($attachmentid, $attach['filename'], $attach['filesize'], $attach['extension']);

				$attach_editor["$attachmentid"] = $attach['filename'];
			}
		}

		$attachurl = "b=$bloginfo[blogid]";
		$newpost_attachmentbit = prepare_blog_newpost_attachmentbit();
		eval('$attachmentoption = "' . fetch_template('blog_entry_editor_attachments') . '";');

	}
	else
	{
		$attachmentoption = '';
	}

	if (defined('POSTPREVIEW'))
	{
		$postpreview =& $preview;
		$blog['message'] = htmlspecialchars_uni($blog['message']);
		// falls down from preview blog entry and has already been sent through htmlspecialchars()
		$title = $blog['title'];
		$reason = $blog['reason'];
		construct_checkboxes($blog, array('allowcomments', 'allowpingback', 'moderatecomments'));
		construct_publish_select($blog, $blog['dateline']);

		$notification = array($vbulletin->GPC['emailupdate'] => 'selected="selected"');
	}
	else
	{ // defaults in here if we're doing a quote etc
		$title = $bloginfo['title'];
		$reason = $bloginfo['edit_reason'];
		$blog['message'] = htmlspecialchars_uni($bloginfo['pagetext']);

		$cats = $db->query_read_slave("
			SELECT blogcategoryid
			FROM " . TABLE_PREFIX . "blog_categoryuser
			WHERE userid = $bloginfo[userid]
				AND blogid = $bloginfo[blogid]
		");
		while ($cat = $db->fetch_array($cats))
		{
			$blog['categories'][] = $cat['blogcategoryid'];
		}

		construct_checkboxes(
			array(
				'allowcomments'    => $bloginfo['allowcomments'],
				'allowpingback'    => $bloginfo['allowpingback'],
				'moderatecomments' => $bloginfo['moderatecomments'],
				'draft'            => ($bloginfo['state'] == 'draft'),
				'disablesmilies'   => (!$bloginfo['allowsmilie']),
				'parseurl'         => 1,
			),
			array('allowcomments', 'allowpingback', 'moderatecomments')
		);
		construct_publish_select($bloginfo, $bloginfo['dateline']);

		if ($vbulletin->userinfo['userid'] == $bloginfo['userid'])
		{
			if ($bloginfo['entrysubscribed'])
			{
				$notification = array($bloginfo['emailupdate'] => 'selected="selected"');
			}
		}
		else if ($subscribed = $db->query_first("SELECT type AS emailupdate FROM " . TABLE_PREFIX . "blog_subscribeentry WHERE blogid = $bloginfo[blogid] AND userid = $bloginfo[userid]"))
		{
			$notification = array($subscribed['emailupdate'] => 'selected="selected"');
		}
	}

	$editorid = construct_edit_toolbar(
		$blog['message'],
		false,
		'blog_entry',
		$vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowsmilies'],
		true,
		$bloginfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canpostattach'] AND !empty($vbulletin->userinfo['attachmentextensions'])
	);

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	cache_ordered_categories($bloginfo['userid']);
	$sidebar =& build_user_sidebar($bloginfo, 0, 0, 'entry');

	// draw nav bar

	$navbits = array(
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$bloginfo[userid]" => $bloginfo['blog_title'],
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]" => $bloginfo['title'],
		'' => $vbphrase['edit_blog_entry'],
	);

	$show['category'] = ($categorybits = construct_category_checkbox($blog['categories'], $bloginfo['userid']));
	$show['parseurl'] = ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_allowbbcode']);
	$show['misc_options'] = ($show['parseurl'] OR !empty($disablesmiliesoption));
	$show['additional_options'] = ($show['misc_options'] OR !empty($attachmentoption));
	$show['edit'] = true;
	$show['physicaldeleteoption'] = (can_moderate_blog('canremoveentries') OR $bloginfo['state'] == 'draft' OR $bloginfo['pending'] OR ($vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry']));
	$show['softdeleteoption'] = ($bloginfo['state'] != 'draft' AND !$bloginfo['pending']);
	$show['keepattachmentsoption'] = $attachcount ? true : false;
	$show['datepicker'] = true;
	$show['draftpublish'] = ($bloginfo['state'] == 'draft');

	$bloginfo['entrydate'] = vbdate($vbulletin->options['dateformat'], $bloginfo['dateline']);
	$bloginfo['entrytime'] = vbdate($vbulletin->options['timeformat'], $bloginfo['dateline']);
	$bloginfo['title_trimmed'] = fetch_trimmed_title($bloginfo['title']);

	$show['delete'] = (
		(
			$bloginfo['state'] != 'deleted'
				OR
			can_moderate_blog('canremoveentries')
				OR
			($vbulletin->userinfo['userid'] == $bloginfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry'])
		)
			AND
		($bloginfo['state'] != 'moderation' OR can_moderate_blog('canmoderateentries'))
			AND
	 	((!$bloginfo['pending'] AND $bloginfo['state'] != 'draft') OR $bloginfo['userid'] == $vbulletin->userinfo['userid'])
	 		AND
		(
			$bloginfo['pending']
				OR
			$bloginfo['state'] == 'draft'
				OR
			can_moderate_blog('candeleteentries')
				OR
			can_moderate_blog('canremoveentries')
				OR
			($vbulletin->userinfo['userid'] == $bloginfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_candeleteentry'])
				OR
			($vbulletin->userinfo['userid'] == $bloginfo['userid'] AND $vbulletin->userinfo['permissions']['vbblog_entry_permissions'] & $vbulletin->bf_ugp_vbblog_entry_permissions['blog_canremoveentry'])
		)
	) ? true : false;

	($hook = vBulletinHook::fetch_hook('blog_post_editentry_complete')) ? eval($hook) : false;

	$url =& $vbulletin->url;
	// complete
	eval('$content = "' . fetch_template('blog_entry_editor') . '";');
}

if ($_REQUEST['do'] == 'editcomment' OR ($_POST['do'] == 'postcomment' AND $vbulletin->GPC['blogtextid']))
{
	$bloginfo = verify_blog($blogtextinfo['blogid'], 1, 'modifychild');

	if (!$blogtextinfo)
	{
		standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
	}

	if ($bloginfo['firstblogtextid'] == $blogtextinfo['blogtextid'] OR !fetch_comment_perm('caneditcomments', $bloginfo, $blogtextinfo))
	{
		standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
	}
}

// #######################################################################
if ($_POST['do'] == 'postcomment')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'blogid'           => TYPE_UINT,
		'title'            => TYPE_NOHTML,
		'message'          => TYPE_STR,
		'wysiwyg'          => TYPE_BOOL,
		'preview'          => TYPE_STR,
		'disablesmilies'   => TYPE_BOOL,
		'parseurl'         => TYPE_BOOL,
		'username'         => TYPE_STR,
		'fromquickcomment' => TYPE_BOOL,
		'ajax'             => TYPE_BOOL,
		'lastcomment'      => TYPE_UINT,
		'imagestamp'       => TYPE_STR,
		'imagehash'        => TYPE_STR,
		'loggedinuser'     => TYPE_UINT,
		'emailupdate'      => TYPE_STR,
		'reason'           => TYPE_NOHTML,
		'humanverify'      => TYPE_ARRAY,
	));

	$bloginfo = verify_blog($blogid);

	/* Checks if they can post comments to their blogs or other peoples blogs */
	if (!$blogtextid AND !($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentown']) AND $bloginfo['userid'] == $vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentothers']) AND $bloginfo['userid'] != $vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	/* Moderators can only edit comments if they are deleted or add comments to moderated posts */
	if (($bloginfo['state'] == 'deleted' AND (!can_moderate_blog('candeleteentries') OR !$blogtextid)) OR ($bloginfo['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries')) OR $bloginfo['state'] == 'draft')
	{
		standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
	}

	if ((!$bloginfo['allowcomments'] AND $vbulletin->userinfo['userid'] != $bloginfo['userid'] AND !can_moderate_blog()) OR !$bloginfo['cancommentmyblog'])
	{
		print_no_permission();
	}

	($hook = vBulletinHook::fetch_hook('blog_post_updatecomment_start')) ? eval($hook) : false;

	// unwysiwygify the incoming data
	if ($vbulletin->GPC['wysiwyg'])
	{
		require_once(DIR . '/includes/functions_wysiwyg.php');
		$vbulletin->GPC['message'] = convert_wysiwyg_html_to_bbcode($vbulletin->GPC['message'], $vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowhtml']);
	}

	if ($vbulletin->GPC['ajax'])
	{
		// posting via ajax so we need to handle those %u0000 entries
		$vbulletin->GPC['message'] = convert_urlencoded_unicode($vbulletin->GPC['message']);
	}

	// parse URLs in message text
	if ($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowbbcode'] AND $vbulletin->GPC['parseurl'])
	{
		require_once(DIR . '/includes/functions_newpost.php');
		$vbulletin->GPC['message'] = convert_url_to_bbcode($vbulletin->GPC['message']);
	}

	if ($vbulletin->GPC['fromquickcomment'])
	{
		if ($vbulletin->userinfo['blog_subscribeothers'] AND !$bloginfo['entrysubscribed'])
		{
			$vbulletin->GPC['emailupdate'] = $vbulletin->userinfo['blog_subscribeothers'];
		}
		else if ($bloginfo['entrysubscribed'])
		{
			$vbulletin->GPC['emailupdate'] = $bloginfo['emailupdate'];
		}
		else
		{
			$vbulletin->GPC['emailupdate'] = 'none';
		}
	}

	$blog['message']        =& $vbulletin->GPC['message'];
	$blog['title']          =& $vbulletin->GPC['title'];
	$blog['blogid']         =& $vbulletin->GPC['blogid'];
	$blog['disablesmilies'] =& $vbulletin->GPC['disablesmilies'];
	$blog['parseurl']       = ($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowbbcode'] AND $vbulletin->GPC['parseurl']);
	$blog['username']       =& $vbulletin->GPC['username'];
	$blog['reason']         =& $vbulletin->GPC['reason'];

	$blogman =& datamanager_init('BlogText', $vbulletin, ERRTYPE_ARRAY, 'blog');

	if ($blogtextid)
	{
		$show['edit'] = true;
		$blogman->set_existing($blogtextinfo);
	}
	else
	{
		// if the blog owner is forcing a comment OR board has comment enforcement on and we are following that policy
		if (($bloginfo['moderatecomments'] OR $vbulletin->options['blog_commentmoderation'] OR !($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_followcommentmoderation'])) AND !can_moderate_blog('canmoderatecomments') AND $bloginfo['userid'] != $vbulletin->userinfo['userid'])
		{
			$blogman->set('state', 'moderation');
		}
		$blogman->set('userid', $vbulletin->userinfo['userid']);
		$blogman->set('bloguserid', $bloginfo['userid']);
		if ($vbulletin->userinfo['userid'] == 0)
		{
			$blogman->setr('username', $blog['username']);
		}
		else
		{
			$blogman->do_set('username', $vbulletin->userinfo['username']);
		}
	}

	$blogman->set_info('blog', $bloginfo);
	$blogman->set_info('preview', $vbulletin->GPC['preview']);
	$blogman->set_info('emailupdate', $vbulletin->GPC['emailupdate']);
	$blogman->set_info('akismet_key', $bloginfo['akismet_key']);
	$blogman->setr('title', $blog['title']);
	$blogman->setr('pagetext', $blog['message']);
	$blogman->setr('blogid', $blog['blogid']);
	$blogman->set('allowsmilie', !$blog['disablesmilies']);

	if (!$vbulletin->userinfo['userid'])
	{
		if ($show['blog_37_compatible'])
		{
			if ($vbulletin->options['hvcheck_post'])
			{
				require_once(DIR . '/includes/class_humanverify.php');
				$verify =& vB_HumanVerify::fetch_library($vbulletin);
				if (!$verify->verify_token($vbulletin->GPC['humanverify']))
				{
					$blogman->errors[] = fetch_error($verify->fetch_error());
				}
			}
		}
		else if ($vbulletin->options['regimagetype'] AND $vbulletin->options['postimagecheck'])
		{
			require_once(DIR . '/includes/functions_regimage.php');
			if (!verify_regimage_hash($vbulletin->GPC['imagehash'], $vbulletin->GPC['imagestamp']))
			{
				$blogman->errors[] = fetch_error('register_imagecheck');
			}
		}
	}

	$blogman->pre_save();

	if ($vbulletin->GPC['fromquickcomment'] AND $vbulletin->GPC['preview'])
	{
		$blogman->errors = array();
	}

	if (!empty($blogman->errors))
	{
		if ($vbulletin->GPC['ajax'])
		{
			require_once(DIR . '/includes/class_xml.php');
			$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
			$xml->add_group('errors');
			foreach ($blogman->errors AS $error)
			{
				$xml->add_tag('error', $error);
			}
			$xml->close_group();
			$xml->print_xml();
		}
		else
		{
			define('COMMENTPREVIEW', true);
			$preview = construct_errors($blogman->errors); // this will take the preview's place
			$_REQUEST['do'] = 'comment';
		}
	}
	else if ($vbulletin->GPC['preview'])
	{

		define('COMMENTPREVIEW', true);
		$preview = process_blog_preview($blog, 'comment');
		$_REQUEST['do'] = 'comment';
	}
	else
	{
		$blogcommentid = $blogman->save();
		if ($blogtextid)
		{
			$blogcommentid =& $blogtextid;

			$update_edit_log = true;

			if (!($permissions['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showeditedby']) AND ($blog['reason'] === '' OR $blog['reason'] == $blogtextinfo['edit_reason']))
			{
				$update_edit_log = false;
			}

			if ($update_edit_log)
			{
				if ($blogtextinfo['dateline'] < (TIMENOW - ($vbulletin->options['noeditedbytime'] * 60)) OR !empty($blog['reason']))
				{
					/*insert query*/
					$db->query_write("
						REPLACE INTO " . TABLE_PREFIX . "blog_editlog (blogtextid, userid, username, dateline, reason)
						VALUES ($blogtextid, " . $vbulletin->userinfo['userid'] . ", '" . $db->escape_string($vbulletin->userinfo['username']) . "', " . TIMENOW . ", '" . $db->escape_string($blog['reason']) . "')
					");
				}
			}
		}

		if ($vbulletin->GPC['ajax'])
		{
			$state = array('visible');

			// Owner/Admin/Super Mod of blog should see all states
			// Moderator, depending on their moderation permissions
			$showmoderation = false;
			if ($bloginfo['userid'] == $vbulletin->userinfo['userid'] OR can_moderate_blog('canmoderatecomments'))
			{
				$showmoderation = true;
				$state[] = 'moderation';
			}

			$deljoinsql = '';
			if ($bloginfo['userid'] == $vbulletin->userinfo['userid'] OR can_moderate_blog())
			{
				$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "blog_deletionlog AS blog_deletionlog ON (blog_text.blogtextid = blog_deletionlog.primaryid AND blog_deletionlog.type = 'blogtextid')";
				$state[] = 'deleted';
			}

			require_once(DIR . '/includes/class_xml.php');
			$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
			$xml->add_group('commentbits');

			require_once(DIR . '/includes/class_bbcode.php');
			require_once(DIR . '/includes/class_blog_response.php');

			$bbcode =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

			$factory =& new vB_Blog_ResponseFactory($vbulletin, $bbcode, $bloginfo);

			$responsebits = '';

			$state_or = array(
				"blog_text.state IN ('" . implode("','", $state) . "')"
			);

			($hook = vBulletinHook::fetch_hook('blog_post_updatecomment_complete')) ? eval($hook) : false;

			// Get the viewing user's moderated entries
			if ($vbulletin->userinfo['userid'] AND ($bloginfo['comments_moderation'] > 0 OR $blogman->fetch_field('state') == 'moderation') AND !can_moderate_blog('canmoderatecomments') AND $vbulletin->userinfo['userid'] != $bloginfo['userid'])
			{
				$state_or[] = "(blog_text.userid = " . $vbulletin->userinfo['userid'] . " AND state = 'moderation')";
			}

			$comments = $db->query_read("
				SELECT blog_text.*, blog_text.ipaddress AS blogipaddress,
					blog_textparsed.pagetexthtml, blog_textparsed.hasimages,
					user.*, userfield.*
					" . ($deljoinsql ? ",blog_deletionlog.userid AS del_userid, blog_deletionlog.username AS del_username, blog_deletionlog.reason AS del_reason" : "") . "
					" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
				FROM " . TABLE_PREFIX . "blog_text AS blog_text
				LEFT JOIN " . TABLE_PREFIX . "blog_textparsed AS blog_textparsed ON(blog_textparsed.blogtextid = blog_text.blogtextid AND blog_textparsed.styleid = " . intval(STYLEID) . " AND blog_textparsed.languageid = " . intval(LANGUAGEID) . ")
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog_text.userid = user.userid)
				LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (userfield.userid = blog_text.userid)
				" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
				$deljoinsql
				WHERE blogid = " . $vbulletin->GPC['blogid'] . "
					AND blog_text.blogtextid <> " . $bloginfo['firstblogtextid'] . "
					AND (" . implode(" OR ", $state_or) . ")
					AND " . (($lastviewed = $vbulletin->GPC['lastcomment']) ?
						"(blog_text.dateline > $lastviewed OR blog_text.blogtextid = $blogcommentid)" :
						"blog_text.blogtextid = $blogcommentid"
						) . "
				ORDER BY blog_text.dateline ASC
			");
			while ($comment = $db->fetch_array($comments))
			{
				$response_handler =& $factory->create($comment);
				$rcomment = process_replacement_vars($response_handler->construct());
				$xml->add_tag('comment', process_replacement_vars($rcomment), array(
					'blogtextid'        => $comment['blogtextid'],
					'visible'           => ($comment['state'] == 'visible') ? 1 : 0,
					'bgclass'           => $bgclass,
					'inlinemod_delete'  => $show['delete'] ? 1 : 0,
					'inlinemod_approve' => $show['approve'] ? 1 : 0,
				));
			}

			$xml->add_tag('time', TIMENOW);
			$xml->close_group();
			$xml->print_xml();
		}
		else
		{
			($hook = vBulletinHook::fetch_hook('blog_post_updatecomment_complete')) ? eval($hook) : false;
			$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]#comment$blogcommentid";
			if ($blogman->fetch_field('state') == 'moderation')
			{
				eval(print_standard_redirect('redirect_blog_commentthanks_moderate', true, true));
			}
			else if ($blogtextid)
			{
				eval(print_standard_redirect('redirect_blog_edit_commentthanks'));
			}
			else
			{
				eval(print_standard_redirect('redirect_blog_commentthanks'));
			}
		}
	}

	unset($blogman);
}

// #######################################################################
if ($_REQUEST['do'] == 'comment')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'blogid'		=> TYPE_UINT,
	));

	$bloginfo = verify_blog($blogid);

	/* Checks if they can post comments to their blogs or other peoples blogs */
	// Don't check this permission if we are editing
	if (!$blogtextid AND !($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentown']) AND $bloginfo['userid'] == $vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (!($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_cancommentothers']) AND $bloginfo['userid'] != $vbulletin->userinfo['userid'])
	{
		print_no_permission();
	}

	if (($bloginfo['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries')) OR $bloginfo['state'] == 'deleted' OR $bloginfo['state'] == 'draft' OR $bloginfo['pending'] == 1)
	{
		standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
	}

	if ((!$bloginfo['allowcomments'] AND $vbulletin->userinfo['userid'] != $bloginfo['userid'] AND !can_moderate_blog()) OR !$bloginfo['cancommentmyblog'])
	{
		print_no_permission();
	}

	require_once(DIR . '/includes/functions_editor.php');

	// get attachment options
	require_once(DIR . '/includes/functions_file.php');
	$inimaxattach = fetch_max_upload_size();
	$maxattachsize = vb_number_format($inimaxattach, 1, true);
	$attachcount = 0;
	$attach_editor = array();
	$attachment_js = '';

	$attachmentoption = '';

	if (defined('COMMENTPREVIEW'))
	{
		$postpreview =& $preview;
		$blog['message'] = htmlspecialchars_uni($blog['message']);
		$title = $blog['title'];
		$reason = $blog['reason'];
		construct_checkboxes($blog);
		$notification = array($vbulletin->GPC['emailupdate'] => 'selected="selected"');
	}
	else
	{ // defaults in here if we're doing a quote etc
		if ($bloginfo['issubscribed'])
		{
			$notification = array($bloginfo['emailupdate'] => 'selected="selected"');
		}
		else
		{
			$notification = array($vbulletin->userinfo['blog_subscribeothers'] => 'selected="selected"');
		}
	}

	($hook = vBulletinHook::fetch_hook('blog_post_comment_start')) ? eval($hook) : false;

	$bloginfo['title_trimmed'] = fetch_trimmed_title($bloginfo['title']);

	$editorid = construct_edit_toolbar(
		$blog['message'],
		false,
		'blog_comment',
		$vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowsmilies'],
		true,
		false
	);

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// image verification
	$imagereg = '';
	$human_verify = '';
	if (!$vbulletin->userinfo['userid'])
	{
		if ($show['blog_37_compatible'])
		{	// vBulletin 3.7.x
			if ($vbulletin->options['hvcheck_post'])
			{
				require_once(DIR . '/includes/class_humanverify.php');
				$verification =& vB_HumanVerify::fetch_library($vbulletin);
				$human_verify = $verification->output_token();
			}
		}
		else if ($vbulletin->options['regimagetype'] AND $vbulletin->options['postimagecheck'])
		{	// vBulletin 3.6.x
			require_once(DIR . '/includes/functions_regimage.php');
			$imagehash = fetch_regimage_hash();
			eval('$imagereg = "' . fetch_template('imagereg') . '";');
		}
	}

	cache_ordered_categories($bloginfo['userid']);
	$sidebar =& build_user_sidebar($bloginfo, 0, 0, 'comment');

	// draw nav bar
	$navbits = array(
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$bloginfo[userid]" => $bloginfo['blog_title'],
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]" => $bloginfo['title'],
		'' => $vbphrase['post_a_comment'],
	);

	// auto-parse URL
	if (!isset($checked['parseurl']))
	{
		$checked['parseurl'] = 'checked="checked"';
	}

	$show['parseurl'] = ($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowbbcode']);
	$show['misc_options'] = ($show['parseurl'] OR !empty($disablesmiliesoption));
	$show['additional_options'] = ($show['misc_options'] OR !empty($attachmentoption));

	($hook = vBulletinHook::fetch_hook('blog_post_comment_complete')) ? eval($hook) : false;

	// complete
	eval('$content = "' . fetch_template('blog_comment_editor') . '";');
}

// #######################################################################
if ($_REQUEST['do'] == 'editcomment')
{
	require_once(DIR . '/includes/functions_editor.php');

	// get attachment options
	require_once(DIR . '/includes/functions_file.php');
	$inimaxattach = fetch_max_upload_size();
	$maxattachsize = vb_number_format($inimaxattach, 1, true);
	$attachcount = 0;
	$attach_editor = array();
	$attachment_js = '';

	$attachmentoption = '';

	$title = $blogtextinfo['title'];
	$blog['message'] = htmlspecialchars_uni($blogtextinfo['pagetext']);
	$reason = $blogtextinfo['edit_reason'];

	if ($vbulletin->userinfo['userid'] == $blogtextinfo['userid'])
	{
		if ($bloginfo['issubscribed'])
		{
			$notification = array($bloginfo['emailupdate'] => 'selected="selected"');
		}
	}
	else if ($subscribed = $db->query_first("SELECT type AS emailupdate FROM " . TABLE_PREFIX . "blog_subscribeentry WHERE blogid = $bloginfo[blogid] AND userid = $blogtextinfo[userid]"))
	{
		$notification = array($subscribed['emailupdate'] => 'selected="selected"');
	}

	($hook = vBulletinHook::fetch_hook('blog_post_editcomment_start')) ? eval($hook) : false;

	$bloginfo['title_trimmed'] = fetch_trimmed_title($bloginfo['title']);

	$editorid = construct_edit_toolbar(
		$blog['message'],
		false,
		'blog_comment',
		$vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowsmilies'],
		true,
		false
	);

	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	// draw nav bar
	$navbits = array(
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$bloginfo[userid]" => $bloginfo['blog_title'],
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]" => $bloginfo['title'],
		'' => $vbphrase['edit_comment'],
	);

	// auto-parse URL
	if (!isset($checked['parseurl']))
	{
		$checked['parseurl'] = 'checked="checked"';
	}

	$show['parseurl'] = ($vbulletin->userinfo['permissions']['vbblog_comment_permissions'] & $vbulletin->bf_ugp_vbblog_comment_permissions['blog_allowbbcode']);
	$show['misc_options'] = ($show['parseurl'] OR !empty($disablesmiliesoption));
	$show['additional_options'] = ($show['misc_options'] OR !empty($attachmentoption));
	$show['edit'] = true;
	$show['delete'] = fetch_comment_perm('candeletecomments', $bloginfo, $blogtextinfo);
	$show['physicaldeleteoption'] = can_moderate_blog('canremovecomments');

	cache_ordered_categories($bloginfo['userid']);
	$sidebar =& build_user_sidebar($bloginfo, 0, 0, 'comment');

	($hook = vBulletinHook::fetch_hook('blog_post_editcomment_complete')) ? eval($hook) : false;

	$url =& $vbulletin->url;
	// complete
	eval('$content = "' . fetch_template('blog_comment_editor') . '";');
}

// #######################################################################
if ($_POST['do'] == 'updatetrackback')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'blogtrackbackid'	=> TYPE_UINT,
		'title'             => TYPE_NOHTML,
		'snippet'           => TYPE_NOHTML
	));

	if (!($trackbackinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "blog_trackback WHERE blogtrackbackid = " . $vbulletin->GPC['blogtrackbackid'])))
	{
		standard_error(fetch_error('invalidid', $vbphrase['trackback'], $vbulletin->options['contactuslink']));
	}

	$bloginfo = verify_blog($trackbackinfo['blogid']);

	if ($trackbackinfo['state'] == 'moderation' AND !can_moderate_blog('canmoderatecomments') AND ($vbulletin->userinfo['userid'] != $bloginfo['userid'] OR !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments'])))
	{
		standard_error(fetch_error('invalidid', $vbphrase['trackback'], $vbulletin->options['contactuslink']));
	}

	if (($bloginfo['state'] == 'deleted' AND !can_moderate_blog('candeleteentries')) OR ($bloginfo['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries')))
	{
		print_no_permission();
	}

	$dataman =& datamanager_init('Blog_Trackback', $vbulletin, ERRTYPE_ARRAY);
	$dataman->set_existing($trackbackinfo);
	$dataman->set_info('skip_build_blog_entry_counters', true);
	$dataman->set('title', $vbulletin->GPC['title']);
	$dataman->set('snippet', $vbulletin->GPC['snippet']);

	$dataman->pre_save();

	// check for errors
	if (!empty($dataman->errors))
	{
		$_REQUEST['do'] = 'edittrackback';

		$errorlist = '';
		foreach ($dataman->errors AS $index => $error)
		{
			$errorlist .= "<li>$error</li>";
		}

		$title = htmlspecialchars_uni($vbulletin->GPC['title']);
		$snippet = htmlspecialchars_uni($vbulletin->GPC['snippet']);

		$show['errors'] = true;
	}
	else
	{
		$show['errors'] = false;

		$dataman->save();

		// if this is a mod edit, then log it
		if ($vbulletin->userinfo['userid'] != $bloginfo['userid'] AND can_moderate('caneditcomments'))
		{
			require_once(DIR . '/includes/blog_functions_log_error.php');
			blog_moderator_action($trackbackinfo, 'trackback_x_edited', array($trackbackinfo['title']));
		}

		#$vbulletin->url = 'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]#trackbacks";
		eval(print_standard_redirect('redirect_blog_edittrackback'));
	}
}

// #######################################################################
if ($_REQUEST['do'] == 'edittrackback')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'blogtrackbackid'	=> TYPE_UINT
	));

	if (!($trackbackinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "blog_trackback WHERE blogtrackbackid = " . $vbulletin->GPC['blogtrackbackid'])))
	{
		standard_error(fetch_error('invalidid', $vbphrase['trackback'], $vbulletin->options['contactuslink']));
	}

	$bloginfo = verify_blog($trackbackinfo['blogid']);

	if ($trackbackinfo['state'] == 'moderation' AND !can_moderate_blog('canmoderatecomments') AND ($vbulletin->userinfo['userid'] != $bloginfo['userid'] OR !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments'])))
	{
		standard_error(fetch_error('invalidid', $vbphrase['trackback'], $vbulletin->options['contactuslink']));
	}

	if (($bloginfo['state'] == 'deleted' AND !can_moderate_blog('candeleteentries')) OR ($bloginfo['state'] == 'moderation' AND !can_moderate_blog('canmoderateentries')))
	{
		print_no_permission();
	}

	if ($show['errors'])
	{
		$trackbackinfo['title'] = $title;
		$trackbackinfo['snippet'] = $snippet;
	}

	cache_ordered_categories($bloginfo['userid']);
	$sidebar =& build_user_sidebar($bloginfo);

	// draw nav bar
	$navbits = array(
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "u=$bloginfo[userid]" => $bloginfo['blog_title'],
		'blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]" => $bloginfo['title'],
		'' => $vbphrase['edit_trackback'],
	);

	($hook = vBulletinHook::fetch_hook('blog_post_edittrackback_complete')) ? eval($hook) : false;

	// complete
	$url = $vbulletin->url;
	eval('$content = "' . fetch_template('blog_edit_trackback') . '";');
}

// build navbar
if (empty($navbits))
{
	$navbits[] = $vbphrase['blogs'];
}
else
{
	$navbits = array_merge(array('blog.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['blogs']), $navbits);
}
$navbits = construct_navbits($navbits);

($hook = vBulletinHook::fetch_hook('blog_post_complete')) ? eval($hook) : false;

eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('BLOG') . '");');

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25228 $
|| ####################################################################
\*======================================================================*/
?>