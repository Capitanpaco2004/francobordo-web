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

$pid  = (int)($_POST['products_id'] ?? 0);
$oid  = (int)($_POST['oid'] ?? 0);
$vid  = (int)($_POST['vid'] ?? 0);
$op   = (string)($_POST['op'] ?? '');
$slot = (int)($_POST['slot'] ?? 1);   // 2 imagenes por valor: slot 1 o 2

if ($pid <= 0 || $oid <= 0 || $vid <= 0)
	amAttrImgFail('Parametros invalidos');
if ($slot !== 1 && $slot !== 2)
	amAttrImgFail('Slot invalido');

// Clave de combinacion de una sola opcion: "oid-vid" (segura, solo enteros)
$combi = $oid . '-' . $vid;
$dir   = __DIR__ . '/../../images/atributos/';

if (!is_dir($dir))
	@mkdir($dir, 0755, true);

// Lee el value actual y lo parsea en slots: [1 => fichero|'', 2 => fichero|''].
// Ficheros nuevos llevan sufijo -1/-2; los legacy sin sufijo se asignan al slot 1.
$amReadSlots = function () use ($pid, $combi, $dir) {
	$slots = array(1 => '', 2 => '');
	$res = tep_db_query('SELECT value FROM products_attributes_actions WHERE products_id = "' . (int)$pid . '" AND products_attributes = "' . $combi . '" AND action = "change_image"');
	if (tep_db_num_rows($res) > 0) {
		$row = tep_db_fetch_array($res);
		foreach (explode('[dxsepare]', (string)$row['value']) as $f) {
			$f = basename(trim($f));
			if ($f === '') continue;
			if (preg_match('/-([12])\.[^.]+$/', $f, $mm))      $slots[(int)$mm[1]] = $f;
			elseif ($slots[1] === '')                          $slots[1] = $f;
			else                                               $slots[2] = $f;
		}
	}
	return $slots;
};

// Construye el value compactado (sin huecos) en orden de slot: f1[dxsepare]f2
$amBuildValue = function ($slots) {
	$present = array();
	if ($slots[1] !== '') $present[] = $slots[1];
	if ($slots[2] !== '') $present[] = $slots[2];
	return implode('[dxsepare]', $present);
};

// Persiste el value (upsert) o borra la fila si queda vacio
$amPersist = function ($value) use ($pid, $combi) {
	$res = tep_db_query('SELECT id FROM products_attributes_actions WHERE products_id = "' . (int)$pid . '" AND products_attributes = "' . $combi . '" AND action = "change_image"');
	$exists = (tep_db_num_rows($res) > 0);
	if ($value === '') {
		if ($exists)
			tep_db_query('DELETE FROM products_attributes_actions WHERE products_id = "' . (int)$pid . '" AND products_attributes = "' . $combi . '" AND action = "change_image"');
		return;
	}
	if ($exists)
		tep_db_perform('products_attributes_actions', array('value' => $value), 'update', 'products_id = "' . (int)$pid . '" AND products_attributes = "' . $combi . '" AND action = "change_image"');
	else
		tep_db_perform('products_attributes_actions', array(
			'products_id'         => $pid,
			'products_attributes' => $combi,
			'value'               => $value,
			'action'              => 'change_image',
		));
};

// ---- Borrar (una imagen de un slot) ----
if ($op === 'clear') {
	$slots = $amReadSlots();
	if ($slots[$slot] !== '' && is_file($dir . $slots[$slot]))
		@unlink($dir . $slots[$slot]);
	$slots[$slot] = '';
	$amPersist($amBuildValue($slots));
	amAttrImgOut(array('ok' => true, 'slot' => $slot));
}

// ---- Guardar (una imagen en un slot) ----
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

	$slots = $amReadSlots();

	// Borramos el fichero previo de ESTE slot (si lo habia, aunque tuviera otra extension)
	if ($slots[$slot] !== '' && is_file($dir . $slots[$slot]))
		@unlink($dir . $slots[$slot]);

	$fname = 'ai_' . $pid . '-' . $oid . '-' . $vid . '-' . $slot . '.' . $ext;
	if (file_put_contents($dir . $fname, $bin) === false)
		amAttrImgFail('No se pudo guardar el fichero');

	$slots[$slot] = $fname;
	$amPersist($amBuildValue($slots));

	amAttrImgOut(array('ok' => true, 'slot' => $slot, 'thumb' => '../images/atributos/' . rawurlencode($fname) . '?v=' . time()));
}

amAttrImgFail('Operacion desconocida');
