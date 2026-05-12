<?php
////
// Sets the status of a banner
function tep_set_banner_status($banners_id, $status)
{
	if ($status == '1') {
		return tep_db_query("update " . TABLE_BANNERS . " set status = '1', date_status_change = now(), date_scheduled = NULL where banners_id = '" . (int)$banners_id . "'");
	} elseif ($status == '0') {
		return tep_db_query("update " . TABLE_BANNERS . " set status = '0', date_status_change = now() where banners_id = '" . (int)$banners_id . "'");
	} else {
		return -1;
	}
}

////
// Auto activate banners
function tep_activate_banners()
{
	$banners_query = tep_db_query("select banners_id, date_scheduled from " . TABLE_BANNERS . " where date_scheduled != ''");
	if (tep_db_num_rows($banners_query)) {
		while ($banners = tep_db_fetch_array($banners_query)) {
			if (date('Y-m-d H:i:s') >= $banners['date_scheduled']) {
				tep_set_banner_status($banners['banners_id'], '1');
			}
		}
	}
}

////
// Auto expire banners
function tep_expire_banners()
{
	$banners_query = tep_db_query("select b.banners_id, b.expires_date from " . TABLE_BANNERS . " b where b.status = '1'");
	if (tep_db_num_rows($banners_query)) {
		while ($banners = tep_db_fetch_array($banners_query)) {
			if (tep_not_null($banners['expires_date'])) {
				if (date('Y-m-d H:i:s') >= $banners['expires_date']) {
					tep_set_banner_status($banners['banners_id'], '0');
				}
			}
		}
	}
}


// Check to see if a banner exists
function tep_banner_exists($action, $identifier)
{
	if ($action == 'dynamic') {
		$banner = tep_db_query("select banners_id, banners_title, banners_html_text, banners_url from " . TABLE_BANNERS . " where status = '1' and banners_group = '" . $identifier . "' ORDER BY RAND()");
		return tep_db_fetch_array($banner);
	} elseif ($action == 'static') {
		$banner_query = tep_db_query("select banners_id, banners_title, banners_html_text, banners_url from " . TABLE_BANNERS . " where status = '1' and banners_id = '" . (int)$identifier . "'");
		return tep_db_fetch_array($banner_query);
	}

	return false;
}

////
// Display a banner from the specified group or banner id ($identifier)
function tep_display_banner($action, $identifier, $aResponsive = array(), $sClass = '')
{
	if ($action == 'dynamic') {
		$banners_query = tep_db_query("select count(*) as count from " . TABLE_BANNERS . " where status = '1' and banners_group = '" . tep_db_input($identifier) . "'");
		$banners = tep_db_fetch_array($banners_query);
		if ($banners['count'] > 0) {
			$banner = tep_db_query("select banners_id, banners_title, banners_html_text from " . TABLE_BANNERS . " where status = '1' and banners_group = '" . tep_db_input($identifier) . "'");
			$banner = tep_db_fetch_array($banner);
		} else {
			return '<strong>TEP ERROR! (tep_display_banner(' . $action . ', ' . $identifier . ') -> No banners with group \'' . $identifier . '\' found!</strong>';
		}
	} elseif ($action == 'static') {
		if (is_array($identifier)) {
			$banner = $identifier;
		} else {
			$banner_query = tep_db_query("select banners_id, banners_title, banners_html_text from " . TABLE_BANNERS . " where status = '1' and banners_id = '" . (int)$identifier . "'");

			if (tep_db_num_rows($banner_query)) {
				$banner = tep_db_fetch_array($banner_query);
			} else {
				return '<strong>TEP ERROR! (tep_display_banner(' . $action . ', ' . $identifier . ') -> Banner with ID \'' . $identifier . '\' not found, or status inactive</strong>';
			}
		}
	} else {
		return '<strong>TEP ERROR! (tep_display_banner(' . $action . ', ' . $identifier . ') -> Unknown $action parameter value - it must be either \'dynamic\' or \'static\'</strong>';
	}

	if (tep_not_null($banner['banners_html_text'])) {
		$banner_string = $banner['banners_html_text'];
	} else {
		$banner_string = '<a href="' . $identifier['banners_url'] . '" class="' . $sClass . '">';

		if (is_array($aResponsive) && count($aResponsive) > 0) {
			$banner_string .= '<picture>';
			$banner_string .= '<!--[if IE 9]><video style="display:none"><![endif]-->';

			foreach ($aResponsive as $sType => $nSize) {
				$sImage = getImagenBanner($banner['banners_id'], $sType);

				if ($nSize == '')
					$banner_string .= '<img alt="' . $banner['banners_title'] . '" srcset="' . $sImage . '" src="' . $sImage . '" alt="' . $banner['banners_title'] . '">';
				else
					$banner_string .= '<source title="' . $banner['banners_title'] . '" srcset="' . $sImage . '" media="(min-width: ' . $nSize . 'px)">';
			}

			$banner_string .= '</picture>';
		} else
			$banner_string .= tep_image(getImagenBanner($banner['banners_id']), $banner['banners_title']);

		$banner_string .= '</a>';
	}

	return $banner_string;
}

