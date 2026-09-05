<?php
require 'includes/application_top.php';
require_once DIR_FS_DOCUMENT_ROOT . 'includes/vendor/autoload.php';
use phpseclib3\Net\SFTP;

set_time_limit(0);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', -1);
ini_set('max_input_time', -1);

const SEIMI_CSV          = '/home/francobordo/public_html/import/feed/SEIMI/SEIMI_products_francobordo.csv';
const SEIMI_SFTP_HOST    = 'stftpamgprd01.blob.core.windows.net'; // SFTP Alliance Marine (Azure Blob)
const SEIMI_SFTP_PORT    = 22;
const SEIMI_SFTP_USER    = 'stftpamgprd01.amghubfrancobordo';
const SEIMI_SFTP_PASS    = 'lkYgzugus0nD0bQi/smYJLzSMY2RyFBS';
const SEIMI_SFTP_REMOTE  = 'products/SEIMI_products_francobordo.csv';
define('IMG_ABS_DIR',     dirname(dirname(__FILE__)) . '/images/productos/');
const NEW_CATEGORY_ID   = 1969; // "SEIMI Nuevos" (parent 0, status 0, creada 2026-07-07)
const TAX_CLASS_IVA21   = 1;
const LANG_ID_ES        = 3;
const LANG_ID_EN        = 1;
const G1_GROUP_ID       = 1;
const G1_FLOOR_FACTOR   = 1.10; // piso: G1 ≥ cost × 1.10
const PRODUCT_NAME_MAX  = 80;
const IMG_HTTP_TIMEOUT  = 12;
const MAX_SUBIMAGES     = 4;    // galería: subimágenes máximas por producto (Picture_2/3 + additional_images)
const ORIGIN_FLAG       = 'seimi';
const EAN_INTERNAL_PREFIX = 28; // prefijo GS1 in-store compartido (id-based, sin colisión — ver memoria ean_internos)
const VAT_RATE          = 0.21; // IVA 21% — usado para redondeo PVP a múltiplos de 0.05 con IVA
const VARIANT_OPTION_ID = 3;    // "Modelo" (mismo que Osculati/Trem/FNI)
const LCP_VARIANT_RATIO = 0.30; // umbral LCP/min(name) para considerar variantes legítimas
const DEFAULT_BRAND     = 'Generico'; // Brand vacío → asignar este fabricante (id=29 en BD)

// Diccionario FR→[ES,EN] para etiquetas de variante (colores/materiales/calificativos).
// Determinista (sin LLM). Word-boundary con /iu; frases multi-palabra ANTES que sus partes.
const LABEL_DICT = [
	'bleu marine' => ['azul marino', 'navy blue'],
	'gris clair'  => ['gris claro', 'light grey'],
	'gris foncé'  => ['gris oscuro', 'dark grey'],
	'jeu de'      => ['juego de', 'set of'],
	'lot de'      => ['lote de', 'set of'],
	'noires' => ['negras', 'black'],   'noire' => ['negra', 'black'],   'noirs' => ['negros', 'black'],   'noir' => ['negro', 'black'],
	'rouges' => ['rojos', 'red'],      'rouge' => ['rojo', 'red'],
	'blanches' => ['blancas', 'white'],'blanche' => ['blanca', 'white'],'blancs' => ['blancos', 'white'], 'blanc' => ['blanco', 'white'],
	'bleues' => ['azules', 'blue'],    'bleue' => ['azul', 'blue'],     'bleus' => ['azules', 'blue'],    'bleu' => ['azul', 'blue'],
	'vertes' => ['verdes', 'green'],   'verte' => ['verde', 'green'],   'verts' => ['verdes', 'green'],   'vert' => ['verde', 'green'],
	'jaunes' => ['amarillos', 'yellow'], 'jaune' => ['amarillo', 'yellow'],
	'gris' => ['gris', 'grey'], 'orange' => ['naranja', 'orange'], 'marron' => ['marrón', 'brown'],
	'violette' => ['violeta', 'purple'], 'violet' => ['violeta', 'purple'], 'rose' => ['rosa', 'pink'],
	'transparente' => ['transparente', 'clear'], 'transparent' => ['transparente', 'clear'],
	'chromée' => ['cromada', 'chrome'], 'chromé' => ['cromado', 'chrome'],
	'dorée' => ['dorada', 'gold'], 'doré' => ['dorado', 'gold'],
	'argentée' => ['plateada', 'silver'], 'argenté' => ['plateado', 'silver'],
	'inox' => ['inox', 'stainless'], 'laiton' => ['latón', 'brass'], 'acier' => ['acero', 'steel'],
	'aluminium' => ['aluminio', 'aluminium'], 'plastique' => ['plástico', 'plastic'],
	'caoutchouc' => ['goma', 'rubber'], 'cuivre' => ['cobre', 'copper'],
	'zinguée' => ['zincada', 'galvanised'], 'zingué' => ['zincado', 'galvanised'],
	'galvanisée' => ['galvanizada', 'galvanised'], 'galvanisé' => ['galvanizado', 'galvanised'],
	'mâle' => ['macho', 'male'], 'femelle' => ['hembra', 'female'],
	'coudée' => ['acodada', 'elbow'], 'coudé' => ['acodado', 'elbow'], 'courbe' => ['curvado', 'curved'],
	'droit' => ['recto', 'straight'], 'droite' => ['derecha', 'right'], 'gauche' => ['izquierda', 'left'],
	'courte' => ['corta', 'short'], 'court' => ['corto', 'short'], 'longue' => ['larga', 'long'], 'long' => ['largo', 'long'],
	'petite' => ['pequeña', 'small'], 'petit' => ['pequeño', 'small'], 'grande' => ['grande', 'large'], 'grand' => ['grande', 'large'],
	'simple' => ['simple', 'single'], 'double' => ['doble', 'double'], 'triple' => ['triple', 'triple'],
	'avec' => ['con', 'with'], 'sans' => ['sin', 'without'], 'pour' => ['para', 'for'],
	'paire' => ['par', 'pair'], 'pièces' => ['piezas', 'pieces'], 'pièce' => ['pieza', 'piece'], 'unité' => ['unidad', 'unit'],
	'mètres' => ['metros', 'metres'], 'mètre' => ['metro', 'metre'],
	'rouleau' => ['rollo', 'roll'], 'boîte' => ['caja', 'box'], 'sachet' => ['bolsa', 'bag'],
	'dévidoir' => ['dispensador', 'dispenser'], 'bobine' => ['bobina', 'spool'], 'coffret' => ['estuche', 'case'],
	'ouverte' => ['abierta', 'open'], 'ouvert' => ['abierto', 'open'], 'fermée' => ['cerrada', 'closed'], 'fermé' => ['cerrado', 'closed'],
	'claire' => ['clara', 'light'], 'clair' => ['claro', 'light'], 'foncée' => ['oscura', 'dark'], 'foncé' => ['oscuro', 'dark'],
	'mate' => ['mate', 'matt'], 'mat' => ['mate', 'matt'], 'brillante' => ['brillante', 'gloss'], 'brillant' => ['brillante', 'gloss'],
	'équipée' => ['equipada', 'equipped'], 'équipé' => ['equipado', 'equipped'], 'brute' => ['bruta', 'bare'], 'brut' => ['bruto', 'bare'],
];

/** Traduce una etiqueta de variante con LABEL_DICT. $to: 0=ES, 1=EN. Palabras desconocidas se conservan. */
function seimiTranslateLabel($label, $to) {
	$out = (string) $label;
	foreach (LABEL_DICT as $fr => $tr) {
		$out = preg_replace('/(?<![\p{L}])' . preg_quote($fr, '/') . '(?![\p{L}])/iu', $tr[$to], $out);
	}
	return $out;
}

const LLM_LABEL_PROMPT_ES = 'Traduce al español de España esta etiqueta corta de variante de un producto náutico (viene en francés o inglés). Conserva códigos de modelo, referencias, números y unidades EXACTAMENTE igual. Responde SOLO con la traducción, sin comentarios.';
const LLM_LABEL_PROMPT_EN = 'Translate this short variant label of a nautical product into English (source is French or Spanish). Keep model codes, references, numbers and units EXACTLY unchanged. Reply ONLY with the translation, no comments.';

/** ¿La etiqueta contiene palabras "de texto" (≥4 letras) fuera de LABEL_DICT? → necesita LLM. */
function seimiLabelNeedsLlm($label) {
	if (!preg_match_all('/\p{L}{4,}/u', (string) $label, $m)) return false;
	foreach ($m[0] as $w) {
		if (!isset(LABEL_DICT[mb_strtolower($w, 'UTF-8')])) return true;
	}
	return false;
}

/** Etiqueta final por idioma: diccionario determinista + fallback LLM (cacheado por run)
 *  cuando queda texto libre que el diccionario no cubre ("brut équipé"…). */
function seimiTranslateLabelFull($label, $to, $useLlm) {
	$dict = seimiTranslateLabel($label, $to);
	if (!$useLlm || !seimiLabelNeedsLlm($label)) return $dict;
	if (!isset($GLOBALS['seimiLabelLlmCache'])) $GLOBALS['seimiLabelLlmCache'] = [];
	$key = $to . '|' . $label;
	if (isset($GLOBALS['seimiLabelLlmCache'][$key])) return $GLOBALS['seimiLabelLlmCache'][$key];
	$out = llmTranslate($label, 2, max(24, mb_strlen($label, 'UTF-8') * 3), $to === 0 ? LLM_LABEL_PROMPT_ES : LLM_LABEL_PROMPT_EN);
	$res = $out !== '' ? $out : $dict;
	$GLOBALS['seimiLabelLlmCache'][$key] = $res;
	return $res;
}

// Marcas para las que NO se prefija el nombre del fabricante al título (UPPERCASE):
const BRAND_NO_PREFIX = ['GENERICO'];

// Brands a EXCLUIR del importador (case-insensitive): marcas con importador propio
// en francobordo (decisión usuario 2026-07-07). Ampliable.
const BRAND_BLACKLIST = [
	'VETUS', 'DOMETIC', 'TECNOSEAL', 'GARMIN', 'JOBE', 'LALIZAS',
];

