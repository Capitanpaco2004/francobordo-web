<?php
/*
  $Id: account_details.php,v 1 2003/08/24 23:22:27 frankl Exp $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2002 osCommerce

  Released under the GNU General Public License

  Admin Create Account
*/

  $newsletter_array = array(array('id' => '1',
                                  'text' => ENTRY_NEWSLETTER_YES),
                            array('id' => '0',
                                  'text' => ENTRY_NEWSLETTER_NO));

function sbs_get_zone_name($country_id, $zone_id) {
    $zone_query = tep_db_query("select zone_name from " . TABLE_ZONES . " where zone_country_id = '" . $country_id . "' and zone_id = '" . $zone_id . "'");
    if (tep_db_num_rows($zone_query)) {
      $zone = tep_db_fetch_array($zone_query);
      return $zone['zone_name'];
    } else {
      return $default_zone;
    }
  }

 // Returns an array with countries
// TABLES: countries
  function sbs_get_countries($countries_id = '', $with_iso_codes = false) {
    $countries_array = array();
    if ($countries_id) {
      if ($with_iso_codes) {
        $countries = tep_db_query("select countries_name, countries_iso_code_2, countries_iso_code_3 from " . TABLE_COUNTRIES . " where countries_id = '" . $countries_id . "' order by countries_name");
        $countries_values = tep_db_fetch_array($countries);
        $countries_array = array('countries_name' => $countries_values['countries_name'],
                                 'countries_iso_code_2' => $countries_values['countries_iso_code_2'],
                                 'countries_iso_code_3' => $countries_values['countries_iso_code_3']);
      } else {
        $countries = tep_db_query("select countries_name from " . TABLE_COUNTRIES . " where countries_id = '" . $countries_id . "'");
        $countries_values = tep_db_fetch_array($countries);
        $countries_array = array('countries_name' => $countries_values['countries_name']);
      }
    } else {
      $countries = tep_db_query("select countries_id, countries_name from " . TABLE_COUNTRIES . " order by countries_name");
      while ($countries_values = tep_db_fetch_array($countries)) {
        $countries_array[] = array('countries_id' => $countries_values['countries_id'],
                                   'countries_name' => $countries_values['countries_name']);
      }
    }

    return $countries_array;
  }
  ////
function sbs_get_country_list($name, $selected = '', $parameters = '') {
   $countries_array = array(array('id' => '', 'text' => PULL_DOWN_DEFAULT));
   $countries = sbs_get_countries();
   $size = sizeof($countries);
   for ($i=0; $i<$size; $i++) {
     $countries_array[] = array('id' => $countries[$i]['countries_id'], 'text' => $countries[$i]['countries_name']);
   }

   return tep_draw_pull_down_menu($name, $countries_array, $selected, $parameters);
}

