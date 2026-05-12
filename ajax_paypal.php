<?php require('includes/application_top.php'); ?>
<div id="mgp-1" class="trnsf cntd-cntr Cabec pypl">
	<div class="MdlCn Barato Cabec">
		<div class="MdlCn-Bd" style="text-align: center">
			<?php 
				echo AJAX_PAYPAL; 

				// Si tenemos activo el sobrecargo
				if( MODULE_FIXED_PAYMENT_CHG_STATUS == 'true' )
				{
					// Obtenemos los sobrecargos
					$table = preg_split("/[:,]/", MODULE_FIXED_PAYMENT_CHG_TYPE);

					// Recorremos
					for ($i = 0; $i < count($table); $i += 3)
					{
						// Si es de paypal_express
						if ($table[$i] == 'paypal_express')
						{
							$od_am_percentage = $table[$i + 2];

							if (substr($od_am_percentage, 0, 1) == '%') {
								$od_am_percentage = substr($od_am_percentage, 1);
							}
						}
					}

					if( $od_am_percentage > 0 )
						echo AJAX_PAYPAL_SOBRECARGO . '<b>' . $od_am_percentage . '%<b><br>';
				}

				if( isAjax() && preg_match( '/checkout\./i', $_SERVER['HTTP_REFERER'] ) )
				{
					echo '<div class="clearfix groupButtons">';
						echo '<a id="ajax-payment-paypal" class="xbutton fright" href="ext/modules/payment/paypal/paypal_express.php">Aceptar</a>';
						echo '<a id="ajax-payment-paypal-cancel" class="xbutton fleft" href="javascript:void(0);">No gracias, seleccionar otro método de pago</a>';
					echo '</div>';
				}
			?>
		</div>
	</div>
</div> 