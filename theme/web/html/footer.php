<?php
	if( ! preg_match( '/index|categories|product_info|search|^manufacturers|products_new|best_sellers|specials/i', basename( $_SERVER['SCRIPT_NAME'] ) ) )
		echo '</div>';

	if( preg_match( '/index|categories|product_info|search|^manufacturers|products_new|best_sellers|specials/i', basename( $_SERVER['SCRIPT_NAME'] ) ) )
		include( DIR_WS_COMPONENTS . 'slideManufacturersFooter.php' );

	echo '<div class="web-cntd thide mhide" id="toTop"><div></div></div>';

	echo '<div xmlns:v="http://rdf.data-vocabulary.org/#" id="brcb" class="web-cntd">';
		echo '<div class="icon tt tt-45"></div>';
		$breadcrumb->display();
	echo '</div>';

	echo '<div id="fotr">';
		echo '<div class="web-cntd d-flex">';
			echo '<div class="left">';
				echo '<a href="' . tep_href_link( '/' ) . '" title="' . TITLE . '" alt="' . TITLE . '" class="logo"><img src="'. modifyImageForTheme('theme/web/images/custom/15.png') . '"/></a>';
				echo '<a class="row addr" href="' . tep_href_link( FILENAME_CONTACT_US ) . '" title="' . TEXT_CONTACT . '">';
					echo '<div class="icon tt tt-2"></div>';
					echo '<div>';
						echo '<div class="titu">C/ San Rafael 8. Alcobendas. MADRID</div>';
						echo '<div class="text">';
							echo TEXT_FOOTER3;
						echo '</div>';
					echo '</div>';
				echo '</a>';
				//echo '<div class="row telf">';
					//echo '<div class="icon tt tt-13"></div>';
					//echo '<div class="text">916 52 88 58</div>';
				//echo '</div>';
				echo '<a href="https://wa.me/+34910514987" class="row whsp" target="_blank" rel="nofollow">';
					echo '<div class="icon tt tt-36"></div>';
					echo '<div class="text">910 51 49 87</div>';
					echo '<div class="atnds">' . TEXT_ATENDEMOS . '<br/> Whatsapp</div>';
				echo '</a>';
				echo '<a href="mailto:info@francobordo.com" class="row mail" rel="nofollow">';
					echo '<div class="icon tt tt-19"></div>';
					echo '<div class="text">info@francobordo.com</div>';
				echo '</div>';
			echo '</a>';

			echo '<div class="cntr">';
				echo '<img src="theme/web/images/custom/19.png"/>';
				echo '<img src="theme/web/images/custom/18.png"/>';
				echo '<img src="theme/web/images/custom/16.png"/>';
				echo '<img src="theme/web/images/custom/17.png"/>';
				echo '<img src="theme/web/images/custom/25.png"/>';
				echo '<img src="theme/web/images/custom/20.png"/>';

				// Trustpilot Review Collector (TrustBox) — unico widget disponible en el plan Free; CTA "Resenanos en Trustpilot".
				echo '<div class="trustpilot-widget tp-footer" data-locale="es-ES" data-template-id="56278e9abfbbba0bdcd568bc" data-businessunit-id="5ec577c88fd1b600014daf93" data-style-height="52px" data-style-width="100%" data-token="4efb3c29-4e2d-4cbc-bb3a-94cc79e4d659">';
					echo '<a href="https://es.trustpilot.com/review/francobordo.com" target="_blank" rel="noopener">Trustpilot</a>';
				echo '</div>';
			echo '</div>';

			echo '<div class="righ">';
					// SalesManago — formulario de suscripción del footer (sustituye al form nativo).
					// El iframe trae su propio título/subtítulo, así que NO repetimos el del sitio.
					// Postea directamente a SM; pide email + nombre + consentimiento.
					echo <<<'SMFOOTERFORM'
