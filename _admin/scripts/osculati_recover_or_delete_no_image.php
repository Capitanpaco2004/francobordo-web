<?php
/**
 * Para cada producto Osculati sin imagen:
 *   1) Intenta descargar imagen probando candidatos (img/suggested_img y variantes con/sin guiones bajos).
 *   2) Si la encuentra → guarda en images/productos/<slug>-<pid>.jpg y actualiza products_image.
 *   3) Si NO la encuentra → BORRA el producto y todas sus dependencias en transacción.
 *
 * Modo DRY (default): solo lista lo que haría. Modo EXECUTE aplica.
 */
include '/home/francobordo/public_html/includes/configure.php';
$m = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
$m->set_charset('utf8');

$dry = !in_array('EXECUTE', $argv ?? [], true);

const OSC_USER = 'C54293';
const OSC_PASS = '0XxBkWSb';
const OSC_FTP_BASE = 'ftp://fw.osculati.it/';
const OSC_IMG_FOLDER = 'IMG/800/';
const IMG_ABS_DIR = '/home/francobordo/public_html/images/productos/';

function downloadImage($imgName, $targetPath) {
	if ($imgName === '') return false;
	$remotePath = OSC_IMG_FOLDER . rawurlencode($imgName);
	$ch = curl_init(OSC_FTP_BASE . $remotePath);
	$fp = fopen($targetPath, 'wb');
	curl_setopt($ch, CURLOPT_USERPWD, OSC_USER . ':' . OSC_PASS);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	$ok = curl_exec($ch);
	fclose($fp);
	if (!$ok || filesize($targetPath) < 100) { @unlink($targetPath); return false; }
	return true;
}

function imageCandidates($img, $sug) {
	$out = [];
	$add = function($name) use (&$out) {
		$name = trim((string) $name);
		if ($name === '') return;
		if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $name)) $name .= '.jpg';
		if (!in_array($name, $out, true)) $out[] = $name;
	};
	$add($img); $add($sug);
	$add(str_replace(' ', '_', $img));
	$add(str_replace('_', ' ', $sug));
	return $out;
}

function tryFallbacks(array $candidates, $targetPath) {
	foreach ($candidates as $c) if (downloadImage($c, $targetPath)) return $c;
	return '';
}

function slugify($s) {
	$s = mb_strtolower($s, 'UTF-8');
	$s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
	$s = preg_replace('/[^a-z0-9]+/', '-', $s);
	return trim($s, '-') ?: 'producto';
}

// 1) Descargar/parsear Code2Ser
$codeFile = '/tmp/Code2Ser.txt';
if (!file_exists($codeFile) || filesize($codeFile) < 1000000) {
	echo "Descargando ENG/Code2Ser.txt...\n";
	$ch = curl_init(OSC_FTP_BASE . 'ENG/Code2Ser.txt');
	$fp = fopen($codeFile, 'wb');
	curl_setopt($ch, CURLOPT_USERPWD, OSC_USER . ':' . OSC_PASS);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_TIMEOUT, 120);
	curl_exec($ch);
	fclose($fp);
}
$raw = file_get_contents($codeFile);
$raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE');
$codeMap = [];
foreach (preg_split("/\r?\n/", $raw) as $line) {
	$cols = explode("\t", $line);
	if (count($cols) < 4) continue;
	$base = trim($cols[0]);
	if ($base === '') continue;
	$codeMap[$base] = ['suggested_img' => trim($cols[2]), 'img' => trim($cols[3])];
}
echo "Code2Ser: " . count($codeMap) . " entradas\n";

// 2) Productos osculati sin imagen
$r = $m->query("SELECT p.products_id, p.products_model, pds.products_name FROM products p LEFT JOIN products_description pds ON pds.products_id=p.products_id AND pds.language_id=3 WHERE p.products_import_origin LIKE 'osculati%' AND (p.products_image IS NULL OR p.products_image='')");
$candidates = [];
while ($row = $r->fetch_assoc()) $candidates[] = $row;
echo "\nProductos osculati sin imagen: " . count($candidates) . "\n\n";

