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

/**
* Blog response factory.
*
* @package 		vBulletin Blog
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Blog_ResponseFactory
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* BB code parser object (if necessary)
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Information about the blog this response belongs to
	*
	* @var	array
	*/
	var $bloginfo = array();

	/**
	* Permission cache for various users.
	*
	* @var	array
	*/
	var $perm_cache = array();

	/**
	* Excerpt doesn't match full post
	*
	* @var boolean
	*/
	var $readmore = false;

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	array			Blog info
	*/
	function vB_Blog_ResponseFactory(&$registry, &$bbcode, &$bloginfo)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Database::Registry object is not an object", E_USER_ERROR);
		}

		$this->bbcode =& $bbcode;
		$this->bloginfo =& $bloginfo;

	}

	/**
	* Create an blog response object for the specified response
	*
	* @param	array	Response information
	*
	* @return	vB_Blog_Response
	*/
	function &create($response, $type = 'Comment')
	{
		$class_name = 'vB_Blog_Response_';
		switch ($response['state'])
		{
			case 'deleted':
				$class_name .= 'Deleted';
				break;

			case 'moderation':
			case 'visible':
			default:
				if ($response['blogtrackbackid'])
				{
					$class_name .= 'Trackback';
				}
				else
				{
					$class_name .= $type;
				}
		}

		/* Needs hooks */

		if (class_exists($class_name))
		{
			return new $class_name($this->registry, $this, $this->bbcode, $this->bloginfo, $response);
		}
		else
		{
			trigger_error('vB_Blog_ResponseFactory::create(): Invalid type.', E_USER_ERROR);
		}
	}
}