<div id="salesmanago-Iframe_f40bdfec-c37a-4c9a-af3c-ad67bdc28bd3"></div>
<script>
    (function() {
        var smIframeParentElem = document.getElementById("salesmanago-Iframe_f40bdfec-c37a-4c9a-af3c-ad67bdc28bd3");
        var smIframeElem = document.createElement("iframe");
        smIframeElem.src = "https://www.salesmanago.com/mscf/3c4292550f151571/default/Formulario_Footer_Suscripcion.htm";
        smIframeElem.style.padding = "0";
        smIframeElem.width = "100%";
        smIframeElem.id = "formulario_footer";
        smIframeElem.style.overflowY = "hidden";
        smIframeElem.style.overflowX = "hidden";
        smIframeElem.style.background = "#FFFFFF";
        smIframeElem.style.maxWidth = "100%";
        smIframeElem.style.border = "none";
        smIframeParentElem.appendChild(smIframeElem);

        window.addEventListener('message', function(event) {
        if (event.data && event.data.height) {
        var iframe = document.getElementById('formulario_footer');

        iframe.style.height = event.data.height + 'px';
        }
        });

        var iframe = document.getElementById('formulario_footer');
        iframe.onload = function() {

        iframe.contentWindow.postMessage({ action: 'sendHeight' }, '*');
        };
    }());
