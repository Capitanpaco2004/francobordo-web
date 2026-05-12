<?php
	use util\tools as tools;

	// Variables esperadas (inyectadas por includeTemplate desde index.php):
	//   $sUrlPage     — url de la pagina del admin (delivery_estimate.php)
	//   $aValues      — array [KEY => value] con las configuraciones actuales
	//   $aSubjects    — array [languages_id => subject] con los asuntos por idioma
	//   $aBodies      — array [languages_id => body] con los cuerpos por idioma
	//   $sShortcodes  — texto (html) con los shortcodes disponibles
?>
<form method="post" id="saveform-send" action="<?php echo tep_href_link( $sUrlPage, 'action=update' ); ?>" class="oeBox column a12 row ax">

	<!-- Bloque 1: reglas de calculo -->
	<div class="oeWrpr">
		<div class="oeTitu"><i class="fa fa-calendar"></i> Reglas de cálculo</div>
		<div class="oeCntd row ax xform xform-horizontal">

			<!-- Activar modulo -->
			<label for="DELIVERY_ESTIMATE_ENABLED" class="column a02 tright inline">Activar módulo:</label>
			<div class="column a10">
				<input type="checkbox" name="DELIVERY_ESTIMATE_ENABLED" id="DELIVERY_ESTIMATE_ENABLED" value="True" <?php echo ( $aValues['DELIVERY_ESTIMATE_ENABLED'] == 'True' ? 'checked="checked"' : '' ); ?>/>
				<label for="DELIVERY_ESTIMATE_ENABLED"><span></span></label>
				<div class="DFhelp">Si está desactivado no se calcula ni se muestra la fecha estimada en los pedidos nuevos. Los pedidos existentes siguen mostrando su fecha si la tienen.</div>
			</div>

			<div class="xline xline-dashed"></div>

			<!-- Dias laborables -->
			<label for="DELIVERY_ESTIMATE_BUSINESS_DAYS" class="column a02 tright inline">Sólo días laborables:</label>
			<div class="column a10">
				<input type="checkbox" name="DELIVERY_ESTIMATE_BUSINESS_DAYS" id="DELIVERY_ESTIMATE_BUSINESS_DAYS" value="True" <?php echo ( $aValues['DELIVERY_ESTIMATE_BUSINESS_DAYS'] == 'True' ? 'checked="checked"' : '' ); ?>/>
				<label for="DELIVERY_ESTIMATE_BUSINESS_DAYS"><span></span></label>
				<div class="DFhelp">Si está activo, al sumar los días se excluyen sábados y domingos. Por defecto se cuentan días naturales.</div>
			</div>

			<div class="xline xline-dashed"></div>

			<!-- Dias con stock -->
			<label for="DELIVERY_ESTIMATE_DAYS_IN_STOCK" class="column a02 tright">Días si hay stock:</label>
			<div class="column a10">
				<input type="number" min="0" name="DELIVERY_ESTIMATE_DAYS_IN_STOCK" id="DELIVERY_ESTIMATE_DAYS_IN_STOCK" value="<?php echo (int)$aValues['DELIVERY_ESTIMATE_DAYS_IN_STOCK']; ?>" style="width: 120px;"/>
				<div class="DFhelp">Días a sumar a la fecha de compra cuando todos los productos del pedido tienen stock disponible.</div>
			</div>

			<div class="xline xline-dashed"></div>

			<!-- Dias sin stock -->
			<label for="DELIVERY_ESTIMATE_DAYS_NO_STOCK" class="column a02 tright">Días si algún producto sin stock:</label>
			<div class="column a10">
				<input type="number" min="0" name="DELIVERY_ESTIMATE_DAYS_NO_STOCK" id="DELIVERY_ESTIMATE_DAYS_NO_STOCK" value="<?php echo (int)$aValues['DELIVERY_ESTIMATE_DAYS_NO_STOCK']; ?>" style="width: 120px;"/>
				<div class="DFhelp">Días a sumar cuando algún producto tiene stock &lt; 0 y &gt; -800, o cuando la cantidad solicitada supera el stock disponible.</div>
			</div>

			<div class="xline xline-dashed"></div>

			<!-- Dias bajo pedido -->
			<label for="DELIVERY_ESTIMATE_DAYS_BACKORDER" class="column a02 tright">Días si bajo pedido:</label>
			<div class="column a10">
				<input type="number" min="0" name="DELIVERY_ESTIMATE_DAYS_BACKORDER" id="DELIVERY_ESTIMATE_DAYS_BACKORDER" value="<?php echo (int)$aValues['DELIVERY_ESTIMATE_DAYS_BACKORDER']; ?>" style="width: 120px;"/>
				<div class="DFhelp">Días a sumar cuando algún producto tiene stock = -800 (bajo pedido).</div>
			</div>
		</div>
	</div>

	<!-- Bloque 2: email al cliente (por idioma con toggle) -->
	<div class="oeWrpr" style="margin-top: 20px;">
		<div class="oeTitu"><i class="fa fa-envelope"></i> Email al cliente cuando se modifica la fecha</div>
		<div class="oeCntd row ax xform xform-horizontal">
			<?php echo tools::getInputLanguages( 'email_subject', 'Asunto:', $aSubjects, $sShortcodes, '', 10, true ); ?>
			<div class="xline xline-dashed"></div>
			<?php echo tools::getInputLanguages( 'email_body', 'Cuerpo del email:', $aBodies, 'Usa los shortcodes para insertar los valores dinámicos. Donde pongas <code>{FECHA_ENTREGA}</code> se insertará la nueva fecha en formato dd/mm/aaaa.', '', 10, false, false ); ?>
		</div>
	</div>

	<!-- Submit oculto (lo dispara el boton "Guardar" de la cabecera via id="saveform") -->
	<input type="submit" style="display: none;" />
