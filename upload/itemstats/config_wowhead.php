<?php

/* 
 * Wowhead configuration
 * started: 04/05/2007
 *
 * author: Frank Matheron
 * email: fenuzz@gmail.com
 * description: wowhead support mod configuration settings
 *
 *
 * part of the itemstats wowhead support mod version 0.3.10
 *
 */

//
// WOWHEAD SUPPORT SETTINGS
//

// template to use when creating wowhead tooltips
define('WOWHEAD_TEMPLATE', 'popup_wowhead.tpl');

// enable/disable the automatic downloading of icons
define('DOWNLOAD_ICONS', true);

// local path to the directory in which the downloaded icons should be stored
// make sure the script has write access to this directory
define('LOCAL_ICON_STORE_PATH', DIR . '/itemstats/wowhead_icons/');

// remote path where the icons can be downloaded
define('REMOTE_ICON_STORE_PATH', 'http://static.wowhead.com/images/icons/medium/');

?>