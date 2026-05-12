<?php
  $specifications_query_raw = "select ps.specification,
                                      s.filter_display,
                                      s.enter_values,
                                      sd.specification_name,
                                      sd.specification_prefix,
                                      sd.specification_suffix
                               from " . TABLE_PRODUCTS_SPECIFICATIONS . " ps,
                                    " . TABLE_SPECIFICATION . " s,
                                    " . TABLE_SPECIFICATION_DESCRIPTION . " sd,
                                    " . TABLE_SPECIFICATION_GROUPS . " sg,
                                    " . TABLE_SPECIFICATIONS_TO_CATEGORIES . " sg2c
                               where sg.show_products = 'True'
                                 and s.show_products = 'True'
                                 and s.specification_group_id = sg.specification_group_id
                                 and sg.specification_group_id = sg2c.specification_group_id
                                 and sd.specifications_id = s.specifications_id
                                 and ps.specifications_id = sd.specifications_id
                                 and sg2c.categories_id = '" . (int) $current_category_id . "'
                                 and ps.products_id = '" . (int) $_GET['products_id'] . "'
                                 and sd.language_id = '" . (int) $languages_id . "'
                                 and ps.language_id = '" . (int) $languages_id . "'
                               order by s.specification_sort_order,
                                        sd.specification_name
                             ";
  // print $specifications_query_raw . "<br>\n";
  $specifications_query = tep_db_query ($specifications_query_raw);

  $count_specificatons = tep_db_num_rows ($specifications_query);
  if ($count_specificatons >= SPECIFICATIONS_MINIMUM_PRODUCTS ) {
    $specification_text = '<ul class="menu">' . "\n";

    while ($specifications = tep_db_fetch_array ($specifications_query) ) {
      if ($specifications['specification'] != '') {
        $specification_text .= '<li>';

          $specification_text .= '● ' . $specifications['specification_name'] . ': ';

        $specification_text .= $specifications['specification_prefix'] . ' ';

        if ($specifications['filter_display'] == 'image' || $specifications['filter_display'] == 'multiimage' || $specifications['enter_values'] == 'image' || $specifications['enter_values'] == 'multiimage') {
          $specification_text .= tep_image (DIR_WS_IMAGES . $specifications['specification'], $specifications['specification_name'], SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT);
        } else {
          $specification_text .= $specifications['specification'] . ' ';
        }

        $specification_text .= $specifications['specification_suffix'];
        $specification_text .= '</li>' . "\n";
      } // if ($specifications['specification']
    } // while ($specifications
    $specification_text .= '</ul>' . "\n";
	
	echo $specification_text;

  } //if ($count_specificatons

?>