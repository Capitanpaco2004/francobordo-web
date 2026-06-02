<?php
/**
 * Lista negra de reimportación — pantalla de administración
 * ---------------------------------------------------------
 * Revisa los productos borrados a propósito que los importadores NO deben volver a
 * dar de alta (tabla import_blacklist). Permite quitar un producto de la lista para
 * que se vuelva a importar en el siguiente pase.
 *
 * Vista AGRUPADA por producto: 1 fila por products_id, mostrando sus códigos/EAN juntos.
 * Creado 2026-06-01.
 */
require 'includes/application_top.php';
require_once dirname(__FILE__) . '/includes/import_blacklist.php';
fb_blacklist_ensure_table();

$msg = '';

// --- Acción: quitar de la lista (por products_id, o por bl_key si no hay pid) ---
if (isset($_GET['action']) && $_GET['action'] === 'remove') {
    if (isset($_GET['pid']) && (int)$_GET['pid'] > 0) {
        $pid = (int)$_GET['pid'];
        $name = '';
        $q = tep_db_query("SELECT products_name FROM import_blacklist WHERE products_id = $pid LIMIT 1");
        if ($r = tep_db_fetch_array($q)) $name = $r['products_name'];
        tep_db_query("DELETE FROM import_blacklist WHERE products_id = $pid");
        $msg = 'Quitado de la lista negra: <b>' . htmlspecialchars($name !== '' ? $name : ('producto #' . $pid)) . '</b>. Volverá a importarse en el próximo pase.';
    } elseif (isset($_GET['key'])) {
        $key = tep_db_input(trim($_GET['key']));
        tep_db_query("DELETE FROM import_blacklist WHERE bl_key = '" . $key . "'");
        $msg = 'Quitada de la lista negra la clave <b>' . htmlspecialchars($_GET['key']) . '</b>.';
    }
}

// --- Búsqueda ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
if ($search !== '') {
    $s = tep_db_input($search);
    $where = "WHERE bl_key LIKE '%" . $s . "%' OR products_name LIKE '%" . $s . "%' OR source LIKE '%" . $s . "%'";
}

// --- Paginación: máx. 200 productos por página ---
$perPage = 200;
// nº total de grupos (productos) según el filtro de búsqueda
$totGroups = 0;
$qg = tep_db_query("SELECT COUNT(*) AS g FROM (
                        SELECT 1 FROM import_blacklist
                        $where
                        GROUP BY products_id, products_name, source, manufacturers_id, deleted_by
                    ) t");
