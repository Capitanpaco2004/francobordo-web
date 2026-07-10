<?php
/**
 * Helpers compartidos del motor auto_specials.
 *
 * Incluir desde otras páginas: require __DIR__ . '/auto_specials_helpers.php';
 */

/**
 * Selecciona la regla aplicable a una variante.
 * Criterio OR: cumple si (dias_sin_venta ≥ X) O (cobertura_efectiva ≥ Y).
 * Entre elegibles gana mayor descuento_pct.
 */
function as_pick_rule($dias_sin_venta, $dias_cob, int $mfg_id, array $rules, $dias_sin_compra = null): ?array {
    // Cada criterio es independiente:
    //   - dias_sin_venta_min: días sin venta requeridos (criterio principal).
    //   - dias_cobertura_min: alternativa por cobertura alta (OR con sin_venta).
    //   - dias_sin_compra_min: gate adicional (AND): solo si llevamos N días sin reponer.
    //     NULL/0 = sin gate (no exige días sin compra).
    $dsv_eff = ($dias_sin_venta === null) ? PHP_INT_MAX : (int)$dias_sin_venta;
    $dsc_eff = ($dias_sin_compra === null) ? PHP_INT_MAX : (int)$dias_sin_compra;
    $cob_eff = ($dias_cob === null)       ? PHP_INT_MAX : (float)$dias_cob;
    $best = null;
    foreach ($rules as $r) {
        $rmfg = (int)$r['manufacturers_id'];
        if ($rmfg !== 0 && $rmfg !== $mfg_id) continue;
        $m_venta = ($dsv_eff >= (int)$r['dias_sin_venta_min']);
        $m_cob   = ($r['dias_cobertura_min'] !== null) && ($cob_eff >= (float)$r['dias_cobertura_min']);
        // Gate compra: si la regla pide ≥ N días sin compra, debe cumplirse.
        $compra_min = isset($r['dias_sin_compra_min']) && $r['dias_sin_compra_min'] !== null ? (int)$r['dias_sin_compra_min'] : 0;
        $m_compra = ($compra_min <= 0) ? true : ($dsc_eff >= $compra_min);
        if ((!$m_venta && !$m_cob) || !$m_compra) continue;
        if ($best === null || (float)$r['descuento_pct'] > (float)$best['descuento_pct']) {
            $best = $r;
        }
    }
    return $best;
}

/**
 * Redondeo universal a múltiplos de 0.05 (memoria feedback_round_to_nickel).
 */
function as_round_nickel(float $v): float {
    return round($v / 0.05) * 0.05;
}

/**
 * Calcula precio de oferta y piso a partir de PVP, coste y regla.
 *
 * El **margen es sobre PVP** (no sobre coste):
 *   floor = cost / (1 − min_margin/100)
 *   Si min_margin=0  → floor = cost
 *   Si min_margin=NULL → no hay piso
 *   Si cost=0/NULL  → no hay piso (sin info de margen)
 *
 * Devuelve:
 *   [pvp_oferta, floor (o null), capped (bool), motivo (string)]
 */
function as_compute_offer(float $pvp, $cost, float $pct, $min_margin_pct): array {
    $raw  = $pvp * (1 - $pct / 100.0);
    $raw  = as_round_nickel($raw);
    $floor = null;
    $cost_f = (float)$cost;
    if ($cost_f > 0 && $min_margin_pct !== null) {
        $m = (float)$min_margin_pct / 100.0;
        if ($m >= 1.0) $m = 0.99; // safety
        $floor = as_round_nickel($cost_f / (1.0 - $m));
    }
    $capped = false;
    $pvp_oferta = $raw;
    if ($floor !== null && $pvp_oferta < $floor) {
        $pvp_oferta = $floor;
        $capped = true;
    }
    return [$pvp_oferta, $floor, $capped];
}

/**
 * Precio efectivo G1 para un producto sin variantes:
 *   1) specials activo con cgid=1 → ese precio
 *   2) products_groups.customers_group_price (cgid=1) → ese precio
 *   3) products.products_price (no hay diferenciación)
 */
