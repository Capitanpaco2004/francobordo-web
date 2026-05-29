<?php
/**
 * osculati_variant_images.php — CLI
 *
 * Para un producto Osculati con variantes: descarga del FTP la imagen específica de
 * cada variante (Code2SerXml <images>) y:
 *   1) crea la imagen-por-valor (images/atributos/ai_<pid>-<oid>-<vid>.jpg +
 *      products_attributes_actions action=change_image) → al seleccionar la variante
 *      en la ficha se muestra su imagen.
 *   2) añade las imágenes DISTINTAS a la galería del padre (products_subimages),
 *      conservando la products_image actual.
 *
 * Idempotente: re-ejecutar no duplica (upsert action + dedup subimages).
 *
 *   php osculati_variant_images.php <PID> [APPLY]
 *   (sin APPLY = DRY-RUN)
 */
$pid   = (int) ($argv[1] ?? 0);
$apply = in_array('APPLY', $argv ?? [], true);
if ($pid <= 0) { fwrite(STDERR, "Uso: php osculati_variant_images.php <PID> [APPLY]\n"); exit(1); }

const OSC_USER     = 'C54293';
const OSC_PASS     = '0XxBkWSb';
const OSC_FTP_BASE = 'ftp://fw.osculati.it/';
const OSC_IMG_FOLDER = 'IMG/800/';
const IMG_PROD = '/home/francobordo/public_html/images/productos/';
const IMG_ATTR = '/home/francobordo/public_html/images/atributos/';
const XT_LOCAL = '/tmp/Code2SerXml.txt';

require '/home/francobordo/public_html/includes/configure.php';
$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($m->connect_error) { fwrite(STDERR, "DB ERROR\n"); exit(1); }
$m->set_charset('utf8');

echo "PID=$pid | Modo: " . ($apply ? "APPLY" : "DRY-RUN") . "\n\n";

/* ---- Code2SerXml: orderCode(sin sufijo) → primer código de imagen ---- */
function readUtf16File($path) {
    $raw = file_get_contents($path);
    $u8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
    $u8 = ltrim($u8, "\xEF\xBB\xBF\xFF\xFE");
    $rows = [];
    foreach (preg_split("/\r\n|\r|\n/", $u8) as $line) { if ($line !== '') $rows[] = explode("\t", $line); }
    return $rows;
}
if (!file_exists(XT_LOCAL)) {
    echo "Descargando Code2SerXml.txt...\n";
    $ch = curl_init(OSC_FTP_BASE . 'ENG/Code2SerXml.txt');
    curl_setopt_array($ch, [CURLOPT_USERPWD => OSC_USER . ':' . OSC_PASS, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 180]);
    $d = curl_exec($ch);
    if ($d === false || strlen($d) < 1000) { fwrite(STDERR, "FTP Code2SerXml falló\n"); exit(1); }
    file_put_contents(XT_LOCAL, $d); unset($d);
}
$imgByCode = [];
foreach (readUtf16File(XT_LOCAL) as $r) {
    if (count($r) < 5) continue;
    $oc = strtolower(preg_replace('/#.*$/', '', trim($r[0])));
    if ($oc === '') continue;
    if (preg_match('#<img[^>]*>(.*?)</img>#', $r[4], $mm)) $imgByCode[$oc] = trim($mm[1]);
}
echo "Code2SerXml: " . count($imgByCode) . " ítems con imagen\n\n";

/* ---- Descarga FTP de una imagen (con cache local por código) ---- */
function ftpDownloadImg($code, $destPath) {
    $name = $code;
    if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $name)) $name .= '.jpg';
    $ch = curl_init(OSC_FTP_BASE . OSC_IMG_FOLDER . rawurlencode($name));
    $fp = fopen($destPath, 'wb');
    curl_setopt_array($ch, [CURLOPT_USERPWD => OSC_USER . ':' . OSC_PASS, CURLOPT_FILE => $fp, CURLOPT_TIMEOUT => 90]);
    $ok = curl_exec($ch); fclose($fp);
    if (!$ok || filesize($destPath) < 200) { @unlink($destPath); return false; }
    return true;
}

/* ---- Variantes del producto ---- */
$variants = [];
$r = $m->query("SELECT options_id oid, options_values_id vid, reference FROM products_attributes WHERE products_id=$pid");
while ($x = $r->fetch_assoc()) $variants[] = $x;
if (!$variants) { echo "Sin variantes. Nada que hacer.\n"; exit(0); }

