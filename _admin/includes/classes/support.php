<?php
/*
  $Id: support.php,v 1.1 2003/02/03 20:06:53 puddled Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 Puddled Computer Services
  Contributed by Puddled Computer services
  http://www.puddled.co.uk

  Author David Howarth
  Email dave@puddled.co.uk

  Released under the GNU General Public License
*/

  class order {
    var $info, $products, $customer, $delivery;
    public $totals;

    function __construct($ticket_id) {
      $this->info = array();
      $this->totals = array();
      $this->products = array();
      $this->customer = array();
      $this->delivery = array();

      $this->query($ticket_id);
    }

    function query($ticket_id) {
//      $order_query = tep_db_query("select customers_id, customers_name, customers_email_address, customers_company, customers_orders, customers_domain, ticket_date, ticket_comments, department_id, ticket_status, priority_id, admin_id, admin_comments, last_modified FROM " . TABLE_SUPPORT_TICKETS . " where ticket_id = '" . tep_db_input($ticket_id) . "'");
      
      
      
      $order_query = tep_db_query("
SELECT 
sh.support_history_id,
t.customers_id, 
CONCAT(c.customers_firstname, ' ', c.customers_lastname) AS customers_name, 
c.customers_email_address, 
a.entry_company, 
t.customers_orders, 
t.customers_domain, 
t.ticket_date, 
sh.ticket_comments, 
sh.department_id, 
sh.ticket_status, 
sh.priority_id, 
sh.admin_id, 
sh.last_modified, 
sh.submitted_by,
sh.user_id 
FROM " . TABLE_SUPPORT_TICKETS_HISTORY . "  sh, " . TABLE_SUPPORT_TICKETS . " t, " . TABLE_CUSTOMERS . " c 
LEFT JOIN " . TABLE_ADDRESS_BOOK . " a 
ON c.customers_default_address_id = a.address_book_id 
WHERE sh.ticket_id = '" . tep_db_input($ticket_id) . "' AND 
t.ticket_id = sh.ticket_id AND 
c.customers_id = t.customers_id AND
a.customers_id = c.customers_id 
ORDER BY sh.support_history_id DESC");


	$thiscomments = '';
  $order_index = 0;  
  while ($order = tep_db_fetch_array($order_query)) {
  	if ($order_index == 0){
  		$orderinfo = array(
                          'department'		=> $order['department_id'],
                          'priority'			=> $order['priority_id'],
                          'status'				=> $order['ticket_status'],
                          'admin'					=> $order['admin_id'],
                          'last_modified' => $order['last_modified'],
                          'max_id' 				=> $order['support_history_id']
                         );
      $this->customer = array('name' 						=> $order['customers_name'],
                              'company'					=> $order['entry_company'],
                              'id'							=> $order['customers_id'],
                              'orders'					=> $order['customers_orders'],
                              'domain'					=> $order['customers_domain'],
                              'date'						=> $order['ticket_date'],
                              'email_address'		=> $order['customers_email_address']);
                         
  	}
  	if ($order['submitted_by'] == 'customer'){
  		$thiscomments .= "<i><strong>". $order['customers_name'] . "</strong> el " . date('d-m-Y H:i:s',strtotime($order['last_modified'])). "</i> <br><span style=\"color:#0000ff\">" . $order['ticket_comments'] . "</span><p>";
  		}
    else {
    	$thiscomments .= "<i>support (". $order['user_id'] . ") el " . date('d-m-Y H:i:s',strtotime($order['last_modified'])). "</i><br><span style=\"color:#ff0000\">" . $order['ticket_comments'] . "</span><p>";}
  	
    
    $order_index ++; 
  }
  
	$orderinfo['ticket_comments'] = $thiscomments;
  
  
   $this->info = $orderinfo;



      

      




    }
  }
?>
