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
 * Precio final actual de la variante para cgid=0 (público):
 *   base = (specials cgid=0 si activo) || products_price
 *   delta = products_attributes (options_values_price, price_prefix)
 */
function as_get_variant_g0_price(int $pa_id): ?float {
    $q = tep_db_query("
        SELECT pa.products_id, pa.options_values_price, pa.price_prefix,
               p.products_price,
               (SELECT specials_new_products_price FROM specials s
                WHERE s.products_id = pa.products_id AND s.customers_group_id=0 AND s.status=1
                LIMIT 1) AS sp0
        FROM products_attributes pa
        JOIN products p ON p.products_id = pa.products_id
        WHERE pa.products_attributes_id = {$pa_id} LIMIT 1");
    $row = tep_db_fetch_array($q);
    if (!$row) return null;
    $base = $row['sp0'] !== null ? (float)$row['sp0'] : (float)$row['products_price'];
    $sign = ($row['price_prefix'] === '-') ? -1.0 : 1.0;
    return round($base + $sign * (float)$row['options_values_price'], 4);
}

/**
 * Precio final actual de la variante para cgid=1 (Profesionales):
 *   base = (specials cgid=1) || products_groups (pid, 1) || products_price
 *   delta = products_attributes_groups (pa_id, 1) || products_attributes (fallback)
 */
function as_get_variant_g1_price(int $pa_id, int $pid): ?float {
    $q = tep_db_query("SELECT specials_new_products_price FROM specials
                       WHERE products_id={$pid} AND customers_group_id=1 AND status=1 LIMIT 1");
    if ($row = tep_db_fetch_array($q)) {
        $base = (float)$row['specials_new_products_price'];
    } else {
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
    return round($base + $sign * $ovp, 4);
}
