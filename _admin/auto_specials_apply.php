<?php
// auto_specials_apply.php — aplicación masiva de ofertas.
// POST con items[] en formato "pid" (sin variante, Fase 3) o "pid:ovid" (variante, Fase 4).
require 'includes/application_top.php';
require __DIR__ . '/auto_specials_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tep_redirect(tep_href_link('auto_specials_preview.php'));
}

$items_raw = $_POST['items'] ?? [];
if (!is_array($items_raw)) $items_raw = [];

// Soporte legacy: ids[] = solo pid (sin variante).
$legacy = $_POST['ids'] ?? [];
if (is_array($legacy)) {
    foreach ($legacy as $pid) $items_raw[] = (int)$pid;
}

$targets = [];
foreach ($items_raw as $raw) {
    $raw = (string)$raw;
    if (strpos($raw, ':') !== false) {
        [$pid, $ovid] = array_pad(explode(':', $raw, 2), 2, 0);
        $targets[] = [(int)$pid, (int)$ovid];
    } else {
        $targets[] = [(int)$raw, 0];
    }
}
// Dedupe
$seen = [];
$targets = array_values(array_filter($targets, function ($t) use (&$seen) {
    if ($t[0] <= 0) return false;
    $k = $t[0] . ':' . $t[1];
    if (isset($seen[$k])) return false;
    $seen[$k] = 1;
    return true;
}));

$user = (string)($_SESSION['login']['user_login'] ?? 'admin');

