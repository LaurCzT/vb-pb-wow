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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for blog users
*
* @package	vBulletin
* @version	$Revision: 23715 $
* @date		$Date: 2007-08-31 02:11:48 -0500 (Fri, 31 Aug 2007) $
*/
class vB_DataManager_Blog_Category extends vB_DataManager
{
	/**
	* Array of recognised and required fields for threadrate, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'blogcategoryid' => array(TYPE_UINT, REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'userid'         => array(TYPE_UINT, REQ_YES,  VF_METHOD, 'verify_userid'),
		'title'          => array(TYPE_STR,  REQ_YES,  VF_METHOD, 'verify_title'),
		'description'    => array(TYPE_STR,  REQ_NO,   VF_METHOD, 'verify_description'),
		'parentlist'     => array(TYPE_STR,  REQ_NO),
		'childlist'      => array(TYPE_STR,  REQ_NO),
		'parentid'       => array(TYPE_UINT, REQ_NO,   VF_METHOD, 'verify_parentid'),
		'displayorder'   => array(TYPE_UINT, REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('blogcategoryid = %1$s', 'blogcategoryid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'blog_category';

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Blog_Category(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('blog_categorydata_start')) ? eval($hook) : false;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('blog_categorydata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function delete($doquery = true)
	{
		$categorylist = '';

		$userids = array();
		$categories = $this->registry->db->query_read("SELECT blogcategoryid, userid FROM " . TABLE_PREFIX . "blog_category WHERE " . $this->condition);
		while ($thiscategory = $this->registry->db->fetch_array($categories))
		{
			$userids["$thiscategory[userid]"] = $thiscategory['userid'];
			$categorylist .= ',' . $thiscategory['blogcategoryid'];
		}

		$categorylist = substr($categorylist, 1);

		if ($categorylist == '')
		{
			$this->error('invalid_category_specified');
		}
		else
		{
			$condition = "blogcategoryid IN ($categorylist)";
			// This make all of the posts belong to Uncategorized -- we might want to make them belong to the parent of the deleted parent category
			$this->db_delete(TABLE_PREFIX, 'blog_categoryuser', $condition);
			$this->db_delete(TABLE_PREFIX, 'blog_category', $condition);

			foreach ($userids AS $userid)
			{
				build_category_genealogy($userid);
				build_blog_user_counters($userid);
			}
		}
	}

	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('blog_categorydata_delete')) ? eval($hook) : false;
	}


	/**
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		build_category_genealogy($this->fetch_field('userid'));
		($hook = vBulletinHook::fetch_hook('blog_categorydata_postsave')) ? eval($hook) : false;
	}

	/**
	* Verifies the title is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_title(&$title)
	{
		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		require_once(DIR . '/includes/functions_newpost.php');
		// censor, remove all caps subjects, and htmlspecialchars post title
		$title = htmlspecialchars_uni(fetch_no_shouting_text(fetch_censored_text(trim($title))));

		// do word wrapping
		$title = fetch_word_wrapped_string($title, $this->registry->options['blog_wordwrap']);

		if (empty($title))
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Verifies the description is valid and sets up the title for saving (wordwrap, censor, etc).
	*
	* @param	string	Title text
	*
	* @param	bool	Whether the title is valid
	*/
	function verify_description(&$desc)
	{
		// replace html-encoded spaces with actual spaces
		$desc = preg_replace('/&#(0*32|x0*20);/', ' ', $desc);

		require_once(DIR . '/includes/functions_newpost.php');
		// censor, remove all caps subjects, and htmlspecialchars post title
		$desc = htmlspecialchars_uni(fetch_no_shouting_text(fetch_censored_text(trim($desc))));

		// do word wrapping
		$desc = fetch_word_wrapped_string($desc, $this->registry->options['blog_wordwrap']);

		return true;
	}

	/**
	*
	* @param		integer	parentid'
	*
	* @return	bool		Valid parentid
	*/
	function verify_parentid(&$parentid)
	{
		if (!($userid = $this->fetch_field('userid')))
		{
			trigger_error('Must set userid before parentid', E_USER_ERROR);
		}

		if ($parentid != 0 AND $parentid == $this->fetch_field('blogcategoryid'))
		{
			$this->error('cant_parent_category_to_self');
			return false;
		}
		else if ($parentid <= 0)
		{
			$parentid = 0;
			return true;
		}
		else if (!isset($this->registry->vbblog['categorycache']["$userid"]["$parentid"]))
		{
			$this->error('invalid_category_specified');
			return false;
		}
		else if ($this->condition !== null)
		{
			return $this->is_subcategory_of($this->fetch_field('blogcategoryid'), $parentid);
		}
		else
		{
			// no condition specified, so it's not an existing category...
			return true;
		}
	}

	/**
	* Verifies that a given blog parent id is not one of its own children
	*
	* @param	integer	The ID of the current category
	* @param	integer	The ID of the category's proposed parentid
	*
	* @return	boolean	Returns true if the children of the given parent category does not include the specified category... or something
	*/
	function is_subcategory_of($blogcategoryid, $parentid)
	{
		$userid = $this->fetch_field('userid');

		if (is_array($this->registry->vbblog['icategorycache']["$userid"]["$blogcategoryid"]))
		{
			foreach ($this->registry->vbblog['icategorycache']["$userid"]["$blogcategoryid"] AS $curcategoryid => $category)
			{
				if ($curcategoryid == $parentid OR !$this->is_subcategory_of($curcategoryid, $parentid))
				{
					$this->error('cant_parent_category_to_child');
					return false;
				}
			}
		}

		return true;
	}
}
/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 23715 $
|| ####################################################################
\*======================================================================*/
?>