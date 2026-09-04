<?php
/**
 * Generacion del CSV de precios para las etiquetas electronicas (ESL Hanshow / Profimax).
 *
 * LOGICA UNICA, compartida por:
 *   - _admin/precios_etiquetas.php  -> descarga manual desde el admin
 *   - api-etiquetas.php             -> descarga automatica desde PROFIMAX .52 (bearer + allowlist)
 *
 * NO DUPLICAR esta logica en ningun otro sitio. Si el cron llevase una copia
 * desactualizada, las etiquetas del estante se quedarian con precios viejos
 * SIN dar ningun error visible.
 *
 * Formato de salida (';' como separador, ISO-8859-1):
 *   producto : id ; "nombre" ; PVP ; oferta ; stock ; EAN ; %dto ;      (8 campos)
 *   variante : id-A<attrId> ; "nombre" ; precio ; 0 ; stock ; EAN ; 0   (7 campos)
 *
 * STOCK BUCKETIZADO (2026-09-03): se devuelve 1 si hay existencias y 0 si no.
 * La etiqueta pinta este campo, asi que mandar la cantidad real hacia que CADA
 * venta repintase la etiqueta: el 98% de las actualizaciones ESL eran por eso
 * (medido: de 485 etiquetas encoladas en un import, 477 por stock y 1 por precio).
 * El layout LAYOUT_LBTEIM muestra "HAY" si el valor es != 0 y oculta el bloque si es 0.
 */
declare(strict_types=1);

if (!defined('FB_ETQ_TAX_ZONE_ID')) { define('FB_ETQ_TAX_ZONE_ID', 31); }
if (!defined('FB_ETQ_LANGUAGE_ID')) { define('FB_ETQ_LANGUAGE_ID', 3); }
if (!defined('FB_ETQ_SEPARATOR'))   { define('FB_ETQ_SEPARATOR', ';'); }

if (!function_exists('fb_etq_pdo')) {
	/** PDO contra la BD de la tienda usando las constantes de configure.php */
	function fb_etq_pdo(): PDO {
		return new PDO(
			'mysql:host=' . DB_SERVER . ';dbname=' . DB_DATABASE . ';charset=utf8',
			DB_SERVER_USERNAME,
			DB_SERVER_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
		);
	}
}

if (!function_exists('fb_etq_tax_rates')) {
	function fb_etq_tax_rates(PDO $pdo): array {
		$map = [];
		$st = $pdo->query('SELECT tax_class_id, tax_rate FROM tax_rates WHERE tax_zone_id = ' . (int) FB_ETQ_TAX_ZONE_ID);
		foreach ($st as $row) { $map[(int) $row['tax_class_id']] = (float) $row['tax_rate']; }
		return $map;
	}
}

if (!function_exists('fb_etq_specials')) {
	function fb_etq_specials(PDO $pdo): array {
		$map = [];
		$st = $pdo->query('SELECT products_id, specials_new_products_price FROM specials');
		foreach ($st as $row) { $map[(int) $row['products_id']] = (float) $row['specials_new_products_price']; }
		return $map;
	}
}

if (!function_exists('fb_etq_products')) {
	function fb_etq_products(PDO $pdo, int $manufacturerId = 0): array {
		$sql = 'SELECT p.products_id, p.products_price, p.products_tax_class_id, p.product_ean, p.products_quantity, pd.products_name
		        FROM products p
		        LEFT JOIN products_description pd ON p.products_id = pd.products_id AND pd.language_id = ' . (int) FB_ETQ_LANGUAGE_ID . '
		        WHERE p.products_status = 1';
		if ($manufacturerId > 0) { $sql .= ' AND p.manufacturers_id = ' . $manufacturerId; }
		$sql .= ' ORDER BY p.products_id ASC';
		return $pdo->query($sql)->fetchAll();
	}
}

