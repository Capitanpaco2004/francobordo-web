<?php
// Pintamos cabeceras de la tabla
$sHtmlModule .= '<div class="box-tbl" style="width: 100%">';
	$sHtmlModule .= tep_draw_form( 'promotions', $sUrlPage, '', 'post' );
	$sHtmlModule .= '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">';
		$sHtmlModule .= '<thead>';
		$sHtmlModule .= '<tr>';
			$sHtmlModule .= '<td width="50" style="text-align: center">ID</td>';
			$sHtmlModule .= '<td style="text-align: left;" width="">Promoción</td>';
			$sHtmlModule .= '<td width="125">Fecha Inicio</td>';
			$sHtmlModule .= '<td width="125">Fecha Fin</td>';
			$sHtmlModule .= '<td width="125">Banner</td>';
			$sHtmlModule .= '<td width="125">Estado</td>';
			$sHtmlModule .= '<td width="125">Acción</td>';
			$sHtmlModule .= '</tr>';
		$sHtmlModule .= '</thead>';
		$sHtmlModule .= '<tbody>';

		// Recorremos popup para mostrar
		while( $aDato = tep_db_fetch_array( $aDatos ) )
		{
		$sHtmlModule .= '<tr>';
			$sHtmlModule .= '<td align="center">#' . $aDato['promotion_id'] . '</td>';
			$sHtmlModule .= '<td align="left">' . $aDato['promotion_name'] . '</td>';
			$sHtmlModule .= '<td align="center">' . formatPromotionDate($aDato['promotion_start']) . '</td>';
			$sHtmlModule .= '<td align="center">' . formatPromotionDate($aDato['promotion_end']) . '</td>';
			$sHtmlModule .= '<td align="center">
				<div style="cursor:pointer" class="btus" data-id=' . $aDato['promotion_id'] . ' data-banner="' . $aDato['promotion_banner'] . '">
				<img width="10" height="10" src="images/icon_status_green' . ($aDato['promotion_banner'] == 0 ? '_light' : '') . '.gif" />
				<img width="10" height="10" src="images/icon_status_red' . ($aDato['promotion_banner'] == 1 ? '_light' : '') . '.gif" />
</div>
</td>';
$sHtmlModule .= '<td align="center">
	<div style="cursor:pointer" class="stus" data-id=' . $aDato['promotion_id'] . ' data-status="' . $aDato['promotion_status'] . '">
	<img width="10" height="10" src="images/icon_status_green' . ($aDato['promotion_status'] == 0 ? '_light' : '') . '.gif" />
	<img width="10" height="10" src="images/icon_status_red' . ($aDato['promotion_status'] == 1 ? '_light' : '') . '.gif" />
	</div>
</td>';
$sHtmlModule .= '<td align="center">';
	$sHtmlModule .= '<div style="display: inline-block; margin-bottom: -7px;" class="btn-group">';
		$sHtmlModule .= '<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>';
		$sHtmlModule .= '<ul class="dropdown-menu" style="left: -70px;">';
			$sHtmlModule .= '<li><a href="' . tep_href_link( $sUrlPage, 'a=edit&page=' . $sGetPage . '&promotion=' . $aDato['promotion_id'] ) . '"><span style="padding-top: 1px;" class="icos-pencil"></span>Editar</a></li>';
			$sHtmlModule .= '<li><a href="' . tep_href_link( $sUrlPage, 'a=delete&promotion=' . $aDato['promotion_id'] ) . '" class="dlet" data-text="0" onclick="if( ! confirm(\'¿Eliminar registro seleccionado?\') ) return false;"><span style="padding-top: 1px;" class="icos-trash"></span>Eliminar</a></li>';
			$sHtmlModule .= '</ul>';
		$sHtmlModule .= '</div>';
	$sHtmlModule .= '</td>';
$sHtmlModule .= '</tr>';
}

// Fin de tabla y paginacion //
$sHtmlModule .= '</tbody>';
$sHtmlModule .= '</table>';
$sHtmlModule .= '</form>';
$sHtmlModule .= $aDatoSplit->showPaginateTable( tep_get_all_get_params( array( 'page' ) ) );
$sHtmlModule .= '</div>';

// Helper para formatear fecha
function formatPromotionDate($date)
{
	if (empty($date) || $date == '0000-00-00 00:00:00') {
		return '-';
	}
	return date('d/m/Y H:i', strtotime($date)) . ' h.';
}
