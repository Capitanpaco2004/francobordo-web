<?php

// Acciones
switch( $sPostAction )
{
	case 'zones_to_geo_zones_delete':
		// Variables
		$aGetId = tep_db_prepare_input( $_GET['id'] );
		$aPostId = tep_db_prepare_input( $_POST['id'] );
		$sIds = '';

		// Si nos envian por get creamos el array
		if( $aGetId != '' ) {
			$aPostId = array($aGetId);
		}

		// Recorremos los id
		foreach( $aPostId as $sId ) {
			$sIds .= $sId . ',';
		}

		// Si tenemos id eliminamos
		if( $sIds != '' ) {
			tep_db_query('delete from zones_to_geo_zones where association_id IN(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->addSession( 'success', 'Los registros se han eliminado correctamente', 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'zones_to_geo_zones_add_country':
		$countries = $_POST['states'];
		$geoZoneId = (int)tep_db_input($_POST['geo_zone_id']);
		$sql = '';
		$rowsExists = [];

		$rows = pharaonix_query('SELECT zone_country_id FROM zones_to_geo_zones WHERE zone_id = 0 AND geo_zone_id = "' . $geoZoneId . '"')->records;

		while ($row = tep_db_fetch_array($rows)) {
			$rowsExists[$row['zone_country_id']] = true;
		}

		if (is_array($countries)) {
			foreach ($countries as $idCountry) {
				if (!isset($rowsExists[$idCountry])) {
					$sql .= '("' . $idCountry . '", "0", "' . $geoZoneId . '", NOW()),';
				}
			}
		}

		if ($sql !== '') {
			tep_db_query('INSERT INTO zones_to_geo_zones (zone_country_id, zone_id, geo_zone_id, date_added) VALUES ' . substr($sql, 0, -1));
		}

		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	case 'zones_to_geo_zones_add':
		$allZones = isset($_POST['all_zones']) ? true : false;
		$idCountry = (int)tep_db_input($_POST['id_country']);
		$geoZoneId = (int)tep_db_input($_POST['geo_zone_id']);
		$states = $_POST['states'];
		$sql = '';
		$rowsExists = [];

		$rows = pharaonix_query('SELECT zone_id FROM zones_to_geo_zones WHERE zone_country_id = "' . $idCountry . '" AND geo_zone_id = "' . $geoZoneId . '"')->records;

		while ($row = tep_db_fetch_array($rows)) {
			$rowsExists[$row['zone_id']] = true;
		}

		if ($allZones) {
			if (!isset($rowsExists[0])) {
				tep_db_query('INSERT INTO zones_to_geo_zones (zone_country_id, zone_id, geo_zone_id, date_added) VALUES ("' . $idCountry . '", "0", "' . $geoZoneId . '", NOW())');
			}
		}
		else {
			if (is_array($states)) {
				foreach ($states as $idZone) {
					if (!isset($rowsExists[$idZone])) {
						$sql .= '("' . $idCountry . '", "' . (int)$idZone . '", "' . $geoZoneId . '", NOW()),';
					}
				}
			}

			if ($sql !== '') {
				tep_db_query('INSERT INTO zones_to_geo_zones (zone_country_id, zone_id, geo_zone_id, date_added) VALUES ' . substr($sql, 0, -1));
			}
		}

		tep_redirect( $_SERVER['HTTP_REFERER'] );
	break;

	case 'zones_to_geo_zones_get_country_zones':
		echo json_encode(tep_get_country_zones(tep_db_prepare_input($_POST['country'])));
		exit();
	break;

	default:
		// Variables
		$geoZoneId = (int)tep_db_prepare_input($_GET['geo_zone_id']);

		// Comprobamos
		$zone = pharaonix_queryOne('SELECT geo_zone_name FROM geo_zones WHERE geo_zone_id = "' . $geoZoneId . '"');
		$geoZoneNotExists = $zone->num_rows <= 0;

		// Si no existe
		if ($geoZoneNotExists) {
			$messageStack->addSession('error', 'Los registros se han eliminado correctamente', 'success');
			tep_redirect( tep_href_link('geo_zones.php') );
		}

		$sSubtitle = 'Listado subzonas de ' . $zone->records['geo_zone_name'];
		$aButtons = array(
			array( 'title' => 'Volver', 'href' => tep_href_link( $sUrlPage), 'icon' => 'fa-arrow-left' ),
			array( 'title' => 'Añadir por zona', 'icon' => 'fa-plus', 'anchor_class' => 'verde insert-subzone' ),
			array( 'title' => 'Añadir por pais', 'icon' => 'fa-plus', 'anchor_class' => 'verde insert-country' )
		);

		// JS
		$aJs = [$sPathModule . '/js/default.js'];
		$aStyle = [$sPathModule . '/css/style.css'];

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">Aplicar acción:&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>Acciones</div>
				<ul class="down drch">
					<li><a data-question="¿Realmente deseas eliminar los registros?" data-action="' . tep_href_link( $sUrlPage, 'action=zones_to_geo_zones_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash-o"></i>Eliminar registros</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Sql
		$sSql = 'SELECT ztgz.association_id, c.countries_name, z.zone_name
				 FROM zones_to_geo_zones ztgz
				 LEFT JOIN zones z ON (ztgz.zone_id = z.zone_id)
				 LEFT JOIN countries c ON(ztgz.zone_country_id = c.countries_id)
				 WHERE c.countries_status = 1 AND geo_zone_id = "' . $geoZoneId . '"
				 ORDER BY c.countries_name asc, z.zone_name asc';

		$aRows= tep_db_query( $sSql );

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/zones_to_geo_zones.php' );
		break;
}
