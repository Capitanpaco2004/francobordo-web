<?php
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use util\tools;

////
// The HTML href link wrapper function
function tep_href_link($page = '', $parameters = '', $connection = 'NONSSL') {

    if ($page == '') {
      die('</td></tr></table></td></tr></table><br><br><font color="#ff0000"><b>Error!</b></font><br><br><b>Unable to determine the page link!<br><br>Function used:<br><br>tep_href_link(\'' . $page . '\', \'' . $parameters . '\', \'' . $connection . '\')</b>');
    }

    // Usar protocolo actual para evitar mixed content
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $link = $scheme . '://' . $_SERVER['HTTP_HOST'] . DIR_WS_ADMIN;
    $link = $parameters == '' ? $link . $page : $link . $page . '?' . $parameters;

    while ( (str_ends_with($link, '&')) || (str_ends_with($link, '?')) ) $link = substr($link, 0, -1);

    return $link;
  }

function tep_catalog_href_link($page = '', $parameters = '', $connection = 'NONSSL') {
	if ($connection == 'NONSSL') {
		$link = HTTP_CATALOG_SERVER . DIR_WS_CATALOG;
	} else if ($connection == 'SSL') {
		if (ENABLE_SSL_CATALOG == 'true') {
			$link = HTTPS_CATALOG_SERVER . DIR_WS_CATALOG;
		} else {
			$link = HTTP_CATALOG_SERVER . DIR_WS_CATALOG;
		}
	} else {
		die('</td></tr></table></td></tr></table><br><br><font color="#ff0000"><b>Error!</b></font><br><br><b>Unable to determine connection method on a link!<br><br>Known methods: NONSSL SSL<br><br>Function used:<br><br>tep_href_link(\'' . $page . '\', \'' . $parameters . '\', \'' . $connection . '\')</b>');
	}
	if ($parameters == '') {
		$link .= $page;
	} else {
		$link .= $page . '?' . $parameters;
	}

	while ((substr($link, -1) == '&') || (substr($link, -1) == '?')) $link = substr($link, 0, -1);

	return $link;
}


