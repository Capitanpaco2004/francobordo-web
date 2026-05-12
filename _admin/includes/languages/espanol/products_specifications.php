<?php
/*
  $Id: products_specifications.php v1.1 20101029 kymation $
  Based on: categories.php 1739 2007-12-20 00:52:16Z hpdl $
  $Loc: catalog/admin/includes/languages/english/ $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

// Headings
  if (!defined('HEADING_TITLE_GROUPS')) define ('HEADING_TITLE_GROUPS', 'Groups');
  define ('HEADING_TITLE_SPECIFICATIONS', 'Specifications in Group: ');
  define ('HEADING_TITLE_FILTERS', 'Filters in  Specification: ');
  define ('HEADING_TITLE_VALUES', 'Specification Values in Specification: ');
  define ('HEADING_TITLE_SEARCH_GROUPS', 'Search for Group:');
  define ('HEADING_TITLE_SEARCH_SPECIFICATIONS', 'Search for Specification:');
  define ('HEADING_TITLE_SEARCH_FILTERS', 'Search for Filter:');
  define ('HEADING_TITLE_SEARCH_VALUES', 'Search for Specification Values:');
  if (!defined('HEADING_TITLE_GROUPS')) define ('HEADING_TITLE_GROUPS', 'Search: ');
  define ('HEADING_TITLE_GOTO', 'Go To: ');

// Specification Groups
  // Table Headings
  define ('TABLE_HEADING_ID', 'ID');
  define ('TABLE_HEADING_GROUPS', 'Group Name');
  define ('TABLE_HEADING_SPECS', 'Specifications');
  define ('TABLE_HEADING_FILTERS', 'Filters');
  define ('TABLE_HEADING_ACTION', 'Action');
  define ('TABLE_HEADING_PRODUCTS', 'Show on Products Info Page');
  define ('TABLE_HEADING_FILTER', 'Show Filter');
  define ('TABLE_HEADING_IN_FILTER', 'Show in Filter');
  define ('TABLE_HEADING_COMPARISON', 'Show Comparison Page');
  define ('TABLE_HEADING_ON_COMPARISON', 'Show on Comparison Page');
  define ('TABLE_HEADING_IN_FEEDER', 'Use in Feeder');
  define ('TABLE_HEADING_SORT_ORDER', 'Sort Order');
  define ('TABLE_HEADING_VALUES', 'Value');

  define ('TEXT_GROUPS_TOTAL', 'Total Groups: ');
  define ('TEXT_SPECS_TOTAL', 'Total Specifications: ');
  define ('TEXT_FILTERS_TOTAL', 'Total Filters: ');
  define ('TEXT_VALUES_TOTAL', 'Total Values: ');
  define ('TEXT_FILTERS_TOTAL_SPEC', 'Total Filters in this Specification: ');
  define ('TEXT_VALUES_TOTAL_SPEC', 'Total Values for this Specification: ');
  define ('TEXT_SPECS_TOTAL_GROUP', 'Total Specifications in this Group: ');
  define ('TEXT_FILTERS_TOTAL_GROUP', 'Total Filters in this Group: ');
  define ('TEXT_VALUES_TOTAL_GROUP', 'Total Values for this Group: ');

  define ('TEXT_INFO_HEADING_NEW_GROUP', 'New Group');
  if (!defined('TEXT_INFO_HEADING_EDIT_GROUP')) define ('TEXT_INFO_HEADING_EDIT_GROUP', 'Edit Group');
  define ('TEXT_NEW_GROUP_INTRO', 'Please fill out the following information for the new group');
  define ('TEXT_GROUP_NAME', 'Group Name:');
  define ('TEXT_SHOW_COMPARISON', 'Show Comparison Page:');
  define ('TEXT_SHOW_ON_PRODUCTS', 'Show on Products Info Page');
  define ('TEXT_SHOW_FILTER', 'Show Filter:');
  define ('TEXT_SHOW_FEEDER', 'Use in Feeder:');
  define ('TEXT_SHOW', 'Yes');
  define ('TEXT_DONT_SHOW', 'No');
  define ('TEXT_FILTER_CLASS', 'Filter Class');
  define ('TEXT_FILTER_DISPLAY', 'Display the Filter as');
  define ('TEXT_FILTER_SHOW_ALL', 'Filter Show All');
  define ('TEXT_ENTER_FILTER', 'Enter Filters as');
  define ('TEXT_ENTER_VALUE', 'Enter Specification Values as');

  define ('TEXT_INFO_HEADING_COPY_GROUP', 'Copy Group');
  define ('TEXT_COPY_GROUP_INTRO', 'Make an exact copy of this group?<br>Copies only the group, unless the following options are checked..');
  define ('TEXT_COPY_QUERY_LINKS', 'Also copy %s category links');
  define ('TEXT_COPY_QUERY_SPECS', 'Also copy %s specifications');
  define ('TEXT_COPY_QUERY_PRODUCTS', 'Also copy %s product specifications');
  define ('TEXT_COPY_QUERY_FILTERS', 'Also copy %s filters');
  define ('TEXT_COPY_QUERY_VALUES', 'Also copy %s specification values');
  define ('TEXT_INFO_CURRENT_GROUP', 'Current Group: ');
  define ('TEXT_GROUPS', 'Select New Group:');
  define ('TEXT_NO_GROUP_SELECT', 'No Groups to Select!');

  define ('TEXT_SPECIFICATIONS', 'Specifications in this Group: ');
  define ('TEXT_FILTERS_GROUP', 'Filters in this Group: ');
  define ('TEXT_FILTERS_SPEC', 'Filters on this Specification: ');
  define ('TEXT_ALL_CATEGORIES', 'Total Specification Groups: ');
  define ('TEXT_ALL_SPECIFICATIONS', 'Total Specifications: ');
  define ('TEXT_ALL_FILTERS', 'Total Filters: ');
  define ('TEXT_LIST_CATEGORIES_LINKED', 'Categories Linked: ');

  define ('TEXT_INFO_HEADING_LINK_CATEGORY', 'Link this Specification Group to a Category');
  define ('TEXT_LINK_CATEGORIES_INTRO', 'Please select the category to link to.');
  define ('TEXT_LINK_TO', 'Category: ');
  define ('TEXT_LINK_TO_SUBCATS', 'Include all Subcategories');

  define ('TEXT_INFO_HEADING_LINK_ALL_CATEGORIES', 'Link to All Categories');
  define ('TEXT_LINK_ALL_INTRO', '<b>Warning!</b> Links all categories at all levels!');

  define ('TEXT_INFO_HEADING_UNLINK_CATEGORY', 'Unlink a Category');
  define ('TEXT_UNLINK_INTRO', 'Please select the category to unlink');
  define ('TEXT_UNLINK_CATEGORY', 'Category: ');
  define ('TEXT_NO_CATEGORIES', 'No categories to unlink!');

  define ('TEXT_INFO_HEADING_UNLINK_ALL_CATEGORIES', 'Unlink all Categories');
  define ('TEXT_UNLINK_ALL_INTRO', '<b>Warning!</b> Unlinks all categories at all levels!');

  define ('TEXT_INFO_HEADING_DELETE_GROUP', 'Delete Group');
  define ('TEXT_DELETE_GROUP_INTRO', 'Do you want to permanently delete this group?');
  define ('TEXT_DELETE_WARNING_SPECS', '<b>Warning!</b> %s specifications will also be deleted!');
  define ('TEXT_DELETE_WARNING_PRODUCTS', '<b>Warning!</b> %s product specs will also be deleted!');
  define ('TEXT_DELETE_WARNING_FILTERS', '<b>Warning!</b> %s filters will also be deleted!');

  define ('TEXT_INFO_HEADING_EMPTY_GROUP', 'There\'s Nothing Here!');
  define ('TEXT_NO_GROUPS', 'Please create one or more Specification Groups.');

  define ('TEXT_INFO_HEADING_EMPTY_SPECIFICATIONS', 'Empty Group');
  define ('TEXT_NO_SPECIFICATIONS', 'Please create one or more Specifications in this Group.');

  if (!defined('TEXT_INFO_HEADING_EDIT_GROUP')) define ('TEXT_INFO_HEADING_EDIT_GROUP', 'Edit Group');
  define ('TEXT_INFO_HEADING_MOVE_GROUP', 'Move Group');

// Specifications
  define ('TEXT_SPECIFICATION', 'Specification');
  define ('TEXT_SPEC_NAME', 'Specification Name: ');
  define ('TEXT_SPEC_DESCRIPTION', 'Specification Description: ');
  define ('TEXT_SPEC_PREFIX', 'Specification Prefix: ');
  define ('TEXT_SPEC_SUFFIX', 'Specification Suffix: ');
  define ('TEXT_SPEC_SORT_ORDER', 'Sort Order: ');
  define ('TEXT_SPEC_COLUMN_NAME', 'Column Name: ');
  define ('TEXT_SPEC_JUSTIFICATION', 'Justification: ');
  define ('TEXT_SPECS_LEGEND', 'Legend');
  define ('TEXT_SPECS_LEGEND_FILTERS', 'Filters Section');
  define ('TEXT_SPECS_LEGEND_VALUES', 'Specification Values Section');

  define ('TEXT_INFO_HEADING_NEW_SPECIFICATION', 'New Specification in &quot;%s&quot;');
  define ('TEXT_NEW_SPECIFICATION_INTRO', 'Please fill out the following information for the new specification in this category.');
  define ('TEXT_SPECIFICATION_NAME', 'Specification Name:');
  define ('TEXT_SPECIFICATION_DESCRIPTION', 'Specification Description:');
  define ('TEXT_SPECIFICATION_PREFIX', 'Specification Prefix:');
  define ('TEXT_SPECIFICATION_SUFFIX', 'Specification Suffix:');
  define ('TEXT_EXISTING_FIELD', 'Use Existing Database Field:');
  define ('TEXT_EXISTING_FIELD_NOTE', '<b>Note:</b> If this field is set, the product data from that field will be used.');

  define ('TEXT_INFO_HEADING_EDIT_SPECIFICATION', 'Edit Specification');
  define ('TEXT_EDIT_INTRO', 'Please make any necessary changes');
  define ('TEXT_EDIT_SORT_ORDER', 'Sort Order');
  define ('TEXT_COLUMN_JUSTIFY', 'Justification');


  define ('TEXT_INFO_HEADING_COPY_SPECIFICATION', 'Copy Specification');
  define ('TEXT_INFO_COPY_SPECIFICATION_INTRO', 'Where do you want to copy the %s Specification?');
  define ('TEXT_COPY_SPECIFICATION_TO', 'Copy the Specification to this Group:');

  define ('TEXT_INFO_HEADING_MOVE_SPECIFICATION', 'Move Specification');
  define ('TEXT_MOVE_SPECIFICATION_INTRO', 'Where do you want to move the %s Specification?');
  define ('TEXT_MOVE_SPECIFICATION_TO', 'Move the Specification to this Group:');
  define ('TEXT_MOVE', 'Move <b>%s</b> to:');

  define ('TEXT_INFO_HEADING_DELETE_SPECIFICATION', 'Delete Specification');
  define ('TEXT_DELETE_SPECIFICATION_INTRO', 'Do you want to permanently delete this Specification?');

// Filters
  define ('TEXT_INFO_HEADING_FILTER', 'Filter Class: ');
  define ('TEXT_FILTER_VALUE', 'Filter: ');
  define ('TEXT_FILTER_SORT_ORDER', 'Filter Sort Order');
  define ('TEXT_INFO_HEADING_EMPTY_FILTERS', 'Empty Filter');
  define ('TEXT_NO_FILTERS', 'You may create one or more Filters in this Specification.');

  define ('TEXT_INFO_HEADING_NEW_FILTER', 'New Filter');
  define ('TEXT_NEW_FILTER_INTRO', 'Please fill out the following information for the new Filter');
  define ('TEXT_NEW_FILTER', 'Filter');

  define ('TEXT_INFO_HEADING_EDIT_FILTER', 'Edit a Filter');
  define ('TEXT_EDIT_FILTER_INTRO', 'Make changes as needed for this Filter');
  define ('TEXT_EDIT_FILTER', 'Filter');

  define ('TEXT_INFO_HEADING_COPY_FILTER', 'Copy a Filter');
  define ('TEXT_COPY_FILTER_INTRO', 'Which Specification do you want to copy this filter to?');
  define ('TEXT_COPY_FILTER_TO', 'Copy Filter <b>%s</b> to: ');

  define ('TEXT_INFO_HEADING_MOVE_FILTER', 'Move a Filter');
  define ('TEXT_MOVE_FILTER_INTRO', 'Where do you want to move this filter?');
  define ('TEXT_MOVE_FILTER_TO', 'Move Filter <b>%s</b> to: ');

  define ('TEXT_INFO_HEADING_DELETE_FILTER', 'Delete a Filter');
  define ('TEXT_DELETE_FILTER_INTRO', 'Do you want to permanently delete this filter?');

// Specification Values
  define ('TEXT_INFO_HEADING_VALUE', 'Specification Value');
  define ('TEXT_SPECIFICATION_VALUE', 'Specification Value: ');
  define ('TEXT_INFO_HEADING_EMPTY_VALUES', 'Empty Specification Value');
  define ('TEXT_NO_VALUES', 'You may create one or more Specification Values in this Specification.');

  define ('TEXT_INFO_HEADING_NEW_VALUE', 'New Specification Value');
  define ('TEXT_NEW_VALUE_INTRO', 'Add a new Specification Value for %s with class %s');
  define ('TEXT_NEW_VALUE', 'Specification Value:');

  define ('TEXT_EDIT_VALUE_INTRO', 'Change a Specification Value for %s with class %s');
  define ('TEXT_EDIT_VALUE', 'Specification Value:');
  if (!defined('TEXT_VALUE_SORT_ORDER')) define ('TEXT_VALUE_SORT_ORDER', 'Specification Value Sort Order:');

  define ('TEXT_INFO_HEADING_DELETE_VALUE', 'Delete a Specification Value');
  define ('TEXT_DELETE_VALUE_INTRO', 'Do you want to permanently delete this Specification Value?');


  define ('TEXT_INFO_HEADING_EDIT_VALUE', 'Specification Value: ');
  define ('TEXT_VALUE', 'Specification Value: ');
  if (!defined('TEXT_VALUE_SORT_ORDER')) define ('TEXT_VALUE_SORT_ORDER', 'Specification Value Sort Order: ');

  define ('TEXT_INFO_HEADING_COPY_VALUE', 'Copy a Specification Value');
  define ('TEXT_COPY_VALUE_INTRO', 'Which Specification do you want to copy this Value to?');
  define ('TEXT_COPY_VALUE_TO', 'Copy Value <b>%s</b> to:');

  define ('TEXT_INFO_HEADING_MOVE_VALUE', 'Move a Specification Value');
  define ('TEXT_MOVE_VALUE_INTRO', 'Which Specification do you want to move this Value to?');
  define ('TEXT_MOVE_VALUE_TO', 'Move Value <b>%s</b> to:');

// General
  define ('IMAGE_LINK', 'Link to a Category');
  define ('IMAGE_LINK_ALL', 'Link to All Categories');
  define ('IMAGE_UNLINK', 'Remove Link to a Category');
  define ('IMAGE_UNLINK_ALL', 'Remove Links to All Categories');
  define ('IMAGE_NEW_FILTER', 'Add a New Filter');
  define ('IMAGE_NEW_VALUE', 'Add a New Value');
  define ('IMAGE_IMPORT_MANUFACTURERS', 'Import All Manufacturers');
  define ('IMAGE_NEW_SPECIFICATION_GROUP', 'Create a New Specification Group');
  define ('IMAGE_NEW_SPECIFICATION', 'Create a New Specification');
  define ('IMAGE_SORT', 'Sort');
  define ('ICON_BLANK', '');

  if (!defined('TEXT_NONE')) define ('TEXT_NONE', 'None');
  define ('TEXT_COMBI', 'Combination');
  define ('TEXT_POPUP', 'Popup Image');
  define ('TEXT_BUY_NOW', 'Buy Now');
  define ('TEXT_PRODUCTS_MODEL', 'Products Model');
  define ('TEXT_PRODUCTS_IMAGE', 'Products Image');
  define ('TEXT_PRODUCTS_PRICE', 'Products Price');
  define ('TEXT_PRODUCTS_WEIGHT', 'Products Weight');
  define ('TEXT_PRODUCTS_MANUFACTURER', 'Manufacturer');
  define ('TEXT_PRODUCTS_NAME', 'Products Name');
  define ('TEXT_LEFT', 'Left');
  define ('TEXT_CENTER', 'Center');
  define ('TEXT_RIGHT', 'Right');
  define ('TEXT_NO_FILTER', 'No Filter');
  define ('TEXT_EXACT', 'Exact');
  define ('TEXT_MULTIPLE', 'Multiple');
  define ('TEXT_RANGE', 'Range');
  define ('TEXT_REVERSE', 'Reverse');
  define ('TEXT_START', 'Start');
  define ('TEXT_PARTIAL', 'Partial');
  define ('TEXT_LIKE', 'Like');
  define ('TEXT_PULLDOWN', 'Pulldown');
  define ('TEXT_RADIO', 'Radio Buttons');
  define ('TEXT_LINKS', 'List of Links');
  define ('TEXT_TEXT_BOX', 'Text Box');
  define ('TEXT_MULTI', 'Multi-Pulldown');
  define ('TEXT_CHECK_BOXES', 'Check Boxes');
  define ('TEXT_IMAGES', 'Images');
  define ('TEXT_MULTI_IMAGE', 'Multi-image');
  define ('TEXT_FINAL_PRICE', 'Final Price');
  if (!defined('TEXT_OPTIONS')) define ('TEXT_OPTIONS', 'Options');


?>