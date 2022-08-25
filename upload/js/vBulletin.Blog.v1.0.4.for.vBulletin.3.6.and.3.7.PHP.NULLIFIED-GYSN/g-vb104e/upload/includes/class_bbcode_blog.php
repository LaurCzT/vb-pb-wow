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

require_once(DIR . '/includes/class_bbcode.php');

class vB_BbCodeParser_Blog extends vB_BbCodeParser
{
	function vB_BbCodeParser_Blog(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, $append_custom_tags);
	}

	/**
	* Handles an [img] tag.
	*
	* @param	string	The text to search for an image in.
	* @param	string	Whether to parse matching images into pictures or just links.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false)
	{
		global $vbphrase;

		if (($has_img_code & BBCODE_HAS_ATTACH) AND preg_match_all('#\[attach(?:=(right|left))?\](\d+)\[/attach\]#i', $bbcode, $matches))
		{
			foreach($matches[2] AS $key => $attachmentid)
			{
				$align = $matches[1]["$key"];
				$search[] = "#\[attach" . (!empty($align) ? '=' . $align : '') . "\]($attachmentid)\[/attach\]#i";

				// attachment specified by [attach] tag belongs to this post
				if (!empty($this->attachments["$attachmentid"]))
				{
					$attachment =& $this->attachments["$attachmentid"];
					if ($attachment['visible'] != 'visible' AND $attachment['userid'] != $this->registry->userinfo['userid'])
					{	// Don't show inline unless the poster is viewing the post (post preview)
						continue;
					}

					if ($attachment['thumbnail_filesize'] == $attachment['filesize'] AND ($this->registry->options['viewattachedimages'] OR $this->registry->options['attachthumbs']))
					{
						$attachment['hasthumbnail'] = false;
						$forceimage = true;
					}

					$addtarget = ($attachment['newwindow']) ? 'target="_blank"' : '';
					/** doesn't need to be added to the link, should just be added to the image
					$addtarget .= !empty($align) ? " style=\"float: $align\" " : '';
					*/

					$attachment['filename'] = fetch_censored_text(htmlspecialchars_uni($attachment['filename']));
					$attachment['extension'] = strtolower(file_extension($attachment['filename']));
					$attachment['filesize'] = vb_number_format($attachment['filesize'], 1, true);

					switch($attachment['extension'])
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
								if ($this->registry->options['attachthumbs'] AND $attachment['hasthumbnail'] AND $this->registry->userinfo['showimages'])
								{	// Display a thumbnail
									$replace[] = "<a href=\"{$this->registry->options['bburl']}/blog_attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=\\1&amp;d=$attachment[dateline]\" $addtarget><img src=\"{$this->registry->options['bburl']}/blog_attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=\\1&amp;thumb=1&amp;d=$attachment[thumbnail_dateline]\" class=\"thumbnail\" border=\"0\" alt=\""
									. construct_phrase($vbphrase['image_larger_version_x_y_z'], $attachment['filename'], $attachment['counter'], $attachment['filesize'], $attachment['attachmentid'])
									. "\" " . (!empty($align) ? " style=\"float: $align; margin: 2px\"" : 'style="margin: 2px"') . " /></a>";
								}
								else if ($this->registry->userinfo['showimages'] AND ($forceimage OR $this->registry->options['viewattachedimages']) AND !in_array($attachment['extension'], array('tiff', 'tif', 'psd', 'pdf')))
								{	// Display the attachment with no link to bigger image
									$replace[] = "<img src=\"{$this->registry->options['bburl']}/blog_attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=\\1&amp;d=$attachment[dateline]\" border=\"0\" alt=\""
									. construct_phrase($vbphrase['image_x_y_z'], $attachment['filename'], $attachment['counter'], $attachment['filesize'])
									. "\" " . (!empty($align) ? " style=\"float: $align; margin: 2px\"" : 'style="margin: 2px"') . " />";
								}
								else
								{	// Display a link
									$replace[] = "<a href=\"{$this->registry->options['bburl']}/blog_attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=\\1&amp;d=$attachment[dateline]\" $addtarget>$attachment[filename]</a>";
								}
							break;
						default:
							$replace[] = "<a href=\"{$this->registry->options['bburl']}/blog_attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=\\1&amp;d=$attachment[dateline]\" $addtarget>$attachment[filename]</a>";
					}
				}
				else
				{	// Belongs to another post so we know nothing about it ... or we are not displying images so always show a link
					$addtarget = (empty($this->attachments["$attachmentid"]) OR $attachment['newwindow']) ? 'target="_blank"' : '';
					/** doesn't need to be added to the link, should just be added to the image
					$addtarget .= !empty($align) ? " style=\"float: $align\" " : '';
					*/
					$replace[] = "<a href=\"{$this->registry->options['bburl']}/blog_attachment.php?{$this->registry->session->vars['sessionurl']}attachmentid=\\1" . (!empty($attachment['dateline']) ? "&amp;d=$attachment[dateline]" : "") . "\" $addtarget>$vbphrase[attachment] \\1</a>";
				}

				// remove attachment from array
				if ($this->unsetattach)
				{
					unset($this->attachments["$attachmentid"]);
				}
			}

			$bbcode = preg_replace($search, $replace, $bbcode);
		}

		// If you wanted to be able to edit [img] when editing a post instead of seeing the image, add the get_class() check from above
		if ($has_img_code & BBCODE_HAS_IMG)
		{
			if ($do_imgcode AND ($this->registry->userinfo['userid'] == 0 OR $this->registry->userinfo['showimages']))
			{
				// do [img]xxx[/img]
				$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"' . iif(!$this->registry->options['allowdynimg'], '?') . ']+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_img_match('\\1')", $bbcode);
			}
			$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_url(str_replace('\\\"', '\"', '\\1'), '')", $bbcode);
		}

		return $bbcode;
	}
}