if (!function_exists('fb_etq_attributes')) {
	function fb_etq_attributes(PDO $pdo, array $productIds): array {
		$out = [];
		if (empty($productIds)) { return $out; }
		$idList = implode(',', array_map('intval', $productIds));
		$lang = (int) FB_ETQ_LANGUAGE_ID;
		$sql = "SELECT patrib.products_id, patrib.price_prefix, popt.products_options_id, patrib.options_values_id,
		               patrib.products_attributes_id, patrib.products_attributes_ean, ps.products_stock_quantity,
		               patrib.options_values_price, pov.products_options_values_name
		        FROM products_options popt
		        INNER JOIN products_attributes patrib ON patrib.options_id = popt.products_options_id
		        LEFT JOIN products_options_values pov ON pov.products_options_values_id = patrib.options_values_id AND pov.language_id = $lang
		        LEFT JOIN products_stock ps ON ps.products_stock_attributes = CONCAT(popt.products_options_id, '-', patrib.options_values_id) AND ps.products_id = patrib.products_id
		        WHERE patrib.products_id IN ($idList) AND popt.language_id = $lang
		        GROUP BY patrib.products_id, patrib.options_values_id
		        ORDER BY patrib.products_id ASC, patrib.options_values_price ASC";
		foreach ($pdo->query($sql) as $row) { $out[$row['products_id']][] = $row; }
		return $out;
	}
}

if (!function_exists('fb_etq_generar')) {
	/**
	 * Escribe el CSV completo en $fp. Devuelve el numero de lineas escritas.
	 * $fp puede ser un fichero o php://output (streaming).
	 */
	function fb_etq_generar(PDO $pdo, int $manufacturerId, $fp): int {
		$taxRates = fb_etq_tax_rates($pdo);
		$specials = fb_etq_specials($pdo);
		$productsData = fb_etq_products($pdo, $manufacturerId);
		if (empty($productsData)) { return 0; }

		$productIds = [];
		foreach ($productsData as $row) { $productIds[] = (int) $row['products_id']; }
		$attributesByProduct = fb_etq_attributes($pdo, $productIds);

		$sep = FB_ETQ_SEPARATOR;
		$total = 0;

		foreach ($productsData as $row) {
			$productId = (int) $row['products_id'];
			$taxClassId = (int) $row['products_tax_class_id'];
			$taxRate = isset($taxRates[$taxClassId]) ? $taxRates[$taxClassId] : 0;
			$taxMultiplier = 1 + ($taxRate / 100);

			$basePrice = (float) $row['products_price'];
			$specialPrice = isset($specials[$productId]) ? (float) $specials[$productId] : 0;

			// stock bucketizado: ver cabecera del fichero
			$stock = ((float) $row['products_quantity'] > 0) ? 1 : 0;

			$priceTax = round($basePrice * $taxMultiplier, 2);
			$specialTax = round($specialPrice * $taxMultiplier, 2);
			// guarda: sin ella, un producto con precio 0 y oferta > 0 lanza DivisionByZeroError en PHP 8
			$descuento = ($specialPrice <= 0 || $basePrice <= 0) ? 0 : number_format((1 - $specialPrice / $basePrice) * 100, 0);

			$nombreISO = mb_convert_encoding($row['products_name'] ?? '', 'ISO-8859-1', 'UTF-8');

			fputcsv($fp, [
				$productId,
				$nombreISO,
				$priceTax,
				$specialTax,
				$stock,
				$row['product_ean'],
				$descuento,
				'',
			], $sep, '"', '');
			$total++;

			if (!empty($attributesByProduct[$productId])) {
				foreach ($attributesByProduct[$productId] as $attr) {
					$stockAttr = ((float) $attr['products_stock_quantity'] > 0) ? 1 : 0;
					$attrValue = (float) $attr['options_values_price'];
					if ($attr['price_prefix'] === '-') { $attrValue *= -1; }
					$patrib = round(($basePrice + $attrValue) * $taxMultiplier, 2);

					$attrNombreISO = mb_convert_encoding($attr['products_options_values_name'] ?? '', 'ISO-8859-1', 'UTF-8');

					$attrLine = [
						$productId . '-A' . $attr['products_attributes_id'],
						substr($nombreISO, 0, 40) . substr($attrNombreISO, 0, 35),
						$patrib,
						'0',
						$stockAttr,
						$attr['products_attributes_ean'],
						'0',
					];

					if (!empty(trim(implode('', $attrLine)))) {
						fputcsv($fp, $attrLine, $sep, '"', '');
						$total++;
					}
				}
			}
		}

		return $total;
	}
}
