<?php
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

////
// The HTML href link wrapper function
if (SEO_ENABLED == 'true') { //run chemo's code
	function tep_href_link($page = '', $parameters = '', $connection = 'NONSSL', $add_session_id = true, $search_engine_safe = true) {
		global $seo_urls;

		if (ENABLE_SSL == 'true')
			$connection = 'SSL';

		if (!is_object($seo_urls)) {
			if (!class_exists('SEO_URL')) {
				include_once($_SERVER['DOCUMENT_ROOT'] . '/' . DIR_WS_CLASSES . 'seo.class.php');
			}
			global $languages_id;
			$seo_urls = new SEO_URL($languages_id);
		}
		return preg_replace('/&/', '&', $seo_urls->href_link($page, $parameters, $connection, $add_session_id));
	}
} else { //run original code
// The HTML href link wrapper function
  function tep_href_link($page = '', $parameters = '', $connection = 'NONSSL', $add_session_id = true, $search_engine_safe = true) {
    global $request_type, $SID, $sessionCore;

    if (ENABLE_SSL == 'true')
		$connection = 'SSL';
    if (!tep_not_null($page)) {
      die('<div class="mensaje"><strong>Unable to determine the page link!</strong></div>');
    }

    if ($connection == 'NONSSL') {
      $link = HTTP_SERVER . DIR_WS_HTTP_CATALOG;
    } elseif ($connection == 'SSL') {
      if (ENABLE_SSL == true) {
        $link = HTTPS_SERVER . DIR_WS_HTTPS_CATALOG;
      } else {
        $link = HTTP_SERVER . DIR_WS_HTTP_CATALOG;
      }
    } else {
      die('<div class="mensaje">Unable to determine connection method on a link!<br /><br />Known methods: NONSSL SSL</strong></div>');
    }

    if (tep_not_null($parameters)) {
      $link .= $page . '?' . tep_output_string($parameters);
      $separator = '&';
    } else {
      $link .= $page;
      $separator = '?';
    }

    while ( (substr($link, -1) == '&') || (substr($link, -1) == '?') ) $link = substr($link, 0, -1);

// Add the session ID when moving from different HTTP and HTTPS servers, or when SID is defined
    if ( ($add_session_id == true) && $sessionCore->hasStarted() && (SESSION_FORCE_COOKIE_USE == 'False') ) {
      if (tep_not_null($SID)) {
        $_sid = $SID;
      } elseif ( ( ($request_type == 'NONSSL') && ($connection == 'SSL') && (ENABLE_SSL == true) ) || ( ($request_type == 'SSL') && ($connection == 'NONSSL') ) ) {
        if (HTTP_COOKIE_DOMAIN != HTTPS_COOKIE_DOMAIN) {
          $_sid = tep_session_name() . '=' . tep_session_id();
        }
      }
    }

    if (isset($_sid)) {
      $link .= $separator . tep_output_string($_sid);
    }
    while (strstr($link, '&&')) $link = str_replace('&&', '&', $link);
    if ( (SEARCH_ENGINE_FRIENDLY_URLS == 'true') && ($search_engine_safe == true) ) {

      $link = str_replace('?', '/', $link);
      $link = str_replace('&', '/', $link);
      $link = str_replace('=', '/', $link);

      $separator = '?';
    }

	if (!tep_session_is_registered('customer_id') && ENABLE_PAGE_CACHE == 'true' && class_exists('page_cache')) {
      $link .= $separator . '<osCsid>';
    } elseif (isset($_sid)) {

      $link .= $separator . $_sid;
      $link = str_replace('&', '&amp;', $link);
    }

    return $link;
  }
}