// Universos del feed (FR, uppercase) → nombre ES de la subcategoría de staging.
// El nombre EN de la subcategoría sale de Universe_EN (title case).
const UNIVERSE_ES = [
	'EAU À BORD'            => 'Agua a Bordo',
	'ÉLECTRICITÉ'           => 'Electricidad',
	'ÉCLAIRAGE ET SIGNALISATION' => 'Iluminación y Señalización',
	'ENVIRONNEMENT MOTEUR ET ACCESSOIRES' => 'Entorno del Motor',
	'ÉQUIPEMENTS DE PONT ET ACCESSOIRES' => 'Equipamiento de Cubierta',
	'MÉCANIQUE ET PIÈCES DÉTACHÉES' => 'Mecánica y Recambios',
	'QUINCAILLERIE ET ACCASTILLAGE' => 'Herrajes y Accesorios',
	'CONFORT'               => 'Confort a Bordo',
	'PRODUITS D\'ENTRETIEN, NETTOYAGE ET PEINTURE' => 'Mantenimiento y Pintura',
	'SÉCURITÉ'              => 'Seguridad',
	'ÉQUIPEMENTS DE NAVIGATION, ÉLECTRONIQUE' => 'Electrónica y Navegación',
	'ANCRE ET MOUILLAGE'    => 'Fondeo',
	'CONFORT À BORD'        => 'Confort a Bordo',
	'ÉLECTRICITÉ & ÉNERGIE' => 'Electricidad',
	'COMMANDES & DIRECTIONS'=> 'Mandos y Dirección',
	'ENVIRONNEMENT MOTEUR'  => 'Entorno del Motor',
	'ÉCLAIRAGE & SIGNALISATION' => 'Iluminación y Señalización',
	'PASSERELLE & NAVIGATION' => 'Pasarela y Cubierta',
	'POMPES MARINE'         => 'Bombas y Agua a Bordo',
	'SPORT ET LOISIRS'      => 'Ocio',
	'ÉQUIPEMENTS DE PONT'   => 'Equipamiento de Cubierta',
	'PROPULSION'            => 'Hélices de Maniobra',
	'ANODES & ENTRETIEN'    => 'Ánodos',
];
const FALLBACK_SUBCAT = 'Varios';

// Traducción FR/EN→ES vía LLM (mismo endpoint que Osculati/Trem/FNI)
const LLM_URL    = 'http://217.127.199.171:28001/v1/chat/completions';
const LLM_MODEL  = 'qwen36-sakamaki-nvfp4';
const LLM_PROMPT = 'Eres un traductor profesional de francés o inglés a español especializado en productos náuticos, marinos y de pesca. Usa terminología técnica náutica precisa en español de España. Glosario náutico de referencia (FR/EN↔ES): bout/cordage/rope/line=cabo, manille/shackle=grillete, taquet/cleat=cornamusa, chaumard/fairlead=pasacable, guindeau/windlass=molinete, propulseur/thruster=hélice de maniobra, cale/bilge=sentina, panneau de pont/boccaporto/hatch=escotilla, pare-battage/fender=defensa, amarrage/mouillage/mooring=amarre o fondeo, ancre/anchor=ancla, chaîne/chain=cadena, coque/hull=casco, pont/deck=cubierta, gouvernail/safran/rudder=timón, passe-coque/through-hull=pasacascos, vanne/seacock=grifo de fondo, acier inoxydable/inox/stainless steel=acero inoxidable, galvanisé/galvanized=galvanizado, hors-bord/outboard=fueraborda, échappement/exhaust=escape, coude=codo, poulie/block=motón, écoute/sheet=escota, winch=winche, davier/bow roller=puntera de proa, réservoir/tank=depósito, pompe/pump=bomba, robinet/tap=grifo, tuyau/hose=manguera, feu de navigation/navigation light=luz de navegación, gilet/lifejacket=chaleco salvavidas, radeau/liferaft=balsa salvavidas. Usa siempre el sentido náutico; no lo traduzcas como términos de otros dominios. Texto plano, conserva <br> si los hay como saltos de línea. Responde SOLO con la traducción, sin comentarios ni explicaciones.';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$max    = isset($_POST['max']) ? (int) $_POST['max'] : (isset($_GET['max']) ? (int) $_GET['max'] : 0);
$dryRun = isset($_POST['dry_run']) || isset($_GET['dry_run']) || $action === 'dry_run';
$skipTranslation = isset($_POST['skip_translation']) || isset($_GET['skip_translation']);
$skipVariants    = isset($_POST['skip_variants'])    || isset($_GET['skip_variants']);
$skipDownload    = isset($_POST['skip_download'])    || isset($_GET['skip_download']);
$selectedBrand   = trim((string) ($_POST['brand'] ?? $_GET['brand'] ?? 'all'));
$selectedUniv    = trim((string) ($_POST['universe'] ?? $_GET['universe'] ?? 'all'));
$onlyCodesRaw    = trim((string) ($_POST['codes'] ?? $_GET['codes'] ?? ''));
$onlyCodes       = [];
if ($onlyCodesRaw !== '') {
	foreach (preg_split('/[\s,;]+/', $onlyCodesRaw) as $c) {
		$c = trim($c);
		if ($c !== '') $onlyCodes[$c] = true;
	}
}
$maxOverridden = 0;
if (!empty($onlyCodes) && $max > 0) { $maxOverridden = $max; $max = 0; }

function logMsg($msg) {
	$line = '[' . date('H:i:s') . '] ' . $msg . "\n";
	echo '<pre style="margin:0;padding:2px 8px;border-bottom:1px solid #eee;white-space:pre-wrap;overflow-wrap:break-word;word-break:break-word;font-family:monospace;font-size:12px;">' . htmlspecialchars($line) . '</pre>';
	@flush();
}

/** Parsea números del feed SEIMI (decimal con punto; tolera coma europea). Null si no numérico. */
function seimiParseNum($v) {
	$v = trim((string) $v);
	if ($v === '') return null;
	if (strpos($v, ',') !== false && strpos($v, '.') === false) $v = str_replace(',', '.', $v);
	$v = str_replace(' ', '', $v);
	return is_numeric($v) ? (float) $v : null;
}

/** Aplana tablas de especificaciones del feed a líneas "Etiqueta: valor".
 *  El feed trae <table> con celdas separadoras vacías (<td><br></td>) y doble valor
 *  métrico + imperial: nos quedamos con etiqueta + PRIMER valor no vacío (el métrico);
 *  la conversión imperial se omite (usuario 2026-07-08: "Cilindrada: 70 cm³/t"). */
function seimiFlattenSpecTables($html) {
	return preg_replace_callback('#<table\b[^>]*>.*?</table>#is', function ($m) {
		$out = [];
		if (preg_match_all('#<tr\b[^>]*>(.*?)</tr>#is', $m[0], $trs)) {
			foreach ($trs[1] as $tr) {
				if (!preg_match_all('#<t[dh]\b[^>]*>(.*?)</t[dh]>#is', $tr, $tds)) continue;
				$cells = [];
				foreach ($tds[1] as $c) {
					$c = strip_tags(preg_replace('#<\s*br\s*/?\s*>#i', ' ', $c));
					$c = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($c, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
					if ($c !== '') $cells[] = $c;
				}
				if (empty($cells)) continue;
				$out[] = count($cells) === 1 ? $cells[0] : ($cells[0] . ': ' . $cells[1]);
			}
		}
		return empty($out) ? '' : '<p>' . implode('<br>', $out) . '</p>';
	}, $html);
}

/** Extrae pares Etiqueta⇒Valor (métrico = primer valor no vacío) de la PRIMERA <table> del HTML del feed. */
function seimiExtractSpecs($html) {
	$specs = [];
	$html = (string) $html;
	if ($html === '' || stripos($html, '<table') === false) return $specs;
	if (!preg_match('#<table\b[^>]*>.*?</table>#is', $html, $tm)) return $specs;
	if (!preg_match_all('#<tr\b[^>]*>(.*?)</tr>#is', $tm[0], $trs)) return $specs;
	foreach ($trs[1] as $tr) {
		if (!preg_match_all('#<t[dh]\b[^>]*>(.*?)</t[dh]>#is', $tr, $tds)) continue;
		$cells = [];
		foreach ($tds[1] as $c) {
			$c = strip_tags(preg_replace('#<\s*br\s*/?\s*>#i', ' ', $c));
			$c = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($c, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
			if ($c !== '') $cells[] = $c;
		}
		if (count($cells) >= 2) $specs[$cells[0]] = $cells[1];
	}
	return $specs;
}

/** Tabla "Variantes y especificaciones" para la descripción de FAMILIAS (estilo tabla Osculati/Oceansouth).
 *  Columnas: Referencia | Modelo | specs del feed (unión, máx 6) | Peso si difiere. Entre marcadores idempotentes. */
function seimiBuildFamilySpecTable(array $items, array $labels, $langId, $useLlm) {
	$isEs = ($langId === LANG_ID_ES);
	$specsBySku = [];
	$specCols = [];
	foreach ($items as $sku => $it) {
		$src = (!$isEs && $it['LONG_EN'] !== '') ? $it['LONG_EN'] : $it['LONG_FR'];
		$sp = seimiExtractSpecs($src);
		$specsBySku[$sku] = $sp;
		foreach ($sp as $k => $v) if (!in_array($k, $specCols, true)) $specCols[] = $k;
	}
	$specCols = array_slice($specCols, 0, 6);
	$weights = [];
	foreach ($items as $it) $weights[(string) $it['_WEIGHT']] = true;
	$withWeight = count($weights) > 1;
	$L = $isEs ? ['ref' => 'Referencia', 'model' => 'Modelo', 'weight' => 'Peso (kg)']
	           : ['ref' => 'Reference', 'model' => 'Model', 'weight' => 'Weight (kg)'];
	$title = $isEs ? 'Variantes y especificaciones' : 'Variants and specifications';
	$headTr = [];
	foreach ($specCols as $k) $headTr[$k] = seimiTranslateLabelFull($k, $isEs ? 0 : 1, $useLlm);
	$FONT  = 'font-family: tahoma, arial, helvetica, sans-serif; font-size: 10pt;';
	$open  = '<table class="osc-spec-table" style="border-collapse: collapse; border: 1px solid rgb(206, 212, 217);" border="1" cellspacing="3" cellpadding="3"><tbody>';
	$hCell = fn($t) => '<td style="background-color: #008cc6; text-align: center; padding: 2px;"><span style="' . $FONT . ' color: #ffffff;">' . htmlspecialchars($t) . '</span></td>';
	$dCell = fn($t) => '<td style="text-align: center; padding: 2px;"><span style="' . $FONT . '">' . htmlspecialchars($t) . '</span></td>';
	$html = '<br><br><!--SEIMI_SPECS_START--><p><strong>' . htmlspecialchars($title) . '</strong></p>' . $open . '<tr>';
	$html .= $hCell($L['ref']) . $hCell($L['model']);
	foreach ($specCols as $k) $html .= $hCell($headTr[$k]);
	if ($withWeight) $html .= $hCell($L['weight']);
	$html .= '</tr>';
	$i = 0;
	foreach ($items as $sku => $it) {
		$i++;
		$ref = $it['SUPCODE'] !== '' ? $it['SUPCODE'] : $sku;
		$row = $dCell($ref) . $dCell($labels[$sku] ?? $sku);
		foreach ($specCols as $k) $row .= $dCell($specsBySku[$sku][$k] ?? '—');
		if ($withWeight) $row .= $dCell(rtrim(rtrim(number_format((float) $it['_WEIGHT'], 3, '.', ''), '0'), '.'));
		$html .= '<tr' . ($i % 2 === 0 ? ' style="background-color: #e2f2f9;"' : '') . '>' . $row . '</tr>';
	}
	$html .= '</tbody></table><!--SEIMI_SPECS_END-->';
	return $html;
}

function cleanHtmlAggressive($html) {
	if ($html === null || $html === '') return '';
	$html = seimiFlattenSpecTables($html);
	$html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html);
	$html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
	$html = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $html);
	$html = preg_replace('#</\s*(p|div|li|tr|h[1-6]|ul|ol|table|article|section)\s*>#i', "\n", $html);
	$html = preg_replace('#<\s*li\b[^>]*>#i', "- ", $html);
	$text = strip_tags($html);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$lines = preg_split("/\r\n|\r|\n/", $text);
	$out = [];
	$emptyStreak = 0;
	foreach ($lines as $l) {
		// OJO: la clase con \xC2\xA0 SIN modificador 'u' (heredada de FNI) opera a nivel de BYTE
		// y mutila el UTF-8 francés (à = C3 A0 pierde el A0). \x{00A0} con 'u' = NBSP real.
		$l = trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $l));
		if ($l === '') {
			if ($emptyStreak < 1 && !empty($out)) { $out[] = ''; }
			$emptyStreak++;
			continue;
		}
		$out[] = $l;
		$emptyStreak = 0;
	}
	return nl2br(trim(implode("\n", $out)), false);
}

