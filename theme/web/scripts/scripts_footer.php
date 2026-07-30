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
<script src="https://apis.google.com/js/platform.js?onload=renderBadge&hl=es" async defer></script>
<script>
	window.renderBadge = function() {
		var ratingBadgeContainer = document.createElement("div");
		document.body.appendChild(ratingBadgeContainer);
		window.gapi.load('ratingbadge', function() {
			window.gapi.ratingbadge.render(ratingBadgeContainer, {"merchant_id": 7605527});
		});
	}
</script>

<!-- Trustpilot TrustBox loader (Review Collector). Carga asincrona, igual que la insignia de Google Customer Reviews de arriba. -->
<script type="text/javascript" src="//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js" async></script>

<!--Doofinder script starts here -->
<?php if (!isset($_SERVER['HTTP_USER_AGENT']) || stripos($_SERVER['HTTP_USER_AGENT'], 'Speed Insights') === false): ?>
	<?php if( SEARCH_AUTOCOMPLETE_DOOFINDER_DENOX == 'Doofinder' ): ?>
	<script src="https://eu1-config.doofinder.com/2.x/039ab31f-abe5-4437-a1ed-f3dce3a3c809.js" async></script> 
		<!--Doofinder script ends here -->
	<?php endif; ?>
<?php endif; ?>

<!-- Francobordo Meilisearch widget -->
<?php if (SEARCH_AUTOCOMPLETE_DOOFINDER_DENOX == 'Francobordo'): ?>
<script src="/theme/web/js/francobordo-search.js?v=<?php echo @filemtime($_SERVER['DOCUMENT_ROOT'].'/theme/web/js/francobordo-search.js'); ?>" defer></script>
<?php endif; ?>
<?php
	// Minify
	echo Minify::getInstance()->js();

	// Evento
	echo join('', event::getInstance()->execute('front_office_footer_after_scripts'));

	// =====================================================================
	// SalesManago — Monitoring Code + Pop-ups (2026-05-18)
	// Inert unless SALESMANAGO_STATUS=true AND the per-feature toggle=true.
	// `_smcsec` defers tracking until cookie consent — keep true for GDPR.
	// Toggles managed from: _admin → Marketing → Sales Manago
	// =====================================================================
	if (defined('SALESMANAGO_STATUS') && SALESMANAGO_STATUS === 'true'
		&& defined('SALESMANAGO_CLIENT_ID') && SALESMANAGO_CLIENT_ID !== '')
	{
		$smEndpoint    = defined('SALESMANAGO_ENDPOINT')          ? rtrim(preg_replace('#^https?://#i','',(string)SALESMANAGO_ENDPOINT), '/') : 'www.salesmanago.pl';
		$smClientId    = (string) SALESMANAGO_CLIENT_ID;
		$smInstanceId  = defined('SALESMANAGO_INSTANCE_ID')       ? (int)(string)SALESMANAGO_INSTANCE_ID : 1;
		$smRequireCons = defined('SALESMANAGO_JS_REQUIRE_CONSENT')&& SALESMANAGO_JS_REQUIRE_CONSENT === 'true';
		$smTrackingOn  = defined('SALESMANAGO_JS_TRACKING')       && SALESMANAGO_JS_TRACKING === 'true';
		$smPopupsOn    = defined('SALESMANAGO_JS_POPUPS')         && SALESMANAGO_JS_POPUPS === 'true';

		if ($smTrackingOn) {
			echo "\n<!-- SalesManago Monitoring Code -->\n";
			echo '<script type="text/javascript">' . "\n";
			echo '    var _smid = ' . json_encode($smClientId) . ';' . "\n";
			echo '    var _smapp = ' . $smInstanceId . ';' . "\n";
			echo '    var _smcsec = ' . ($smRequireCons ? 'true' : 'false') . ';' . "\n";
			echo '    (function(w, r, a, sm, s) {' . "\n";
			echo "        w['SalesmanagoObject'] = r;" . "\n";
			echo '        w[r] = w[r] || function () {( w[r].q = w[r].q || [] ).push(arguments)};' . "\n";
			echo "        sm = document.createElement('script'); sm.type = 'text/javascript'; sm.async = true; sm.src = a;" . "\n";
			echo "        s = document.getElementsByTagName('script')[0];" . "\n";
			echo '        s.parentNode.insertBefore(sm, s);' . "\n";
			echo "    })(window, 'sm', ('https:' == document.location.protocol ? 'https://' : 'http://') + " . json_encode($smEndpoint . '/static/sm.js') . ');' . "\n";
			echo '</script>' . "\n";
		}

		if ($smPopupsOn) {
			echo "\n<!-- SalesManago Pop-ups -->\n";
			echo '<script src="https://' . htmlspecialchars($smEndpoint, ENT_QUOTES) . '/dynamic/' . htmlspecialchars($smClientId, ENT_QUOTES) . '/popups.js"></script>' . "\n";
		}
	}

	// =====================================================================
	// ChatBot Pedro — pruebas internas (2026-07-29)
	// Visible SOLO desde la IP de la oficina; el proxy /chatbot/ de este
	// mismo dominio aplica el mismo gate por IP (defensa en profundidad).
	// Para abrirlo a todos los clientes: quitar la condicion de IP.
	// =====================================================================
	if (($_SERVER['REMOTE_ADDR'] ?? '') === '217.127.199.171') {
		echo "\n<!-- Francobordo ChatBot Pedro (pruebas internas) -->\n";
		echo '<script src="/chatbot/embed.js" defer></script>' . "\n";
	}

