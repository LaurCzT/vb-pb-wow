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
* Blog entry factory.
*
* @package 		vBulletin Blog
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Blog_EntryFactory
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
	var $categories = array();

	/**
	* True if inline moderation checkbox has been displayed
	*
	* @var	boolean
	*/
	var $inlinemod = false;

	/**
	* True if an entry can be deleted
	*
	* @var	boolean
	*/
	var $delete = false;

	/**
	* True if an entry can be undeleted
	*
	* @var	boolean
	*/
	var $undelete = false;

	/**
	* Permission cache for various users.
	*
	* @var	array
	*/
	var $perm_cache = array();

	/**
	* Array holding some conditional values for status codes
	*
	* @var	array
	*/
	var $status = array();

	/**
	* Constructor, sets up the object.
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	array			Blog info
	*/
	function vB_Blog_EntryFactory(&$registry, &$bbcode, &$categories)
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
		$this->categories =& $categories;
	}

	/**
	* Create an blog response object for the specified response
	*
	* @param	array	 Response information
	8 @param string Override auto detection
	*
	* @return	vB_Blog_Response
	*/
	function &create($entry, $type = '')
	{
		$class_name = 'vB_Blog_Entry';

		if ($type == 'external')
		{
			$class_name .= '_External';
		}
		else
		{
			switch ($entry['state'])
			{
				case 'deleted':
					$class_name .= '_Deleted';
					break;

				case 'moderation':
				case 'visible':
				default:
					if ($type)
					{
						$class_name .= $type;
					}
					break;
			}
		}

		/* Needs hooks */

		if (class_exists($class_name))
		{
			return new $class_name($this->registry, $this, $this->bbcode, $this->categories, $entry);
		}
		else
		{
			trigger_error('vB_Blog_EntryFactory::create(): Invalid type (' . htmlspecialchars($class_name) . ')', E_USER_ERROR);
		}
	}
}