function seimiSlugify($text, $maxLen = 50) {
	$t = trim((string) $text);
	if (function_exists('iconv')) {
		$conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
		if ($conv !== false && $conv !== '') $t = $conv;
	}
	$t = strtolower($t);
	$t = preg_replace('/[^a-z0-9]+/', '-', $t);
	$t = trim($t, '-');
	if (strlen($t) > $maxLen) $t = substr($t, 0, $maxLen);
	$t = trim($t, '-');
	return $t === '' ? 'producto' : $t;
}

/** Si la marca tiene valor, no está en BRAND_NO_PREFIX y el nombre no empieza ya por ella, devuelve "<Brand> ". */
function seimiBrandPrefix($rawBrand, $rawName = '') {
	$brand = trim((string) $rawBrand);
	if ($brand === '') return '';
	if (in_array(strtoupper($brand), BRAND_NO_PREFIX, true)) return '';
	// Con 256 marcas, muchos títulos ya llevan la marca dentro ("Batterie Exide Dual…"): no duplicar.
	if ($rawName !== '' && preg_match('/(?<![\p{L}\p{N}])' . preg_quote($brand, '/') . '(?![\p{L}\p{N}])/iu', $rawName)) return '';
	return seimiNormalizeManufacturer($brand) . ' ';
}

/** Antepone el prefijo de marca a $name y trunca al máximo de varchar(80). */
function seimiApplyBrandPrefix($prefix, $name) {
	if ($name === null || $name === '') return $prefix !== '' ? rtrim($prefix) : '';
	return mb_substr($prefix . $name, 0, PRODUCT_NAME_MAX, 'UTF-8');
}

function seimiNormalizeManufacturer($name) {
	$name = trim(preg_replace('/\s+/', ' ', (string) $name));
	if ($name === '') return '';
	$words = explode(' ', $name);
	$out = [];
	foreach ($words as $w) {
		if ($w === '') continue;
		$alpha = preg_replace('/[^A-Za-z]/', '', $w);
		if (strlen($alpha) > 0 && strtoupper($alpha) === $alpha && strlen($alpha) <= 4) {
			$out[] = $w;
		} else {
			$out[] = mb_convert_case(mb_strtolower($w, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
		}
	}
	return implode(' ', $out);
}

function resolveManufacturer($mysqli, $rawName, &$cache, &$createdLog, $dryRun) {
	$key = strtoupper(trim($rawName));
	if ($key === '') return null;
	if (isset($cache[$key])) return $cache[$key];
	$qkey = $mysqli->real_escape_string($key);
	$r = $mysqli->query("SELECT manufacturers_id FROM manufacturers WHERE UPPER(TRIM(manufacturers_name))=\"$qkey\" LIMIT 1");
	if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['manufacturers_id']; return $cache[$key]; }
	$display = seimiNormalizeManufacturer($rawName);
	if ($dryRun) { $cache[$key] = 0; $createdLog[$key] = $display; return 0; }
	$qd = $mysqli->real_escape_string($display);
	$mysqli->query("INSERT INTO manufacturers (manufacturers_name, date_added) VALUES (\"$qd\", NOW())");
	$id = (int) $mysqli->insert_id;
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_ES . ", \"\")");
	$mysqli->query("INSERT INTO manufacturers_info (manufacturers_id, languages_id, manufacturers_url) VALUES ($id, " . LANG_ID_EN . ", \"\")");
	$cache[$key] = $id;
	$createdLog[$key] = $display . " (id=$id)";
	return $id;
}

/** Subcategoría de staging por Universo bajo SEIMI Nuevos (status=0, oculta), con nombre ES+EN.
 *  Patrón de Lalizas/Vetus (getOrCreateSubcategory), ampliado a nombre EN propio. */
function getOrCreateSubcategory($mysqli, $nameEs, $nameEn, $parentId, $dryRun, &$cache, &$createdLog) {
	$nm = trim(preg_replace('/\s+/u', ' ', (string) $nameEs));
	if ($nm === '') $nm = FALLBACK_SUBCAT;
	$nmEn = trim(preg_replace('/\s+/u', ' ', (string) $nameEn));
	if ($nmEn === '') $nmEn = $nm;
	$key = mb_strtoupper($nm, 'UTF-8');
	if (isset($cache[$key])) return $cache[$key];
	$qName = $mysqli->real_escape_string($nm);
	$parentId = (int) $parentId;
	if ($parentId > 0) {
		$r = $mysqli->query("SELECT c.categories_id FROM categories c INNER JOIN categories_description cd ON cd.categories_id=c.categories_id AND cd.language_id=" . LANG_ID_ES . " WHERE c.parent_id=$parentId AND UPPER(TRIM(cd.categories_name))=UPPER('$qName') LIMIT 1");
		if ($r && $row = $r->fetch_assoc()) { $cache[$key] = (int) $row['categories_id']; return $cache[$key]; }
	}
	if ($dryRun) { $cache[$key] = 0; $createdLog[] = "subcategoría '$nm' bajo $parentId (dry-run, no creada)"; return 0; }
	$r = $mysqli->query("SELECT IFNULL(MAX(sort_order), 0) + 1 AS nso FROM categories WHERE parent_id=$parentId");
	$nso = (int) ($r->fetch_assoc()['nso'] ?? 1);
	$mysqli->query("INSERT INTO categories (parent_id, sort_order, date_added, last_modified, categories_status) VALUES ($parentId, $nso, NOW(), NOW(), 0)"); // subcat oculta (status=0): staging
	$newId = (int) $mysqli->insert_id;
	$qEn = $mysqli->real_escape_string($nmEn);
	$mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_ES . ", '$qName')");
	$mysqli->query("INSERT INTO categories_description (categories_id, language_id, categories_name) VALUES ($newId, " . LANG_ID_EN . ", '$qEn')");
	$cache[$key] = $newId;
	$createdLog[] = "subcategoría '$nm' (id=$newId) bajo $parentId";
	return $newId;
}

/** Mapa de duplicados — FILTRADO por origin SEIMI (multi-marca, como FNI/Lankhorst).
 *  EAN sigue siendo GLOBAL (identificador único GS1). Incluye lista negra de reimportación. */
function buildExistingMap($mysqli) {
	$existing = [];
	$f = "(p.products_import_origin LIKE 'vdm%' OR p.products_import_origin LIKE 'kent%' OR p.products_import_origin LIKE 'seimi%')"; // familia Alliance Marine: la misma ref de fabricante puede venir por las 3 filiales
	$r = $mysqli->query("SELECT LOWER(p.products_model) m FROM products p WHERE p.products_model<>'' AND p.products_model IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(p.reference_prov) m FROM products p WHERE p.reference_prov<>'' AND p.reference_prov IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(pa.reference) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference<>'' AND pa.reference IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(pa.reference_prov) m FROM products_attributes pa INNER JOIN products p ON p.products_id=pa.products_id WHERE pa.reference_prov<>'' AND pa.reference_prov IS NOT NULL AND $f");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	// EAN global (GS1)
	$r = $mysqli->query("SELECT LOWER(product_ean) m FROM products WHERE product_ean<>'' AND product_ean IS NOT NULL");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	$r = $mysqli->query("SELECT LOWER(products_attributes_ean) m FROM products_attributes WHERE products_attributes_ean<>'' AND products_attributes_ean IS NOT NULL");
	while ($row = $r->fetch_assoc()) $existing[$row['m']] = true;
	// Lista negra de reimportación: trata como "ya existentes" los códigos/EAN de productos borrados a propósito.
	require_once dirname(__FILE__) . '/includes/import_blacklist.php';
	$existing += fb_blacklist_keys();
	return $existing;
}

/** Detecta respuestas degeneradas del LLM (bucles "R R R…", gibberish). Igual que FNI. */
function llmLooksDegenerate($s) {
	$s = trim((string) $s);
	if ($s === '') return true;
	$alnum = preg_replace('/[^\p{L}\p{N}]/u', '', $s);
	if (mb_strlen($alnum, 'UTF-8') >= 20) {
		$chars = preg_split('//u', mb_strtolower($alnum, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
		$freq = array_count_values($chars);
		if (count($chars) && (max($freq) / count($chars)) > 0.6) return true;
	}
	$tokens = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
	if (count($tokens) >= 8) {
		$tf = array_count_values(array_map(fn($t) => mb_strtolower($t, 'UTF-8'), $tokens));
		if ((max($tf) / count($tokens)) > 0.45) return true;
	}
	return false;
}

function llmTranslate($text, $maxRetries = 2, $maxOutChars = 0, $sysPrompt = null) {
	if (trim((string) $text) === '') return '';
	$payload = json_encode([
		'model' => LLM_MODEL,
		'messages' => [
			['role' => 'system', 'content' => $sysPrompt !== null ? $sysPrompt : LLM_PROMPT],
			['role' => 'user',   'content' => $text],
		],
		'temperature' => 0.2, 'top_p' => 0.8, 'top_k' => 20,
		'repetition_penalty' => 1.15, // frena los bucles degenerados del NVFP4
		'max_tokens' => $sysPrompt !== null ? 200 : 1500, // etiquetas/cabeceras cortas vs descripciones
		'chat_template_kwargs' => ['enable_thinking' => false],
	], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); // nunca FALSE por bytes sueltos del feed
	for ($i = 0; $i <= $maxRetries; $i++) {
		$ch = curl_init(LLM_URL);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_TIMEOUT => 90,
			CURLOPT_CONNECTTIMEOUT => 10,
		]);
		$resp = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		unset($ch);
		if ($resp !== false && $code === 200) {
			$j = json_decode($resp, true);
			$content = $j['choices'][0]['message']['content'] ?? null;
			if (is_string($content) && trim($content) !== '' && !llmLooksDegenerate($content)
				&& ($maxOutChars <= 0 || mb_strlen(trim($content), 'UTF-8') <= $maxOutChars)) {
				return trim($content);
			}
		}
		usleep(500000);
	}
	return '';
}

