<?php

 tep_draw_hidden_field($account['customers_id']);    
 
  $my_account_query = tep_db_query ("select a.admin_id, a.admin_firstname, a.admin_lastname, a.admin_email_address, a.admin_created, a.admin_modified, a.admin_logdate, a.admin_lognum, g.admin_groups_name from " . TABLE_ADMIN . " a, " . TABLE_ADMIN_GROUPS . " g where a.admin_id= " . $login_id . " and g.admin_groups_id= " . $login_groups_id . "");
  $myAccount = tep_db_fetch_array($my_account_query);

?>
<div class="fluid grid">
	<div class="box-tbl grid6">
		<div class="box-head">
			<h6>Datos del cliente:</h6>
			<div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_CUSTOMERS_ID; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('customers_id', $account['customers_id']); ?></div>
		    <div class="clear"></div>
		</div> 
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_FIRST_NAME; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('firstname', $account['customers_firstname']); ?></div>
		    <div class="clear"></div>
		</div> 
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_LAST_NAME; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('lastname', $account['customers_lastname']); ?></div>
		    <div class="clear"></div>
		</div>  
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_NIF; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('nif', $address['entry_NIF']); ?></div>
		    <div class="clear"></div>
		</div>  
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_EMAIL_ADDRESS; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('email_address', $account['customers_email_address']); ?></div>
		    <div class="clear"></div>
		</div>  
		<?php if (ACCOUNT_COMPANY == 'true') { ?>  
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_COMPANY; ?></label></div>
			    <div class="grid6"><?php echo tep_draw_input_field('company', $address['entry_company']);?></div>
			    <div class="clear"></div>
			</div> 
		<?php } ?>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_TELEPHONE_NUMBER; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('telephone', $account['customers_telephone']); ?></div>
		    <div class="clear"></div>
		</div> 
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_FAX_NUMBER; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('fax', $account['customers_fax']); ?></div>
		    <div class="clear"></div>
		</div> 
	</div>
	<div class="box-tbl grid6">
		<div class="box-head">
			<h6>Dirección:</h6>
			<div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_STREET_ADDRESS; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('street_address', $address['entry_street_address']); ?></div>
		    <div class="clear"></div>
		</div> 
		<?php if (ACCOUNT_SUBURB == 'true') { ?>
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_SUBURB; ?></label></div>
			    <div class="grid6"><?php echo tep_draw_input_field('suburb', $address['entry_suburb']); ?></div>
			    <div class="clear"></div>
			</div>
		<?php } ?>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_POST_CODE; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('postcode', $address['entry_postcode']); ?></div>
		    <div class="clear"></div>
		</div> 
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_CITY; ?></label></div>
		    <div class="grid6"><?php echo tep_draw_input_field('city', $address['entry_city']);?></div>
		    <div class="clear"></div>
		</div> 
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_COUNTRY; ?></label></div>
			<div id="indicator"></div>
		    <div class="grid6">
			    <?php
	                if ($address['entry_country_id']){
	                  echo tep_draw_pull_down_menu('country', tep_get_countries(), $address['entry_country_id'], 'onChange="getStates(this.value, \'states\', \'create_order.php\');"');
	                }else{
	                  echo tep_draw_pull_down_menu('country', tep_get_countries(), STORE_COUNTRY, 'onChange="getStates(this.value, \'states\', \'create_order.php\');"');
	                }
	                tep_draw_hidden_field('step', '3');
	            ?>
            </div>
		    <div class="clear"></div>
		</div>
		<?php if (ACCOUNT_STATE == 'true') { ?>
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_STATE; ?></label></div>
			    <div class="grid6"><span id="states">
				    <?php
			              $zone_query = tep_db_query("select zone_id, zone_name from " . TABLE_ZONES . " where zone_country_id = '" . $address['entry_country_id'] . "' and zone_id = '" . $address['entry_zone_id'] . "'");
			              if (tep_db_num_rows($zone_query)) {
			                $zone = tep_db_fetch_array($zone_query);
			                $state = $zone['zone_name'];
							$country = $address['entry_country_id'];
			              }else{
			                $country = 195;
			              }
							echo ajax_get_zones_html($country,$state,false);
		            ?></span></span>
	            </div>
			    <div class="clear"></div>
			</div>
		<?php } ?>
	</div>
	<div class="box-tbl grid6">
		<div class="box-head">
			<h6>Otros:</h6>
			<div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_CURRENCY; ?></label></div>
		    <div class="grid6"><?php echo $SelectCurrencyBox ?></div>
		    <div class="clear"></div>
		</div> 
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_ADMIN; ?></label></div>
		    <div class="grid6">
		    	 <?php 
                      if (isset($myAccount['admin_firstname'])){
                        $cs_id=$myAccount['admin_firstname'].' ('.$myAccount['admin_email_address'].')';
                      }else{
                         $cs_id = $_SERVER['REMOTE_USER']; 
                      }
                  ?>
                  <?php echo tep_draw_input_field('cust_service', $cs_id) ?> 
		    </div>
		    <div class="clear"></div>
		</div> 
		<div class="formRow">
		    <div class="grid6"><label>Crear usuario nuevo:</label></div>
		    <div class="grid6">
		    	 <?php echo tep_draw_checkbox_field('new_cust', 1) ?>
		    </div>
		    <div class="clear"></div>
		</div> 
	</div>
	
	<div class="grid12" style="margin:0px;">
	    <div class="wButton grid12">
			<a href="#" onclick="$(this).closest('form').submit()" title="" class="buttonL bGreen" style="float: right; margin-top: 15px;">Siguiente</a>
	    	<a href="<?php echo tep_href_link(FILENAME_DEFAULT, '', 'SSL');?>" title="" class="buttonL bBlack" style="margin-right: 10px; float: right; margin-top: 15px;">Volver</a>
	    </div>
	</div>
</div>