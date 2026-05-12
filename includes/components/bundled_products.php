<?php
	function display_bundle($bundle_id, $bundle_price)
	{
		global $languages_id, $aProductoInfo, $currencies;
  
		$bundle_sum = 0;
		
		$aDatos = tep_db_query("SELECT pd.products_name, pb.*, p.products_bundle, p.products_id, p.products_model, p.products_price, p.products_image
								FROM " . TABLE_PRODUCTS . " p
								INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id=pd.products_id 
								INNER JOIN " . TABLE_PRODUCTS_BUNDLES . " pb ON pb.subproduct_id=pd.products_id
								WHERE pb.bundle_id = " . (int)$bundle_id . " and language_id = '" . (int)$languages_id . "'" );

		echo '<div class="fich-bg">';
			echo '<div class="web-cntd fich-wrpr-rows">';
				echo '<div class="wrpr-titu">' . TEXT_PRODUCTS_BY_BUNDLE . '</div>';

				echo '<div class="wrpr-rows">';
					while( $aDato = tep_db_fetch_array($aDatos) )
					{
						$sUrlProduct = tep_href_link( FILENAME_PRODUCT_INFO, ($cPath ? 'cPath=' . $cPath . '&' : '') . 'products_id=' . $aDato['products_id']);

						echo '<div class="row d-flex flex-column-tx flex-column-mx align-items-center">';
							echo '<div class="col-1 d-flex align-items-center">';
								echo '<a href="' . $sUrlProduct . '" target="_blank">';
									echo tep_image( DIR_WS_IMAGES . 'productos/' . $aDato['products_image'], $aDato['products_name'], 66, 72, 'class="imge"', false );
								echo '</a>';

								echo '<div>';
									echo '<a class="titu" href="' . $sUrlProduct . '" target="_blank">'  . $aDato['subproduct_qty'] . 'x ' . $aDato['products_name'] . ($aDato['products_model'] != '' ? ' (' . $aDato['products_model'] . ')' : '') . '</a>';

									/**
									 * #EXE-972-18979
									 * @author Daniel Lucia <daniel.lucia@denox.es>
									 */
									//echo getAttributesSelectHtml(intval($aDato['products_id']), $aDato);
									
								echo '</div>';
							echo '</div>';
							echo '<div class="col-2 d-flex align-items-center">';
								echo '<div class="prco">' . $currencies->display_price( $aDato['products_price'], tep_get_tax_rate($aProductoInfo['products_tax_class_id']) ) . '</div>';
							echo '</div>';
						echo '</div>';

						if( $aDato['products_bundle'] == "yes" )
							display_bundle( $aDato['subproduct_id'], $aDato['products_price'] );
						  
						$bundle_sum += $aDato['products_price'] * $aDato['subproduct_qty'];
					}
					
					$bundle_saving = $bundle_sum - $bundle_price;
					$bundle_sum = $currencies->display_price( $bundle_sum, tep_get_tax_rate($aProductoInfo['products_tax_class_id']) );
					$bundle_saving =  $currencies->display_price($bundle_saving, tep_get_tax_rate($aProductoInfo['products_tax_class_id']));

					echo '<div class="fich-box-titl-rojo row d-flex flex-column-tx flex-column-mx align-items-center">';
						echo '<div class="col-1 d-flex align-items-center">';
							echo TEXT_IT_SAVE .' ' . $bundle_saving;
						echo '</div>';
						echo '<div class="col-2 d-flex align-items-center">';
							echo TEXT_RATE_COSTS . ' ' . $bundle_sum;
						echo '</div>';
					echo '</div>';
				echo '</div>';

			echo '</div>';
		echo '</div>';
	}
	
if( $aProductoInfo['products_bundle'] == "yes" ) 
	display_bundle($_GET['products_id'], $aProductoInfo['products_price']);
?>