function downloadImage($url, $destAbs) {
	if (empty($url)) return false;
	$ch = curl_init($url);
	$fp = fopen($destAbs, 'wb');
	if (!$fp) return false;
	curl_setopt_array($ch, [
		CURLOPT_FILE => $fp,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT => IMG_HTTP_TIMEOUT,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FrancobordoImporter/1.0)',
		CURLOPT_SSL_VERIFYPEER => false,
	]);
	$ok = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	unset($ch);
	fclose($fp);
	$ok = $ok && $code === 200 && filesize($destAbs) > 0;
	if (!$ok) @unlink($destAbs);
	return $ok;
}

/** Redondea un PVP NETO de modo que el precio CON IVA quede en múltiplo de 0.05. */
function roundToNickel($net) {
	$wi = ((float) $net) * (1 + VAT_RATE);
	$r  = round($wi * 20) / 20;
	return round($r / (1 + VAT_RATE), 4);
}

function calcG1Price($price, $cost) {
	$price = (float) $price;
	$cost = (float) $cost;
	if ($price <= 0) return 0.0;
	$mult = 0.90;
	if ($cost > 0) {
		$margin = ($price - $cost) / $price;
		if      ($margin >= 0.45) $mult = 0.75;
		elseif  ($margin >= 0.40) $mult = 0.80;
		elseif  ($margin >= 0.35) $mult = 0.82;
		elseif  ($margin >= 0.30) $mult = 0.85;
	}
	$tier  = $price * $mult;
	$floor = $cost * G1_FLOOR_FACTOR;
	return round(max($tier, $floor), 4);
}

function ean13Checksum($payload12) {
	if (strlen($payload12) !== 12 || !ctype_digit($payload12)) return -1;
	$sum = 0;
	for ($i = 0; $i < 12; $i++) {
		$d = (int) $payload12[$i];
		$sum += ($i % 2 === 0) ? $d : $d * 3;
	}
	return (10 - ($sum % 10)) % 10;
}
function isValidEan13($ean) {
	$ean = trim((string) $ean);
	if (strlen($ean) !== 13 || !ctype_digit($ean)) return false;
	return ean13Checksum(substr($ean, 0, 12)) === (int) $ean[12];
}
/** Normaliza el Barcode del feed a EAN-13 válido, o '' si no lo es.
 *  Maneja UPC-A de 12 díg (prepend 0) y GTIN-14 con 0 inicial (strip) — el
 *  checksum GTIN se conserva al re-padear con ceros. */
function seimiNormalizeEan($raw) {
	$e = preg_replace('/\D/', '', trim((string) $raw));
	if (strlen($e) === 12) $e = '0' . $e;
	elseif (strlen($e) === 14 && $e[0] === '0') $e = substr($e, 1);
	return isValidEan13($e) ? $e : '';
}
function generateInternalEan13($productId, $providerPrefix) {
	$pp = (int) $providerPrefix;
	if ($pp < 20 || $pp > 28) return '';
	if ($productId <= 0 || $productId > 9999999999) return '';
	$payload = str_pad((string) $pp, 2, '0', STR_PAD_LEFT) . str_pad((string) $productId, 10, '0', STR_PAD_LEFT);
	$check = ean13Checksum($payload);
	return $check < 0 ? '' : ($payload . $check);
}

function longestCommonPrefix(array $strs) {
	if (empty($strs)) return '';
	$strs = array_values($strs);
	$prefix = $strs[0];
	$prefixLen = mb_strlen($prefix, 'UTF-8');
	foreach ($strs as $s) {
		while ($prefixLen > 0 && mb_substr($s, 0, $prefixLen, 'UTF-8') !== $prefix) {
			$prefixLen--;
			$prefix = mb_substr($prefix, 0, $prefixLen, 'UTF-8');
		}
		if ($prefixLen === 0) return '';
	}
	return $prefix;
}

function seimiExtractMeasure($title) {
	$title = (string) $title;
	// Diámetro: Ø 8 / Ø8 / diamètre 8 / diameter 8 / diam. 8 mm
	if (preg_match('/(?:Ø|diamètre|diameter|diam\.?)\s*(\d+(?:[.,]\d+)?)\s*(mm|cm)?/iu', $title, $m)) {
		return 'Ø ' . $m[1] . ' ' . (isset($m[2]) && $m[2] !== '' ? $m[2] : 'mm');
	}
	// Tamaño tornillería: 4,8X32 / 5,5X16 / M6X20
	if (preg_match('/(M?\d+(?:[.,]\d+)?)\s*[Xx]\s*(\d+(?:[.,]\d+)?)\b/u', $title, $m)) {
		return $m[1] . 'x' . $m[2];
	}
	// Rango "X-Y unidad"
	if (preg_match('/(\d+(?:[.,]\d+)?\s*-\s*\d+(?:[.,]\d+)?)\s*(kg|g|mm|cm|mt|m|l|ml|HP|CV|W|V|A|Ah|AMP|Hz|°)\b/iu', $title, $m)) {
		return trim($m[1]) . ' ' . $m[2];
	}
	// Valor + unidad explícita
	if (preg_match('/(\d+(?:[.,]\d+)?)\s*(Ah|AMP|kg|g|mm|cm|mt|m|lt|l|ml|in|inch|"|HP|CV|W|V|A|Hz|°|fl\s*oz|oz)\b/iu', $title, $m)) {
		return $m[1] . ' ' . strtolower($m[2]);
	}
	return '';
}

/** Busca (por nombre ES) o crea un option value con nombre ES y EN propios.
 *  Gotcha conocido (mismo que Oceansouth): si ya existe por ES, no se toca su EN. */
function findOrCreateOptionValue($mysqli, $nameEs, $nameEn = null) {
	if ($nameEn === null || $nameEn === '') $nameEn = $nameEs;
	$nameSafe = $mysqli->real_escape_string($nameEs);
	$q = $mysqli->query("SELECT pov.products_options_values_id
		FROM products_options_values pov
		INNER JOIN products_options_values_to_products_options pov2po ON pov2po.products_options_values_id = pov.products_options_values_id
		WHERE pov2po.products_options_id = " . VARIANT_OPTION_ID . "
		  AND pov.language_id = " . LANG_ID_ES . "
		  AND pov.products_options_values_name = '$nameSafe'
		LIMIT 1");
	if ($row = $q->fetch_assoc()) return (int) $row['products_options_values_id'];
	$nq = $mysqli->query("SELECT IFNULL(MAX(products_options_values_id), 0) + 1 AS nid FROM products_options_values");
	$nrow = $nq->fetch_assoc();
	$newId = (int) $nrow['nid'];
	$nameEnSafe = $mysqli->real_escape_string($nameEn);
	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_ES . ", '$nameSafe', '')");
	$mysqli->query("INSERT INTO products_options_values (products_options_values_id, language_id, products_options_values_name, CCODIVAL) VALUES ($newId, " . LANG_ID_EN . ", '$nameEnSafe', '')");
	$mysqli->query("INSERT INTO products_options_values_to_products_options (products_options_id, products_options_values_id) VALUES (" . VARIANT_OPTION_ID . ", $newId)");
	return $newId;
}

/** Descarga SEIMI_products_francobordo.csv vía SFTP (Azure Blob). Devuelve ['ok'=>bool,...]. */
function downloadFeedFromSftp($localPath) {
	try {
		$dir = dirname($localPath);
		if (!is_dir($dir)) @mkdir($dir, 0775, true);
		$sftp = new SFTP(SEIMI_SFTP_HOST, SEIMI_SFTP_PORT, 30);
		if (!$sftp->login(SEIMI_SFTP_USER, SEIMI_SFTP_PASS)) return ['ok' => false, 'err' => 'login fallido'];
		$tmp = $localPath . '.tmp.' . uniqid();
		if (!$sftp->get(SEIMI_SFTP_REMOTE, $tmp)) return ['ok' => false, 'err' => 'get fallido'];
		if (!file_exists($tmp) || filesize($tmp) < 1000000) { // el feed real ronda 38 MB
			@unlink($tmp);
			return ['ok' => false, 'err' => 'fichero descargado vacío/inválido'];
		}
		if (!@rename($tmp, $localPath)) { @unlink($tmp); return ['ok' => false, 'err' => 'rename fallido']; }
		return ['ok' => true, 'size' => filesize($localPath)];
	} catch (Exception $e) {
		return ['ok' => false, 'err' => $e->getMessage()];
	}
}

/** Lee el CSV SEIMI (pipe |, UTF-8, CON cabecera) mapeando por nombre de columna.
 *  Solo Product_status=Active. Devuelve filas con claves normalizadas. */
