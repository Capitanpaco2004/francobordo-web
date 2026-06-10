<?php
/*
  amAttrName.php — editar el nombre de un valor de atributo (products_options_values_name)
  en el idioma activo del attribute manager (selector de banderas ES/EN).

  Los valores de atributo son globales: el mismo products_options_values_id puede estar
  usado por varios productos. op=info devuelve el desglose de productos afectados (se muestra
  al entrar en modo edicion). op=save escribe; mantiene la guarda needConfirm por si se llama
  sin confirmar desde otro flujo.

  Auth: misma puerta que attributeManager.php (admin logueado + token de sesion del AM).
*/

// chdir('../') deja el cwd en _admin/ (igual que attributeManager.php / amAttrImage.php)
chdir('../');

require_once('includes/application_top.php');
require_once('attributeManager/includes/attributeManagerSessionFunctions.inc.php');
require_once('attributeManager/classes/attributeManagerConfig.class.php');
require_once('attributeManager/classes/stopDirectAccess.class.php');

stopDirectAccess::checkAuthorisation(AM_SESSION_VALID_INCLUDE);

header('Content-Type: application/json; charset=' . CHARSET);

function amNameOut($a) { echo json_encode($a); exit; }
function amNameFail($m) { amNameOut(array('ok' => false, 'error' => $m)); }

$vid       = (int)($_POST['vid'] ?? 0);
$op        = (string)($_POST['op'] ?? '');
$confirmed = (int)($_POST['confirmed'] ?? 0);

if ($vid <= 0)
	amNameFail('Parametros invalidos');

// Idioma del valor a editar. PRIORIDAD: el idioma que el cliente envia (data-lang del input),
// que es EXACTAMENTE el idioma en que se renderizo el nombre -> el guardado coincide con lo que
// se ve/edita. Esto evita el desajuste por la sesion de idioma de la AM (amGetSessionVariable usa
// $GLOBALS, que se puebla distinto entre attributeManager.php y este endpoint).
// Validamos contra la tabla languages. Fallback: sesion AM -> AM_DEFAULT_LANGUAGE_ID.
$lang = 0;
$postLang = (int)($_POST['lang'] ?? 0);
if ($postLang > 0) {
	$chkLang = tep_db_query('SELECT languages_id FROM languages WHERE languages_id = "' . $postLang . '" LIMIT 1');
	if (tep_db_num_rows($chkLang) > 0)
		$lang = $postLang;
}
if ($lang <= 0) {
	$lang = amGetSessionVariable(AM_SESSION_CURRENT_LANG_VAR_NAME);
	if ($lang === false || (int)$lang <= 0)
		$lang = AM_DEFAULT_LANGUAGE_ID;
}
$lang = (int)$lang;

// Cuantos productos usan este valor (blast radius del cambio)
function amNameProductCount($vid) {
	$res = tep_db_query('SELECT COUNT(DISTINCT products_id) AS n FROM products_attributes WHERE options_values_id = "' . (int)$vid . '"');
	$row = tep_db_fetch_array($res);
	return (int)$row['n'];
}

// ---- Info: lista de productos afectados (para mostrar al entrar en edicion) ----
if ($op === 'info') {
	$nProd = amNameProductCount($vid);
	$cap   = 50;
	$list  = array();
	$res = tep_db_query(
		'SELECT pa.products_id AS pid, pd.products_name AS pname
		 FROM products_attributes pa
		 LEFT JOIN products_description pd ON pd.products_id = pa.products_id AND pd.language_id = "' . $lang . '"
		 WHERE pa.options_values_id = "' . $vid . '"
		 GROUP BY pa.products_id
		 ORDER BY pname
		 LIMIT ' . ($cap + 1)
	);
	while ($r = tep_db_fetch_array($res))
		$list[] = array('id' => (int)$r['pid'], 'name' => (string)$r['pname']);

	$more = 0;
	if (count($list) > $cap) {
		$list = array_slice($list, 0, $cap);
		$more = $nProd - $cap;
	}

	amNameOut(array('ok' => true, 'products' => $nProd, 'list' => $list, 'more' => $more));
}

// ---- Save ----
if ($op === 'save') {
	$name = trim((string)($_POST['name'] ?? ''));
	if ($name === '')
		amNameFail('El nombre no puede estar vacio');
	if (mb_strlen($name) > 255)
		amNameFail('Nombre demasiado largo (maximo 255 caracteres)');

	$nProd = amNameProductCount($vid);

	if ($nProd > 1 && $confirmed !== 1)
		amNameOut(array('ok' => false, 'needConfirm' => true, 'products' => $nProd));

	// Update / insert del nombre en el idioma activo
	$chk = tep_db_query('SELECT products_options_values_id FROM products_options_values WHERE products_options_values_id = "' . $vid . '" AND language_id = "' . $lang . '" LIMIT 1');
	if (tep_db_num_rows($chk) > 0)
		tep_db_query('UPDATE products_options_values SET products_options_values_name = "' . tep_db_input($name) . '" WHERE products_options_values_id = "' . $vid . '" AND language_id = "' . $lang . '"');
	else
		tep_db_query('INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name) VALUES ("' . $vid . '", "' . $lang . '", "' . tep_db_input($name) . '")');

	amNameOut(array('ok' => true, 'name' => $name, 'products' => $nProd, 'language_id' => $lang));
}

amNameFail('Operacion desconocida');