</form>

<!-- Popup de vista previa del email (carga el email completo via iframe con el layout UHtmlEmails) -->
<div id="delivery-estimate-preview-popup" class="mfp-hide mfp-white" style="background: #fff; max-width: 820px; margin: 0 auto; padding: 0; border-radius: 4px; overflow: hidden;">
	<div style="background: #f5f5f5; border-bottom: 1px solid #e0e0e0; padding: 14px 20px;">
		<div style="font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Asunto</div>
		<div id="delivery-estimate-preview-subject" style="font-size: 16px; font-weight: bold; color: #333;"></div>
	</div>
	<div style="background: #fafafa; border-bottom: 1px solid #e0e0e0; padding: 10px 20px; font-size: 13px; color: #666;">
		<div><strong>Para:</strong> Juan Pérez &lt;juan.perez@ejemplo.com&gt;</div>
		<div><strong>De:</strong> <?php echo htmlspecialchars( defined('STORE_NAME') ? STORE_NAME : 'Tu tienda', ENT_QUOTES, 'UTF-8' ); ?></div>
	</div>
	<iframe id="delivery-estimate-preview-iframe" style="width: 100%; height: 640px; border: 0; background: #fff; display: block;"></iframe>
	<div style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 10px 20px; font-size: 11px; color: #999; font-style: italic;">
		Valores de ejemplo — los shortcodes se sustituirán con los datos reales del pedido del cliente. Incluye cabecera, logo y pie del email tal como lo recibirá.
	</div>
</div>

