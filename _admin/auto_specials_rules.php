<?php
// Fase 2 — CRUD de reglas auto_specials_tier_rules
require 'includes/application_top.php';
require __DIR__ . '/auto_specials_helpers.php';

$msg = '';
$err = '';

// ---- Acciones --------------------------------------------------------------
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $mfg = (int)($_POST['manufacturers_id'] ?? 0);
    $prio = (int)($_POST['prioridad'] ?? 100);
    $dvm = (int)($_POST['dias_sin_venta_min'] ?? 0);
    $dscm_raw = trim($_POST['dias_sin_compra_min'] ?? '');
    $dscm = ($dscm_raw === '' ? 'NULL' : (string)(int)$dscm_raw);
    $dcm_raw = trim($_POST['dias_cobertura_min'] ?? '');
    $dcm = ($dcm_raw === '' ? 'NULL' : (string)(int)$dcm_raw);
    $pct = (float)str_replace(',', '.', $_POST['descuento_pct'] ?? '0');
    $vig = (int)($_POST['vigencia_dias'] ?? 60);
    $mmp_raw = trim($_POST['min_margin_pct'] ?? '');
    $mmp = ($mmp_raw === '' ? 'NULL' : sprintf('%.2f', (float)str_replace(',', '.', $mmp_raw)));
    $act = isset($_POST['activo']) ? 1 : 0;
    $nota = tep_db_input(substr(trim($_POST['nota'] ?? ''), 0, 120));
    $now = date('Y-m-d H:i:s');
    if ($pct < 0 || $pct > 100) {
        $err = 'Descuento % debe estar entre 0 y 100';
    } else {
        $pct_s = sprintf('%.2f', $pct);
        if ($id > 0) {
            tep_db_query("UPDATE auto_specials_tier_rules SET
                manufacturers_id={$mfg}, prioridad={$prio},
                dias_sin_venta_min={$dvm}, dias_sin_compra_min={$dscm},
                dias_cobertura_min={$dcm},
                descuento_pct={$pct_s}, vigencia_dias={$vig}, min_margin_pct={$mmp},
                activo={$act}, nota='{$nota}', modified_at='{$now}'
                WHERE id={$id}");
            $msg = "Regla #{$id} actualizada.";
        } else {
            tep_db_query("INSERT INTO auto_specials_tier_rules
              (manufacturers_id, prioridad, dias_sin_venta_min, dias_sin_compra_min, dias_cobertura_min,
               descuento_pct, vigencia_dias, min_margin_pct, activo, nota, created_at, modified_at)
              VALUES ({$mfg},{$prio},{$dvm},{$dscm},{$dcm},{$pct_s},{$vig},{$mmp},{$act},'{$nota}','{$now}','{$now}')");
            $msg = 'Regla creada con id=' . tep_db_insert_id();
        }
    }
} elseif ($action === 'toggle') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        tep_db_query("UPDATE auto_specials_tier_rules SET activo = 1 - activo,
            modified_at = NOW() WHERE id = {$id}");
        $msg = "Regla #{$id}: toggled.";
    }
} elseif ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        tep_db_query("DELETE FROM auto_specials_tier_rules WHERE id = {$id}");
        $msg = "Regla #{$id} eliminada.";
    }
}

// ---- Cargar reglas + categorías raíz ---------------------------------------
$rules_q = tep_db_query("SELECT * FROM auto_specials_tier_rules
                          ORDER BY activo DESC, manufacturers_id ASC, prioridad ASC, descuento_pct ASC");

// Filtro opcional "Compra ≤ fecha" para acotar el conteo
$f_buy_before = trim((string)($_GET['buy_before'] ?? ''));
$f_buy_before = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_buy_before) ? $f_buy_before : '';

