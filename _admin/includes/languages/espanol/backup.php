<?php

define('HEADING_TITLE', 'Copia de seguridad - Base de Datos');

define('TABLE_HEADING_TITLE', 'Titulo');
define('TABLE_HEADING_FILE_DATE', 'Fecha');
define('TABLE_HEADING_FILE_SIZE', 'Tamaño');
define('TABLE_HEADING_ACTION', 'Acción');
// BOF new defines
define('TABLE_HEADING_TABLE_NAME', 'Nombre');
define('TABLE_HEADING_TIME_USED', 'Tiempo usado (s)');
// EOF new defines

define('TEXT_INFO_HEADING_NEW_BACKUP', 'Nuevo Backup');
define('TEXT_INFO_HEADING_RESTORE_LOCAL', 'Restaurar desde archivo Local');
define('TEXT_INFO_NEW_BACKUP', 'No interrumpa el proceso de copia, que puede durar unos minutos.');
define('TEXT_INFO_UNPACK', '<br><br>(despues de descomprimir el archivo)');
define('TEXT_INFO_RESTORE', 'No interrumpa el proceso de restauraci&oacute;n.<br><br>Cuanto mas grande sea la copia de seguridad, mas tardar&aacute; este proceso!<br><br>Si es posible, use el cliente de mysql.<br><br>Por ejemplo:<br><br><b>mysql -h' . DB_SERVER . ' -u' . DB_SERVER_USERNAME . ' -p ' . DB_DATABASE . ' < %s </b> %s');
define('TEXT_INFO_RESTORE_LOCAL', 'No interrumpa el proceso de restauraci&oacute;n.<br><br>Cuanto mas grande sea la copia de seguridad, mas tardar&aacute; este proceso!');
define('TEXT_INFO_DATE', 'Fecha:');
define('TEXT_INFO_SIZE', 'Tamaño:');
define('TEXT_INFO_COMPRESSION', 'Compresión:');
define('TEXT_INFO_USE_GZIP', 'Usar GZIP');
define('TEXT_INFO_USE_ZIP', 'Usar ZIP');
define('TEXT_INFO_USE_NO_COMPRESSION', 'Sin Compresión (Puro SQL)');
define('TEXT_INFO_DOWNLOAD_ONLY', 'Descargar solo (no guardar en el servidor)');
define('TEXT_INFO_BEST_THROUGH_HTTPS', '');
define('TEXT_DELETE_INTRO', '¿Estás seguro de que quieres eliminar este backup?');
define('TEXT_INFO_TABLE_STRUCTURE_ONLY', 'Solo la estructura de tabla va a generars een tu backup, no el contenido en datos de estas tablas.');
define('TEXT_NO_EXTENSION', 'Ninguna');
define('TEXT_BACKUP_DIRECTORY', 'Directorio Backup:');
define('TEXT_LAST_RESTORATION', 'Ultima Restauración:');
define('TEXT_FORGET', '(<u>olvidar</u>)');
// BOF new defines
define('TEXT_INFO_RESTORE_LOCAL_FILE', 'The file uploaded must be an sql file, either raw (.sql) or  compressed [gzipped (.gz) or zipped (.zip)] provided the server is capable of decompressing them.');
define('TEXT_INFO_MAX_FILE_SIZE_FOR_UPLOAD', 'The maximum size of an uploaded file that will be accepted by the server is ');
define('TEXT_INFO_EMPTY_SESSIONS_WHOSONLINE', '¿Deseas vaciar las tablas de sessiones y clientes conectados? (aconsejable cuando se realiza una restauración completa)');
define('TEXT_CONTINUE_BACKUP_IN_X_SECONDS', 'Backup will continue in <span id="countdown"></span> seconds.');
define('TEXT_CONTINUE_RESTORE_IN_X_SECONDS', 'La restauración del backup continuará en <span id="countdown"></span> segundos.');
define('TEXT_REFRESH_IN_X_SECONDS', 'Next screen in maximum <span id="countdown2"></span> seconds (approximately)');
define('TEXT_PROGRESS_OF_RESTORE', 'Progeso de la restauración: %s%% del fichero leido.');
define('TEXT_TIME_NEEDED_FOR_RESTORE', 'Tiempo total de la restauración: %s (s)');
// EOF new defines

