<?php
/*
//----------------------------------------------------------------------------
// Copyright (c) 2006-2007 Asymmetric Software - Innovation & Excellence
// Author: Mark Samios
// http://www.asymmetrics.com
// Total Configuration module for osCommerce Admin
//----------------------------------------------------------------------------
// Script is intended to be used with:
// osCommerce, Open Source E-Commerce Solutions
// http://www.oscommerce.com
// Copyright (c) 2003 osCommerce
//----------------------------------------------------------------------------
// Released under the GNU General Public License
//----------------------------------------------------------------------------
*/
define('HEADING_TITLE', 'Configuración');
define('HEADING_ALL', 'Configuración Total');
define('HEADING_SELECT', 'Seleccionar:');
define('TABLE_HEADING_CONFIGURATION_ID', 'ID');
define('TABLE_HEADING_CONFIGURATION_KEY', 'Key');
define('TABLE_HEADING_CONFIGURATION_TITLE', 'Title');
define('TABLE_HEADING_CONFIGURATION_VALUE', 'Valor');
define('TABLE_HEADING_ACTION', 'Acción');

define('HEADING_CONFIRM', 'Confirmation - Backup your Database before proceeding');

define('TABLE_HEADING_OPTIMIZE', 'Opciones de la tabla de configuración');

define('TEXT_INFO_EDIT_INTRO', 'Por favor, haga los cambios necesarios');
define('TEXT_INFO_DATE_ADDED', 'Date Added:');
define('TEXT_INFO_LAST_MODIFIED', 'Last Modified:');
define('TEXT_INFO_SORT_ORDER', 'Sort Order:');

define('TEXT_INFO_NO_CHANGES', 'Sin cambios');
define('TEXT_INFO_ENABLE', 'Activos');
define('TEXT_INFO_SHOW_ALL', 'Mostrar todos');
define('TEXT_INFO_GROUP', 'Grupo');
define('TEXT_INFO_UNNAMED_GROUP', 'Unamed Group-ID=');

define('TEXT_INFO_OPTIMIZE_SORT', 'Ordenar por ID');
define('TEXT_INFO_OPTIMIZE_DUPLICATES', 'Eliminar duplicados');

define('TEXT_INFO_OPERATION', '<b>Sort by ID:</b> Restructures the configuration table to use the IDs sequentially. <br><b>Remove Duplicates:</b> Removes duplicated keys from the configuration table.');

define('TEXT_INFO_CONFIRM_DUPLICATES', 'The following duplicates will be removed from the configuration table in the database');
define('TEXT_INFO_CONFIRM_CONFIG', 'The Configuration table will be sorted by configuration_id');

define('IMAGE_SUBMIT', 'Submit');

// edit config fields

define('TEXT_INFO_HEADING_HIDDEN_FIELDS', 'Ocultar valores de las opciones de configuración');
define('TEXT_INFO_HEADING_NEW', 'New Configuration Field Values');
define('TEXT_INFO_NEW', 'Enter new configuration field values:');
define('TEXT_INFO_HEADING_EDIT', 'Edit Configuration Field Values');
define('TEXT_INFO_EDIT_GROUP', 'Enter group configuration field values:');
define('TEXT_INFO_EDIT', 'Edit configuration field values:');
define('TEXT_INFO_HEADING_DELETE', 'Delete Configuration Field Values');

define('TEXT_INFO_TITLE', 'Field Title:');
define('TEXT_INFO_KEY', 'Field Key:');

// case new
define('TEXT_CONFIG_TITLE', 'Title:');
define('TEXT_CONFIGURATION_KEY', 'Key:');
define('TEXT_CONFIG_DESCRIPTION', 'Description:');
define('TEXT_CONFIG_VALUE', 'Value:');
define('TEXT_CONFIG_SORT_ORDER', 'Sort Order:');
define('TEXT_CONFIG_USE_FUNCTION', 'use function:');
define('TEXT_CONFIG_SET_FUNCTION', 'set function:');

// config fields
define('TEXT_CONFIGURATION_TITLE', 'configuration_title');
define('TEXT_CONFIGURATION_KEY', 'configuration_key');
define('TEXT_CONFIGURATION_VALUE', 'configuration_value');
define('TEXT_CONFIGURATION_DESCRIPTION', 'configuration_description');
define('TEXT_CONFIGURATION_GROUP_ID', 'configuration_group_id');
define('TEXT_CONFIGURATION_SORT_ORDER', 'sort_order');
define('TEXT_CONFIGURATION_USE_FUNCTION', 'use_function');
define('TEXT_CONFIGURATION_SET_FUNCTION', 'set_function');
define('TEXT_CONFIGURATION_DATE_ADDED', 'date_added');

// config groups
define('TEXT_CONFIGURATION_GROUP_TITLE', 'group title');
define('TEXT_CONFIGURATION_GROUP_DESCRIPTION', 'group description');

define('TEXT_NOT_SET', 'Not Set');

define('TEXT_INFO_DELETE', 'WARNING: You are actually removing<br>&nbsp;Critical Database Fields here. DO NOT<br>&nbsp;CONTINUE unless you are absolutely<br>&nbsp;sure as to what you are doing !!!<br><br>&nbsp;Confirm the deletion of this database<br>&nbsp;configuration field key.');

define('IMAGE_EDIT_GROUP', 'Edit Groups Configuration Field Values');

define('SUCCESS_HIDDEN_FIELD_ADDED', 'Hidden Configuration Value was successfully entered into the Database.');
define('SUCCESS_HIDDEN_FIELD_REMOVED', 'Hidden Configuration Value was successfully removed from the Database.');
define('SUCCESS_HIDDEN_FIELD_UPDATED', 'Hidden Configuration Value was successfully Updated.');
define('SUCCESS_HIDDEN_GROUP_FIELDS_UPDATED', 'Hidden Group Configuration Values have been successfully Updated.');
define('SUCCESS_HIDDEN_FIELDS_ACTIVE', 'Hidden Configuration Values have been successfully Activated.');
define('SUCCESS_HIDDEN_FIELDS_DEACTIVATED', 'Hidden Configuration Values have been successfully Deactivated.');
?>