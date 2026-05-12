<?php
/*
  $Id: configuration.php,v 1.1.1.1 2002/11/28 20:57:09 wilt Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/


define('HEADER_TITLE', 'Infobox Display,Update and Create');
define('TABLE_HEADING_CONFIGURATION_TITLE', 'Title');
define('TABLE_HEADING_CONFIGURATION_VALUE', 'Filename');
define('TABLE_HEADING_ACTION', 'Action');
define('TABLE_HEADING_COLUMN', 'Column');
define('TABLE_HEADING_SORT_ORDER', 'Position');

define('TEXT_INFO_DATE_ADDED', 'Date Added:');
define('TEXT_INFO_LAST_MODIFIED', 'Last Modified:');
define('TEXT_INFO_STATS1', 'There are currently<br>');
define('TEXT_INFO_STATS2', 'boxes in the left column and <br>');
define('TEXT_INFO_STATS3', 'boxes in the right column');

define('TEXT_INFO_EDIT_INTRO', 'Please make any necessary changes');
define('TEXT_INFOBOX_ACTIVATE', 'Activate this box?');
define('TEXT_INFOBOX_COLUMN', 'Which column?');
define('TEXT_INFOBOX_ORDER', 'What order?');

define('TEXT_INFO_HEADING_DELETE_INFOBOX', 'Delete Infobox?');
define('TEXT_INFO_DELETE_INTRO', '<P STYLE="color: red; font-weight: bold;">Confirm OK to delete the Infobox');

define('TEXT_INFO_HEADING_NEW_INFOBOX', 'Adding a new Infobox');
define('TEXT_INFO_INSERT_INTRO', 'An example for the<b> what\'s_new.php</b> infobox is selected');
define('TEXT_INFOBOX_NEW_FILENAME', 'Filename');
define('TEXT_INFOBOX_NEW_NAME', 'The infoBox heading?');
define('TEXT_INFOBOX_NEW_KEY', 'Define key');
define('TEXT_INFOBOX_NEW_COLUMN', 'Which column?<br>(right or left)');

define('IMAGE_NEW_INFOBOX', 'New infobox');
define('IMAGE_INFOBOX_STATUS_GREEN', 'Left');
define('IMAGE_INFOBOX_STATUS_GREEN_LIGHT', 'Set left');
define('IMAGE_INFOBOX_STATUS_RED', 'Right');
define('IMAGE_INFOBOX_STATUS_RED_LIGHT', 'Set Right');

define('BOX_HEADING_BOXES', 'Boxes admin');

define('JS_BOX_HEADING', '* The \'Define Key\' must be completed.    Example BOX_HEADING_WHATS_NEW\n');
define('JS_INFO_BOX_HEADING', '* The \'Box heading\' must be completed.\n');
define('JS_BOX_LOCATION', '* You must select a column to display your Infobox\n');
define('JS_INFO_BOX_FILENAME', '* You must select a Filename for your Infobox\n');
?>