// Pre-compute conteo de variantes ganadas por cada regla (solo reglas activas).
// Una variante "pertenece" a la regla que sea la ganadora de `as_pick_rule()`.
$active_rules = [];
$_aq = tep_db_query("SELECT * FROM auto_specials_tier_rules WHERE activo=1
                     ORDER BY descuento_pct DESC, prioridad ASC");
while ($_ar = tep_db_fetch_array($_aq)) $active_rules[] = $_ar;

$rule_counts = [];   // rule_id => num variantes ganadas
$rule_pcounts = [];  // rule_id => num productos distintos ganados
$_seen_pids_per_rule = [];
$buy_clause = '';
if ($f_buy_before !== '') {
    $bb = tep_db_input($f_buy_before);
    $buy_clause = " AND (v.ultima_compra IS NULL OR v.ultima_compra <= '{$bb}')";
}
$_vq = tep_db_query("
    SELECT v.products_id, v.options_values_id, v.dias_sin_venta, v.dias_sin_compra, v.dias_cobertura,
           v.stock_variant, p.manufacturers_id
    FROM qfac_sales_velocity v
    JOIN products p ON p.products_id = v.products_id
    WHERE p.products_status = 1 AND v.stock_variant > 0 {$buy_clause}
");
while ($_vr = tep_db_fetch_array($_vq)) {
    $_picked = as_pick_rule(
        $_vr['dias_sin_venta'], $_vr['dias_cobertura'] ?? null,
        (int)$_vr['manufacturers_id'], $active_rules,
        $_vr['dias_sin_compra'] ?? null);
    if (!$_picked) continue;
    $rid = (int)$_picked['id'];
    $rule_counts[$rid] = ($rule_counts[$rid] ?? 0) + 1;
    $pid = (int)$_vr['products_id'];
    if (!isset($_seen_pids_per_rule[$rid])) $_seen_pids_per_rule[$rid] = [];
    if (!isset($_seen_pids_per_rule[$rid][$pid])) {
        $_seen_pids_per_rule[$rid][$pid] = 1;
        $rule_pcounts[$rid] = ($rule_pcounts[$rid] ?? 0) + 1;
    }
}

$mfgs_q = tep_db_query("
    SELECT manufacturers_id, manufacturers_name
    FROM manufacturers
    ORDER BY manufacturers_name
");
$mfgs = [];
while ($m = tep_db_fetch_array($mfgs_q)) $mfgs[(int)$m['manufacturers_id']] = $m['manufacturers_name'];

// Para editar pre-cargado: si ?edit=N
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_row = null;
if ($edit_id > 0) {
    $eq = tep_db_query("SELECT * FROM auto_specials_tier_rules WHERE id={$edit_id} LIMIT 1");
    $edit_row = tep_db_fetch_array($eq) ?: null;
}
?>
<?php require THEME . 'html/header.php'; ?>

<style>
  .asr-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; }
  .asr-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:20px; }
  .asr-table { font-size: 13px; border-collapse: separate; border-spacing: 0; width: 100%; }
  .asr-table th { background:#f8fafc; padding:8px; text-align:left; border-bottom:2px solid #cbd5e1; font-size:12px; color:#334155; }
  .asr-table td { padding:8px; border-bottom:1px solid #f1f5f9; vertical-align: middle; }
  .asr-table tr.inactive { opacity: 0.5; }
  .asr-pct { font-weight: 700; color:#166534; }
  .asr-badge-global { background:#e0f2fe; color:#075985; padding:2px 8px; border-radius:8px; font-size:11px; font-weight:600; }
  .asr-badge-cat { background:#ede9fe; color:#5b21b6; padding:2px 8px; border-radius:8px; font-size:11px; font-weight:600; }
  .asr-actions a { margin-right: 8px; }
  .asr-msg { background:#dcfce7; color:#166534; padding:8px 12px; border-radius:6px; margin-bottom:12px; }
  .asr-err { background:#fee2e2; color:#991b1b; padding:8px 12px; border-radius:6px; margin-bottom:12px; }
  .asr-form .form-control, .asr-form .form-select { font-size:13px; }
  .asr-help { color:#64748b; font-size:11px; }
</style>

<div class="asr-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Reglas de descuento automático
      <small class="text-muted" style="font-size:13px">Fase 2 · CRUD</small></h3>
    <a href="auto_specials_preview.php" class="btn btn-sm btn-outline-primary">→ Ver candidatos</a>
  </div>

  <form method="get" class="row g-2 mb-3" style="align-items:end;background:#fff;border:1px solid #e5e7eb;padding:10px;border-radius:8px">
    <div class="col-md-3">
      <label class="form-label" style="font-size:12px">Acotar conteo por última compra ≤</label>
      <input type="date" name="buy_before" class="form-control form-control-sm"
             value="<?=htmlspecialchars($f_buy_before)?>">
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary btn-sm">Aplicar al conteo</button>
      <?php if ($f_buy_before): ?>
        <a href="auto_specials_rules.php" class="btn btn-sm btn-outline-secondary">×</a>
      <?php endif; ?>
    </div>
    <div class="col-md-7 asr-help" style="align-self:center">
      Sirve para ver cuántos productos afectaría cada regla si limitas a los repuestos antes de una fecha.
      No modifica la regla; solo filtra el conteo y el link "Ver candidatos".
    </div>
  </form>

  <?php if ($msg): ?><div class="asr-msg"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if ($err): ?><div class="asr-err"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <!-- Formulario crear/editar -->
  <div class="asr-card">
    <h5><?=$edit_row?"Editar regla #{$edit_id}":"Nueva regla"?></h5>
    <form method="post" class="asr-form row g-2">
      <input type="hidden" name="action" value="save">
      <?php if ($edit_row): ?><input type="hidden" name="id" value="<?=$edit_id?>"><?php endif; ?>

      <div class="col-md-3">
        <label class="form-label">Fabricante</label>
        <select name="manufacturers_id" class="form-select">
          <option value="0" <?=$edit_row && (int)$edit_row['manufacturers_id']===0 ? 'selected' : ''?>>— Global (fallback) —</option>
          <?php foreach ($mfgs as $mid => $mname): ?>
            <option value="<?=$mid?>" <?=$edit_row && (int)$edit_row['manufacturers_id']===$mid ? 'selected':''?>>
              <?=htmlspecialchars($mname)?> (#<?=$mid?>)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="asr-help">Si un fabricante tiene regla, aplica esa; si no, la global.</div>
      </div>

      <div class="col-md-1">
        <label class="form-label">Prioridad</label>
        <input type="number" name="prioridad" class="form-control"
               value="<?=$edit_row?(int)$edit_row['prioridad']:100?>">
        <div class="asr-help">menor = antes</div>
      </div>

      <div class="col-md-2">
        <label class="form-label">Días sin venta ≥</label>
        <input type="number" name="dias_sin_venta_min" class="form-control" min="0"
               value="<?=$edit_row?(int)$edit_row['dias_sin_venta_min']:0?>">
      </div>

      <div class="col-md-2">
        <label class="form-label">Días sin compra ≥</label>
        <input type="number" name="dias_sin_compra_min" class="form-control" min="0"
               value="<?=$edit_row && $edit_row['dias_sin_compra_min']!==null ? (int)$edit_row['dias_sin_compra_min'] : ''?>"
               placeholder="(opcional)">
        <div class="asr-help">vacío = no exigir compra antigua</div>
      </div>

      <div class="col-md-2">
        <label class="form-label">Cobertura (días) ≥</label>
        <input type="number" name="dias_cobertura_min" class="form-control" min="0"
               value="<?=$edit_row && $edit_row['dias_cobertura_min']!==null ? (int)$edit_row['dias_cobertura_min'] : ''?>"
               placeholder="(opcional)">
        <div class="asr-help">vacío = no usar</div>
      </div>

      <div class="col-md-1">
        <label class="form-label">Descuento %</label>
        <input type="number" name="descuento_pct" class="form-control" min="0" max="100" step="0.5"
               value="<?=$edit_row?number_format((float)$edit_row['descuento_pct'],2,'.',''):'10.00'?>">
      </div>

      <div class="col-md-1">
        <label class="form-label">Vigencia (d)</label>
        <input type="number" name="vigencia_dias" class="form-control" min="1"
               value="<?=$edit_row?(int)$edit_row['vigencia_dias']:60?>">
      </div>

      <div class="col-md-1">
        <label class="form-label">Margen mín. %</label>
        <input type="number" name="min_margin_pct" class="form-control" min="0" max="100" step="0.5"
               value="<?=$edit_row && $edit_row['min_margin_pct']!==null ? number_format((float)$edit_row['min_margin_pct'],2,'.','') : '10.00'?>"
               placeholder="(vacío = sin piso)">
        <div class="asr-help">Sobre PVP. Vacío = sin piso. Floor = coste / (1 − m/100).</div>
      </div>

      <div class="col-md-2">
        <label class="form-label">Nota</label>
        <input type="text" name="nota" class="form-control" maxlength="120"
               value="<?=htmlspecialchars($edit_row['nota'] ?? '')?>">
      </div>

      <div class="col-12 d-flex align-items-center gap-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1"
                 <?=(!$edit_row || (int)$edit_row['activo']===1) ? 'checked' : ''?>>
          <label class="form-check-label" for="activo">Activa</label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><?=$edit_row?'Guardar cambios':'Crear regla'?></button>
        <?php if ($edit_row): ?>
          <a href="auto_specials_rules.php" class="btn btn-sm btn-outline-secondary">Cancelar</a>
        <?php endif; ?>
        <span class="asr-help ms-auto">Criterio: aplica si <strong>días sin venta ≥</strong> O <strong>cobertura ≥</strong> (cualquiera de los dos).</span>
      </div>
    </form>
  </div>

  <!-- Listado -->
  <div class="asr-card">
    <h5>Reglas existentes</h5>
    <table class="asr-table">
      <thead>
        <tr>
          <th>id</th>
          <th>Ámbito</th>
          <th class="text-end">prio</th>
          <th class="text-end">sin venta ≥</th>
          <th class="text-end">sin compra ≥</th>
          <th class="text-end">cob. ≥</th>
          <th class="text-end">% desc.</th>
          <th class="text-end">vigencia</th>
          <th class="text-end">margen mín.</th>
          <th class="text-end" title="Productos · variantes ganadas como regla activa (entre stock > 0)">aplica a</th>
          <th>nota</th>
          <th>act.</th>
          <th>actualizado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php while ($r = tep_db_fetch_array($rules_q)): ?>
        <?php
          $mfg = (int)$r['manufacturers_id'];
          $mfg_name = $mfg === 0
            ? '<span class="asr-badge-global">GLOBAL</span>'
            : '<span class="asr-badge-cat">' . htmlspecialchars($mfgs[$mfg] ?? '#'.$mfg) . '</span>';
        ?>
        <tr class="<?=((int)$r['activo']===1)?'':'inactive'?>">
          <td><?=$r['id']?></td>
          <td><?=$mfg_name?></td>
          <td class="text-end"><?=$r['prioridad']?></td>
          <td class="text-end"><?=$r['dias_sin_venta_min']?> d</td>
          <td class="text-end"><?=$r['dias_sin_compra_min']!==null ? ($r['dias_sin_compra_min'].' d') : '—'?></td>
          <td class="text-end"><?=$r['dias_cobertura_min']!==null ? ($r['dias_cobertura_min'].' d') : '—'?></td>
          <td class="text-end asr-pct">−<?=number_format((float)$r['descuento_pct'],1,',','.')?>%</td>
          <td class="text-end"><?=$r['vigencia_dias']?> d</td>
          <td class="text-end"><?=$r['min_margin_pct']!==null ? number_format((float)$r['min_margin_pct'],1,',','.').'%' : '—'?></td>
          <td class="text-end">
            <?php
              $rid = (int)$r['id'];
              $vc = $rule_counts[$rid] ?? 0;
              $pc = $rule_pcounts[$rid] ?? 0;
            ?>
            <?php if ($vc > 0): ?>
              <a href="auto_specials_preview.php?rule_id=<?=$rid?><?=$f_buy_before?'&buy_before=' . urlencode($f_buy_before):''?>" title="Ver las variantes a las que aplica esta regla<?=$f_buy_before?' (con compra ≤ ' . htmlspecialchars($f_buy_before) . ')':''?>">
                <strong><?=number_format($pc,0,',','.')?></strong> p · <strong><?=number_format($vc,0,',','.')?></strong> v
              </a>
            <?php else: ?>
              <span style="color:#94a3b8">0</span>
            <?php endif; ?>
          </td>
          <td><?=htmlspecialchars($r['nota'] ?? '')?></td>
          <td><?=((int)$r['activo']===1)?'<span style="color:#16a34a;font-weight:bold">✓</span>':'<span style="color:#94a3b8">—</span>'?></td>
          <td style="font-size:11px;color:#64748b"><?=$r['modified_at']?></td>
          <td class="asr-actions">
            <a href="?edit=<?=$r['id']?>">editar</a>
            <a href="?action=toggle&id=<?=$r['id']?>" onclick="return confirm('¿Cambiar estado activo de la regla #<?=$r['id']?>?')"><?=((int)$r['activo']===1)?'desactivar':'activar'?></a>
            <a href="?action=delete&id=<?=$r['id']?>" onclick="return confirm('¿BORRAR la regla #<?=$r['id']?> definitivamente?')" style="color:#dc2626">borrar</a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <p class="asr-help">
    Cómo evalúa el motor: para cada variante con stock, se buscan las reglas <strong>activas</strong>
    cuyo <strong>fabricante</strong> coincida con el del producto (o sean GLOBAL).
    De las que cumplen criterios (días sin venta o cobertura), gana la de <strong>mayor descuento</strong>.
    La aplicación efectiva (modificar <code>specials</code> o <code>options_values_price</code>) llegará
    en Fase 3-4.
  </p>
</div>

<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
