<?php
require 'includes/application_top.php';
require 'includes/modules/payment/paypal_vzero.php';
include 'includes/languages/' . $language . '/modules/payment/paypal_vzero.php';
?>
<div id="mgp-1" class="trnsf Container Cabec pypl">
	<div class="MdlCn Barato Cabec">
		<div class="MdlCn-Bd" style="text-align: center">
			<?php
			$payment_module = new paypal_vzero();
			//echo $payment_module->process_button();
			?>
		</div>
	</div>
</div>