$recovered = []; $toDelete = [];
foreach ($candidates as $c) {
	$pid = (int) $c['products_id'];
	$base = $c['products_model'];
	if (!isset($codeMap[$base])) {
		echo "  pid=$pid sku=$base → NO ENTRADA EN Code2Ser → DELETE\n";
		$toDelete[] = $c; continue;
	}
	$cands = imageCandidates($codeMap[$base]['img'], $codeMap[$base]['suggested_img']);
	if (empty($cands)) {
		echo "  pid=$pid sku=$base → SIN CANDIDATOS DE IMAGEN → DELETE\n";
		$toDelete[] = $c; continue;
	}
	$tmp = '/tmp/osc-recover-' . $pid . '.jpg';
	$winner = tryFallbacks($cands, $tmp);
	if ($winner === '') {
		echo "  pid=$pid sku=$base → IMG NO DESCARGABLE (probados: " . implode(', ', $cands) . ") → DELETE\n";
		$toDelete[] = $c; continue;
	}
	echo "  pid=$pid sku=$base → RECUPERABLE con '$winner'\n";
	$c['_winner'] = $winner;
	$c['_tmp'] = $tmp;
	$recovered[] = $c;
}

echo "\nRESUMEN: " . count($recovered) . " recuperables, " . count($toDelete) . " a borrar\n";

if ($dry) {
	echo "\n[DRY] no se aplica nada. Pasa EXECUTE para aplicar.\n";
	// limpiar tmp files
	foreach ($recovered as $c) @unlink($c['_tmp']);
	exit;
}

// 3) Aplicar recuperables
echo "\n--- Aplicando recuperaciones ---\n";
foreach ($recovered as $c) {
	$pid = (int) $c['products_id'];
	$slug = slugify((string) $c['products_name']);
	$ext = strtolower(pathinfo($c['_winner'], PATHINFO_EXTENSION)) ?: 'jpg';
	$finalName = "$slug-$pid.$ext";
	$finalPath = IMG_ABS_DIR . $finalName;
	if (@rename($c['_tmp'], $finalPath)) {
		$qn = $m->real_escape_string($finalName);
		if ($m->query("UPDATE products SET products_image='$qn' WHERE products_id=$pid")) {
			echo "  OK pid=$pid → $finalName\n";
		} else {
			echo "  ERR UPDATE pid=$pid: " . $m->error . "\n";
		}
	} else {
		echo "  ERR rename pid=$pid\n";
	}
}

// 4) Aplicar borrado
if (!empty($toDelete)) {
	$ids = implode(',', array_map(fn($c) => (int) $c['products_id'], $toDelete));
	$rs = $m->query("SELECT COUNT(*) c FROM orders_products WHERE products_id IN ($ids)");
	$sales = (int) $rs->fetch_assoc()['c'];
	if ($sales > 0) { echo "\nABORT borrado: $sales líneas de pedido referencian estos productos\n"; exit; }

	echo "\n--- Borrando " . count($toDelete) . " productos sin imagen recuperable ---\n";
	$m->begin_transaction();
	try {
		$tables = [
			'products_description','products_to_categories','products_attributes',
			'products_attributes_groups','products_stock','products_groups','specials',
			'reviews','customers_basket','products_notifications',
		];
		foreach ($tables as $t) {
			$check = $m->query("SHOW TABLES LIKE '$t'");
			if (!$check || $check->num_rows === 0) continue;
			$res = $m->query("DELETE FROM $t WHERE products_id IN ($ids)");
			if (!$res) throw new Exception("DELETE $t: " . $m->error);
			echo "  $t: " . $m->affected_rows . " filas\n";
		}
		$res = $m->query("DELETE FROM products WHERE products_id IN ($ids)");
		if (!$res) throw new Exception("DELETE products: " . $m->error);
		echo "  products: " . $m->affected_rows . " filas\n";
		$m->commit();
		echo "=== COMMIT OK ===\n";
	} catch (Exception $e) {
		$m->rollback();
		echo "ROLLBACK: " . $e->getMessage() . "\n";
	}
}
