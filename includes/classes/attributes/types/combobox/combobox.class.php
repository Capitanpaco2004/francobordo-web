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

		// 🔎 Obtenemos el check_stock del producto (global)
		$checkStock = 0;
		if ($products_id > 0) {
			$q = tep_db_query("SELECT check_stock FROM products WHERE products_id = " . (int)$products_id);
			$row = tep_db_fetch_array($q);
			$checkStock = (int)$row['check_stock'];
		}

		// Recorremos los atributos
		while ($aDato = tep_db_fetch_array($aDatos)) {
			// Precio
			$sPrice = $currencies->display_price((float)$aDato['options_values_price'], tep_get_tax_rate($nTaxId));
			if ($aDato['price_prefix'] == '-') {
				$sPriceText = $currencies->display_price($products_price - (float)$aDato['options_values_price'], tep_get_tax_rate($nTaxId));
			} else {
				$sPriceText = $currencies->display_price((float)$aDato['options_values_price'] + $products_price, tep_get_tax_rate($nTaxId));
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
					$textStatus = ' (24 horas)';
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
