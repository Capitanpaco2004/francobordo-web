<?php
/*
 - to filter customers by counting signin, firstname and lastname
 - created by smartosc 
 - 09/10/2007
*/
  require('includes/application_top.php');
  // get customers options
  $options_array = get_inactive_customers_clearance();
  
  $selected_option = (isset($_GET['cust_options']) && tep_not_null($_GET['cust_options'])) ? htmlspecialchars($_GET['cust_options']) : htmlspecialchars($_POST['cust_options']);
    
  if($selected_option ==''){
	 $selected_option = 1;
  }  
 

  // begin deleting customers were selected  
 if(isset($_GET['del']) && $_GET['del'] == 'del'){
 
  // delete of all the customers     
  if(isset($_GET['sel_all']) && $_GET['sel_all'] == '1'){
	  
	  $cus_ids = "0";
	  if($selected_option == '1' || $selected_option == '2'){
			$cus_ids_query = tep_db_query("select customers_info_id from " . TABLE_CUSTOMERS_INFO .  " where customers_info_number_of_logons = 0 or customers_info_number_of_logons is NULL ");
			while($cus_ids_value = tep_db_fetch_array($cus_ids_query)){
					$cus_ids .= "," . $cus_ids_value['customers_info_id'];
			}
	  }
	  
	  switch($selected_option){
	  
	  case '1':    // have not been ever logined	 	     
		tep_db_query("delete from " . TABLE_CUSTOMERS . " where customers_id in ( ". $cus_ids ." )");
		tep_db_query("delete from " . TABLE_CUSTOMERS_INFO . "  where customers_info_number_of_logons = 0 or customers_info_number_of_logons is NULL " );
	  break;	
	  
	  case '2':    // have not been ever logined and not subscrible newsletters	 	    
		// get customers_id with the customers not subcrible newsletter
		$custo_id = "0";
		$custo_id_query = tep_db_query("select customers_id from " .TABLE_CUSTOMERS. " where customers_newsletter <>'1'");	
		
		while($custo_id_value = tep_db_fetch_array($custo_id_query)){
		   		$custo_id .= "," . $custo_id_value['customers_id'];
		}

		tep_db_query("delete from " . TABLE_CUSTOMERS . "  where customers_id in ( " . $cus_ids . " ) and customers_newsletter <>'1'");

		tep_db_query("delete from " . TABLE_CUSTOMERS_INFO . " where customers_info_id in( " . $custo_id . " ) and (customers_info_number_of_logons = 0 or customers_info_number_of_logons is NULL )");        
      break;
	
	  case '3':    // firstname and lastname were duplicated
		 // get customers_id with the customers not subcrible newsletter		
		 $custo_id = getAll();
		 
		 tep_db_query("delete from " .TABLE_CUSTOMERS. "  where customers_id in (".$custo_id.") ");	
		 
		 tep_db_query("delete from " .TABLE_CUSTOMERS_INFO. "  where customers_info_id in (".$custo_id.")");	
		 
		 tep_db_query("delete from " .TABLE_ADDRESS_BOOK. "  where customers_id in (".$custo_id.")");	
		 
		 tep_db_query("delete from " .TABLE_ORDERS. "  where customers_id in (".$custo_id.")");	
		 
      break;
	  }
  }elseif(isset($_GET['cus'])){		
		if(is_array($_GET['cus'])){
			 $cus_id = "";
			
             for($inde = 0; $inde < sizeof($_GET['cus'])-1; $inde++){
					$cus_id .= $_GET['cus'][$inde].", ";
			 }
					$cus_id .= $_GET['cus'][$inde]."";
             //
			 tep_db_query("delete from " . TABLE_CUSTOMERS . " where customers_id in (".$cus_id.")");
			 tep_db_query("delete from " . TABLE_CUSTOMERS_INFO . " where customers_info_id in (".$cus_id.")");
			 tep_db_query("delete from " .TABLE_ADDRESS_BOOK. "  where customers_id in (".$cus_id.")");			 
		     tep_db_query("delete from " .TABLE_ORDERS. "  where customers_id in (".$cus_id.")");	
		}
  }
 } // end deleting customers were selected  
  
  
  // conditions
   $codit ='';
   $add_field = '';
	switch($selected_option){
		case '1':   // not been ever logined
		$codit = " left join ".TABLE_CUSTOMERS_INFO." cin on cin.customers_info_id = c.customers_id where (cin.customers_info_number_of_logons = 0 or cin.customers_info_number_of_logons is NULL ) ";
		$add_field = ", cin.customers_info_number_of_logons as number_logon ";
		break;	

		case '2':  // not been ever logined and not subscrible newsletter 
		$codit = " left join ".TABLE_CUSTOMERS_INFO." cin on cin.customers_info_id = c.customers_id where ((cin.customers_info_number_of_logons = 0 or cin.customers_info_number_of_logons is NULL ) and c.customers_newsletter <>'1') ";
		$add_field = " , cin.customers_info_number_of_logons as number_logon ";        
        break;

		case '3':  // first name and last name are duplicated
		 $customers_id = getAll(); 
		 $codit = " where c.customers_id in(".$customers_id.")";	
		break;
	}
   //
    $customers_query_raw = "select c.customers_id, c.customers_lastname, c.customers_firstname, c.customers_email_address " . $add_field . " from " . TABLE_CUSTOMERS . " c " .$codit . " order by c.customers_lastname asc, c.customers_firstname asc";
     // get total_customers
	 $total_customers_query = $customers_query_raw;
	 $total_customers_value = tep_db_query($total_customers_query);
	 $total_customer = tep_db_num_rows($total_customers_value); 
	 
    $customers_split1 = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $total_customers_query, $customers_query_numrows);
    $customers_query1 = tep_db_query($total_customers_query);
	$number_records = tep_db_num_rows($customers_query1);
	
