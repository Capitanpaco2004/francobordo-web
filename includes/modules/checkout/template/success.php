<?php
use util\event;
// GoogleTags: Cargamos el código de Google Ads para el tracking de Conversion
//GoogleTags: Cargo el Código de Analytics para Comercio Electronico Mejorado

event::getInstance()->execute("after_purchase");
?>
<?php
//Sistema de Reseñas de Clientes en Google
$resenaGoogle = true;

if ($resenaGoogle == true):
    $sCustomersEmailAddress = $_SESSION['sCustomersEmailAddress'];
    $sShippingEstimated = date('Y-m-d', strtotime("+2 day", time()));

    // Preparar la sección de productos para JavaScript
    $jsProductsArray = [];

    foreach ($products as $product) {
        // Asegurarse de que el EAN no esté vacío y no sea "0"
        if (!empty($product['ean']) && $product['ean'] != "0") {
            $jsProductsArray[] = '{"gtin":"' . $product['ean'] . '"}';
        }
    }

    // Convertir el array PHP a una cadena JSON solo si no está vacío
    $jsProductsString = !empty($jsProductsArray) ? ', "products": [' . implode(',', $jsProductsArray) . ']' : '';
    ?>

    <script src="https://apis.google.com/js/platform.js?onload=renderOptIn" async defer></script>
    <script>
      window.renderOptIn = function() {
        window.gapi.load('surveyoptin', function() {
          window.gapi.surveyoptin.render({
              "merchant_id": 7605527,
              "order_id": "<?php echo $ordersId; ?>",
              "email": "<?php echo $sCustomersEmailAddress; ?>",
              "delivery_country": "ES",
              "estimated_delivery_date": "<?php echo $sShippingEstimated; ?>"<?php echo $jsProductsString; ?>
          });
        });
      }
    </script>
<?php endif; ?>
<div class="success_image">
	<div class="success_text">
		<div class="col a12 chkc-sccs-titu afixed d-flex align-items-center"><?php echo str_replace('%ORDER%', (string)($ordersId ?? ''), CHECKOUT_SUCCESS_TITLE_ORDER); ?></div>

		<div class="col a12 ax row chkc-sccs-infr afixed">
			<div class="succes_info">
				<?php echo  CHECKOUT_SUCCESS_INFORMATION; ?>
			</div>
			<div class="success_mi_cuenta">
				<?php echo str_replace(array('%STORE%', '%LINK_ACCOUNT_ORDER%'), array(STORE_NAME, tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $ordersId)), CHECKOUT_SUCCESS_INFORMATION_2); ?>
			</div>
			<div>
				<?php echo  str_replace(array('%STORE%', '%LINK_ACCOUNT_ORDER%'), array(STORE_NAME, tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $ordersId)), CHECKOUT_SUCCESS_INFORMATION_3); ?>
			</div>
		</div>
			<div class="success_print_container_mobile">
				<a class="success_print" href="<?php echo tep_href_link('printorder.php', 'order_id=' . $ordersId); ?>"><?php echo CHECKOUT_SUCCESS_PRINT_ORDER; ?></a>
	</div>
			<a target="_blank" href="https://www.google.com/search?sa=X&sca_esv=7ce453054a09a889&rlz=1C1UEAD_esES1040ES1040&tbm=lcl&sxsrf=ACQVn08FDWxR3wsKp0nKc1gB-v0F_Kh6bg:1706689535296&q=Francobordo+Tienda+N%C3%A1utica+Rese%C3%B1as&rflfq=1&num=20&stick=H4sIAAAAAAAAAONgkxIxNDcwN7E0NDKxMLc0NjWwtDAwNN3AyPiKUcWtKDEvOT8pvyglXyEkMzUvJVHB7_DC0pLM5ESFoNTi1MMbE4sXsRKlDACLWvEeaQAAAA&rldimm=17074912487935098015&hl=es-ES&ved=2ahUKEwiZ1_j7mYeEAxVLTqQEHXNfCfoQ9fQKegQIExAF&cshid=1706689554323431&biw=1208&bih=919&dpr=1#lkt=LocalPoiReviews&lrd=0xd422d10ce842a35:0xecf64670e640bc9f,3,,,,">
				<div class="success_google_link">
					<div class="succes_google_text_first">
						<?php echo CHECKOUT_SUCCESS_GOOGLE_1 ?>
</div>
					<?php echo tep_image("/includes/modules/checkout/images/google.png",'','','','class="success_google_img')?>
					<div class="succes_google_text_second">
						<b><?php echo CHECKOUT_SUCCESS_GOOGLE_2 ?></b>
					</div>
				</div>

			</a>
	</div>
	<div class="success_print_container">
		<a class="success_print" href="<?php echo tep_href_link('printorder.php', 'order_id=' . $ordersId); ?>"><?php echo CHECKOUT_SUCCESS_PRINT_ORDER; ?></a>
	</div>
	<?php (DOWNLOAD_ENABLED == 'true' ? include(DIR_WS_COMPONENTS . 'downloads.php') : ''); ?>
</div>
<div id="chkc-fotr" class="col a12 afixed mhide">
	<p class="cntn"><a class="hv8" href="<?php echo tep_href_link(FILENAME_DEFAULT); ?>"><i class="ick-tt ick-tt-12"></i> <?php echo CHECKOUT_CART_CONTINUE_BUY; ?></a></p>
</div>
