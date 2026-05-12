<?php
	include( DIR_WS_CLASSES  . 'specifications.php' );
	$spec_object = new Specifications();

	$specs_query_raw = "select s.specifications_id,
                               s.products_column_name,
                               s.filter_class,
                               s.filter_show_all,
                               s.filter_display,
                               sd.specification_name,
                               sd.specification_prefix,
                               sd.specification_suffix
                        from " . TABLE_SPECIFICATION . " s,
                             " . TABLE_SPECIFICATION_DESCRIPTION . " sd,
                             " . TABLE_SPECIFICATION_GROUPS . " sg,
                             " . TABLE_SPECIFICATIONS_TO_CATEGORIES . " s2c
                        where s.specification_group_id = sg.specification_group_id
                          and sg.specification_group_id = s2c.specification_group_id
                          and sd.specifications_id = s.specifications_id
                          and s2c.categories_id = '" . $current_category_id . "'
                          and s.show_filter = 'True'
                          and sg.show_filter = 'True'
                          and sd.language_id = '" . $languages_id . "'
                        order by s.specification_sort_order,
								sd.specification_name";

	$specs_query = tep_db_query ($specs_query_raw);

	$box_text =  ''; // Build an HTML string to go into the text part of the box
	
    while( $specs_array = tep_db_fetch_array ($specs_query) )
	{
		// Retrieve the GET vars, sanitize, and assign to variables
		// Variable names are the letter "f" followed by the specifications_id for that spec.
		$var = 'f' . $specs_array['specifications_id'];
		$$var = '0';

		if( isset ($_GET[$var]) && $_GET[$var] != '' )
		{
			// Sanitize variables to prevent hacking
			$$var = tep_clean_get__recursive ($_GET[$var]);

			// Get rid of extra values if Select All is selected
			$$var = tep_select_all_override ($$var);
		}

		$filters_query_raw = "select sf.specification_filters_id,
								     sfd.filter
							  from " . TABLE_SPECIFICATIONS_FILTERS . " sf,
								   " . TABLE_SPECIFICATIONS_FILTERS_DESCRIPTION . " sfd
							  where sfd.specification_filters_id = sf.specification_filters_id
									and sf.specifications_id = '" . (int) $specs_array['specifications_id'] . "'
									and sfd.language_id = '" . $languages_id . "'
							  order by sf.filter_sort_order,
									   sfd.filter";

		$filters_query = tep_db_query ($filters_query_raw);

		$count_filters = tep_db_num_rows ($filters_query);
		$filters_select_array = array();

		if (defined( 'SPECIFICATIONS_FILTER_MINIMUM' ) && $count_filters >= SPECIFICATIONS_FILTER_MINIMUM)
		{
			$filters_array = array();
			$box_text .=  '<div class="cntd"><label>' . $specs_array['specification_name'] . '</label>';

			$filter_index = 0;
			if ($specs_array['filter_show_all'] == 'True') 
			{
				$count = 1;
				if (SPECIFICATION_FILTER_NO_RESULT != 'normal' || SPECIFICATIONS_FILTER_SHOW_COUNT == 'True')
				{
					// Filter ID is set to 0 so no filter will be applied
					$count = $spec_object->getFilterCount ('0', $specs_array['specifications_id'], $specs_array['filter_class'], $specs_array['products_column_name']);
				}
				
				// The ID value must be set as a string, not an integer
				$filters_select_array[$filter_index] = array ('id' => '0', 'text' => TEXT_SHOW_ALL, 'count' => $count );
				
				$filter_index++;
			}

			$previous_filter = 0;
			$previous_filter_id = 0;

			while ($filters_array = tep_db_fetch_array ($filters_query) ) 
			{
				$filter_id = $filters_array['filter'];

				// Format currency if the column is a price
				if ($specs_array['products_column_name'] == 'products_price' || $specs_array['products_column_name'] == 'final_price')
				{
					$previous_filter = $currencies->format ($previous_filter);
					$filter_text = $currencies->format ($filters_array['filter']);
				}
				else
				{
					$filter_text = $filters_array['filter'];

					if( !empty( $specs_array['specification_prefix'] ) )
						$filter_text = $specs_array['specification_prefix'] . ' ' . $filter_text;
				
					if( !empty( $specs_array['specification_suffix'] ) )
						$filter_text = $filter_text . ' ' . $specs_array['specification_suffix'];
				}

				// Set up the range if class is range
				if ($specs_array['filter_class'] == 'range')
				{
					$filter_text = $previous_filter . ' - ' . $filter_text;
					$filter_id = $previous_filter_id . '-' . $filters_array['filter'];

					$previous_filter = $filters_array['filter'];
					$previous_filter_id = $filters_array['filter'];
				}

				$count = 1;
				if (SPECIFICATION_FILTER_NO_RESULT != 'normal' || SPECIFICATIONS_FILTER_SHOW_COUNT == 'True')
					$count = $spec_object->getFilterCount ($filter_id, $specs_array['specifications_id'], $specs_array['filter_class'], $specs_array['products_column_name']);

				$filters_select_array[$filter_index] = array ('id' => ($filter_id), 'text' => $filter_text, 'count' => $count );

				$filter_index++;
			}

			// For the Range class only, add an extra filter at the end for maximum value +
			if ($specs_array['filter_class'] == 'range')
			{
				if( $specs_array['products_column_name'] == 'products_price' || $specs_array['products_column_name'] == 'final_price' )
					$previous_filter = $currencies->format ($previous_filter);

				$count = 1;

				if( SPECIFICATION_FILTER_NO_RESULT != 'normal' || SPECIFICATIONS_FILTER_SHOW_COUNT == 'True' )
					$count = $spec_object->getFilterCount ($previous_filter_id, $specs_array['specifications_id'], $specs_array['filter_class'], $specs_array['products_column_name']);

				
				$filters_select_array[$filter_index] = array ('id' => $previous_filter_id, 'text' => $previous_filter . '+', 'count' => $count );
			} // if ($specs_array['filter_class'] == 'range'

			$box_text .= tep_get_filter_string ('pulldown', $filters_select_array, FILENAME_PRODUCTS_FILTERS, $var, $$var) . '</div>';
		} // if ($count_filters
    }

	$bShowComparison = function_exists('tep_has_spec_group') && tep_has_spec_group($current_category_id, 'show_comparison');

	if( $bShowComparison || $box_text != '' )
	{
		echo '<div class="flttop"><div class="d-flex web-cntd">';

		echo '<div id="fltr-spcf" class="d-flex xform">';
			echo $box_text;
		echo '</div>';
	}

	// Si tenemos que mostrar el boton comparar
	if( $bShowComparison )
	{
		echo tep_draw_form('compare', tep_href_link(FILENAME_COMPARISON, 'cPath=' . $cPath), 'get', 'id="form-cmpr"');
			echo tep_draw_hidden_field('cPath', $cPath);
			echo '<textarea name="ids"></textarea>';
			echo '<button id="bton-cmpr" class="hvr9" type="submit">' . TEXT_COMPARAR_PRODUCTOS . '</button>';
		echo '</form>';
	}

	if( $bShowComparison || $box_text != '' )
		echo '</div></div>';
?>