<!-- Popup "Enviar email de prueba" -->
<div id="delivery-estimate-sendtest-popup" class="mfp-hide mfp-white" style="background: #fff; max-width: 460px; margin: 0 auto; padding: 28px 32px; border-radius: 4px;">
	<h3 style="margin: 0 0 8px 0; font-size: 18px; color: #333;"><i class="fa fa-paper-plane" style="margin-right:8px; color:#2bb0e2;"></i>Enviar email de prueba</h3>
	<p style="margin: 0 0 20px 0; color: #666; font-size: 13px; line-height: 18px;">Se enviará un email de prueba con datos ficticios a la dirección que indiques, usando la plantilla del idioma que selecciones. Útil para comprobar cómo se ve el email al llegar al cliente.</p>

	<form method="post" id="delivery-estimate-sendtest-form" action="<?php echo tep_href_link( $sUrlPage, 'action=send_test' ); ?>">
		<label for="delivery-estimate-sendtest-email" style="display:block; margin-bottom:6px; font-size:13px; color:#333; font-weight:bold;">Email destino:</label>
		<input type="email" name="test_email" id="delivery-estimate-sendtest-email" required placeholder="tu@email.com" style="width:100%; padding:10px; font-size:14px; border:1px solid #ccc; border-radius:3px; margin-bottom:16px; box-sizing:border-box;"/>

		<label for="delivery-estimate-sendtest-lang" style="display:block; margin-bottom:6px; font-size:13px; color:#333; font-weight:bold;">Idioma de la plantilla:</label>
		<select name="test_lang_id" id="delivery-estimate-sendtest-lang" style="width:100%; padding:10px; font-size:14px; border:1px solid #ccc; border-radius:3px; margin-bottom:20px; box-sizing:border-box;">
			<?php foreach( tep_get_languages() as $aLang ): ?>
				<option value="<?php echo (int)$aLang['id']; ?>"<?php if( defined('DEFAULT_LANGUAGE_ID') && (int)$aLang['id'] === (int)DEFAULT_LANGUAGE_ID ) echo ' selected'; ?>><?php echo htmlspecialchars( $aLang['name'], ENT_QUOTES, 'UTF-8' ); ?></option>
			<?php endforeach; ?>
		</select>

		<div style="text-align: right;">
			<a href="javascript:void(0);" class="mfp-close" title="Cancelar" style="color:#000000; text-decoration:none; font-size:18px; margin-right:14px; vertical-align:middle;"><i class="fa fa-times"></i></a>
			<button type="submit" class="xbutton hv8 small verde" style="vertical-align:middle;"><i class="fa fa-paper-plane"></i> Enviar prueba</button>
		</div>
	</form>
</div>

