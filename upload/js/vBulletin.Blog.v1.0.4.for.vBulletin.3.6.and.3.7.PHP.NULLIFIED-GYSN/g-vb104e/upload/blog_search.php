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
define('THIS_SCRIPT', 'blog_search');
define('VBBLOG_PERMS', 1);
define('VBBLOG_STYLE', 1);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('vbblogglobal', 'posting', 'search');

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'blogstats',
	'blogfeatured',

);

// pre-cache templates used by all actions
$globaltemplates = array(
	'BLOG',
);

// pre-cache templates used by specific actions
$actiontemplates = array(
	'search'					=> array(
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_search_advanced',
		'blog_sidebar_generic',
		'blog_sidebar_user',
		'blog_sidebar_comment_link',
		'blog_sidebar_category_link',
		'imagereg',
		'humanverify',
	),
	'searchresults'		=>	array(
		'blog_sidebar_calendar',
		'blog_sidebar_calendar_day',
		'blog_sidebar_generic',
		'blog_search_results_result',
		'blog_search_results',
		'blog_sidebar_user',
		'blog_sidebar_comment_link',
		'blog_sidebar_category_link',
	),
);

$actiontemplates['dosearch'] =& $actiontemplates['search'];

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'search';
}

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/blog_init.php');

ini_set('memory_limit', -1);

// ### STANDARD INITIALIZATIONS ###
$navbits = array();