?>

<?php require(THEME . 'html/header.php'); ?>

<style>
	.text_link{
		
	}
</style>
<script language="javascript" src="includes/general.js"></script>
<script type="text/javascript">
<!--
    function MM_findObj(n, d) { //v4.01
      var p,i,x;  if(!d) d=document; if((p=n.indexOf("?"))>0&&parent.frames.length) {
        d=parent.frames[n.substring(p+1)].document; n=n.substring(0,p);}
      if(!(x=d[n])&&d.all) x=d.all[n]; for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
      for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=MM_findObj(n,d.layers[i].document);
      if(!x && d.getElementById) x=d.getElementById(n); return x;
    }
//-->
</script>	
<script language="javascript">
<!--
	var sDisplay = 'none';
    function not_display(){
	   
	     var mess = MM_findObj('number_selected_customers');
	     mess.style.visibility = 'hidden';
	     mess.style.display = 'none';  	

		 var mess = MM_findObj('selected_all_customers');
		 mess.style.visibility = 'hidden';
		 mess.style.display = 'none';  
		 set_checkbox_value(false);	
	}
	
	function set_checkbox_value(val){
		for(i=0; i<document.filter.elements.length; i++)
		{
			if(document.filter.elements[i].type=="checkbox" && document.filter.elements[i].name.substring(0,3)=='cus')
			{
				document.filter.elements[i].checked = val;
			}
		}
	}
	
	function sel_option(){	   
		//var option = document.filter.cust_options.value;
		var option = document.getElementById('cust_options');		
		document.location.href ='customers_options.php?cust_options='+option.value; 		
	}

   function check_all(){
   	    document.filter.sel_all.value='1';		
	    var mess = MM_findObj('selected_all_customers');
	    mess.style.visibility = 'visible';
	    mess.style.display = 'inline';  	

	    var mess = MM_findObj('number_selected_customers');
	    mess.style.visibility = 'hidden';
	    mess.style.display = 'none';  	

		set_checkbox_value(true);
   } 
   
   function check_all_page()
	{	
	  document.filter.sel_all.value='0';	  
	 //var mess = document.getElementById('number_selected_customers');
	 var mess = MM_findObj('number_selected_customers');	   
		mess.style.visibility = 'visible';
		mess.style.display = 'block';
		

	 var mess = MM_findObj('selected_all_customers');
		mess.style.visibility = 'hidden';
		mess.style.display = 'none';
	 	
		set_checkbox_value(true);
	}
   
	function uncheck_all_page()
	{
        document.filter.sel_all.value='0';	
		not_display();	     	    	
	}
//-->		
</script>


<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
  <tr>
