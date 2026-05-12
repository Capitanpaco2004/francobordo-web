<?php
  // Syscheck script - by That Software Guy
 
  // Titles
  define('SYSCHECK_TITLE', 'SysCheck - Scans osCommerce for Vulnerabilities'); 
  define('SYSCHECK_VERSION', 'Version ');
  define('ADMIN_USERS', 'Admin users'); 
  define('DIRCHECK_TITLE', 'Directories with incorrect permissions'); 
  define('NOPHP_TITLE', 'PHP files which shouldn\'t be there'); 
  define('SYSCHECK_REPORT_END', 'End of SysCheck'); 
  define('EVAL_TITLE', 'PHP files which contain unexpected calls to EVAL'); 
  define('FILEMANAGER_TEST_TITLE', 'Checking for file_manager.php'); 
  define('FILECHECK_TITLE', 'Files with incorrect permissions'); 

  // Other strings
  define('NO_ISSUES', 'No issues');
  define('NONE_FOUND', 'None found');
  define('RUN_SYSCHECK', 'Syscheck');
  define('SYSCHECK_BACK', 'Back to Admin'); 

  // Skip messages
  define('SKIP_RUN_WRITABLE_DIRECTORIES_TEST','Skipping writable directories test');
  define('SKIP_RUN_PHP_UNEXPECTED_FILES_TEST','Skipping unexpected PHP file test');
  define('SKIP_RUN_PHP_EVAL_TEST','Skipping php scripts with EVAL calls test'); 
  define('SKIP_RUN_WRITABLE_FILES_TEST','Skipping writable files test'); 
  define('SKIP_ADMIN_USERS_TEST', 'Skipping admin users test'); 
  define('SKIP_FILEMANAGER_TEST', 'Skipping filemanager test'); 
  define('FILEMANAGER_NOT_FOUND', 'You have deleted admin/file_manager.php'); 
  define('FILEMANAGER_FOUND', 'You have NOT deleted admin/file_manager.php.  You may wish to do so to enhance the security of your site.'); 
  define('REASON_NOT_ROOT', 'Reason: SERVER_ADMIN is not root@localhost'); 
?>