class vB_BbCodeParser_Blog_Snippet extends vB_BbCodeParser_Blog
{
	/**
	* Length of the snippet in characters.
	*
	* @var	integer
	*/
	var $snippet_length = 500;

	/**
	* A list of uninterruptable tags. These tags will not be broken by a snippet,
	* even at a space. Useful for tags whose text is something like a URL.
	*
	* @var	array	Key: tag name; value: anything that casts to true
	*/
	var $uninterruptable = array(
		'img' => true,
		'url' => true
	);

	/**
	* Boolean value if submitted text is made into a snippet
	*
	* @var	Boolean
	*/
	var $createdsnippet = false;

	function vB_BbCodeParser_Blog_Snippet(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser_Blog($registry, $tag_list, $append_custom_tags);

		$this->snippet_length = $this->registry->options['vbblog_snippet'];
	}

	/**
	* Parse an input string with BB code to a final output string of HTML
	*
	* @param	string	Input Text (BB code)
	* @param	bool	Whether to parse smilies
	* @param	bool	Whether to allow HTML (for smilies)
	*
	* @return	string	Ouput Text (HTML)
	*/
	function parse_bbcode($input_text, $do_smilies, $do_html = false)
	{
		return $this->parse_array(
			$this->make_snippet(
				$this->fix_tags($this->build_parse_array($input_text)),
				vbstrlen($input_text)
			),
			$do_smilies,
			$do_html
		);
	}