<!-- body_text //-->
    <td width="100%" valign="top">
	<table border="0" width="100%" cellspacing="0" cellpadding="2">
	<?php echo tep_draw_form('filter', FILENAME_CUSTOMERS_OPTIONS, '', 'get'); ?>
	<?php echo tep_draw_hidden_field('del','del');?>
	<?php echo tep_draw_hidden_field('sel_all','');?>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
			
         <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?></td>
		 </tr>	
		<tr>
            <td align="right"></td>
            <td align="right" style="font-size:14px; font-weight:bold;"><?php echo TEXT_SELECT_OPTIONS; ?>&nbsp;&nbsp;
			  <?php echo tep_draw_pull_down_menu('cust_options', $options_array, $selected_option, 'onChange="sel_option()" id="cust_options"');?>
			</td>
		</tr>	         
        </table></td>
      </tr>
	   <tr>
			   	<td colspan="5" align="right">			
			   <!-- Display number of customers are selected //-->
			    <div  style="background-color: #fff; display: none;" id="number_selected_customers" >
					
				     <div style="font-size:14px;" align="right">
					   <?php					      					   
					    $count = 0 ;
					   	if($number_records < MAX_DISPLAY_SEARCH_RESULTS){
						   $count = $number_records;
						}else{
						   $count = MAX_DISPLAY_SEARCH_RESULTS;
						}
						if($count > 0 && $total_customer > $count){
					       echo sprintf(TEXT_MESSAGE_A, $count); ?>
						   <a style="color:#0000FF; font-family:Verdana, Arial, Helvetica, sans-serif;font-size:12px; font-weight:normal; color: white;" href="#" onClick="javascript: check_all()"><?php echo sprintf(TEXT_SELECT_ALL, $total_customer);?></a>
						<?php   	   
						}else{
						   echo sprintf(TEXT_MESSAGE_B, $count); ?>
						   <a style="color:#0000FF; font-family:Verdana, Arial, Helvetica, sans-serif;font-size:12px; font-weight:normal; color: white;" href="#" onClick="javascript: uncheck_all_page()"><?php echo TEXT_CLEAR_ALL;?></a>
						<?php						   	   
						}						
					   ?>
					 	
					 </div>					 
				</div>
				<!-- Display of all number of customers are selected //-->
			    <div id="selected_all_customers" style="display: none; background-color: #fff;">
				     <div style="font-size:14px;" align="right"> 
					   <?php					      					   
						if($total_customer > 0){					     
						   echo sprintf(TEXT_MESSAGE_B, $count); ?>
						   <a style="color:#0000FF; font-family:Verdana, Arial, Helvetica, sans-serif;font-size:12px; font-weight:normal; color: white;" href="#" onClick="javascript: uncheck_all_page()"><?php echo TEXT_CLEAR_ALL;?></a>
						<?php						   	   
						}						
					   ?>
					 	 
					 </div>					 
				</div>
				</td>
				</tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top">
			  <table border="0" width="100%" cellspacing="0" cellpadding="2">
               <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_LASTNAME; ?></td>
                <td class="dataTableHeadingContent"><?php echo TABLE_HEADING_FIRSTNAME; ?></td>
				<td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_EMAIL_ADDRESS; ?></td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACCOUNT_CREATED; ?></td>
                <td style="font-family:Verdana, Arial, Helvetica, sans-serif;font-size:12px; font-weight:normal; color: white;" align="right">
				<?php echo TEXT_SELECT; ?>&nbsp;
			  <a style="color:#0000FF; font-family:Verdana, Arial, Helvetica, sans-serif;font-size:12px; font-weight:normal; color: white;" href="#" onClick="javascript: check_all_page()"><?php echo TEXT_SELECT_ALL_PAGE; ?></a>,
			  <a style="color:#0000FF; font-family:Verdana, Arial, Helvetica, sans-serif;font-size:12px; font-weight:normal; color: white;" href="#"  onClick="uncheck_all_page()"><?php echo TEXT_SELECT_NONE; ?></a>
				</td>
               </tr>
			  
<?php	

    $customers_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $customers_query_raw, $customers_query_numrows);
    $customers_query = tep_db_query($customers_query_raw);

    while ($customers = tep_db_fetch_array($customers_query)) {
      $info_query = tep_db_query("select customers_info_date_account_created as date_account_created from " . TABLE_CUSTOMERS_INFO . " where customers_info_id = '" . $customers['customers_id'] . "'");
      $info = tep_db_fetch_array($info_query);
?>
			  <tr class="dataTableRow">	
                <td class="dataTableContent"><?php echo $customers['customers_lastname']; ?></td>
                <td class="dataTableContent"><?php echo $customers['customers_firstname']; ?></td>
				<td class="dataTableContent" align="right"><?php echo $customers['customers_email_address']; ?></td>
                <td class="dataTableContent" align="right"><?php echo tep_date_short($info['date_account_created']); ?></td>
                <td class="dataTableContent" align="right"><input type="checkbox" name="cus[]" value="<?php echo $customers['customers_id'];?>">&nbsp;</td>
              </tr>
<?php
    }
?>
              
			  <tr>
				<td colspan="5" align="right"> <?php echo tep_image_submit('button_delete.png', IMAGE_DELETE); ?></td>
			  </tr>
			  
			 
            </table></td>
          </tr>
        </table></td>
      </tr>

    </table>
	</form>
	<table>
	    <tr>
                <table class="table-page" border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText" valign="top"><?php echo $customers_split->display_count($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_CUSTOMERS); ?></td>
                    <td class="smallText" align="right"><?php echo $customers_split->display_links($customers_query_numrows, MAX_DISPLAY_SEARCH_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page'], tep_get_all_get_params(array('page', 'info', 'x', 'y', 'cID'))); ?></td>
                  </tr>

                </table></td>
        </tr>
	</table>
	</td>
<!-- body_text_eof //-->
  </tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require(THEME . 'html/footer.php'); ?>
<!-- footer_eof //-->
<br>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
