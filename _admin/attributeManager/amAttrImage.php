<?php
/*
  amAttrImage.php — Opcion 3: imagen principal por valor de atributo.

  Sube o borra la imagen asociada a un valor de atributo y la persiste como
  accion "change_image" en products_attributes_actions (clave products_attributes = "oid-vid").
  El frontend (option.class.php -> array_option_action -> app.js -> product_info.php?a=chng_image)
  ya consume esa fila y cambia la galeria de la ficha al seleccionar el valor.

  Auth: misma puerta que attributeManager.php (admin logueado + token de sesion del AM).
*/

// chdir('../') deja el cwd en _admin/ (igual que attributeManager.php)
chdir('../');

require_once('includes/application_top.php');
require_once('attributeManager/includes/attributeManagerSessionFunctions.inc.php');
require_once('attributeManager/classes/attributeManagerConfig.class.php');
require_once('attributeManager/classes/stopDirectAccess.class.php');

// Solo accesible desde la pagina del attribute manager (token de sesion)
stopDirectAccess::checkAuthorisation(AM_SESSION_VALID_INCLUDE);

header('Content-Type: application/json; charset=' . CHARSET);

function amAttrImgOut($arr) { echo json_encode($arr); exit; }
function amAttrImgFail($msg) { amAttrImgOut(array('ok' => false, 'error' => $msg)); }

$pid = (int)($_POST['products_id'] ?? 0);
$oid = (int)($_POST['oid'] ?? 0);
$vid = (int)($_POST['vid'] ?? 0);
$op  = (string)($_POST['op'] ?? '');

if ($pid <= 0 || $oid <= 0 || $vid <= 0)
	amAttrImgFail('Parametros invalidos');

// Clave de combinacion de una sola opcion: "oid-vid" (segura, solo enteros)
$combi = $oid . '-' . $vid;
$dir   = __DIR__ . '/../../images/atributos/';

if (!is_dir($dir))
	@mkdir($dir, 0755, true);

// Borra los ficheros referenciados por la fila actual de esta clave
$amDeleteExistingFiles = function () use ($pid, $combi, $dir) {
	$res = tep_db_query('SELECT value FROM products_attributes_actions WHERE products_id = "' . (int)$pid . '" AND products_attributes = "' . $combi . '" AND action = "change_image"');
	if (tep_db_num_rows($res) > 0) {
		$row = tep_db_fetch_array($res);
		foreach (explode('[dxsepare]', (string)$row['value']) as $f) {
			$f = basename(trim($f));
			if ($f !== '' && is_file($dir . $f))
				@unlink($dir . $f);
		}
	}
};

// ---- Borrar ----
if ($op === 'clear') {
	$amDeleteExistingFiles();
	tep_db_query('DELETE FROM products_attributes_actions WHERE products_id = "' . (int)$pid . '" AND products_attributes = "' . $combi . '" AND action = "change_image"');
	amAttrImgOut(array('ok' => true));
}

// ---- Guardar ----
if ($op === 'save') {
	$data = (string)($_POST['image'] ?? '');

	if (!preg_match('#^data:image/(jpe?g|png|webp|gif);base64,#i', $data, $m))
		amAttrImgFail('Formato no permitido (jpg, png, webp o gif)');

	$ext = strtolower($m[1]);
	if ($ext === 'jpeg') $ext = 'jpg';

	$bin = base64_decode(preg_replace('#^data:image/[^;]+;base64,#i', '', $data), true);
	if ($bin === false)
		amAttrImgFail('Codificacion base64 invalida');

	if (strlen($bin) > 3 * 1024 * 1024)
		amAttrImgFail('La imagen es demasiado grande (maximo 3 MB)');

	if (@getimagesizefromstring($bin) === false)
		amAttrImgFail('El archivo no es una imagen valida');

	$amDeleteExistingFiles();

	$fname = 'ai_' . $pid . '-' . $oid . '-' . $vid . '.' . $ext;
	if (file_put_contents($dir . $fname, $bin) === false)
		amAttrImgFail('No se pudo guardar el fichero');

	// Upsert de la fila change_image
	$res = tep_db_query('SELECT id FROM products_attributes_actions WHERE products_id = "' . (int)$pid . '" AND products_attributes = "' . $combi . '" AND action = "change_image"');
	if (tep_db_num_rows($res) > 0)
		tep_db_perform('products_attributes_actions', array('value' => $fname), 'update', 'products_id = "' . (int)$pid . '" AND products_attributes = "' . $combi . '" AND action = "change_image"');
	else
		tep_db_perform('products_attributes_actions', array(
			'products_id'         => $pid,
			'products_attributes' => $combi,
			'value'               => $fname,
			'action'              => 'change_image',
		));

	amAttrImgOut(array('ok' => true, 'thumb' => '../images/atributos/' . rawurlencode($fname) . '?v=' . time()));
}

amAttrImgFail('Operacion desconocida');
