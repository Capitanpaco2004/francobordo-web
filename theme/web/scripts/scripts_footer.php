<?php
	// Librerias
	use util\event;
	use util\minify\Minify;

	// Capa responsive
	echo '<div id="responsive"></div>';

	/**
	 * YKV-200-68693
	 */
		if( preg_match( '/product_info\.php/i', $_SERVER['SCRIPT_NAME'] ) && isset($_GET['attr']) ) {
			echo sprintf('<script>var attr_seleted = %d;</script>', $_GET['attr']);
		}


	// Paginación por scroll //
	echo '<script type="text/javascript">
			next_data_url = "' . (isset($sNextUrl) && $sNextUrl != '' ? $sNextUrl . '&type=json' : '')  . '";
			prev_data_url = "' . (isset($sPrevUrl) && $sPrevUrl != '' ? $sPrevUrl . '&type=json' : '') . '";
		  </script>';
	// Paginación por scroll //

	// Comprobara si esta logueado y si tiene la ultima version de los terminos generales
	$rgpd->checkShowInformationTermsGeneralCustomer();

	if (defined('NOTIFICATIONS_ACTIVE')) {
		$aIPDebug = explode(',', NOTIFICATIONS_IP_DEBUG);
		$aIPDebug = (empty($aIPDebug) ? array() : $aIPDebug);
		$notificacionActivo = ( NOTIFICATIONS_ACTIVE == 'true' || in_array( $_SERVER['REMOTE_ADDR'], $aIPDebug) ? 'true' : 'false');

		if (preg_match( '/checkout\.php/i', $_SERVER['SCRIPT_NAME'] )) {
			$notificacionActivo = 'false';
		}
		echo '
		<script type="text/javascript">
			var notificationsActive = '.$notificacionActivo.'
			var shop = "'.NOTIFICATIONS_SHOP_ID.'"
			var config = {
			    apiKey: "'.NOTIFICATIONS_APIKEY.'",
			    authDomain: "'.NOTIFICATIONS_AUTHDOMAIN.'",
			    databaseURL: "'.NOTIFICATIONS_DATABASE_URL.'",
			    projectId: "'.NOTIFICATIONS_PROJECT_ID.'",
			    storageBucket: "'.NOTIFICATIONS_STORAGE_BUCKET.'",
			    messagingSenderId: "'.NOTIFICATIONS_MESS_SENDER_ID.'"
			  };
		</script>
		';
	} else {
		echo '
		<script type="text/javascript">
			var notificationsActive = false
		</script>
		';
	}

	// Idioma wishlist
	$dxWishlist->getLngScript();

	// Captcha
	if (!isset($_SERVER['HTTP_USER_AGENT']) || stripos($_SERVER['HTTP_USER_AGENT'], 'Speed Insights') === false) {
		echo '
		<script src="https://www.google.com/recaptcha/api.js"></script>
		<script>
        function captchaSubmit(data) {
            document.getElementById("contact_us_form").submit();
        }
    	</script>
		';
	}

	// Captcha
	if( preg_match( '/contact_us\.php/i', $_SERVER['SCRIPT_NAME'] ) )
		echo '<script type="text/javascript" src="//www.google.com/recaptcha/api/js/recaptcha_ajax.js"></script>';
?>
<script src="https://apis.google.com/js/platform.js?onload=renderBadge" async defer></script>
<script>
	window.renderBadge = function() {
		var ratingBadgeContainer = document.createElement("div");
		document.body.appendChild(ratingBadgeContainer);
		window.gapi.load('ratingbadge', function() {
			window.gapi.ratingbadge.render(ratingBadgeContainer, {"merchant_id": 7605527});
		});
	}
</script>

<!--Doofinder script starts here -->
<?php if (!isset($_SERVER['HTTP_USER_AGENT']) || stripos($_SERVER['HTTP_USER_AGENT'], 'Speed Insights') === false): ?>
	<?php if( SEARCH_AUTOCOMPLETE_DOOFINDER_DENOX == 'Doofinder' ): ?>
	<script src="https://eu1-config.doofinder.com/2.x/039ab31f-abe5-4437-a1ed-f3dce3a3c809.js" async></script> 
		<!--Doofinder script ends here -->
	<?php endif; ?>
<?php endif; ?>
<?php
	// Minify
	echo Minify::getInstance()->js();

	// Evento
	echo join('', event::getInstance()->execute('front_office_footer_after_scripts'));

