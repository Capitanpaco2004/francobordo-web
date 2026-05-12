<?php	 	

/*
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

/*
Google Product feed configuration for The Feedmachine Solution
based on google-simple.php by: Lech Madrzyk
----------------------------
This configuration is complient with the Google-Feed specifications of march 2012.
It has to be used together with the modified feedmachine.php file which includes the 'IS_IN_STOCK' Keyword definition.
*/

define( 'DIR_WS_HTTP_CATALOG', '/' );

$feed_config = array('name' => 'Google Product Search',
                     'precio' => '0',
                     'authors' => 'raiwa',
                     'filename' => 'doofinder.txt', //change the name and the filename to a unique name for each language and country
                     'schema_version' => '2.0',
                     'fields' => array('id'               =>   array('output' => 'products_id',
                                                                     'type' => 'DB'
                                                                    ),
                                               
                                       'title'            =>   array('output' => 'products_name',
                                                                     'type' => 'DB',
                                                                     'options' => array('STRIP_HTML', 'STRIP_CRLF')
                                                                    ),
                                       'price'            =>   array('output' => 'FM_RS_final_price_with_TAX',
                                                                     'type' => 'FUNCTION'
                                                                    ),
                                       'brand'            =>   array('output' => 'manufacturers_name',
                                                                     'type' => 'DB',
                                                                     'options' => array('STRIP_HTML', 'HTML_ENTITIES', 'STRIP_CRLF')
                                                                    ),
                                       'mpn'              =>   array('output' => 'products_model',
                                                                     'type' => 'DB'
                                                                    ),
                         'google_product_category'        =>   array('output' => 'FM_RS_google_categories_es_sp_2',  //change the name to the name used for the function
                                                                     'type' => 'FUNCTION'
                                                                    ),
                                       'product_type'     =>   array('output' => 'CATEGORY_TREE',
                                                                     'type' => 'KEYWORD',
                                                                     'options' => array('STRIP_HTML', 'STRIP_CRLF')
                                                                    ),
                                       'link'             =>   array('output' => 'PRODUCTS_URL',
                                                                     'type' => 'KEYWORD'
                                                                    ),
                                       'image_link'       =>   array('output' => 'IMAGE_URL',
                                                                     'type' => 'KEYWORD'
                                                                    ),
                                       'condition'        =>   array('output' => 'new', //change to 'used' or 'refurbished' if needed
                                                                     'type' => 'VALUE'
                                                                    ),
                                       'description'      =>   array('output' => 'products_description',
                                                                     'type' => 'DB',
                                                                     'options' => array('STRIP_HTML', 'STRIP_CRLF')
                                                                    ),
                                       'shipping_weight'  =>   array('output' => 'FM_RS_shipping_weight_and_unit',
                                                                     'type' => 'FUNCTION',
                                                                    ),
                                       'availability'        =>   array('output' => 'IS_IN_STOCK',
                                                                     'type' => 'KEYWORD'
                                                                    )
                                      ),
                     'encoding' => 'false', //'utf8' or false for standard encoding
                     'currency_decimal_override' => false,
                     'currency_thousands_override' => '',
                     'add_field_names' => true,
                     'category_tree_seperator' => ' > ',
                     'seperator' => "\t",
                     'text_qualifier' => '',
                     'newline' => "\n",
                     'include_record_function' => ''
                    );

//FEED FUNCTIONS BEGIN

function FM_RS_product_id_es_sp_2($product) {
  return 'Francobordo' . $product['products_id'] . '_es_sp';
}

function FM_RS_google_categories_es_sp_2($product) {
	$output_field_category = ($product['parent_id'] > 0) ? $product['parent_id'] : $product['categories_id'];
	return (($output_field_category == 1) ? 'Google > Category > Tree1' :
		(($output_field_category == 2) ? 'Google > Category > Tree2':
		 (($output_field_category == 3) ? 'Google > Category > Tree3':
		  (($output_field_category == 4) ? 'Google > Category > Tree4':
		   (($output_field_category == 5) ? 'Google > Category > Tree5':
		    (($output_field_category == 6) ? 'Google > Category > Tree6':
		     (($output_field_category == 7) ? 'Google > Category > Tree7':
		      (($output_field_category == 8) ? 'Google > Category > Tree8':
		       (($output_field_category == 9) ? 'Google > Category > Tree9':
			(($output_field_category == 10) ? 'Google > Category > Tree10':
			 (($output_field_category == 11) ? 'Google > Category > Tree11':
			  (($output_field_category == 12) ? 'Google > Category > Tree12':
			   (($output_field_category == 13) ? 'Google > Category > Tree13':
			    (($output_field_category == 14) ? 'Google > Category > Tree14':
			     (($output_field_category == 15) ? 'Google > Category > Tree15':
			      (($output_field_category == 10000) ? '':
			       (($output_field_category == 10000) ? '':
				(($output_field_category == 10000) ? '':
				 (($output_field_category == 10000) ? '':
				  (($output_field_category == 10000) ? '':
				  	  ''))))))))))))))))))));
	}

function FM_RS_final_price_with_tax($product) 
{
	
	$sql = "SELECT specials_new_products_price from specials where products_id = '" . $product['products_id'] . "' AND status = '1' limit 1";
    $special_query  = tep_db_query($sql);
    $special_result = tep_db_fetch_array($special_query);
    if ($special_result['specials_new_products_price'] > 0) {
        $price = $special_result['specials_new_products_price'];
    } else {
		$price =$product['final_price'];
    }
	
	$price = round(($price) * (1 + ((tep_get_tax_rate($product['products_tax_class_id']) / 100))), 2);
    return $price;
 
}	
//FEED FUNCTIONS END

?>