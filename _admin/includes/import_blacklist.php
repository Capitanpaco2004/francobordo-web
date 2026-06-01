<?php
/**
 * Lista negra de reimportación  (import_blacklist)
 * -------------------------------------------------
 * Registro de productos borrados A PROPÓSITO que NO deben volver a darse de alta
 * por ninguno de los importadores de proveedor (import-*-altas.php).
 *
 * Problema que resuelve: al borrar un producto importado que no interesa, el siguiente
 * pase del importador lo vuelve a crear (vuelve a descargar imágenes y datos). Con esta
 * lista negra el importador trata esos códigos/EAN como "ya conocidos" y los salta, igual
 * que hace con los que ya están en `products`.
 *
 * Clave de bloqueo (la misma con la que deduplican los importadores):
 *   - products_model
 *   - reference_prov
 *   - product_ean / products_attributes_ean
 * Todo se compara en minúsculas. `source` = products_import_origin (informativo).
 *
 * Usa la capa tep_db_* de osCommerce, disponible en TODOS los importadores y en el admin
 * (vía includes/application_top.php). Reversible: basta borrar la fila de import_blacklist.
 *
 * Creado 2026-05-30.
 */

if (!function_exists('fb_blacklist_ensure_table')) {

    /** Crea la tabla si no existe (idempotente, sin necesidad de SQL manual). */
    function fb_blacklist_ensure_table() {
        static $done = false;
        if ($done) return;
        tep_db_query("CREATE TABLE IF NOT EXISTS import_blacklist (
            id int NOT NULL AUTO_INCREMENT,
            bl_key varchar(190) NOT NULL,
            bl_type enum('model','reference','ean') NOT NULL DEFAULT 'model',
            products_id int DEFAULT NULL,
            manufacturers_id int DEFAULT NULL,
            source varchar(64) DEFAULT NULL,
            products_name varchar(255) DEFAULT NULL,
            reason varchar(255) DEFAULT NULL,
            deleted_by varchar(96) DEFAULT NULL,
            date_added datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_key (bl_key, bl_type),
            KEY idx_key (bl_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $done = true;
    }

    /**
     * Devuelve el set de claves (minúsculas) que deben bloquear el reimport:
     * modelos + referencias + EANs. Formato [clave => true] para fusionar con `$existing`
     * de los importadores mediante `$existing += fb_blacklist_keys();`.
     * Cacheado en estático (consulta una sola vez por petición).
     */
    function fb_blacklist_keys() {
        static $cache = null;
        if ($cache !== null) return $cache;
        fb_blacklist_ensure_table();
        $cache = array();
        $q = tep_db_query("SELECT LOWER(bl_key) AS k FROM import_blacklist WHERE bl_key <> ''");
        while ($r = tep_db_fetch_array($q)) $cache[$r['k']] = true;
        return $cache;
    }

    /** ¿Está esta clave (modelo/ref/EAN) en la lista negra? Comparación en minúsculas. */
    function fb_blacklist_has($key) {
        $key = strtolower(trim((string)$key));
        if ($key === '') return false;
        $keys = fb_blacklist_keys();
        return isset($keys[$key]);
    }

    /**
     * Añade un producto (y sus variantes) a la lista negra ANTES de borrarlo.
     * Registra products_model, reference_prov y product_ean de la cabecera, más
     * reference / reference_prov / products_attributes_ean de cada variante.
     * Devuelve el nº de claves registradas.
     */
    function fb_blacklist_add_product($products_id, $reason = '', $deleted_by = '') {
        fb_blacklist_ensure_table();
        $pid = (int)$products_id;
        if ($pid <= 0) return 0;

        $q = tep_db_query("SELECT p.products_model, p.reference_prov, p.product_ean,
                                  p.manufacturers_id, p.products_import_origin,
                                  (SELECT pd.products_name FROM products_description pd
                                    WHERE pd.products_id = p.products_id
                                    ORDER BY pd.language_id LIMIT 1) AS products_name
                           FROM products p WHERE p.products_id = $pid");
        $p = tep_db_fetch_array($q);
        if (!$p) return 0;

        $mid  = (int)$p['manufacturers_id'];
        $src  = $p['products_import_origin'];
        $name = $p['products_name'];

        $rows = array();
        if (trim((string)$p['products_model']) !== '') $rows[] = array('model',     $p['products_model']);
        if (trim((string)$p['reference_prov']) !== '') $rows[] = array('reference', $p['reference_prov']);
        if (trim((string)$p['product_ean'])    !== '') $rows[] = array('ean',       $p['product_ean']);

        $qa = tep_db_query("SELECT reference, reference_prov, products_attributes_ean
                            FROM products_attributes WHERE products_id = $pid");
        while ($a = tep_db_fetch_array($qa)) {
            if (trim((string)$a['reference'])               !== '') $rows[] = array('reference', $a['reference']);
            if (trim((string)$a['reference_prov'])          !== '') $rows[] = array('reference', $a['reference_prov']);
            if (trim((string)$a['products_attributes_ean']) !== '') $rows[] = array('ean',       $a['products_attributes_ean']);
        }

        $srcSql  = ($src !== null && $src !== '') ? "'" . tep_db_input($src) . "'" : 'NULL';
        $midSql  = $mid > 0 ? $mid : 'NULL';
        $n = 0;
        foreach ($rows as $row) {
            $type = $row[0];
            $val  = trim($row[1]);
            if ($val === '') continue;
            tep_db_query("INSERT IGNORE INTO import_blacklist
                (bl_key, bl_type, products_id, manufacturers_id, source, products_name, reason, deleted_by, date_added)
                VALUES (
                    '" . tep_db_input($val)  . "',
                    '" . tep_db_input($type) . "',
                    $pid, $midSql, $srcSql,
                    '" . tep_db_input((string)$name)        . "',
                    '" . tep_db_input((string)$reason)      . "',
                    '" . tep_db_input((string)$deleted_by)  . "',
                    NOW())");
            $n++;
        }
        return $n;
    }
}