function tep_image_thumb($sImagen, $nWidth, $nHeight, $sDefault = '', $forInstagram = false) {
	// Variables
	$bDelete = (!empty( $_GET['delete'] ) ? $_GET['delete'] : 'false');

	// Crear una instancia del gestor de imágenes con el driver GD
	$manager = new ImageManager(new Driver());

	// Definir el formato y la calidad basados en constantes o variables globales
	$format = strtolower(defined('IMAGEN_TIPO_IMAGEN') ? IMAGEN_TIPO_IMAGEN : 'JPG');
	$quality = intval(defined('IMAGEN_COMPRESS_IMAGE') ? IMAGEN_COMPRESS_IMAGE : 75);

	// Directorio del thumbnail y preparación del nombre del archivo
	$pathInfo = pathinfo($sImagen);
	$sPathThumbnail = $pathInfo['dirname'] . '/thumbnails/';
	$sFileNameThumb = $pathInfo['filename'] . '_thumb_' . $nWidth . 'x' . $nHeight;
	$fullPathThumb = $sPathThumbnail . $sFileNameThumb . '.' . $format;
	$fullPathThumbWebP = $sPathThumbnail . $sFileNameThumb . '.webp';
	// Si la imagen original no existe, devolvemos el mismo "no_image" sin crear nada
	if (!file_exists($sImagen) || !is_file($sImagen)) {
		$noImage = $sDefault ?: 'theme/web/images/general/no_image.jpg';
		return $noImage;
	}
	$fileTime = filemtime($sImagen);
	// Asegurar la creación del directorio de miniaturas
	if (!is_dir($sPathThumbnail)) {
		mkdir($sPathThumbnail, 0777, true);

	}

	// Si tenemos thumb, pero fue creada antes que la imagen normal, la borramos.
	if( file_exists( $fullPathThumb ) && file_exists( $sImagen ) ) {
		if (filemtime($fullPathThumb) < filemtime($sImagen)) {
			unlink($sPathThumbnail . $sFileNameThumb);
			unlink($fullPathThumbWebP);
		}
	}

	// Si existe la imagen del thumb cargamos desde la cache y no queremos eliminarla
	if( file_exists( $fullPathThumb ) && file_exists( $fullPathThumbWebP ) && $bDelete == 'false' )
	{
		// Mostramos la imagen ya guardada
		return $fullPathThumb;
	}

	// Si la imagen existe y deseamos eliminarla
	if (file_exists($sPathThumbnail . $sFileNameThumb) && $bDelete == 'true') {
		unlink($fullPathThumb);
		unlink($fullPathThumbWebP);
	}

	try {
		// Cargar la imagen original
		$image = $manager->read($sImagen);

		// Si solo se pasa altura, calcular ancho proporcional
		if (!$nWidth && $nHeight) {
			$imgWidth = $image->width();
			$imgHeight = $image->height();

			// Escalar proporcionalmente
			$ratio = $nHeight / $imgHeight;
			$nWidth = intval($imgWidth * $ratio);
		}

		// Redimensionar la imagen manteniendo el aspecto original
		if ($forInstagram) {
			// Redimensionar la imagen para llenar el espacio disponible sin agregar espacios en blanco
			$image->cover($nWidth, $nHeight);
		} else {
			$image->pad($nWidth, $nHeight, 'ffffff'); // rellena con blanco (JPG no soporta alpha)
		}

		// encode edited image
		if( $format == 'png' ) {
			$image->toPng(true)->save($fullPathThumb);
		} elseif ($format == 'gif') {
			$image->toGif($quality)->save($fullPathThumb);
		}elseif ($format == 'jpg'){
			$image->toJpeg($quality)->save($fullPathThumb);
		}

		$image->toWebp($quality)->save($fullPathThumbWebP);

	} catch (Exception $e) {
		// Manejo de errores
		return $sDefault ?: 'theme/web/images/general/no_image.jpg';
	}

	return $fullPathThumb . (defined('CACHE_IMAGE_VERSION') && CACHE_IMAGE_VERSION == 'true' ? '?v=' . $fileTime : '');
}

