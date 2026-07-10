<?php
if (!defined('TEXT_SELECCIONE')) define('TEXT_SELECCIONE', 'Seleccione...');

class option_combobox
{
	public function __construct()
	{
	}

	public function frontendGetHtml($aDatos, $aDatoOption, $sPlantilla, $aOpcionesSelected, $bShowPrice, $nTaxId, $products_id = 0)
	{
		// Variables
		global $currencies;
		$sHtmlOption = '';
		$nCont = 0;

		$sHtmlOption .= '<option></option>';
		$products_price = (float)getPriceFromProductsId($products_id);

		// Oferta + variante: el modificador de variante (options_values_price) está dimensionado sobre el
		// precio COMPLETO (sin oferta). Si hay oferta activa escalamos el modificador por el mismo ratio de
		// descuento, de modo que cada variante mantenga el % de la oferta y no un descuento fijo en euros.
		// Sin oferta (full == eff) el ratio es 1 → comportamiento idéntico al anterior.
		// function_exists: combobox.class.php es compartido; el path AJAX de _admin/order_edit
		// no carga la general.php que define getFullPriceFromProductsId() → sin guard, Fatal.
		// Fallback: ratio 1 (sin escalar por oferta) cuando la función no está disponible.
		$full_price  = function_exists('getFullPriceFromProductsId') ? (float)getFullPriceFromProductsId($products_id) : 0.0;
		$offer_ratio = ($full_price > 0) ? ($products_price / $full_price) : 1.0;

		// 🔎 Obtenemos el check_stock del producto (global)
		$checkStock = 0;
		if ($products_id > 0) {
			$q = tep_db_query("SELECT check_stock FROM products WHERE products_id = " . (int)$products_id);
			$row = tep_db_fetch_array($q);
			$checkStock = (int)$row['check_stock'];
		}

		// Ofertas de VARIANTE del motor auto_specials: precargamos el delta ORIGINAL
		// (snapshot) por options_values_id para poder pintar el precio "antes" tachado
		// aunque el producto no tenga specials (una variante rebajada via delta es
		// indistinguible de un precio normal sin esta info). cgid segun sesion SPPC
		// (0=Retail, 1=Profesionales); Amazon/EBAY no llevan tracking -> sin tachado.
		$aVariantOffers = array();
		if ($products_id > 0) {
			$nCgidSession = isset($_SESSION['sppc_customer_group_id']) ? (int)$_SESSION['sppc_customer_group_id'] : 0;
			if ($nCgidSession === 0 || $nCgidSession === 1) {
				$q = tep_db_query("SELECT options_values_id, ovp_orig, prefix_orig FROM auto_specials_active
				                   WHERE products_id = " . (int)$products_id . "
				                     AND customers_group_id = " . $nCgidSession . "
				                     AND estado = 'active' AND options_values_id > 0 AND ovp_orig IS NOT NULL");
				while ($row = tep_db_fetch_array($q)) {
					$aVariantOffers[(int)$row['options_values_id']] = $row;
				}
			}
		}

		// Recorremos los atributos
		while ($aDato = tep_db_fetch_array($aDatos)) {
			// Precio (modificador escalado por el ratio de oferta)
			$mod    = (float)$aDato['options_values_price'] * $offer_ratio;
			$sPrice = $currencies->display_price($mod, tep_get_tax_rate($nTaxId));
			if ($aDato['price_prefix'] == '-') {
				$sPriceText = $currencies->display_price($products_price - $mod, tep_get_tax_rate($nTaxId));
			} else {
				$sPriceText = $currencies->display_price($mod + $products_price, tep_get_tax_rate($nTaxId));
			}

			// Precio anterior (tachado) POR VARIANTE: precio COMPLETO de la variante sin ofertas.
			// Dos fuentes, por prioridad:
			//  1) Oferta de VARIANTE del motor auto_specials -> delta ORIGINAL del snapshot
			//     (el delta actual ya esta rebajado; usarlo mostraria un "antes" falso).
			//  2) Oferta de PRODUCTO (offer_ratio < 1) -> modificador actual SIN escalar.
			// Vacio si no hay oferta; app.js (changePrice) lo lleva al <s>.
			$nBaseFull = ($full_price > 0) ? $full_price : $products_price;
			$nOvid = (int)$aDato['products_options_values_id'];
			if (isset($aVariantOffers[$nOvid])) {
				$modOrig = (float)$aVariantOffers[$nOvid]['ovp_orig'];
				$nPrecioAntes = ($aVariantOffers[$nOvid]['prefix_orig'] == '-') ? ($nBaseFull - $modOrig) : ($nBaseFull + $modOrig);
				// Sanity: solo tachar si el "antes" supera al precio efectivo actual
				$nPrecioAhora = ($aDato['price_prefix'] == '-') ? ($products_price - $mod) : ($products_price + $mod);
				$sPriceLast = ($nPrecioAntes > $nPrecioAhora + 0.005) ? $currencies->display_price($nPrecioAntes, tep_get_tax_rate($nTaxId)) : '';
			} elseif ($offer_ratio < 1 && $full_price > 0) {
				$modFull = (float)$aDato['options_values_price'];
				if ($aDato['price_prefix'] == '-') {
					$sPriceLast = $currencies->display_price($full_price - $modFull, tep_get_tax_rate($nTaxId));
				} else {
					$sPriceLast = $currencies->display_price($modFull + $full_price, tep_get_tax_rate($nTaxId));
				}
			} else {
				$sPriceLast = '';
			}

			$sPriceData = str_replace(['.', '€', ','], ['', '', '.'], $sPrice);

			// Status por stock
			$textStatus = '';
			$textStatusColor = '';

			if ($products_id > 0) {
				$nStock = stock_en_atributos($aDato['options_id'], $aDato['options_values_id'], $products_id);

				// ⚡ Reusamos la misma lógica que claseBotonComprar()
				if (DISABLE_SHIPPING_5_DAYS == 'true' && $nStock <= 0) {
					$textStatus = ' (Sin stock)';
					$textStatusColor = '#e80d0d';
				} elseif ($checkStock && $nStock <= 0) {
					$textStatus = ' (Sin stock)';
					$textStatusColor = '#e80d0d';
				} elseif ($nStock <= -100 && $nStock >= -150) {
					$textStatus = ' (Plazo 2-4 días)';
					$textStatusColor = '#f7a521';
				} elseif ($nStock <= 0 && $nStock >= -799) {
					$textStatus = ' (Plazo 7-10 días)';
					$textStatusColor = '#f7a521';
				} elseif ($nStock <= -800 && $nStock >= -899) {
					$textStatus = ' (Bajo pedido)';
					$textStatusColor = '#2faded';
				} elseif ($nStock <= -900 && $nStock >= -901) {
					$textStatus = ' (Sin stock)';
					$textStatusColor = '#e80d0d';
				} elseif ($nStock > 0) {
					$textStatus = ' (En stock)';
					$textStatusColor = '#10c789';
				}
			}

			// Construimos option
			$sHtmlOption .= '<option
					data-reference="' . $aDato['reference'] . '"
					data-price="' . $sPriceData . '"
					data-price-prefix="' . $aDato['price_prefix'] . '"
					value="' . $aDato['products_options_values_id'] . '"
					data-price-text="'.$sPriceText.'"
					data-price-last="'.$sPriceLast.'"
					data-status-text="'.$textStatus.'"
					data-status-text-color="'.$textStatusColor.'">'
				. $aDato['products_options_values_name'] .
				'</option>';

			$nCont++;
		}

		// Retornamos
		return str_replace(
			['%REPLACE_VALUE_SELECT%', '%REPLACE_OPTION_NAME%'],
			[
				'<select data-holder="'.TEXT_SELECCIONE.'" '
				. ($aDatoOption['products_options_track_stock'] == 1
					? ' data-track="1" data-name="' . $aDatoOption['products_options_name'] . '" data-required="true"'
					: '')
				. ' name="id[' . $aDatoOption['products_options_id'] . ']" data-oid="' . $aDatoOption['products_options_id'] . '" class="select2-attributes">'
				. $sHtmlOption .
				'</select>',
				$aDatoOption['products_options_name']
			],
			$sPlantilla
		);
	}

	public function getAllowValues()
	{
		return true;
	}
}
?>
