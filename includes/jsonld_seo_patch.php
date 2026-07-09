<?php
/**
 * jsonld_seo_patch.php  (anadido 2026-06-15)
 * Mejoras SEO sobre el JSON-LD de producto del addon oscdenox/addon-structured-data.
 * Usa el evento oficial del addon (ver su README) -> NO edita codigo de vendor.
 * Incluido una vez desde product_info.php.
 *
 *  1)  gtin13: solo si el producto tiene un EAN-13 GS1 real (digito de control valido y fuera de
 *      los rangos restringidos 02/04/05 y 20-29, que son los EAN internos de Francobordo).
 *  1b) sku/mpn: fallback a products_model cuando el addon los deja vacios.
 *  2)  availability: agotado->OutOfStock, qty>0->InStock, "bajo pedido/X dias" (qty<=0 comprable)->BackOrder.
 *  3)  hasMerchantReturnPolicy: desistimiento 14 dias naturales, devolucion a cargo del cliente
 *      (gratis en tienda), ES+PT. (Politica: envio-de-productos-i-1.html). El envio NO se declara aqui
 *      por ser variable segun peso/volumen -> se gestiona en Google Merchant Center.
 */

use util\event;

if (!function_exists('fb_is_real_gs1_ean13')) {
    function fb_is_real_gs1_ean13($ean)
    {
        $ean = trim((string)$ean);
        if (!preg_match('/^[0-9]{13}$/', $ean)) {
            return false;
        }
        if (preg_match('/^(2|0[245]|9[89])/', $ean)) {
            return false; // rangos restringidos GS1: 2x/02/04/05 (uso interno) + 98x/99x (cupones/devoluciones)
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $d = (int)$ean[$i];
            $sum += ($i % 2 === 0) ? $d : $d * 3;
        }
        $check = (10 - ($sum % 10)) % 10;
        return $check === (int)$ean[12];
    }
}

event::getInstance()->add('front_office_addon_structured_data_product_before', function ($productEntity) {
    if (!is_object($productEntity)) {
        return;
    }
    $pid = (int)$productEntity->getProperty('productID');
    if ($pid <= 0) {
        return;
    }
    $res = tep_db_query("select product_ean, products_model, products_quantity, check_stock from " . TABLE_PRODUCTS . " where products_id = '" . $pid . "' limit 1");
    if (!$res) {
        return;
    }
    $row = tep_db_fetch_array($res);
    if (!$row) {
        return;
    }

    // 1) gtin13 (solo EAN-13 GS1 real)
    $ean = trim((string)($row['product_ean'] ?? ''));
    if (fb_is_real_gs1_ean13($ean)) {
        $productEntity->gtin13($ean);
    }

    // 1b) sku / mpn de respaldo desde products_model (sin pisar lo que el addon ya haya puesto)
    if ((string)$productEntity->getProperty('sku', '') === '') {
        $model = trim((string)($row['products_model'] ?? ''));
        if ($model !== '') {
            $productEntity->sku($model);
            $productEntity->mpn($model);
        }
    }

    // Offer (objeto por referencia dentro del Product)
    $offers = $productEntity->getProperty('offers');
    if (is_array($offers)) {
        $offers = $offers[0] ?? null;
    }
    if (!is_object($offers)) {
        return;
    }

    // 2) availability afinada (misma logica que el boton de compra de la ficha)
    if (function_exists('claseBotonComprar')) {
        $qty   = (int)($row['products_quantity'] ?? 0);
        $check = $row['check_stock'] ?? '';
        $clase = trim(claseBotonComprar($qty, $check));
        if ($clase === 'prdt-agtd' && function_exists('maxActiveVariantStock')) {
            $nVar = maxActiveVariantStock($pid);
            if ($nVar !== null) {
                $clase = trim(claseBotonComprar((int)$nVar, $check));
            }
        }
        if (strpos($clase, 'prdt-agtd') !== false) {
            $av = 'https://schema.org/OutOfStock';
        } elseif ($qty > 0) {
            $av = 'https://schema.org/InStock';
        } else {
            $av = 'https://schema.org/BackOrder';
        }
        $offers->availability($av);
    }

    // 3) Politica de devoluciones (desistimiento 14 dias naturales; gastos de devolucion a cargo del
    //    cliente, gratis en tienda). Igual para todos los productos. -> hasMerchantReturnPolicy
    $offers->returnPolicy([
        '@type'                 => 'MerchantReturnPolicy',
        'applicableCountry'     => ['ES', 'PT'],
        'returnPolicyCountry'   => 'ES',
        'returnPolicyCategory'  => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays'    => 14,
        'returnMethod'          => 'https://schema.org/ReturnByMail',
        'returnFees'            => 'https://schema.org/ReturnFeesCustomerResponsibility',
    ]);
});