function tep_image_thumb($sImagen, $nWidth, $nHeight, $sDefault = '') {
	// Variables
	$bDelete = (!empty( $_GET['delete'] ) ? $_GET['delete'] : 'false');

	// Crear una instancia del gestor de imágenes con el driver GD
	$manager = new ImageManager(new Driver());

	// Definir el formato y la calidad basados en constantes o variables globales
	$format = strtolower(defined('IMAGEN_TIPO_IMAGEN') ? IMAGEN_TIPO_IMAGEN : 'JPG');
	$quality = intval(defined('IMAGEN_COMPRESS_IMAGE') ? IMAGEN_COMPRESS_IMAGE : 75);

	// FIX ruta web vs FS (perf categories.php jul-2026): los callers pasan una ruta WEB
	// ('/images/...') pero las operaciones de disco (file_exists/mkdir/save/filemtime)
	// necesitan ruta de FICHERO. Antes se usaba la ruta web como ruta FS -> file_exists
	// SIEMPRE false -> se regeneraba el thumb con GD en CADA llamada (~18 ms/fila) y se
	// devolvia el placeholder. Mapear WEB->FS con DIR_FS_CATALOG; las rutas relativas
	// (fallback no_image, o callers que pasan '../images/...') se dejan intactas porque
	// ya resuelven desde el CWD (comportamiento anterior de esos callers sin cambios).
	$fnToFs = function($p) {
		if ($p === '' || $p[0] !== '/') return $p;                                    // relativa -> intacta
		if (defined('DIR_FS_CATALOG') && strpos($p, DIR_FS_CATALOG) === 0) return $p;  // ya es FS bajo docroot
		return (defined('DIR_FS_CATALOG') ? rtrim(DIR_FS_CATALOG, '/') : '') . '/' . ltrim($p, '/');
	};

	// Directorio del thumbnail y preparación del nombre del archivo (nombre derivado de la ruta WEB)
	$pathInfo = pathinfo($sImagen);
	$sPathThumbnail = $pathInfo['dirname'] . '/thumbnails/';
	$sFileNameThumb = $pathInfo['filename'] . '_thumb_' . $nWidth . 'x' . $nHeight;
	$fullPathThumb = $sPathThumbnail . $sFileNameThumb . '.' . $format;      // ruta WEB (para el <img src>)
	$fullPathThumbWebP = $sPathThumbnail . $sFileNameThumb . '.webp';

	// Equivalentes en FS para operar en disco
	$sImagenFs           = $fnToFs($sImagen);
	$sPathThumbnailFs    = $fnToFs($sPathThumbnail);
	$fullPathThumbFs     = $fnToFs($fullPathThumb);
	$fullPathThumbWebPFs = $fnToFs($fullPathThumbWebP);

	// Asegurar la creación del directorio de miniaturas
	if (!is_dir($sPathThumbnailFs)) {
		@mkdir($sPathThumbnailFs, 0777, true);
	}

	// Verificar si la imagen original existe
	if (!file_exists($sImagenFs)) {
		// Si no existe, usar la defaultImage para generar el thumbnail
		$sImagen = $sDefault ?: '../theme/web/images/general/no_image.jpg';
		$sImagenFs = $fnToFs($sImagen);
	}

	// Si tenemos thumb, pero fue creada antes que la imagen normal, la borramos.
	if( file_exists( $fullPathThumbFs ) && file_exists( $sImagenFs ) ) {
		if (filemtime($fullPathThumbFs) < filemtime($sImagenFs)) {
			@unlink($fullPathThumbFs);
			@unlink($fullPathThumbWebPFs);
		}
	}

	// Si existe la imagen del thumb cargamos desde la cache y no queremos eliminarla
	if( file_exists( $fullPathThumbFs ) && file_exists( $fullPathThumbWebPFs ) && $bDelete == 'false' )
	{
		// Mostramos la imagen ya guardada (se devuelve la ruta WEB para el <img src>)
		return $fullPathThumb;
	}

	// Si la imagen existe y deseamos eliminarla
	if (file_exists($fullPathThumbFs) && $bDelete == 'true') {
		@unlink($fullPathThumbFs);
		@unlink($fullPathThumbWebPFs);
	}

	try {
		// Cargar la imagen original
		$image = $manager->read($sImagenFs);

		// Redimensionar la imagen manteniendo el aspecto original
		$image->pad($nWidth, $nHeight);

		// encode edited image
		if( $format == 'png' ) {
			$image->toPng(true)->save($fullPathThumbFs);
		} elseif ($format == 'gif') {
			$image->toGif($quality)->save($fullPathThumbFs);
		}elseif ($format == 'jpg'){
			$image->toJpeg($quality)->save($fullPathThumbFs);
		}
		$image->toWebp($quality)->save($fullPathThumbWebPFs);

	} catch (Exception $e) {
		// Manejo de errores
		return $sDefault ?: '../theme/web/images/general/no_image.jpg';
	}

	return $fullPathThumb . (defined('CACHE_IMAGE_VERSION') && CACHE_IMAGE_VERSION == 'true' ? '?v=' . filemtime($sImagenFs) : '');
}

// "On the Fly" Auto Thumbnailer using GD Library, servercaching and browsercaching
// Scales product images dynamically, resulting in smaller file sizes, and keeps
// proper image ratio. Used in conjunction with product_thumb.php t/n generator.
function tep_image($src, $alt = '', $width = '', $height = '', $params = '', $bSize = true) {

    // Set default image variable and code
    $image = '<img src="' . $src . '"';

	if( ! $bSize )
	{
		$image = '<img src="' . tep_image_thumb( $src, $width, $height ) . '"';
	}

    // Add remaining image parameters if they exist
    if ($width) {
        $image .= ' width="' . tep_output_string($width) . '"';
    }

    if ($height) {
        $image .= ' height="' . tep_output_string($height) . '"';
    }

    if (tep_not_null($params)) $image .= ' ' . $params;

    $image .= ' border="0" alt="' . tep_output_string($alt) . '"';

    if (tep_not_null($alt)) {
  		$image .= ' title="' . tep_output_string($alt) . '"';
	}

    $image .= ' />';

    return $image;
}

