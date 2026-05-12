<?php
// defines for support ticket system
if (!defined('BOX_SUPPORT_HEADING')) define('BOX_SUPPORT_HEADING', 'Support System');
if (!defined('BOX_TICKET_STATUS')) define('BOX_TICKET_STATUS', 'Ticket Status');
if (!defined('BOX_TICKET_PRIORITY')) define('BOX_TICKET_PRIORITY', 'Ticket Priority');
if (!defined('BOX_TICKET_ADMINS')) define('BOX_TICKET_ADMINS', 'Ticket Admins');
if (!defined('BOX_TICKET_TICKETS')) define('BOX_TICKET_TICKETS', 'Support Tickets');
if (!defined('BOX_TICKET_DEPARTMENT')) define('BOX_TICKET_DEPARTMENT', 'Support Dept\'s');

define('FILENAME_SUPPORT_EMAIL', 'support_email.php');
define('FILENAME_SUPPORT_TICKETS', 'support.php');
define('FILENAME_SUPPORT_STATUS', 'support_status.php');
define('FILENAME_SUPPORT_DEPARTMENT', 'support_department.php');
define('FILENAME_SUPPORT_PRIORITY', 'support_priority.php');
define('FILENAME_SUPPORT_ADMIN', 'support_admin.php');
define('FILENAME_SUPPORT_ADMIN_EMAIL','support_admin_email');
define('FILENAME_CATALOG_SUPPORT_INFO', 'support_info.php');
if (!defined('TABLE_SUPPORT_TICKETS')) define('TABLE_SUPPORT_TICKETS', 'support_tickets');
if (!defined('TABLE_SUPPORT_DEPARTMENT')) define('TABLE_SUPPORT_DEPARTMENT', 'support_department');
if (!defined('TABLE_SUPPORT_PRIORITY')) define('TABLE_SUPPORT_PRIORITY', 'support_priority');
if (!defined('TABLE_SUPPORT_ADMINS')) define('TABLE_SUPPORT_ADMINS', 'support_assign');
if (!defined('TABLE_SUPPORT_ASSIGN')) define('TABLE_SUPPORT_ASSIGN', 'support_assign');
if (!defined('TABLE_SUPPORT_STATUS')) define('TABLE_SUPPORT_STATUS', 'support_status');
if (!defined('TABLE_SUPPORT_RESPONSE')) define('TABLE_SUPPORT_RESPONSE', 'support_response');
if (!defined('TABLE_SUPPORT_TICKETS_HISTORY')) define('TABLE_SUPPORT_TICKETS_HISTORY', 'support_ticket_history');

define('FILENAME_NEWS', 'news2.php');        //news filename
if (!defined('TABLE_NEWS')) define('TABLE_NEWS', '002_news');       // news-table

if (!defined('TABLE_FAQ')) define('TABLE_FAQ', 'faq');
define('FILENAME_FAQ', 'faq.php');
if (!defined('BOX_HEADING_FAQ')) define('BOX_HEADING_FAQ', 'FAQ manager');
if (!defined('BOX_FAQ_MANAGER')) define('BOX_FAQ_MANAGER', 'FAQ manager');
if (!defined('TITLE')) define('TITLE', 'Administrador');
?>
