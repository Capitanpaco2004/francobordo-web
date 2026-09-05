<?php
require 'includes/application_top.php';
require_once dirname(__FILE__) . '/includes/universal_import_lib.php';
require_once dirname(__FILE__) . '/includes/import_blacklist.php';

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

/* ──────────────────────────────────────────────────────────────────────────
 * Importador Universal (altas por URL)
 *
 * Flujo en 2 pasos:
 *   1) "Analizar": se descarga la ficha (WooCommerce Store API / Shopify .js /
 *      JSON-LD + HTML genérico), se extraen nombre, descripción, imágenes,
 *      variantes, marca, SKU, peso… y se muestra un formulario de revisión con
 *      los precios por variante ya calculados (PVP tecleado × ratio de la web).
 *   2) "Importar": traduce/maqueta con el LLM (ES+EN), descarga imágenes e
 *      inserta el producto (status=2 salvo "activar") en la categoría "Universal".
 *
 * Toda la lógica vive en includes/universal_import_lib.php (compartida con el
 * lanzador CLI import/Universal/uv_cli.php). Creado 2026-09-04.
 * ────────────────────────────────────────────────────────────────────────── */

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$isExec = ($action === 'execute');
$errors = []; $plan = null; $fetchDiag = [];
$selfUrl = tep_href_link('import-universal-altas.php');

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { $errors[] = 'BD: ' . $mysqli->connect_error; $isExec = false; }
else $mysqli->set_charset('utf8');
$adminName = '';
if (!empty($login_id) && empty($errors)) { $ra = $mysqli->query("SELECT admin_email_address FROM admin WHERE admin_id=" . (int) $login_id); if ($ra && ($xa = $ra->fetch_assoc())) $adminName = (string) $xa['admin_email_address']; }