define('ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST', 'Error: No existe el directorio de copias de seguridad.');
define('ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE', 'Error: No hay permiso de escritura en el directorio de copias de seguridad.');
define('ERROR_DOWNLOAD_LINK_NOT_ACCEPTABLE', 'Error: No se aceptan enlaces.');


define('SUCCESS_LAST_RESTORE_CLEARED', 'Éxito: La fecha de ultima restauraci&oacute;n ha sido borrada.');
define('SUCCESS_DATABASE_SAVED', 'Éxito: Se ha guardado la base de datos.');
define('SUCCESS_DATABASE_RESTORED', 'Éxito: Se ha restaurado la base de datos.');
define('SUCCESS_BACKUP_DELETED', 'Éxito: Se ha eliminado la copia de seguridad.');

// BOF new defines
define('SUCCESS_LOG_DELETED', 'Éxito: El log del archivo ha sido eliminado.');
define('SELECT_DESELECT_ALL', '<b>Seleccione/Deseleccione todas las tablas</b>');
define('SORT_BY_NAME', 'Sort by name ascending   --> A-B-C From Top ');
define('SORT_BY_NAME_DESC', 'Sort by name descending  --> Z-X-Y From Top ');
define('SORT_BY_DATE', 'Sort by date ascending  --> Old files first ');
define('SORT_BY_DATE_DESC', 'Sort by date descending  --> Newest files first ');
define('SORT_BY_SIZE', 'Sort by size ascending  --> Large to small ');
define('SORT_BY_SIZE_DESC', 'Sort by size descending  --> Small to large ');
define('TEXT_INFO_NO_INFORMATION', 'No hay información disponible');
define('SUCCESS_GZIP_COMPRESS', 'Éxito: Archivo %s comprimido con éxito');
define('SUCCESS_ZIP_COMPRESS', 'Éxito: Archivo %s  comprimido con éxito');
define('SUCCESS_GUNZIP', 'Éxito: Archivo %s gunziped con éxito');
define('SUCCESS_UNZIP', 'Success: Archivo %s succesfully  con éxito');
define('SUCCESS_EMAIL', 'Backup %s enviado por email OK');
define('EMAIL_MESSAGE', 'Please find attached your database backup file: %s Created with Auto Backup or Database Backup Manager.%s Many hours are spent creating contributions, please do consider making a donation to support and enable continued development.');
define('ERROR_BACKUP_NO_TABLES_SELECTED', 'Error: no tablas selected for backup');
define('ERROR_RESTORE_NO_TABLES_SELECTED', 'Error: no tablas selected for restore');
define('ERROR_FILE_DOES_NOT_EXIST', 'Error: File %s does not exist.');
define('ERROR_FILE_CANNOT_BE_MOVED', 'Error: Uploaded file %s cannot be moved.');
define('ERROR_FILE_EXTENSION_NOT_SQL_GZ_ZIP', 'Error: You may only upload files with the extensions sql, gz, or zip.');
define('ERROR_PROBLEM_WITH_RESTORE_FILE', 'Error: File %s does not exist or is not set.');
define('ERROR_FILE_CANNOT_BE_OPENED_FOR_READING', 'Error: Backup file %s cannot be opened for reading.');
define('ERROR_FILE_SEEKING_FILESIZE', 'Error: Problem seeking end of file %s to get file size.');
define('ERROR_FILEOFFSET_LARGER_THAN_FILESIZE', 'Error: File offset given for %s is larger than file size.');
define('ERROR_CANNOT_READ_FILE_POINTER', 'Error: File pointer for getting file offset cannot be read.');
define('ERROR_FILE_ALREADY_EXISTS', 'Error: File %s already exists.');
define('ERROR_GZIP_FILE_NOT_VALID', 'Error: File %s does not appear to be a gzip file.');
define('ERROR_ZIP_FILE_NOT_VALID', 'Error: File %s does not appear to be a zip file.');
define('ERROR_NO_GZIP_AVAILABLE', 'Error: Gzip compression both through exec and PHP is not available');
define('ERROR_NO_GUNZIP_AVAILABLE', 'Error: Gunzip both through exec and PHP is not available');
define('ERROR_NO_ZIP_AVAILABLE', 'Error: ZIP compression (through exec) is not available');
define('ERROR_NO_UNZIP_AVAILABLE', 'Error: UNZIP decompression (through exec) is not available');
define('ERROR_COMPRESSED_FILE_ALREADY_EXISTS', 'Error: A compressed file %s already exists.');
define('ERROR_UNCOMPRESSED_FILE_ALREADY_EXISTS', 'Error: An uncompressed file %s already exists.');
define('TEXT_INFO_TABLES_IN_BACKUP', '<br />' . "\n" .'<b>Tablas en este backup:</b>' . "\n");
define('ERROR_FILE_NOT_REMOVEABLE', 'Error: I can not remove this file. Please set the right user permissions on: %s'); // general osC bug; only in language file of filemanager
define('ERROR_ON_GZIP', 'Error: GZIPing the database file was not successful');
define('ERROR_ON_GUNZIP', 'Error: GUNZIPing the database file was not successful');
define('ERROR_BACKUP_NO_BACKUP_FILE', 'Error: no backup file found');
define('ERROR_DATABASE_RESTORE_QUERY','Error: a database error was encountered during a query');
define('ERROR_EMAIL_PARAMS', 'Emailing Backup failure, authentication/address parameters missing.');
define('ERROR_EMAIL_FAILED','Errors occurred trying to send backup %s by email.');
define('ERROR_EMAIL_SIZE','Emailing Backup Error, the file size of %s is beyond the mail server limit.');
define('ERROR_EMAIL_MEMORY','Emailing Backup Error, Need %s memory, but only %s available.');
define('ERROR_EMAIL_AUTH_PARAM','Emailing Backup failure, authentication/address parameters missing.');
define('ERROR_EMAIL_ADD_PARAM','Emailing Backup failure, address parameters missing.');
if (!defined('ERROR_BACKUP_NO_BACKUP_FILE')) define('ERROR_BACKUP_NO_BACKUP_FILE', 'Error: no backup file found');
define('WARNING_PEAR', 'Atención, esta copia de seguridad no se puede enviar por e-mail ya que no esta configurada la opción de envío por e-mail.');
define('WARNING_BACKUP_TABLE_UNDERWAY', 'Progreso de la tabla %s.');
define('IMAGE_GZIP', 'GZip Compress');
define('IMAGE_GUNZIP', 'Descomprimir');
define('IMAGE_SUBMIT', 'Enviar');
define('IMAGE_ZIP', 'Zip');
define('IMAGE_UNZIP', 'Unzip');
// see http://www.php.net/manual/en/features.file-upload.errors.php
define('PHP_FILE_UPLOAD_ERROR_0', 'There is no error, the file uploaded with success.'); // UPLOAD_ERR_OK
define('PHP_FILE_UPLOAD_ERROR_1', 'The uploaded file exceeds the upload_max_filesize directive in php.ini.'); // UPLOAD_ERR_INI_SIZE
define('PHP_FILE_UPLOAD_ERROR_2', 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.'); // UPLOAD_ERR_FORM_SIZE
define('PHP_FILE_UPLOAD_ERROR_3', 'The uploaded file was only partially uploaded.'); // UPLOAD_ERR_PARTIAL
define('PHP_FILE_UPLOAD_ERROR_4', 'No file was uploaded.');  // UPLOAD_ERR_NO_FILE
// there is no error 5 in case you wondered why this is missing
define('PHP_FILE_UPLOAD_ERROR_6', 'Missing a temporary folder.'); //  UPLOAD_ERR_NO_TMP_DIR
define('PHP_FILE_UPLOAD_ERROR_7', 'Failed to write file to disk.'); // UPLOAD_ERR_CANT_WRITE
define('PHP_FILE_UPLOAD_ERROR_8', 'File upload stopped by extension.'); // UPLOAD_ERR_EXTENSION
define('PHP_FILE_UPLOAD_ERROR_UNKNOWN', 'Unknown error uploading file.');
?>