function convertImageToWebP($sImagen, $sPathDestination, $quality=80) {
	// Informacion de la imagen
	$aImageInfo = @getimagesize( $sImagen );

	// Segun el mime realizamos una instancia diferente
	switch( $aImageInfo['mime'] )
	{
		case 'image/jpeg':
		case 'image/jpg':
			$gdImage = imagecreatefromjpeg( $sImagen );
			break;
		case 'image/gif':
			$gdImage = imagecreatefromgif( $sImagen );
			break;
		case 'image/png':
			$gdImage = imagecreatefrompng( $sImagen );
			imagepalettetotruecolor($gdImage);
			imagealphablending($gdImage, true);
			imagesavealpha($gdImage, true);
			break;
		default:
			return false;
	}
	return imagewebp($gdImage, $sPathDestination, $quality);
}

////
// The HTML form submit button wrapper function
// Outputs a button in the selected language
  function tep_image_submit($image, $alt = '', $parameters = '') {
    global $language;

	if ($alt !== '') {
		return '<input type="submit" class="button_admin_new" value="' . $alt . '" />';
	} else {
		$image_submit = '<input type="image" src="' . tep_output_string(DIR_WS_LANGUAGES . $language . '/images/buttons/' . $image) . '" border="0" alt="' . tep_output_string($alt) . '"';

		if (tep_not_null($alt)) {
            $image_submit .= ' title=" ' . tep_output_string($alt) . ' "';
        }

		if (tep_not_null($parameters)) {
            $image_submit .= ' ' . $parameters;
        }

		return $image_submit . ' />';
	}
  }

////
// Draw a 1 pixel black line
  function tep_black_line() {
    return tep_image(DIR_WS_IMAGES . 'pixel_black.png', '', '100%', '1');
  }

////
// Output a separator either through whitespace, or with an image
  function tep_draw_separator($image = 'pixel_black.png', $width = '100%', $height = '1') {
    return tep_image(DIR_WS_IMAGES . $image, '', $width, $height);
  }

////
// Output a function button in the selected language
  function tep_image_button($image, $alt = '', $params = '') {
    global $language;

	if ($alt !== '' && pathinfo((string) $image, PATHINFO_EXTENSION) !== 'gif') {
		return '<span class="button_admin_new">' . $alt . '</span>';
	} else {
		return tep_image(DIR_WS_LANGUAGES . $language . '/images/buttons/' . $image, $alt, '', '', $params);
	}
  }

////
// javascript to dynamically update the states/provinces list when the country is changed
// TABLES: zones
  function tep_js_zone_list($country, $form, $field) {
    $countries_query = tep_db_query("select distinct zone_country_id from " . TABLE_ZONES . " order by zone_country_id");
    $num_country = 1;
    $output_string = '';
    while ($countries = tep_db_fetch_array($countries_query)) {
      if ($num_country == 1) {
        $output_string .= '  if (' . $country . ' == "' . $countries['zone_country_id'] . '") {' . "\n";
      } else {
        $output_string .= '  } else if (' . $country . ' == "' . $countries['zone_country_id'] . '") {' . "\n";
      }

      $states_query = tep_db_query("select zone_name, zone_id from " . TABLE_ZONES . " where zone_country_id = '" . $countries['zone_country_id'] . "' order by zone_name");

      $num_state = 1;
      while ($states = tep_db_fetch_array($states_query)) {
        if ($num_state == '1') {
            $output_string .= '    ' . $form . '.' . $field . '.options[0] = new Option("' . PLEASE_SELECT . '", "");' . "\n";
        }
        $output_string .= '    ' . $form . '.' . $field . '.options[' . $num_state . '] = new Option("' . $states['zone_name'] . '", "' . $states['zone_id'] . '");' . "\n";
        $num_state++;
      }
      $num_country++;
    }

    return $output_string . ('  } else {' . "\n" . '    ' . $form . '.' . $field . '.options[0] = new Option("' . TYPE_BELOW . '", "");' . "\n" . '  }' . "\n");
  }