</script>
SMFOOTERFORM;
				echo '<div class="rdes d-flex">';
					// echo '<a href="https://www.facebook.com/francobordocom" target="_blank" class="fb hv9" title="Facebook Francobordo"><i class="fab fa-facebook-f"></i></a>';
					// echo '<a href="https://twitter.com/francobordo_com" target="_blank" class="tw hv9" title="Twitter Francobordo"><i class="fab fa-twitter"></i></a>';
					// echo '<a href="#" target="_blank" class="in hv9" title="Instagram Francobordo"><i class="fab fa-instagram"></i></a>';
					// echo '<a href="#" target="_blank" class="yt hv9" title="Youtube Francobordo"><i class="fab fa-youtube"></i></a>';
				echo '</div>';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	echo '<div id="fotr-sub">';
		echo '<div class="web-cntd">';
			echo '<div class="left">';
				echo '<a href="' . tep_href_link( 'information.php', 'info_id=3'  ) . '" title="' . TEXT_AVISO_LEGAL . '">' . TEXT_AVISO_LEGAL . '</a><span class="sepa"></span>';
				echo '<a href="' . tep_href_link( 'information.php', 'info_id=15'  ) . '" title="' . TEXT_PRIVACIDAD . '">' . TEXT_PRIVACIDAD . '</a><span class="sepa"></span>';
				echo '<a href="' . tep_href_link( 'information.php', 'info_id=6'  ) . '" title="' . TEXT_COOKIES . '">' . TEXT_COOKIES . '</a><span class="sepa"></span>';
				echo '<a href="' . tep_href_link( 'information.php', 'info_id=1'  ) . '" title="' . TEXT_ENVIOS_DEVO . '">' . TEXT_ENVIOS_DEVO . '</a>';
			echo '</div>';
			//echo '<a href="https://www.confianzaonline.es/empresas/francobordo.htm" target="_blank" rel="nofollow" class="cntr">';
			echo '<div class="cntr" style="background:none;"></div>';
			//	echo '<img src="theme/web/images/custom/21.png"/>';
			echo '</a>';
			echo '<div class="righ">';
				echo '<div class="copy">Copyright © ' . date( 'Y' ) . ' www.francobordo.com</div>';
				echo '<div class="denox hv9 order-3-mx">';
					//echo '<span>' . TEXT_DENOX . ':</span>';
					echo '<a href="http://www.denox.es/" target="_blank" title="Diseño tiendas online" class="hovr2 sp">Denox</a>';
				echo '</div>';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	/*echo '<div id="redsoc" class="infr-rdes thide mhide">';
		echo '<div>';
			echo '<a href="https://www.facebook.com/francobordocom" target="_blank" class="fb hv9" title="Facebook Francobordo"><i class="fab fa-facebook-f"></i></a>';
			echo '<a href="https://twitter.com/francobordo_com" target="_blank" class="tw hv9" title="Twitter Francobordo"><i class="fab fa-twitter"></i></a>';
			// echo '<a href="#" target="_blank" class="in hv9" title="Instagram Francobordo"><i class="fab fa-instagram"></i></a>';
			// echo '<a href="#" target="_blank" class="yt hv9" title="Youtube Francobordo"><i class="fab fa-youtube"></i></a>';
			echo '<span></span>';
		echo '</div>';
	echo '</div>';*/

	// Ventana wishlish
	echo '<a href="' . tep_href_link( 'favoritos.php' ) . '" rel="nofollow" id="fvrt-show"><i class="fa fa-heart"></i>&nbsp;<span></span></a>';

	// Menu de usuario
	_getMenuLoginUser();

	//echo '<div id="Trustbadge"></div>';

	echo '<div id="chge-lnge" class="zoom-anim-dialog mfp-hide">';
		echo '<div class="web-cntd ">';
			echo '<div class="titu">' . TEXT_SELEC_IDIOMA . ':</div>';
			echo '<div class="d-flex justify-content-center">';
				echo '<a class="row' . ($language == 'espanol' ? ' actv' : '') . '" href="' . tep_href_link (basename($PHP_SELF), tep_get_all_get_params(array('language', 'currency')) . 'language=es', $request_type ) . '">';
					echo '<img src="theme/web/images/custom/9.png"/>';
					echo '<span>Español</span>';
				echo '</a>';
				echo '<a class="row' . ($language == 'english' ? ' actv' : '') . '" href="' . tep_href_link (basename($PHP_SELF), tep_get_all_get_params(array('language', 'currency')) . 'language=en', $request_type ) . '">';
					echo '<img src="theme/web/images/custom/10.png"/>';
					echo '<span>English</span>';
				echo '</a>';
			echo '</div>';
		echo '</div>';
	echo '</div>';

	echo '<span id="menu-panel-head" class="d-flex head align-items-end">';
		echo '<i class="fclose far fa-times"></i>';
		echo '<a href="' . tep_href_link( FILENAME_DEFAULT ) . '" title="' . TITLE . '" alt="' . TITLE . '" class="logo"><img src="theme/web/images/custom/4' . ($languages_id == 3 ? '' : '_' . $languages_id) . '.png"/></a>';
		echo '<span class="wrpr d-hide d-flex ml-auto dhide">';
			if ($customerCore->hasLogin())
				echo '<a href="#my-account" class="mgp-inln lgin"><div><i class="tt tt-17"></i><span>' . $customer_first_name . '</span></div></a>';
			else
				echo '<div class="lgin tt tt-17"></div>';
			echo '<a href="#chge-lnge" class="lnge esp mgp-inln"><i class="tt tt-10"><span></span></i></a>';
			echo '<a href="' . tep_href_link( 'favoritos.php' ) . '" title="' . MY_WISH . '" class="fvrt tt tt-11"></a>';
		echo '</span>';
	echo '</span>';

	include( DIR_THEME. 'scripts/scripts_footer.php' );

	if( tep_session_is_registered('transferencia_bancaria') && $transferencia_bancaria )
	{
		echo '<a id="ajx-trns" href="ajax_transferencia_bancaria.php" class="magnific-popup-ajax"></a>';
		tep_session_unregister('transferencia_bancaria');

		echo '<script type="text/javascript">
				window.addEvent( "domready", function()
				{
					jQuery("#ajx-trns").trigger( "click" );
				});
			</script>';
	}

	// BOF: MOD QPBPP for SPCC v4.2
	if (isset($_SESSION['min_order_qty_not_met'])) {
	  unset($_SESSION['min_order_qty_not_met']);
	}
	if (isset($_SESSION['qty_blocks_not_met'])) {
	  unset($_SESSION['qty_blocks_not_met']);
	}
	// EOF: MOD QPBPP for SPCC v4.2



	// Terminación html
	echo '</body></html>';
?>