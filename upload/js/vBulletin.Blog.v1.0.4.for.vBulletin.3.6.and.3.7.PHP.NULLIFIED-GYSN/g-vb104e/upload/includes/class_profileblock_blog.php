<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 1.0.4
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

/**
* Class for Profile Blog Block
*
* @package vBulletin
*/
class vB_ProfileBlock_Blog extends vB_ProfileBlock
{
	/**
	* The name of the template to be used for the block
	*
	* @var string
	*/
	var $template_name = 'blog_member_block';

	/**
	* Whether or not the block is enabled
	*
	* @return bool
	*/
	function block_is_enabled()
	{
		$continue = false;
		if ($this->profile->userinfo['canviewmyblog'] AND $this->profile->userinfo['userid'] != $this->registry->userinfo['userid'] AND ($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewothers']))
		{ // Someone elses blog we can view
			$continue = true;
		}
		else if ($this->profile->userinfo['userid'] == $this->registry->userinfo['userid'] AND ($this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canviewown']))
		{ // Our own blog
			$continue = true;
		}

		if (!$continue)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Whether to return an empty wrapper if there is no content in the blocks
	*
	* @return bool
	*/
	function confirm_empty_wrap()
	{
		return false;
	}

	/**
	* Should we actually display anything?
	*
	* @return	bool
	*/
	function confirm_display()
	{
		return !(empty($this->block_data['latestentries']));
	}

	/**
	* Prepare any data needed for the output
	*
	* @param	string	The id of the block
	* @param	array	Options specific to the block
	*/
	function prepare_output($id = '', $options = array())
	{
		global $show, $vbphrase, $stylevar;

		$show['lastentry'] = true;
		$this->block_data['entries'] = vb_number_format($this->profile->userinfo['entries']);

		$this->block_data['lastblogtitle'] = '';
		$this->block_data['lastblogdate'] = $vbphrase['never'];
		$this->block_data['lastblogtime'] = '';

		if (!in_coventry($this->profile->userinfo['userid']) AND ($this->profile->userinfo['lastblog']))
		{
			$sql_and = array();
			$state = array('visible');

			$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
			$sql_and[] = "blog.dateline <= " . TIMENOW;
			$sql_and[] = "blog.pending = 0";
			$sql_and[] = "blog.userid = " . $this->profile->userinfo['userid'];

			$blogids = array();
			$blogs = $this->registry->db->query_read_slave("
				SELECT blogid, attach
				FROM " . TABLE_PREFIX . "blog AS blog
				WHERE " . implode("\r\n\tAND ", $sql_and) . "
				ORDER BY blog.dateline DESC
				LIMIT 5
			");
			while ($blog = $this->registry->db->fetch_array($blogs))
			{
				$blogids[] = $blog['blogid'];
				$attachcount += $blog['attach'];
			}

			if ($blogids)
			{
				$this->block_data['lastblogtitle'] = $this->profile->userinfo['lastblogtitle'];
				$this->block_data['lastblogdate'] = vbdate($this->registry->options['dateformat'], $this->profile->userinfo['lastblog']);
				$this->block_data['lastblogtime'] = vbdate($this->registry->options['timeformat'], $this->profile->userinfo['lastblog'], true);

				$categories = array();
				$cats = $this->registry->db->query_read_slave("
					SELECT blogid, title, blog_category.blogcategoryid, blog_category.userid
					FROM " . TABLE_PREFIX . "blog_categoryuser AS blog_categoryuser
					LEFT JOIN " . TABLE_PREFIX . "blog_category AS blog_category ON (blog_category.blogcategoryid = blog_categoryuser.blogcategoryid)
					WHERE blogid IN (" . implode(',', $blogids) . ")
					ORDER BY blogid, displayorder
				");
				while ($cat = $this->registry->db->fetch_array($cats))
				{
					$categories["$cat[blogid]"][] = $cat;
				}

				require_once(DIR . '/includes/class_bbcode_blog.php');
				require_once(DIR . '/includes/class_blog_entry.php');

				$bbcode =& new vB_BbCodeParser_Blog_Snippet_Featured($this->registry, fetch_tag_list());
				$factory =& new vB_Blog_EntryFactory($this->registry, $bbcode, $categories);

				$first = true;
				// Last Five Entries
				$entries = $this->registry->db->query_read_slave("
					SELECT blog.*, blog.options AS blogoptions, blog_text.pagetext, blog_text.allowsmilie, blog_text.ipaddress, blog_text.reportthreadid,
						blog_text.ipaddress AS blogipaddress,
						user.*, userfield.*, usertextfield.*
						" . (($this->registry->options['threadvoted'] AND $this->registry->userinfo['userid']) ? ', blog_rate.vote' : '') . "
						" . (!($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehiddencustomfields']) ? $this->registry->profilefield['hidden'] : "") . "
						" . (($this->registry->options['threadmarking'] AND $this->registry->userinfo['userid']) ? ", blog_read.readtime AS blogread, blog_userread.readtime  AS bloguserread" : "") . "
					FROM " . TABLE_PREFIX . "blog AS blog
					INNER JOIN " . TABLE_PREFIX . "blog_text AS blog_text ON (blog_text.blogtextid = blog.firstblogtextid)
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (blog.userid = user.userid)
					LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
					LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
					" . (($this->registry->options['threadmarking'] AND $this->registry->userinfo['userid']) ? "
					LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON (blog_read.blogid = blog.blogid AND blog_read.userid = " . $this->registry->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON (blog_userread.bloguserid = blog.userid AND blog_userread.userid = " . $this->registry->userinfo['userid'] . ")
					" : "") . "
					" . (($this->registry->options['threadvoted'] AND $this->registry->userinfo['userid']) ? "LEFT JOIN " . TABLE_PREFIX . "blog_rate AS blog_rate ON (blog_rate.blogid = blog.blogid AND blog_rate.userid = " . $this->registry->userinfo['userid'] . ")" : '') . "
					WHERE blog.blogid IN (" . implode(',', $blogids) . ")
					ORDER BY blog.dateline DESC
					LIMIT 5
				");
				while ($blog = $this->registry->db->fetch_array($entries))
				{
					if ($first)
					{
						$show['latestentry'] = true;
						$first = false;
					}
					else
					{
						$show['latestentry'] = false;
					}

					$entry_handler =& $factory->create($blog, '_Profile');
					$entry_handler->cachable = false;
					$entry_handler->excerpt = true;
					$this->block_data['latestentries'] .= $entry_handler->construct();
				}

				// Comments
				$state = array('visible');
				$commentstate = array('visible');
				$sql_and = array();

				$sql_and[] = "blog.state IN('" . implode("', '", $state) . "')";
				$sql_and[] = "blog.dateline <= " . TIMENOW;
				$sql_and[] = "blog.pending = 0";
				$sql_and[] = "blog_text.state IN('" . implode("', '", $commentstate) . "')";
				$sql_and[] = "blog.firstblogtextid <> blog_text.blogtextid";
				$sql_and[] = "blog_text.bloguserid = " . $this->profile->userinfo['userid'];

				$this->registry->options['vbblog_snippet'] = 20;
				require_once(DIR . '/includes/class_blog_response.php');
				$bbcode =& new vB_BbCodeParser_Blog_Snippet_Featured($this->registry, fetch_tag_list());
				$factory =& new vB_Blog_ResponseFactory($this->registry, $bbcode, $bloginfo);

				$comments = $this->registry->db->query_read_slave("
					SELECT
						blog_text.username AS postusername, blog_text.ipaddress AS blogipaddress, blog_text.state, blog.blogid, blog_text.blogtextid, blog_text.title, blog.title AS entrytitle, blog_text.dateline, blog_text.pagetext, blog_text.allowsmilie, user.*,
						blog_user.title AS blogtitle,
						IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid, options_ignore, options_buddy, options_everyone, blog.userid AS blog_userid,
						blog.state AS blog_state, blog.firstblogtextid
					" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
					" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? ", blog_read.readtime AS blogread, blog_userread.readtime AS bloguserread" : "") . "
					FROM " . TABLE_PREFIX . "blog_text AS blog_text
					LEFT JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = blog_text.blogid)
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = blog_text.userid)
					LEFT JOIN " . TABLE_PREFIX . "blog_user AS blog_user ON (blog_user.bloguserid = blog.userid)
					" . (($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']) ? "
					LEFT JOIN " . TABLE_PREFIX . "blog_read AS blog_read ON (blog_read.blogid = blog.blogid AND blog_read.userid = " . $vbulletin->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "blog_userread AS blog_userread ON (blog_userread.bloguserid = blog.userid AND blog_userread.userid = " . $vbulletin->userinfo['userid'] . ")
					" : "") . "
					" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
					WHERE " . implode("\r\n\tAND ", $sql_and) . "
					ORDER BY blog_text.dateline DESC
					LIMIT 5
				");
				while ($comment = $this->registry->db->fetch_array($comments))
				{
					$bloginfo = array(
						'userid'          => $comment['blog_userid'],
						'state'           => $comment['blog_state'],
						'firstblogtextid' => $comment['firstblogtextid'],
						'blogread'        => $comment['blogread'],
						'bloguserread'    => $comment['bloguserread'],
					);
					$response_handler->bloginfo =& $bloginfo;

					$response_handler =& $factory->create($comment, 'Comment_Profile');
					$response_handler->cachable = false;
					$response_handler->linkblog = true;
					$this->block_data['commentsreceived'] .= $response_handler->construct();
				}
			}
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile$ - $Revision: 17893 $
|| ####################################################################
\*======================================================================*/
?>