// Scales product images dynamically, resulting in smaller file sizes, and keeps
// proper image ratio. Used in conjunction with product_thumb.php t/n generator.
function tep_image($src, $alt = '', $width = '', $height = '', $params = '', $bSize = true, $bLazyLoad = true, $bNoscript = true, $forInstagram = false) {
	// Comprobamos si existe propiedad class
	$classProperty = '';
	if (strstr($params, 'class="') !== false && $bLazyLoad) {
		preg_match ('/class="(?P<class>[a-z 0-9]+)"/i', $params, $aux);
		$classProperty = $aux['class'];
		$params = str_replace('class="'.  $classProperty . '"', '', $params);
	}

    if( ! $bSize )
    {
        $src = tep_image_thumb($src, $width, $height, '', $forInstagram);
    }

    // Set default image variable and code
    $image = '<img ' . ($bLazyLoad ? 'class="lazy ' . $classProperty . '" data-' : '') . 'src="' . $src . '"';

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

    // Fase C: si existe un .webp hermano del src, envolver en <picture> con <source type="image/webp">
    $srcNoQuery = preg_replace('/\?.*$/', '', $src);
    $srcWebp = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '.webp', $srcNoQuery);
    if ($srcWebp !== $srcNoQuery && @file_exists($srcWebp)) {
        $srcsetAttr = $bLazyLoad ? 'data-srcset' : 'srcset';
        $image = '<picture><source ' . $srcsetAttr . '="' . $srcWebp . '" type="image/webp" />' . $image . '</picture>';
    }

    if ($bLazyLoad && $bNoscript) {
    	$image .= '<noscript>' . str_replace(['data-src', 'data-srcset'], ['src', 'srcset'], $image) . '</noscript>';
    }

    return $image;
}

////
// The HTML form submit button wrapper function
// Outputs a button in the selected language
function tep_image_submit($image, $alt = '', $parameters = '', $sClass = '') {
	$image_submit = '<div class="bton-dflt' . ($sClass != '' ? ' ' . $sClass : '') . '">' . $alt;

	$image_submit .= '<input type="submit" alt="' . tep_output_string($alt) . '" title=" ' . tep_output_string($alt) . ' "';

	if (tep_not_null($parameters))
		$image_submit .= ' ' . $parameters;

	$image_submit .= ' /></div>';

	return $image_submit;
}

////
// Output a function button in the selected language
function tep_image_button($image, $alt = '', $sClass = '') {
	return '<span class="bton-dflt' . ($sClass != '' ? ' ' . $sClass : '') . '">' . $alt . '</span>';
}

////
// Output a separator either through whitespace, or with an image
function tep_draw_separator($image = 'pixel_black.gif', $width = '100%', $height = '1') {
	return '&nbsp;';
}

////
// Output a form
function tep_draw_form($name, $action, $method = 'post', $parameters = '', $tokenize = false) {
	global $sessiontoken;
	$form = '<form name="' . tep_output_string($name) . '" action="' . tep_output_string($action) . '" method="' . tep_output_string($method) . '"';

	if (tep_not_null($parameters)) $form .= ' ' . $parameters;

	$form .= '>';

	if (($tokenize == true) && isset($sessiontoken)) {
		$form .= '<input type="hidden" name="formid" value="' . tep_output_string($sessiontoken) . '" />';
	}
	return $form;
}

////
// Output a form input field
function tep_draw_input_field($name, $value = '', $parameters = '', $required = false, $type = 'text', $reinsert_value = true) {
	global $_GET, $_POST;

	$field = '<input type="' . tep_output_string($type) . '" name="' . tep_output_string($name) . '" ';

	if (($reinsert_value == true) && ((isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])))) {
		if (isset($_GET[$name]) && is_string($_GET[$name])) {
			$value = stripslashes($_GET[$name]);
		} else if (isset($_POST[$name]) && is_string($_POST[$name])) {
			$value = stripslashes($_POST[$name]);
		}
	}

	if (tep_not_null($value)) {
		$field .= ' value="' . tep_output_string($value) . '"';
	}

	if (tep_not_null($parameters)) $field .= ' ' . $parameters;
	$field .= 'id="' . tep_output_string($name) . '"';
	$field .= ' />';

	if ($required == true) $field .= TEXT_FIELD_REQUIRED;

	return $field;
}

////
// Output a form password field
function tep_draw_password_field($name, $value = '', $parameters = 'maxlength="40"') {
	return tep_draw_input_field($name, $value, $parameters, false, 'password', false);
}