$rules = [];
$rq = tep_db_query("SELECT * FROM auto_specials_tier_rules WHERE activo=1
                    ORDER BY descuento_pct DESC, prioridad ASC");
while ($rr = tep_db_fetch_array($rq)) $rules[] = $rr;
$overrides_map = as_load_overrides_map();

$applied = 0; $skipped = 0; $errors = 0; $details = [];
$now = date('Y-m-d H:i:s');

foreach ($targets as $t) {
    [$pid, $ovid] = $t;

    if ($ovid === 0) {
        // -------------------- Fase 3: producto sin variantes --------------------
        $q = tep_db_query("
            SELECT p.products_price, p.products_cost, p.manufacturers_id,
                   v.dias_sin_venta, v.dias_sin_compra, v.dias_cobertura, v.stock_variant,
                   (SELECT COUNT(*) FROM products_attributes pa WHERE pa.products_id=p.products_id) AS n_variants
            FROM products p
            JOIN qfac_sales_velocity v ON v.products_id=p.products_id AND v.options_values_id=0
            WHERE p.products_id={$pid} AND p.products_status=1 LIMIT 1");
        $row = tep_db_fetch_array($q);
        if (!$row) { $errors++; $details[] = "#{$pid}: no activo o sin velocity"; continue; }
        if ((int)$row['n_variants'] > 0) {
            $skipped++; $details[] = "#{$pid}: tiene variantes, mandar como pid:ovid"; continue;
        }
        $rule = as_pick_rule_with_override($row['dias_sin_venta'], $row['dias_cobertura'], (int)$row['manufacturers_id'], $rules, $overrides_map, $pid, $ovid, $row['dias_sin_compra'] ?? null);
        if (!$rule) { $skipped++; $details[] = "#{$pid}: sin regla aplicable"; continue; }

        $pvp = (float)$row['products_price'];
        $cost = (float)$row['products_cost'];
        if ($pvp <= 0) { $errors++; $details[] = "#{$pid}: PVP 0"; continue; }

        [$pvp_oferta, $floor, $capped] = as_compute_offer(
            $pvp, $cost, (float)$rule['descuento_pct'], $rule['min_margin_pct']);

        if ($pvp_oferta >= $pvp) {
            $skipped++; $details[] = "#{$pid}: descuento no mejora PVP"; continue;
        }

        $vig = (int)$rule['vigencia_dias'];
        $expires = date('Y-m-d H:i:s', strtotime("+{$vig} days"));
        $stock_snap = (int)$row['stock_variant'];

        // cgid=0
        $exist0 = as_get_active_special($pid, 0);
        $prev0 = $exist0 ? (float)$exist0['specials_new_products_price'] : null;
        if ($exist0 && $prev0 !== null && $prev0 <= $pvp_oferta) {
            $details[] = "#{$pid} cgid=0: special existente ya mejor o igual";
        } else {
            $pvp_str = sprintf('%.4f', $pvp_oferta);
            $floor_sql = $floor !== null ? sprintf('%.4f', $floor) : '0';
            if ($exist0) {
                $sid = (int)$exist0['specials_id'];
                tep_db_query("UPDATE specials SET
                    specials_new_products_price={$pvp_str}, specials_min_price={$floor_sql},
                    expires_date='{$expires}', expires_repeat=1,
                    start_date='{$now}', specials_last_modified='{$now}', status=1 WHERE specials_id={$sid}");
            } else {
                tep_db_query("INSERT INTO specials
                  (products_id, specials_new_products_price, specials_min_price,
                   specials_date_added, specials_last_modified, expires_date, expires_repeat,
                   status, customers_group_id, start_date)
                  VALUES ({$pid}, {$pvp_str}, {$floor_sql},
                    '{$now}', '{$now}', '{$expires}', 1, 1, 0, '{$now}')");
                $sid = (int)tep_db_insert_id();
            }
            tep_db_query("UPDATE auto_specials_active SET estado='closed_replaced', updated_at='{$now}'
                          WHERE products_id={$pid} AND options_values_id=0 AND customers_group_id=0 AND estado='active'");
            tep_db_query("INSERT INTO auto_specials_active
                (products_id, options_values_id, customers_group_id, rule_id, specials_id,
                 pvp_original, pvp_oferta, pct_aplicado, min_floor, capped_to_floor,
                 stock_snapshot, fecha_inicio, fecha_fin, estado, motivo, usuario,
                 prev_specials_price, prev_specials_existed, created_at, updated_at)
                VALUES ({$pid}, 0, 0, {$rule['id']}, {$sid},
                  {$pvp}, {$pvp_str}, {$rule['descuento_pct']}, " . ($floor !== null ? $floor_sql : 'NULL') . ", " . ($capped?1:0) . ",
                  {$stock_snap}, '{$now}', '{$expires}', 'active', '" . tep_db_input($rule['nota'] ?? '') . "',
                  '" . tep_db_input($user) . "',
                  " . ($prev0 !== null ? sprintf('%.4f', $prev0) : 'NULL') . ",
                  " . ($exist0 ? 1 : 0) . ", '{$now}', '{$now}')");
            $applied++;
        }

        // G1 piping (Fase 3)
        $price_g1 = as_get_g1_price($pid);
        if ($price_g1 !== null && $pvp_oferta < $price_g1) {
            $exist1 = as_get_active_special($pid, 1);
            $prev1 = $exist1 ? (float)$exist1['specials_new_products_price'] : null;
            if (!$exist1 || $prev1 === null || $prev1 > $pvp_oferta) {
                $pvp_str = sprintf('%.4f', $pvp_oferta);
                $floor_sql = $floor !== null ? sprintf('%.4f', $floor) : '0';
                if ($exist1) {
                    $sid1 = (int)$exist1['specials_id'];
                    tep_db_query("UPDATE specials SET
                        specials_new_products_price={$pvp_str}, specials_min_price={$floor_sql},
                        expires_date='{$expires}', expires_repeat=1,
                        start_date='{$now}', specials_last_modified='{$now}', status=1 WHERE specials_id={$sid1}");
                } else {
                    tep_db_query("INSERT INTO specials
                      (products_id, specials_new_products_price, specials_min_price,
                       specials_date_added, specials_last_modified, expires_date, expires_repeat,
                       status, customers_group_id, start_date)
                      VALUES ({$pid}, {$pvp_str}, {$floor_sql},
                        '{$now}', '{$now}', '{$expires}', 1, 1, 1, '{$now}')");
                    $sid1 = (int)tep_db_insert_id();
                }
                tep_db_query("UPDATE auto_specials_active SET estado='closed_replaced', updated_at='{$now}'
                              WHERE products_id={$pid} AND options_values_id=0 AND customers_group_id=1 AND estado='active'");
                tep_db_query("INSERT INTO auto_specials_active
                    (products_id, options_values_id, customers_group_id, rule_id, specials_id,
                     pvp_original, pvp_oferta, pct_aplicado, min_floor, capped_to_floor,
                     stock_snapshot, fecha_inicio, fecha_fin, estado, motivo, usuario,
                     prev_specials_price, prev_specials_existed, created_at, updated_at)
                    VALUES ({$pid}, 0, 1, {$rule['id']}, {$sid1},
                      {$price_g1}, {$pvp_str}, {$rule['descuento_pct']}, " . ($floor !== null ? $floor_sql : 'NULL') . ", " . ($capped?1:0) . ",
                      {$stock_snap}, '{$now}', '{$expires}', 'active', 'G1 piping',
                      '" . tep_db_input($user) . "',
                      " . ($prev1 !== null ? sprintf('%.4f', $prev1) : 'NULL') . ",
                      " . ($exist1 ? 1 : 0) . ", '{$now}', '{$now}')");
            }
        }

        // --- Defensiva: sincroniza start_date de TODOS los specials activos del producto.
        // Sin esto, otros cgid (Amazon=2, EBAY=3) con start_date antiguo quedarían
        // ocultos en la web por la query MAX(start_date) en PriceFormatterStore.
        // Excluye venta_flash=1 programadas a futuro (su start_date tiene significado).
        tep_db_query("UPDATE specials SET start_date='{$now}'
                      WHERE products_id={$pid} AND status=1
                        AND NOT (venta_flash=1 AND start_date > NOW())");

    } else {
        // -------------------- Fase 4: variante (ovid > 0) --------------------
        $q = tep_db_query("
            SELECT p.products_price, p.products_cost, p.manufacturers_id,
                   pa.products_attributes_id,
                   pa.options_values_price AS ovp0, pa.price_prefix AS pref0,
                   v.dias_sin_venta, v.dias_sin_compra, v.dias_cobertura, v.stock_variant
            FROM products p
            JOIN products_attributes pa ON pa.products_id=p.products_id AND pa.options_values_id={$ovid}
            JOIN qfac_sales_velocity v ON v.products_id=p.products_id AND v.options_values_id={$ovid}
            WHERE p.products_id={$pid} AND p.products_status=1 LIMIT 1");
        $row = tep_db_fetch_array($q);
        if (!$row) { $errors++; $details[] = "#{$pid}:{$ovid}: no encontrado"; continue; }

        $pa_id = (int)$row['products_attributes_id'];
        $rule = as_pick_rule_with_override($row['dias_sin_venta'], $row['dias_cobertura'], (int)$row['manufacturers_id'], $rules, $overrides_map, $pid, $ovid, $row['dias_sin_compra'] ?? null);
        if (!$rule) { $skipped++; $details[] = "#{$pid}:{$ovid}: sin regla"; continue; }

        $cost = (float)$row['products_cost'];
        $base = (float)$row['products_price'];
        $sign0 = ($row['pref0'] === '-') ? -1.0 : 1.0;
        $pvp_var = round($base + $sign0 * (float)$row['ovp0'], 4);
        if ($pvp_var <= 0) { $errors++; $details[] = "#{$pid}:{$ovid}: PVP variante 0"; continue; }

        [$pvp_oferta, $floor, $capped] = as_compute_offer(
            $pvp_var, $cost, (float)$rule['descuento_pct'], $rule['min_margin_pct']);
        if ($pvp_oferta >= $pvp_var) {
            $skipped++; $details[] = "#{$pid}:{$ovid}: descuento no mejora"; continue;
        }

        $vig = (int)$rule['vigencia_dias'];
        $expires = date('Y-m-d H:i:s', strtotime("+{$vig} days"));
        $stock_snap = (int)$row['stock_variant'];

        // cgid=0 → UPDATE products_attributes
        [$new_ovp, $new_prefix] = as_variant_delta_for_target($pvp_oferta, $base);
        $ovp_orig0 = (float)$row['ovp0'];
        $prefix_orig0 = (string)$row['pref0'];

        $ovp_str = sprintf('%.4f', $new_ovp);
        $changed0 = ($ovp_str !== sprintf('%.4f', $ovp_orig0) || $new_prefix !== $prefix_orig0);
        if (!$changed0) {
            $details[] = "#{$pid}:{$ovid} cgid=0: delta ya igual";
        } else {
            tep_db_query("UPDATE products_attributes SET
                options_values_price={$ovp_str},
                price_prefix='{$new_prefix}',
                attributes_last_modified='{$now}'
                WHERE products_attributes_id={$pa_id}");
            tep_db_query("UPDATE auto_specials_active SET estado='closed_replaced', updated_at='{$now}'
                          WHERE products_id={$pid} AND options_values_id={$ovid} AND customers_group_id=0 AND estado='active'");
            tep_db_query("INSERT INTO auto_specials_active
                (products_id, options_values_id, customers_group_id, rule_id, specials_id,
                 pvp_original, pvp_oferta, pct_aplicado, min_floor, capped_to_floor,
                 stock_snapshot, fecha_inicio, fecha_fin, estado, motivo, usuario,
                 prev_specials_price, prev_specials_existed,
                 ovp_orig, prefix_orig, created_at, updated_at)
                VALUES ({$pid}, {$ovid}, 0, {$rule['id']}, NULL,
                  {$pvp_var}, " . sprintf('%.4f', $pvp_oferta) . ", {$rule['descuento_pct']}, " . ($floor !== null ? sprintf('%.4f', $floor) : 'NULL') . ", " . ($capped?1:0) . ",
                  {$stock_snap}, '{$now}', '{$expires}', 'active', '" . tep_db_input('Variante · ' . ($rule['nota'] ?? '')) . "',
                  '" . tep_db_input($user) . "',
                  NULL, 0,
                  " . sprintf('%.4f', $ovp_orig0) . ", '{$prefix_orig0}', '{$now}', '{$now}')");
            $applied++;
        }

        // cgid=1 (G1) piping en variante: comparar precio actual G1 y bajar delta_g1 si la oferta es menor
        $price_g1 = as_get_variant_g1_price($pa_id, $pid);
        if ($price_g1 !== null && $pvp_oferta < $price_g1) {
            // base G1 = precio padre G1 (de specials G1 → products_groups → products_price)
            $qg = tep_db_query("SELECT specials_new_products_price FROM specials
                                WHERE products_id={$pid} AND customers_group_id=1 AND status=1 LIMIT 1");
            if ($r1 = tep_db_fetch_array($qg)) {
                $base_g1 = (float)$r1['specials_new_products_price'];
            } else {
                $qg = tep_db_query("SELECT customers_group_price FROM products_groups WHERE products_id={$pid} AND customers_group_id=1 LIMIT 1");
                if ($r1 = tep_db_fetch_array($qg)) {
                    $base_g1 = (float)$r1['customers_group_price'];
                } else {
                    $base_g1 = $base;
                }
            }

            [$new_ovp_g1, $new_prefix_g1] = as_variant_delta_for_target($pvp_oferta, $base_g1);

            // Lee fila g1 actual (puede no existir → INSERT)
            $qg = tep_db_query("SELECT options_values_price, price_prefix FROM products_attributes_groups
                                WHERE products_attributes_id={$pa_id} AND customers_group_id=1 LIMIT 1");
            $r1 = tep_db_fetch_array($qg);
            $ovp_orig1 = $r1 ? (float)$r1['options_values_price'] : null;
            $prefix_orig1 = $r1 ? (string)$r1['price_prefix'] : null;

            $ovp_str1 = sprintf('%.4f', $new_ovp_g1);
            if ($r1) {
                tep_db_query("UPDATE products_attributes_groups SET
                    options_values_price={$ovp_str1}, price_prefix='{$new_prefix_g1}'
                    WHERE products_attributes_id={$pa_id} AND customers_group_id=1");
            } else {
                tep_db_query("INSERT INTO products_attributes_groups
                    (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id,
                     options_values_weight, weight_prefix)
                    VALUES ({$pa_id}, 1, {$ovp_str1}, '{$new_prefix_g1}', {$pid}, 0, '')");
            }
            tep_db_query("UPDATE auto_specials_active SET estado='closed_replaced', updated_at='{$now}'
                          WHERE products_id={$pid} AND options_values_id={$ovid} AND customers_group_id=1 AND estado='active'");
            tep_db_query("INSERT INTO auto_specials_active
                (products_id, options_values_id, customers_group_id, rule_id, specials_id,
                 pvp_original, pvp_oferta, pct_aplicado, min_floor, capped_to_floor,
                 stock_snapshot, fecha_inicio, fecha_fin, estado, motivo, usuario,
                 prev_specials_price, prev_specials_existed,
                 ovp_orig, prefix_orig, created_at, updated_at)
                VALUES ({$pid}, {$ovid}, 1, {$rule['id']}, NULL,
                  {$price_g1}, " . sprintf('%.4f', $pvp_oferta) . ", {$rule['descuento_pct']}, " . ($floor !== null ? sprintf('%.4f', $floor) : 'NULL') . ", " . ($capped?1:0) . ",
                  {$stock_snap}, '{$now}', '{$expires}', 'active', 'Variante G1 piping',
                  '" . tep_db_input($user) . "',
                  NULL, 0,
                  " . ($ovp_orig1 !== null ? sprintf('%.4f', $ovp_orig1) : 'NULL') . ",
                  " . ($prefix_orig1 !== null ? "'{$prefix_orig1}'" : 'NULL') . ", '{$now}', '{$now}')");
        }
    }
}

$flash = "Aplicadas: {$applied} · saltadas: {$skipped} · errores: {$errors}";
if ($details) {
    $flash .= "\n\nDetalle:\n" . implode("\n", array_slice($details, 0, 50));
    if (count($details) > 50) $flash .= "\n…y " . (count($details)-50) . " más";
}
$_SESSION['auto_specials_flash'] = $flash;

$back = $_POST['return_url'] ?? 'auto_specials_preview.php';
tep_redirect($back);