?>
<div class="fluid grid">
	<div class="box-tbl grid6">
		<div class="box-head">
			<h6>Datos del cliente</h6>
			<div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_FIRST_NAME; ?></label></div>
		    <div class="grid6">
		    	<?php
				  if ($is_read_only) {
				    echo $account['customers_firstname'];
				  } elseif ($error) {
				    if ($entry_firstname_error) {
				      echo tep_draw_input_field('firstname') . '&nbsp;' . ENTRY_FIRST_NAME_ERROR;
				    } else {
				      echo $firstname . tep_draw_hidden_field('firstname');
				    }
				  } else {
				    echo tep_draw_input_field('firstname', $account['customers_firstname']);
				  }
				?>
		    </div>
		    <div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_LAST_NAME; ?></label></div>
		    <div class="grid6">
		    	<?php
				   if ($is_read_only) {
				    echo $account['customers_lastname'];
				  } elseif ($error) {
				    if ($entry_lastname_error) {
				      echo tep_draw_input_field('lastname') . '&nbsp;' . ENTRY_LAST_NAME_ERROR;
				    } else {
				      echo $lastname . tep_draw_hidden_field('lastname');
				    }
				  } else {
				    echo tep_draw_input_field('lastname', $account['customers_lastname']);
				  }
				?>
		    </div>
		    <div class="clear"></div>
		</div>
		<?php  if (ACCOUNT_NIF == 'true') { ?>
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_NIF; ?></label></div>
			    <div class="grid6">
			    	<?php
					   	if ($is_read_only) {
							echo $account['entry_nif'];
						} elseif ($error) {
							if (ACCOUNT_NIF_REQ == 'true') echo tep_draw_input_field('entry_nif', $account['entry_nif'], 'maxlength="9"', true) . '&nbsp;' . ENTRY_LAST_NAME_ERROR;
							else echo tep_draw_input_field('entry_nif', $account['entry_nif'], 'maxlength="9"') . '&nbsp;' . (strlen($_POST['entry_nif']) <= 2 ? ENTRY_LAST_NAME_ERROR : '');
						} else {
							if (ACCOUNT_NIF_REQ == 'true') echo tep_draw_input_field('entry_nif', $account['entry_nif'], 'maxlength="9"', true);
							else echo tep_draw_input_field('entry_nif', $account['entry_nif'], 'maxlength="9"');
						}
					?>
			    </div>
			    <div class="clear"></div>
			</div>
		<?php } ?>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_EMAIL_ADDRESS; ?></label></div>
		    <div class="grid6">
		    	<?php
				   	if ($is_read_only) {
						echo $account['customers_email_address'];
					} elseif ($error) {
						if ($entry_email_address_error) {
							echo tep_draw_input_field('email_address') . '&nbsp;' . ENTRY_EMAIL_ADDRESS_ERROR;
						} elseif ($entry_email_address_check_error) {
						  	echo tep_draw_input_field('email_address') . '&nbsp;' . ENTRY_EMAIL_ADDRESS_CHECK_ERROR;
						} elseif ($entry_email_address_exists) {
						  	echo tep_draw_input_field('email_address') . '&nbsp;' . ENTRY_EMAIL_ADDRESS_ERROR_EXISTS;
						} else {
						  	echo $email_address . tep_draw_hidden_field('email_address');
						}
					} else {
						echo tep_draw_input_field('email_address', $account['customers_email_address']);
					}
				?>
		    </div>
		    <div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_TELEPHONE_NUMBER; ?></label></div>
		    <div class="grid6">
		    	<?php
				  if ($is_read_only) {
				    echo $account['customers_telephone'];
				  } elseif ($error) {
				    if ($entry_telephone_error) {
				      echo tep_draw_input_field('telephone') . '&nbsp;' . ENTRY_TELEPHONE_NUMBER_ERROR;
				    } else {
				      echo $telephone . tep_draw_hidden_field('telephone');
				    }
				  } else {
				    echo tep_draw_input_field('telephone', $account['customers_telephone']);
				  }
				?>
		    </div>
		    <div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_FAX_NUMBER; ?></label></div>
		    <div class="grid6">
		    	<?php
				  if ($is_read_only) {
				    echo $account['customers_fax'];
				  } elseif ($processed) {
				    echo $fax . tep_draw_hidden_field('fax');
				  } else {
				    echo tep_draw_input_field('fax', $account['customers_fax']);
				  }
				?>
		    </div>
		    <div class="clear"></div>
		</div>
		<?php  if (ACCOUNT_DOB == 'true') { ?>
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_DATE_OF_BIRTH; ?></label></div>
			    <div class="grid6">
				<?php
				    if ($is_read_only) {
				      echo tep_date_short($account['customers_dob']);
				    } elseif ($error) {
				      if ($entry_date_of_birth_error) {
				        echo tep_draw_input_field('dob') . '&nbsp;' . ENTRY_DATE_OF_BIRTH_ERROR;
				      } else {
				        echo $dob . tep_draw_hidden_field('dob');
				      }
				    } else {
				      echo tep_draw_input_field('dob', tep_date_short($account['customers_dob']));
				    }
				?>
			    </div>
			    <div class="clear"></div>
			</div>
		<?php } ?>
		<?php
			if (ACCOUNT_GENDER == 'true') {
			    $male = ($account['customers_gender'] == 'm') ? true : false;
   				$female = ($account['customers_gender'] == 'f') ? true : false;
		?>
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_GENDER; ?></label></div>
			    <div class="grid6">
				<?php
				    if ($is_read_only) {
				      echo ($account['customers_gender'] == 'm') ? MALE : FEMALE;
				    } elseif ($error) {
				      if ($entry_gender_error) {
				        echo tep_draw_radio_field('gender', 'm', $male) . '&nbsp;&nbsp;' . MALE . '&nbsp;&nbsp;' . tep_draw_radio_field('gender', 'f', $female) . '&nbsp;&nbsp;' . FEMALE . '&nbsp;' . ENTRY_GENDER_ERROR;
				      } else {
				        echo ($gender == 'm') ? MALE : FEMALE;
				        echo tep_draw_hidden_field('gender');
				      }
				    } else {
				      echo tep_draw_radio_field('gender', 'm', $male) . '&nbsp;&nbsp;' . MALE . '&nbsp;&nbsp;' . tep_draw_radio_field('gender', 'f', $female) . '&nbsp;&nbsp;' . FEMALE . '&nbsp;' ;
				    }
				?>
			    </div>
			    <div class="clear"></div>
			</div>
		<?php } ?>
		<?php  if (ACCOUNT_COMPANY == 'true') { ?>
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_COMPANY; ?></label></div>
			    <div class="grid6">
				<?php
				    if ($is_read_only) {
				      echo $account['entry_company'];
				    } elseif ($error) {
				      if ($entry_company_error) {
				        echo tep_draw_input_field('company') . '&nbsp;' . ENTRY_COMPANY_ERROR;
				      } else {
				        echo $company . tep_draw_hidden_field('company');
				      }
				    } else {
				      echo tep_draw_input_field('company', $account['entry_company']) . '&nbsp;' ;
				    }
				?>
			    </div>
			    <div class="clear"></div>
			</div>
		<?php } ?>
		<div class="formRow" style="display: none;">
		    <div class="grid6"><label><?php echo ENTRY_NEWSLETTER; ?></label></div>
		    <div class="grid6">
			<?php
			  if ($is_read_only) {
			    if ($account['customers_newsletter'] == '1') {
			      echo ENTRY_NEWSLETTER_YES;
			    } else {
			      echo ENTRY_NEWSLETTER_NO;
			    }
			  } elseif ($processed) {
			    if ($newsletter == '1') {
			      echo ENTRY_NEWSLETTER_YES;
			    } else {
			      echo ENTRY_NEWSLETTER_NO;
			    }
			    echo tep_draw_hidden_field('newsletter');
			  } else {
			    echo tep_draw_pull_down_menu('newsletter', $newsletter_array, $account['customers_newsletter']) . '&nbsp;' ;
			  }
			?>
		    </div>
		    <div class="clear"></div>
		</div>
	</div>

	<div class="box-tbl grid6">
		<div class="box-head">
			<h6><?php echo CATEGORY_ADDRESS; ?></h6>
			<div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_STREET_ADDRESS; ?></label></div>
		    <div class="grid6">
			 <?php
				  if ($is_read_only) {
				    echo $account['entry_street_address'];
				  } elseif ($error) {
				    if ($entry_street_address_error) {
				      echo tep_draw_input_field('street_address') . '&nbsp;' . ENTRY_STREET_ADDRESS_ERROR;
				    } else {
				      echo $street_address . tep_draw_hidden_field('street_address');
				    }
				  } else {
				    echo tep_draw_input_field('street_address', $account['entry_street_address']);
				  }
			?>
			</div>
		    <div class="clear"></div>
		</div>
		<?php if (ACCOUNT_SUBURB == 'true') { ?>
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_SUBURB; ?></label></div>
			    <div class="grid6">
				 <?php
				    if ($is_read_only) {
				      echo $account['entry_suburb'];
				    } elseif ($error) {
				      if ($entry_suburb_error) {
				        echo tep_draw_input_field('suburb') . '&nbsp;' . ENTRY_SUBURB_ERROR;
				      } else {
				        echo $suburb . tep_draw_hidden_field('suburb');
				      }
				    } else {
				      echo tep_draw_input_field('suburb', $account['entry_suburb']) . '&nbsp;' ;
				    }
				?>
				</div>
			    <div class="clear"></div>
			</div>
		<?php } ?>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_POST_CODE; ?></label></div>
		    <div class="grid6 getCitiesFromCP campo">
			 <?php
				  if ($is_read_only) {
				    echo $account['entry_postcode'];
				  } elseif ($error) {
				    if ($entry_post_code_error) {
				      echo tep_draw_input_field('postcode', false, ' id="postcode" ') . '&nbsp;' . ENTRY_POST_CODE_ERROR;
				    } else {
				      echo $postcode . tep_draw_hidden_field('postcode');
				    }
				  } else {
				    echo tep_draw_input_field('postcode', $account['entry_postcode'], ' id="postcode" ') . '&nbsp;' ;
				  }
			?>
			</div>
		    <div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_CITY; ?></label></div>
		    <div class="grid6 city campo">
			 <?php
				  if ($is_read_only) {
				    echo $account['entry_city'];
				  } elseif ($error) {
				    if ($entry_city_error) {
                        echo ajax_get_cities_html(false, false, false, false, true);
				        //echo tep_draw_input_field('city') . '&nbsp;' . ENTRY_CITY_ERROR;
				    } else {
				      echo $city . tep_draw_hidden_field('city_id', $city_id);
				    }
				  } else {
				    echo tep_draw_input_field('city', $account['entry_city']) . '&nbsp;' ;
				  }
			?>
			</div>
		    <div class="clear"></div>
		</div>
		<div class="formRow">
		    <div class="grid6"><label><?php echo ENTRY_COUNTRY; ?></label></div>
			<div id="indicator"></div>
		    <div class="grid6">
				<?php
					$account['entry_country_id'] = STORE_COUNTRY;
					if ($is_read_only)
						echo tep_get_country_name($account['entry_country_id']);
					elseif ($error)
					{
						if ($entry_country_error)
							echo sbs_get_country_list('country', '', 'onChange="getStates(this.value, \'states\', \'create_account.php\');"') . '&nbsp;' . ENTRY_COUNTRY_ERROR;
						else
							echo tep_get_country_name($country) . tep_draw_hidden_field('country');
					}
					else
						echo sbs_get_country_list('country', $account['entry_country_id'], 'onChange="getStates(this.value, \'states\', \'create_account.php\');"') . '&nbsp;' ;
				?>
			</div>
		    <div class="clear"></div>
		</div>
		<?php if (ACCOUNT_STATE == 'true') { ?>
			<div class="formRow">
			    <div class="grid6"><label><?php echo ENTRY_STATE; ?></label></div>
			    <div class="grid6 getCitiesFromZone campo"><span id="states">
					<?php
						$state = sbs_get_zone_name($country, $zone_id);
						if ($is_read_only)
							echo sbs_get_zone_name($account['entry_country_id'], $account['entry_zone_id']);
						elseif ($error)
						{
							if ($entry_state_error)
							{
								$zones_query = tep_db_query("select zone_name from " . TABLE_ZONES . " where zone_country_id = '" . tep_db_input($country) . "' order by zone_name");
								if (tep_db_num_rows($zones_query)>0)
								{
									$zones_array = array();

									while ($zones_values = tep_db_fetch_array($zones_query))
										$zones_array[] = array('id' => $zones_values['zone_id'], 'text' => $zones_values['zone_name']);

									echo tep_draw_pull_down_menu('state', $zones_array) . '&nbsp;' . ENTRY_STATE_ERROR;
								}
								else
									echo tep_draw_input_field('state') . '&nbsp;' . ENTRY_STATE_ERROR;
							}
							else
								echo ajax_get_zones_html_id($country, $state, false );
						}
						else
							echo ajax_get_zones_html_id($account['entry_country_id'], sbs_get_zone_name($account['entry_country_id'], $account['entry_zone_id']), false ) . '&nbsp;';
					?>
				</span></div>
			    <div class="clear"></div>
			</div>
		<?php } ?>
	</div>
	<div class="grid6">
	    <div class="wButton grid6">
	    	<a href="<?php echo tep_href_link(FILENAME_DEFAULT, '', 'SSL');?>" title="" class="buttonL bBlack" style="margin-top: 10px;">Volver</a> <a href="#" onclick="$(this).closest('form').submit()" title="" class="buttonL bGreen" style="margin-top: 10px;">Crear cuenta</a>
	    </div>
	</div>
</div>