////
// Output a selection field - alias function for tep_draw_checkbox_field() and tep_draw_radio_field()
function tep_draw_selection_field($name, $type, $value = '', $checked = false, $parameters = '') {
	global $_GET, $_POST;

	$selection = '<input type="' . tep_output_string($type) . '" name="' . tep_output_string($name) . '"';

	if (tep_not_null($value)) $selection .= ' value="' . tep_output_string($value) . '"';

	if (($checked == true) || (isset($_GET[$name]) && is_string($_GET[$name]) && (($_GET[$name] == 'on') || (stripslashes($_GET[$name]) == $value))) || (isset($_POST[$name]) && is_string($_POST[$name]) && (($_POST[$name] == 'on') || (stripslashes($_POST[$name]) == $value)))) {
		$selection .= ' checked="checked"';
	}

	if (tep_not_null($parameters)) $selection .= ' ' . $parameters;

	$selection .= ' />';

	return $selection;
}

////
// Output a form checkbox field
function tep_draw_checkbox_field($name, $value = '', $checked = false, $parameters = '') {
	return tep_draw_selection_field($name, 'checkbox', $value, $checked, $parameters);
}

////
// Output a form radio field
function tep_draw_radio_field($name, $value = '', $checked = false, $parameters = '') {
	return tep_draw_selection_field($name, 'radio', $value, $checked, $parameters);
}

////
// Output a form textarea field
function tep_draw_textarea_field($name, $wrap, $width, $height, $text = '', $parameters = '', $reinsert_value = true) {
	global $_GET, $_POST;

	$field = '<textarea name="' . tep_output_string($name) . '" id="' . tep_output_string($name) . '" wrap="' . tep_output_string($wrap) . '" cols="' . tep_output_string($width) . '" rows="' . tep_output_string($height) . '"';

	if (tep_not_null($parameters)) $field .= ' ' . $parameters;

	$field .= '>';

	if (($reinsert_value == true) && ((isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])))) {
		if (isset($_GET[$name]) && is_string($_GET[$name])) {
			$field .= tep_output_string_protected(stripslashes($_GET[$name]));
		} else if (isset($_POST[$name]) && is_string($_POST[$name])) {
			$field .= tep_output_string_protected(stripslashes($_POST[$name]));
		}
	} else if (tep_not_null($text)) {
		$field .= tep_output_string_protected($text);
	}

	$field .= '</textarea>';

	return $field;
}

////
// Output a form hidden field
function tep_draw_hidden_field($name, $value = '', $parameters = '') {
	global $_GET, $_POST;

	$field = '<input type="hidden" name="' . tep_output_string($name) . '"';

	if (tep_not_null($value)) {
		$field .= ' value="' . tep_output_string($value) . '"';
	} else if ((isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name]))) {
		if ((isset($_GET[$name]) && is_string($_GET[$name]))) {
			$field .= ' value="' . tep_output_string(stripslashes($_GET[$name])) . '"';
		} else if ((isset($_POST[$name]) && is_string($_POST[$name]))) {
			$field .= ' value="' . tep_output_string(stripslashes($_POST[$name])) . '"';
		}
	}

	if (tep_not_null($parameters)) $field .= ' ' . $parameters;

	$field .= ' />';

	return $field;
}

////
// Hide form elements
function tep_hide_session_id() {
	global $sessionCore, $SID;

	if (($sessionCore->hasStarted() == true) && tep_not_null($SID)) {
		return tep_draw_hidden_field(tep_session_name(), tep_session_id());
	}
}