/* Check they can view a blog, any blog */
if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
{
	if (!$vbulletin->userinfo['userid'] OR !($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_canviewown']))
	{
		print_no_permission();
	}
}

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################


$searcherrors = array();
$search_fields = array(
	/* Primary search things */
	'title'          => TYPE_STR,
	'text'           => TYPE_STR,
	'comments_title' => TYPE_STR,
	'comments_text'  => TYPE_STR,
	'textortitle'    => TYPE_STR,
	'searchuserid'   => TYPE_UINT,
	'username'       => TYPE_STR,
);

$optional_fields = array(
	/* Optional extras */
	'sort'           => TYPE_NOHTML,
	'sortorder'      => TYPE_NOHTML,
	'ignorecomments' => TYPE_BOOL,
	'quicksearch'    => TYPE_BOOL,
	'titleonly'      => TYPE_BOOL,
	'boolean'        => TYPE_BOOL,
	'imagehash'      => TYPE_STR,
	'imagestamp'     => TYPE_STR,
	'humanverify'    => TYPE_ARRAY,
);

($hook = vBulletinHook::fetch_hook('blog_search_start')) ? eval($hook) : false;

// #######################################################################
if ($_POST['do'] == 'dosearch')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansearch']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('p', $search_fields + $optional_fields);

	($hook = vBulletinHook::fetch_hook('blog_search_dosearch_start')) ? eval($hook) : false;

	if ($prevsearch = $db->query_first("
		SELECT blogsearchid, dateline
		FROM " . TABLE_PREFIX . "blog_search
		WHERE " . (!$vbulletin->userinfo['userid'] ?
			"ipaddress = '" . $db->escape_string(IPADDRESS) . "'" :
			"userid = " . $vbulletin->userinfo['userid']) . "
		ORDER BY dateline DESC LIMIT 1
	"))
	{
		if ($vbulletin->options['searchfloodtime'] > 0)
		{
			$timepassed = TIMENOW - $prevsearch['dateline'];
			$is_special_user = (($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR can_moderate());

			if ($timepassed < $vbulletin->options['searchfloodtime'] AND !$is_special_user)
			{
				$searcherrors[] = fetch_error('searchfloodcheck', $vbulletin->options['searchfloodtime'], ($vbulletin->options['searchfloodtime'] - $timepassed));
			}
		}
	}

	$criteria = array();
	foreach (array_keys($search_fields + $optional_fields) AS $varname)
	{
		$criteria["$varname"] = $vbulletin->GPC["$varname"];
	}

	if (!$vbulletin->userinfo['userid'])
	{
		if ($show['blog_37_compatible'])
		{
			if ($vbulletin->options['hvcheck_search'])
			{
				require_once(DIR . '/includes/class_humanverify.php');
				$verify =& vB_HumanVerify::fetch_library($vbulletin);
				if (!$verify->verify_token($vbulletin->GPC['humanverify']))
				{
					if ($criteria['quicksearch'])
					{
						$searcherrors[] = fetch_error('please_complete_humanverification');
					}
					else
					{
						$searcherrors[] = fetch_error($verify->fetch_error());
					}
				}
			}
		}
		else if ($vbulletin->options['regimagetype'] AND $vbulletin->options['searchimagecheck'])
		{
			require_once(DIR . '/includes/functions_regimage.php');
			if (!verify_regimage_hash($vbulletin->GPC['imagehash'], $vbulletin->GPC['imagestamp']))
			{
				if ($criteria['quicksearch'])
				{
					$searcherrors[] = fetch_error('register_enter_imagecheck');
				}
				else
				{
					$searcherrors[] = fetch_error('register_imagecheck');
				}
			}
		}
	}

	if (empty($searcherrors))
	{
		if (($criteria['quicksearch'] AND !$criteria['titleonly']) OR $criteria['boolean'] == 1)
		{
			$criteria['textortitle'] = $criteria['title'];
			$criteria['title'] = '';
		}

		if ($criteria['ignorecomments'])
		{ // we only want posts
			$criteria['comments_title'] = $criteria['comments_text'] = '';
		}
		else
		{
			$criteria['comments_title'] = $criteria['title'];
			$criteria['comments_text'] = $criteria['text'];
			#$criteria['title'] = $criteria['text'] = '';
		}

		require_once(DIR . '/includes/class_blog_search.php');
		$search =& new vB_Blog_Search($vbulletin);

		$has_criteria = false;
		foreach ($search_fields AS $fieldname => $clean_type)
		{
			if (!empty($criteria["$fieldname"]))
			{
				if ($search->add($fieldname, $criteria["$fieldname"]))
				{
					$has_criteria = true;
				}
			}
		}

		$search->set_sort($criteria['sort'], $criteria['sortorder']);

		if ($search->has_errors())
		{
			$searcherrors = $search->generator->errors;
		}

		if (!$search->has_criteria())
		{
			$searcherrors[] = fetch_error('blog_need_search_criteria');
		}

		if (empty($searcherrors))
		{
			$search_perms = build_blog_permissions_query($vbulletin->userinfo);
			$searchid = $search->execute($search_perms);
			($hook = vBulletinHook::fetch_hook('blog_search_dosearch_complete')) ? eval($hook) : false;

			if ($search->has_errors())
			{
				$searcherrors = $search->generator->errors;
			}
			else
			{
				$vbulletin->url = 'blog_search.php?' . $vbulletin->session->vars['sessionurl'] . "do=searchresults&searchid=$searchid";
				eval(print_standard_redirect('blog_search_executed'));
			}
		}
	}

	$_REQUEST['do'] = 'search';
}

// #######################################################################
if ($_REQUEST['do'] == 'searchresults')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'searchid'   => TYPE_UINT,
		'start'      => TYPE_UINT,
		'pagenumber' => TYPE_UINT,
		'perpage'    => TYPE_UINT
	));

	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansearch']))
	{
		print_no_permission();
	}

	$search = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "blog_search
		WHERE blogsearchid = " . $vbulletin->GPC['searchid']
	);
	if (!$search)
	{
		standard_error(fetch_error('invalidid', $vbphrase['search'], $vbulletin->options['contactuslink']));
	}

	($hook = vBulletinHook::fetch_hook('blog_search_results_start')) ? eval($hook) : false;

	if ($search['searchuserid'])
	{
		$userinfo = fetch_userinfo($search['searchuserid'], 1);
		cache_ordered_categories($userinfo['userid']);
		$sidebar =& build_user_sidebar($userinfo);
	}
	else
	{
		$sidebar =& build_overview_sidebar();
	}

	// Set Perpage .. this limits it to 10. Any reason for more?
	if ($vbulletin->GPC['perpage'] == 0)
	{
		$perpage = 15;
	}
	else if ($vbulletin->GPC['perpage'] > 10)
	{
		$perpage = 30;
	}
	else
	{
		$perpage = $vbulletin->GPC['perpage'];
	}

	$pagenum = ($vbulletin->GPC['pagenumber'] > 0 ? $vbulletin->GPC['pagenumber'] : 1);
	$maxpages = ceil($search['resultcount'] / $perpage);
	if ($pagenum > $maxpages)
	{
		$pagenum = $maxpages;
	}

	if (!$vbulletin->GPC['start'])
	{
		$vbulletin->GPC['start'] = ($pagenum - 1) * $perpage;
		$previous_results = $vbulletin->GPC['start'];
	}
	else
	{
		$previous_results = ($pagenum - 1) * $perpage;
	}
	$previouspage = $pagenum - 1;
	$nextpage = $pagenum + 1;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('blog_search_results_query')) ? eval($hook) : false;

	$results = $db->query_read("
		SELECT blog.*, blog_searchresult.offset
		" . (($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true)) ? "
		,IF(blog_tachyentry.userid IS NULL, blog.lastcomment, blog_tachyentry.lastcomment) AS lastcomment
		,IF(blog_tachyentry.userid IS NULL, blog.lastcommenter, blog_tachyentry.lastcommenter) AS lastcommenter
		,IF(blog_tachyentry.userid IS NULL, blog.lastblogtextid, blog_tachyentry.lastblogtextid) AS lastblogtextid
		" : "") . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "blog_searchresult AS blog_searchresult
		INNER JOIN " . TABLE_PREFIX . "blog AS blog ON (blog_searchresult.id = blog.blogid)
		" . (($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true)) ? "
		LEFT JOIN " . TABLE_PREFIX . "blog_tachyentry AS blog_tachyentry ON (blog_tachyentry.blogid = blog.blogid AND blog_tachyentry.userid = " . $vbulletin->userinfo['userid'] . ")
		" : "") . "
		$hook_query_joins
		WHERE blog_searchresult.blogsearchid = $search[blogsearchid]
			AND blog_searchresult.offset >= " . $vbulletin->GPC['start'] . "
		$hook_query_where
		ORDER BY offset
		LIMIT $perpage
	");

	$resultbits = '';
	while ($blog = $db->fetch_array($results))
	{
		$canmoderation = (can_moderate_blog('canmoderatecomments') OR $vbulletin->userinfo['userid'] == $blog['userid']);
		$blog['trackbacks_total'] = $blog['trackback_visible'] + ($canmoderation ? $blog['trackback_moderation'] : 0);
		$blog['comments_total'] = $blog['comments_visible'] + ($canmoderation ? $blog['comments_moderation'] : 0);
		$blog['lastcommenter_encoded'] = urlencode($blog['lastcommenter']);

		$blog['lastposttime'] = vbdate($vbulletin->options['timeformat'], $blog['lastcomment']);
		$blog['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $blog['lastcomment'], true);
		eval('$resultbits .= "' . fetch_template('blog_search_results_result') . '";');
	}

	$next_result = $previous_results + $db->num_rows($results) + 1;
	$show['next_page'] = ($next_result <= $search['resultcount']);
	$show['previous_page'] = ($pagenum > 1);
	$show['pagenav'] = ($show['next_page'] OR $show['previous_page']);
	$first = ($pagenum - 1) * $perpage + 1;
	$last = ($last = $perpage * $pagenum) > $search['resultcount'] ? $search['resultcount'] : $last;

	$pagenav = construct_page_nav(
		$pagenum,
		$perpage,
		$search['resultcount'],
		'blog_search.php?' . $vbulletin->session->vars['sessionurl'] . "do=searchresults&amp;searchid=$search[blogsearchid]",
		''
	);

	// navbar and output
	$navbits['blog_search.php?' . $vbulletin->session->var['sessionurl'] . 'do=search'] = $vbphrase['search'];
	$navbits[] = $vbphrase['search_results'];

	($hook = vBulletinHook::fetch_hook('blog_search_results_complete')) ? eval($hook) : false;

	eval('$content = "' . fetch_template('blog_search_results') . '";');
}

// #######################################################################
if ($_REQUEST['do'] == 'search')
{
	if (!($vbulletin->userinfo['permissions']['vbblog_general_permissions'] & $vbulletin->bf_ugp_vbblog_general_permissions['blog_cansearch']))
	{
		print_no_permission();
	}

	$vbulletin->input->clean_array_gpc('r', $search_fields + $optional_fields);

	($hook = vBulletinHook::fetch_hook('blog_search_form_start')) ? eval($hook) : false;

	if (!empty($searcherrors))
	{
		$errorlist = '';
		foreach($searcherrors AS $error)
		{
			$errorlist .= "<li>$error</li>";
		}
		$show['errors'] = true;
	}

	if ($vbulletin->GPC['quicksearch'])
	{
		if (!$vbulletin->GPC['titleonly'])
		{
			$vbulletin->GPC['text'] = $vbulletin->GPC['title'];
			$vbulletin->GPC_exists['text'] = true;
		}
		$vbulletin->GPC['boolean'] = 1;
		$vbulletin->GPC_exists['boolean'] = true;
	}
	else if (!$vbulletin->GPC['boolean'])
	{
		$vbulletin->GPC['boolean'] = 1;
		$vbulletin->GPC_exists['boolean'] = true;
	}

	// if search conditions are specified in the URI, use them
	foreach (array_keys($search_fields + $optional_fields) AS $varname)
	{
		if ($vbulletin->GPC_exists["$varname"] AND !is_array($vbulletin->GPC["$varname"]))
		{
			$$varname = htmlspecialchars_uni($vbulletin->GPC["$varname"]);
			$checkedvar = $varname . 'checked';
			$selectedvar = $varname . 'selected';
			$$checkedvar = array($vbulletin->GPC["$varname"] => 'checked="checked"');
			$$selectedvar = array($vbulletin->GPC["$varname"] => 'selected="selected"');
		}
	}

	// image verification
	$imagereg = '';
	$human_verify = '';
	if (!$vbulletin->userinfo['userid'])
	{
		if ($show['blog_37_compatible'])
		{	// vBulletin 3.7.x
			if ($vbulletin->options['hvcheck_search'])
			{
				require_once(DIR . '/includes/class_humanverify.php');
				$verification =& vB_HumanVerify::fetch_library($vbulletin);
				$human_verify = $verification->output_token();
			}
		}
		else if ($vbulletin->options['regimagetype'] AND $vbulletin->options['searchimagecheck'])
		{	// vBulletin 3.6.x
			require_once(DIR . '/includes/functions_regimage.php');
			$imagehash = fetch_regimage_hash();
			eval('$imagereg = "' . fetch_template('imagereg') . '";');
		}
	}

	// navbar and output
	$navbits[] = $vbphrase['search'];

	$sidebar =& build_overview_sidebar();

	($hook = vBulletinHook::fetch_hook('blog_search_form_complete')) ? eval($hook) : false;

	eval('$content = "' . fetch_template('blog_search_advanced') . '";');
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

($hook = vBulletinHook::fetch_hook('blog_search_complete')) ? eval($hook) : false;

eval('$navbar = "' . fetch_template('navbar') . '";');
eval('print_output("' . fetch_template('BLOG') . '");');

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25228 $
|| ####################################################################
\*======================================================================*/
?>