function loadCsvRows($path, &$statusSkipped = 0, &$badRows = 0) {
	$f = fopen($path, 'r');
	if (!$f) return [];
	$header = fgetcsv($f, 0, '|', chr(34), '');
	if (!$header) { fclose($f); return []; }
	// BOM UTF-8 defensivo en la primera celda
	if (isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
	$idx = array_flip(array_map('trim', $header));
	$ncols = count($header);
	$col = function (array $r, $name) use ($idx) {
		$i = $idx[$name] ?? null;
		return ($i !== null && array_key_exists($i, $r)) ? trim((string) $r[$i]) : '';
	};
	$rows = [];
	while (($r = fgetcsv($f, 0, '|', chr(34), '')) !== false) {
		if (count($r) !== $ncols) { $badRows++; continue; }
		$status = $col($r, 'Product_status');
		if (strcasecmp($status, 'Active') !== 0) { $statusSkipped++; continue; }
		$addImgs = [];
		$aiRaw = $col($r, 'additional_images');
		if ($aiRaw !== '' && $aiRaw !== '[]') {
			$dec = json_decode($aiRaw, true);
			if (is_array($dec)) $addImgs = array_values(array_filter(array_map('trim', $dec)));
		}
		$rows[] = [
			'SKU'        => $col($r, 'SKU'),
			'SUPCODE'    => $col($r, 'supplier_item_code'),
			'EAN'        => seimiNormalizeEan($col($r, 'Barcode')),
			'NAME_FR'    => $col($r, 'Item_description'),
			'NAME_EN'    => $col($r, 'Item_description_EN'),
			'SHORT_FR'   => $col($r, 'Short_description'),
			'SHORT_EN'   => $col($r, 'Short_description_EN'),
			'LONG_FR'    => $col($r, 'Long_description'),
			'LONG_EN'    => $col($r, 'Long_description_EN'),
			'UNIVERSE'   => mb_strtoupper($col($r, 'Universe'), 'UTF-8'),
			'UNIVERSE_EN'=> $col($r, 'Universe_EN'),
			'PARENT'     => $col($r, 'Parent_id'),
			'BRAND'      => $col($r, 'Brand'),
			'WEIGHT'     => $col($r, 'Weight'),
			'PIC1'       => $col($r, 'Picture_1'),
			'PIC2'       => $col($r, 'Picture_2'),
			'PIC3'       => $col($r, 'Picture_3'),
			'ADDIMGS'    => $addImgs,
			'NET_PRICE'  => $col($r, 'Net_price_ex_VAT'),
			'PUBLIC'     => $col($r, 'Public_price_ex_VAT'),
			'PYRO'       => strcasecmp($col($r, 'Pyrotechnic'), 'true') === 0,
			'DANGER'     => strcasecmp($col($r, 'Dangerous'), 'true') === 0,
			'MULTIPLE'   => $col($r, 'Multiple_sales_quantity'),
		];
	}
	fclose($f);
	return $rows;
}

/** Marcas presentes en el CSV (post-blacklist), con conteo, para el desplegable. */
function listBrandsFromCsv($path) {
	$rows = loadCsvRows($path);
	$brands = [];
	foreach ($rows as $r) {
		$bUp = strtoupper(trim($r['BRAND']));
		if ($bUp !== '' && in_array($bUp, BRAND_BLACKLIST, true)) continue;
		$b = $r['BRAND'] !== '' ? $r['BRAND'] : DEFAULT_BRAND;
		$brands[$b] = ($brands[$b] ?? 0) + 1;
	}
	arsort($brands);
	return $brands;
}

/** Universos presentes en el CSV (nombre ES), con conteo, para el desplegable. */
function listUniversesFromCsv($path) {
	$rows = loadCsvRows($path);
	$univ = [];
	foreach ($rows as $r) {
		$es = UNIVERSE_ES[$r['UNIVERSE']] ?? FALLBACK_SUBCAT;
		$univ[$es] = ($univ[$es] ?? 0) + 1;
	}
	arsort($univ);
	return $univ;
}

/** Decide si un grupo Parent_id con N rows es una familia legítima de variantes (LCP nombres FR). */
function isVariantFamily(array $rows) {
	if (count($rows) < 2) return false;
	$names = array_map(fn($r) => $r['NAME_FR'] !== '' ? $r['NAME_FR'] : $r['NAME_EN'], $rows);
	$lcp = longestCommonPrefix($names);
	$lcpLen = mb_strlen($lcp, 'UTF-8');
	$minLen = min(array_map(fn($s) => mb_strlen($s, 'UTF-8'), $names));
	if ($minLen <= 0) return false;
	$ratio = $lcpLen / $minLen;
	if ($ratio < LCP_VARIANT_RATIO) return false;
	if ($ratio >= 0.50) return true;
	// Zona gris (30-50%): el sufijo de CADA SKU debe contener al menos un dígito (medida).
	foreach ($names as $name) {
		$suffix = mb_substr($name, $lcpLen, null, 'UTF-8');
		if (!preg_match('/\d/u', $suffix)) return false;
	}
	return true;
}

/** Descarga imagen principal + subimágenes (galería) para un pid. Devuelve [mainOk(bool), nSubs(int)]. */
function seimiDownloadImages($mysqli, $pid, array $row, $nameForSlug) {
	$urls = [];
	foreach (array_merge([$row['PIC1'], $row['PIC2'], $row['PIC3']], $row['ADDIMGS']) as $u) {
		$u = trim((string) $u);
		if ($u !== '' && !in_array($u, $urls, true)) $urls[] = $u;
	}
	if (empty($urls)) return [false, 0];
	$slug = seimiSlugify($nameForSlug);
	$mainOk = false;
	$mainName = $slug . '-' . $pid . '.jpg';
	$queue = $urls;
	// principal: primera URL que baje bien
	while (!empty($queue)) {
		$u = array_shift($queue);
		if (downloadImage($u, IMG_ABS_DIR . $mainName)) { $mainOk = true; break; }
	}
	if (!$mainOk) return [false, 0];
	$mysqli->query("UPDATE products SET products_image=\"" . $mysqli->real_escape_string($mainName) . "\" WHERE products_id=$pid");
	// galería: siguientes URLs hasta MAX_SUBIMAGES
	$subs = [];
	$n = 0;
	foreach ($queue as $u) {
		if ($n >= MAX_SUBIMAGES) break;
		$subName = $slug . '-' . $pid . '-sub' . ($n + 1) . '.jpg';
		if (downloadImage($u, IMG_ABS_DIR . $subName)) { $subs[] = $subName; $n++; }
	}
	if (!empty($subs)) {
		$subJson = json_encode($subs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$mysqli->query("UPDATE products SET products_subimages='" . $mysqli->real_escape_string($subJson) . "' WHERE products_id=$pid");
	}
	return [true, count($subs)];
}

$isAction = ($action === 'execute' || $action === 'dry_run');

if ($isAction) {
	@header('X-Accel-Buffering: no');
	@header('Content-Type: text/html; charset=utf-8');
	while (ob_get_level() > 0) @ob_end_flush();
	@ob_implicit_flush(true);
	// Liberar la conexión PDO del handler de sesión (evita "MySQL server has gone away" en batches largos)
	if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
}
?>
<?php require THEME . 'html/header.php'; ?>
<table style="width:100%;"><tr><td>
<div style="padding:20px;">
<?php if ($isAction): ?>
	<h2>Importador SEIMI (Alliance Marine) — <?php echo $dryRun ? 'DRY-RUN (sin cambios)' : 'EJECUCIÓN REAL'; ?></h2>
	<p>
		<a href="<?php echo tep_href_link('import-seimi-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
	<div style="background:#fafafa;border:1px solid #ddd;border-radius:4px;margin-top:15px;max-height:600px;overflow-y:auto;">
<?php
echo str_pad('<!-- streaming pad -->', 4096) . "\n";
@flush();

logMsg("Modo: " . ($dryRun ? "dry-run" : "EXECUTE")
	. ($skipTranslation ? " (sin traducción LLM FR/EN→ES)" : "")
	. ($skipVariants    ? " (sin agrupar variantes)" : "")
	. ($skipDownload    ? " (sin descargar feed del SFTP)" : "")
	. " | brand=" . ($selectedBrand === 'all' ? 'TODAS' : $selectedBrand)
	. " | universo=" . ($selectedUniv === 'all' ? 'TODOS' : $selectedUniv)
	. ($max > 0 ? ", max=$max" : ""));

if (NEW_CATEGORY_ID <= 0) { logMsg("ERROR: NEW_CATEGORY_ID sin fijar (crear categoría 'SEIMI Nuevos' y poner su id)"); goto end_action; }

if (!$skipDownload) {
	$mtime = file_exists(SEIMI_CSV) ? filemtime(SEIMI_CSV) : 0;
	logMsg("Descargando " . SEIMI_SFTP_REMOTE . " desde SFTP " . SEIMI_SFTP_HOST . " …" . ($mtime ? " (local actual: " . date('Y-m-d H:i', $mtime) . ", " . round(filesize(SEIMI_CSV)/1048576, 1) . " MB)" : ""));
	$dl = downloadFeedFromSftp(SEIMI_CSV);
	if ($dl['ok']) {
		logMsg("  ✓ descargado: " . round($dl['size']/1048576, 1) . " MB (mtime " . date('Y-m-d H:i', filemtime(SEIMI_CSV)) . ")");
	} else {
		logMsg("  ✗ descarga fallida: " . $dl['err'] . " — uso copia local existente si la hay");
		if (!file_exists(SEIMI_CSV)) { logMsg("ERROR: no hay copia local y SFTP falló"); goto end_action; }
	}
}

if (!file_exists(SEIMI_CSV)) { logMsg("ERROR: CSV no encontrado: " . SEIMI_CSV); goto end_action; }
logMsg("Leyendo CSV…");
$statusSkipped = 0; $badRows = 0;
$rows = loadCsvRows(SEIMI_CSV, $statusSkipped, $badRows);
logMsg("Filas Active leídas: " . count($rows) . " | no-Active (End of life/Dead) saltadas: $statusSkipped | filas mal formadas: $badRows");

$mysqli = new mysqli(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD, DB_DATABASE);
if ($mysqli->connect_error) { logMsg("ERROR DB: " . $mysqli->connect_error); goto end_action; }
$mysqli->set_charset('utf8');

if (!$dryRun && !is_dir(IMG_ABS_DIR)) { @mkdir(IMG_ABS_DIR, 0775, true); }

logMsg("Cargando IDs existentes en BD…");
$existing = buildExistingMap($mysqli);
logMsg("  → " . count($existing) . " referencias ya en BD (scope origin=seimi + EAN global + lista negra)");

$candidates = [];
$skippedExisting = $skippedNoSku = $skippedNoName = $skippedBadPrice = $skippedBrand = $skippedBrandFilter = $skippedUnivFilter = 0;
$pyroList = [];
$nDanger = 0; $nMultiple = 0;
// Si hay codes específicos, derivamos los Parent_id para incluir toda la familia.
$wantedParents = [];
if (!empty($onlyCodes)) {
	foreach ($rows as $row) {
		if (isset($onlyCodes[$row['SKU']]) || ($row['SUPCODE'] !== '' && isset($onlyCodes[$row['SUPCODE']]))) {
			if ($row['PARENT'] !== '') $wantedParents[$row['PARENT']] = true;
		}
	}
}
foreach ($rows as $row) {
	$sku = $row['SKU'];
	if ($sku === '') { $skippedNoSku++; continue; }
	$inOnlyCodes = !empty($onlyCodes) && (isset($onlyCodes[$sku]) || ($row['SUPCODE'] !== '' && isset($onlyCodes[$row['SUPCODE']])) || ($row['PARENT'] !== '' && isset($wantedParents[$row['PARENT']])));
	if (!empty($onlyCodes) && !$inOnlyCodes) continue;

	if ($row['NAME_FR'] === '' && $row['NAME_EN'] === '') { $skippedNoName++; continue; }

	// Filtro de marca: skip si está en blacklist; asignar "Generico" si está vacía.
	$brandUp = strtoupper(trim($row['BRAND']));
	if (!$inOnlyCodes && $brandUp !== '' && in_array($brandUp, BRAND_BLACKLIST, true)) { $skippedBrand++; continue; }
	if ($row['BRAND'] === '') $row['BRAND'] = DEFAULT_BRAND;

	// Filtro por marca del desplegable
	if (empty($onlyCodes) && $selectedBrand !== 'all' && strcasecmp($row['BRAND'], $selectedBrand) !== 0) { $skippedBrandFilter++; continue; }

	// Filtro por universo del desplegable (nombre ES)
	$univEs = UNIVERSE_ES[$row['UNIVERSE']] ?? FALLBACK_SUBCAT;
	$row['_UNIV_ES'] = $univEs;
	$row['_UNIV_EN'] = mb_convert_case(mb_strtolower($row['UNIVERSE_EN'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
	if (empty($onlyCodes) && $selectedUniv !== 'all' && strcasecmp($univEs, $selectedUniv) !== 0) { $skippedUnivFilter++; continue; }

	// Dedup: SKU SEIMI, referencia de fabricante y EAN
	if (isset($existing[strtolower($sku)])) { $skippedExisting++; continue; }
	if ($row['SUPCODE'] !== '' && isset($existing[strtolower($row['SUPCODE'])])) { $skippedExisting++; continue; }
	if ($row['EAN'] !== '' && isset($existing[strtolower($row['EAN'])])) { $skippedExisting++; continue; }

	$net = seimiParseNum($row['NET_PRICE']);
	if ($net === null || $net <= 0) { $skippedBadPrice++; continue; }
	$pub = seimiParseNum($row['PUBLIC']);
	$cost = $net;
	$price = ($pub !== null && $pub > $cost) ? $pub : $cost * 2.0;
	$row['_NET']    = $net;
	$row['_COST']   = $cost;
	$row['_PRICE']  = roundToNickel($price);
	$row['_G1']     = roundToNickel(calcG1Price($row['_PRICE'], $cost));
	$weight = seimiParseNum($row['WEIGHT']);
	$row['_WEIGHT'] = ($weight === null || $weight <= 0) ? 1.0 : $weight;
	if ($row['PYRO'])  $pyroList[] = $sku;
	if ($row['DANGER']) $nDanger++;
	$mult = seimiParseNum($row['MULTIPLE']);
	if ($mult !== null && $mult > 1) $nMultiple++;
	$candidates[$sku] = $row;
}
logMsg("Candidatos tras pre-filtro: " . count($candidates));
logMsg("  pre-skip: existentes=$skippedExisting | sin SKU=$skippedNoSku | sin nombre=$skippedNoName | precio=$skippedBadPrice | brand-blacklist=$skippedBrand | brand-filter=$skippedBrandFilter | universo-filter=$skippedUnivFilter");
if (!empty($pyroList)) logMsg("  ⚠ PIROTECNIA en candidatos (" . count($pyroList) . "): " . implode(', ', array_slice($pyroList, 0, 30)) . (count($pyroList) > 30 ? '…' : '') . " — revisar flag pyro/categoría 20 al activar");
if ($nDanger)   logMsg("  ⚠ Mercancía peligrosa (Dangerous=true) en candidatos: $nDanger — puede condicionar transportista");
if ($nMultiple) logMsg("  ℹ Con múltiplo de venta >1: $nMultiple (el múltiplo NO se aplica en web; revisar a mano si importa)");

// Agrupación por Parent_id
$families = [];
$standalone = [];
if ($skipVariants) {
	$standalone = $candidates;
	logMsg("Agrupación por Parent_id desactivada — todos los candidatos como sueltos.");
} else {
	$byParent = [];
	foreach ($candidates as $sku => $row) {
		$p = $row['PARENT'];
		if ($p === '') { $standalone[$sku] = $row; continue; }
		// REGLA (usuario 2026-07-08): las variantes deben COMPARTIR imagen — el Parent_id del
		// feed agrupa SERIES (bombas distintas con specs/foto propias). Sub-agrupamos cada
		// parent por Picture_1: misma imagen = candidato a familia; imagen distinta = aparte.
		$byParent[$p . '#' . md5($row['PIC1'])][$sku] = $row;
	}

	$famVariant = $famSplit = 0;
	foreach ($byParent as $p => $items) {
		if (count($items) === 1) {
			$sku = array_key_first($items);
			$standalone[$sku] = $items[$sku];
			continue;
		}
		if (isVariantFamily($items)) {
			$families[$p] = $items;
			$famVariant++;
		} else {
			foreach ($items as $sku => $r) $standalone[$sku] = $r;
			$famSplit++;
		}
	}
	logMsg("Parent_id con ≥2 SKUs: variantes=$famVariant | divididos en sueltos (LCP insuficiente)=$famSplit");
}
// Filtro final por codes específicos
if (!empty($onlyCodes)) {
	$famF = [];
	foreach ($families as $p => $items) {
		$hit = false;
		foreach ($items as $sku => $it) {
			if (isset($onlyCodes[$sku]) || ($it['SUPCODE'] !== '' && isset($onlyCodes[$it['SUPCODE']]))) { $hit = true; break; }
		}
		if ($hit) $famF[$p] = $items;
	}
	$stdF = [];
	foreach ($standalone as $sku => $r) {
		if (isset($onlyCodes[$sku]) || ($r['SUPCODE'] !== '' && isset($onlyCodes[$r['SUPCODE']]))) $stdF[$sku] = $r;
	}
	$families = $famF;
	$standalone = $stdF;
	logMsg("Filtro por codes específicos: quedan " . count($families) . " familias + " . count($standalone) . " sueltos");
}
logMsg("Tras consolidar: " . count($families) . " familias multi-variante + " . count($standalone) . " sueltos");

$mfgCache = [];
$mfgCreated = [];
$subcatCache = [];
$subcatCreated = [];
$nInserted = $nFamiliesIns = $nStandaloneIns = 0;
$nWithVar = $nWithImg = $nSubImgs = $imgFail = $imgEmpty = $translateFail = $errors = 0;

/** Construye nombre/descr ES y EN de una fila (con LLM opcional). */
function seimiBuildTexts(array $row, $brandPrefix, $skipTranslation, $dryRun, &$translateFail) {
	$rawNameFr = $row['NAME_FR'] !== '' ? $row['NAME_FR'] : $row['NAME_EN'];
	$rawNameEn = $row['NAME_EN'] !== '' ? $row['NAME_EN'] : $row['NAME_FR'];
	$titleFr = seimiApplyBrandPrefix($brandPrefix, $rawNameFr);
	$titleEn = seimiApplyBrandPrefix($brandPrefix, $rawNameEn);

	// Cuerpo FR: long → short. Cuerpo EN: long_EN → short_EN → fallback FR.
	$bodyFr = cleanHtmlAggressive($row['LONG_FR'] !== '' ? $row['LONG_FR'] : $row['SHORT_FR']);
	$bodyEnSrc = $row['LONG_EN'] !== '' ? $row['LONG_EN'] : $row['SHORT_EN'];
	$bodyEn = cleanHtmlAggressive($bodyEnSrc);
	if ($bodyEn === '') $bodyEn = $bodyFr;
	$descEn = $bodyEn !== '' ? ($rawNameEn . "<br><br>" . $bodyEn) : $rawNameEn;

	// ES: fallback = texto FR sin traducir (dry-run / skip)
	$nameEs = $titleFr;
	$descEs = $bodyFr !== '' ? ($rawNameFr . "<br><br>" . $bodyFr) : $rawNameFr;
	if (!$skipTranslation && !$dryRun) {
		$tn = llmTranslate($rawNameFr, 2, max(60, mb_strlen($rawNameFr, 'UTF-8') * 3));
		if ($tn !== '') $nameEs = seimiApplyBrandPrefix($brandPrefix, $tn); else $translateFail++;
		$titleForDesc = $tn !== '' ? $tn : $rawNameFr;
		$bodyEs = '';
		$bodySrcEs = $bodyFr !== '' ? $bodyFr : $bodyEn;
		if ($bodySrcEs !== '') {
			$tb = llmTranslate($bodySrcEs);
			$bodyEs = $tb !== '' ? $tb : $bodySrcEs; // a falta de traducción, conserva el texto fuente
			if ($tb === '') $translateFail++;
		}
		$descEs = $bodyEs !== '' ? ($titleForDesc . "<br><br>" . $bodyEs) : $titleForDesc;
	}
	return [$nameEs, $descEs, $titleEn, $descEn];
}

// ---- 1) Familias con variantes ----
foreach ($families as $parent => $items) {
	if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }

	uasort($items, fn($a, $b) => $a['_NET'] <=> $b['_NET']);
	$cheapestSku = array_key_first($items);
	$cheap = $items[$cheapestSku];
	$brand = $cheap['BRAND'];
	$mfgId = resolveManufacturer($mysqli, $brand, $mfgCache, $mfgCreated, $dryRun);
	$brandPrefix = seimiBrandPrefix($brand, $cheap['NAME_FR'] !== '' ? $cheap['NAME_FR'] : $cheap['NAME_EN']);
	$catId = getOrCreateSubcategory($mysqli, $cheap['_UNIV_ES'], $cheap['_UNIV_EN'], NEW_CATEGORY_ID, $dryRun, $subcatCache, $subcatCreated);

	// Si el cuerpo del más barato era SOLO la tabla de specs del feed, se omite:
	// la tabla de familia (abajo) ya recoge las specs de TODAS las variantes.
	$cheapForText = $cheap;
	if ($cheap['LONG_FR'] !== '') {
		$strippedNoTable = trim(strip_tags(preg_replace('#<table\b[^>]*>.*?</table>#is', '', $cheap['LONG_FR'])));
		if (mb_strlen($strippedNoTable, 'UTF-8') < 40) { $cheapForText['LONG_FR'] = ''; $cheapForText['LONG_EN'] = ''; }
	}
	list($nameEs, $descEs, $titleEn, $descEn) = seimiBuildTexts($cheapForText, $brandPrefix, $skipTranslation, $dryRun, $translateFail);

	// Etiquetas de variante (ANTES del insert: la tabla de familia las usa en la descripción).
	// Señales en orden; la primera con valores únicos y no vacíos gana.
	$frTitlesAll = array_map(fn($it) => $it['NAME_FR'] !== '' ? $it['NAME_FR'] : $it['NAME_EN'], $items);
	$enTitlesAll = array_map(fn($it) => $it['NAME_EN'] !== '' ? $it['NAME_EN'] : $it['NAME_FR'], $items);
	// trims multibyte-safe ('–'/'·' con ltrim/rtrim byte a byte también corrompen UTF-8)
	$mbTrim = fn($s) => preg_replace('/^[\s\-–·,;]+|[\s\-–·,;]+$/u', '', (string) $s);
	$commonFr = $mbTrim(longestCommonPrefix($frTitlesAll));
	$commonEn = $mbTrim(longestCommonPrefix($enTitlesAll));
	$lcpStrip = function ($name, $common) use ($mbTrim) {
		if ($common === '' || mb_strpos($name, $common) !== 0) return '';
		$rest = $mbTrim(mb_substr($name, mb_strlen($common, 'UTF-8'), null, 'UTF-8'));
		return (mb_strlen($rest, 'UTF-8') <= 64) ? $rest : '';
	};
	$labelCandidates = [];
	foreach ($items as $sku => $it) {
		$labelCandidates[$sku] = [
			'lcp_fr'     => $lcpStrip($frTitlesAll[$sku], $commonFr),
			'lcp_en'     => $lcpStrip($enTitlesAll[$sku], $commonEn),
			'measure_fr' => seimiExtractMeasure($frTitlesAll[$sku]),
			'measure_en' => seimiExtractMeasure($enTitlesAll[$sku]),
		];
	}
	// Selección por idioma: ES parte de las señales FR (dict+LLM); EN prefiere las señales nativas EN.
	$pickLabels = function (array $order) use ($labelCandidates) {
		foreach ($order as $signal) {
			$vals = array_map(fn($c) => $c[$signal], $labelCandidates);
			$nonEmpty = array_filter($vals, fn($v) => $v !== '');
			if (count($nonEmpty) === count($vals) && count(array_unique($nonEmpty)) === count($vals)) return $vals;
		}
		return [];
	};
	$baseFr = $pickLabels(['lcp_fr', 'lcp_en', 'measure_fr', 'measure_en']);
	$baseEn = $pickLabels(['lcp_en', 'measure_en', 'lcp_fr', 'measure_fr']);
	$useLlmLabels = !$skipTranslation && !$dryRun;
	$labelsEs = [];
	$labelsEn = [];
	foreach ($items as $sku => $it) {
		$bf = $baseFr[$sku] ?? '';
		if ($bf === '') $bf = $sku;
		$be = $baseEn[$sku] ?? '';
		if ($be === '') $be = $bf;
		$labelsEs[$sku] = mb_substr(seimiTranslateLabelFull($bf, 0, $useLlmLabels), 0, 64, 'UTF-8');
		$labelsEn[$sku] = mb_substr(seimiTranslateLabelFull($be, 1, $useLlmLabels), 0, 64, 'UTF-8');
	}

	// Tabla "Variantes y especificaciones" al final de la descripción (solo familias)
	if (!$dryRun) {
		$descEs .= seimiBuildFamilySpecTable($items, $labelsEs, LANG_ID_ES, $useLlmLabels);
		$descEn .= seimiBuildFamilySpecTable($items, $labelsEn, LANG_ID_EN, $useLlmLabels);
	}

	if ($dryRun) {
		$nInserted++; $nFamiliesIns++;
		if ($nFamiliesIns <= 12) {
			$namesAll = array_map(fn($r) => $r['NAME_FR'] !== '' ? $r['NAME_FR'] : $r['NAME_EN'], $items);
			$lcp = longestCommonPrefix($namesAll);
			logMsg(sprintf("  WOULD INSERT FAMILIA parent=%s (%d variantes) cheap=%s %.2f€ [lcp=%d] cat=%s name=\"%s\"",
				$cheap['PARENT'], count($items), $cheapestSku, $cheap['_COST'],
				mb_strlen($lcp, 'UTF-8'), $cheap['_UNIV_ES'], mb_substr($nameEs, 0, 50, 'UTF-8')));
		}
		continue;
	}

	$mysqli->begin_transaction();
	try {
		// model = referencia del fabricante si la hay (mejor dedup/visible); reference_prov = SKU SEIMI (código de pedido)
		$model = $cheap['SUPCODE'] !== '' ? $cheap['SUPCODE'] : $cheapestSku;
		$qmodel = $mysqli->real_escape_string($model);
		$qrefprov = $mysqli->real_escape_string($cheapestSku);
		$qean   = $mysqli->real_escape_string($cheap['EAN']);
		$qmfg   = (int) $mfgId;
		$sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, \"$qmodel\", \"\", " . number_format($cheap['_PRICE'], 4, '.', '') . ", " . number_format($cheap['_COST'], 4, '.', '') . ", NOW(), {$cheap['_WEIGHT']}, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qrefprov\", \"" . ORIGIN_FLAG . "\")";
		if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameEs);
		$qDescEs = $mysqli->real_escape_string($descEs);
		$qNameEn = $mysqli->real_escape_string($titleEn);
		$qDescEn = $mysqli->real_escape_string($descEn);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . ($catId > 0 ? $catId : NEW_CATEGORY_ID) . ")")) throw new Exception("p2c: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($cheap['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

		$variantsCreated = 0;
		$ovsUsados = []; // guardia anti-colisión de labels por producto (ver francobordo_pa_duplicates)
		foreach ($items as $sku => $it) {
			$delta   = round($it['_PRICE'] - $cheap['_PRICE'], 4);
			$prefix  = $delta < 0 ? '-' : '+';
			$valueId = findOrCreateOptionValue($mysqli, $labelsEs[$sku], $labelsEn[$sku]);
			if (isset($ovsUsados[$valueId])) {
				$labelDisEs = mb_substr($labelsEs[$sku] . ' (' . $sku . ')', 0, 64, 'UTF-8');
				$labelDisEn = mb_substr($labelsEn[$sku] . ' (' . $sku . ')', 0, 64, 'UTF-8');
				$valueId = findOrCreateOptionValue($mysqli, $labelDisEs, $labelDisEn);
			}
			$ovsUsados[$valueId] = true;
			$refShown = $it['SUPCODE'] !== '' ? $it['SUPCODE'] : $sku;
			$qref    = $mysqli->real_escape_string($refShown);
			$qrefpr  = $mysqli->real_escape_string($sku);
			$qvean   = $mysqli->real_escape_string($it['EAN']);
			// Peso variante = DELTA sobre el padre (shopping_cart.php suma con prefix +/-)
			$weightDelta  = round($it['_WEIGHT'] - $cheap['_WEIGHT'], 3);
			$weightPrefix = $weightDelta < 0 ? '-' : '+';
			$weightAbs    = abs($weightDelta);
			if (!$mysqli->query("INSERT INTO products_attributes SET
				products_id=$pid, options_id=" . VARIANT_OPTION_ID . ", options_values_id=$valueId,
				options_values_price=" . number_format(abs($delta), 4, '.', '') . ", price_prefix='$prefix',
				reference='$qref', reference_prov='$qrefpr', products_attributes_ean='$qvean',
				options_values_weight=" . number_format($weightAbs, 3, '.', '') . ", weight_prefix='$weightPrefix'"))
				throw new Exception("attr: " . $mysqli->error);
			$paId = (int) $mysqli->insert_id;
			$variantsCreated++;

			$g1Delta = round($it['_G1'] - $cheap['_G1'], 4);
			$g1Prefix = $g1Delta < 0 ? '-' : '+';
			if (!$mysqli->query("INSERT INTO products_attributes_groups (products_attributes_id, customers_group_id, options_values_price, price_prefix, products_id, options_values_weight, weight_prefix) VALUES ($paId, " . G1_GROUP_ID . ", " . number_format(abs($g1Delta), 4, '.', '') . ", '$g1Prefix', $pid, 0, '+')"))
				throw new Exception("attr_groups: " . $mysqli->error);
		}
		if ($variantsCreated > 0) $nWithVar++;

		$mysqli->commit();

		// Imágenes (fuera de la transacción, como FNI): principal + galería
		list($imgOk, $subs) = seimiDownloadImages($mysqli, $pid, $cheap, $nameEs ?: $titleEn);
		if ($imgOk) { $nWithImg++; $nSubImgs += $subs; }
		elseif (empty(array_filter(array_merge([$cheap['PIC1'], $cheap['PIC2'], $cheap['PIC3']], $cheap['ADDIMGS'])))) { $imgEmpty++; }
		else { $imgFail++; }

		if (!isValidEan13($cheap['EAN'])) {
			$genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($genEan !== '') {
				$mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
			}
		}

		$nInserted++; $nFamiliesIns++;
		logMsg(sprintf("OK FAMILIA parent=%s pid=%d cheap=%s [%d variantes] cat=%s price=%.2f cost=%.2f g1=%.2f", $cheap['PARENT'], $pid, $cheapestSku, $variantsCreated, $cheap['_UNIV_ES'], $cheap['_PRICE'], $cheap['_COST'], $cheap['_G1']));
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("ERROR familia parent=" . $cheap['PARENT'] . " ($parent): " . $e->getMessage());
	}
}

// ---- 2) Sueltos ----
foreach ($standalone as $sku => $row) {
	if ($max > 0 && $nInserted >= $max) { logMsg("Alcanzado límite max=$max, parando."); break; }

	$mfgId = resolveManufacturer($mysqli, $row['BRAND'], $mfgCache, $mfgCreated, $dryRun);
	$brandPrefix = seimiBrandPrefix($row['BRAND'], $row['NAME_FR'] !== '' ? $row['NAME_FR'] : $row['NAME_EN']);
	$catId = getOrCreateSubcategory($mysqli, $row['_UNIV_ES'], $row['_UNIV_EN'], NEW_CATEGORY_ID, $dryRun, $subcatCache, $subcatCreated);

	list($nameEs, $descEs, $titleEn, $descEn) = seimiBuildTexts($row, $brandPrefix, $skipTranslation, $dryRun, $translateFail);

	if ($dryRun) {
		$nInserted++; $nStandaloneIns++;
		if ($nStandaloneIns <= 8) logMsg("  WOULD INSERT SUELTO sku=$sku cat=" . $row['_UNIV_ES'] . " name='" . mb_substr($nameEs, 0, 60, 'UTF-8') . "'");
		continue;
	}

	$mysqli->begin_transaction();
	try {
		$model = $row['SUPCODE'] !== '' ? $row['SUPCODE'] : $sku;
		$qmodel = $mysqli->real_escape_string($model);
		$qrefprov = $mysqli->real_escape_string($sku);
		$qean   = $mysqli->real_escape_string($row['EAN']);
		$qmfg   = (int) $mfgId;
		$sql = "INSERT INTO products (products_quantity, check_stock, products_model, products_image, products_price, products_cost, products_date_added, products_weight, products_status, products_tax_class_id, manufacturers_id, product_ean, reference_prov, products_import_origin)
			VALUES (0, 0, \"$qmodel\", \"\", " . number_format($row['_PRICE'], 4, '.', '') . ", " . number_format($row['_COST'], 4, '.', '') . ", NOW(), {$row['_WEIGHT']}, 2, " . TAX_CLASS_IVA21 . ", $qmfg, \"$qean\", \"$qrefprov\", \"" . ORIGIN_FLAG . "\")";
		if (!$mysqli->query($sql)) throw new Exception("products: " . $mysqli->error);
		$pid = (int) $mysqli->insert_id;

		$qNameEs = $mysqli->real_escape_string($nameEs);
		$qDescEs = $mysqli->real_escape_string($descEs);
		$qNameEn = $mysqli->real_escape_string($titleEn);
		$qDescEn = $mysqli->real_escape_string($descEn);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_ES . ", \"$qNameEs\", \"$qDescEs\", 0)")) throw new Exception("desc ES: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_description (products_id, language_id, products_name, products_description, products_viewed) VALUES ($pid, " . LANG_ID_EN . ", \"$qNameEn\", \"$qDescEn\", 0)")) throw new Exception("desc EN: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_to_categories (products_id, categories_id) VALUES ($pid, " . ($catId > 0 ? $catId : NEW_CATEGORY_ID) . ")")) throw new Exception("p2c: " . $mysqli->error);
		if (!$mysqli->query("INSERT INTO products_groups (customers_group_id, products_id, customers_group_price, products_qty_blocks, products_min_order_qty) VALUES (" . G1_GROUP_ID . ", $pid, " . number_format($row['_G1'], 4, '.', '') . ", 1, 1)")) throw new Exception("g1: " . $mysqli->error);

		$mysqli->commit();

		list($imgOk, $subs) = seimiDownloadImages($mysqli, $pid, $row, $nameEs ?: $titleEn);
		if ($imgOk) { $nWithImg++; $nSubImgs += $subs; }
		elseif (empty(array_filter(array_merge([$row['PIC1'], $row['PIC2'], $row['PIC3']], $row['ADDIMGS'])))) { $imgEmpty++; }
		else { $imgFail++; }

		if (!isValidEan13($row['EAN'])) {
			$genEan = generateInternalEan13($pid, EAN_INTERNAL_PREFIX);
			if ($genEan !== '') {
				$mysqli->query("UPDATE products SET product_ean='" . $mysqli->real_escape_string($genEan) . "' WHERE products_id=$pid AND (product_ean IS NULL OR product_ean='' OR LENGTH(product_ean)<>13)");
			}
		}

		$nInserted++; $nStandaloneIns++;
		logMsg("OK SUELTO pid=$pid sku=$sku cat=" . $row['_UNIV_ES'] . " price={$row['_PRICE']} cost={$row['_COST']} g1={$row['_G1']}");
	} catch (Exception $e) {
		$mysqli->rollback();
		$errors++;
		logMsg("ERROR suelto sku=$sku: " . $e->getMessage());
	}
}

logMsg("==================== RESUMEN ====================");
logMsg("Insertados: $nInserted (familias=$nFamiliesIns sueltos=$nStandaloneIns)");
logMsg("Con imagen: $nWithImg (subimágenes bajadas: $nSubImgs) | sin URL: $imgEmpty | fallos descarga: $imgFail");
logMsg("Familias con variantes creadas: $nWithVar");
logMsg("Traducciones FR/EN→ES fallidas: $translateFail");
logMsg("Errores INSERT: $errors");
if (!empty($subcatCreated)) {
	logMsg("Subcategorías " . ($dryRun ? "que se crearían" : "creadas") . ":");
	foreach ($subcatCreated as $v) logMsg("  · $v");
}
if (!empty($mfgCreated)) {
	logMsg("Manufacturers " . ($dryRun ? "que se crearían" : "creados") . " (" . count($mfgCreated) . "):");
	foreach ($mfgCreated as $k => $v) logMsg("  · $v");
}

end_action:
?>
	</div>
	<p style="margin-top:15px;">
		<a href="<?php echo tep_href_link('import-seimi-altas.php'); ?>" class="xbutton small hv9">← Volver</a>
	</p>
<?php else: ?>
	<h2>Importador SEIMI / Alliance Marine (altas) — variantes por Parent_id</h2>
	<p>
		Descarga <code><?php echo SEIMI_SFTP_REMOTE; ?></code> del SFTP de Alliance Marine (Azure), agrupa SKUs por
		<code>Parent_id</code> del feed <strong>solo si comparten imagen</strong> (Picture_1 idéntica; el Parent_id del feed
		agrupa series de productos distintos) + heurística LCP, e inserta lo que no exista en BD bajo
		<strong>SEIMI Nuevos</strong> (cat <?php echo NEW_CATEGORY_ID; ?>) en
		subcategorías de staging por <strong>Universo</strong> (Electricidad, Mecánica, Fondeo…), todo oculto (status 0/2).
	</p>
	<form method="get" style="background:#f5f5f5;padding:15px;border-radius:5px;">
		<p>
			<strong>Universo</strong>:
			<select name="universe" style="min-width:260px;">
				<option value="all">— Todos —</option>
				<?php
				$univList = file_exists(SEIMI_CSV) ? listUniversesFromCsv(SEIMI_CSV) : [];
				foreach ($univList as $u => $cnt) {
					$sel = ($selectedUniv === $u) ? ' selected' : '';
					echo '<option value="' . htmlspecialchars($u) . '"' . $sel . '>' . htmlspecialchars($u) . ' (' . $cnt . ')</option>';
				}
				?>
			</select>
			&nbsp;&nbsp;<strong>Marca</strong>:
			<select name="brand" style="min-width:300px;">
				<option value="all">— Todas las marcas —</option>
				<?php
				$brandList = file_exists(SEIMI_CSV) ? listBrandsFromCsv(SEIMI_CSV) : [];
				foreach ($brandList as $b => $cnt) {
					$sel = ($selectedBrand === $b) ? ' selected' : '';
					echo '<option value="' . htmlspecialchars($b) . '"' . $sel . '>' . htmlspecialchars($b) . ' (' . $cnt . ')</option>';
				}
				?>
			</select>
			<?php if (!file_exists(SEIMI_CSV)): ?>
				<em style="color:#a00;">— CSV no descargado todavía; ejecuta una vez para popular los desplegables.</em>
			<?php endif; ?>
		</p>
		<p>
			<strong>SKUs específicos</strong> (opcional; acepta SKU SEIMI o referencia de fabricante; ignora filtros y máximo; si es variante importa toda la familia):<br>
			<textarea name="codes" rows="3" style="width:100%;font-family:monospace;" placeholder="Uno o varios SKUs separados por coma, espacio o salto de línea."><?php echo htmlspecialchars($onlyCodesRaw); ?></textarea>
		</p>
		<p>
			<label><input type="checkbox" name="dry_run" value="1" checked> Dry-run (sin cambios)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_translation" value="1"> Saltar traducción LLM FR/EN→ES (mucho más rápido; queda en francés para retraducir)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_variants" value="1"> Modo legacy: ignorar Parent_id (cada SKU como suelto)</label>
		</p>
		<p>
			<label><input type="checkbox" name="skip_download" value="1"> No descargar el feed del SFTP (usar copia local)</label>
		</p>
		<p>
			Inserts máximos por ejecución (0 = sin límite):
			<input type="number" name="max" value="50" min="0" style="width:80px;">
		</p>
		<button type="submit" name="action" value="dry_run" class="xbutton small hv9">Dry-run</button>
		<button type="submit" name="action" value="execute" class="xbutton small hv9 verde" onclick="return confirm('¿Ejecutar de verdad? Insertará productos en la BD.');">Ejecutar</button>
	</form>
	<p style="margin-top:20px;color:#888;font-size:12px;">
		<strong>Reglas aplicadas:</strong><br>
		- <code>products_cost</code> = Net_price_ex_VAT (neto negociado, sin IVA).<br>
		- <code>products_price</code> = Public_price_ex_VAT si &gt; cost; si no, cost × 2. Redondeo a múltiplo de 0,05 con IVA.<br>
		- G1 con tiers según margen real, piso <code>cost × <?php echo G1_FLOOR_FACTOR; ?></code>.<br>
		- Solo <code>Product_status=Active</code> (End of life / Dead se saltan).<br>
		- <code>products_model</code> = referencia del fabricante (supplier_item_code); <code>reference_prov</code> = SKU SEIMI (código de pedido).<br>
		- Variantes en <code>products_attributes</code> con <code>options_id=<?php echo VARIANT_OPTION_ID; ?></code> (Modelo). Padre = SKU más barato. Familias sin EAN máster. <strong>Solo se agrupan SKUs con la MISMA imagen</strong> (sub-grupos por Picture_1 dentro de cada Parent_id).<br>
		- Tablas de especificaciones del feed → líneas "Etiqueta: valor" (valor métrico; conversión imperial omitida).<br>
		- Etiqueta variante: resto del título tras prefijo común → medida → SKU. En ES y EN por separado: diccionario FR→ES/EN + <strong>fallback LLM</strong> (cacheado) para texto libre ("brut équipé"…). Con skip_translation queda el diccionario solo.<br>
		- <strong>Familias: tabla "Variantes y especificaciones"</strong> al final de la descripción (Referencia | Modelo | specs del feed | Peso si difiere), estilo tabla Osculati, entre marcadores SEIMI_SPECS. Si el cuerpo era solo la tabla de specs del feed, se omite (la tabla de familia ya lo cubre).<br>
		- Idiomas: ES traducido del FR (o EN) vía LLM; EN de las columnas _EN (fallback FR).<br>
		- Imagen: principal + hasta <?php echo MAX_SUBIMAGES; ?> subimágenes (Picture_1-3 + additional_images).<br>
		- EAN: Barcode normalizado (UPC-12→EAN-13); si no pasa checksum → interno prefijo <?php echo EAN_INTERNAL_PREFIX; ?>.<br>
		- Stock NO se toca (<code>products_quantity=0, check_stock=0</code>, status=2 pendiente de revisión).<br>
		- <strong>Brands excluidos</strong> (importador propio): <?php echo implode(', ', BRAND_BLACKLIST); ?>.<br>
		- Brand vacío → fabricante "<strong><?php echo DEFAULT_BRAND; ?></strong>". Prefijo de marca en el título si no lo lleva ya.<br>
		- Pirotecnia / mercancía peligrosa: NO se filtran, se listan en el log para revisión.<br>
		- Output en streaming en tiempo real.
	</p>
<?php endif; ?>
</div>
</td></tr></table>
<?php require THEME . 'html/footer.php'; ?>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