if ($rg = tep_db_fetch_array($qg)) $totGroups = (int)$rg['g'];
$totPages = max(1, (int)ceil($totGroups / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $totPages) $page = $totPages;
$offset = ($page - 1) * $perPage;

// --- Datos agrupados por producto (o por clave suelta si products_id es NULL/0) ---
$rows = array();
$q = tep_db_query("SELECT products_id, products_name, source, manufacturers_id, deleted_by,
                          MIN(date_added) AS first_added, MAX(date_added) AS last_added,
                          GROUP_CONCAT(DISTINCT CONCAT(bl_type, ':', bl_key) ORDER BY bl_type SEPARATOR '||') AS keys_concat,
                          GROUP_CONCAT(DISTINCT bl_key SEPARATOR ',') AS raw_keys
                   FROM import_blacklist
                   $where
                   GROUP BY products_id, products_name, source, manufacturers_id, deleted_by
                   ORDER BY last_added DESC, products_id DESC
                   LIMIT $perPage OFFSET $offset");
while ($r = tep_db_fetch_array($q)) $rows[] = $r;

// total productos distintos / total claves
$totP = 0; $totK = 0;
$qt = tep_db_query("SELECT COUNT(DISTINCT IFNULL(products_id,0)) AS p, COUNT(*) AS k FROM import_blacklist");
if ($rt = tep_db_fetch_array($qt)) { $totP = (int)$rt['p']; $totK = (int)$rt['k']; }
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td><div style="padding:20px;">
<h2>Lista negra de reimportación</h2>
<p>Productos que has borrado a propósito y que los importadores de proveedor <strong>no volverán a dar de alta</strong>.
   Si quitas un producto de esta lista, se podrá volver a importar en el siguiente pase del importador correspondiente.</p>
<p style="color:#888;font-size:12px;">Total: <b><?php echo $totP; ?></b> productos vetados (<?php echo $totK; ?> claves entre modelos, referencias y EAN).</p>

<?php if ($msg !== ''): ?>
<div style="background:#e7f6e7;border:1px solid #9c9;padding:10px;border-radius:4px;margin:10px 0;"><?php echo $msg; ?></div>
<?php endif; ?>

<form method="get" style="margin:12px 0;">
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por nombre, código, EAN o proveedor..." style="width:320px;padding:5px;">
    <button type="submit" class="xbutton small hv9">Buscar</button>
    <?php if ($search !== ''): ?><a href="<?php echo tep_href_link('import_blacklist_admin.php'); ?>" class="xbutton small">Ver todos</a><?php endif; ?>
</form>

<table class="table-data" border="0" cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;">
    <tr style="background:#2b2f3a;color:#fff;text-align:left;">
        <th style="padding:8px;">Producto</th>
        <th style="padding:8px;">Proveedor</th>
        <th style="padding:8px;">Códigos / EAN vetados</th>
        <th style="padding:8px;">Borrado por</th>
        <th style="padding:8px;">Fecha</th>
        <th style="padding:8px;text-align:center;">Acción</th>
    </tr>
    <?php if (empty($rows)): ?>
    <tr><td colspan="6" style="padding:14px;text-align:center;color:#888;">No hay productos en la lista negra<?php echo $search !== '' ? ' para esa búsqueda' : ''; ?>.</td></tr>
    <?php else: $i = 0; foreach ($rows as $row): $i++; $pid = (int)$row['products_id'];
        // formatea las claves: model:xxx || ean:yyy
        $chips = '';
        foreach (explode('||', $row['keys_concat']) as $kv) {
            $parts = explode(':', $kv, 2);
            $type = $parts[0]; $val = isset($parts[1]) ? $parts[1] : '';
            $color = $type === 'ean' ? '#3598db' : ($type === 'reference' ? '#888' : '#5e9424');
            $label = $type === 'ean' ? 'EAN' : ($type === 'reference' ? 'Ref' : 'Modelo');
            $chips .= '<span style="display:inline-block;background:' . $color . ';color:#fff;border-radius:3px;padding:1px 6px;margin:1px;font-size:11px;">' . $label . ': ' . htmlspecialchars($val) . '</span> ';
        }
        $removeUrl = $pid > 0
            ? tep_href_link('import_blacklist_admin.php', 'action=remove&pid=' . $pid . ($search !== '' ? '&search=' . urlencode($search) : ''))
            : tep_href_link('import_blacklist_admin.php', 'action=remove&key=' . urlencode($row['raw_keys']) . ($search !== '' ? '&search=' . urlencode($search) : ''));
    ?>
    <tr style="background:<?php echo $i % 2 ? '#fff' : '#f5f5f5'; ?>;border-bottom:1px solid #e3e3e3;">
        <td style="padding:8px;"><b><?php echo htmlspecialchars($row['products_name'] !== '' ? $row['products_name'] : ('producto #' . $pid)); ?></b><?php echo $pid > 0 ? ' <span style="color:#aaa;font-size:11px;">(ID ' . $pid . ')</span>' : ''; ?></td>
        <td style="padding:8px;"><?php echo htmlspecialchars($row['source'] ?? ''); ?></td>
        <td style="padding:8px;"><?php echo $chips; ?></td>
        <td style="padding:8px;font-size:12px;"><?php echo htmlspecialchars($row['deleted_by'] ?? ''); ?></td>
        <td style="padding:8px;font-size:12px;white-space:nowrap;"><?php echo htmlspecialchars($row['last_added'] ?? ''); ?></td>
        <td style="padding:8px;text-align:center;">
            <a href="<?php echo $removeUrl; ?>" onclick="return confirm('¿Quitar este producto de la lista negra? Se podrá volver a importar.');"
               style="display:inline-block;background:#d9342b;color:#fff;border-radius:4px;padding:4px 10px;text-decoration:none;font-size:12px;white-space:nowrap;">Quitar de la lista</a>
        </td>
    </tr>
    <?php endforeach; endif; ?>
</table>

<?php
// --- Controles de paginación ---
$pgBase = function ($p) use ($search) {
    $params = 'page=' . $p . ($search !== '' ? '&search=' . urlencode($search) : '');
    return tep_href_link('import_blacklist_admin.php', $params);
};
$desde = $totGroups > 0 ? ($offset + 1) : 0;
$hasta = min($offset + $perPage, $totGroups);
?>
<div style="margin-top:15px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;">
    <span style="color:#666;">Mostrando <b><?php echo $desde; ?>–<?php echo $hasta; ?></b> de <b><?php echo $totGroups; ?></b> productos · página <?php echo $page; ?>/<?php echo $totPages; ?></span>
    <?php if ($totPages > 1): ?>
        <span style="margin-left:auto;"></span>
        <?php if ($page > 1): ?>
            <a href="<?php echo $pgBase(1); ?>" class="xbutton small">« Primera</a>
            <a href="<?php echo $pgBase($page - 1); ?>" class="xbutton small">‹ Anterior</a>
        <?php endif; ?>
        <?php if ($page < $totPages): ?>
            <a href="<?php echo $pgBase($page + 1); ?>" class="xbutton small">Siguiente ›</a>
            <a href="<?php echo $pgBase($totPages); ?>" class="xbutton small">Última »</a>
        <?php endif; ?>
    <?php endif; ?>
</div>
<p style="margin-top:10px;color:#888;font-size:12px;">Máximo 200 productos por página. Usa el buscador para filtrar.</p>
</div></td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