function uvLogMsg($msg) {
    echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars('[' . date('H:i:s') . '] ' . $msg) . '</pre>';
    @flush();
}
function uvH($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function uvFmt($n, $d = 2) { return number_format((float) $n, $d, ',', '.'); }
function uvEditLink($pid) {
    global $mysqli;
    $cat = 0;
    if ($mysqli instanceof mysqli) { $r = $mysqli->query("SELECT categories_id FROM products_to_categories WHERE products_id=" . (int) $pid . " LIMIT 1"); if ($r && ($x = $r->fetch_assoc())) $cat = (int) $x['categories_id']; }
    return tep_href_link('categories.php', 'cPath=' . $cat . '&pID=' . (int) $pid . '&action=new_product');
}

if ($action === 'analyze' && empty($errors)) {
    $url = trim((string) ($_POST['url'] ?? ''));
    $prod = uvFetchProduct($url, $fetchDiag);
    if (isset($prod['error'])) { $errors[] = $prod['error']; if (!empty($prod['diag'])) $fetchDiag = $prod['diag']; }
    else {
        $plan = uvBuildPlan($prod, $_POST);
        if (!uvSavePlan($plan)) { $errors[] = 'No se pudo guardar el plan en ' . UV_CACHE_DIR . ' (permisos).'; $plan = null; }
    }
}
if ($isExec) {
    $plan = uvLoadPlan($_POST['token'] ?? '');
    if (!$plan) { $errors[] = 'Plan caducado o no encontrado: vuelve a analizar la URL.'; $isExec = false; }
    else {
        if (isset($_POST['main_img']) && is_numeric($_POST['main_img'])) {
            $order = [(int) $_POST['main_img']];
            foreach (array_keys($plan['images']) as $i) if ($i !== (int) $_POST['main_img']) $order[] = $i;
            $_POST['img_order'] = $order;
        }
        $plan = uvApplyReview($plan, $_POST);
        if (isset($_POST['src_text'])) $plan['product']['text'] = trim(str_replace("\r", '', (string) $_POST['src_text']));
        uvSavePlan($plan);
        @header('X-Accel-Buffering: no');
        @header('Content-Type: text/html; charset=utf-8');
        while (ob_get_level() > 0) @ob_end_flush();
        @ob_implicit_flush(true);
        if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
    }
}
?>
<?php require THEME . 'html/header.php'; ?>
<style>
.uv-box{background:#f7f7f7;border:1px solid #ddd;border-radius:5px;padding:14px;margin:12px 0}
.uv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(0,1fr));gap:10px 18px}
.uv-grid label,.uv-field label{display:block;font-size:12px;color:#555;margin-bottom:2px}
.uv-grid input[type=text],.uv-grid input[type=number],.uv-grid select{width:100%;box-sizing:border-box}
.uv-tbl{border-collapse:collapse;width:100%;font-size:12px}
.uv-tbl th,.uv-tbl td{border:1px solid #ddd;padding:4px 6px;vertical-align:middle}
.uv-tbl th{background:#e9eef3;text-align:left}
.uv-tbl input[type=text]{width:100%;box-sizing:border-box;font-size:12px}
.uv-tbl input.num{width:80px;font-size:12px;text-align:right}
.uv-thumb{max-height:90px;max-width:120px;border:1px solid #ccc;background:#fff}
.uv-err{background:#fde8e8;border:1px solid #e0a0a0;color:#900;padding:10px;border-radius:4px;margin:10px 0}
.uv-note{background:#fffbe6;border:1px solid #ffd700;padding:10px;border-radius:4px;font-size:13px}
.uv-diag{font-family:monospace;font-size:11px;color:#666;white-space:pre-wrap}
.uv-chk label{display:inline-block;margin-right:18px;font-size:13px}
</style>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<h2>Importador Universal (alta de producto por URL)</h2>
<?php foreach ($errors as $e) echo '<div class="uv-err">' . uvH($e) . '</div>';
      if (!empty($errors) && !empty($fetchDiag)) echo '<div class="uv-diag">' . uvH(implode("\n", $fetchDiag)) . '</div>'; ?>

<?php if ($isExec): ?>
    <?php $simulate = !empty($_POST['simulate']); ?>
    <h3><?php echo $simulate ? 'SIMULACIÓN (no se inserta nada)' : 'IMPORTACIÓN REAL'; ?> — <?php echo uvH($plan['url']); ?></h3>
    <div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;max-height:520px;overflow-y:auto;">
<?php
    echo str_pad('<!-- streaming pad -->', 4096) . "\n"; @flush();
    $t0 = microtime(true);
    $res = uvExecutePlan($mysqli, $plan, ['simulate' => $simulate, 'force' => !empty($_POST['force']), 'admin' => $adminName], 'uvLogMsg');
    uvLogMsg(sprintf('Fin en %.1fs', microtime(true) - $t0));
?>
    </div>
    <?php if ($res['ok'] && $res['pid'] > 0): ?>
        <div class="uv-note" style="margin-top:12px;">
            <strong>Producto creado: #<?php echo (int) $res['pid']; ?></strong> — <?php echo uvH($res['texts']['name_es'] ?? ''); ?><br>
            <a href="<?php echo uvEditLink($res['pid']); ?>" class="xbutton small hv9 verde" target="_blank">Editar en el admin (revisar y activar)</a>
            &nbsp; <a href="https://www.francobordo.com/product_info.php?products_id=<?php echo (int) $res['pid']; ?>" class="xbutton small hv9" target="_blank">Ver en la tienda</a>
        </div>
    <?php endif; ?>
    <?php if (!empty($res['texts']) && ($simulate || !$res['ok'])): $t = $res['texts']; ?>
        <div class="uv-box">
            <p><strong>Nombre ES:</strong> <?php echo uvH($t['name_es']); ?><br><strong>Nombre EN:</strong> <?php echo uvH($t['name_en']); ?></p>
            <?php if (!empty($t['labels'])): ?><p><strong>Variantes:</strong> <?php foreach ($t['labels'] as $lb) echo uvH($lb['es']) . ' / <em>' . uvH($lb['en']) . '</em> &nbsp;·&nbsp; '; ?></p><?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(0,1fr));gap:16px;">
                <div><p><strong>Descripción ES</strong></p><div style="background:#fff;border:1px solid #ddd;padding:10px;font-size:13px;"><?php echo $t['desc_es']; ?></div></div>
                <div><p><strong>Descripción EN</strong></p><div style="background:#fff;border:1px solid #ddd;padding:10px;font-size:13px;"><?php echo $t['desc_en']; ?></div></div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($res['conflicts']) && !$res['ok']): ?>
        <form method="post" action="<?php echo $selfUrl; ?>" style="margin-top:10px;">
            <?php foreach ($_POST as $k => $v) { if ($k === 'force' || $k === 'action') continue; if (is_array($v)) { foreach ($v as $k2 => $v2) { if (is_array($v2)) { foreach ($v2 as $k3 => $v3) echo '<input type="hidden" name="' . uvH($k) . '[' . uvH($k2) . '][' . uvH($k3) . ']" value="' . uvH($v3) . '">'; } else echo '<input type="hidden" name="' . uvH($k) . '[' . uvH($k2) . ']" value="' . uvH($v2) . '">'; } } else echo '<input type="hidden" name="' . uvH($k) . '" value="' . uvH($v) . '">'; } ?>
            <input type="hidden" name="action" value="execute"><input type="hidden" name="force" value="1">
            <button type="submit" class="xbutton small hv9" onclick="return confirm('¿Importar aunque haya duplicados?');">Forzar importación pese a los duplicados</button>
        </form>
    <?php endif; ?>
    <p style="margin-top:15px;"><a href="<?php echo $selfUrl; ?>" class="xbutton small hv9">← Importar otro producto</a></p>

<?php elseif ($plan): $p = $plan['product']; $in = $plan['input']; ?>
    <form method="post" action="<?php echo $selfUrl; ?>" id="uvReview">
    <input type="hidden" name="action" value="execute">
    <input type="hidden" name="token" value="<?php echo uvH($plan['token']); ?>">
    <input type="hidden" name="img_submitted" value="1">
    <div class="uv-box">
        <p style="margin:0 0 8px;"><strong>Ficha analizada</strong>: <a href="<?php echo uvH($p['url']); ?>" target="_blank"><?php echo uvH($p['url']); ?></a><br>
        Fuente: <code><?php echo uvH($p['source']); ?></code> · Idioma detectado: <code><?php echo uvH($p['lang'] ?: '?'); ?></code>
        · Precio en la web: <?php echo $p['price'] !== null ? uvFmt($p['price']) . ' ' . uvH($p['currency']) : '—'; ?>
        · Peso web: <?php echo ($p['weight_kg'] ?? null) > 0 ? uvFmt($p['weight_kg'], 3) . ' kg' : '—'; ?>
        · Categorías web: <?php echo uvH(implode(' > ', $p['categories']) ?: '—'); ?>
        · Variantes: <?php echo count($p['variants']); ?> <?php echo $p['option_name'] !== '' ? '(' . uvH($p['option_name']) . ')' : ''; ?></p>
        <details><summary style="cursor:pointer;font-size:12px;color:#666;">Diagnóstico de la extracción</summary><div class="uv-diag"><?php echo uvH(implode("\n", $p['diag'])); ?></div></details>
    </div>

    <div class="uv-box">
        <div class="uv-grid" style="grid-template-columns:2fr 1fr 1fr 1fr;">
            <div><label>Nombre origen (se traduce a ES/EN con el LLM)</label><input type="text" name="input[name]" value="<?php echo uvH($in['name'] !== '' ? $in['name'] : $p['name']); ?>" maxlength="80"></div>
            <div><label>Marca / fabricante (se crea si no existe)</label><input type="text" name="input[brand]" list="uvMfg" value="<?php echo uvH($in['brand']); ?>" maxlength="32"><?php if ($in['brand'] === '' && !empty($p['site_name'])) echo '<span style="color:#a60;font-size:11px;">La ficha no indica marca; nombre del sitio: <strong>' . uvH($p['site_name']) . '</strong></span>'; ?></div>
            <div><label>Referencia / SKU (vacío = se genera UV&lt;id&gt;)</label><input type="text" name="input[sku]" value="<?php echo uvH($in['sku']); ?>" maxlength="32"></div>
            <div><label>EAN (vacío = interno prefijo 28; ignorado si hay variantes)</label><input type="text" name="input[ean]" value="<?php echo uvH($in['ean']); ?>" maxlength="14"></div>
        </div>
        <div class="uv-grid" style="grid-template-columns:repeat(5,1fr);margin-top:10px;">
            <div><label>PVP con IVA (€) — variante más barata</label><input type="text" name="input[pvp_gross]" value="<?php echo uvH($in['pvp_gross'] > 0 ? number_format($in['pvp_gross'], 2, '.', '') : ''); ?>"></div>
            <div><label>Precio profesional G1 con IVA (€) (vacío = tiers si hay coste; si no, sin tarifa)</label><input type="text" name="input[g1_gross]" value="<?php echo uvH($in['g1_gross'] > 0 ? number_format($in['g1_gross'], 2, '.', '') : ''); ?>"></div>
            <div><label>Coste neto (€) (opcional)</label><input type="text" name="input[cost]" value="<?php echo uvH($in['cost'] > 0 ? number_format($in['cost'], 4, '.', '') : ''); ?>"></div>
            <div><label>Peso kg (vacío = web o 1,0)</label><input type="text" name="input[weight]" value="<?php echo uvH($in['weight'] > 0 ? $in['weight'] : ''); ?>" placeholder="<?php echo uvH($plan['weight_kg']); ?>"></div>
            <div><label>IVA</label><select name="input[tax_class]"><option value="1"<?php echo $in['tax_class'] == 1 ? ' selected' : ''; ?>>21 % (general)</option><option value="3"<?php echo $in['tax_class'] == 3 ? ' selected' : ''; ?>>10 % (reducido)</option><option value="2"<?php echo $in['tax_class'] == 2 ? ' selected' : ''; ?>>4 % (superreducido)</option></select></div>
        </div>
        <div class="uv-chk" style="margin-top:10px;">
            <label><input type="checkbox" name="input[brand_prefix]" value="1"<?php echo $in['brand_prefix'] ? ' checked' : ''; ?>> Anteponer la marca al nombre</label>
            <label><input type="checkbox" name="input[use_llm]" value="1"<?php echo $in['use_llm'] ? ' checked' : ''; ?>> Traducir y maquetar con el LLM (ES+EN)</label>
            <label><input type="checkbox" name="input[with_variants]" value="1"<?php echo $in['with_variants'] ? ' checked' : ''; ?>> Importar con variantes</label>
            <label><input type="checkbox" name="input[activate]" value="1"<?php echo $in['activate'] ? ' checked' : ''; ?>> Activar directamente (status=1; si no, borrador status=2)</label>
            <label><input type="checkbox" name="force" value="1"> Forzar aunque haya duplicados</label>
        </div>
    </div>

    <?php if (!empty($plan['variants'])): ?>
    <div class="uv-box">
        <p style="margin:0 0 6px;"><strong>Variantes</strong> — opción de la tienda: <code>options_id=<?php echo (int) $plan['option_id']; ?></code> (<?php echo uvH($plan['option_name']); ?>). El padre será la más barata; el PVP de cada una sale del PVP tecleado × ratio de precios de la web (edítalo si hace falta). Etiquetas: se traducen con el LLM.</p>
        <table class="uv-tbl"><tr><th>Imp.</th><th>Etiqueta (origen)</th><th>SKU</th><th>Precio web</th><th>Ratio</th><th>PVP IVA inc. €</th><th>G1 IVA inc. €</th><th>EAN</th><th>Peso kg</th></tr>
        <?php foreach ($plan['variants'] as $i => $v): ?>
            <tr>
                <td><input type="checkbox" name="var[<?php echo $i; ?>][include]" value="1"<?php echo $v['include'] ? ' checked' : ''; ?>></td>
                <td><input type="text" name="var[<?php echo $i; ?>][label]" value="<?php echo uvH($v['label']); ?>"></td>
                <td style="width:130px;"><input type="text" name="var[<?php echo $i; ?>][sku]" value="<?php echo uvH($v['sku']); ?>" maxlength="32"></td>
                <td style="text-align:right;"><?php echo $v['src_price'] !== null ? uvFmt($v['src_price']) . ' ' . uvH($p['currency']) : '—'; ?></td>
                <td style="text-align:right;"><?php echo uvFmt($v['ratio'], 3); ?></td>
                <td><input class="num" type="text" name="var[<?php echo $i; ?>][pvp_gross]" value="<?php echo uvH(number_format($v['pvp_gross'], 2, '.', '')); ?>"></td>
                <td><input class="num" type="text" name="var[<?php echo $i; ?>][g1_gross]" value="<?php echo uvH($v['g1_gross'] > 0 ? number_format($v['g1_gross'], 2, '.', '') : ''); ?>"></td>
                <td style="width:130px;"><input type="text" name="var[<?php echo $i; ?>][ean]" value="<?php echo uvH($v['ean']); ?>" maxlength="14"></td>
                <td><input class="num" type="text" name="var[<?php echo $i; ?>][weight]" value="<?php echo uvH($v['weight']); ?>"></td>
            </tr>
        <?php endforeach; ?>
        </table>
    </div>
    <?php elseif (!empty($p['variants'])): ?>
        <div class="uv-note">La web tiene <?php echo count($p['variants']); ?> variantes pero se ha desmarcado "Importar con variantes": se dará de alta como producto suelto con el PVP tecleado.</div>
    <?php endif; ?>

    <div class="uv-box">
        <p style="margin:0 0 6px;"><strong>Imágenes</strong> (la marcada como principal va a <code>products_image</code>; el resto, hasta <?php echo UV_MAX_SUBIMAGES; ?>, a la galería; se convierten a JPG). Desmarca logos o imágenes ajenas.</p>
        <?php if (empty($plan['images'])): ?><div class="uv-err">No se encontró ninguna imagen: sin imagen no se puede importar.</div><?php endif; ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
        <?php foreach ($plan['images'] as $i => $im): ?>
            <div style="text-align:center;font-size:11px;max-width:140px;">
                <a href="<?php echo uvH($im['url']); ?>" target="_blank" rel="noreferrer"><img class="uv-thumb" src="<?php echo uvH($im['url']); ?>" alt="(sin vista previa)" referrerpolicy="no-referrer"></a><br>
                <label><input type="checkbox" name="img[<?php echo $i; ?>]" value="1"<?php echo $im['include'] ? ' checked' : ''; ?>> incluir</label>
                <label><input type="radio" name="main_img" value="<?php echo $i; ?>"<?php echo $i === 0 ? ' checked' : ''; ?>> principal</label>
                <div style="color:#888;"><?php echo uvH($im['kind']); ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="uv-box">
        <p style="margin:0 0 6px;"><strong>Texto origen de la descripción</strong> (lo que se traduce/maqueta; puedes limpiarlo antes). Especificaciones detectadas: <?php echo count($p['specs']); ?><?php if (!empty($p['specs'])) { echo ' — '; foreach ($p['specs'] as $s) echo uvH($s[0]) . ': ' . uvH($s[1]) . '; '; } ?></p>
        <textarea name="src_text" rows="10" style="width:100%;box-sizing:border-box;font-size:12px;"><?php echo uvH($p['text']); ?></textarea>
    </div>

    <p>
        <button type="submit" class="xbutton small hv9 verde" onclick="return confirm('¿Importar el producto en la tienda?');">Importar</button>
        <button type="submit" name="simulate" value="1" class="xbutton small hv9">Solo simular (ver textos del LLM sin insertar)</button>
        <a href="<?php echo $selfUrl; ?>" class="xbutton small hv9">Cancelar</a>
    </p>
    </form>

<?php else: ?>
    <p class="uv-note">
        Pega la URL de la ficha del producto en la web del fabricante/proveedor y teclea lo que la web no sabe (PVP, precio profesional, coste, EAN).
        El importador extrae nombre, descripción, imágenes, variantes y especificaciones (WooCommerce, Shopify o cualquier web con JSON-LD/HTML),
        traduce y maqueta ES+EN con el LLM local y crea el producto en la categoría <strong><?php echo UV_CATEGORY_ES; ?></strong> (oculta) como borrador (<code>status=2</code>) para revisarlo.
        Reglas: precios netos con IVA calculado desde lo tecleado · variantes en deltas (padre = más barata) · stock no se toca · EAN interno prefijo 28 si falta · dedup por SKU/EAN/URL/lista negra.
    </p>
    <form method="post" action="<?php echo $selfUrl; ?>">
        <input type="hidden" name="action" value="analyze">
        <div class="uv-box">
            <div class="uv-field"><label>URL de la ficha del producto</label><input type="text" name="url" style="width:100%;box-sizing:border-box;" placeholder="https://www.nasamarine.com/product/wind-cup-kit/" value="<?php echo uvH($_POST['url'] ?? ''); ?>" required></div>
            <div class="uv-grid" style="grid-template-columns:repeat(4,1fr);margin-top:10px;">
                <div><label>PVP con IVA (€) *</label><input type="text" name="pvp_gross" value="<?php echo uvH($_POST['pvp_gross'] ?? ''); ?>" required></div>
                <div><label>Precio profesional G1 con IVA (€)</label><input type="text" name="g1_gross" value="<?php echo uvH($_POST['g1_gross'] ?? ''); ?>"></div>
                <div><label>Coste neto (€)</label><input type="text" name="cost" value="<?php echo uvH($_POST['cost'] ?? ''); ?>"></div>
                <div><label>EAN</label><input type="text" name="ean" value="<?php echo uvH($_POST['ean'] ?? ''); ?>"></div>
                <div><label>Marca (vacío = la detecta la web)</label><input type="text" name="brand" list="uvMfg" value="<?php echo uvH($_POST['brand'] ?? ''); ?>"></div>
                <div><label>Referencia / SKU (vacío = el de la web)</label><input type="text" name="sku" value="<?php echo uvH($_POST['sku'] ?? ''); ?>"></div>
                <div><label>Peso kg (vacío = web o 1,0)</label><input type="text" name="weight" value="<?php echo uvH($_POST['weight'] ?? ''); ?>"></div>
                <div><label>IVA</label><select name="tax_class"><option value="1">21 % (general)</option><option value="3">10 % (reducido)</option><option value="2">4 % (superreducido)</option></select></div>
            </div>
            <div class="uv-chk" style="margin-top:10px;">
                <label><input type="checkbox" name="brand_prefix" value="1" checked> Anteponer la marca al nombre</label>
                <label><input type="checkbox" name="use_llm" value="1" checked> Traducir y maquetar con el LLM</label>
                <label><input type="checkbox" name="with_variants" value="1" checked> Importar con variantes</label>
                <label><input type="checkbox" name="activate" value="1"> Activar directamente</label>
            </div>
            <p style="margin:12px 0 0;"><button type="submit" class="xbutton small hv9 verde">Analizar la ficha →</button></p>
        </div>
    </form>
    <?php if (empty($errors) || $action === 'analyze'): $recent = uvRecentImports($mysqli, 30); ?>
    <h3>Últimas importaciones</h3>
    <?php if (empty($recent)): ?><p style="color:#888;">Todavía no hay importaciones.</p><?php else: ?>
    <table class="uv-tbl"><tr><th>Fecha</th><th>#</th><th>Producto</th><th>Origen</th><th>Var.</th><th>Estado</th><th>PVP neto</th><th>Quién</th></tr>
    <?php foreach ($recent as $r): ?>
        <tr>
            <td><?php echo uvH($r['date_added']); ?></td>
            <td><a href="<?php echo uvEditLink($r['products_id']); ?>" target="_blank"><?php echo (int) $r['products_id']; ?></a></td>
            <td><?php echo uvH($r['products_name']); ?></td>
            <td><a href="<?php echo uvH($r['source_url']); ?>" target="_blank"><?php echo uvH($r['source_host']); ?></a> <span style="color:#888;">(<?php echo uvH($r['source_type']); ?>)</span></td>
            <td><?php echo (int) $r['variants']; ?></td>
            <td><?php echo $r['products_status'] === null ? '<span style="color:#900;">borrado</span>' : ((int) $r['products_status'] === 1 ? 'activo' : 'borrador (' . (int) $r['products_status'] . ')'); ?></td>
            <td style="text-align:right;"><?php echo $r['products_price'] !== null ? uvFmt($r['products_price'], 4) : '—'; ?></td>
            <td><?php echo uvH($r['admin_name']); ?></td>
        </tr>
    <?php endforeach; ?>
    </table>
    <?php endif; endif; ?>
<?php endif; ?>

<?php if (!$isExec): ?>
<datalist id="uvMfg"><?php foreach (uvListManufacturers($mysqli) as $mn) echo '<option value="' . uvH($mn) . '">'; ?></datalist>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
