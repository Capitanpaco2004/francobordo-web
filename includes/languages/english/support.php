<?php
/*
  $Id: support.php,v 1.19 2002/07/21 23:38:57 Puddled Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 Puddled Computer Services
  Contributed by Puddled Computer services
  http://www.puddled.co.uk

  Author David Howarth
  Email dave@puddled.co.uk

  Released under the GNU General Public License
*/
define('TEXT_MAIN_SUPPORT', '<BR><p><span class="greetUser">Instant Help</span></p>
<p>The answer to your question may be in: <a href="' . tep_href_link(FILENAME_LOGIN, '', 'SSL') . '" style="color:#0000ff">FAQs</a> <a href="' . tep_href_link(FILENAME_LOGIN, '', 'SSL') . '" style="color:#0000ff"></a>.</p>

<p><span class="greetUser">Contact us</span></p>
<p>Our customer service team is uniquely qualified to manage its effects in a reasonable time. <p>
<hr size=1>

<p><strong>Registered customers. </strong></p><p>Please <a href="' . tep_href_link(FILENAME_SUPPORT, 'action=new', 'NONSSL') . '" style="color:#0000ff; font-size: 12px; font-weight : bold;">Submit a new question</a> through support tickets <a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, 'view=all', 'NONSSL') . '"  style="color:#0000ff; font-size: 12px; font-weight : bold;"> or update an existing ticket </a>and we will respond promptly. Kindly suggest the inclusion of your question in the FAQ.</p> 
<br/ > <p>We recommend communication through support tickets as this information will be recorded in our systems, whereas a message sent by mail could be lost or rejected by spam filters. The <em>contact</em> form (below) could also be lost or rejected by spam filters</P>

<hr size=1>

<p><strong>Visitors.  </strong></P>
<p>We invite you to send us a message via our contact form. Please 

  <a href="'. tep_href_link('contact_us.php') . '" target="_blank" style="color:#0000ff">Click here to contact us<br></a>


.</p>

<hr size=1>

If you have difficulty understanding the Spanish form, please click here to translate it:<br>
<a href="http://www.google.com/language_tools?hl=en" target="_blank" style="color:#0000ff">http://www.google.com/language_tools?hl=es</a>

');

define( 'SEND_TICKET', 'Send&nbsp;Ticket' );
define( 'OPEN_TICKETS', 'Open&nbsp;Tickets' );
define( 'CLOSE_TICKETS', 'Close&nbsp;Tickets' );
define( 'ALL_TICKETS', 'All&nbsp;Tickets' );
define('HEADING_TITLE', 'Support Tickets');
define('HEADING_TICKET_ID', 'Ticket&nbsp;ID:');
define('HEADING_TICKET_PRIORITY', 'Ticket Priority:');
define('HEADING_TICKET_STATUS', 'Status:');
define('HEADING_TICKET_DEPARTMENT', 'Department:');
define('HEADING_TICKET_ASSIGNED', 'Assigned&nbsp;to:');
define('HEADING_TICKET_SUBJECT', 'Assigned&nbsp;to:');
define('HEADING_TICKET_CUSTOMER', 'Customer:');

define('TABLE_HEADING_COMMENTS', 'Admin Comments');
define('TABLE_HEADING_CUSTOMERS', 'Customers');
define('TABLE_HEADING_COMPANY', 'Company');
define('TABLE_HEADING_DEPARTMENT', 'Department');
define('TABLE_HEADING_STATUS', 'Status');
define('TABLE_HEADING_ACTION', 'Action');
define('TABLE_HEADING_ID', 'ID');

define('TABLE_HEADING_STATUS', 'Status');
define('TABLE_HEADING_OLD_DEPT', 'Old Dept');
define('TABLE_HEADING_NEW_DEPT', 'New Dept');
define('TABLE_HEADING_OLD_ADMIN', 'Old Admin');
define('TABLE_HEADING_NEW_ADMIN', 'New Admin');
define('TABLE_HEADING_SUBJECT', 'Subject');
define('TABLE_HEADING_NEW_VALUE', 'New Value');
define('TABLE_HEADING_OLD_VALUE', 'Old Value');
define('TABLE_HEADING_CUSTOMER_NOTIFIED', 'Customer Notified');
define('TABLE_HEADING_DATE_ADDED', 'Date Added');

