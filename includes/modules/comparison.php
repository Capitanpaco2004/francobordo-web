<?php
	if( $current_category_id != 0 )
	{
		// This is used here to generate the column headings (Row 0 of the table)
		//   and later to step through the columns on each row
		$list_box_contents = array ();
		$specifications_query_raw = "select s.specifications_id, s.specification_sort_order, s.products_column_name, s.column_justify, s.filter_display, sd.specification_name, sd.specification_prefix, sd.specification_suffix, sg.specification_group_id
									 from specifications s
									 join specification_description sd on (sd.specifications_id = s.specifications_id and sd.language_id = '" . (int) $languages_id . "')
									 join specification_groups sg on (sg.specification_group_id = s.specification_group_id and sg.show_comparison = 'True')
									 join specification_groups_to_categories sg2c on (sg2c.specification_group_id = sg.specification_group_id)
									 join categories_description cd on (cd.categories_id = sg2c.categories_id)
									 where s.show_comparison = 'True' and cd.categories_id = '" . (int) $current_category_id . "' and cd.language_id = '" . (int) $languages_id . "'
									 order by s.specification_sort_order, sd.specification_name ";

		$specifications_query = tep_db_query($specifications_query_raw);
		
		$sHtml = '';

		if( tep_db_num_rows( $specifications_query ) > 0 )
		{
				$specification_id_array = array ();
				
				$sHtml .= '<div class="fble-th">';
				$nColum = 1;
				
				while ($specifications_heading = tep_db_fetch_array($specifications_query)) 
				{
					// Set up the heading for the table
					$box_text = '&nbsp;';

					if( $specifications_heading['specification_name'] != '' )
						$box_text = $specifications_heading['specification_name'];

					if( $specifications_heading['specification_suffix'] != '' && SPECIFICATIONS_COMP_SUFFIX == 'True' )
						$box_text .= ' (' . $specifications_heading['specification_suffix'] . ')';

					$sHtml .= '<div class="fble-td fble-col-' . $nColum . '">' . $box_text . '</div>';
						
					// Build an array to use as an index on the table rows
					$id = $specifications_heading['specifications_id'];
					$group_id = $specifications_heading['specification_group_id'];

					$specification_id_array[$id] = array (
					  'id' => $specifications_heading['specifications_id'],
					  'sort_order' => $specifications_heading['specification_sort_order'],
					  'column_name' => $specifications_heading['products_column_name'],
					  'column_justify' => $specifications_heading['column_justify'],
					  'name' => $specifications_heading['specification_name'],
					  'prefix' => $specifications_heading['specification_prefix'],
					  'suffix' => $specifications_heading['specification_suffix'],
					  'display' => $specifications_heading['filter_display'],
					  'enter' => $specifications_heading['enter_values'],
					  'group_id' => $specifications_heading['specification_group_id']
					);
					
					$nColum++;
				}
				$sHtml .= '</div>';

			// Table rows
			$sIds = implode( ',', $comp_array );

			$products_query_raw = "select distinct p.products_id
								   from " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c
								   join " . TABLE_SPECIFICATIONS_TO_CATEGORIES . " s2c on (p2c.categories_id = s2c.categories_id)
								   join " . TABLE_PRODUCTS . " p on (p.products_id = p2c.products_id)
								   where p.products_status = 1 and p2c.categories_id = '" . (int) $current_category_id . "' and s2c.specification_group_id = '" . (int) $group_id . "' " . ( $sIds == '' ? '' : "and p.products_id in(" . $sIds . ")" ) . "
								   order by p.products_id";

			$products_query = tep_db_query($products_query_raw);
		
			if( tep_db_num_rows($products_query) >= SPECIFICATIONS_MINIMUM_COMPARISON )
			{
				$nCont = 0;
			
				// Add the product rows
				while ($products_array = tep_db_fetch_array($products_query))
				{
					// Each product is a row
					$products_id = $products_array['products_id'];

					// Check to see if this product has any specifications
					$check_query_raw = "select count(products_specification_id) as total
										from " . TABLE_PRODUCTS_SPECIFICATIONS . "
										where products_id = '" . $products_id . "' and (specification != '')";

					$check_query = tep_db_query($check_query_raw);
					$check_total = tep_db_fetch_array($check_query);

					// Show product
					if( $check_total['total'] > 0 || SPECIFICATIONS_PRODUCTS_NO_SPEC == 'True' ) 
					{
						reset($specification_id_array);

						// Get the existing fields data
						$field_array = tep_fill_existing_fields( $products_id, $languages_id );

						//Start the row							
						$sHtml .= tep_draw_form( 'cart_quantity', tep_href_link(FILENAME_DEFAULT, tep_get_all_get_params(array('action')) . 'action=add_product'), 'post', 'class="fble-tr' . ($nCont % 2 == 0 ? ' fble-odd' : '') . '"' );
						
						// Get the data for each specification in the row
						$nColum = 1;
						foreach ($specification_id_array as $specs_id => $specs_data)
						{
							// Get the cell parameters
							$table_cell = tep_specification_table_cell( $specs_id, $products_id, $languages_id, $field_array, $specs_data );
							
							$sHtml .= '<div class="fble-td fble-col-' . $nColum . '">' . $table_cell['box_text'] . '</div>';
							$nColum++;
						}
						
						$sHtml .= '</form>';
						
						$nCont++;
					}
				}

				echo '<div data-responsive="false" class="fble fble-comparasion">' . $sHtml . '</div>';
			}
			else 
			{
				echo TEXT_NO_COMPARISON_AVAILABLE . PHP_EOL;
			}
		}
	}
	else 
	{
		echo TEXT_NO_COMPARISON_AVAILABLE . PHP_EOL;
	}
?>