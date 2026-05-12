<?php
/*
  $Id: auto_backup_db.php, v 3.0 24/09/2009 13:00:00 

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/
	
// initialise dBase etc if cron job	
if(!defined('DB_DATABASE')) { 
			$cron = true;
			require( dirname(__FILE__) . '/../../includes/define.php' );
			require( dirname(__FILE__) . '/../../includes/configure.php' );
			require('../' . DIR_WS_FUNCTIONS . 'database.php');
      tep_db_connect() or die('Unable to connect to database server!');
			require('../' . DIR_WS_FUNCTIONS . 'general.php');
			require('database_tables.php');
			include('../' . DIR_WS_LANGUAGES . 'english.php');
			$configuration_query = tep_db_query('select configuration_key as cfgKey, configuration_value as cfgValue from ' . TABLE_CONFIGURATION);
  		while ($configuration = tep_db_fetch_array($configuration_query)) {
   	  define($configuration['cfgKey'], $configuration['cfgValue']);
																																	  }
															}

include(($cron ? '../' : '') . DIR_WS_LANGUAGES . 'english/backup.php');
// set vars for php4
$at_backup_db = NULL;$saving = NULL;$zip = NULL;$gzip = NULL;$at_total = 0; 
// check if the backup directory exists
$at_dir_ok = false;
if (is_dir(DIR_FS_BACKUP)) {
	if (is_writeable(DIR_FS_BACKUP)) {
		$at_dir_ok = true;
	} else {
	  if ($cron) { echo ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE; } else {
	  $messageStack->add_session('', 'none');
		$messageStack->add(ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE, 'error');
	} }
} else {
		if ($cron) { echo ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST; } else {
  $messageStack->add_session('', 'none');
	$messageStack->add(ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST, 'error');
} }
// comment out if you cant get library installed
if (BACKUP_ZIP == 'true') {
require_once('PEAR.php');
include ('Archive/Zip.php'); 
}
// comment out if you cant get library installed

//create sort func
function Comp($a, $b)
{
	return (strstr($a, DB_DATABASE) < strstr($b, DB_DATABASE)) ? -1 : 1;
}
// time
function microtime_float() {
    list($usec, $sec) = explode(" ",microtime());
    return ((float)$usec + (float)$sec);
}
//format
function str_format_number($String, $Format){
    if ($Format == '') return $String;
    if ($String == '') return $String;

    $Result = '';
    $FormatPos = 0;
    $StringPos = 0;
    While ((strlen($Format) - 1) >= $FormatPos){
        //If its a number => stores it
        if (is_numeric(substr($Format, $FormatPos, 1))){
            $Result .= substr($String, $StringPos, 1);
            $StringPos++;
        //If it is not a number => stores the caracter
        } Else {
            $Result .= substr($Format, $FormatPos, 1);
        }
        //Next caracter at the mask.
        $FormatPos++;
    }

    return $Result;
}

if ($at_dir_ok == true) {
	$at_dir = dir(DIR_FS_BACKUP);
	$at_contents = array();
	while ($at_file = $at_dir->read()) {
		if (!is_dir(DIR_FS_BACKUP . $at_file)) {
			$at_contents[]=$at_file;
		}
	}
	if (sizeof($at_contents)) {
	usort($at_contents, 'Comp');
	$at_total = sizeof($at_contents)-1;
	$at_entry = $at_contents[$at_total];
	$at_last = ($at_total ? $at_contents[$at_total-1] : 0);
	$at_ref_entry = $at_entry;
	  $at_entry = strstr($at_entry, DB_DATABASE);
	 	$at_entry = eregi_replace(DB_DATABASE . '[_-]', '', $at_entry);
		$at_entry = eregi_replace('[.a-z]', '', $at_entry);
		$at_last = strstr($at_last, DB_DATABASE);
		$at_last = eregi_replace(DB_DATABASE . '[_-]', '', $at_last);
		$at_last = eregi_replace('[.a-z]', '', $at_last);
		$at_current_date = strtotime(date('Y-m-d H:i'));
		$at_entry =($at_entry ? $at_entry : '200001010000');
		$at_last =($at_last ? $at_last : '200001010000');
		$format = '0000-00-00 00:00';
		$at_entry = str_format_number($at_entry, $format);
    $at_last = str_format_number($at_last, $format);
		$at_dif_date = ($at_current_date - strtotime($at_entry))/60;
		$at_last_date = ($at_current_date - strtotime($at_last))/60;
		$at_dir->close();

//Autobackup DB Calculo de hora FIN

//$messageStack->add_session('', 'none');
//$messageStack->add_session('Last backup ' . $at_dif_date . ' Minutes Ago, Previous backup ' . $at_last_date . ' Minutes Ago, Last file date read as ' . date("d-m-Y H:i", strtotime($at_entry)), 'success'); 
if($at_dif_date>BACKUP_INTERVAL) {
	$at_backup_db = 'at_backupnow';$hr_dif = (int)($at_dif_date/60);$mn_dif = $at_dif_date-($hr_dif*60);
	$saving = 'ATTENTION: Making a Auto-Backup of your Database ... ( ' . ($hr_dif ? $hr_dif . ' Hrs ' : '') . $mn_dif  . ' minutes since last backup ).';
	if ($at_dif_date<(BACKUP_SAVE_INTERVAL * 60) && $at_last_date <(BACKUP_SAVE_INTERVAL * 60)) {$filename = DIR_FS_BACKUP . $at_ref_entry; 
			if ($at_ref_entry <> '') {
			unlink($filename);
	    $messageStack->add('', 'none');
	    $messageStack->add('Deleted expired backup from ' . $filename, 'warning'); 
			     }
			}
}

} else { $at_backup_db = 'at_backupnow';$saving = 'ATTENTION: Making the first Auto-Backup of your Database.';} 
 } 

if (tep_not_null($at_backup_db)) {
  if ($cron) { echo date(PHP_DATE_TIME_FORMAT) . ' ' . $saving . " \n\n"; } else {
  $messageStack->add('', 'none');
	$messageStack->add($saving, 'warning');
	} 
	switch ($at_backup_db) {
		case 'at_backupnow':
		tep_set_time_limit(0);$startSave = (float)microtime_float();
		$at_backup_file = 'db_' . DB_DATABASE . '_' . date('YmdHi') . '.sql';//BACKUP SAVE INTERVAL  BACKUP_INTERVAL
		if ($at_fp = fopen(DIR_FS_BACKUP . $at_backup_file, 'w')) {
		
		$tables_query = tep_db_query('show tables');
      while ($tables_result = tep_db_fetch_array($tables_query)) {
        foreach ($tables_result as $table_results_name) {
        $tables.= $table_results_name . ', ' ;
        }
		} $tables = substr_replace($tables, '', -2, 2);
		$at_schema = '# osCommerce, Open Source E-Commerce Solutions' . "\n" .
    '# http://www.oscommerce.com' . "\n" .
    '#' . "\n" .
		'# Database Backup For ' . STORE_NAME . "\n" .
		'# Copyright (c) ' . date('Y') . ' ' . STORE_OWNER . "\n" .
		'#' . "\n" .
		'# Database: ' . DB_DATABASE . "\n" .
		'# Database Server: ' . DB_SERVER . "\n" .
		'#' . "\n" .
		'# Backup Date: ' . date(PHP_DATE_TIME_FORMAT) . "\n" .
		'# Backed up tables: ' . $tables . "\n\n";
		fputs($at_fp, $at_schema);
		$at_tables_query = tep_db_query('show tables');
		while ($at_tables = tep_db_fetch_array($at_tables_query)) {
			$at_table = reset($at_tables);
			$at_schema = 'drop table if exists ' . $at_table . ';' . "\n" . 'create table ' . $at_table . ' (' . "\n";
			$at_table_list = array();
			$at_fields_query = tep_db_query("show fields from " . $at_table);
			while ($at_fields = tep_db_fetch_array($at_fields_query)) {
				$at_table_list[] = $at_fields['Field'];
				$at_schema .= '  ' . $at_fields['Field'] . ' ' . $at_fields['Type'];
				if (strlen($at_fields['Default']) > 0) $at_schema .= ' default \'' . $at_fields['Default'] . '\'';
				if ($at_fields['Null'] != 'YES') $at_schema .= ' not null';
				if (isset($at_fields['Extra'])) $at_schema .= ' ' . $at_fields['Extra'];
				$at_schema .= ',' . "\n";
			}
			$at_schema = ereg_replace(",\n$", '', $at_schema);
			// add the keys
			$at_index = array();
			$at_keys_query = tep_db_query("show keys from " . $at_table);
			while ($at_keys = tep_db_fetch_array($at_keys_query)) {
				$at_kname = $at_keys['Key_name'];
				if (!isset($at_index[$at_kname])) {
					$at_index[$at_kname] = array('unique' => !$at_keys['Non_unique'], 'columns' => array());
				}
				$at_index[$at_kname]['columns'][] = $at_keys['Column_name'];
			}
			foreach ($at_index as $at_kname => $at_info) {
				$at_schema .= ',' . "\n";
				$at_columns = implode($at_info['columns'], ', ');
				if ($at_kname == 'PRIMARY') {
					$at_schema .= '  PRIMARY KEY (' . $at_columns . ')';
				} elseif ($at_info['unique']) {
					$at_schema .= '  UNIQUE ' . $at_kname . ' (' . $at_columns . ')';
				} else {
					$at_schema .= '  KEY ' . $at_kname . ' (' . $at_columns . ')';
				}
			}
			$at_schema .= "\n" . ');' . "\n\n";
			fputs($at_fp, $at_schema);
			// dump the data
			$at_rows_query = tep_db_query("select " . implode(',', $at_table_list) . " from " . $at_table);
			while ($at_rows = tep_db_fetch_array($at_rows_query)) {
				$at_schema = 'insert into ' . $at_table . ' (' . implode(', ', $at_table_list) . ') values (';
				foreach ($at_table_list as $at_i) {
					if (!isset($at_rows[$at_i])) {
						$at_schema .= 'NULL, ';
					} elseif (tep_not_null($at_rows[$at_i])) {
						$at_row = addslashes($at_rows[$at_i]);
						$at_row = ereg_replace("\n#", "\n".'\#', $at_row);
						$at_schema .= '\'' . $at_row . '\', ';
					} else {
						$at_schema .= '\'\', ';
					}
				}
				$at_schema = ereg_replace(', $', '', $at_schema) . ');' . "\n";
				fputs($at_fp, $at_schema);
			}
		}
		fclose($at_fp);$endSave = (float)microtime_float();$backuptime = round($endSave - $startSave, 2);
		} else { 
		if ($cron) { echo 'Error opening backup file for write,  please check backup directory permissions.'; } else {
	  $messageStack->add('', 'none');
	  $messageStack->add('Error opening backup file for write,  please check backup directory permissions.', 'warning');
     } }
		break;
	}
	if(file_exists(DIR_FS_BACKUP.$at_backup_file)){
	  if (BACKUP_ZIP == 'gzip') bu_gzip (DIR_FS_BACKUP, $at_backup_file, true);
	  if (method_exists('Archive_Zip','create')) {
		$archive = New Archive_Zip(DIR_FS_BACKUP.$at_backup_file.'.zip');
		$archive->create(DIR_FS_BACKUP.$at_backup_file); }
		if(file_exists(DIR_FS_BACKUP.$at_backup_file.'.zip')) { unlink(DIR_FS_BACKUP.$at_backup_file); $zip=true; $at_backup_file.='.zip';}
		if(file_exists(DIR_FS_BACKUP.$at_backup_file.'.gz')) { $gzip=true; $at_backup_file.='.gz';}
		$at_backup_db="ok";$ziptime= round((float)microtime_float() - $endSave, 2);
	}
}
if($at_backup_db=="ok"){
 $database_saved =' Database Backup completed in ' . $backuptime . ' secs' . ($zip ? ' and zipped in ' . $ziptime .' secs.' : ($gzip ? ' and gzipped in ' . $ziptime .' secs.' : '.'));
	if ($cron) { echo $database_saved; } else {
	$messageStack->add_session('', 'none');
	$messageStack->add_session($database_saved.': <a href="backups/'.$at_backup_file.'" target="_blank"><blink><b>Check SQL</b></blink></a>', 'success');
} }  elseif ($cron) { echo date(PHP_DATE_TIME_FORMAT) . ' No chron job backup made, last backup made less than ' . BACKUP_SAVE_INTERVAL . ' mins ago.'; }
//AUTOBACKUP DB FIN

?>