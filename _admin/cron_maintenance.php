<?php
/*
 * Mantenimiento diario de la tienda (NO sistema de puntos).
 * Extraído de customers_points_expire.php el 2026-05-16 para separar responsabilidades.
 *
 * Hace:
 *  - Sincroniza precios de grupos (distribución / Amazon / Ebay) con productos nuevos.
 *  - Limpia precios huérfanos (productos eliminados).
 *  - Sincroniza atributos en los grupos.
 *  - Limpia atributos huérfanos.
 *  - Desmarca productos destacados caducados.
 *  - Borra ofertas caducadas / de productos descatalogados.
 *
 * Invocar diario vía cron HTTP (mismo patrón que customers_points_expire.php).
 */

include_once('includes/application_top.php');

// Guardia: solo aceptamos invocaciones desde el cron (User-Agent cPanel-Cron).
// El script está exento de login admin (application_top.php), así que necesita su propio gate.
if (($_SERVER['HTTP_USER_AGENT'] ?? '') !== 'cPanel-Cron') {
    http_response_code(403);
    exit('Forbidden');
}

echo "<pre>\n";

// --- Precios por grupo --------------------------------------------------------
tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . " (customers_group_id, customers_group_price, products_id, products_qty_blocks, products_min_order_qty) select 1, products_price*0.9, products_id, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS . " where products_id not in (select products_id from " . TABLE_PRODUCTS_GROUPS . " where customers_group_id=1)");
echo "Insertados precios en grupo 1 (Distribución)\n";

tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . " (customers_group_id, customers_group_price, products_id, products_qty_blocks, products_min_order_qty) select 2, products_price*1.1, products_id, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS . " where products_id not in (select products_id from " . TABLE_PRODUCTS_GROUPS . " where customers_group_id=2)");
echo "Insertados precios en grupo 2 (Amazon)\n";

tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . " (customers_group_id, customers_group_price, products_id, products_qty_blocks, products_min_order_qty) select 3, products_price*1.1, products_id, products_qty_blocks, products_min_order_qty from " . TABLE_PRODUCTS . " where products_id not in (select products_id from " . TABLE_PRODUCTS_GROUPS . " where customers_group_id=3)");
echo "Insertados precios en grupo 3 (Ebay)\n";

tep_db_query("delete from " . TABLE_PRODUCTS_GROUPS . " where products_id not in (select products_id from " . TABLE_PRODUCTS . ")");
echo "Borrados precios de productos eliminados\n";

// --- Atributos por grupo ------------------------------------------------------
tep_db_query("insert into " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id) select products_attributes_id, 1, options_values_price*0.9, price_prefix, products_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_attributes_id not in (select products_attributes_id from " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where customers_group_id=1)");
echo "Insertados atributos en grupo 1 (Distribución)\n";

tep_db_query("insert into " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id) select products_attributes_id, 2, options_values_price*1.1, price_prefix, products_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_attributes_id not in (select products_attributes_id from " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where customers_group_id=2)");
echo "Insertados atributos en grupo 2 (Amazon)\n";

tep_db_query("insert into " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id) select products_attributes_id, 3, options_values_price*1.1, price_prefix, products_id from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_attributes_id not in (select products_attributes_id from " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where customers_group_id=3)");
echo "Insertados atributos en grupo 3 (Ebay)\n";

tep_db_query("delete from " . TABLE_PRODUCTS_ATTRIBUTES_GROUPS . " where products_attributes_id not in (select products_attributes_id from " . TABLE_PRODUCTS_ATTRIBUTES . ")");
echo "Borrados atributos de productos eliminados\n";

// --- Productos destacados caducados ------------------------------------------
tep_db_query("update " . TABLE_PRODUCTS . " set products_featured='0', products_featured_until='0000-00-00' where products_featured=1 and (products_featured_until is null or products_featured_until='0000-00-00')");
echo "Actualizados productos destacados\n";

// --- Ofertas caducadas / de productos descatalogados -------------------------
tep_db_query("UPDATE " . TABLE_PRODUCTS . " SET products_last_modified = NOW() WHERE products_id in (SELECT products_id from " . TABLE_SPECIALS . " WHERE expires_date < CURDATE() AND expires_date != '0000-00-00 00:00:00')");
tep_db_query("DELETE FROM " . TABLE_SPECIALS . " WHERE expires_date < CURDATE() AND expires_date != '0000-00-00 00:00:00'");
echo "Borradas ofertas caducadas\n";

tep_db_query("delete from " . TABLE_SPECIALS . " WHERE products_id in (SELECT products_id FROM products WHERE products_status=0)");
echo "Borradas ofertas de productos descatalogados\n";

echo "</pre>\n";