////
// Output a form
  function tep_draw_form($name, $action, $parameters = '', $method = 'post', $params = '') {
    $form = '<form name="' . tep_output_string($name) . '" action="';
    if (tep_not_null($parameters)) {
      $form .= tep_href_link($action, $parameters);
    } else {
      $form .= tep_href_link($action);
    }
    $form .= '" method="' . tep_output_string($method) . '"';
    if (tep_not_null($params)) {
      $form .= ' ' . $params;
    }

    return $form . '>';
  }

////
// Output a form input field
  function tep_draw_input_field($name, $value = '', $parameters = '', $required = false, $type = 'text', $reinsert_value = true) {
    global $_GET, $_POST;

    $field = '<input type="' . tep_output_string($type) . '" name="' . tep_output_string($name) . '" ';

    if ( ($reinsert_value == true) && ( (isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])) ) ) {
      if (isset($_GET[$name]) && is_string($_GET[$name])) {
        $value = stripslashes($_GET[$name]);
      } elseif (isset($_POST[$name]) && is_string($_POST[$name])) {
        $value = stripslashes($_POST[$name]);
      }
    }

    if (tep_not_null($value)) {
      $field .= ' value="' . tep_output_string($value) . '"';
    }

    if (tep_not_null($parameters)) {
        $field .= ' ' . $parameters;
    }

    $field .= ' />';

    if ($required == true) {
        $field .= TEXT_FIELD_REQUIRED;
    }

    return $field;
  }

////
// Output a form password field
  function tep_draw_password_field($name, $value = '', $required = false) {
    return tep_draw_input_field($name, $value, 'maxlength="40"', $required, 'password', false);
  }

////
// Output a form filefield
  function tep_draw_file_field($name, $required = false, $parameters = '') {
    return tep_draw_input_field($name, '', $parameters, $required, 'file');
  }

////
// Output a selection field - alias function for tep_draw_checkbox_field() and tep_draw_radio_field()
  function tep_draw_selection_field($name, $type, $value = '', $checked = false, $compare = '', $params = '') {
    global $_GET, $_POST;

    $selection = '<input ' . $params . ' type="' . tep_output_string($type) . '" name="' . tep_output_string($name) . '"';

    if (tep_not_null($value)) {
        $selection .= ' value="' . tep_output_string($value) . '"';
    }

    if ( ($checked == true) || (isset($_GET[$name]) && is_string($_GET[$name]) && (($_GET[$name] === 'on') || (stripslashes($_GET[$name]) == $value))) || (isset($_POST[$name]) && is_string($_POST[$name]) && (($_POST[$name] === 'on') || (stripslashes($_POST[$name]) == $value))) || (tep_not_null($compare) && ($value == $compare)) ) {
      $selection .= ' checked="checked"';
    }

    return $selection . ' />';
  }

////
// Output a form checkbox field
  function tep_draw_checkbox_field($name, $value = '', $checked = false, $compare = '', $params = '') {
    return tep_draw_selection_field($name, 'checkbox', $value, $checked, $compare, $params);
  }

////
// Output a form radio field
  function tep_draw_radio_field($name, $value = '', $checked = false, $compare = '') {
    return tep_draw_selection_field($name, 'radio', $value, $checked, $compare);
  }

////
// Output a form textarea field
  function tep_draw_textarea_field($name, $wrap, $width, $height, $text = '', $parameters = '', $reinsert_value = true) {
    global $_GET, $_POST;

     $field = '<textarea name="' . tep_output_string($name) . '" id="' . tep_output_string($wrap) . '" cols="' . tep_output_string($width) . '" rows="' . tep_output_string($height) . '"';

    if (tep_not_null($parameters)) {
        $field .= ' ' . $parameters;
    }

    $field .= '>';

    if ( ($reinsert_value == true) && ( (isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])) ) ) {
      if (isset($_GET[$name]) && is_string($_GET[$name])) {
        $field .= tep_output_string_protected(stripslashes($_GET[$name]));
      } elseif (isset($_POST[$name]) && is_string($_POST[$name])) {
        $field .= tep_output_string_protected(stripslashes($_POST[$name]));
      }
    } elseif (tep_not_null($text)) {
      $field .= tep_output_string_protected($text);
    }

    return $field . '</textarea>';
  }