function getImagenBanner($sId, $sResponsive = '')
{
	if (!is_dir(DIR_WS_IMAGES . 'banners/'))
		return false;

	global $languages_id, $lng;
	$aImagenes = scandir(DIR_WS_IMAGES . 'banners/');

	// Comprobamos que $lng sea un objeto ya definido si no lo creamos
	if (!isset($lng) || isset($lng) && !is_object($lng)) {
		require_once(DIR_WS_CLASSES . 'language.php');
		$lng = new language;
	}

	// Recorremos los lenguajes
	foreach ($lng->catalogLanguages as $key => $value) {
		if ($languages_id == $value['id']) {
			$matches = preg_grep('/^' . $sId . '_' . $value['id'] . '_' . $sResponsive . '/i', $aImagenes);

			if (count($matches) > 0) {
				$matches = array_values($matches);
				return DIR_WS_IMAGES . 'banners/' . $matches[0];
			}
		}
	}
}

/* Funciones para Banners Destacados */
////
// Auto Expire banner_destacados
function tep_expire_bannersdestacados()
{
	//Desactiva los banners
	$banners_query = tep_db_query("select banner_destacados_id from " . TABLE_BANNERS_DESTACADOS . " where estado = '1' and ((date_start != '' AND date_start != '0000-00-00 00:00:00' AND now() < date_start) OR (date_end != '' AND date_end != '0000-00-00 00:00:00' AND now() > date_end))");
	if (tep_db_num_rows($banners_query)) {
		while ($banners = tep_db_fetch_array($banners_query)) {
			tep_set_bannersdestacados_status($banners['banner_destacados_id'], '0');
		}
	}
}

////
// Auto Activa banner_destacados
function tep_active_bannersdestacados()
{
	// Activa los banners
	$banners_query = tep_db_query("
        SELECT banner_destacados_id
        FROM " . TABLE_BANNERS_DESTACADOS . "
        WHERE estado = '0'
          AND date_start != '0000-00-00 00:00:00'
          AND NOW() >= date_start
          AND (date_end = '0000-00-00 00:00:00' OR date_end > NOW())
    ");
	if (tep_db_num_rows($banners_query)) {
		while ($banners = tep_db_fetch_array($banners_query)) {
			tep_set_bannersdestacados_status($banners['banner_destacados_id'], '1');
		}
	}
}

////
// Sets the status of a banner_destacados
function tep_set_bannersdestacados_status($banner_destacados_id, $status)
{
	return tep_db_query("update " . TABLE_BANNERS_DESTACADOS . " set estado = '" . (int)$status . "' where banner_destacados_id = '" . (int)$banner_destacados_id . "'");
}

?>