////
// Output a form pull down menu
function tep_draw_pull_down_menu($name, $values, $default = '', $parameters = '', $required = false) {
	global $_GET, $_POST;

	$field = '<select name="' . tep_output_string($name) . '"';

	if (tep_not_null($parameters)) $field .= ' ' . $parameters;

	$field .= '>';

	if (empty($default) && ((isset($_GET[$name]) && is_string($_GET[$name])) || (isset($_POST[$name]) && is_string($_POST[$name])))) {
		if (isset($_GET[$name]) && is_string($_GET[$name])) {
			$default = stripslashes($_GET[$name]);
		} else if (isset($_POST[$name]) && is_string($_POST[$name])) {
			$default = stripslashes($_POST[$name]);
		}
	}

	for ($i = 0, $n = sizeof($values); $i < $n; $i++) {
		$field .= '<option value="' . tep_output_string($values[$i]['id']) . '"';
		if ($default == $values[$i]['id']) {
			$field .= ' SELECTED';
		}

		$field .= '>' . tep_output_string($values[$i]['text'], ['"' => '&quot;', '\'' => '&#039;', '<' => '&lt;', '>' => '&gt;']) . '</option>';
	}
	$field .= '</select>';

	if ($required == true) $field .= TEXT_FIELD_REQUIRED;

	return $field;
}

////
// Creates a pull-down list of countries
function tep_get_country_list($name, $selected = '', $parameters = '') {
	$countries_array = [['id' => '', 'text' => PULL_DOWN_COUNTRY]];
	$countries       = tep_get_countries();

	for ($i = 0, $n = sizeof($countries); $i < $n; $i++) {
		$countries_array[] = ['id' => $countries[$i]['countries_id'], 'text' => $countries[$i]['countries_name']];
	}

	return tep_draw_pull_down_menu($name, $countries_array, $selected, $parameters);
}

function ajax_get_cities_html($country = 0, $zone = false, $cp = false, $selected = true, $return = false) {
	if (array_key_exists('HTTP_REFERER', $_SERVER) && !preg_match('/address_book_process/i', $_SERVER['HTTP_REFERER']))
		$output = '<label for="city">' . ENTRY_CITY . '</label>';

	$cities_array = [];
	$sql          = false;
	if ((int)$zone > 0)
		$sql = "SELECT id, name, cp, id_zone, id_country FROM cities WHERE id_zone = '" . (int)$zone . "' AND id_country = '" . $country . "' ORDER BY name";

	if ((int)$cp > 0)
		$sql = "SELECT id, name, cp, id_zone, id_country FROM cities WHERE cp = '" . $cp . "' AND id_country = '" . $country . "' ORDER BY name";

	if ($sql != false) {
		$zones_query    = tep_db_query($sql);
		$cities_array[] = ['id' => '0', 'text' => PULL_DOWN_CITY];

		while ($zones_values = tep_db_fetch_array($zones_query)) {
			$cities_array[] = ['id' => $zones_values['id'], 'text' => $zones_values['name'] . ' [' . $zones_values['cp'] . ']'];
			$id_zone        = (int)$zones_values['id_zone'];
			$id_country     = (int)$zones_values['id_country'];
		}

		if (tep_db_num_rows($zones_query) == 1)
			$selected = $cities_array[1]['id'];

		if (tep_db_num_rows($zones_query))
			$output .= tep_draw_pull_down_menu('city_id', $cities_array, (int)$selected, ' class="select2" ');
		else {
			$output .= tep_draw_input_field('city', '');
		}
		if (tep_not_null(ENTRY_CITY_TEXT))
			$output .= '&nbsp;<span class="inputRequirement">' . ENTRY_CITY_TEXT . '</span>';
	} else {
		$cities_array[] = ['id' => 0, 'text' => 'Debe seleccionar una provincia o código postal'];
		$output         .= tep_draw_pull_down_menu('city_id', $cities_array, (int)$selected);
		if (tep_not_null(ENTRY_CITY_TEXT))
			$output .= '&nbsp;<span class="inputRequirement">' . ENTRY_CITY_TEXT . '</span>';
	}

	if ($return == false) {
		header('Content-type: text/html; charset=' . CHARSET);
		echo json_encode(['cities' => $output, 'id_zone' => $id_zone, 'id_country' => $id_country, 'selected' => $selected]);
	} else
		return $output;
}