////
// Output a form textarea field w/ fckeditor
  function tep_draw_fckeditor($name, $width, $height, $text) {

	$oFCKeditor = new FCKeditor($name);
	$oFCKeditor -> Width  = $width;
	$oFCKeditor -> Height = $height;
	$oFCKeditor -> BasePath	= 'fckeditor/';
	$oFCKeditor -> Value = $text;

    return $oFCKeditor->Create($name);
  }

////
// Output a form hidden field
  function tep_draw_hidden_field($name, $value = '', $parameters = '') {
    global $_GET, $_POST;

    $field = '<input type="hidden" name="' . tep_output_string($name) . '"';

    if (tep_not_null($value)) {
      $field .= ' value="' . tep_output_string($value) . '"';
    } elseif ( (isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])) ) {
      if ( (isset($_GET[$name]) && is_string($_GET[$name])) ) {
        $field .= ' value="' . tep_output_string(stripslashes($_GET[$name])) . '"';
      } elseif ( (isset($_POST[$name]) && is_string($_POST[$name])) ) {
        $field .= ' value="' . tep_output_string(stripslashes($_POST[$name])) . '"';
      }
    }

    if (tep_not_null($parameters)) {
        $field .= ' ' . $parameters;
    }

    return $field . ' />';
  }

////
// Hide form elements
function tep_hide_session_id() {
	$string = '';

	// Verifica si la sesión está activa y si tiene un ID válido
	if (session_id() && tep_not_null(session_id())) {
		$string = tep_draw_hidden_field(session_name(), session_id());
	}

	return $string;
}

  function tep_draw_pull_down_version(){
	  global $configurationOption;

	  $versionLast = $configurationOption['configuration_value'];
	  $versionNext = tools::version($versionLast);

	  return tep_draw_pull_down_menu($configurationOption['configuration_key'], [
		  ['id' => $versionLast, 'text' => $versionLast],
		  ['id' => $versionNext, 'text' => $versionNext]
	  ], $versionLast);
  }

////
// Output a form pull down menu
function tep_draw_pull_down_menu($name, $values, $default = '', $parameters = '', $required = false)
{
	global $_GET, $_POST, $configurationOption;
	$name = isset($configurationOption) ? $configurationOption['configuration_key'] : $name;

	$field = '<select OnMouseWheel="return false;" name="' . tep_output_string($name) . '"' . (tep_not_null($parameters) ? ' ' . $parameters : '') . '>';

	if (empty($default) && ((isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])))) {
		if (isset($_GET[$name]) && is_string($_GET[$name])) {
			$default = stripslashes($_GET[$name]);
		} elseif (isset($_POST[$name]) && is_string($_POST[$name])) {
			$default = stripslashes($_POST[$name]);
		}
	}

	foreach ($values as $item) {
		if (isset($item['id'])) {
			$field .= '<option value="' . tep_output_string($item['id']) . '"';
			if ($default == $item['id']) {
				$field .= ' selected="selected"';
			}
			$field .= '>' . tep_output_string($item['text'], ['"' => '&quot;', '\'' => '&#039;', '<' => '&lt;', '>' => '&gt;']) . '</option>';
		}
	}


	$field .= '</select>';

	if ($required == true) {
		$field .= TEXT_FIELD_REQUIRED;
	}

	return $field;
}
////
// Creates a pull-down list for dates
function tep_draw_pull_down_date($day='', $month='', $year='', $full=false, $starty='', $name='date'){
	if ($day=='') {$day=date('d');}
	if ($month=='') {$month=date('m');}
	if ($year=='') {$year=date('Y');}
	$eyear=date('Y');
	if ($starty == '') {$starty=date('Y')-1; $eyear=date('Y')+2;}

	// Array for days
$days=[];
$days[] = ['id' => '00', 'text' => 'not'];
for($i=1; $i<=31; $i++){
$j = strlen($i)!= 2 ? '0' . $i : $i;
$days[] = ['id' => $j, 'text' => $j]; }

$months[] = ['id' => '00', 'text' => 'set'];
for($i=1; $i<=12; $i++){
$j = strlen($i)!= 2 ? '0' . $i : $i;
$months[] = ['id' => $j, 'text' => $j]; }

for($i=$starty; $i<=$eyear; $i++){
$j = $i;
if (!$full) {
$y = $i - 2000;
$j = strlen($y)!= 2 ? '0' . $y : $y; }
$years[] = ['id' => $i, 'text' => $j]; }

// mm dd yy contries = 38 canada,139 Micronesia,163 Palau,168 Philippines,223 & 224 United States
if(STORE_COUNTRY == 223 || STORE_COUNTRY == 224 || STORE_COUNTRY == 38 || STORE_COUNTRY == 139 || STORE_COUNTRY == 163 || STORE_COUNTRY == 168) {
echo tep_draw_pull_down_menu('select_month', $months, $month);
echo tep_draw_pull_down_menu('select_day', $days, $day); }
else {
echo tep_draw_pull_down_menu('select_day', $days, $day);
echo tep_draw_pull_down_menu('select_month', $months, $month); }
echo tep_draw_pull_down_menu('select_year', $years, $year);

return $_POST['select_day'].'/'.$_POST['select_month'].'/'.$_POST['select_year'];
	}

