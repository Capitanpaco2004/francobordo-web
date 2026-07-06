<?php
// attr_specials.php — Ofertas por variante (propiedad) de un producto.
// Aplica/revierte descuentos a variantes concretas via delta (products_attributes),
// reutilizando el motor auto_specials (snapshot reversible + cron de cierre).
require 'includes/application_top.php';
require __DIR__ . '/auto_specials_helpers.php';

$pID = (int)($_GET['pID'] ?? ($_POST['pID'] ?? 0));
$cPathSafe = preg_replace('/[^0-9_]/', '', (string)($_GET['cPath'] ?? ($_POST['cPath'] ?? '')));
$user = (string)($_SESSION['login']['user_login'] ?? 'admin');
$msg = ''; $err = '';

$TAX_BY_CLASS = [0 => 0.0, 1 => 21.0, 2 => 4.0, 3 => 10.0];

// ---- Producto ----------------------------------------------------------------
$prod = null;
if ($pID > 0) {
    $q = tep_db_query("SELECT p.products_id, p.products_price, p.products_cost, p.products_tax_class_id,
                              p.products_status, pd.products_name
                       FROM products p
                       LEFT JOIN products_description pd ON pd.products_id=p.products_id AND pd.language_id=3
                       WHERE p.products_id={$pID} LIMIT 1");
    $prod = tep_db_fetch_array($q) ?: null;
}

// ---- Acciones ------------------------------------------------------------------
if ($prod && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $ovid = (int)($_POST['ovid'] ?? 0);
    $tax_rate = $TAX_BY_CLASS[(int)$prod['products_tax_class_id']] ?? 21.0;
    $iva_mult = 1 + $tax_rate / 100;

    if ($accion === 'aplicar' && $ovid > 0) {
        $dias = max(1, (int)($_POST['dias'] ?? 14));
        $also_g1 = isset($_POST['g1']) ? true : false;

        // Normaliza "49,95" / "1.234,56" / "49.95" / "15%" → [valor_float, es_pct] o null si vacío
        $parse = function ($raw) {
            $raw = trim((string)$raw);
            if ($raw === '') return null;
            $es_pct = (substr($raw, -1) === '%');
            if ($es_pct) $raw = substr($raw, 0, -1);
            if (strpos($raw, ',') !== false) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            }
            return [(float)$raw, $es_pct];
        };
        $in_retail = $parse($_POST['precio'] ?? '');
        $in_g1     = $parse($_POST['precio_g1'] ?? '');

        if ($in_retail === null && $in_g1 === null) {
            $err = 'Indica un precio (c/IVA) o % en Retail, en G1 o en ambos.';
        } else {
            $vq = tep_db_query("SELECT pa.products_attributes_id, pa.options_values_price, pa.price_prefix
                                FROM products_attributes pa
                                WHERE pa.products_id={$pID} AND pa.options_values_id={$ovid} LIMIT 1");
            $vr = tep_db_fetch_array($vq);
            if (!$vr) {
                $err = 'Variante no encontrada.';
            } else {
                $sign = ($vr['price_prefix'] === '-') ? -1.0 : 1.0;
                $pvp_var_net = (float)$prod['products_price'] + $sign * (float)$vr['options_values_price'];

                // Retail: % sobre su propio precio actual; importe → neto desde c/IVA
                $target_net = null;
                if ($in_retail !== null) {
                    [$v, $es_pct] = $in_retail;
                    if ($v <= 0) { $err = 'Precio/% Retail inválido.'; }
                    else $target_net = $es_pct ? as_round_nickel($pvp_var_net * (1 - $v / 100))
                                               : round($v / $iva_mult, 4);
                }
                // G1: % sobre el precio G1 actual de la variante; importe → neto desde c/IVA
                $target_g1_net = null;
                if ($err === '' && $in_g1 !== null) {
                    [$v, $es_pct] = $in_g1;
                    if ($v <= 0) { $err = 'Precio/% G1 inválido.'; }
                    else {
                        $price_g1_cur = as_get_variant_g1_price((int)$vr['products_attributes_id'], $pID);
                        if ($price_g1_cur === null) { $err = 'Este producto no tiene precio G1 (products_groups).'; }
                        else $target_g1_net = $es_pct ? as_round_nickel($price_g1_cur * (1 - $v / 100))
                                                      : round($v / $iva_mult, 4);
                    }
                }

                if ($err === '') {
                    $renovar = isset($_POST['renovar']) ? 1 : 0;
                    [$ok, $detalle] = as_apply_variant_offer(
                        $pID, $ovid, $target_net, null, false,
                        $dias, null, 'Oferta manual por variante', $user,
                        $renovar /* 1 = el cron renueva cada 14d mientras haya stock */,
                        ($in_g1 === null ? $also_g1 : false), // piping solo si no hay G1 explícito
                        $target_g1_net);
                    if ($ok) $msg = $detalle; else $err = $detalle;
                }
            }
        }
    } elseif ($accion === 'revertir' && $ovid > 0) {
        [$ok, $detalle] = as_revert_variant_offer($pID, $ovid, $user);
        if ($ok) $msg = $detalle; else $err = $detalle;
    }
}

// ---- Variantes + estado ---------------------------------------------------------
$variantes = [];
if ($prod) {
    $q = tep_db_query("
        SELECT pa.options_values_id, pa.options_id, pa.options_values_price, pa.price_prefix,
               pov.products_options_values_name AS nombre, pov.CCODIVAL,
               (SELECT ps.products_stock_quantity FROM products_stock ps
                WHERE ps.products_id=pa.products_id
                  AND ps.products_stock_attributes=CONCAT(pa.options_id,'-',pa.options_values_id)
                LIMIT 1) AS stock_raw,
               (SELECT pag.options_values_price FROM products_attributes_groups pag
                WHERE pag.products_attributes_id=pa.products_attributes_id AND pag.customers_group_id=1
                LIMIT 1) AS ovp_g1,
               (SELECT pag.price_prefix FROM products_attributes_groups pag
                WHERE pag.products_attributes_id=pa.products_attributes_id AND pag.customers_group_id=1
                LIMIT 1) AS pref_g1
        FROM products_attributes pa
        LEFT JOIN products_options_values pov
          ON pov.products_options_values_id=pa.options_values_id AND pov.language_id=3
        WHERE pa.products_id={$pID}
        ORDER BY pov.products_options_values_name");
    while ($r = tep_db_fetch_array($q)) $variantes[] = $r;

    // Ofertas activas por variante (para badge + revertir)
    $ofertas_act = [];
    $aq = tep_db_query("SELECT options_values_id, customers_group_id, pvp_original, pvp_oferta,
                               fecha_fin, auto_renew, rule_id
                        FROM auto_specials_active
                        WHERE products_id={$pID} AND options_values_id>0 AND estado='active'");
    while ($ar = tep_db_fetch_array($aq)) $ofertas_act[(int)$ar['options_values_id']][] = $ar;
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
  .avs-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; }
  .avs-table { font-size: 13px; border-collapse: collapse; width: 100%; background: #fff; }
  .avs-table th { background:#f8fafc; padding:8px; text-align:left; border-bottom:2px solid #cbd5e1; font-size:12px; }
  .avs-table td { padding:8px; border-bottom:1px solid #f1f5f9; vertical-align: middle; }
  .avs-num { font-variant-numeric: tabular-nums; text-align: right; }
  .avs-badge { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; }
  .avs-badge.manual { background:#fed7aa; color:#7c2d12; }
  .avs-msg { background:#dcfce7; color:#166534; padding:8px 12px; border-radius:6px; margin-bottom:12px; }
  .avs-err { background:#fee2e2; color:#991b1b; padding:8px 12px; border-radius:6px; margin-bottom:12px; }
  .avs-inp { border:1px solid #ccc; padding:3px 6px; width:90px; text-align:right; }
  .avs-inp-d { border:1px solid #ccc; padding:3px 6px; width:52px; text-align:right; }
  .avs-help { color:#64748b; font-size:11px; }
  .avs-margin-ok { color:#166534; font-weight:600; } .avs-margin-low { color:#a16207; font-weight:600; } .avs-margin-neg { background:#fee2e2; color:#991b1b; padding:1px 6px; border-radius:4px; font-weight:700; }
</style>

<div class="avs-wrap">
<?php if (!$prod): ?>
  <h3>Ofertas por variante</h3>
  <p>Indica un producto: <code>attr_specials.php?pID=XXXX</code> — o entra desde la ficha del producto (box "¿Producto en Oferta?").</p>
<?php else:
    $tax_rate = $TAX_BY_CLASS[(int)$prod['products_tax_class_id']] ?? 21.0;
    $iva_mult = 1 + $tax_rate / 100;
    $cost = (float)$prod['products_cost'];
?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <h3 style="margin:0">Ofertas por variante — #<?=$pID?> <?=htmlspecialchars($prod['products_name'] ?? '')?></h3>
    <a class="btn btn-sm btn-outline-secondary" href="categories.php?cPath=<?=htmlspecialchars($cPathSafe)?>&pID=<?=$pID?>&action=new_product">← Volver a la ficha</a>
  </div>
  <p class="avs-help">
    PVP base (c/IVA): <b><?=number_format((float)$prod['products_price'] * $iva_mult, 2, ',', '.')?> €</b>
    · Coste (s/IVA): <b><?=number_format($cost, 2, ',', '.')?> €</b>
    · IVA <?=number_format($tax_rate,0)?>%.
    La oferta baja el precio de la variante (delta). El cron horario la revierte al llegar la fecha fin o al agotarse el stock.
    <?php if ((int)$prod['products_status'] !== 1): ?><b style="color:#c00">OJO: producto no activo (status=<?=(int)$prod['products_status']?>).</b><?php endif; ?>
  </p>

  <?php if ($msg): ?><div class="avs-msg"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="avs-err"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <?php if (empty($variantes)): ?>
    <p>Este producto no tiene variantes. Usa la oferta normal (specials) desde la ficha.</p>
  <?php else: ?>
  <table class="avs-table">
    <thead>
      <tr>
        <th>Variante</th>
        <th class="avs-num">Stock</th>
        <th class="avs-num">PVP actual (c/IVA)</th>
        <th class="avs-num">PVP G1 (c/IVA)</th>
        <th class="avs-num">Margen actual</th>
        <th>Oferta activa</th>
        <th>Nueva oferta</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($variantes as $v):
        $ovid = (int)$v['options_values_id'];
        $sign = ($v['price_prefix'] === '-') ? -1.0 : 1.0;
        $pvp_net = (float)$prod['products_price'] + $sign * (float)$v['options_values_price'];
        $pvp_iva = $pvp_net * $iva_mult;
        $margen = $pvp_net > 0 ? round(($pvp_net - $cost) / $pvp_net * 100, 1) : null;
        $mcls = $margen === null ? '' : ($margen < 0 ? 'avs-margin-neg' : ($margen < 10 ? 'avs-margin-low' : 'avs-margin-ok'));
        $stock = (int)($v['stock_raw'] ?? 0); if ($stock < 0 || $stock == 2000) $stock = 0;
        // G1: base G1 (products_groups o specials g1) + delta g1 si existe
        $g1_price_iva = null;
        $gq = tep_db_query("SELECT customers_group_price FROM products_groups WHERE products_id={$pID} AND customers_group_id=1 LIMIT 1");
        if ($gr = tep_db_fetch_array($gq)) {
            $g1_base = (float)$gr['customers_group_price'];
            if ($v['ovp_g1'] !== null) {
                $sg = ($v['pref_g1'] === '-') ? -1.0 : 1.0;
                $g1_price_iva = ($g1_base + $sg * (float)$v['ovp_g1']) * $iva_mult;
            } else {
                $g1_price_iva = ($g1_base + $sign * (float)$v['options_values_price']) * $iva_mult;
            }
        }
        $activas = $ofertas_act[$ovid] ?? [];
    ?>
      <tr>
        <td><b><?=htmlspecialchars($v['nombre'] ?? $v['CCODIVAL'] ?? ('ovid ' . $ovid))?></b>
            <span class="avs-help">(<?=htmlspecialchars($v['CCODIVAL'] ?? '')?>)</span></td>
        <td class="avs-num"><?=$stock?></td>
        <td class="avs-num"><b><?=number_format($pvp_iva, 2, ',', '.')?> €</b></td>
        <td class="avs-num"><?=$g1_price_iva !== null ? number_format($g1_price_iva, 2, ',', '.') . ' €' : '—'?></td>
        <td class="avs-num"><?php if ($margen !== null): ?><span class="<?=$mcls?>"><?=number_format($margen,1,',','.')?>%</span><?php else: ?>—<?php endif; ?></td>
        <td>
          <?php if (empty($activas)): ?>—<?php else: foreach ($activas as $a): ?>
            <span class="avs-badge <?=((int)$a['auto_renew']===0)?'manual':''?>"
                  title="cgid=<?=(int)$a['customers_group_id']?> · antes <?=number_format((float)$a['pvp_original']*$iva_mult,2,',','.')?> €">
              <?=((int)$a['customers_group_id']===0?'Retail':'G1')?>: <?=number_format((float)$a['pvp_oferta']*$iva_mult,2,',','.')?> €
              hasta <?=date('d/m/y', strtotime($a['fecha_fin']))?><?=((int)$a['auto_renew']===1)?' ⟳':''?>
            </span><br>
          <?php endforeach; endif; ?>
          <?php if (!empty($activas)): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="pID" value="<?=$pID?>"><input type="hidden" name="cPath" value="<?=htmlspecialchars($cPathSafe)?>">
              <input type="hidden" name="accion" value="revertir"><input type="hidden" name="ovid" value="<?=$ovid?>">
              <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Revertir la oferta de esta variante al precio original?')">Revertir</button>
            </form>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <form method="post" style="display:inline">
            <input type="hidden" name="pID" value="<?=$pID?>"><input type="hidden" name="cPath" value="<?=htmlspecialchars($cPathSafe)?>">
            <input type="hidden" name="accion" value="aplicar"><input type="hidden" name="ovid" value="<?=$ovid?>">
            <span style="font-size:11px;color:#64748b">Retail</span>
            <input type="text" name="precio" class="avs-inp" placeholder="€ c/IVA o %"
                   title="Precio Retail final con IVA (ej. 49,95) o porcentaje (ej. 15%). Vacío = no tocar Retail.">
            <span style="font-size:11px;color:#64748b">G1</span>
            <input type="text" name="precio_g1" class="avs-inp" placeholder="€ c/IVA o %"
                   title="Precio Profesionales final con IVA o %. Vacío = usar el checkbox de igualar.">
            <input type="number" name="dias" class="avs-inp-d" value="14" min="1" max="365" title="Días de vigencia"> d
            <label style="font-size:11px" title="Solo si G1 está vacío: iguala el precio de Profesionales al Retail cuando la oferta quede por debajo">
              <input type="checkbox" name="g1" checked> =G1
            </label>
            <label style="font-size:11px" title="Repetir oferta: al acercarse la fecha fin, el cron la renueva 14 días más mientras la variante tenga stock (igual que 'Repetir oferta' en specials.php)">
              <input type="checkbox" name="renovar"> ⟳14d
            </label>
            <button type="submit" class="btn btn-sm btn-success"
                    onclick="return confirm('¿Aplicar la oferta a esta variante?')">Aplicar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="avs-help" style="margin-top:10px">
    · Los precios se teclean <b>con IVA</b> (como los ve el cliente) o como <b>%</b> con el símbolo (ej. <code>15%</code>). El % de Retail se aplica sobre el PVP Retail; el % de G1 sobre el precio G1 actual.
    · Puedes rellenar <b>solo Retail</b>, <b>solo G1</b> o <b>ambos</b> con precios distintos. Si G1 va vacío y "=G1" está marcado, Profesionales se iguala al Retail cuando la oferta quede por debajo de su precio.
    · Sin <b>⟳14d</b>, la oferta termina en su fecha fin (o al agotarse el stock) y el cron restaura el precio original. Con <b>⟳14d</b>, se renueva sola cada 14 días mientras haya stock — como "Repetir oferta" en las ofertas de producto.
    · <b>⟳</b> en el badge = oferta renovable (de regla o manual con repetición).
    · Limitación: la web no muestra el precio tachado en variantes; el precio simplemente baja.
  </p>
  <?php endif; ?>
<?php endif; ?>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
