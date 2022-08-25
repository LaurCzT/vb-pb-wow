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
define('THIS_SCRIPT', 'blog_report');
define('VBBLOG_PERMS', 1);
define('VBBLOG_STYLE', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'vbblogglobal',
	'messaging',
	'postbit',
);

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array(
	'BLOG',
	'blog_sidebar_calendar',
	'blog_sidebar_calendar_day',
	'blog_sidebar_category_link',
	'blog_sidebar_comment_link',
	'blog_report_item',
	'blog_sidebar_user',
	'newpost_usernamecode',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/blog_init.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

//check usergroup of user to see if they can use this
if (!$vbulletin->userinfo['userid'])
{
	print_no_permission();
}

$reportthread = ($rpforumid = $vbulletin->options['rpforumid'] AND $rpforuminfo = fetch_foruminfo($rpforumid));
$reportemail = ($vbulletin->options['enableemail'] AND $vbulletin->options['rpemail']);

if (!$reportthread AND !$reportemail)
{
	standard_error(fetch_error('emaildisabled'));
}

$perform_floodcheck = (
	!($permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
	AND $vbulletin->options['emailfloodtime']
	AND $vbulletin->userinfo['userid']
);

if ($perform_floodcheck AND ($timepassed = TIMENOW - $vbulletin->userinfo['emailstamp']) < $vbulletin->options['emailfloodtime'])
{
	standard_error(fetch_error('emailfloodcheck', $vbulletin->options['emailfloodtime'], ($vbulletin->options['emailfloodtime'] - $timepassed)));
}

$bloginfo = verify_blog($blogid);

if ($blogtextinfo)
{
	if ($blogtextinfo['blogtextid'] == $bloginfo['firstblogtextid'])
	{
		$blogtextinfo = array();
		$blogtextid = $bloginfo['firstblogtextid'];
	}
	else if (!fetch_comment_perm('canviewcomments', $bloginfo, $blogtextinfo))
	{
		print_no_permission();
	}
}
else
{
	$blogtextid = $bloginfo['firstblogtextid'];
}

if ($bloginfo['state'] == 'draft' OR $bloginfo['pending'])
{
	standard_error(fetch_error('invalidid', $vbphrase['blog'], $vbulletin->options['contactuslink']));
}

$reportthreadid = $blogtextinfo ? $blogtextinfo['reportthreadid'] : $bloginfo['reportthreadid'];

($hook = vBulletinHook::fetch_hook('blog_report_start')) ? eval($hook) : false;

if (empty($_POST['do']))
{

	// draw nav bar
	$navbits = array();
	if ($blogtextinfo)
	{
		$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "bt=$blogtextinfo[blogtextid]"] = $bloginfo['title'];
	}
	else
	{
		$navbits['blog.php?' . $vbulletin->session->vars['sessionurl'] . "b=$bloginfo[blogid]"] = $bloginfo['title'];
	}
	$navbits[''] = $vbphrase['report_blog_entry'];

	require_once(DIR . '/includes/functions_editor.php');
	$textareacols = fetch_textarea_width();
	eval('$usernamecode = "' . fetch_template('newpost_usernamecode') . '";');

	$bloginfo['title_trimmed'] = fetch_trimmed_title($bloginfo['title']);

	($hook = vBulletinHook::fetch_hook('blog_report_form_start')) ? eval($hook) : false;

	$url = $vbulletin->url;
	eval('$content = "' . fetch_template('blog_report_item') . '";');
}

if ($_POST['do'] == 'sendemail')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'reason'	=> TYPE_STR,
	));

	if ($vbulletin->GPC['reason'] == '')
	{
		standard_error(fetch_error('noreason'));
	}

	// trim the reason so it's not too long
	if ($vbulletin->options['postmaxchars'] > 0)
	{
		$trimmed_reason = substr($vbulletin->GPC['reason'], 0, $vbulletin->options['postmaxchars']);
	}
	else
	{
		$trimmed_reason = $vbulletin->GPC['reason'];
	}

	if ($perform_floodcheck)
	{
		$flood_limit = ($reportemail ? $vbulletin->options['emailfloodtime'] : $vbulletin->options['floodchecktime']);
		require_once(DIR . '/includes/class_floodcheck.php');
		$floodcheck =& new vB_FloodCheck($vbulletin, 'user', 'emailstamp');
		$floodcheck->commit_key($vbulletin->userinfo['userid'], TIMENOW, TIMENOW - $flood_limit);
		if ($floodcheck->is_flooding())
		{
			standard_error(fetch_error('emailfloodcheck', $flood_limit, $floodcheck->flood_wait()));
		}
	}

	$mods = array();
	$moderators = $db->query_read_slave("
		SELECT DISTINCT user.email, user.languageid, user.userid, user.username
		FROM " . TABLE_PREFIX . "blog_moderator AS blog_moderator
		INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
	");
	while ($moderator = $db->fetch_array($moderators))
	{
		$mods["$moderator[userid]"] = $moderator;
		$modlist .= (!empty($modlist) ? ', ' : '') . unhtmlspecialchars($moderator['username']);
	}

	if (empty($modlist))
	{
		$modlist = $vbphrase['n_a'];
	}

	if ($reportthread)
	{
		// Determine if we need to create a thread or a post

		if (!$reportthreadid OR
			!($rpthreadinfo = fetch_threadinfo($reportthreadid)) OR
			($rpthreadinfo AND (
				$rpthreadinfo['isdeleted'] OR
				!$rpthreadinfo['visible'] OR
				$rpthreadinfo['forumid'] != $rpforuminfo['forumid'])
			))
		{
			// post not been reported or reported thread was deleted/moderated/moved
			$reportinfo = array(
				'blogtitle'  => unhtmlspecialchars($bloginfo['blog_title']),
				'entrytitle' => unhtmlspecialchars($bloginfo['title']),
				'rusername'  => unhtmlspecialchars($vbulletin->userinfo['username']),
				'pusername'  => unhtmlspecialchars($blogtextinfo ? $blogtextinfo['username'] : $bloginfo['username']),
				'reason'     => $trimmed_reason,
			);

			if ($blogtextinfo)
			{
				eval(fetch_email_phrases('blog_reportcomment_thread', 0));
			}
			else
			{
				eval(fetch_email_phrases('blog_reportentry_thread', 0));
			}

			if (!$vbulletin->options['rpuserid'] OR !($userinfo = fetch_userinfo($vbulletin->options['rpuserid'])))
			{
				$userinfo =& $vbulletin->userinfo;
			}
			$threadman =& datamanager_init('Thread_FirstPost', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_info('forum', $rpforuminfo);
			$threadman->set_info('skip_moderator_email', true);
			$threadman->set_info('skip_floodcheck', true);
			$threadman->set_info('skip_charcount', true);
			$threadman->set_info('mark_thread_read', true);
			$threadman->set_info('skip_title_error', true);
			$threadman->set_info('parseurl', true);
			$threadman->set('allowsmilie', true);
			$threadman->set('userid', $userinfo['userid']);
			$threadman->setr_info('user', $userinfo);
			$threadman->set('title', $subject);
			$threadman->set('pagetext', $message);
			$threadman->set('forumid', $rpforuminfo['forumid']);
			$threadman->set('visible', 1);
			if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
			{
				// not posting as the current user, IP won't make sense
				$threadman->set('ipaddress', '');
			}
			$rpthreadid = $threadman->save();

			$blogman =& datamanager_init('BlogText', $vbulletin, ERRTYPE_SILENT, 'blog');
			$blogman->set_info('skip_floodcheck', true);
			$blogman->set_info('skip_charcount', true);
			$blogman->set_info('skip_build_blog_counters', true);
			$blogman->set_info('skip_build_category_counters', true);
			$blogman->set_info('parseurl', true);
			$blogman->set('reportthreadid', $rpthreadid);

			// if $reportthreadid exists then it means then the discussion thread has been deleted/moved
			$checkrpid = ($reportthreadid ? $reportthreadid : 0);
			$blogman->condition = "blogtextid = $blogtextid AND reportthreadid = $checkrpid";
			if (!$blogman->save(true, false, true)) // affected_rows = 0, meaning another user reported this before us (race condition)
			{
				// Delete the thread we just created
				if ($delthread = fetch_threadinfo($rpthreadid))
				{
					$$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
					$threadman->set_existing($delthread);
					$threadman->delete($rpforuminfo['countposts'], true, NULL, false);
					unset($threadman);
				}

				// Get the reported thread id so we can now insert a post
				$rpinfo = $db->query_first("
					SELECT reportthreadid
					FROM " . TABLE_PREFIX . "blog_text AS blog_text
					WHERE blogtextid = $blogtextid
				");
				if ($rpinfo['reportthreadid'])
				{
					$reportthreadid = $rpinfo['reportthreadid'];
				}
			}
			else
			{
				$threadman->set_info('skip_moderator_email', false);
				$threadman->email_moderators(array('newthreademail', 'newpostemail'));
				$reportthreadid = 0;
				$rpthreadinfo = array(
					'threadid'   => $rpthreadid,
					'forumid'    => $rpforuminfo['forumid'],
					'postuserid' => $userinfo['userid'],
				);

				// check the permission of the other user
				$userperms = fetch_permissions($rpthreadinfo['forumid'], $userinfo['userid'], $userinfo);
				if (($userperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($userperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) AND $userinfo['autosubscribe'] != -1)
				{
					$vbulletin->db->query_write("
						INSERT IGNORE INTO " . TABLE_PREFIX . "subscribethread
							(userid, threadid, emailupdate, folderid, canview)
						VALUES
							(" . $userinfo['userid'] . ", $rpthreadinfo[threadid], $userinfo[autosubscribe], 0, 1)
					");
				}
			}

			unset($threadman);
			unset($postman);
		}
		else
		{
			$rpthreadid = $reportthreadid;
		}

		if ($reportthreadid AND
			$rpthreadinfo = fetch_threadinfo($reportthreadid) AND
			!$rpthreadinfo['isdeleted'] AND
			$rpthreadinfo['visible'] == 1 AND
			$rpthreadinfo['forumid'] == $rpforuminfo['forumid'])
		{
			// Already reported, thread still exists/visible, and thread is in the right forum.
			// Technically, if the thread exists but is in the wrong forum, we should create the
			// thread, but that should only occur in a race condition.
			$reportinfo = array(
				'rusername' => unhtmlspecialchars($vbulletin->userinfo['username']),
				'reason'    => $trimmed_reason,
			);
			if ($blogtextinfo)
			{
				eval(fetch_email_phrases('blog_reportcomment_post', 0));
			}
			else
			{
				eval(fetch_email_phrases('blog_reportentry_post', 0));
			}

			if (!$vbulletin->options['rpuserid'] OR (!$userinfo AND !($userinfo = fetch_userinfo($vbulletin->options['rpuserid']))))
			{
				$userinfo =& $vbulletin->userinfo;
			}

			$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
			$postman->set_info('thread', $rpthreadinfo);
			$postman->set_info('forum', $rpforuminfo);
			$postman->set_info('skip_floodcheck', true);
			$postman->set_info('skip_charcount', true);
			$postman->set_info('parseurl', true);
			$postman->set('threadid', $rpthreadid);
			$postman->set('userid', $userinfo['userid']);
			$postman->set('allowsmilie', true);
			$postman->set('visible', true);
			$postman->set('title', $subject);
			$postman->set('pagetext', $message);
			if ($userinfo['userid'] != $vbulletin->userinfo['userid'])
			{
				// not posting as the current user, IP won't make sense
				$postman->set('ipaddress', '');
			}
			$postman->save();
			unset($postman);
		}
	}

	// Send Email to moderators/supermods/admins
	if ($reportemail)
	{
		$threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
		$postinfo['title'] = unhtmlspecialchars($postinfo['title']);

		$reportinfo = array(
			'blogtitle'  => unhtmlspecialchars($bloginfo['blog_title']),
			'entrytitle' => unhtmlspecialchars($bloginfo['title']),
			'rusername'  => unhtmlspecialchars($vbulletin->userinfo['username']),
			'pusername'  => unhtmlspecialchars($blogtextinfo ? $blogtextinfo['username'] : $bloginfo['username']),
			'puserid'    => $blogtextinfo ? $blogtextinfo['userid'] : $bloginfo['userid'],
			'reason'     => $trimmed_reason,
		);

		if (empty($mods) OR $vbulletin->options['rpemail'] == 2)
		{
			$moderators = $db->query_read_slave("
				SELECT DISTINCT user.email, user.languageid, user.username, user.userid
				FROM " . TABLE_PREFIX . "usergroup AS usergroup
				INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
				WHERE usergroup.adminpermissions <> 0
				" . (!empty($mods) ? "AND userid NOT IN (" . implode(',', array_keys($mods)) . ")" : "") . "
			");

			while ($moderator = $db->fetch_array($moderators))
			{
				$mods["$moderator[userid]"] = $moderator;
			}
		}

		($hook = vBulletinHook::fetch_hook('blog_report_send_process')) ? eval($hook) : false;

		foreach ($mods AS $userid => $moderator)
		{
			if (!empty($moderator['email']))
			{
				$email_langid = ($moderator['languageid'] > 0 ? $moderator['languageid'] : $vbulletin->options['languageid']);

				($hook = vBulletinHook::fetch_hook('blog_report_send_email')) ? eval($hook) : false;

				if ($rpthreadinfo)
				{	// had some permission checks here but it generated crazy queries
					if ($blogtextinfo)
					{
						eval(fetch_email_phrases('blog_reportcomment_discuss', $email_langid));
					}
					else
					{
						eval(fetch_email_phrases('blog_reportentry_discuss', $email_langid));
					}
				}
				else
				{
					if ($blogtextinfo)
					{
						eval(fetch_email_phrases('blog_reportcomment_nodiscuss', $email_langid));
					}
					else
					{
						eval(fetch_email_phrases('blog_reportentry_nodiscuss', $email_langid));
					}
				}

				vbmail($moderator['email'], $subject, $message, true);
			}
		}

		($hook = vBulletinHook::fetch_hook('blog_report_send_complete')) ? eval($hook) : false;
	}

	eval(print_standard_redirect('redirect_reportthanks'));
}

($hook = vBulletinHook::fetch_hook('blog_report_complete')) ? eval($hook) : false;

// build navbar
if (empty($navbits))
{
	$navbits[] = $vbphrase['blogs'];
}
else
{
	$navbits = array_merge(array('blog.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['blogs']), $navbits);
}

cache_ordered_categories($bloginfo['userid']);
$sidebar =& build_user_sidebar($bloginfo);

$navbits = construct_navbits($navbits);

eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('BLOG') . '");');

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 17991 $
|| ####################################################################
\*======================================================================*/
?>