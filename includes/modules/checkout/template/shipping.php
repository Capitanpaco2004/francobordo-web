<?php

use util\tools;

if (isset($_SESSION['module_shipping_estimator'])) {
    require_once DIR_WS_CLASSES . 'shipping.php';
    $shipping_modules = new \shipping;
    $shipping = $shipping_modules->select($_SESSION['module_shipping_estimator']);
}

?>
<form id="checkout_form" action="<?php echo tep_href_link(FILENAME_CHECKOUT_SHIPPING . 'process/'); ?>" method="post" class="col chkc-right">
	<div id="chkt-bton" class="checkout_form_target xbutton verde hv9 expand bton-cntn dhide thide"><?php echo CHECKOUT_CONTINUE; ?></div>

	<?php echo $messageStack->show('message_error'); ?>

	<?php if (is_array($quotes) && sizeof($quotes) > 0 && sizeof($quotes[0]) > 0): ?>

		<div class="chkc-titu2"><?php echo CHECKOUT_SHIPPING_TITLE_SELECT; ?></div>

		<div class="chkc-mthh-wrpr" data-shipping-method>
			<?php foreach ($quotes as $aQuote): ?>
				<?php foreach ($aQuote['methods'] as $aMethod): ?>
					<?php $checked = (($aQuote['id'] . '_' . $aMethod['id'] == $shipping['id'] || $aQuote['id'] == $shipping['id']) ? true : false);?>
					<?php $sId = $aQuote['id'] . '_' . $aMethod['id'];?>
					<div class="chkc-mthh ax mx row aflex mflex <?php echo ($checked ? 'actv' : ''); ?>" data-method>
						<?php if (!isset($aQuote['error'])): ?>
							<div class="prco afixed" data-price><?php echo $currencies->format(tep_add_tax($aMethod['cost'], isset($aQuote['tax']) ? $aQuote['tax'] : 0)); ?></div>
							<div class="inpt xform afixed">
								<?php echo tep_draw_radio_field('shipping', $sId, $checked, 'id="' . $sId . '"'); ?><label for="<?php echo $sId; ?>"><span></span></label>
							</div>
						<?php endif; ?>
						<div class="imge afixed">
							<img src="<?php echo (isset($aQuote['icon']) && file_exists($sPathModule . '/images/' . $aQuote['icon']) ? $sPathModule . '/images/' . $aQuote['icon'] : $sPathModule . '/images/shipping_default.png'); ?>"/>
						</div>
						<div class="infr-wrp mfixed <?php echo ($aMethod['id'] == 'retira' || $aMethod['id'] == 'seurpunto' || $aMethod['id'] == 'correosoficina' || $aMethod['id'] == 'cexpunto') ? 'zindex' : ''; ?>">
							<div class="titu">
								<b data-title><?php echo $aQuote['module']; ?></b><br/>
							</div>
							<div data-info class="infr">
								<?php if (!isset($aQuote['error'])): ?>
									<?php echo $aMethod['title']; ?>
								<?php else: ?>
									<?php echo $messageStack->show(array('class' => 'eror', 'text' => $aQuote['error'])); ?>
								<?php endif; ?>

								<?php 
									// Inicio, select con las tiendas
									if ($aMethod['id']  == 'retira') {
															
										echo '<div class="selct">';
										echo '<label>' . TEXT_SELECT_STORE . '</label>';

										// La tienda de Denia (CP 03700) solo se ofrece si el cliente tiene
										// alguna direccion en ese CP (gate por libreta de direcciones).
										$bDeniaAllowed = false;
										if (isset($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] > 0) {
											$rDenia = tep_db_query("SELECT 1 FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id = '" . (int)$_SESSION['customer_id'] . "' AND entry_postcode LIKE '03700%' LIMIT 1");
											if (tep_db_num_rows($rDenia) > 0) {
												$bDeniaAllowed = true;
											}
										}

										// Consultamos las tiendas (excluyendo Denia si no procede)
										$sStoreSql = 'SELECT id_store, store_name, store_address, store_cost FROM store WHERE store_status = 1';
										if (!$bDeniaAllowed) {
											$sStoreSql .= " AND store_address NOT LIKE '%03700%'";
										}
										$sStoreSql .= ' ORDER BY store_name DESC';
										$aDatos = tep_db_query($sStoreSql);

                                        echo '<select name="store_id" class="shipping-change-store">';

										while ($aDato = tep_db_fetch_array($aDatos)) {
                                            $store_cost = $currencies->format(tep_add_tax($aDato['store_cost'], tep_get_tax_rate(1)));
                                            echo '<option data-price="' . $store_cost . '" value="' . $aDato['id_store'] . '" '. ($aDato['id_store'] == $store_id ? ' selected ' : '') . '>'  . $aDato['store_name'] . ' (' . $aDato['store_address'] . ')</option>';
										}

                                        echo '</select>';
                                        echo '</div>';

									}

									// Inicio, selector de punto de recogida SEUR (lista + mapa Leaflet)
									if ($aMethod['id'] == 'seurpunto' && class_exists('seurpunto')) {
										$aSeurPuntos = seurpunto::puntos($order->delivery['postcode'], $order->delivery['city'], $order->delivery['country']['iso_code_2']);
										$sSeurSel = isset($_SESSION['seur_pudo_sel']['id']) ? $_SESSION['seur_pudo_sel']['id'] : '';
										echo '<div class="selct" id="seurPudoBox">';
										// data-price: el JS del checkout pisa el precio de la fila con el
										// data-price de la opción seleccionada al cambiar cualquier select.
										$sSeurPrice = $currencies->format(tep_add_tax($aMethod['cost'], isset($aQuote['tax']) ? $aQuote['tax'] : 0));
										$sSeurIvaZ = isset($aQuote['tax']) ? $aQuote['tax'] : 0;
										$kgSeurZ = (isset($GLOBALS['cart']) && is_object($GLOBALS['cart']) && method_exists($GLOBALS['cart'], 'show_weight')) ? (float) $GLOBALS['cart']->show_weight() : 0.0;
										if ($kgSeurZ <= 0 && isset($GLOBALS['shipping_weight'])) $kgSeurZ = (float) $GLOBALS['shipping_weight'];
										$seurPrecioZonaZ = function ($zona) use ($kgSeurZ, $sSeurIvaZ, $currencies, $sSeurPrice) {
										    if ($kgSeurZ <= 0 || !class_exists('seurpunto')) return $sSeurPrice;
										    $b = seurpunto::costePorPeso($kgSeurZ, $zona) * seurpunto::FUEL;
										    $cir = round($b * (1 + $sSeurIvaZ / 100) / 0.05) * 0.05;
										    $c = ($sSeurIvaZ > 0) ? ($cir / (1 + $sSeurIvaZ / 100)) : $cir;
										    return $currencies->format(tep_add_tax($c, $sSeurIvaZ));
										};
										$sSeurPricePen = $seurPrecioZonaZ('PEN'); $sSeurPriceBal = $seurPrecioZonaZ('BAL');
										echo '<label>' . TEXT_SELECT_SEUR_PUDO . '</label>';
										// Buscador: permite recoger en OTRO código postal distinto al de entrega
										echo '<div style="display:flex;gap:6px;margin:0 0 6px 0;align-items:center">';
										echo '<input type="text" id="seurPudoCpInput" maxlength="5" inputmode="numeric" placeholder="' . TEXT_SEUR_PUDO_CP_PLACEHOLDER . '" value="' . htmlspecialchars(trim($order->delivery['postcode'])) . '" style="width:130px;padding:6px;box-sizing:border-box">';
										echo '<button type="button" id="seurPudoCpBtn" data-searching="' . htmlspecialchars(TEXT_SEUR_PUDO_CP_SEARCHING) . '" style="padding:6px 12px;cursor:pointer">' . TEXT_SEUR_PUDO_CP_SEARCH . '</button>';
										echo '</div>';
										echo '<input type="hidden" name="seur_pudo_cp" id="seurPudoCp" value="' . htmlspecialchars(trim($order->delivery['postcode'])) . '">';
										echo '<select name="seur_pudo" id="seurPudoSelect" data-price="' . $sSeurPrice . '" data-price-pen="' . htmlspecialchars($sSeurPricePen) . '" data-price-bal="' . htmlspecialchars($sSeurPriceBal) . '" data-empty-text="' . htmlspecialchars(TEXT_SEUR_PUDO_EMPTY) . '" data-choose-text="' . htmlspecialchars(TEXT_SELECT_SEUR_PUDO_CHOOSE) . '">';
										echo '<option value="" data-price="' . $sSeurPrice . '">' . TEXT_SELECT_SEUR_PUDO_CHOOSE . '</option>';
										foreach ($aSeurPuntos as $aPto) {
											$sPtoZ = (substr((string) $aPto['cp'], 0, 2) === '07') ? $sSeurPriceBal : $sSeurPricePen;
											echo '<option data-price="' . htmlspecialchars($sPtoZ) . '" value="' . htmlspecialchars($aPto['id']) . '"' . ($aPto['id'] == $sSeurSel ? ' selected' : '') . '>'
												. htmlspecialchars($aPto['name'] . ' - ' . $aPto['address'] . ' (' . $aPto['cp'] . ' ' . $aPto['city'] . ')') . '</option>';
										}
										echo '</select>';
										echo '<div id="seurPudoMsg" class="mensaje" style="display:' . (empty($aSeurPuntos) ? 'block' : 'none') . ';margin-top:6px">' . TEXT_SEUR_PUDO_EMPTY . '</div>';
										echo '<div id="seurPudoMap" style="height:320px;margin-top:8px;border:1px solid #ccc;border-radius:4px;position:relative;z-index:1"></div>';
										echo '<script type="application/json" id="seurPudoData">' . json_encode($aSeurPuntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
										echo '</div>';
									}
									// Fin, selector de punto de recogida SEUR

									// Inicio, selector de recogida en oficina de Correos (lista + mapa Leaflet)
									if ($aMethod['id'] == 'correosoficina') {
										$sCorOfiSel   = isset($_SESSION['correos_oficina_sel']['id']) ? $_SESSION['correos_oficina_sel']['id'] : '';
										$sCorOfiPrice = $currencies->format(tep_add_tax($aMethod['cost'], isset($aQuote['tax']) ? $aQuote['tax'] : 0));
										$sCorOfiCp    = htmlspecialchars(trim($order->delivery['postcode']));
										$sCorOfiCity  = htmlspecialchars(trim($order->delivery['city']));
										echo '<div class="selct" id="correosOfiBox">';
										echo '<label>' . (defined('TEXT_SELECT_CORREOS_OFICINA') ? TEXT_SELECT_CORREOS_OFICINA : 'Elige tu oficina de Correos:') . '</label>';
										echo '<div style="display:flex;gap:6px;margin:0 0 6px 0;align-items:center">';
										echo '<input type="text" id="correosOfiCpInput" maxlength="5" inputmode="numeric" placeholder="' . (defined('TEXT_CORREOS_OFICINA_CP_PLACEHOLDER') ? htmlspecialchars(TEXT_CORREOS_OFICINA_CP_PLACEHOLDER) : 'Cód. postal') . '" value="' . $sCorOfiCp . '" style="width:130px;padding:6px;box-sizing:border-box">';
										echo '<button type="button" id="correosOfiCpBtn" data-searching="' . (defined('TEXT_CORREOS_OFICINA_CP_SEARCHING') ? htmlspecialchars(TEXT_CORREOS_OFICINA_CP_SEARCHING) : 'Buscando oficinas…') . '" style="padding:6px 12px;cursor:pointer">' . (defined('TEXT_CORREOS_OFICINA_CP_SEARCH') ? htmlspecialchars(TEXT_CORREOS_OFICINA_CP_SEARCH) : 'Buscar oficinas') . '</button>';
										echo '</div>';
										echo '<input type="hidden" name="correos_oficina_cp" id="correosOfiCp" value="' . $sCorOfiCp . '">';
										echo '<input type="hidden" name="correos_oficina_q" id="correosOfiCity" value="' . $sCorOfiCity . '">';
										echo '<input type="hidden" id="correosOfiSelInit" value="' . htmlspecialchars($sCorOfiSel) . '">';
										echo '<select name="correos_oficina" id="correosOfiSelect" data-price="' . $sCorOfiPrice . '">';
										echo '<option value="" data-price="' . $sCorOfiPrice . '">' . (defined('TEXT_CORREOS_OFICINA_CP_SEARCHING') ? TEXT_CORREOS_OFICINA_CP_SEARCHING : 'Buscando oficinas…') . '</option>';
										echo '</select>';
										echo '<div id="correosOfiMsg" class="mensaje" style="display:none;margin-top:6px">' . (defined('TEXT_CORREOS_OFICINA_EMPTY') ? TEXT_CORREOS_OFICINA_EMPTY : 'No hay oficinas de Correos cercanas a tu código postal.') . '</div>';
										echo '<div id="correosOfiMap" style="height:320px;margin-top:8px;border:1px solid #ccc;border-radius:4px;position:relative;z-index:1"></div>';
										echo '</div>';
									}
									// Fin, selector de recogida en oficina de Correos

									// Inicio, selector de punto de recogida CEX (lista + mapa Leaflet)
									if ($aMethod['id'] == 'cexpunto' && class_exists('cexpunto')) {
										$aCexPuntos = cexpunto::puntos($order->delivery['postcode'], $order->delivery['city'], $order->delivery['country']['iso_code_2']);
										$sCexSel = isset($_SESSION['cex_pudo_sel']['id']) ? $_SESSION['cex_pudo_sel']['id'] : '';
										echo '<div class="selct" id="cexPudoBox">';
										// data-price: el JS del checkout pisa el precio de la fila con el
										// data-price de la opción seleccionada al cambiar cualquier select.
										$sCexPrice = $currencies->format(tep_add_tax($aMethod['cost'], isset($aQuote['tax']) ? $aQuote['tax'] : 0));
										echo '<label>' . TEXT_SELECT_CEX_PUDO . '</label>';
										// Buscador: permite recoger en OTRO código postal distinto al de entrega
										echo '<div style="display:flex;gap:6px;margin:0 0 6px 0;align-items:center">';
										echo '<input type="text" id="cexPudoCpInput" maxlength="5" inputmode="numeric" placeholder="' . TEXT_CEX_PUDO_CP_PLACEHOLDER . '" value="' . htmlspecialchars(trim($order->delivery['postcode'])) . '" style="width:130px;padding:6px;box-sizing:border-box">';
										echo '<button type="button" id="cexPudoCpBtn" data-searching="' . htmlspecialchars(TEXT_CEX_PUDO_CP_SEARCHING) . '" style="padding:6px 12px;cursor:pointer">' . TEXT_CEX_PUDO_CP_SEARCH . '</button>';
										echo '</div>';
										echo '<input type="hidden" name="cex_pudo_cp" id="cexPudoCp" value="' . htmlspecialchars(trim($order->delivery['postcode'])) . '">';
										echo '<select name="cex_pudo" id="cexPudoSelect" data-price="' . $sCexPrice . '" data-empty-text="' . htmlspecialchars(TEXT_CEX_PUDO_EMPTY) . '" data-choose-text="' . htmlspecialchars(TEXT_SELECT_CEX_PUDO_CHOOSE) . '">';
										echo '<option value="" data-price="' . $sCexPrice . '">' . TEXT_SELECT_CEX_PUDO_CHOOSE . '</option>';
										foreach ($aCexPuntos as $aPto) {
											echo '<option data-price="' . $sCexPrice . '" value="' . htmlspecialchars($aPto['id']) . '"' . ($aPto['id'] == $sCexSel ? ' selected' : '') . '>'
												. htmlspecialchars($aPto['name'] . ' - ' . $aPto['address'] . ' (' . $aPto['cp'] . ' ' . $aPto['city'] . ')') . '</option>';
										}
										echo '</select>';
										echo '<div id="cexPudoMsg" class="mensaje" style="display:' . (empty($aCexPuntos) ? 'block' : 'none') . ';margin-top:6px">' . TEXT_CEX_PUDO_EMPTY . '</div>';
										echo '<div id="cexPudoMap" style="height:320px;margin-top:8px;border:1px solid #ccc;border-radius:4px;position:relative;z-index:1"></div>';
										echo '<script type="application/json" id="cexPudoData">' . json_encode($aCexPuntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
										echo '</div>';
									}
									// Fin, selector de punto de recogida CEX

								?>
							</div>
						</div>
					</div>
				<?php endforeach;?>
			<?php endforeach;?>
		</div>

		<?php $checked = !isset($_SESSION['choose_insurance']) || (isset($_SESSION['choose_insurance']) && (int)$_SESSION['choose_insurance'] == 1); ?>
		<?php echo tep_draw_checkbox_field('choose_insurance', "1",  $checked, ' id="choose_insurance" style="display: none;" '); ?>

	<?php else: ?>
		<?php echo $messageStack->show(array('class' => 'warning', 'text' => CHECKOUT_ERROR_SHIPPING_MESSAGE)); ?>
	<?php endif;?>

	<div class="chkc-titu2"><?php echo CHECKOUT_SHIPPING_TITLE_ADDRESS; ?> <a href="#chkc-shpg-slct" class="fright mgp-inln"><i class="dhide fas fa-sync-alt chkc-chge"></i><span class="thide mhide"><?php echo CHECKOUT_SHIPPING_SELECT_ADDRESS; ?></span></a></div>
	<div class="chkc-adrb-wrpr">
		<div class="chkc-adrb">
			<b class="titu"><?php echo $sAddressTitle; ?> <a href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS, 'edit=' . $sendto); ?>" class="edit">(<?php echo CHECKOUT_EDIT; ?>)</a></b>
			<div class="infr" id="address-shipping-text">
				<?php echo $sAddress; ?>
			</div>
		</div>
		<div class="fotr tright">
			<a <?php echo (tep_count_customer_address_book_entries() >= MAX_ADDRESS_BOOK_ENTRIES ? 'data-alert="' . str_replace('%MAX%', MAX_ADDRESS_BOOK_ENTRIES, CHECKOUT_MAX_ADDRESS) . '" data-alert-icon="warning"' : ''); ?> href="<?php echo tep_href_link(FILENAME_ADDRESS_BOOK_PROCESS); ?>" class="addb"><?php echo CHECKOUT_ADD_ADDRESS; ?> <i class="fa fa-plus"></i></a>
		</div>
	</div>

	<div class="chkc-titu2 chkc-obsv-titu">3. <?php echo CHECKOUT_SHIPPING_TITLE_COMMENTS_TRANSPORT; ?></div>
	<div class="chkc-obsv chkc-text">
		<div class="text"><?php echo CHECKOUT_SHIPPING_TITLE_COMMENTS_TRANSPORT_TEXT; ?></div>
		<div class="xform"><?php echo tep_draw_textarea_field('comments', 'soft', '60', '3', isset($comments) ? $comments : ''); ?></div>
	</div>
</form>

<?php
// Inicio, punto de recogida SEUR: Leaflet + mapa (solo si se ha pintado el selector)
$bSeurPudoMap = false;
if (is_array($quotes)) {
	foreach ($quotes as $aQ) { if (isset($aQ['id']) && $aQ['id'] == 'seurpunto') { $bSeurPudoMap = true; break; } }
}
if ($bSeurPudoMap):
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<style>
/* El selector y el mapa SOLO se muestran cuando el método SEUR Punto está marcado */
.chkc-mthh:not(:has(#seurpunto_seurpunto:checked)) #seurPudoBox { display: none; }
</style>
<script>
(function () {
	var seurMap = null, seurMarkers = {}, seurLayer = null, inited = false;

	function radioChecked() {
		var r = document.getElementById('seurpunto_seurpunto');
		return !!(r && r.checked);
	}

	// Pinta (o repinta) la lista de puntos: select + marcadores del mapa.
	function renderPoints(puntos) {
		var elSel = document.getElementById('seurPudoSelect');
		var elMsg = document.getElementById('seurPudoMsg');
		if (!elSel) return;
		var price = elSel.dataset.price || '';
		var pricePen = elSel.dataset.pricePen || price, priceBal = elSel.dataset.priceBal || price;

		// Reconstruir el select (manteniendo data-price para el JS del checkout)
		elSel.innerHTML = '';
		var o0 = document.createElement('option');
		o0.value = ''; o0.textContent = elSel.dataset.chooseText || '--';
		o0.setAttribute('data-price', price);
		elSel.appendChild(o0);
		puntos.forEach(function (p) {
			var o = document.createElement('option');
			o.value = p.id;
			o.textContent = p.name + ' - ' + p.address + ' (' + p.cp + ' ' + p.city + ')';
			o.setAttribute('data-price', (String(p.cp || '').replace(/[^0-9]/g, '').slice(0, 2) === '07') ? priceBal : pricePen);
			elSel.appendChild(o);
		});
		if (elMsg) elMsg.style.display = puntos.length ? 'none' : 'block';

		// Repintar marcadores
		seurMarkers = {};
		if (seurLayer) { seurLayer.clearLayers(); }
		if (!seurMap || !seurLayer) return;
		var bounds = [];
		puntos.forEach(function (p) {
			if (!p.lat || !p.lng) return;
			var html = '<strong>' + p.name + '</strong><br>' + p.address + '<br>' + p.cp + ' ' + p.city
				+ (p.hours ? '<br><small>' + p.hours + '</small>' : '')
				+ '<br><a href="#" onclick="window.seurPudoChoose(\'' + p.id + '\');return false;"><strong>✓ Elegir este punto</strong></a>';
			var m = L.marker([p.lat, p.lng]).bindPopup(html);
			seurLayer.addLayer(m);
			seurMarkers[p.id] = m;
			bounds.push([p.lat, p.lng]);
		});
		if (bounds.length) seurMap.fitBounds(bounds, { padding: [25, 25] });
	}

	// Init perezosa: Leaflet no puede nacer en un div oculto (tamaño 0).
	function ensureMap() {
		var elMap  = document.getElementById('seurPudoMap');
		var elData = document.getElementById('seurPudoData');
		if (!elMap || !elData || typeof L === 'undefined' || !radioChecked()) return;
		if (inited) { if (seurMap) seurMap.invalidateSize(); return; }
		if (elMap.offsetParent === null) { setTimeout(ensureMap, 200); return; } // aún oculto
		inited = true;

		seurMap = L.map(elMap).setView([40.4168, -3.7038], 6); // España por defecto
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 18, attribution: '&copy; OpenStreetMap'
		}).addTo(seurMap);
		seurLayer = L.layerGroup().addTo(seurMap);

		var puntos;
		try { puntos = JSON.parse(elData.textContent || '[]'); } catch (e) { puntos = []; }
		renderPoints(puntos);
		setTimeout(function () {
			if (!seurMap) return;
			seurMap.invalidateSize();
			var ids = Object.keys(seurMarkers);
			if (ids.length) {
				var b = ids.map(function (k) { return seurMarkers[k].getLatLng(); });
				seurMap.fitBounds(b, { padding: [25, 25] });
			}
		}, 300);

		var elSel = document.getElementById('seurPudoSelect');
		if (elSel) elSel.addEventListener('change', function () {
			var m = seurMarkers[this.value];
			if (m && seurMap) { seurMap.setView(m.getLatLng(), 15); m.openPopup(); }
		});

		// Buscador: recoger en OTRO código postal
		var btn = document.getElementById('seurPudoCpBtn');
		var inp = document.getElementById('seurPudoCpInput');
		function buscar() {
			var cp = (inp && inp.value || '').replace(/\D/g, '');
			if (cp.length !== 5) { if (inp) inp.focus(); return; }
			var searching = (btn && btn.dataset.searching) || 'Buscando…';
			if (btn) { btn.disabled = true; btn.textContent = searching; }
			// feedback también en el desplegable mientras llega la respuesta
			var elSel = document.getElementById('seurPudoSelect');
			if (elSel) {
				elSel.innerHTML = '';
				var ow = document.createElement('option');
				ow.value = ''; ow.textContent = searching;
				ow.setAttribute('data-price', elSel.dataset.price || '');
				elSel.appendChild(ow);
			}
			fetch('/seur_puntos.php?cp=' + cp, { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					var hid = document.getElementById('seurPudoCp');
					if (j && j.ok) {
						if (hid) hid.value = j.cp;
						renderPoints(j.puntos || []);
					} else {
						renderPoints([]);
					}
				})
				.catch(function () { renderPoints([]); })
				.finally(function () { if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Buscar'; } });
		}
		if (btn) { btn.dataset.label = btn.textContent; btn.addEventListener('click', buscar); }
		if (inp) inp.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); buscar(); } // no enviar el form del checkout
		});
	}

	window.seurPudoChoose = function (id) {
		var elSel = document.getElementById('seurPudoSelect');
		if (!elSel) return;
		elSel.value = id;
		var radio = document.getElementById('seurpunto_seurpunto');
		if (radio && !radio.checked) {
			radio.checked = true;
			// replicar el marcado visual del checkout (clase actv de la fila)
			var row = radio.closest('[data-method]');
			if (row && row.parentNode) {
				var act = row.parentNode.querySelectorAll('.actv');
				for (var i = 0; i < act.length; i++) act[i].classList.remove('actv');
				row.classList.add('actv');
			}
		}
		var m = seurMarkers[id];
		if (m && seurMap) { seurMap.setView(m.getLatLng(), 15); m.openPopup(); }
	};

	// El checkout marca el radio por JS al pulsar la fila (sin evento change):
	// re-evaluamos tras cada clic en cualquier método.
	document.addEventListener('click', function (e) {
		if (e.target && e.target.closest && e.target.closest('[data-method]')) {
			setTimeout(ensureMap, 250);
		}
	});
	// Por si el método ya viene preseleccionado de la sesión
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { setTimeout(ensureMap, 250); });
	} else { setTimeout(ensureMap, 250); }
})();
</script>
<?php endif; ?>
<?php
// Inicio, recogida en oficina de Correos: Leaflet + mapa (solo si se pintó el selector)
$bCorreosOfiMap = false;
if (is_array($quotes)) {
	foreach ($quotes as $aQ) { if (isset($aQ['id']) && $aQ['id'] == 'correosoficina') { $bCorreosOfiMap = true; break; } }
}
if ($bCorreosOfiMap):
?>
<?php if (empty($bSeurPudoMap)): // si SEUR estaba presente ya cargó Leaflet ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<?php endif; ?>
<style>
.chkc-mthh:not(:has(#correosoficina_correosoficina:checked)) #correosOfiBox { display: none; }
</style>
<script>
(function () {
	var coMap = null, coMarkers = {}, coLayer = null, coMapInited = false, coWired = false, coLoaded = false, coLast = [];
	function radioChecked() { var r = document.getElementById('correosoficina_correosoficina'); return !!(r && r.checked); }

	// Pinta el desplegable (textContent = seguro) y, si hay mapa, los marcadores (popup por DOM).
	function renderOfis(ofis) {
		ofis = ofis || []; coLast = ofis;
		var elSel = document.getElementById('correosOfiSelect');
		var elMsg = document.getElementById('correosOfiMsg');
		if (elSel) {
			var price = elSel.dataset.price || '';
			var prev = document.getElementById('correosOfiSelInit');
			var want = elSel.value || (prev ? prev.value : '');
			elSel.innerHTML = '';
			var o0 = document.createElement('option');
			o0.value = ''; o0.textContent = '-- Selecciona una oficina --';
			o0.setAttribute('data-price', price);
			elSel.appendChild(o0);
			ofis.forEach(function (o) {
				var op = document.createElement('option');
				op.value = o.id;
				op.textContent = o.name + ' - ' + o.address + ' (' + o.cp + ' ' + o.city + ')';
				op.setAttribute('data-price', price);
				if (o.id === want) op.selected = true;
				elSel.appendChild(op);
			});
			if (elMsg) elMsg.style.display = ofis.length ? 'none' : 'block';
		}
		coMarkers = {};
		if (coLayer) coLayer.clearLayers();
		if (!coMap || !coLayer) return;
		var bounds = [];
		ofis.forEach(function (o) {
			if (!o.lat || !o.lng) return;
			var div = document.createElement('div');
			var st = document.createElement('strong'); st.textContent = o.name; div.appendChild(st);
			div.appendChild(document.createElement('br'));
			div.appendChild(document.createTextNode(o.address + ' · ' + o.cp + ' ' + o.city));
			if (o.hours) { div.appendChild(document.createElement('br')); var sm = document.createElement('small'); sm.textContent = o.hours; div.appendChild(sm); }
			div.appendChild(document.createElement('br'));
			var a = document.createElement('a'); a.href = '#';
			var ab = document.createElement('strong'); ab.textContent = '✓ Elegir esta oficina'; a.appendChild(ab);
			a.addEventListener('click', function (e) { e.preventDefault(); window.correosOfiChoose(o.id); });
			div.appendChild(a);
			var m = L.marker([o.lat, o.lng]).bindPopup(div);
			coLayer.addLayer(m); coMarkers[o.id] = m; bounds.push([o.lat, o.lng]);
		});
		if (bounds.length) coMap.fitBounds(bounds, { padding: [25, 25] });
	}

	function fetchOfis(cp, q) {
		var elSel = document.getElementById('correosOfiSelect');
		var city = document.getElementById('correosOfiCity');
		var q = (typeof q !== 'undefined' && q !== null) ? q : (city && city.value ? city.value : ''); if (city) city.value = q; var qs = q ? '&q=' + encodeURIComponent(q) : '';
		if (elSel) { elSel.innerHTML = ''; var ow = document.createElement('option'); ow.value = ''; ow.textContent = 'Buscando oficinas…'; elSel.appendChild(ow); }
		return fetch('/correos_oficinas.php?cp=' + cp + qs, { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				var hid = document.getElementById('correosOfiCp');
				if (j && j.ok) { if (hid) hid.value = j.cp; renderOfis(j.oficinas || []); }
				else renderOfis([]);
			})
			.catch(function () { renderOfis([]); });
	}

	// Lista + buscador: NO dependen de Leaflet → el selector sigue usable aunque el mapa no cargue.
	function ensureList() {
		if (!radioChecked()) return;
		if (!coWired) {
			coWired = true;
			var btn = document.getElementById('correosOfiCpBtn'), inp = document.getElementById('correosOfiCpInput');
			function buscar() {
				var cp = ((inp && inp.value) || '').replace(/\D/g, '');
				if (cp.length !== 5) { if (inp) inp.focus(); return; }
				if (btn) { btn.disabled = true; var lbl = btn.dataset.label || btn.textContent; btn.dataset.label = lbl; btn.textContent = btn.dataset.searching || 'Buscando…'; fetchOfis(cp, '').finally(function () { btn.disabled = false; btn.textContent = lbl; }); }
				else fetchOfis(cp, '');
			}
			if (btn) btn.addEventListener('click', buscar);
			if (inp) inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); buscar(); } });
			var elSel = document.getElementById('correosOfiSelect');
			if (elSel) elSel.addEventListener('change', function () { var m = coMarkers[this.value]; if (m && coMap) { coMap.setView(m.getLatLng(), 15); m.openPopup(); } });
		}
		if (!coLoaded) {
			coLoaded = true;
			var hid = document.getElementById('correosOfiCp');
			var cp0 = ((hid && hid.value) || '').replace(/\D/g, '');
			if (cp0.length === 5) fetchOfis(cp0);
		}
	}

	// Mapa Leaflet: solo si la librería cargó. Si la CDN falla, la lista sigue operativa.
	function ensureMap() {
		var elMap = document.getElementById('correosOfiMap');
		if (!elMap || typeof L === 'undefined' || !radioChecked()) return;
		if (coMapInited) { coMap.invalidateSize(); return; }
		if (elMap.offsetParent === null) { setTimeout(ensureMap, 200); return; }
		coMapInited = true;
		coMap = L.map(elMap).setView([40.4168, -3.7038], 6);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(coMap);
		coLayer = L.layerGroup().addTo(coMap);
		setTimeout(function () { if (coMap) coMap.invalidateSize(); }, 300);
		renderOfis(coLast);   // dibuja los marcadores de lo ya cargado, sin re-fetch
	}

	window.correosOfiChoose = function (id) {
		var elSel = document.getElementById('correosOfiSelect'); if (!elSel) return; elSel.value = id;
		var radio = document.getElementById('correosoficina_correosoficina');
		if (radio && !radio.checked) {
			radio.checked = true;
			var row = radio.closest('[data-method]');
			if (row && row.parentNode) { var act = row.parentNode.querySelectorAll('.actv'); for (var i = 0; i < act.length; i++) act[i].classList.remove('actv'); row.classList.add('actv'); }
		}
		var m = coMarkers[id]; if (m && coMap) { coMap.setView(m.getLatLng(), 15); m.openPopup(); }
	};

	function coInit() { ensureList(); ensureMap(); }
	document.addEventListener('click', function (e) { if (e.target && e.target.closest && e.target.closest('[data-method]')) setTimeout(coInit, 250); });
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(coInit, 250); });
	else setTimeout(coInit, 250);
})();
</script>
<?php endif; ?>
<?php
// Inicio, punto de recogida CEX: Leaflet + mapa (solo si se ha pintado el selector)
$bCexPudoMap = false;
if (is_array($quotes)) {
	foreach ($quotes as $aQ) { if (isset($aQ['id']) && $aQ['id'] == 'cexpunto') { $bCexPudoMap = true; break; } }
}
if ($bCexPudoMap):
?>
<?php if (empty($bSeurPudoMap) && empty($bCorreosOfiMap)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<?php endif; ?>
<style>
/* El selector y el mapa SOLO se muestran cuando el método CEX Punto está marcado */
.chkc-mthh:not(:has(#cexpunto_cexpunto:checked)) #cexPudoBox { display: none; }
</style>
<script>
(function () {
	var cexMap = null, cexMarkers = {}, cexLayer = null, inited = false;

	function radioChecked() {
		var r = document.getElementById('cexpunto_cexpunto');
		return !!(r && r.checked);
	}

	// Pinta (o repinta) la lista de puntos: select + marcadores del mapa.
	function renderPoints(puntos) {
		var elSel = document.getElementById('cexPudoSelect');
		var elMsg = document.getElementById('cexPudoMsg');
		if (!elSel) return;
		var price = elSel.dataset.price || '';

		// Reconstruir el select (manteniendo data-price para el JS del checkout)
		elSel.innerHTML = '';
		var o0 = document.createElement('option');
		o0.value = ''; o0.textContent = elSel.dataset.chooseText || '--';
		o0.setAttribute('data-price', price);
		elSel.appendChild(o0);
		puntos.forEach(function (p) {
			var o = document.createElement('option');
			o.value = p.id;
			o.textContent = p.name + ' - ' + p.address + ' (' + p.cp + ' ' + p.city + ')';
			o.setAttribute('data-price', price);
			elSel.appendChild(o);
		});
		if (elMsg) elMsg.style.display = puntos.length ? 'none' : 'block';

		// Repintar marcadores
		cexMarkers = {};
		if (cexLayer) { cexLayer.clearLayers(); }
		if (!cexMap || !cexLayer) return;
		var bounds = [];
		puntos.forEach(function (p) {
			if (!p.lat || !p.lng) return;
			var html = '<strong>' + p.name + '</strong><br>' + p.address + '<br>' + p.cp + ' ' + p.city
				+ (p.hours ? '<br><small>' + p.hours + '</small>' : '')
				+ '<br><a href="#" onclick="window.cexPudoChoose(\'' + p.id + '\');return false;"><strong>✓ Elegir este punto</strong></a>';
			var m = L.marker([p.lat, p.lng]).bindPopup(html);
			cexLayer.addLayer(m);
			cexMarkers[p.id] = m;
			bounds.push([p.lat, p.lng]);
		});
		if (bounds.length) cexMap.fitBounds(bounds, { padding: [25, 25] });
	}

	// Init perezosa: Leaflet no puede nacer en un div oculto (tamaño 0).
	function ensureMap() {
		var elMap  = document.getElementById('cexPudoMap');
		var elData = document.getElementById('cexPudoData');
		if (!elMap || !elData || typeof L === 'undefined' || !radioChecked()) return;
		if (inited) { if (cexMap) cexMap.invalidateSize(); return; }
		if (elMap.offsetParent === null) { setTimeout(ensureMap, 200); return; } // aún oculto
		inited = true;

		cexMap = L.map(elMap).setView([40.4168, -3.7038], 6); // España por defecto
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 18, attribution: '&copy; OpenStreetMap'
		}).addTo(cexMap);
		cexLayer = L.layerGroup().addTo(cexMap);

		var puntos;
		try { puntos = JSON.parse(elData.textContent || '[]'); } catch (e) { puntos = []; }
		renderPoints(puntos);
		setTimeout(function () {
			if (!cexMap) return;
			cexMap.invalidateSize();
			var ids = Object.keys(cexMarkers);
			if (ids.length) {
				var b = ids.map(function (k) { return cexMarkers[k].getLatLng(); });
				cexMap.fitBounds(b, { padding: [25, 25] });
			}
		}, 300);

		var elSel = document.getElementById('cexPudoSelect');
		if (elSel) elSel.addEventListener('change', function () {
			var m = cexMarkers[this.value];
			if (m && cexMap) { cexMap.setView(m.getLatLng(), 15); m.openPopup(); }
		});

		// Buscador: recoger en OTRO código postal
		var btn = document.getElementById('cexPudoCpBtn');
		var inp = document.getElementById('cexPudoCpInput');
		function buscar() {
			var cp = (inp && inp.value || '').replace(/\D/g, '');
			if (cp.length !== 5) { if (inp) inp.focus(); return; }
			var searching = (btn && btn.dataset.searching) || 'Buscando…';
			if (btn) { btn.disabled = true; btn.textContent = searching; }
			// feedback también en el desplegable mientras llega la respuesta
			var elSel = document.getElementById('cexPudoSelect');
			if (elSel) {
				elSel.innerHTML = '';
				var ow = document.createElement('option');
				ow.value = ''; ow.textContent = searching;
				ow.setAttribute('data-price', elSel.dataset.price || '');
				elSel.appendChild(ow);
			}
			fetch('/cex_puntos.php?cp=' + cp, { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					var hid = document.getElementById('cexPudoCp');
					if (j && j.ok) {
						if (hid) hid.value = j.cp;
						renderPoints(j.puntos || []);
					} else {
						renderPoints([]);
					}
				})
				.catch(function () { renderPoints([]); })
				.finally(function () { if (btn) { btn.disabled = false; btn.textContent = btn.dataset.label || 'Buscar'; } });
		}
		if (btn) { btn.dataset.label = btn.textContent; btn.addEventListener('click', buscar); }
		if (inp) inp.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); buscar(); } // no enviar el form del checkout
		});
	}

	window.cexPudoChoose = function (id) {
		var elSel = document.getElementById('cexPudoSelect');
		if (!elSel) return;
		elSel.value = id;
		var radio = document.getElementById('cexpunto_cexpunto');
		if (radio && !radio.checked) {
			radio.checked = true;
			// replicar el marcado visual del checkout (clase actv de la fila)
			var row = radio.closest('[data-method]');
			if (row && row.parentNode) {
				var act = row.parentNode.querySelectorAll('.actv');
				for (var i = 0; i < act.length; i++) act[i].classList.remove('actv');
				row.classList.add('actv');
			}
		}
		var m = cexMarkers[id];
		if (m && cexMap) { cexMap.setView(m.getLatLng(), 15); m.openPopup(); }
	};

	// El checkout marca el radio por JS al pulsar la fila (sin evento change):
	// re-evaluamos tras cada clic en cualquier método.
	document.addEventListener('click', function (e) {
		if (e.target && e.target.closest && e.target.closest('[data-method]')) {
			setTimeout(ensureMap, 250);
		}
	});
	// Por si el método ya viene preseleccionado de la sesión
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { setTimeout(ensureMap, 250); });
	} else { setTimeout(ensureMap, 250); }
})();
</script>
<?php endif; ?>
<?php // Fin, recogida en oficina de Correos ?>
<?php // Fin, punto de recogida SEUR ?>

<?php echo $boxCheckout->addressBook($sendto, CHECKOUT_SHIPPING_SELECT_ADDRESS, tep_href_link(FILENAME_CHECKOUT_SHIPPING . 'select_address/')); ?>

<?php echo tools::includeTemplate($sPathTemplate . '/column.php'); ?>