function tep_draw_pull_down_date($name = 'date', $day = '', $month = '', $year = '', $full = false, $mnth = false, $starty = '') {
	if ($day == '') $day = '0';
	if ($month == '') $month = '0';
	if ($year == '') $year = '0';

	$endy = date('Y');
	if ($starty == '') {
		$starty = date('Y') - 1;
		$endy   = date('Y') + 2;
	}
	$named = $name . 'd';
	$namem = $name . 'm';
	$namey = $name . 'Y';

	// Array for days
	$defaultday[0] = ['id'   => '0',
					  'text' => TEXT_DATE_DAY];

	for ($i = 1; $i <= 31; $i++) {
		if (strlen($i) != 2) {
			$j = '0' . $i;
		} else {
			$j = $i;
		}

		$days[] = ['id' => $j, 'text' => $j];
	}
	if ($day == '0')
		$days = array_merge($defaultday, $days);

	// Array for months
	$defaultmonth[0] = ['id'   => '0',
						'text' => TEXT_DATE_MONTH];
	if ($mnth) {
		$months = [['id' => '01', 'text' => TEXT_DATE_JAN],
				   ['id' => '02', 'text' => TEXT_DATE_FEB],
				   ['id' => '03', 'text' => TEXT_DATE_MAR],
				   ['id' => '04', 'text' => TEXT_DATE_APR],
				   ['id' => '05', 'text' => TEXT_DATE_MAY],
				   ['id' => '06', 'text' => TEXT_DATE_JUN],
				   ['id' => '07', 'text' => TEXT_DATE_JUL],
				   ['id' => '08', 'text' => TEXT_DATE_AUG],
				   ['id' => '09', 'text' => TEXT_DATE_SEP],
				   ['id' => '10', 'text' => TEXT_DATE_OCT],
				   ['id' => '11', 'text' => TEXT_DATE_NOV],
				   ['id' => '12', 'text' => TEXT_DATE_DEC]];
	} else {
		for ($i = 1; $i <= 12; $i++) {
			if (strlen($i) != 2) {
				$j = '0' . $i;
			} else {
				$j = $i;
			}
			$months[] = ['id' => $j, 'text' => $j];
		}
	}
	if ($month == '0')
		$months = array_merge($defaultmonth, $months);

	// Array for years
	$defaultyear[0] = ['id'   => '0',
					   'text' => TEXT_DATE_YEAR];

	for ($i = $starty; $i <= $endy; $i++) {
		$j = $i;
		if ($full) {
			$y = $i - 2000;
			if (strlen($y) != 2) {
				$j = '0' . $y;
			} else {
				$j = $y;
			}
		}
		$years[] = ['id' => $i, 'text' => $j];
	}

	if ($year == '0')
		$years = array_merge($defaultyear, $years);

	$field = '';

	// switch (DATE_FORMAT) {
	// case 'd/m/Y':
	$field .= tep_draw_pull_down_menu($named, $days, $day);
	$field .= tep_draw_pull_down_menu($namem, $months, $month);
	$field .= tep_draw_pull_down_menu($namey, $years, $year);
	// break;
	// case 'm/d/Y':
	// $field .= tep_draw_pull_down_menu($named, $months, $month);
	// $field .= tep_draw_pull_down_menu($namem, $days, $day);
	// $field .= tep_draw_pull_down_menu($namey, $years, $year);
	// break;
	// case 'Y/m/d':
	// $field .= tep_draw_pull_down_menu($named, $years, $year);
	// $field .= tep_draw_pull_down_menu($namem, $months, $month);
	// $field .= tep_draw_pull_down_menu($namey, $days, $day);
	// break;
	// default:
	// $field .= tep_draw_pull_down_menu($named, $days, $day);
	// $field .= tep_draw_pull_down_menu($namem, $months, $month);
	// $field .= tep_draw_pull_down_menu($namey, $years, $year);
	// break;
	// }

	return $field;
}