<script type="text/javascript">
(function(){
	var previewUrl = <?php echo json_encode( tep_href_link( $sUrlPage, 'action=preview', 'NONSSL' ) ); ?>;
	// Asunto (para rellenar el header del popup) — sustituimos shortcodes client-side sólo para el subject
	var sampleSubject = {
		'{ORDERS_ID}'     : '12345',
		'{CUSTOMER_NAME}' : 'Juan Pérez',
		'{FECHA_ENTREGA}' : '<?php echo date('d/m/Y', strtotime('+7 days')); ?>',
		'{COMENTARIO}'    : '',
		'{STORE_NAME}'    : <?php echo json_encode( defined('STORE_NAME') ? STORE_NAME : 'Tu tienda' ); ?>,
		'{LINK_PEDIDO}'   : ''
	};

	function replaceSubjectShortcodes( text ) {
		Object.keys( sampleSubject ).forEach( function( key ) {
			text = text.split( key ).join( sampleSubject[key] );
		});
		return text;
	}

	function getActiveLanguageId() {
		// input-language visibles tienen display distinto de 'none'. Usamos el subject como referencia.
		var candidates = document.querySelectorAll( '.input-language[name^="email_subject["]' );
		for( var i = 0; i < candidates.length; i++ ) {
			if( candidates[i].style.display !== 'none' ) {
				var match = candidates[i].name.match( /\[(\d+)\]/ );
				if( match ) return match[1];
			}
		}
		if( candidates.length > 0 ) {
			var match = candidates[0].name.match( /\[(\d+)\]/ );
			if( match ) return match[1];
		}
		return null;
	}

	function getSubjectFor( langId ) {
		var input = document.querySelector( 'input[name="email_subject[' + langId + ']"]' );
		return input ? input.value : '';
	}

	function getBodyFor( langId ) {
		var targetName = 'email_body[' + langId + ']';

		// TinyMCE: buscar editor cuyo textarea original tenga este name
		if( typeof tinymce !== 'undefined' && tinymce.editors && tinymce.editors.length ) {
			for( var i = 0; i < tinymce.editors.length; i++ ) {
				var ed = tinymce.editors[i];
				if( ed && ed.targetElm && ed.targetElm.name === targetName ) {
					return ed.getContent(); // contenido vivo, aún sin guardar
				}
			}
		}
		// Fallback: textarea directo
		var ta = document.querySelector( 'textarea[name="' + targetName + '"]' );
		return ta ? ta.value : '';
	}

	function renderPreviewInIframe( subject, body ) {
		// Mostramos el subject ya con shortcodes sustituidos en la barra del popup
		document.getElementById( 'delivery-estimate-preview-subject' ).textContent = replaceSubjectShortcodes( subject );

		// POST al endpoint; devuelve el HTML envuelto con el layout UHtmlEmails.
		// Lo cargamos en el iframe via srcdoc.
		var iframe = document.getElementById( 'delivery-estimate-preview-iframe' );
		iframe.srcdoc = '<html><body style="font-family:Arial;padding:40px;color:#999;">Cargando vista previa...</body></html>';

		var fd = new FormData();
		fd.append( 'subject', subject );
		fd.append( 'body', body );

		fetch( previewUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin'
		})
		.then( function( res ) { return res.text(); })
		.then( function( html ) {
			iframe.srcdoc = html;
		})
		.catch( function( err ) {
			iframe.srcdoc = '<html><body style="font-family:Arial;padding:40px;color:#c00;">Error cargando la vista previa: ' + err + '</body></html>';
		});
	}

	function openPreview() {
		var langId = getActiveLanguageId();
		if( ! langId ) {
			alert( 'No se detectó idioma activo.' );
			return;
		}

		var subject = getSubjectFor( langId );
		var body    = getBodyFor( langId );

		renderPreviewInIframe( subject, body );

		if( typeof jQuery !== 'undefined' && typeof jQuery.magnificPopup !== 'undefined' ) {
			jQuery.magnificPopup.open({
				items: { src: '#delivery-estimate-preview-popup', type: 'inline' },
				mainClass: 'mfp-fade',
				removalDelay: 160
			});
		}
		else {
			// Fallback: nueva ventana con el preview directamente (POST vía form programático)
			var w = window.open( '', 'delivery_preview', 'width=860,height=760' );
			var tmp = document.createElement( 'form' );
			tmp.method = 'POST';
			tmp.action = previewUrl;
			tmp.target = 'delivery_preview';
			[ ['subject', subject], ['body', body] ].forEach( function( pair ) {
				var inp = document.createElement( 'input' );
				inp.type = 'hidden'; inp.name = pair[0]; inp.value = pair[1];
				tmp.appendChild( inp );
			});
			document.body.appendChild( tmp );
			tmp.submit();
			document.body.removeChild( tmp );
		}
	}

	function openSendTestPopup() {
		if( typeof jQuery !== 'undefined' && typeof jQuery.magnificPopup !== 'undefined' ) {
			jQuery.magnificPopup.open({
				items: { src: '#delivery-estimate-sendtest-popup', type: 'inline' },
				mainClass: 'mfp-fade',
				removalDelay: 160,
				focus: '#delivery-estimate-sendtest-email'
			});
		}
		else {
			// Fallback simple con prompt
			var email = prompt( 'Email destino para la prueba:' );
			if( ! email ) return;
			var f = document.getElementById( 'delivery-estimate-sendtest-form' );
			document.getElementById( 'delivery-estimate-sendtest-email' ).value = email;
			f.submit();
		}
	}

	document.addEventListener( 'click', function( e ) {
		if( ! e.target.closest ) return;

		if( e.target.closest( '#delivery-estimate-preview-btn' ) ) {
			e.preventDefault();
			openPreview();
			return;
		}
		if( e.target.closest( '#delivery-estimate-sendtest-btn' ) ) {
			e.preventDefault();
			openSendTestPopup();
			return;
		}
	});
})();
</script>
