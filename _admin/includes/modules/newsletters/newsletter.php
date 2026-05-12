<?php
/*
  $Id: newsletter.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
*/

  class newsletter {
    var $show_choose_audience, $title, $content;

    function __construct($title, $content, $send_to_customer_groups) {

      $this->show_choose_audience = false;
      $this->title = $title;
      $this->content = $content;
      $this->send_to_customer_groups = $send_to_customer_groups;
    }

    function choose_audience() {
      return false;
    }

    function confirm() {
      global $_GET;

      // Cantidad
      $mail_query = tep_db_query( 'select count(*) as count from subscribers where customers_newsletter = 1 and subscribers_blacklist = 0' );
      $mail = tep_db_fetch_array($mail_query);
      $nCount = $mail['count'];
      $mail_query = tep_db_query( 'select count(*) as count from customers where customers_newsletter = 1 and customers_group_id in (' . $this->send_to_customer_groups . ')' );
      $mail = tep_db_fetch_array($mail_query);
      $nCount = $mail['count'] + $nCount;     
      $mail['count'] = $nCount;

      $confirm_string = '<table border="0" cellspacing="0" cellpadding="2">' . "\n" .
                        '  <tr>' . "\n" .
                        '    <td class="main"><font color="#ff0000"><b>' . sprintf(TEXT_COUNT_CUSTOMERS, $mail['count']) . '</b></font></td>' . "\n" .
                        '  </tr>' . "\n" .
                        '  <tr>' . "\n" .
                        '    <td>' . tep_draw_separator('pixel_trans.gif', '1', '10') . '</td>' . "\n" .
                        '  </tr>' . "\n" .
                        '  <tr>' . "\n" .
                        '    <td class="main"><b>' . $this->title . '</b></td>' . "\n" .
                        '  </tr>' . "\n" .
                        '  <tr>' . "\n" .
                        '    <td>' . tep_draw_separator('pixel_trans.gif', '1', '10') . '</td>' . "\n" .
                        '  </tr>' . "\n" .
                        '  <tr>' . "\n" .
                        '    <td class="main"><tt>' . nl2br($this->content) . '</tt></td>' . "\n" .
                        '  </tr>' . "\n" .
                        '  <tr>' . "\n" .
                        '    <td>' . tep_draw_separator('pixel_trans.gif', '1', '10') . '</td>' . "\n" .
                        '  </tr>' . "\n" .
                        '  <tr>' . "\n" .
                        '    <td align="right"><a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID'] . '&action=confirm_send') . '">' . tep_image_button('button_send.gif', IMAGE_SEND) . '</a> <a href="' . tep_href_link(FILENAME_NEWSLETTERS, 'page=' . $_GET['page'] . '&nID=' . $_GET['nID']) . '">' . tep_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a></td>' . "\n" .
                        '  </tr>' . "\n" .
                        '</table>';

      return $confirm_string;
    }

// BOF Separate Pricing Per Customer
    function send($newsletter_id, $send_to_customer_groups)
    {
	$aEmails = array();

	// Consulta customers
	$mail_query = tep_db_query("select customers_firstname as name, customers_lastname as lastname, customers_email_address as email from " . TABLE_CUSTOMERS . " where customers_newsletter = '1' and customers_group_id in (" . $send_to_customer_groups . ")");

	while ($mail = tep_db_fetch_array($mail_query)) 
		$aEmails[$mail['email']] = array( 'name' => $mail['name'], 'lastname' => $mail['lastname'] );

	// Consulta subscribers
	$mail_query = tep_db_query("select subscribers_firstname as name, subscribers_lastname as lastname, subscribers_email_address as mail from subscribers where customers_newsletter = '1' and subscribers_blacklist = '0' ");
	
	while ($mail = tep_db_fetch_array($mail_query)) 
	{
		if( isset( $aEmails[$mail['mail']] ) )
			continue;
		
		$aEmails[$mail['mail']] = array( 'name' => $mail['name'], 'lastname' => $mail['lastname'] );
	}

      $mimemessage = new email(array('X-Mailer: osCommerce bulk mailer'));
	  if (EMAIL_USE_HTML == 'true') {//Send html email
		$mimemessage->add_html($this->content);
	  }else{//Send text email
		$mimemessage->add_text($this->content);
	  }

      $mimemessage->build_message();
	
	foreach( $aEmails as $key => $value )
	{
		 $mimemessage->send($value['name'] . ' ' . $value['lastname'], $key, '', EMAIL_FROM, $this->title);
	}

      $newsletter_id = tep_db_prepare_input($newsletter_id);
      tep_db_query("update " . TABLE_NEWSLETTERS . " set date_sent = now(), status = '1' where newsletters_id = '" . tep_db_input($newsletter_id) . "'");
    }
  }
?>
