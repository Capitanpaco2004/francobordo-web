<?php
/*
  $Id: header_navigation.php,v 1.19 2003/04/27 16:11:52 hpdl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License
  Updated by Gnidhal (fx@geniehalles.com)
*/
  $menu_dhtml = MENU_DHTML;
  $box_files_list = array();

  
  
//rmh M-S_multi-stores begin
  if (CanShowBox('configuration.php')) { 
		$box_files_list[] = array("configuration", "configuration.php" , BOX_HEADING_CONFIGURATION);
  }
  if (CanShowBox('modules.php')) {  
		$box_files_list[] = array("modules", "modules.php" , BOX_HEADING_MODULES);
  }
  if (CanShowBox('catalog.php')) {  
		$box_files_list[] = array("catalog", "catalog.php" , BOX_HEADING_CATALOG);
  }
  if (CanShowBox('families.php')) {  
		$box_files_list[] = array("families", "families.php" , BOX_HEADING_FAMILIES);
 // Family products 3.4
  }
  if (CanShowBox('customers.php')) {  
		$box_files_list[] = array("customers", "customers.php" , BOX_HEADING_CUSTOMERS);
  }
  if (CanShowBox('orders.php')) {  
		$box_files_list[] = array("orders", "orders.php" , BOX_HEADING_ORDERS);
  }
  if (CanShowBox('returns.php')) {  
		$box_files_list[] = array("returns", "returns.php" , BOX_HEADING_REFUNDS);
 // RMA Return System
  }
  if (CanShowBox('information.php')) {  
		$box_files_list[] = array("information", "information.php" , BOX_HEADING_INFORMATION);
  // Information Page Unlimited v1.3a
  }
  if (CanShowBox('faqdesk.php')) {  
		$box_files_list[] = array("faqdesk", "faqdesk.php" , BOX_HEADING_FAQDESK);
 // Faqdesk v1.2
  }
  if (CanShowBox('stores.php')) {  
		$box_files_list[] = array("stores", "stores.php" , BOX_HEADING_STORES);
  }
  if (CanShowBox('taxes.php')) {  
		$box_files_list[] = array("taxes", "taxes.php" , BOX_HEADING_LOCATION_AND_TAXES);
  }
  if (CanShowBox('localization.php')) {  
		$box_files_list[] = array("localization", "localization.php" , BOX_HEADING_LOCALIZATION);
  }
  if (CanShowBox('reports.php')) {  
		$box_files_list[] = array("reports", "reports.php" , BOX_HEADING_REPORTS);
  }
  if (CanShowBox('tools.php')) {
		$box_files_list[] = array("tools", "tools.php" , BOX_HEADING_TOOLS);
  }
  if (CanShowBox('marketing.php')) {
		$box_files_list[] = array("marketing", "marketing.php" , BOX_HEADING_MARKETING);
  }
  if (CanShowBox('administrators.php')) {
        $box_files_list[] = array("administrators", "administrators.php" , BOX_HEADING_ADMINISTRATORS);   //rmh M-S_multi-stores end
  }
  
  
   echo '<!-- Menu bar #2. --> <div class="menuBar" style="width:100%;">';
   
   foreach($box_files_list as $item_menu) {
     echo "<a class=\"menuButton\" href=\"\" onclick=\"return buttonClick(event, '".$item_menu[0]."Menu');\" onmouseover=\"buttonMouseover(event, '".$item_menu[0]."Menu');\">".$item_menu[2]."</a>" ;
   }
   echo "</div>";
foreach($box_files_list as $item_menu) require(DIR_WS_BOXES. $item_menu[1] );


?>