// Creates a pull-down list of countries
function tep_get_country_list2($name, $selected = '', $parameters = '') {
	$countries_array = [['id' => '', 'text' => PULL_DOWN_COUNTRY]];
	$countries       = tep_get_countries();

	for ($i = 0, $n = sizeof($countries); $i < $n; $i++) {
		$countries_array[] = ['id' => $countries[$i]['countries_id'], 'text' => $countries[$i]['countries_name']];
	}

	return tep_draw_pull_down_menu($name, $countries_array, $selected, $parameters);
}

function ajax_get_zones_html2($country, $default_zone = '', $ajax_output = true) {
	$output = '';

	$zones_array   = [];
	$zones_query   = tep_db_query("select zone_id, zone_name from " . TABLE_ZONES . " where zone_country_id = '" . (int)$country . "' order by zone_name");
	$zones_array[] = ['id' => '0', 'text' => PULL_DOWN_STATE];
	while ($zones_values = tep_db_fetch_array($zones_query)) {
		$zones_array[] = ['id' => $zones_values['zone_id'], 'text' => $zones_values['zone_name']];
	}

	if (tep_db_num_rows($zones_query)) {
		$output .= tep_draw_pull_down_menu('zone_id', $zones_array, $default_zone);
	} else {
		$output .= tep_draw_input_field('state', '');
	}
	if (tep_not_null(ENTRY_STATE_TEXT)) $output .= '&nbsp;<span class="inputRequirement">' . ENTRY_STATE_TEXT . '</span>';

	if ($ajax_output) {
		header('Content-type: text/html; charset=' . CHARSET);
		echo $output;
	} else {
		return $output;
	}
}

function ajax_get_cities_html2($country = 0, $zone = false, $cp = false, $selected = true, $return = false, $class = '', $city_name = '') {
	//$output = '<label for="city">'.ENTRY_CITY.'</label>';

	$cities_array = [];
	$sql          = false;
	if ((int)$zone > 0)
		$sql = "SELECT id, name, cp, id_zone, id_country FROM cities WHERE id_zone = '" . (int)$zone . "' AND id_country = '" . $country . "' ORDER BY name";

	if ((int)$cp > 0)
		$sql = "SELECT id, name, cp, id_zone, id_country FROM cities WHERE cp = '" . $cp . "' AND id_country = '" . $country . "' ORDER BY name";
	if ((int)$selected <= 0) {
		return tep_draw_input_field('city', $city_name);
	}
	if ($sql != false) {
		$zones_query    = tep_db_query($sql);
		$cities_array[] = ['id' => '0', 'text' => PULL_DOWN_CITY];

		while ($zones_values = tep_db_fetch_array($zones_query)) {
			$cities_array[] = ['id' => $zones_values['id'], 'text' => $zones_values['name'] . ' [' . $zones_values['cp'] . ']'];
			$id_zone        = (int)$zones_values['id_zone'];
			$id_country     = (int)$zones_values['id_country'];
		}

		if (tep_db_num_rows($zones_query) == 1)
			$selected = $cities_array[1]['id'];

		if (tep_db_num_rows($zones_query)) {
			$output .= tep_draw_pull_down_menu('city_id', $cities_array, (int)$selected, ' class="' . $class . '" ');
		} else {
			$output .= tep_draw_input_field('city', $city_name);
		}
		//$output .= (defined( 'ENTRY_CITY_TEXT' ) && ENTRY_CITY_TEXT != '*' && ENTRY_CITY_TEXT != '' ) ? '<div class="DFhelp">' . ENTRY_CITY_TEXT . '</div>' : '';

	} else {
		$cities_array[] = ['id' => 0, 'text' => 'Debe seleccionar una provincia o código postal'];
		$output         .= tep_draw_pull_down_menu('city_id', $cities_array, (int)$selected, ' class="' . $class . '" ');
		if (tep_not_null(ENTRY_CITY_TEXT))
			$output .= '&nbsp;<span class="inputRequirement column afixed">' . ENTRY_CITY_TEXT . '</span>';
	}

	if ($return == false) {
		header('Content-type: text/html; charset=' . CHARSET);
		echo json_encode(['cities' => $output, 'id_zone' => $id_zone, 'id_country' => $id_country, 'selected' => $selected]);
	} else
		return $output;
}

?>