	/**
	* Chops a set of (fixed) BB code tokens to a specified length or slightly over.
	* It will search for the first whitespace after the snippet length.
	*
	* @param	array	Fixed tokens
	* @param	integer	Length of the text before parsing (optional)
	*
	* @return	array	Tokens, chopped to the right length.
	*/
	function make_snippet($tokens, $initial_length = 0)
	{
		// no snippet to make, or our original text was short enough
		if ($this->snippet_length == 0 OR ($initial_length AND $initial_length < $this->snippet_length))
		{
			$this->createdsnippet = false;
			return $tokens;
		}

		$counter = 0;
		$stack = array();
		$new = array();
		$over_threshold = false;

		foreach ($tokens AS $tokenid => $token)
		{
			// only count the length of text entries
			if ($token['type'] == 'text')
			{
				$length = vbstrlen($token['data']);

				// uninterruptable means that we will always show until this tag is closed
				$uninterruptable = (isset($stack[0]) AND isset($this->uninterruptable["$stack[0]"]));

				if ($counter + $length < $this->snippet_length OR $uninterruptable)
				{
					// this entry doesn't push us over the threshold
					$new["$tokenid"] = $token;
					$counter += $length;
				}
				else
				{
					// a text entry that pushes us over the threshold
					$over_threshold = true;
					$last_char_pos = $this->snippet_length - $counter - 1; // this is the threshold char; -1 means look for a space at it
					if ($last_char_pos < 0)
					{
						$last_char_pos = 0;
					}

					if (preg_match('#\s#s', $token['data'], $match, PREG_OFFSET_CAPTURE, $last_char_pos))
					{
						$token['data'] = substr($token['data'], 0, $match[0][1]); // chop to offset of whitespace
						if (substr($token['data'], -3) == '<br')
						{
							// we cut off a <br /> code, so just take this out
							$token['data'] = substr($token['data'], 0, -3);
						}

						$new["$tokenid"] = $token;
					}
					else
					{
						$new["$tokenid"] = $token;
					}

					break;
				}
			}
			else
			{
				// not a text entry
				if ($token['type'] == 'tag')
				{
					// build a stack of open tags
					if ($token['closing'] == true)
					{
						// by now, we know the stack is sane, so just remove the first entry
						array_shift($stack);
					}
					else
					{
						array_unshift($stack, $token['name']);
					}
				}

				$new["$tokenid"] = $token;
			}
		}

		// since we may have cut the text, close any tags that we left open
		foreach ($stack AS $tag_name)
		{
			$new[] = array('type' => 'tag', name => $tag_name, 'closing' => true);
		}

		$this->createdsnippet = (sizeof($new) != sizeof($tokens) OR $over_threshold); // we did something, so we made a snippet

		return $new;
	}
}


class vB_BbCodeParser_Blog_Snippet_Featured extends vB_BbCodeParser_Blog_Snippet
{

	var $undisplayable_tags = array(
		'code' => array(
			'extra_lines_after' => 2, // extra line breaks after for block-level elements
			'replace_phrase' => 'featured_replacement_code' // name of the phrase to replace with
		),
		'php' => array(
			'extra_lines_after' => 2,
			'replace_phrase' => 'featured_replacement_php'
		),
		'html' => array(
			'extra_lines_after' => 2,
			'replace_phrase' => 'featured_replacement_html'
		)
	);

	/**
	* Constructor. Sets up the tag list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		List of tags to parse
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_BbCodeParser_Blog_Snippet_Featured(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser_Blog_Snippet($registry, $tag_list, $append_custom_tags);

		// change all unparsable tags to use the unparsable callback
		foreach (array_keys($this->undisplayable_tags) AS $remove)
		{
			if (isset($this->tag_list['option']["$remove"]))
			{
				$this->tag_list['option']["$remove"]['callback'] = 'handle_undisplayable_tag';
				unset($this->tag_list['option']["$remove"]['html']);
			}
			if (isset($this->tag_list['no_option']["$remove"]))
			{
				$this->tag_list['no_option']["$remove"]['callback'] = 'handle_undisplayable_tag';
				unset($this->tag_list['no_option']["$remove"]['html']);
			}
		}
	}

	/**
	* Parse an input string with BB code to a final output string of HTML
	*
	* @param	string	Input Text (BB code)
	* @param	bool	Whether to parse smilies
	* @param	bool	Whether to allow HTML (for smilies)
	*
	* @return	string	Ouput Text (HTML)
	*/
	function parse_bbcode($input_text, $do_smilies, $do_html = false)
	{
		global $vbulletin;

		$temp = $vbulletin->options['wordwrap'];
		$vbulletin->options['wordwrap'] = $vbulletin->options['blog_wordwrap'];

		$output = $this->parse_array(
			$this->make_snippet(
				$this->fix_tags($this->build_parse_array($input_text)),
				vbstrlen($input_text)
			),
			$do_smilies,
			$do_html
		);

		$vbulletin->options['wordwrap'] = $temp;
		return $output;
	}

