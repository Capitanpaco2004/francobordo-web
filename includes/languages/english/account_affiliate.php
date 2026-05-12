<?php

/**
 * #XCC-313-91043
 */

define('NAVBAR_TITLE_1', 'Account');
define('NAVBAR_TITLE_2', 'My affiliate data');
define('NAVBAR_TITLE_3', 'Edit my information');
define('HEADING_VALUE_COMISSION', 'Accumulated');
define('HEADING_COMISSION', 'Commission');
define('HEADING_COMISSION_STATUS', 'Condition');
define('HEADING_COUPON', 'Coupon:');
define('HEADING_BIO', 'Bio:');
define('HEADING_BIO_IMAGE', 'Image:');
define('HEADING_URL', 'Affiliate url:');
define('HEADING_DETAILS', 'Details');
define('HEADING_DATE', 'Date order');
define('HEADING_DAYS_LEFT', 'Order verification days');

define('HEADING_HISTORY_ID', 'ID');
define('HEADING_HISTORY_TOTAL', 'Total');
define('HEADING_HISTORY_STATUS', 'Status');
define('HEADING_HISTORY_DATE', 'Application date');
define('HEADING_HISTORY', 'Payment history');

define('TEXT_BOTTOM', '
<div class="info-invoices">
<i class="fas fa-info-circle"></i>
<ul>
	<li>The invoice must be sent to the email  <strong>%s</strong></li>
	<li>The subject of the email of the invoice after "Withdraw funds" must contain the ID of the withdrawal Example: 0001 + Date of the withdrawal</li>
	<li><strong>Important.</strong> The minimum amount to withdraw is <strong>%s</strong> so if you need a lower transfer you should contact ' . STORE_OWNER_EMAIL_ADDRESS . ' to manage the withdrawal by our administrators/li>
</ul>
<p style="margin-top: 10px;"><a href="'.tep_href_link('information.php', 'info_id=' . AFFILLIATES_INFO_ID).'" target="_blank" style="color: #2faded; text-decoration: underline;">More information</a></p>
</div>');