/**
* Generic blog response class.
*
* @package 		vBulletin Blog
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Blog_Response
{
	/**
	* Registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Factory object that created this object. Used for permission caching.
	*
	* @var	vB_Blog_ResponseFactory
	*/
	var $factory = null;

	/**
	* BB code parser object (if necessary)
	*
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Cached information from the BB code parser
	*
	* @var	array
	*/
	var $parsed_cache = array();

	/**
	* Information about the blog this response belongs to
	*
	* @var	array
	*/
	var $bloginfo = array();

	/**
	* Information about this response
	*
	* @var	array
	*/
	var $response = array();

	/**
	* Variable which identifies if the data should be cached
	*
	* @var	boolean
	*/
	var $cachable = true;

	/**
	* Comment template needs linking back to its owner since it is being used outside of a specific post
	*
	* @var	boolean
	*/
	var $linkblog = false;

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = '';

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	vB_Blog_ResponseFactory
	* @param	array			Blog info
	* @param	array			Response info
	*/
	function vB_Blog_Response(&$registry, &$factory, &$bbcode, $bloginfo, $response)
	{
		if (!is_subclass_of($this, 'vB_Blog_Response'))
		{
			trigger_error('Direct instantiation of vB_Blog_Response class prohibited. Use the vB_Blog_ResponseFactory class.', E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Database::Registry object is not an object", E_USER_ERROR);
		}

		$this->registry =& $registry;
		$this->factory =& $factory;
		$this->bbcode =& $bbcode;

		$this->bloginfo = $bloginfo;
		$this->response = $response;
	}

	/**
	* Template method that does all the work to display an issue note, including processing the template
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		// preparation for display...
		$this->prepare_start();

		if ($this->response['userid'])
		{
			$this->process_registered_user();
		}
		else
		{
			$this->process_unregistered_user();
		}

		$this->process_date_status();
		$this->process_display();
		$this->process_text();
		$this->prepare_end();

		// actual display...
		$bloginfo =& $this->bloginfo;
		$response =& $this->response;

		global $show, $vbphrase, $stylevar;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		$show['readmore'] = $this->readmore;

		eval('$output = "' . fetch_template($this->template) . '";');

		//$output = print_r($response, true);

		return $output;
	}

	/**
	* Any startup work that needs to be done to a note.
	*/
	function prepare_start()
	{
		$this->response = array_merge($this->response, convert_bits_to_array($this->response['options'], $this->registry->bf_misc_useroptions));
		$this->response = array_merge($this->response, convert_bits_to_array($this->response['adminoptions'], $this->registry->bf_misc_adminoptions));
	}

	/**
	* Process note as if a registered user posted
	*/
	function process_registered_user()
	{
		global $show, $vbphrase;

		fetch_musername($this->response);

		$this->response['onlinestatus'] = 0;
		// now decide if we can see the user or not
		if ($this->response['lastactivity'] > (TIMENOW - $this->registry->options['cookietimeout']) AND $this->response['lastvisit'] != $this->response['lastactivity'])
		{
			if ($this->response['invisible'])
			{
				if (($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden']) OR $this->response['userid'] == $this->registry->userinfo['userid'])
				{
					// user is online and invisible BUT bbuser can see them
					$this->response['onlinestatus'] = 2;
				}
			}
			else
			{
				// user is online and visible
				$this->response['onlinestatus'] = 1;
			}
		}

		if (!isset($this->factory->perm_cache["{$this->response['userid']}"]))
		{
			$this->factory->perm_cache["{$this->response['userid']}"] = cache_permissions($this->response, false);
		}

		// get avatar
		if ($this->response['avatarid'])
		{
			$this->response['avatarurl'] = $this->response['avatarpath'];
		}
		else
		{
			if ($this->response['hascustomavatar'] AND $this->registry->options['avatarenabled'])
			{
				if ($this->registry->options['usefileavatar'])
				{
					$this->response['avatarurl'] = $this->registry->options['avatarurl'] . '/avatar' . $this->response['userid'] . '_' . $this->response['avatarrevision'] . '.gif';
				}
				else
				{
					$this->response['avatarurl'] = 'image.php?' . $this->registry->session->vars['sessionurl'] . 'u=' . $this->response['userid'] . '&amp;dateline=' . $this->response['avatardateline'];
				}
				if ($this->response['avwidth'] AND $this->response['avheight'])
				{
					$this->response['avwidth'] = 'width="' . $this->response['avwidth'] . '"';
					$this->response['avheight'] = 'height="' . $this->response['avheight'] . '"';
				}
				else
				{
					$this->response['avwidth'] = '';
					$this->response['avheight'] = '';
				}
			}
			else
			{
				$this->response['avatarurl'] = '';
			}
		}

		if ( // no avatar defined for this user
			empty($this->response['avatarurl'])
			OR // visitor doesn't want to see avatars
			($this->registry->userinfo['userid'] > 0 AND !$this->registry->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$this->response['avatarid'] AND !($this->factory->perm_cache["{$this->response['userid']}"]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$this->response['adminavatar']) //
		)
		{
			$show['avatar'] = false;
		}
		else
		{
			$show['avatar'] = true;
		}

		$show['emaillink'] = (
			$this->response['showemail'] AND $this->registry->options['displayemails'] AND (
				!$this->registry->options['secureemail'] OR (
					$this->registry->options['secureemail'] AND $this->registry->options['enableemail']
				)
			) AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']
		);
		$show['homepage'] = ($this->response['homepage'] != '' AND $this->response['homepage'] != 'http://');
		$show['pmlink'] = ($this->registry->options['enablepms'] AND $this->registry->userinfo['permissions']['pmquota'] AND ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
	 					OR ($this->response['receivepm'] AND $this->factory->perm_cache["{$this->bloginfo['userid']}"]['pmquota'])
	 				)) ? true : false;
	}

	/**
	* Process note as if an unregistered user posted
	*/
	function process_unregistered_user()
	{
		global $show;

		$this->response['rank'] = '';
		$this->response['notesperday'] = 0;
		$this->response['displaygroupid'] = 1;
		$this->response['username'] = $this->response['postusername'];
		fetch_musername($this->response);
		//$this->response['usertitle'] = $vbphrase['guest'];
		$this->response['usertitle'] =& $this->registry->usergroupcache["0"]['usertitle'];
		$this->response['joindate'] = '';
		$this->response['notes'] = 'n/a';
		$this->response['avatar'] = '';
		$this->response['profile'] = '';
		$this->response['email'] = '';
		$this->response['useremail'] = '';
		$this->response['icqicon'] = '';
		$this->response['aimicon'] = '';
		$this->response['yahooicon'] = '';
		$this->response['msnicon'] = '';
		$this->response['skypeicon'] = '';
		$this->response['homepage'] = '';
		$this->response['findnotes'] = '';
		$this->response['signature'] = '';
		$this->response['reputationdisplay'] = '';
		$this->response['onlinestatus'] = '';

		$show['avatar'] = false;
	}

	/**
	* Prepare the text for display
	*/
	function process_text()
	{
		$this->bbcode->set_parse_userinfo($this->response, $this->factory->perm_cache["{$this->response['userid']}"]);
		$this->response['message'] = $this->bbcode->parse(
			$this->response['pagetext'],
			($this->bloginfo['firstblogtextid'] == $this->response['blogtextid']) ? 'blog_entry' : 'blog_comment',
			$this->response['allowsmilie'], // fix
			false,
			$this->response['pagetexthtml'], // fix
			$this->response['hasimages'], // fix
			$this->cachable
		);
		$this->parsed_cache =& $this->bbcode->cached;
		$this->readmore = ($this->bbcode->createdsnippet);
	}

	/**
	* Any closing work to be done.
	*/
	function prepare_end()
	{
		global $show;

		global $onload, $blogtextid;

		if (can_moderate_blog('canviewips'))
		{
			$this->response['blogipaddress'] = ($this->response['blogipaddress'] ? htmlspecialchars_uni(long2ip($this->response['blogipaddress'])) : '');
		}
		else
		{
			$this->response['blogipaddress'] = '';
		}

		if ($blogtextid AND $this->response['blogtextid'] == $blogtextid)
		{
			$this->response['scrolltothis'] = ' id="currentPost"';
			$onload = " onload=\"if (is_ie || is_moz) { fetch_object('currentPost').scrollIntoView(true); }\"";
		}
		else
		{
			$this->response['scrolltothis'] = '';
		}

		$show['linkblog'] = ($this->linkblog);
		$show['reportlink'] = (
			$this->registry->userinfo['userid']
			AND ($this->registry->options['rpforumid'] OR
				($this->registry->options['enableemail'] AND $this->registry->options['rpemail']))
		);
	}

	function process_date_status()
	{
		global $vbphrase;

		if (!empty($this->bloginfo))
		{
			if (isset($this->bloginfo['blogview']))
			{
				$lastvisit = $this->bloginfo['blogview'];
			}
			else if ($this->registry->userinfo['userid'] AND $vbulletin->options['threadmarking'])
			{
				$blogview = max($this->bloginfo['blogread'], $this->bloginfo['bloguserread'], TIMENOW - ($this->registry->options['markinglimit'] * 86400));
				$lastvisit = $this->bloginfo['blogview'] = intval($blogview);
			}
			else
			{
				$blogview = max(fetch_bbarray_cookie('blog_lastview', $this->bloginfo['blogid']), fetch_bbarray_cookie('blog_userread', $this->bloginfo['userid']), $this->registry->userinfo['lastvisit']);
				$lastvisit = intval($blogview);
			}
		}
		else
		{
			$lastvisit = $this->registry->userinfo['lastvisit'];
		}

		if ($this->response['dateline'] > $lastvisit)
		{
			$this->response['statusicon'] = 'new';
			$this->response['statustitle'] = $vbphrase['unread_date'];
		}
		else
		{
			$this->response['statusicon'] = 'old';
			$this->response['statustitle'] = $vbphrase['old'];
		}

		$this->response['date'] = vbdate($this->registry->options['dateformat'], $this->response['dateline'], true);
		$this->response['time'] = vbdate($this->registry->options['timeformat'], $this->response['dateline']);
	}

	function process_display()
	{
		global $show;

		$show['moderation'] = ($this->response['state'] == 'moderation');
		$show['private'] = false;
		if (can_moderate() AND $this->response['blog_userid'] != $this->registry->userinfo['userid'])
		{
			$everyoneelsecanview = $this->response['options_everyone'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $this->response['options_buddy'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];

			if (!$everyoneelsecanview AND (!$this->response['buddyid'] OR !$buddiescanview))
			{
				$show['private'] = true;
			}
		}

		$show['edit'] = fetch_comment_perm('caneditcomments', $this->bloginfo, $this->response);
		$show['approve'] = (fetch_comment_perm('candeletecomments', $this->bloginfo, $this->response) AND fetch_comment_perm('canmoderatecomments', $this->bloginfo, $this->response));
		$show['delete'] = fetch_comment_perm('candeletecomments', $this->bloginfo, $this->response);
		$show['undelete'] = fetch_comment_perm('candeletecomments', $this->bloginfo, $this->response);

		if ($show['delete'] OR $show['approve'] OR $show['undelete'])
		{
			$this->inlinemod = true;
			$show['inlinemod'] = true;
		}
		else
		{
			$show['inlinemod'] = false;
		}

		if ($this->response['edit_userid'])
		{
			$this->response['edit_date'] = vbdate($this->registry->options['dateformat'], $this->response['edit_dateline'], true);
			$this->response['edit_time'] = vbdate($this->registry->options['timeformat'], $this->response['edit_dateline']);
			if ($this->response['edit_reason'])
			{
				$this->response['edit_reason'] = fetch_word_wrapped_string($this->response['edit_reason']);
			}
			$show['commentedited'] = true;
		}
		else
		{
			$show['commentedited'] = false;
		}

	}
}

class vB_Blog_Response_Deleted extends vB_Blog_Response
{
	var $template = 'blog_comment_deleted';
}

class vB_Blog_Response_Comment extends vB_Blog_Response
{
	var $template = 'blog_comment';
}

class vB_Blog_Response_Comment_Profile extends vB_Blog_Response
{
	var $template = 'blog_comment_profile';
}

class vB_Blog_Response_Trackback extends vB_Blog_Response
{
	var $template = 'blog_trackback';

	var $inlinemod = false;

	function process_registered_user() {}
	function process_unregistered_user() {}

	function process_display()
	{
		global $show;

		parent::process_display();

		if ($this->response['state'] == 'moderation')
		{
			$show['edit_trackback'] = ((can_moderate_blog('caneditcomments') AND can_moderate_blog('canmoderatecomments')) OR ($this->bloginfo['state'] == 'visible' AND $this->registry->userinfo['userid'] == $this->response['userid'] AND $this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
			$show['approve_trackback'] = (can_moderate_blog('canmoderatecomments') OR ($this->bloginfo['state'] == 'visible' AND $this->registry->userinfo['userid'] == $this->response['userid'] AND $this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
			$show['delete_trackback'] = ((can_moderate_blog('canmoderatecomments') AND can_moderate_blog('candeletecomments')) OR ($this->bloginfo['state'] == 'visible' AND $this->registry->userinfo['userid'] == $this->response['userid'] AND $this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
		}
		else
		{
			$show['edit_trackback'] = (can_moderate_blog('caneditcomments') OR ($this->bloginfo['state'] == 'visible' AND $this->registry->userinfo['userid'] == $this->response['userid'] AND $this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
			$show['approve_trackback'] = (can_moderate_blog('canmoderatecomments') OR ($this->bloginfo['state'] == 'visible' AND $this->registry->userinfo['userid'] == $this->response['userid'] AND $this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
			$show['delete_trackback'] = (can_moderate_blog('candeletecomments') OR ($this->bloginfo['state'] == 'visible' AND $this->registry->userinfo['userid'] == $this->response['userid'] AND $this->registry->userinfo['permissions']['vbblog_general_permissions'] & $this->registry->bf_ugp_vbblog_general_permissions['blog_canmanageblogcomments']));
		}
		if ($show['delete_trackback'] OR $show['approve_trackback'])
		{
			$this->inlinemod = true;
			$show['inlinemod_trackback'] = true;
		}
		else
		{
			$show['inlinemod_trackback'] = false;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25019 $
|| ####################################################################
\*======================================================================*/
?>