function tep_draw_textarea_field_tinymce($name, $wrap, $width, $height, $text = '', $parameters = '', $reinsert_value = true) {

	$hasClass = (strpos($parameters, 'class=') !== false);
	$field = '<textarea' . (!$hasClass ? ' class="tinymce"' : '') . ' name="' . tep_output_string($name) . '" wrap="' . tep_output_string($wrap) . '" cols="' . tep_output_string($width) . '" rows="' . tep_output_string($height) . '"';

	if (tep_not_null($parameters)) {
		$field .= ' ' . $parameters;
	}

	$field .= '>';

	if ((isset($GLOBALS[$name])) && ($reinsert_value == true)) {
		$field .= tep_output_string_protected(stripslashes($GLOBALS[$name]));
	} else if (tep_not_null($text)) {
		$field .= tep_output_string_protected($text);
	}

	$field .= '</textarea>';

	return $field;
}

function getScriptsTinyMce()
{
	static $tinymceLoaded = false;
	if ($tinymceLoaded) return '';

	$output = '';
	$output .= '<script src="../includes/vendor/tinymce/tinymce/tinymce.min.js"></script>';
	$output .= '<script src="includes/modules/tinymce/tinymce-es.js"></script>';
	$output .= '<script src="includes/modules/tinymce/tinymce-init.js"></script>';

	$tinymceLoaded = true;
	return $output;
}
  // Output a form muliple select menu
function tep_draw_mselect_menu($name, $values, $selected_vals = [], $params = '', $required = false) {
    $field = '<select name="' . $name . '"';
    if ($params) {
        $field .= ' ' . $params;
    }
    $field .= ' multiple>';
    $counter = count($values);
    for ($i=0; $i<$counter; $i++) {
	//if ($values[$i]['id'])
      $field .= '<option value="' . $values[$i]['id'] . '"';
      if( $values[$i]['id'] )
		{
    	if ( ((strlen((string) $values[$i]['id']) > 0) && ($GLOBALS[$name] == $values[$i]['id'])) ) {
    	  $field .= ' SELECTED';
    	}
  		else
		{
			for ($j=0; $j<count($selected_vals); $j++) {
				if ($selected_vals[$j]['id'] == $values[$i]['id'])
				{
			        $field .= ' SELECTED';
				}
			}
		}
		}
      $field .= '>' . $values[$i]['text'] . '</option>';
    }
    $field .= '</select>';
    if ($required) {
        $field .= TEXT_FIELD_REQUIRED;
    }
    return $field;
  }