function as_get_g1_price(int $pid): ?float {
    $q = tep_db_query("SELECT specials_new_products_price FROM specials
                       WHERE products_id={$pid} AND customers_group_id=1 AND status=1
                       LIMIT 1");
    if ($row = tep_db_fetch_array($q)) {
        return (float)$row['specials_new_products_price'];
    }
    $q = tep_db_query("SELECT customers_group_price FROM products_groups
                       WHERE products_id={$pid} AND customers_group_id=1 LIMIT 1");
    if ($row = tep_db_fetch_array($q)) {
        return (float)$row['customers_group_price'];
    }
    $q = tep_db_query("SELECT products_price FROM products WHERE products_id={$pid} LIMIT 1");
    if ($row = tep_db_fetch_array($q)) {
        return (float)$row['products_price'];
    }
    return null;
}

/**
 * Carga TODOS los overrides en un dict (pid, ovid) → row.
 * Llamar una vez al inicio de la página para evitar N+1 queries.
 */
function as_load_overrides_map(): array {
    $map = [];
    $q = tep_db_query("SELECT * FROM auto_specials_overrides");
    while ($r = tep_db_fetch_array($q)) {
        $key = (int)$r['products_id'] . ':' . (int)$r['options_values_id'];
        $map[$key] = $r;
    }
    return $map;
}

/**
 * Devuelve una "regla virtual" si hay override para (pid, ovid),
 * en caso contrario, llama a as_pick_rule normal.
 *
 * La regla virtual hereda vigencia/margen de la regla normal SI también
 * existe (para que apply tenga defaults razonables); sino usa el override.
 */
function as_pick_rule_with_override($dias_sin_venta, $dias_cob, int $mfg_id, array $rules,
                                    array $overrides_map, int $pid, int $ovid,
                                    $dias_sin_compra = null): ?array {
    $key = $pid . ':' . $ovid;
    if (isset($overrides_map[$key])) {
        $ov = $overrides_map[$key];
        $base = as_pick_rule($dias_sin_venta, $dias_cob, $mfg_id, $rules, $dias_sin_compra);
        return [
            'id'                  => 0, // virtual
            'is_override'         => true,
            'override_id'         => (int)$ov['id'],
            'manufacturers_id'    => 0,
            'prioridad'           => 0,
            'dias_sin_venta_min'  => 0,
            'dias_cobertura_min' => null,
            'descuento_pct'       => $ov['descuento_pct'],
            'vigencia_dias'       => $ov['vigencia_dias'] !== null ? $ov['vigencia_dias']
                                     : ($base['vigencia_dias'] ?? 60),
            'min_margin_pct'      => array_key_exists('min_margin_pct', $ov) && $ov['min_margin_pct'] !== null
                                     ? $ov['min_margin_pct']
                                     : ($base['min_margin_pct'] ?? 10),
            'activo'              => 1,
            'nota'                => 'Override: ' . ($ov['nota'] ?? ''),
        ];
    }
    return as_pick_rule($dias_sin_venta, $dias_cob, $mfg_id, $rules, $dias_sin_compra);
}

/**
 * Lee specials activo (no expirado) para (pid, cgid).
 */
function as_get_active_special(int $pid, int $cgid): ?array {
    $q = tep_db_query("SELECT * FROM specials
                       WHERE products_id={$pid} AND customers_group_id={$cgid}
                         AND status=1
                       ORDER BY specials_id DESC LIMIT 1");
    $row = tep_db_fetch_array($q);
    return $row ?: null;
}

// ===========================================================================
// Fase 4 — variantes
// ===========================================================================

/**
 * Aplica una oferta a UNA variante ajustando su delta (products_attributes) y/o
 * el delta G1 (products_attributes_groups cgid=1).
 * Registra snapshot en auto_specials_active para poder revertir.
 *
 * $pvp_oferta_net: precio final NETO (sin IVA) para cgid=0 (Retail), o NULL para no tocar Retail.
 * $pvp_g1_net:     precio final NETO explicito para G1, o NULL. Si NULL y $also_g1=true,
 *                  se iguala al de Retail cuando este quede por debajo del precio G1 (piping).
 * $auto_renew: 1 = el cron renueva mientras haya stock; 0 = manual, respeta fecha_fin.
 *
 * Devuelve array(bool ok, string detalle).
 */
function as_apply_variant_offer(int $pid, int $ovid, $pvp_oferta_net, $floor, bool $capped,
                                int $vigencia_dias, $rule_id, string $motivo, string $usuario,
                                int $auto_renew = 1, bool $also_g1 = true, $pvp_g1_net = null): array {
    $now = date('Y-m-d H:i:s');
    $q = tep_db_query("
        SELECT p.products_price, p.products_cost,
               pa.products_attributes_id, pa.options_id,
               pa.options_values_price AS ovp0, pa.price_prefix AS pref0
        FROM products p
        JOIN products_attributes pa ON pa.products_id = p.products_id AND pa.options_values_id = {$ovid}
        WHERE p.products_id = {$pid} AND p.products_status = 1 LIMIT 1");
    $row = tep_db_fetch_array($q);
    if (!$row) return [false, "#{$pid}:{$ovid}: variante no encontrada o producto inactivo"];

    $pa_id = (int)$row['products_attributes_id'];
    $base  = (float)$row['products_price'];
    $sign0 = ($row['pref0'] === '-') ? -1.0 : 1.0;
    // El frontend escala el precio de variante con el ratio del specials de PRODUCTO:
    // precio_web = (base ± delta) × ratio. Los targets que recibimos son precios WEB
    // (lo que debe ver el cliente), así que el delta se calcula compensando el ratio.
    $ratio0 = as_product_offer_ratio($pid, 0);
    $pvp_var_full = round($base + $sign0 * (float)$row['ovp0'], 4);
    $pvp_var = round($pvp_var_full * $ratio0, 4); // precio web actual
    if ($pvp_oferta_net === null && $pvp_g1_net === null)
        return [false, "#{$pid}:{$ovid}: indica precio Retail, precio G1 o ambos"];
    if ($pvp_oferta_net !== null) {
        $pvp_oferta_net = (float)$pvp_oferta_net;
        if ($pvp_oferta_net <= 0) return [false, "#{$pid}:{$ovid}: precio de oferta Retail invalido"];
        if ($pvp_oferta_net >= $pvp_var) return [false, "#{$pid}:{$ovid}: la oferta Retail (" . number_format($pvp_oferta_net,4) . ") no mejora el precio web actual (" . number_format($pvp_var,4) . ")"];
    }

    // Stock actual de la variante (informativo para el cierre por stock)
    $stock_snap = 0;
    $sq = tep_db_query("SELECT products_stock_quantity FROM products_stock
                        WHERE products_id={$pid}
                          AND products_stock_attributes = '" . (int)$row['options_id'] . "-{$ovid}' LIMIT 1");
    if ($sr = tep_db_fetch_array($sq)) {
        $stock_snap = (int)$sr['products_stock_quantity'];
        if ($stock_snap < 0 || $stock_snap == 2000) $stock_snap = 0;
    }

    $expires = date('Y-m-d H:i:s', strtotime("+{$vigencia_dias} days"));
    $detalle = "#{$pid}:{$ovid}:";

    // cgid=0 → delta en products_attributes (solo si hay precio Retail)
    if ($pvp_oferta_net !== null) {
        // Compensar ratio: para que el cliente vea T, el precio completo debe ser T/ratio
        [$new_ovp, $new_prefix] = as_variant_delta_for_target(round($pvp_oferta_net / $ratio0, 4), $base);
        $ovp_orig0 = (float)$row['ovp0'];
        $prefix_orig0 = (string)$row['pref0'];
        tep_db_query("UPDATE products_attributes SET
            options_values_price=" . sprintf('%.4f', $new_ovp) . ",
            price_prefix='{$new_prefix}',
            attributes_last_modified='{$now}'
            WHERE products_attributes_id={$pa_id}");
        tep_db_query("UPDATE auto_specials_active SET estado='closed_replaced', updated_at='{$now}'
                      WHERE products_id={$pid} AND options_values_id={$ovid} AND customers_group_id=0 AND estado='active'");
        $pct = ($pvp_var > 0) ? round((1 - $pvp_oferta_net / $pvp_var) * 100, 2) : 0;
        tep_db_query("INSERT INTO auto_specials_active
            (products_id, options_values_id, customers_group_id, rule_id, specials_id,
             pvp_original, pvp_oferta, pct_aplicado, min_floor, capped_to_floor,
             stock_snapshot, fecha_inicio, fecha_fin, estado, auto_renew, motivo, usuario,
             prev_specials_price, prev_specials_existed,
             ovp_orig, prefix_orig, created_at, updated_at)
            VALUES ({$pid}, {$ovid}, 0, " . ($rule_id !== null ? (int)$rule_id : 'NULL') . ", NULL,
              " . sprintf('%.4f', $pvp_var) . ", " . sprintf('%.4f', $pvp_oferta_net) . ", {$pct}, " . ($floor !== null ? sprintf('%.4f', (float)$floor) : 'NULL') . ", " . ($capped ? 1 : 0) . ",
              {$stock_snap}, '{$now}', '{$expires}', 'active', " . (int)$auto_renew . ", '" . tep_db_input($motivo) . "',
              '" . tep_db_input($usuario) . "',
              NULL, 0,
              " . sprintf('%.4f', $ovp_orig0) . ", '" . tep_db_input($prefix_orig0) . "', '{$now}', '{$now}')");
        $detalle .= " Retail a " . number_format($pvp_oferta_net, 4) . " (antes " . number_format($pvp_var, 4) . ")";
    }

    // cgid=1 (Profesionales): precio explicito, o piping (igualar al Retail si mejora)
    $price_g1 = as_get_variant_g1_price($pa_id, $pid);
    $target_g1 = null;
    if ($pvp_g1_net !== null) {
        $pvp_g1_net = (float)$pvp_g1_net;
        if ($pvp_g1_net <= 0) return [false, $detalle . " · precio G1 invalido"];
        if ($price_g1 !== null && $pvp_g1_net >= $price_g1)
            return [($pvp_oferta_net !== null), $detalle . " · la oferta G1 (" . number_format($pvp_g1_net,4) . ") no mejora el precio G1 actual (" . number_format($price_g1,4) . ") — G1 sin cambios"];
        $target_g1 = $pvp_g1_net;
    } elseif ($also_g1 && $pvp_oferta_net !== null && $price_g1 !== null && $pvp_oferta_net < $price_g1) {
        $target_g1 = $pvp_oferta_net;
    }

    if ($target_g1 !== null && $price_g1 !== null) {
        {
            // Base del delta G1 = precio de grupo COMPLETO (products_groups). El specials
            // de producto G1 (si existe) actúa como ratio en el frontend, no como base.
            $qg = tep_db_query("SELECT customers_group_price FROM products_groups WHERE products_id={$pid} AND customers_group_id=1 LIMIT 1");
            $base_g1 = ($r1 = tep_db_fetch_array($qg)) ? (float)$r1['customers_group_price'] : $base;
            $ratio1 = as_product_offer_ratio($pid, 1);
            [$new_ovp_g1, $new_prefix_g1] = as_variant_delta_for_target(round($target_g1 / $ratio1, 4), $base_g1);
            $qg = tep_db_query("SELECT options_values_price, price_prefix FROM products_attributes_groups
                                WHERE products_attributes_id={$pa_id} AND customers_group_id=1 LIMIT 1");
            $r1 = tep_db_fetch_array($qg);
            $ovp_orig1 = $r1 ? (float)$r1['options_values_price'] : null;
            $prefix_orig1 = $r1 ? (string)$r1['price_prefix'] : null;
            if ($r1) {
                tep_db_query("UPDATE products_attributes_groups SET
                    options_values_price=" . sprintf('%.4f', $new_ovp_g1) . ", price_prefix='{$new_prefix_g1}'
                    WHERE products_attributes_id={$pa_id} AND customers_group_id=1");
            } else {
                tep_db_query("INSERT INTO products_attributes_groups
                    (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix)
                    VALUES ({$pa_id}, 1, " . sprintf('%.4f', $new_ovp_g1) . ", '{$new_prefix_g1}', {$pid}, 0, '')");
            }
            tep_db_query("UPDATE auto_specials_active SET estado='closed_replaced', updated_at='{$now}'
                          WHERE products_id={$pid} AND options_values_id={$ovid} AND customers_group_id=1 AND estado='active'");
            tep_db_query("INSERT INTO auto_specials_active
                (products_id, options_values_id, customers_group_id, rule_id, specials_id,
                 pvp_original, pvp_oferta, pct_aplicado, min_floor, capped_to_floor,
                 stock_snapshot, fecha_inicio, fecha_fin, estado, auto_renew, motivo, usuario,
                 prev_specials_price, prev_specials_existed,
                 ovp_orig, prefix_orig, created_at, updated_at)
                VALUES ({$pid}, {$ovid}, 1, " . ($rule_id !== null ? (int)$rule_id : 'NULL') . ", NULL,
                  " . sprintf('%.4f', $price_g1) . ", " . sprintf('%.4f', $target_g1) . ", " . (($price_g1 > 0) ? round((1 - $target_g1 / $price_g1) * 100, 2) : 0) . ", " . ($floor !== null ? sprintf('%.4f', (float)$floor) : 'NULL') . ", " . ($capped ? 1 : 0) . ",
                  {$stock_snap}, '{$now}', '{$expires}', 'active', " . (int)$auto_renew . ", '" . tep_db_input($motivo . ($pvp_g1_net !== null ? ' · G1 explicito' : ' · G1 piping')) . "',
                  '" . tep_db_input($usuario) . "',
                  NULL, 0,
                  " . ($ovp_orig1 !== null ? sprintf('%.4f', $ovp_orig1) : 'NULL') . ",
                  " . ($prefix_orig1 !== null ? "'" . tep_db_input($prefix_orig1) . "'" : 'NULL') . ", '{$now}', '{$now}')");
            $detalle .= " · G1 a " . number_format($target_g1, 4) . " (antes " . number_format($price_g1, 4) . ")";
        }
    }

    return [true, $detalle];
}

/**
 * Revierte manualmente las ofertas activas de una variante (cgid 0 y 1)
 * restaurando el delta original desde el snapshot. Marca estado='closed_manual'.
 */
function as_revert_variant_offer(int $pid, int $ovid, string $usuario): array {
    $now = date('Y-m-d H:i:s');
    $q = tep_db_query("SELECT * FROM auto_specials_active
                       WHERE products_id={$pid} AND options_values_id={$ovid} AND estado='active'");
    $n = 0;
    while ($a = tep_db_fetch_array($q)) {
        $cgid = (int)$a['customers_group_id'];
        $paq = tep_db_query("SELECT products_attributes_id FROM products_attributes
                             WHERE products_id={$pid} AND options_values_id={$ovid} LIMIT 1");
        $par = tep_db_fetch_array($paq);
        if ($par && $a['ovp_orig'] !== null && $a['prefix_orig'] !== null) {
            $pa_id = (int)$par['products_attributes_id'];
            $ovp_s = sprintf('%.4f', (float)$a['ovp_orig']);
            $pfx_s = tep_db_input($a['prefix_orig']);
            if ($cgid === 0) {
                tep_db_query("UPDATE products_attributes SET options_values_price={$ovp_s}, price_prefix='{$pfx_s}', attributes_last_modified='{$now}' WHERE products_attributes_id={$pa_id}");
            } else {
                tep_db_query("UPDATE products_attributes_groups SET options_values_price={$ovp_s}, price_prefix='{$pfx_s}' WHERE products_attributes_id={$pa_id} AND customers_group_id={$cgid}");
            }
        }
        tep_db_query("UPDATE auto_specials_active SET estado='closed_manual', motivo=CONCAT(COALESCE(motivo,''), ' · revertida por " . tep_db_input($usuario) . "'), updated_at='{$now}' WHERE id=" . (int)$a['id']);
        $n++;
    }
    return [$n > 0, $n > 0 ? "Revertidas {$n} ofertas de la variante" : "La variante no tiene ofertas activas"];
}

/**
 * Calcula el delta (options_values_price + price_prefix) que debe tener una
 * variante para que su PVP final sea `target_price`, dado el precio base padre.
 *
 *   target_price = base_price ± delta
 *   delta = target_price - base_price
 *   si delta >= 0: prefix '+', ovp = delta
 *   si delta < 0:  prefix '-', ovp = abs(delta)
 *
 * Devuelve [ovp, prefix] redondeado a 4 decimales.
 */
function as_variant_delta_for_target(float $target_price, float $base_price): array {
    $d = round($target_price - $base_price, 4);
    if ($d >= 0) return [$d, '+'];
    return [round(abs($d), 4), '-'];
}

/**
 * Ratio de oferta de PRODUCTO para (pid, cgid): specials_activo / precio_base.
 * 1.0 si no hay specials. El frontend escala el precio de variante con este
 * ratio: precio_web = (base ± delta) × ratio (fix "doble descuento" 2026-06-17,
 * ver memoria precio-oferta-variante).
 */
function as_product_offer_ratio(int $pid, int $cgid): float {
    if ($cgid === 0) {
        $q = tep_db_query("SELECT products_price AS base FROM products WHERE products_id={$pid} LIMIT 1");
    } else {
        $q = tep_db_query("SELECT customers_group_price AS base FROM products_groups
                           WHERE products_id={$pid} AND customers_group_id={$cgid} LIMIT 1");
    }
    $row = tep_db_fetch_array($q);
    $base = $row ? (float)$row['base'] : 0.0;
    if ($base <= 0) return 1.0;
    $q = tep_db_query("SELECT specials_new_products_price FROM specials
                       WHERE products_id={$pid} AND customers_group_id={$cgid} AND status=1 LIMIT 1");
    $sp = tep_db_fetch_array($q);
    if (!$sp || $sp['specials_new_products_price'] === null) return 1.0;
    $r = (float)$sp['specials_new_products_price'] / $base;
    return ($r > 0 && $r < 1) ? $r : 1.0;
}

/**
 * Precio WEB actual de la variante para cgid=0 (lo que ve el cliente):
 *   (products_price ± delta) × ratio_specials
 */
function as_get_variant_g0_price(int $pa_id): ?float {
    $q = tep_db_query("
        SELECT pa.products_id, pa.options_values_price, pa.price_prefix, p.products_price
        FROM products_attributes pa
        JOIN products p ON p.products_id = pa.products_id
        WHERE pa.products_attributes_id = {$pa_id} LIMIT 1");
    $row = tep_db_fetch_array($q);
    if (!$row) return null;
    $sign = ($row['price_prefix'] === '-') ? -1.0 : 1.0;
    $full = (float)$row['products_price'] + $sign * (float)$row['options_values_price'];
    return round($full * as_product_offer_ratio((int)$row['products_id'], 0), 4);
}

/**
 * Precio WEB actual de la variante para cgid=1 (Profesionales):
 *   (base_grupo ± delta_g1) × ratio_specials_g1
 * base_grupo = products_groups (pid,1); delta con fallback al de cgid=0.
 */
function as_get_variant_g1_price(int $pa_id, int $pid): ?float {
    $q = tep_db_query("SELECT customers_group_price FROM products_groups
                       WHERE products_id={$pid} AND customers_group_id=1 LIMIT 1");
    if ($row = tep_db_fetch_array($q)) {
        $base = (float)$row['customers_group_price'];
    } else {
        $q = tep_db_query("SELECT products_price FROM products WHERE products_id={$pid} LIMIT 1");
        $row = tep_db_fetch_array($q);
        if (!$row) return null;
        $base = (float)$row['products_price'];
    }
    // delta G1 (con fallback a delta G0 si no hay fila para cgid=1)
    $q = tep_db_query("SELECT options_values_price, price_prefix
                       FROM products_attributes_groups
                       WHERE products_attributes_id={$pa_id} AND customers_group_id=1 LIMIT 1");
    if ($row = tep_db_fetch_array($q)) {
        $ovp = (float)$row['options_values_price'];
        $prefix = $row['price_prefix'];
    } else {
        $q = tep_db_query("SELECT options_values_price, price_prefix
                           FROM products_attributes
                           WHERE products_attributes_id={$pa_id} LIMIT 1");
        $row = tep_db_fetch_array($q);
        if (!$row) return null;
        $ovp = (float)$row['options_values_price'];
        $prefix = $row['price_prefix'];
    }
    $sign = ($prefix === '-') ? -1.0 : 1.0;
    return round(($base + $sign * $ovp) * as_product_offer_ratio($pid, 1), 4);
}