	/**
	* Handles tags that would be unsuitable for the featured blog display,
	* mainly because of width constraints (due to the profile picture).
	*
	* @param	string	Text (ignored)
	* @param	string	Option (ignored)
	*
	* @return	string	Placeholder HTML
	*/
	function handle_undisplayable_tag($text, $option = '')
	{
		global $vbphrase;

		$tag_info = $this->undisplayable_tags[$this->current_tag['name']];

		$output = '';

		if (!empty($tag_info['extra_lines_before']))
		{
			$output .= str_repeat("<br />\n", $tag_info['extra_lines_before']);
		}

		$output .= $vbphrase["$tag_info[replace_phrase]"];

		if (!empty($tag_info['extra_lines_after']))
		{
			$output .= str_repeat("<br />\n", $tag_info['extra_lines_after']);
		}

		return $output;
	}

	/**
	* Handles a [url] tag. Creates a link to another web page.
	*
	* @param	string	If tag has option, the displayable name. Else, the URL.
	* @param	string	If tag has option, the URL.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_url($text, $link)
	{
		global $vbphrase;

		$rightlink = trim($link);
		if (empty($rightlink))
		{
			// no option -- use param
			$rightlink = trim($text);
		}
		$rightlink = str_replace(array('`', '"', "'", '['), array('&#96;', '&quot;', '&#39;', '&#91;'), $this->strip_smilies($rightlink));

		// remove double spaces -- fixes issues with wordwrap
		$rightlink = str_replace('  ', '', $rightlink);

		if (!preg_match('#^[a-z0-9]+(?<!about|javascript|vbscript|data):#si', $rightlink))
		{
			$rightlink = "http://$rightlink";
		}

		if (!trim($link) OR str_replace('  ', '', $text) == $rightlink)
		{
			// this is just going to show a URL, so show a place holder instead to cater to fixed styles
			return  $vbphrase['featured_replacement_link'];
		}

		// standard URL hyperlink
		return "<a href=\"$rightlink\" target=\"_blank\">$text</a>";
	}

	/**
	* Handles an [img] tag.
	*
	* @param	string	The text to search for an image in.
	* @param	string	Whether to parse matching images into pictures or just links.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false)
	{
		global $vbphrase;

		if (($has_img_code & BBCODE_HAS_ATTACH) AND preg_match_all('#\[attach(?:=(right|left))?\](\d+)\[/attach\]#i', $bbcode, $matches))
		{
			foreach($matches[2] AS $key => $attachmentid)
			{
				$align = $matches[1]["$key"];
				$search[] = "#\[attach" . (!empty($align) ? '=' . $align : '') . "\]($attachmentid)\[/attach\]#i";
				$replace[] = $vbphrase['featured_replacement_attachment'];

				// remove attachment from array
				if ($this->unsetattach)
				{
					unset($this->attachments["$attachmentid"]);
				}
			}

			$bbcode = preg_replace($search, $replace, $bbcode);
		}

		// If you wanted to be able to edit [img] when editing a post instead of seeing the image, add the get_class() check from above
		if ($has_img_code & BBCODE_HAS_IMG)
		{
			if ($do_imgcode AND ($this->registry->userinfo['userid'] == 0 OR $this->registry->userinfo['showimages']))
			{
				// do [img]xxx[/img]
				$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"' . iif(!$this->registry->options['allowdynimg'], '?') . ']+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_img_match('\\1')", $bbcode);
			}
			$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_url(str_replace('\\\"', '\"', '\\1'), '')", $bbcode);
		}

		return $bbcode;
	}

	/**
	* Handles a match of the [img] tag that will be displayed as an actual image.
	*
	* @param	string	The URL to the image.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img_match($link)
	{
		global $vbphrase;

		return $vbphrase['featured_replacement_image'];
	}

}

/*======================================================================*\
|| ####################################################################
|| #
|| # SVN: $Revision: 17991 $
|| ####################################################################
\*======================================================================*/
?>