function tep_draw_button($title = null, $icon = null, $link = null, $priority = null, $params = null) {
	static $button_counter = 1;

	$types = ['submit', 'button', 'reset'];

	if (!isset($params['type'])) {
		$params['type'] = 'submit';
	}

	if (!in_array($params['type'], $types)) {
		$params['type'] = 'submit';
	}

	if (($params['type'] == 'submit') && isset($link)) {
		$params['type'] = 'button';
	}

	if (!isset($priority)) {
		$priority = 'secondary';
	}
	if (!isset($params['id'])) {
		$idbutton = "tdb" . $button_counter . "";
	} else {
		$idbutton       = $params['id'];
		$button_counter -= 1;
	}
	$button = '<span class="tdbLink">';
	if (($params['type'] == 'button') && isset($link)) {
		$button .= '<a id="' . $idbutton . '" href="' . $link . '"';
		if (isset($params['newwindow'])) {
			$button .= ' target="_blank"';
		}
	} else {
		$button .= '<button id="' . $idbutton . '" type="' . tep_output_string($params['type']) . '"';
		if (isset($params['params'])) {
			$button .= ' ' . $params['params'];
		}
	}
	$button .= '>' . $title;

	if (($params['type'] == 'button') && isset($link)) {
		$button .= '</a>';
	} else {
		$button .= '</button>';
	}
	$button .= '</span><script type="text/javascript">$("#' . $idbutton . '").button(';
	$args   = [];

	if (isset($icon)) {
		if (!isset($params['iconpos'])) {
			$params['iconpos'] = 'left';
		}

		if ($params['iconpos'] == 'left') {
			$args[] = 'icons:{primary:"ui-icon-' . $icon . '"}';
		} else {
			$args[] = 'icons:{secondary:"ui-icon-' . $icon . '"}';
		}
	}

	if (empty($title)) {
		$args[] = 'text:false';
	}

	// modification : desactivation d'un bouton
	if (isset($params['disabled'])) {
		$args[]   = 'disabled: true';
		$activate = 'false';
	}
	if (!empty($args)) {
		$button .= '{' . implode(',', $args) . '}';
	}
	$activate = $activate ?? '';
	$button .= ').addClass("ui-priority-' . $priority . '").click(function() {return ' . $activate . ';}).parent().removeClass("tdbLink");</script>';
	$button_counter++;

	return $button;
}

function ajax_get_cities_html($country = 0, $zone = false, $cp = false, $selected = true, $return = false) {
	//$output = '<label for="city">'.ENTRY_CITY.'</label>';
	$output = '';
	$name   = ($_GET['name'] != '' ? $_GET['name'] : 'city_id');

	$cities_array = [];
	$sql          = false;
	if ((int)$zone > 0)
		$sql = "SELECT id, name, cp, id_zone, id_country FROM cities WHERE id_zone = '" . (int)$zone . "' AND id_country = '" . $country . "' ORDER BY name";

	if ((int)$cp > 0)
		$sql = "SELECT id, name, cp, id_zone, id_country FROM cities WHERE cp = '" . $cp . "' AND id_country = '" . $country . "' ORDER BY name";


	if ($sql != false) {
		$zones_query    = tep_db_query($sql);
		$cities_array[] = ['id' => '0', 'text' => (defined('PULL_DOWN_DEFAULT') ? PULL_DOWN_DEFAULT : 'Seleccione...')];

		while ($zones_values = tep_db_fetch_array($zones_query)) {
			$cities_array[] = ['id' => $zones_values['id'], 'text' => $zones_values['name'] . ' [' . $zones_values['cp'] . ']'];
			$id_zone        = (int)$zones_values['id_zone'];
			$id_country     = (int)$zones_values['id_country'];
		}

		if (tep_db_num_rows($zones_query) == 1)
			$selected = $cities_array[1]['id'];

		if (tep_db_num_rows($zones_query))
			$output .= tep_draw_pull_down_menu($name, $cities_array, (int)$selected, ' class="select2" ');
		else {
			$output .= tep_draw_input_field('city', '');
		}
	} else {
		$cities_array[] = ['id' => 0, 'text' => 'Debe seleccionar una provincia o código postal'];
		$output         .= tep_draw_pull_down_menu($name, $cities_array, (int)$selected);
	}

	if ($return == false) {
		header('Content-type: text/html; charset=' . CHARSET);
		echo json_encode(['cities' => $output, 'id_zone' => $id_zone, 'id_country' => $id_country, 'sql' => $sql, 'selected' => $selected]);
	} else
		return $output;
}
