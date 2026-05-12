<?php

// Acciones
switch( $sPostAction )
{
	case 'default_crud':
		// Variables
		$sGetId = array_key_exists( 'id', $_POST ) ? tep_db_input( $_POST['id'] ) : (array_key_exists( 'id', $_GET ) ? tep_db_input( $_GET['id'] ) : false);
		$aMessageError = array();
		$sSubtitle = ($sGetId != '' ? TEXT_EDIT : TEXT_ADD) . ' ' . strtolower(GEO_ZONES_TABLE_GEO_ZONE);
		$aButtons = array(
			array( 'title' => TEXT_BACK, 'href' => tep_href_link( $sUrlPage ), 'icon' => 'fa-arrow-left' ),
			array( 'title' => TEXT_SAVE, 'icon' => 'fa-save', 'extra' => 'id="saveform"', 'anchor_class' => 'verde' )
		);
		$aRecord = array();

		// Si estamos editando
		if( $sGetId != false )
		{
			// Obtenemos el registro
			$aRecord = pharaonix_queryOne( 'SELECT * FROM geo_zones WHERE geo_zone_id = "' . (int)$sGetId . '"' );

			// Si no existe
			if( $aRecord->num_rows == 0 )
			{
				$messageStack->addSession( 'success', GEO_ZONES_NO_EXISTS, 'error' );
				tep_redirect( tep_href_link(  $sUrlPage, 'action=replace' ) );
			}

			// Registro
			$aRecord = $aRecord->records;
		}

		// Insertar o actualizar
		if( $_SERVER['REQUEST_METHOD'] === 'POST' )
		{
			// Pasamos todos los post por tep_db_prepare_input
			array_walk( $_POST, function( $value, $key){ global $_POST, $aRecord; $_POST[$key] = tep_db_prepare_input( $_POST[$key] ); $aRecord[$key] = $_POST[$key]; } );

			// Comprobamos que nos hayan enviado un word
			if( array_key_exists( 'geo_zone_name', $_POST ) && $_POST['geo_zone_name'] == '' || !array_key_exists( 'geo_zone_name', $_POST ) )
				$aMessageError['geo_zone_name'] = $messageStack->show( array( 'text' => GEO_ZONES_ERROR_ZONE, 'class' => 'error' ) );

			// Si no existe errores actualizamos/insertamos
			if( count( $aMessageError ) == 0 )
			{
				$aSql = array(
					'geo_zone_name' => $_POST['geo_zone_name'],
					'geo_zone_description' => $_POST['geo_zone_description'],
					'geo_zones_type_id' => $_POST['geo_zone_type'],
					'date_added' => 'now()'
				);

				if( $sGetId != false )
					tep_db_perform( 'geo_zones', $aSql, 'update', 'geo_zone_id = "' . (int)$sGetId . '"' );
				else
					tep_db_perform( 'geo_zones', $aSql );

				// Mensaje
				$messageStack->addSession( 'success', ($sGetId != false ? GEO_ZONES_EDIT_SUCCESS : GEO_ZONES_ADD_SUCCESS), 'success' );

				// Redireccionamos
				tep_redirect( tep_href_link(  $sUrlPage ) );
			}
		}

		// Tipos de zona
		$geoZonesTypes = tep_db_query('SELECT geo_zones_type_id, geo_zones_type_title FROM geo_zones_type ORDER BY geo_zones_type_id ASC;');
		$selectGeoZoneTypes = array();

		while ($geoZonesType = tep_db_fetch_array($geoZonesTypes)) {
			$selectGeoZoneTypes[] = array('id' => $geoZonesType['geo_zones_type_id'], 'text' => $geoZonesType['geo_zones_type_title']);
		}

		// Template
		$sHtmlModule = includeTemplate( $sPathTemplate . '/default_crud.php' );
		break;

	case 'default_delete':
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
			tep_db_query('delete from zones_to_geo_zones where geo_zone_id IN(' . substr( $sIds, 0, -1 ) . ')' );
			tep_db_query('delete from geo_zones where geo_zone_id IN(' . substr( $sIds, 0, -1 ) . ')' );
		}

		// Redireccionamos
		$messageStack->addSession( 'success', GEO_ZONES_DELETE_SUCCESS, 'success' );
		tep_redirect( $_SERVER['HTTP_REFERER'] );
		break;

	default:
		// Variables
		$sSubtitle = GEO_ZONES_SUBTITLE;
		$aButtons = array(
			array( 'title' => TEXT_ADD, 'href' => tep_href_link( $sUrlPage, 'action=default_crud' ), 'icon' => 'fa-plus', 'anchor_class' => 'verde' )
		);
		$geoZonesTypes = pharaonix_getArrayAssociativeSql('SELECT geo_zones_type_id as id, geo_zones_type_title as text FROM geo_zones_type', 'id', 'text');

		// JS
		$aJs = [$sPathModule . '/js/default.js'];
		$aStyle = [$sPathModule . '/css/style.css'];

		// Html para el boton masivo
		$sHtmlActionMasivo = '<label class="column afluid">' . GEO_ZONES_APPLY_ACTION . ':&nbsp;&nbsp;</label>
			<div class="column afluid"><div class="drop masv xfselect">
				<div>' . GEO_ZONES_ACTIONS . '</div>
				<ul class="down drch">
					<li><a data-question="' . GEO_ZONES_DELETE_RECORDS_CONFIRM . '" data-error="' . GEO_ZONES_DELETE_RECORDS_ERROR . '" data-action="' . tep_href_link( $sUrlPage, 'action=default_delete' ) . '" href="javascript:void(0);" class="hv"><i class="fa fa-trash"></i>' . GEO_ZONES_DELETE_RECORDS . '</a></li>
				</ul>
			</div></div>&nbsp; - &nbsp;';

		// Sql
		$sSql = 'SELECT gz.geo_zone_id, gz.geo_zone_name, gz.geo_zone_description, gz.last_modified, gz.date_added, count(ztgz.geo_zone_id) as zones_count, gz.geo_zones_type_id
					 FROM geo_zones gz
					 LEFT JOIN zones_to_geo_zones ztgz ON (gz.geo_zone_id = ztgz.geo_zone_id)
					 ' . ($sWhere ?? '') . ' GROUP BY gz.geo_zone_id ORDER BY geo_zone_name ASC';

		$aRows= tep_db_query( $sSql );

		// Modulo
		$sHtmlModule = includeTemplate( $sPathTemplate . '/default.php' );
		break;
}
