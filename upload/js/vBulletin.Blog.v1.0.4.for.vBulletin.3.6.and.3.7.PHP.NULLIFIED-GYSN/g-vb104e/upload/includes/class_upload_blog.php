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

require_once(DIR . '/includes/class_upload.php');

class vB_Upload_Attachment_Blog extends vB_Upload_Attachment
{
	var $bloginfo = array();

	function fetch_max_uploadsize($extension)
	{
		if (!empty($this->userinfo['attachmentpermissions']["$extension"]['size']))
		{
			return $this->userinfo['attachmentpermissions']["$extension"]['size'];
		}
		else
		{
			return 0;
		}
	}

	function is_valid_extension($extension)
	{
		return !empty($this->userinfo['attachmentpermissions']["$extension"]['permissions']);
	}

	function process_upload($uploadstuff = '')
	{
		if ($this->registry->attachmentcache === null)
		{
			trigger_error('vB_Upload_Attachment: Attachment cache not specfied. Can not continue.', E_USER_ERROR);
		}

		if ($this->accept_upload($uploadstuff))
		{
			// Verify Extension is proper
			if (!$this->is_valid_extension($this->upload['extension']))
			{
				$this->set_error('upload_invalid_file');
				return false;
			}

			$jpegconvert = false;
			// is this a filetype that can be processed as an image?
			if ($this->image->is_valid_info_extension($this->upload['extension']))
			{
				$this->maxwidth = $this->userinfo['attachmentpermissions']["{$this->upload['extension']}"]['width'];
				$this->maxheight = $this->userinfo['attachmentpermissions']["{$this->upload['extension']}"]['height'];

				if ($this->imginfo = $this->image->fetch_image_info($this->upload['location']))
				{
					if (!$this->imginfo[2])
					{
						$this->set_error('upload_invalid_image');
						return false;
					}

					if ($this->image->fetch_imagetype_from_extension($this->upload['extension']) != $this->imginfo[2])
					{
						$this->set_error('upload_invalid_image_extension', $this->imginfo[2]);
						return false;
					}

					if (($this->maxwidth > 0 AND $this->imginfo[0] > $this->maxwidth) OR ($this->maxheight > 0 AND $this->imginfo[1] > $this->maxheight))
					{
						$resizemaxwidth = ($this->registry->config['Misc']['maxwidth']) ? $this->registry->config['Misc']['maxwidth'] : 2592;
						$resizemaxheight = ($this->registry->config['Misc']['maxheight']) ?$this->registry->config['Misc']['maxheight'] : 1944;
						if ($this->registry->options['attachresize'] AND $this->image->is_valid_resize_type($this->imginfo[2]) AND $this->imginfo[0] <= $resizemaxwidth AND $this->imginfo[1] <= $resizemaxheight)
						{
							$this->upload['resized'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $this->maxwidth, $this->maxheight, $this->registry->options['thumbquality'], false, false, true, false);
							if (empty($this->upload['resized']['filedata']))
							{
								if (!empty($this->upload['resized']['imageerror']) AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
								{
									if (($error = $this->image->fetch_error()) !== false AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
									{
										$this->set_error('image_resize_failed_x', htmlspecialchars_uni($error));
										return false;
									}
									else
									{
										$this->set_error($this->upload['resized']['imageerror']);
										return false;
									}
								}
								else
								{
									$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
									return false;
								}
							}
							else
							{
								$jpegconvert = true;
							}
						}
						else
						{
							$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
							return false;
						}
					}
				}
				else if ($this->upload['extension'] != 'pdf')
				{	// don't error on .pdf imageinfo failures
					if ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						$this->set_error('upload_imageinfo_failed_x', htmlspecialchars_uni($this->image->fetch_error()));
					}
					else
					{
						$this->set_error('upload_invalid_image');
					}
					return false;
				}

				// Generate Thumbnail
				if ($this->registry->attachmentcache["{$this->upload['extension']}"]['thumbnail'] AND $this->registry->options['attachthumbs'])
				{
					$labelimage = ($this->registry->options['attachthumbs'] == 3 OR $this->registry->options['attachthumbs'] == 4);
					$drawborder = ($this->registry->options['attachthumbs'] == 2 OR $this->registry->options['attachthumbs'] == 4);
					$this->upload['thumbnail'] = $this->image->fetch_thumbnail($this->upload['filename'], $this->upload['location'], $this->registry->options['attachthumbssize'], $this->registry->options['attachthumbssize'], $this->registry->options['thumbquality'], $labelimage, $drawborder, $jpegconvert, true, $this->upload['resized']['width'], $this->upload['resized']['height'], $this->upload['resized']['filesize']);
					if (empty($this->upload['thumbnail']['filedata']) AND !empty($this->upload['thumbnail']['imageerror']) AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
					{
						if (($error = $this->image->fetch_error()) !== false AND $this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel'])
						{
							$this->set_warning('thumbnail_failed_x', htmlspecialchars_uni($error));
						}
						else
						{
							$this->set_warning($this->upload['thumbnail']['imageerror']);
						}
					}
				}
			}

			$this->maxuploadsize = $this->fetch_max_uploadsize($this->upload['extension']);
			if (!$jpegconvert AND $this->maxuploadsize > 0 AND $this->upload['filesize'] > $this->maxuploadsize)
			{
				$this->set_error('upload_file_exceeds_forum_limit', vb_number_format($this->upload['filesize'], 1, true), vb_number_format($this->maxuploadsize, 1, true));
				return false;
			}

			if (!empty($this->upload['resized']))
			{
				if (!empty($this->upload['resized']['filedata']))
				{
					$this->upload['filestuff'] =& $this->upload['resized']['filedata'];
					$this->upload['filesize'] =& $this->upload['resized']['filesize'];
					if ($this->upload['resized']['filename'])
					{
						$this->upload['filename'] =& $this->upload['resized']['filename'];
					}
				}
				else
				{
					$this->set_error('upload_exceeds_dimensions', $this->maxwidth, $this->maxheight, $this->imginfo[0], $this->imginfo[1]);
					return false;
				}
			}
			else if (!($this->upload['filestuff'] = @file_get_contents($this->upload['location'])))
			{
				$this->set_error('upload_file_failed');
				return false;
			}

			if (!$this->check_attachment_overage())
			{
				return false;
			}

			@unlink($this->upload['location']);
			return $this->save_upload();
		}
		else
		{
			return false;
		}
	}

	function check_attachment_overage()
	{
		return true;
	}

	function save_upload()
	{
		$this->data->set('dateline', TIMENOW);
		$this->data->set('thumbnail_dateline', TIMENOW);
		if ($this->data->fetch_field('visible') === null)
		{
			if (isset($this->foruminfo['moderateattach']))
			{
				$visible = ((!$this->foruminfo['moderateattach'] OR can_moderate($this->foruminfo['forumid'], 'canmoderateattachments')) ? 'visible' : 'moderation');
			}
			else
			{
				#default an attachment with no specified visibility to true
				$visible = 'visible';
			}
			$this->data->set('visible', $visible);
		}
		$this->data->setr('userid', $this->userinfo['userid']);
		$this->data->setr('filename', $this->upload['filename']);
		$this->data->setr('posthash', $this->bloginfo['posthash']);
		$this->data->setr_info('filedata', $this->upload['filestuff']);
		$this->data->setr_info('thumbnail', $this->upload['thumbnail']['filedata']);
		$this->data->setr_info('blogid', $this->bloginfo['blogid']);

		// Update an existing attachment of the same name, rather than insert a new one or throw an "Attachment Already Exists" error
		// I don't think this is actually used so ignore it for now
		$this->data->set_info('updateexisting', true);

		if (!($result = $this->data->save()))
		{
			if (empty($this->data->errors[0]) OR !($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				$this->set_error('upload_file_failed');
			}
			else
			{
				$this->error =& $this->data->errors[0];
			}
		}

		unset($this->upload);

		return $result;
	}
}

/*======================================================================*\
|| ####################################################################
|| #
|| # CVS: $RCSfile$ - $Revision: 16192 $
|| ####################################################################
\*======================================================================*/
?>
