<?php
// Tools
use util\tools as tools;
use util\date as date;

// Incluimos el application_top
require_once('includes/application_top.php');

// Variables
$sUrlPage = 'banner_manager.php';
$sPathModule = 'includes/modules/banner_manager';
$sPathTemplate = $sPathModule . '/template';
$sTitle = HEADING_TITLE;
$sSubtitle = '';
$aButtons = [];
$sPostAction = array_key_exists('action', $_POST) ? tep_db_input($_POST['action']) : (array_key_exists('action', $_GET) ? tep_db_input($_GET['action']) : false);

$sGetPage = tep_db_prepare_input($_GET['page'] ?? 1);
$sGetOrderby = tep_db_prepare_input($_GET['orderby'] ?? '');
$sGetSort = tep_db_prepare_input($_GET['sort'] ?? '');

// Messagestack estilo
$messageStack->style = 'solenopsis';

// Extension de imagen para banners
$banner_extension = tep_banner_image_extension();

// Acciones
switch ($sPostAction) {
	case 'setflag':
		// Variables
		$nId = tep_db_prepare_input($_GET['bID']);
		$nFlag = tep_db_prepare_input($_GET['flag']);

		if (($nFlag == '0') || ($nFlag == '1')) {
			tep_set_banner_status($nId, $nFlag);
			$messageStack->addSession('success', SUCCESS_BANNER_STATUS_UPDATED, 'success');
		} else {
			$messageStack->addSession('success', ERROR_UNKNOWN_STATUS_FLAG, 'error');
		}

		tep_redirect($_SERVER['HTTP_REFERER']);
		break;

	case 'delete':
		// Variables
		$aGetId = tep_db_prepare_input($_GET['bID'] ?? '');
		$aPostId = tep_db_prepare_input($_POST['id'] ?? []);
		$sIds = '';

		// Si nos envian por get creamos el array
		if ($aGetId != '')
			$aPostId = [$aGetId];

		// Recorremos los id
		if (is_array($aPostId)) {
			foreach ($aPostId as $sId) {
				$sIds .= $sId . ',';

				$aImagenes = glob(getcwd() . '/../images/banners/' . $sId . '_*');
				$aImagenesThumb = glob(getcwd() . '/../images/banners/thumbnails/' . $sId . '_*');

				foreach ($aImagenes as $sFile)
					@unlink($sFile);

				foreach ($aImagenesThumb as $sFile)
					@unlink($sFile);
			}
		}

		// Si tenemos id eliminamos
		if ($sIds != '') {
			tep_db_query('DELETE FROM ' . TABLE_BANNERS . ' WHERE banners_id IN(' . substr($sIds, 0, -1) . ')');
			tep_db_query('DELETE FROM ' . TABLE_BANNERS_HISTORY . ' WHERE banners_id IN(' . substr($sIds, 0, -1) . ')');
		}

		// Redireccionamos
		$messageStack->addSession('success', SUCCESS_BANNER_REMOVED, 'success');
		tep_redirect(tep_href_link($sUrlPage, 'page=' . $sGetPage));
		break;

	case 'crud':
		// Javascript y css
		$aJs = [$sPathModule . '/js/index.js'];
		$aStyle = [$sPathModule . '/css/style.css'];

		// Variables
		$sGetId = array_key_exists('bID', $_POST) ? tep_db_input($_POST['bID']) : (array_key_exists('bID', $_GET) ? tep_db_input($_GET['bID']) : false);
		$aMessageError = [];
		$sSubtitle = ($sGetId != '' ? TEXT_EDIT : TEXT_ADD) . ' Banner';
		$aButtons = [
			['title' => TEXT_BACK, 'href' => tep_href_link($sUrlPage), 'icon' => 'fa-arrow-left'],
			['title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde'],
		];
		$aRecord = [
			'banners_title' => '',
			'banners_url' => '',
			'banners_group' => '',
			'expires_date' => '',
			'expires_impressions' => '',
			'date_scheduled' => '',
		];

		// Obtenemos idiomas
		$aLanguages = tep_get_languages();

		// Obtenemos grupos existentes
		$groups_array = [];
		$groups_query = tep_db_query("SELECT DISTINCT banners_group FROM " . TABLE_BANNERS . " ORDER BY banners_group");
		while ($groups = tep_db_fetch_array($groups_query)) {
			$groups_array[] = ['id' => $groups['banners_group'], 'text' => $groups['banners_group']];
		}

		// Si estamos editando
		if ($sGetId != false) {
			// Obtenemos el registro
			$aRecords = tep_db_query("SELECT banners_id, banners_title, banners_url, banners_group, status,
									  DATE_FORMAT(date_scheduled, '%d/%m/%Y') as date_scheduled,
									  DATE_FORMAT(expires_date, '%d/%m/%Y') as expires_date,
									  expires_impressions, date_status_change
									  FROM " . TABLE_BANNERS . "
									  WHERE banners_id = '" . (int)$sGetId . "'");

			// Si no existe
			if (tep_db_num_rows($aRecords) == 0) {
				$messageStack->addSession('success', TEXT_BANNER_NOT_FOUND, 'error');
				tep_redirect(tep_href_link($sUrlPage));
			}

			$aRecord = tep_db_fetch_array($aRecords);
		}

		// Insertar o actualizar
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			// Variables
			$banners_title = tep_db_prepare_input($_POST['banners_title']);
			$banners_url = tep_db_prepare_input($_POST['banners_url']);
			$new_banners_group = tep_db_prepare_input($_POST['new_banners_group']);
			$banners_group = (empty($new_banners_group)) ? tep_db_prepare_input($_POST['banners_group']) : $new_banners_group;
			$expires_date = tep_db_prepare_input($_POST['expires_date']);
			$expires_impressions = tep_db_prepare_input($_POST['expires_impressions'] ?? '');
			$date_scheduled = tep_db_prepare_input($_POST['date_scheduled']);
			$aImagenes = is_dir(DIR_FS_CATALOG_IMAGES . 'banners/') ? scandir(DIR_FS_CATALOG_IMAGES . 'banners/') : [];

			// Validaciones
			if (empty($banners_title))
				$aMessageError['banners_title'] = $messageStack->show(['text' => ERROR_BANNER_TITLE_REQUIRED, 'class' => 'error']);

			if (empty($banners_group))
				$aMessageError['banners_group'] = $messageStack->show(['text' => ERROR_BANNER_GROUP_REQUIRED, 'class' => 'error']);

			// Procesamos imagenes
			foreach ($_FILES as $key => $sImagen) {
				if ($sImagen['error'] != 0) {
					@unlink(DIR_FS_CATALOG_IMAGES . 'banners/dxbanner-' . preg_replace('/^.+_/i', '', $key));
					continue;
				}

				$dxUpload = new upload($key);
				$dxUpload->set_destination(DIR_FS_CATALOG_IMAGES . 'banners/');

				if ($dxUpload->parse() && $dxUpload->save()) {
					// Si subimos una nueva imagen eliminamos la que estaba anteriormente
					if ($sGetId != false) {
						switch (true) {
							case preg_match('/banners_image_movil/i', $key):
								$sType = 'm';
								break;
							case preg_match('/banners_image_tablet/i', $key):
								$sType = 't';
								break;
							case preg_match('/banners_image_web/i', $key):
								$sType = 'w';
								break;
						}

						$aFiles = preg_grep('/^' . $sGetId . '_' . preg_replace('/^.+_/i', '', $key) . '_' . $sType . '/i', $aImagenes);

						foreach ($aFiles as $sFile) {
							@unlink(DIR_FS_CATALOG_IMAGES . 'banners/' . $sFile);
						}

						// Eliminamos los thumbs
						if (is_dir(DIR_FS_CATALOG_IMAGES . 'banners/thumbnails/')) {
							$aImagenesThumb = scandir(DIR_FS_CATALOG_IMAGES . 'banners/thumbnails/');
							$aFiles = preg_grep('/^' . $sGetId . '_' . preg_replace('/^.+_/i', '', $key) . '/i', $aImagenesThumb);

							foreach ($aFiles as $sFile) {
								@unlink(DIR_FS_CATALOG_IMAGES . 'banners/thumbnails/' . $sFile);
							}
						}
					}

					// Tipo responsive
					if (preg_match('/web/i', $key)) {
						$sResponsive = 'w';
					} else if (preg_match('/tablet/i', $key)) {
						$sResponsive = 't';
					} else if (preg_match('/movil/i', $key)) {
						$sResponsive = 'm';
					}

					$sNombreImagen = 'dxbanner-' . preg_replace('/^.+_/i', '', $key) . '-' . $sResponsive;
					$sImagenFilename = DIR_FS_CATALOG_IMAGES . 'banners/' . $sNombreImagen . '.jpg';
					rename(DIR_FS_CATALOG_IMAGES . 'banners/' . $dxUpload->filename, $sImagenFilename);
				}
			}

			// Si no hay errores guardamos
			if (count($aMessageError) == 0) {
				$sql_data_array = [
					'banners_title' => $banners_title,
					'banners_url' => $banners_url,
					'banners_group' => $banners_group,
				];

				if ($sGetId != false) {
					tep_db_perform(TABLE_BANNERS, $sql_data_array, 'update', "banners_id = '" . (int)$sGetId . "'");
					$messageStack->addSession('success', SUCCESS_BANNER_UPDATED, 'success');
				} else {
					$sql_data_array['date_added'] = 'now()';
					$sql_data_array['status'] = '1';
					tep_db_perform(TABLE_BANNERS, $sql_data_array);
					$sGetId = tep_db_insert_id();
					$messageStack->addSession('success', SUCCESS_BANNER_INSERTED, 'success');
				}

				// Movemos las imagenes
				foreach ($_FILES as $key => $sImagen) {
					$sResponsive = false;

					if ($sImagen['error'] != 0)
						continue;

					if (preg_match('/web/i', $key)) {
						$sResponsive = 'w';
					} else if (preg_match('/tablet/i', $key)) {
						$sResponsive = 't';
					} else if (preg_match('/movil/i', $key)) {
						$sResponsive = 'm';
					}

					$key = preg_replace('/^.+_/i', '', $key);

					$sNombreImagen = $sGetId . '_' . $key . '_' . $sResponsive . '_' . nombre_imagen(strtolower($banners_title)) . get_image_extension(DIR_FS_CATALOG_IMAGES . 'banners/dxbanner-' . $key);
					$sImagenFilename = DIR_FS_CATALOG_IMAGES . 'banners/' . $sNombreImagen . '.jpg';
					@rename(DIR_FS_CATALOG_IMAGES . 'banners/dxbanner-' . $key . '-' . $sResponsive . '.jpg', $sImagenFilename);

					// Creamos la version WebP de la Imagen subida
					if (function_exists('convertImageToWebP')) {
						convertImageToWebP($sImagenFilename, DIR_FS_CATALOG_IMAGES . '/banners/' . $sNombreImagen . '.webp');
					}
				}

				// Fecha de expiracion
				if (tep_not_null($expires_date)) {
					[$day, $month, $year] = explode('/', $expires_date);
					$expires_date = $year .
						((strlen($month) == 1) ? '0' . $month : $month) .
						((strlen($day) == 1) ? '0' . $day : $day);

					tep_db_query("UPDATE " . TABLE_BANNERS . " SET expires_date = '" . tep_db_input($expires_date) . "', expires_impressions = null WHERE banners_id = '" . (int)$sGetId . "'");
				} else if (tep_not_null($expires_impressions)) {
					tep_db_query("UPDATE " . TABLE_BANNERS . " SET expires_impressions = '" . tep_db_input($expires_impressions) . "', expires_date = null WHERE banners_id = '" . (int)$sGetId . "'");
				}

				// Fecha programada
				if (tep_not_null($date_scheduled)) {
					[$day, $month, $year] = explode('/', $date_scheduled);
					$date_scheduled = $year .
						((strlen($month) == 1) ? '0' . $month : $month) .
						((strlen($day) == 1) ? '0' . $day : $day);

					tep_db_query("UPDATE " . TABLE_BANNERS . " SET status = '0', date_scheduled = '" . tep_db_input($date_scheduled) . "' WHERE banners_id = '" . (int)$sGetId . "'");
				}

				// Redireccionamos
				tep_redirect(tep_href_link($sUrlPage, (isset($_GET['page']) ? 'page=' . $_GET['page'] . '&' : '') . 'bID=' . $sGetId));
			}
		}

		// Template
		$sHtmlModule = includeTemplate($sPathTemplate . '/crud.php');
		break;

	default:
		// Variables
		$sSubtitle = TEXT_BANNERS_LIST;
		$aButtons[] = ['title' => TEXT_ADD, 'href' => tep_href_link($sUrlPage, 'action=crud'), 'icon' => 'fa-plus', 'anchor_class' => 'verde'];

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">' . TEXT_APPLY_ACTION . ':&nbsp;&nbsp;</label>
		<div class="column afluid"><div class="drop masv xfselect">
			<div>' . TEXT_ACTIONS . '</div>
			<ul class="down drch" style="width: 230px;">
				<li><a data-question="' . TEXT_INFO_DELETE_INTRO . '" data-action="' . tep_href_link($sUrlPage, 'action=delete') . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . TEXT_DELETE_BANNERS . '</a></li>
			</ul>
		</div></div>&nbsp; - &nbsp;';

		// Filtros
		$aFilter = ['search' => ''];
		$aAuxFilter = array_key_exists('filter', $_GET) && is_array($_GET['filter']) ? $_GET['filter'] : (array_key_exists('filter', $_POST) && is_array($_POST['filter']) ? $_POST['filter'] : []);
		$sWhere = '';

		// Limpiamos variables get filter
		array_walk($aFilter, function ($value, $key) {
			global $aFilter, $aAuxFilter;
			$aFilter[$key] = tep_db_prepare_input(array_key_exists($key, $aAuxFilter) ? $aAuxFilter[$key] : $aFilter[$key]);
		});

		// Where
		if ($aFilter['search'] !== '') {
			$sWhere = 'WHERE (LOWER(banners_title) LIKE "%' . strtolower($aFilter['search']) . '%" OR LOWER(banners_group) LIKE "%' . strtolower($aFilter['search']) . '%")';
		}

		// Order by
		if ($sGetOrderby != '')
			$sOrderby = $sGetOrderby . ' ' . $sGetSort;
		else
			$sOrderby = 'banners_title ASC';

		// Sql
		$sSql = 'SELECT banners_id, banners_title, banners_group, status, expires_date, expires_impressions, date_status_change, date_scheduled, date_added
				 FROM ' . TABLE_BANNERS . '
				 ' . $sWhere . ' ORDER BY ' . $sOrderby;

		// Le quitamos los tabuladores y saltos de linea para que splitpageresult funcione con el SQL
		$sSql = preg_replace('/[\r\n\t]+/', ' ', $sSql);

		// Sql para el count
		$sSqlCount = 'SELECT COUNT(table_aux.banners_id) as total FROM (' . $sSql . ') as table_aux';

		// Datos y paginacion
		$aRowsSplit = new splitPageResults($sGetPage, MAX_DISPLAY_SEARCH_RESULTS, $sSql, $nAux, $sSqlCount);
		$aRows = tep_db_query($sSql);

		// Template
		$sHtmlModule = includeTemplate($sPathTemplate . '/index.php');
		break;
}

// Pintamos
echo includeTemplate($sPathTemplate . '/base.php');
?>