/* products_image actual (para nombrar subimágenes y mantenerla) */
$prod = $m->query("SELECT products_image, products_subimages FROM products WHERE products_id=$pid")->fetch_assoc();
$mainImg = $prod['products_image'];
$base = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '', $mainImg);

$srcCache = [];   // imgcode → ruta temporal descargada
$distinctCodes = [];
$valActions = [];  // [oid,vid,imgcode,aiFile]

foreach ($variants as $v) {
    $key = strtolower(preg_replace('/#.*$/', '', trim($v['reference'])));
    $code = $imgByCode[$key] ?? '';
    if ($code === '') { echo "  variante {$v['reference']} → SIN imagen en XML\n"; continue; }
    if (!isset($srcCache[$code])) {
        $tmp = sys_get_temp_dir() . '/oscimg_' . md5($code) . '.jpg';
        if (!file_exists($tmp)) {
            if (!ftpDownloadImg($code, $tmp)) { echo "  $code → descarga FTP FALLÓ\n"; continue; }
        }
        $srcCache[$code] = $tmp;
        if (!in_array($code, $distinctCodes, true)) $distinctCodes[] = $code;
    }
    $aiFile = 'ai_' . $pid . '-' . $v['oid'] . '-' . $v['vid'] . '.jpg';
    $valActions[] = ['oid'=>$v['oid'], 'vid'=>$v['vid'], 'code'=>$code, 'ai'=>$aiFile, 'ref'=>$v['reference']];
    echo "  variante {$v['reference']} (vid {$v['vid']}) → $code → $aiFile\n";
}

echo "\nImágenes DISTINTAS para galería del padre: " . count($distinctCodes) . " (" . implode(', ', $distinctCodes) . ")\n";

if (!$apply) { echo "\nDRY-RUN. Para aplicar: php " . basename(__FILE__) . " $pid APPLY\n"; exit(0); }

/* ---- APPLY ---- */
// 1) Imagen por valor (atributos) + action change_image
foreach ($valActions as $a) {
    $dst = IMG_ATTR . $a['ai'];
    if (!@copy($srcCache[$a['code']], $dst)) { echo "  ERROR copiando {$a['ai']}\n"; continue; }
    $combi = $a['oid'] . '-' . $a['vid'];
    $exists = $m->query("SELECT id FROM products_attributes_actions WHERE products_id=$pid AND products_attributes='$combi' AND action='change_image'")->num_rows;
    $valEsc = $m->real_escape_string($a['ai']);
    if ($exists) {
        $m->query("UPDATE products_attributes_actions SET value='$valEsc' WHERE products_id=$pid AND products_attributes='$combi' AND action='change_image'");
    } else {
        $m->query("INSERT INTO products_attributes_actions (products_id, products_attributes, value, action) VALUES ($pid, '$combi', '$valEsc', 'change_image')");
    }
    echo "  ✓ imagen-por-valor $combi → {$a['ai']}\n";
}

// 2) Galería del padre: products_subimages (conservar products_image actual)
//    Dedup POR CONTENIDO (md5): no repetir la imagen principal ni entre sí.
$subFiles = [];
$cur = $prod['products_subimages'];
if (is_string($cur) && $cur !== '' && ($dec = json_decode($cur, true)) && is_array($dec)) $subFiles = $dec;
$seenHashes = [];
if ($mainImg !== '' && file_exists(IMG_PROD . $mainImg)) $seenHashes[md5_file(IMG_PROD . $mainImg)] = true;
foreach ($subFiles as $sf) { if (file_exists(IMG_PROD . $sf)) $seenHashes[md5_file(IMG_PROD . $sf)] = true; }
$n = 1;
foreach ($distinctCodes as $code) {
    $h = md5_file($srcCache[$code]);
    if (isset($seenHashes[$h])) { echo "  galería: $code = duplicada del padre (mismo contenido), skip\n"; continue; }
    $n++;
    $fname = $base . '-v' . $n . '.jpg';
    if (in_array($fname, $subFiles, true)) continue;
    if (@copy($srcCache[$code], IMG_PROD . $fname)) { $subFiles[] = $fname; $seenHashes[$h] = true; echo "  ✓ galería padre += $fname\n"; }
}
$subFiles = array_values(array_unique($subFiles));
$json = $m->real_escape_string(json_encode($subFiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$m->query("UPDATE products SET products_subimages='$json' WHERE products_id=$pid");
echo "\nproducts_subimages = $json\n";
echo "OK.\n";