define('ENTRY_CUSTOMER', 'Customer:');
define('ENTRY_SOLD_TO', 'SOLD TO:');
define('ENTRY_STREET_ADDRESS', 'Street Address:');
define('ENTRY_SUBURB', 'Suburb:');
define('ENTRY_CITY', 'City:');
define('ENTRY_POST_CODE', 'Post Code:');
define('ENTRY_STATE', 'State:');
define('ENTRY_COUNTRY', 'Country:');
define('ENTRY_TELEPHONE', 'Telephone:');
define('ENTRY_EMAIL_ADDRESS', 'E-Mail Address:');
define('ENTRY_DELIVERY_TO', 'Delivery To:');
define('ENTRY_SHIP_TO', 'SHIP TO:');
define('ENTRY_SUBJECT', 'Subject:');
define('ENTRY_COMPANY_NAME', 'Company name:');
define('ENTRY_PROBLEM_REPORTED', 'Problem reported:');
define('ENTRY_RE_OPEN_TICKET', 'Re-open ticket');
define('ENTRY_SELECT_STATUS', 'Select status');
define('ENTRY_CREDIT_CARD_NUMBER', 'Credit Card Number:');
define('ENTRY_CREDIT_CARD_EXPIRES', 'Credit Card Expires:');
define('ENTRY_SUB_TOTAL', 'Sub-Total:');
define('ENTRY_TAX', 'Tax:');
define('ENTRY_SHIPPING', 'Shipping:');
define('ENTRY_TOTAL', 'Total:');
define('ENTRY_DATE_PURCHASED', 'Date Purchased:');
define('ENTRY_STATUS', 'Status:');
define('ENTRY_DATE_LAST_UPDATED', 'Date Last Updated:');
define('ENTRY_NOTIFY_CUSTOMER', 'Notify Customer:');
define('ENTRY_NOTIFY_COMMENTS', 'Append Comments:');
define('ENTRY_PRINTABLE', 'Print Invoice');
define('ENTRY_TICKET_DATE', 'Ticket submitted on');
define('ENTRY_DEPARTMENT', 'Department');
define('ENTRY_SUPPORTER', 'Assigned to');
define('ENTRY_NOTIFY_CLOSE', 'Close ticket');
define('ENTRY_ORDERS', 'Orders:');
define('TEXT_INFO_HEADING_DELETE_ORDER', 'Delete Ticket');
define('TEXT_INFO_DELETE_INTRO', 'Are you sure you want to delete this ticket?');

define('TEXT_DATE_ORDER_CREATED', 'Date Created:');
define('TEXT_DATE_ORDER_LAST_MODIFIED', 'Last Modified:');
define('TEXT_INFO_PAYMENT_METHOD', 'Ticket Priority:');

define('TEXT_ALL_ORDERS', 'All Tickets');
define('TEXT_NO_ORDER_HISTORY', 'No Ticket History Available');
define('TEXT_NO_COMMENTS_AVAILABLE', 'No comments available');


define('ERROR_ORDER_DOES_NOT_EXIST', 'Error: Ticket does not exist.');
define('SUCCESS_ORDER_UPDATED', 'Success: Ticket has been successfully updated.');
define('WARNING_ORDER_NOT_UPDATED', 'Warning: Nothing to change. The Ticket was not updated.');

define('TEXT_SUPPORT_ADDED', 'Your support request has been added to the database, and is now being investigated');
define('TEXT_SUPPORT_UPDATE', 'The support ticket you submitted, has now been updated');
define('TEXT_SUPPORT_SOLVED', 'Your suppor ticket has now been resolved');
define('TEXT_SUPPORT_ADDED_TO_FAQ', 'The support ticket you submitted has now been added to the faq system');
define('TEXT_SUPPORT_CLOSED', 'This ticket is now closed');

define('TEXT_SUPPORT_DEPT', 'Category');
define('TEXT_SUPPORT_PRIORITY', 'Priority');
define('TEXT_SUPPORT_USER_NAME', 'Your nanem');
define('TEXT_SUPPORT_USER_EMAIL', 'Your email');
define('TEXT_SUPPORT_ORDERS', 'Order(s) #');
define('TEXT_SUPPORT_IF_APPLICABLE', 'If applicable');
define('TEXT_SUPPORT_DOMAIN', 'Reason');
define('TEXT_SUPPORT_TEXT', 'Comments');
define('TEXT_SUPPORT_FAQ', 'Recommended for Frequently Asked Questions(FAQ)');

define('TEXT_NO_PURCHASES', 'No open tickets in your history');
define('TEXT_NO_CLOSED', 'No closed tickets in your history');

?>