/**
* Generic blog entry class.
*
* @package 		vBulletin Blog
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Blog_Entry
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
	* @var	vB_Blog_EntryFactory
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
	* Information about the possible categories we need
	*
	* @var	array
	*/
	var $categories = array();

	/**
	* Information about the blog this entry belongs to
	*
	* @var	array
	*/
	var $blog = array();

	/**
	* Variable which identifies if the data should be cached
	*
	* @var	boolean
	*/
	var $cachable = true;

	/**
	* The template that will be used for outputting
	*
	* @var	string
	*/
	var $template = 'blog_entry_with_userinfo';

	/**
	* The array of attachment information
	*
	* @var	array
	*/
	var $attachments = array();

	/**
	*	Return an excerpt of the entry
	*
	* @var boolean
	*/
	var $excerpt = false;

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
	* @param	vB_Blog_EntryFactory
	* @param	array			Blog info
	*/
	function vB_Blog_Entry(&$registry, &$factory, &$bbcode, &$categories, $blog)
	{
		if (!is_subclass_of($this, 'vB_Blog_Entry'))
		{
			//trigger_error('Direct instantiation of vB_Blog_Entry class prohibited. Use the vB_Blog_EntryFactory class.', E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Blog_Entry::Registry object is not an object", E_USER_ERROR);
		}

		$this->registry =& $registry;
		$this->factory =& $factory;
		$this->bbcode =& $bbcode;
		$this->categories =& $categories;

		$this->blog = $blog;
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

		if ($this->blog['userid'])
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
		// we're showing a read more so no attachments
		if (!$this->readmore)
		{
			$this->process_attachments();
		}
		$this->prepare_end();

		// actual display...
		$blog =& $this->blog;
		$status =& $this->status;

		global $show, $vbphrase, $stylevar;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		$show['readmore'] = $this->readmore;

		eval('$output = "' . fetch_template($this->template) . '";');

		return $output;
	}

	/**
	* Any startup work that needs to be done to a note.
	*/
	function prepare_start()
	{
		$this->blog = array_merge($this->blog, convert_bits_to_array($this->blog['options'], $this->registry->bf_misc_useroptions));
		$this->blog = array_merge($this->blog, convert_bits_to_array($this->blog['adminoptions'], $this->registry->bf_misc_adminoptions));
	}

	/**
	* Process note as if a registered user posted
	*/
	function process_registered_user()
	{
		global $show, $vbphrase;

		fetch_musername($this->blog);

		$this->blog['onlinestatus'] = 0;
		// now decide if we can see the user or not
		if ($this->blog['lastactivity'] > (TIMENOW - $this->registry->options['cookietimeout']) AND $this->blog['lastvisit'] != $this->blog['lastactivity'])
		{
			if ($this->blog['invisible'])
			{
				if (($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden']) OR $this->blog['userid'] == $this->registry->userinfo['userid'])
				{
					// user is online and invisible BUT bbuser can see them
					$this->blog['onlinestatus'] = 2;
				}
			}
			else
			{
				// user is online and visible
				$this->blog['onlinestatus'] = 1;
			}
		}

		if (!isset($this->factory->perm_cache["{$this->blog['userid']}"]))
		{
			$this->factory->perm_cache["{$this->blog['userid']}"] = cache_permissions($this->blog, false);
		}

		// get avatar
		if ($this->blog['avatarid'])
		{
			$this->blog['avatarurl'] = $this->blog['avatarpath'];
		}
		else
		{
			if ($this->blog['hascustomavatar'] AND $this->registry->options['avatarenabled'])
			{
				if ($this->registry->options['usefileavatar'])
				{
					$this->blog['avatarurl'] = $this->registry->options['avatarurl'] . '/avatar' . $this->blog['userid'] . '_' . $this->blog['avatarrevision'] . '.gif';
				}
				else
				{
					$this->blog['avatarurl'] = 'image.php?' . $this->registry->session->vars['sessionurl'] . 'u=' . $this->blog['userid'] . '&amp;dateline=' . $this->blog['avatardateline'];
				}

				$this->blog['avwidthpx'] = intval($this->blog['avwidth']);
				$this->blog['avheightpx'] = intval($this->blog['avheight']);

				if ($this->blog['avwidth'] AND $this->blog['avheight'])
				{
					$this->blog['avwidth'] = 'width="' . $this->blog['avwidth'] . '"';
					$this->blog['avheight'] = 'height="' . $this->blog['avheight'] . '"';
				}
				else
				{
					$this->blog['avwidth'] = '';
					$this->blog['avheight'] = '';
				}
			}
			else
			{
				$this->blog['avatarurl'] = '';
			}
		}

		if ( // no avatar defined for this user
			empty($this->blog['avatarurl'])
			OR // visitor doesn't want to see avatars
			($this->registry->userinfo['userid'] > 0 AND !$this->registry->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$this->blog['avatarid'] AND !($this->factory->perm_cache["{$this->blog['userid']}"]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$this->blog['adminavatar']) //
		)
		{
			$show['avatar'] = false;
		}
		else
		{
			$show['avatar'] = true;
		}

		// PROFILE PIC
		$show['profilepic'] = ($this->registry->options['profilepicenabled'] AND $this->blog['profilepic'] AND ($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseeprofilepic'] OR $this->registry->userinfo['userid'] == $this->blog['userid']) AND ($this->factory->perm_cache["{$this->blog['userid']}"]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canprofilepic'] OR $this->blog['adminprofilepic'])) ? true : false;

		if ($this->registry->options['usefileavatar'])
		{
			$this->blog['profilepicurl'] = $this->registry->options['profilepicurl'] . '/profilepic' . $this->blog['userid'] . '_' . $this->blog['profilepicrevision'] . '.gif';
		}
		else
		{
			$this->blog['profilepicurl'] = 'image.php?' . $this->registry->session->vars['sessionurl'] . 'u=' . $this->blog['userid'] . "&amp;dateline=" . $this->blog['profilepicdateline'] . "&amp;type=profile";
		}

		$this->blog['ppwidthpx'] = intval($this->blog['ppwidth']);
		$this->blog['ppheightpx'] = intval($this->blog['ppheight']);

		if ($this->blog['ppwidthpx'] AND $this->blog['ppheightpx'])
		{
			$this->blog['ppwidth'] = 'width="' . $this->blog['ppwidthpx'] . '"';
			$this->blog['ppheight'] = 'height="' . $this->blog['ppheightpx'] . '"';
		}

		$show['subscribelink'] = ($this->blog['userid'] != $this->registry->userinfo['userid'] AND $this->registry->userinfo['userid']);
		$show['blogsubscribed'] = $this->blog['blogsubscribed'];
		$show['entrysubscribed'] = $this->blog['entrysubscribed'];

		$show['emaillink'] = (
			$this->blog['showemail'] AND $this->registry->options['displayemails'] AND (
				!$this->registry->options['secureemail'] OR (
					$this->registry->options['secureemail'] AND $this->registry->options['enableemail']
				)
			) AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']
		);
		$show['homepage'] = ($this->blog['homepage'] != '' AND $this->blog['homepage'] != 'http://');
		$show['pmlink'] = ($this->registry->options['enablepms'] AND $this->registry->userinfo['permissions']['pmquota'] AND ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
	 					OR ($this->blog['receivepm'] AND $this->factory->perm_cache["{$this->blog['userid']}"]['pmquota'])
	 				)) ? true : false;

	}

	/**
	* Process note as if an unregistered user posted
	*/
	function process_unregistered_user()
	{
		global $show;

		$show['subscribelink'] = false;

		$this->blog['rank'] = '';
		$this->blog['notesperday'] = 0;
		$this->blog['displaygroupid'] = 1;
		fetch_musername($this->blog);
		//$this->blog['usertitle'] = $vbphrase['guest'];
		$this->blog['usertitle'] =& $this->registry->usergroupcache["0"]['usertitle'];
		$this->blog['joindate'] = '';
		$this->blog['notes'] = 'n/a';
		$this->blog['avatar'] = '';
		$this->blog['profile'] = '';
		$this->blog['email'] = '';
		$this->blog['useremail'] = '';
		$this->blog['icqicon'] = '';
		$this->blog['aimicon'] = '';
		$this->blog['yahooicon'] = '';
		$this->blog['msnicon'] = '';
		$this->blog['skypeicon'] = '';
		$this->blog['homepage'] = '';
		$this->blog['findnotes'] = '';
		$this->blog['signature'] = '';

		$this->blog['reputationdisplay'] = '';
		$this->blog['onlinestatus'] = '';
	}

	/**
	* Prepare the text for display
	*/
	function process_text()
	{
		global $vbphrase;

		$this->bbcode->attachments =& $this->attachments;
		$this->bbcode->unsetattach = true;
		$this->bbcode->set_parse_userinfo($this->blog, $this->factory->perm_cache["{$this->blog['userid']}"]);
		$this->blog['message'] = $this->bbcode->parse(
			$this->blog['pagetext'],
			'blog_entry',
			$this->blog['allowsmilie'],
			false,
			$this->blog['pagetexthtml'], // fix
			$this->blog['hasimages'], // fix
			$this->cachable
		);
		if ($this->bbcode->createdsnippet !== true)
		{
			$this->parsed_cache =& $this->bbcode->cached;
		}
		$this->readmore = ($this->bbcode->createdsnippet);
	}

	/**
	* Processes any attachments to this entry.
	*/
	function process_attachments()
	{
		global $stylevar, $show, $vbphrase;

		if (!empty($this->attachments))
		{
			$show['attachments'] = true;
			$show['imageattachment'] = $show['imageattachmentlink'] = $show['thumbnailattachment'] = false;

			$attachcount = sizeof($this->attachments);
			$thumbcount = 0;

			if (!$this->registry->options['attachthumbs'] AND !$this->registry->options['viewattachedimages'])
			{
				$showimagesprev = $this->registry->userinfo['showimages'];
				$this->registry->userinfo['showimages'] = false;
			}

			foreach ($this->attachments AS $attachmentid => $attachment)
			{
				if ($attachment['thumbnail_filesize'] == $attachment['filesize'])
				{
					// This is an image that is already thumbnail sized..
					$attachment['hasthumbnail'] = 0;
					$attachment['forceimage'] = 1;
				}

				$show['newwindow'] = $attachment['newwindow'];

				$attachment['filename'] = fetch_censored_text(htmlspecialchars_uni($attachment['filename']));
				$attachment['attachmentextension'] = strtolower(file_extension($attachment['filename']));
				$attachment['filesize'] = vb_number_format($attachment['filesize'], 1, true);

				if ($attachment['visible'] == 'visible')
				{
					if (THIS_SCRIPT == 'blogexternal')
					{
						$attachment['counter'] = $vbphrase['n_a'];
						$show['views'] = false;
					}
					else
					{
						$show['views'] = true;
					}
					switch($attachment['attachmentextension'])
					{
						case 'gif':
						case 'jpg':
						case 'jpeg':
						case 'jpe':
						case 'png':
						case 'bmp':
						case 'tiff':
						case 'tif':
						case 'psd':
						case 'pdf':
							if (!$this->registry->userinfo['showimages'])
							{
								eval('$this->blog[\'imageattachmentlinks\'] .= "' . fetch_template('blog_entry_attachment') . '";');
								$show['imageattachmentlink'] = true;
							}
							else if ($this->registry->options['attachthumbs'])
							{
								if ($attachment['hasthumbnail'])
								{
									$thumbcount++;
									if ($this->registry->options['attachrow'] AND $thumbcount >= $this->registry->options['attachrow'])
									{
										$thumbcount = 0;
										$show['br'] = true;
									}
									else
									{
										$show['br'] = false;
									}
									eval('$this->blog[\'thumbnailattachments\'] .= "' . fetch_template('blog_entry_attachment_thumbnail') . '";');
									$show['thumbnailattachment'] = true;
								}
								else if (!in_array($attachment['attachmentextension'], array('tiff', 'tif', 'psd', 'pdf')) AND $attachment['forceimage'])
								{
									eval('$this->blog[\'imageattachments\'] .= "' . fetch_template('blog_entry_attachment_image') . '";');
									$show['imageattachment'] = true;
								}
								else
								{
									// Special case for PDF - don't list it as an 'image'
									if ($attachment['attachmentextension'] == 'pdf')
									{
										eval('$this->blog[\'otherattachments\'] .= "' . fetch_template('blog_entry_attachment') . '";');
										$show['otherattachment'] = true;
									}
									else
									{
										eval('$this->blog[\'imageattachmentlinks\'] .= "' . fetch_template('blog_entry_attachment') . '";');
										$show['imageattachmentlink'] = true;
									}
								}
							}
							else if (!in_array($attachment['attachmentextension'], array('tiff', 'tif', 'psd', 'pdf')) AND ($this->registry->options['viewattachedimages'] == 1 OR ($this->registry->options['viewattachedimages'] == 2 AND $attachcount == 1)))
							{
								eval('$this->blog[\'imageattachments\'] .= "' . fetch_template('blog_entry_attachment_image') . '";');
								$show['imageattachment'] = true;
							}
							else
							{
								eval('$this->blog[\'imageattachmentlinks\'] .= "' . fetch_template('blog_entry_attachment') . '";');
								$show['imageattachmentlink'] = true;
							}
							break;
						default:
							eval('$this->blog[\'otherattachments\'] .= "' . fetch_template('blog_entry_attachment') . '";');
							$show['otherattachment'] = true;
					}
				}
			}
			if (!$this->registry->options['attachthumbs'] AND !$this->registry->options['viewattachedimages'])
			{
				$this->registry->userinfo['showimages'] = $showimagesprev;
			}
		}
		else
		{
			$show['attachments'] = false;
		}
	}

	/**
	* Any closing work to be done.
	*/
	function prepare_end()
	{
		global $show;

		if (can_moderate_blog('canviewips'))
		{
			$this->blog['blogipaddress'] = ($this->blog['blogipaddress'] ? htmlspecialchars_uni(long2ip($this->blog['blogipaddress'])) : '');
		}
		else
		{
			$this->blog['blogipaddress'] = '';
		}
		$show['reportlink'] = (
			$this->blog['state'] != 'draft'
			AND
			!$this->blog['pending']
			AND
			$this->registry->userinfo['userid']
			AND ($this->registry->options['rpforumid'] OR
				($this->registry->options['enableemail'] AND $this->registry->options['rpemail']))
		);
	}

	function process_date_status()
	{
		global $vbphrase;

		if (!empty($this->blog))
		{
			if ($this->registry->userinfo['userid'] AND $vbulletin->options['threadmarking'])
			{
				$blogview = max($this->blog['blogread'], $this->blog['bloguserread'], TIMENOW - ($this->registry->options['markinglimit'] * 86400));
				$lastvisit = intval($blogview);
			}
			else
			{
				$blogview = max(fetch_bbarray_cookie('blog_lastview', $this->blog['blogid']), fetch_bbarray_cookie('blog_userread', $this->blog['userid']), $this->registry->userinfo['lastvisit']);
				$lastvisit = intval($blogview);
			}
		}
		else
		{
			$lastvisit = $this->registry->userinfo['lastvisit'];
		}

		if ($this->blog['dateline'] > $lastvisit)
		{
			$this->blog['statusicon'] = 'new';
			$this->blog['statustitle'] = $vbphrase['unread_date'];
		}
		else
		{
			$this->blog['statusicon'] = 'old';
			$this->blog['statustitle'] = $vbphrase['old'];
		}

		$this->blog['date'] = vbdate($this->registry->options['dateformat'], $this->blog['dateline'], true);
		$this->blog['time'] = vbdate($this->registry->options['timeformat'], $this->blog['dateline']);
	}

	function process_display()
	{
		global $show, $vbphrase, $stylevar;
		static $delete, $approve;

		$blog =& $this->blog;

		if ($this->blog['ratingnum'] >= $this->registry->options['vbblog_ratingpost'] AND $this->blog['ratingnum'])
		{
			$this->blog['ratingavg'] = vb_number_format($this->blog['ratingtotal'] / $this->blog['ratingnum'], 2);
			$this->blog['rating'] = intval(round($this->blog['ratingtotal'] / $this->blog['ratingnum']));
			$show['rating'] = true;
		}
		else
		{
			$show['rating'] = false;
		}

		if (!$this->blog['blogtitle'])
		{
			$this->blog['blogtitle'] = $this->blog['username'];
		}

		$categorybits = array();
		if (!empty($this->categories["{$this->blog[blogid]}"]))
		{
			foreach ($this->categories["{$this->blog[blogid]}"] AS $index => $category)
			{
				eval('$categorybits[] = "' . fetch_template('blog_entry_category') . '";');
			}
		}
		else
		{
			$category = array(
				'blogcategoryid'   => -1,
				'title'            => $vbphrase['uncategorized'],
				'userid'           => $this->blog['userid'],
			);
			eval('$categorybits[] = "' . fetch_template('blog_entry_category') . '";');
		}

		$show['category'] = true;
		$this->blog['categorybits'] = implode(', ', $categorybits);

		$show['trackback_moderation'] = ($this->blog['trackback_moderation'] AND ($this->blog['userid'] == $this->registry->userinfo['userid'] OR can_moderate_blog('canmoderatecomments'))) ? true : false;
		$show['comment_moderation'] = ($this->blog['hidden'] AND ($this->blog['userid'] == $this->registry->userinfo['userid'] OR can_moderate_blog('canmoderatecomments'))) ? true : false;

		$canremove = ($this->registry->userinfo['userid'] == $this->blog['userid'] AND $this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_canremoveentry']);
		$canundelete = $candelete = (
			$this->registry->userinfo['userid'] == $this->blog['userid']
				AND
			$this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_candeleteentry']
				AND
			(
				$blog['state'] != 'deleted'
					OR
				$blog['del_userid'] == $this->registry->userinfo['userid']
			)
		);

		$canedit = (
			$this->registry->userinfo['userid'] == $this->blog['userid']
				AND
			$this->registry->userinfo['permissions']['vbblog_entry_permissions'] & $this->registry->bf_ugp_vbblog_entry_permissions['blog_caneditentry']
				AND
			(
				$blog['state'] != 'deleted'
					OR
				$blog['del_userid'] == $this->registry->userinfo['userid']
			)
		);

		if ($blog['state'] == 'deleted')
		{
			$show['edit'] = (($canedit OR can_moderate_blog('caneditentries')) AND (can_moderate_blog('candeleteentries') OR $candelete));
			$show['delete'] = ((can_moderate_blog('candeleteentries') AND can_moderate_blog('canremoveentries')) OR ($candelete AND $canremove));
			$show['undelete'] = (can_moderate_blog('candeleteentries') OR $canundelete);
			$show['approve'] = (can_moderate_blog('candeleteentries') AND can_moderate_blog('canmoderateentries'));
		}
		else if ($blog['state'] == 'moderation')
		{
			$show['edit'] = (can_moderate_blog('canmoderateentries') AND can_moderate_blog('caneditentries'));
			$show['delete'] = ((can_moderate_blog('canremoveentries') OR can_moderate_blog('candeleteentries')) AND can_moderate_blog('canmoderateentries'));
			$show['undelete'] = false;	// Can't undelete a moderated post!
			$show['approve'] = can_moderate_blog('canmoderateentries');
		}
		else if ($blog['state'] == 'draft')
		{	// This should *always* be the owner of the post
			$show['edit'] = true;
			$show['delete'] = true;
			$show['undelete'] = false;
			$show['approve'] = false;
		}
		else
		{
			$show['edit'] = (can_moderate_blog('caneditentries') OR $canedit);
			$show['delete'] = (can_moderate_blog('candeleteentries') OR can_moderate_blog('canremoveentries') OR $candelete OR $canremove);
			$show['undelete'] = false;	// Can't undelete a visible post!
			$show['approve'] = can_moderate_blog('canmoderateentries');
		}

		if ($show['delete'])
		{
			$this->delete = true;
		}

		if ($show['undelete'])
		{
			$this->undelete = true;
		}

		if ($show['delete'] OR $show['approve'] OR $show['undelete'])
		{
			$this->inlinemod = true;
			$show['inlinemod'] = true;
		}
		else
		{
			$show['inlinemod'] = false;
		}

		if ($this->blog['dateline'] > TIMENOW OR $this->blog['pending'])
		{
			$this->status['phrase'] = $vbphrase['pending_blog_entry'];
			$this->status['image'] = "$stylevar[imgdir_misc]/blog/pending.gif";
			$show['status'] = true;
		}
		else if ($this->blog['state'] == 'deleted')
		{
			$this->status['image'] = "$stylevar[imgdir_misc]/trashcan.gif";
			$this->status['phrase'] = $vbphrase['deleted_blog_entry'];
			$show['status'] = true;
		}
		else if ($this->blog['state'] == 'moderation')
		{
			$this->status['phrase'] = $vbphrase['moderated_blog_entry'];
			$this->status['image'] = "$stylevar[imgdir_misc]/moderated.gif";
			$show['status'] = true;
		}
		else if ($this->blog['state'] == 'draft')
		{
			$this->status['phrase'] = $vbphrase['draft_blog_entry'];
			$this->status['image'] = "$stylevar[imgdir_misc]/blog/draft.gif";
			$show['status'] = true;
		}
		else
		{
			$show['status'] = false;
		}

		$show['private'] = false;
		if (can_moderate() AND $blog['userid'] != $this->registry->userinfo['userid'])
		{
			$everyoneelsecanview = $blog['options_everyone'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			$buddiescanview = $blog['options_buddy'] & $this->registry->bf_misc_vbblogsocnetoptions['canviewmyblog'];
			if (!$everyoneelsecanview AND (!$blog['buddyid'] OR !$buddiescanview))
			{
				$show['private'] = true;
			}
		}

		if ($this->blog['edit_userid'])
		{
			$this->blog['edit_date'] = vbdate($this->registry->options['dateformat'], $this->blog['edit_dateline'], true);
			$this->blog['edit_time'] = vbdate($this->registry->options['timeformat'], $this->blog['edit_dateline']);
			if ($this->blog['edit_reason'])
			{
				$this->blog['edit_reason'] = fetch_word_wrapped_string($this->blog['edit_reason']);
			}
			$show['entryedited'] = true;
		}
		else
		{
			$show['entryedited'] = false;
		}
	}
}

class vB_Blog_Entry_Deleted extends vB_Blog_Entry
{
	var $template = 'blog_entry_deleted';
}

class vB_Blog_Entry_Featured extends vB_Blog_Entry
{
	var $template = 'blog_entry_featured';

	/**
	* Entry retrieval type, either randomly or not
	*
	* @var	array
	*/
	var $random = false;

	/**
	* Set show['random']
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		global $show;

		$show['randomfeatured'] = $this->random;

		return parent::construct();
	}
}

class vB_Blog_Entry_Profile extends vB_Blog_Entry
{
	var $template = 'blog_entry_profile';
}

class vB_Blog_Entry_User extends vB_Blog_Entry
{
	var $template = 'blog_entry_without_userinfo';
}

class vB_Blog_Entry_External extends vB_Blog_Entry
{
	var $template = 'blog_entry_external';

	/**
	* Template method that does all the work to display an issue note, including processing the template
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		// preparation for display...
		$this->prepare_start();

		global $stylevar;

		$imgdir_attach = $stylevar['imgdir_attach'];
		if (!preg_match('#^[a-z]+:#siU', $stylevar['imgdir_attach']))
		{
			if ($stylevar['imgdir_attach'][0] == '/')
			{
				$url = parse_url($this->registry->options['bburl']);
				$stylevar['imgdir_attach'] = 'http://' . $url['host'] . $stylevar['imgdir_attach'];
			}
			else
			{
				$stylevar['imgdir_attach'] = $this->registry->options['bburl'] . '/' . $stylevar['imgdir_attach'];
			}
		}

		if ($this->blog['userid'])
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
		$this->process_attachments();
		$this->prepare_end();

		// actual display...
		$blog = $this->blog;
		$status =& $this->status;

		if ($this->attachments)
		{
			$search = '#(href|src)="blog_attachment\.php#si';
			$replace = '\\1="' . $this->registry->options['bburl'] . '/' . 'blog_attachment.php';
			$items = array(
				't' => $blog['thumbnailattachments'],
				'a' => $blog['imageattachments'],
				'l' => $blog['imageattachmentlinks'],
				'o' => $blog['otherattachments'],
			);

			$newitems = preg_replace($search, $replace, $items);
			unset($items);
			$blog['thumbnailattachments'] = $newitems['t'];
			$blog['imageattachments'] = $newitems['a'];
			$blog['imageattachmentlinks'] = $newitems['l'];
			$blog['otherattachments'] = $newitems['o'];
		}

		global $show, $vbphrase, $stylevar;
		global $spacer_open, $spacer_close;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		$show['readmore'] = $this->readmore;

		$sessionurl = $this->registry->session->vars['sessionurl'];
		$this->registry->session->vars['sessionurl'] = '';

		eval('$output = "' . fetch_template($this->template) . '";');

		$this->registry->session->vars['sessionurl'] = $sessionurl;
		$stylevar['imgdir_attach'] = $imgdir_attach;

		return $output;
	}
	/**
	* Parses the post for BB code.
	*/
	function process_text()
	{
		$this->blog['allowsmilie'] = false;
		parent::process_text();
	}
}

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 25020 $
|| ####################################################################
\*======================================================================*/
?>