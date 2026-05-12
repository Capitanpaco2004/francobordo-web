<?php
/*
  $Id: faq.php v.0.1 26.05.2002 
  povered by Adgrafics-Ukraine http://adgrafics.net 
  victor@zolochevsky.com

  The Exchange Project - Community Made Shopping!
  http://www.theexchangeproject.org 

  Copyright (c) 2000,2001 The Exchange Project

  Released under the GNU General Public License
*/
// BOF Separate Pricing Per Customer
  if (isset($_SESSION['sppc_customer_group_id']) && $_SESSION['sppc_customer_group_id'] != '0') {
  $customer_group_id = $_SESSION['sppc_customer_group_id'];
  } else {
   $customer_group_id = '0';
  }
// EOF Separate Pricing Per Customer

  require('includes/application_top.php');
  
if (!tep_session_is_registered('customer_id')) {
  if (isset ($_GET['action']) or isset ($_GET['view'])){
    $navigation->set_snapshot();
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }
} else {
	if (!$_GET['action']){
  	
  	if (!$_GET['view']){
global $customer_id; 
$customer_query = tep_db_query("select customers_email_address, customers_firstname, customers_lastname from " . TABLE_CUSTOMERS . " where customers_id =  '" . $customer_id . "'");
$customer_info = tep_db_fetch_array($customer_query);
$customer_email = $customer_info['customers_email_address'];
$customer_name = $customer_info['customers_firstname'] . ' ' . $customer_info['customers_lastname'];

//echo "name = $customer_name email=$customer_email";
//echo "<br>select customers_email_address, customers_firstname, customers_lastname from " . TABLE_CUSTOMERS . " where customers_id =  '" . $customer_id . "'";
//exit;
  	}
  }
}



if (!$_GET['action']){
  	
  	if (!$_GET['view']){
  		$_GET['action'] = 'main';
  	}
  	else{
      $_GET['action'] = 'show_tickets';
    }
  }
if ($_GET['action']) {
    switch ($_GET['action']) {
    case 'show_tickets':
    break;

    case 'edit_ticket':
          $ticket_details = tep_db_query("select * from " . TABLE_SUPPORT_TICKETS . " where ticket_id = '" . $_GET['ticket_id'] . "' and customers_id = '" . $customer_id . "'");
          $ticket = tep_db_fetch_array($ticket_details);

          $ticket_history = tep_db_query("SELECT * FROM " . TABLE_TICKET_HISTORY . " where ticket_id = '" .$_GET['ticket_id'] . "'");
          $history = tep_db_fetch_array($ticket_history);

}
}

  require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_SUPPORT);
  $breadcrumb->add(NAVBAR_TITLE, tep_href_link(FILENAME_SUPPORT, '', 'NONSSL'));
require(DIR_THEME. 'html/header.php');
require(DIR_THEME. 'html/column_left.php');
?>

<table border="0" width="100%" cellspacing="3" cellpadding="3">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top">
    <table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
        <tr>
            <td class="main"><?php   include(DIR_WS_MODULES . 'support_menu.php'); ?>	</td>
        </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '100%', '10'); ?></td>
      </tr>
      <tr>
        <td><br><table border="0" width="100%" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main">

<table border="0" width="100%" cellspacing="1" cellpadding="3" bgcolor="#336699"><tr bgcolor="#FFFFFF"><td  class="main">
<ol>
<?php 

//mysql_connect($server = DB_SERVER, $username = DB_SERVER_USERNAME, $password = DB_SERVER_PASSWORD) or die("Unable to connect to SQL server");


while ($faq=faq_toc()) {
?>
<li><?php echo $faq[toc];?></li>

<?php }
?>
</ol>
</td></tr></table>
<hr size="1" color="#336699">
<!-- answers -->
<?php while ($faq=read_faq()) {
new infoBox(array(array('text' => '<b><a name=' . $faq[faq_id] .'>'.$faq[question].'</a></b><br><br>'.$faq[answer])));
echo "&nbsp;\n";
}
?>
<!-- end answers -->


</td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td align="right" class="main"><br><?php
        if (tep_session_is_registered('customer_id')) {
             echo '<a href="' . tep_href_link(FILENAME_SUPPORT_TRACK, '', 'NONSSL') . '">' . tep_image_button('button_continue.gif', IMAGE_BUTTON_CONTINUE) . '</a></td>';
        } else {
            echo '<a href="' . tep_href_link(FILENAME_DEFAULT, '', 'NONSSL') . '">' . tep_image_button('button_continue.gif', IMAGE_BUTTON_CONTINUE) . '</a></td>';
        }
        ?>
      </tr>
    </table></td>
  </tr>
</table>

<?php
	require( DIR_THEME. 'html/column_right.php' );
	require( DIR_THEME. 'html/footer.php' );
	require( DIR_WS_INCLUDES . 'application_bottom.php' );
?>
