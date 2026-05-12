<?php
/*
  $Id: contrib_tracker.php,v 1.7.12 2008/11/08 15:57:07 stevedallas Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

define('NAVBAR_TITLE', 'Contribution Tracker');
define('HEADING_TITLE', 'Contribution Tracker');
define('HEADING_TITLE_CONTRIBSEARCH','Search');
define('HEADING_CONTRIB_RSS_SUPPORT_SITE','OSCommerce Contributions RSS Feed');
define('HEADING_CONTRIB_HTML_SUPPORT_SITE','OSCommerce Contributions HTML Site');
define('HEADING_SUB_TITLE','Overview');
define('HEADING_SUB_TITLE_EDIT','Update');
define('HEADING_SUB_TITLE_INSERT','Insert');
define('HEADING_SUB_TITLE_DELETE','Delete');
define('HEADING_SUB_TITLE_SETFLAG','Change Status');
define('HEADING_SUB_TITLE_READONLY','Preview');


define('TEXT_NAME_VERSION','Contribution Name:');
define('TEXT_OSC_ID','OSC ID:');
define('TEXT_QUICKOSC_ID','Quick Add OSC ID:');
define('TEXT_INFO_HTML','Contribution URL:');
define('TEXT_CONFIG_COMMENTS','Notes:');
define('TEXT_INFO_HEADING_DELETE','Delete Contribution Entry?');
define('TEXT_INFO_DELETE_INTRO','Do you really want to delete ');
define('TEXT_INFO_DATE_ADDED','Date Added to Store:');
define('TEXT_INFO_LAST_MODIFIED','Last official update:');
define('TEXT_INFO_STATUS_CHANGE','Status Changed:');
define('TEXT_INFO_STATUS','Status:');
define('TEXT_INFO_TO_REMEMBER','Last integrated into store:');
define('TEXT_INFO_SUPPORT','Support Thread URL:');
define('TEXT_OSC_SUPPORT_THREAD','Support Thread URL');

define('TEXT_CONFIG_STATUS','Marker:');
define('TEXT_DISPLAY_NUMBER_OF_RECORDS','Displaying <b>%d</b> through <b>%d</b> of <b>%d</b> contributions');
define('TEXT_NO_DATA','No matching data found');
define('TEXT_EMPTY_DATABASE','No Contributions in Database!');
define('TEXT_HELP_NOTES_HEADER','Contribution Tracker usage notes:');
define('TEXT_HELP_NOTES','<li>Enter the title EXACTLY as on the contribution\'s page.</li><li>When the status light is red '.tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', 'New Update Exists', 10, 10).', click the contrib\'s name to see the updates.</li><li>Click on the green light '. tep_image(DIR_WS_IMAGES . "icon_status_green.gif", "No New Update", 10, 10).' to set the last integrated date to now OR to the date of the last update.</li>');
define('TEXT_CONTRIB_HEADER','Latest OSC Contributions:');
define('TEXT_VERSION_NUMBER','Version:');
define('TEXT_TODAY_DATE','Today');
define('TEXT_NAME_NOTE_NEW','<a href="javascript:toggleDivBlock(\'nameInfo\');">(info)</a><span id="nameInfo" style="display: none;"><br><i>For contributions on the OSCommerce site you only need to enter the id and Contribution Tracker will fill in the URL, and the name of this contribution.</i></span>');
define('TEXT_ID_NOTE_NEW','<a href="javascript:toggleDivBlock(\'idInfo\');">(info)</a><span id="idInfo" style="display: none;"><br><i>For contributions on the OSCommerce site you only need to enter the id and Contribution Tracker will fill in the URL, and the name of this contribution.</i></span>');
define('TEXT_VERSION_NOTE_NEW','<a href="javascript:toggleDivBlock(\'versionInfo\');">(info)</a><span id="versionInfo" style="display: none;"><br><i>Enter the version you installed here.</i></span>');
define('TEXT_INT_DATE_NEW','<a href="javascript:toggleDivBlock(\'IntInfo\');">(info)</a><span id="IntInfo" style="display: none;"><br><i>Check the \'Today\' box to enter today\'s date as the last integrated date.</i></span>');
define('TEXT_ADD_DATE_NEW','<a href="javascript:toggleDivBlock(\'AddInfo\');">(info)</a><span id="AddInfo" style="display: none;"><br><i>Check the \'Today\' box to enter today\'s date as the date you added this contribution to your store.</i></span>');
define('TEXT_ID_QUICK_NEW','<a href="javascript:toggleDivBlock(\'QuickEnter\');">(info)</a><span id="QuickEnter" style="display: none;"><br><i>Enter the Id of a contribution to quickly add it to your list. The current date will be used for installation and last integrated date. You will need to add your version number later manually if you want to.</i></span>');
define('TEXT_MANUAL_CHECK','Check contribution\'s page for update');
define('TEXT_OSC_URL','<a href="javascript:toggleDivBlock(\'OSCURL\');">(info)</a><span id="OSCURL" style="display: none;"><br><i>Enter the url of a contribution. If you entered the id you don\'t need to enter the url here, Contribution Tracker will do it for you.</i></span>');
define('TEXT_OSC_SUPPORT','<a href="javascript:toggleDivBlock(\'SUPPORTURL\');">(info)</a><span id="SUPPORTURL" style="display: none;"><br><i>Enter the url of the contribution\'s support thread.</i></span>');

define('TEXT_NEW_UPDATE_EXISTS','New Update Exists');
define('TEXT_SET_NEW_UPDATE_EXISTS','Set: New Update Exists Marker');
define('TEXT_NO_NEW_UPDATE','No New Update');
define('TEXT_SET_NO_NEW_UPDATE','Set: No New Update Marker');
define('TEXT_NO_STATUS','No Status');
define('TEXT_INFO','Info');

define('MESSAGE_INSERT_SUCCESS','Contribution successfully added.');
define('MESSAGE_INSERT_ERROR','Failed to add contribution.');
define('MESSAGE_INSERT_DUPLICATE','Contribution already exists.');
define('MESSAGE_UPDATE_SUCCESS','Contribution successfully updated.');
define('MESSAGE_UPDATE_ERROR','Failed to update contribution.');
define('MESSAGE_DELETE_SUCCESS','Contribution successfully deleted.');
define('MESSAGE_DELETE_ERROR','Failed to delete contribution.');
define('MESSAGE_UPDATECHECK_SUCCESS','Date added successfully.');
define('MESSAGE_UPDATECHECK_ERROR','Failed to add date.');
define('MESSAGE_LINKCHANGE_SUCCESS','Updated contribution link to new add-ons URL.');
define('MESSAGE_MANUALALL_SUCCESS','All contributions have been successfully updated.');
define('MESSAGE_MANUALALL_ERROR','Contribution failed to update: Contrib Name: ');
define('MESSAGE_MANUALALL_NONE_ERROR','There are no contributions to check.');

define('IMAGE_BUTTON_BACK','Back to previous page');
define('IMAGE_INSERT','Add new contribution');
define('IMAGE_BUTTON_UPSORT','Sort this column Ascending');
define('IMAGE_BUTTON_DOWNSORT','Sort this column Descending');
define('IMAGE_ICON_STATUS_NONE','Set: No status');
define('IMAGE_ICON_INFO',' Preview ');
define('IMAGE_CHECKALL',' Check HTML pages for all contribs');


define('TABLE_HEADING_NAME', 'Contribution Title');
define('TABLE_HEADING_STATUS', 'Status');
define('TABLE_HEADING_ACTION', 'Action');
define('TABLE_HEADING_VERSION', 'Version');
define('HEADING_LAST_CHECK', 'Last Manual Contrib Check:');
?>
