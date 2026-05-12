<?
/* support_email for admin use only

*/

/* this defines the email sent to a customer when a ticket has been updated */

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT', 'Ticket Update');
define('EMAIL_TEXT_ORDER_NUMBER', 'TICKET Number:');
define('EMAIL_TEXT_INVOICE_URL', 'Detailed Summary:');
define('EMAIL_TEXT_DATE_ORDERED', 'Date Submitted:');
define('EMAIL_TEXT_STATUS_UPDATE', 'Your ticket has been updated to the following status.' . "\n\n" . 'New status: %s' . "\n\n");
define('EMAIL_TEXT_COMMENTS_UPDATE', 'The comments for your ticket are' . "\n\n%s\n\n");
define('EMAIL_TEXT_ADD_COMMENTS', 'Please feel free to add comments to this ticket if you have any questions' . "\n\n");
define('EMAIL_TEXT_RE_OPEN', 'Please feel free to re-open this ticket if you have any questions' . "\n\n");


/* this defines the email sent to acustomer when a ticket has had the administrator changed */

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT_NEW_ADMIN', 'Ticket Update');
define('EMAIL_TEXT_ORDER_NUMBER_NEW_ADMIN', 'TICKET Number:');
define('EMAIL_TEXT_INVOICE_URL_NEW_ADMIN', 'Detailed Summary:');
define('EMAIL_TEXT_DATE_ORDERED_NEW_ADMIN', 'Date Submitted:');
define('EMAIL_TEXT_STATUS_UPDATE_NEW_ADMIN', 'Your ticket has been assigned to the following Administrator.' . "\n\n" . 'New Administrator: %s' . "\n\n" . 'Please reply to this email if you have any questions.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE_NEW_ADMIN', 'The comments for your ticket are' . "\n\n%s\n\n");

/* this defines the email sent to acustomer when a ticket has been closed */

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT_CLOSED', 'Ticket Update');
define('EMAIL_TEXT_ORDER_NUMBER_CLOSED', 'TICKET Number:');
define('EMAIL_TEXT_INVOICE_URL_CLOSED', 'Detailed Summary:');
define('EMAIL_TEXT_DATE_ORDERED_CLOSED', 'Date Submitted:');
define('EMAIL_TEXT_STATUS_UPDATE_CLOSED', 'Your support ticket has now been Closed' . "\n\n" . 'Please reply to this email if you have any questions.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE_CLOSED', 'The comments for your ticket are' . "\n\n%s\n\n");

/* this defines the email sent to an administrator when a ticket has been assigned to them */

define('EMAIL_SEPARATOR', '------------------------------------------------------');
define('EMAIL_TEXT_SUBJECT_ADMIN', 'New ticket Update');
define('EMAIL_TEXT_ORDER_NUMBER_ADMIN', 'TICKET Number:');
define('EMAIL_TEXT_INVOICE_URL_ADMIN', 'Detailed Summary:');
define('EMAIL_TEXT_DATE_ORDERED_ADMIN', 'Date Submitted:');
define('EMAIL_TEXT_STATUS_UPDATE_ADMIN', 'You have been assigned this ticket.' . "\n\n" . 'Ticket ID: ' . $oID . "\n\n" . 'Please reply to this email if you have any questions.' . "\n");
define('EMAIL_TEXT_COMMENTS_UPDATE_ADMIN', 'The comments for the ticket are' . "\n\n%s\n\n");

