<?php require('includes/application_top.php'); ?>
<div id="mgp-1" class="trnsf cntd-cntr Cabec pypl">
	<div class="MdlCn Barato Cabec">
		<div class="MdlCn-Bd" style="text-align: center">
			<?php 
				echo AJAX_PAYPAL; 

				if( isAjax() && preg_match( '/checkout\./i', $_SERVER['HTTP_REFERER'] ) )
				{
					echo '<div class="clearfix groupButtons">';
						echo '<a id="ajax-payment-paypal-cancel" class="xbutton fright" href="ext/modules/payment/paypal/paypal_express.php">Aceptar</a>';
					echo '</div>';
				}
			?>
		</div>
	</div>
</div> 