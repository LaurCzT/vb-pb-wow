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

// Convert the blog's shortvars and then add them to the standard shortvars, mainly for who's online
$temp = $vbulletin->input->shortvars;
$vbulletin->input->shortvars = array(
	'b'  => 'blogid',
	'bt' => 'blogtextid',
	'm'  => 'month',
	'd'  => 'day',
	'y'  => 'year',
	'uc' => 'usercommentid',
);
foreach (array('_GET', '_POST') AS $arrayname)
{
	$vbulletin->input->convert_shortvars($GLOBALS["$arrayname"]);
}
$vbulletin->input->shortvars = array_merge($temp, $vbulletin->input->shortvars);

/**
* Determine moderator ability
*
* @param string		Permissions
* @param interger	Userid
* @param	string	Comma separated list of usergroups to which the user belongs
*
* @return	boolean
*/
function can_moderate_blog($do = '', $userid = -1, $usergroupids = '')
{
	global $vbulletin;

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}
	else if ($userid == 0)
	{
		return false;
	}

	$issupermod = false;
	$superpermissions = 0;
	if ($userid == $vbulletin->userinfo['userid'])
	{
		if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
		{
			DEVDEBUG('  USER IS A SUPER MODERATOR');
			$issupermod = true;
		}
	}
	else
	{
		if (!$usergroupids)
		{
			$tempuser = $vbulletin->db->query_first_slave("SELECT usergroupid, membergroupids FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
			if (!$tempuser)
			{
				return false;
			}
			$usergroupids = $tempuser['usergroupid'] . iif(trim($tempuser['membergroupids']), ",$tempuser[membergroupids]");
		}
		if ($supermodcheck = $vbulletin->db->query_first_slave("
			SELECT usergroupid
			FROM " . TABLE_PREFIX . "usergroup
			WHERE usergroupid IN ($usergroupids)
				AND (adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ") != 0
			LIMIT 1
		"))
		{
			DEVDEBUG('  USER IS A SUPER MODERATOR');
			$issupermod = true;
		}
	}

	if (empty($do))
	{
		if ($issupermod)
		{
			return true;
		}
		else if (isset($vbulletin->userinfo['isblogmoderator']))
		{
			if ($vbulletin->userinfo['isblogmoderator'])
			{
				DEVDEBUG('	USER HAS ISBLOGMODERATOR SET');
				return true;
			}
			else
			{
				DEVDEBUG('	USER DOES NOT HAVE ISBLOGMODERATOR SET');
				return false;
			}
		}
	}

	cache_blog_moderators();
	$permissions = intval($vbulletin->vbblog['modcache']["$userid"]['normal']['permissions']);
	if ($issupermod)
	{
		if (isset($vbulletin->vbblog['modcache']["$userid"]['super']))
		{
			$permissions |= $vbulletin->vbblog['modcache']["$userid"]['super']['permissions'];
		}
		else
		{
			$permissions |= array_sum($vbulletin->bf_misc_vbblogmoderatorpermissions);
		}
	}

	if (empty($do) AND $permissions)
	{
		return true;
	}
	else if ($permissions & $vbulletin->bf_misc_vbblogmoderatorpermissions["$do"])
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
* Cache blog moderators into $vbulletin->blog
*
* @return	void
*/
function cache_blog_moderators()
{
	global $vbulletin;

	if (!is_array($vbulletin->vbblog['modcache']))
	{
		$vbulletin->vbblog['modcache'] = array();
		$blogmoderators = $vbulletin->db->query_read_slave("
			SELECT bm.userid, bm.permissions, bm.type, user.username
			FROM " . TABLE_PREFIX . "blog_moderator AS bm
			INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
		");
		while ($moderator = $vbulletin->db->fetch_array($blogmoderators))
		{
			$vbulletin->vbblog['modcache']["$moderator[userid]"]["$moderator[type]"] = $moderator;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 23779 $
|| ####################################################################
\*======================================================================*/
?>