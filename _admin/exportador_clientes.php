<?php 
	require( 'includes/application_top.php' );

	// Variables
	$aDatos         = null;
	$aDato          = null;
	$aExportar      = array();
	$aProvincias    = array();
	$sSqlSelect     = '';
	$sSqlFrom       = '';
	$sSqlWhere      = '';
	$sMensaje       = '';
	$nFields        = null;
	$nCont          = null;
	$aCampos        = array();
	$aCampo			= null;
	$sExcel         = '';
	
	
	// Datos exportar
	$aExportar = array( array( 'id' => '*', 'text' => 'Todos' ), 
						array( 'id' => '1', 'text' => 'Sin compra' ),
						array( 'id' => '2', 'text' => 'Con compra' ) );

	// Datos paises
	$aDatos = tep_db_query( "SELECT countries_id, countries_name, countries_iso_code_2
							 FROM countries
							 ORDER BY countries_name ASC"); 

	$aCountries[] = array( 'id' => '*', 'text' => 'Todos' );

	while( $aDato = tep_db_fetch_array( $aDatos ) )
	{
		$aCountries[] = array( 'id'   => $aDato['countries_id'],
							   'text' => $aDato['countries_name'] . ' (' . $aDato['countries_iso_code_2'] . ')' );
	}
	
	// Datos ciudades
	$aDatos = tep_db_query( 'SELECT zone_id, zone_name
							 FROM zones
							 WHERE zone_country_id = 195
							 ORDER BY zone_name ASC' ); 

	$aProvincias[] = array( 'id' => '*', 'text' => 'Todos' );

	while( $aDato = tep_db_fetch_array( $aDatos ) )
	{
		$aProvincias[] = array( 'id'   => $aDato['zone_id'],
								'text' => $aDato['zone_name'] );
	}

	// Si nos han enviado el formulario
	if( $_POST['type'] )
	{
		// Consulta por defecto
		$sSqlSelect = 'SELECT distinct c.customers_id as ID_CLIENTE, c.customers_email_address AS EMAIL, c.customers_firstname AS NOMBRE, c.customers_lastname AS APELLIDOS';
		$sSqlFrom = ' FROM customers c';
		$sSqlFrom .= ' inner join address_book ab on(c.customers_id = ab.customers_id)';
		$sSqlFrom .= ' inner join countries co on(ab.entry_country_id = co.countries_id)';
		$sSqlFrom .= ' inner join zones z on(ab.entry_zone_id = z.zone_id)';

		// Si el tipo es 2 añaadimos mas campos
		if( $_POST['type'] == 2 )
			$sSqlSelect .= ', c.customers_telephone AS TELEFONO, co.countries_name AS PAIS, z.zone_name AS PROVINCIA';

		// Comprobamos la opcion de exportar
		if( $_POST['exportar'] == '1' )
			$sSqlWhere .= ($sSqlWhere == '' ? 'where ' : ' and ') . 'c.customers_id not in ( select distinct(customers_id) from orders)';
		elseif( $_POST['exportar'] == '2' )
			$sSqlFrom .= ' inner join orders o on(c.customers_id = o.customers_id)';

		// Comprobamos la opcion del pais
		if( $_POST['countries'] != '*' )
			$sSqlWhere .= ($sSqlWhere == '' ? 'where ' : ' and ') . 'ab.entry_country_id = "' . (int)$_POST['countries'] . '"';

		// Comprobamos la opcion de la provincia
		if( $_POST['zones'] != '*' )
			$sSqlWhere .= ($sSqlWhere == '' ? 'where ' : ' and ') . 'ab.entry_zone_id = "' . $_POST['zones'] . '"';
		
		// Extraemos los datos
		$aDatos = tep_db_query( $sSqlSelect . $sSqlFrom . $sSqlWhere . ' order by ID_CLIENTE asc' );

		// Comprobamos el resultado
		if( tep_db_num_rows( $aDatos ) == 0 )
			$sMensaje = '<div class="msje msje-wrng"><div class="msje-icon"></div>No existen registros con los filtros establecidos</div>';
		else
		{
			// Obtenemos los campos
			$nFields = tep_db_num_fields( $aDatos );

			for( $nCont = 0; $nCont < $nFields; $nCont++ )
				$aCampos[] = mysqli_field_name( $aDatos, $nCont );
			
			// Principio de la tabla
			$sExcel = '<table><tr>';
			
			// Creamos las cabeceras
			foreach( $aCampos as $aCampo )
				$sExcel .= '<th>' . $aCampo .  '</th>';

			$sExcel .= '</tr>';

			// Creamos los regitros
			while( $aDato = tep_db_fetch_array( $aDatos ) )
			{
				$sExcel .= '<tr>';

				foreach( $aCampos as $aCampo )
					$sExcel .= '<td>' . htmlentities( $aDato[$aCampo], ENT_COMPAT, 'UTF-8' ) . '</td>';

				$sExcel .= '</tr>';
			}

			// Fin de la tabla
			$sExcel .= '</table>';
			
			header( 'Content-type: application/vnd.ms-excel;' );
			header( 'Content-Disposition: attachment; filename=exportador_clientes_excel_' . date( 'd_m_Y_H-i-s' ) . '.xls');
			header ('Content-Transfer-Encoding: binary');
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' ); 

			echo $sExcel;
			exit();
		}
	}

	require( THEME . 'html/header.php' );
?>

<style type="text/css">
	#form-expr
	{
		background-color: #F5F5F5;
		border: 1px dashed #CCCCCC;
		font-weight: bold;
		padding: 10px;
		overflow: hidden;
		padding: 10px;
	}

	#form-expr p
	{
		float: left;
		margin-right: 13px;
	}

	#form-expr label
	{
		display: inline-block;
		margin-right: 7px;
	}

	#btns
	{
		float: left;
		width: 100%;
		margin-top: 10px;
	}

	#btns a
	{
		background-color: #666666;
		color: #FFFFFF;
		display: inline-block;
		margin-right: 5px;
		padding: 4px 8px;
		-moz-border-radius:5px;
		-webkit-border-radius: 5px;
		-khtml-border-radius: 5px;
	}

	#btns a:hover
	{
		background-color: #6f6f6f;
		text-decoration: none;
	}
</style>

<h1 class="pageHeading">Exportador de clientes Excel - Marketing</h1>
<?php echo $sMensaje; ?>
<form name="form_exportar" id="form-expr" method="post" action="exportador_clientes.php">
	<p>
		<label for="exportar">Exportar:</label>
		<?php echo tep_draw_pull_down_menu( 'exportar', $aExportar, null ); ?>
	</p>
	<p>
		<label for="countries">Pais:</label>
		<?php echo tep_draw_pull_down_menu( 'countries', $aCountries, 195, 'id="countries"' ); ?>
	</p>
	<p id="zones_p">
		<label for="zones">Provincia:</label>
		<?php echo tep_draw_pull_down_menu( 'zones', $aProvincias, null, 'id="zones"' ); ?>
	</p>
	<input id="type" name="type" type="hidden" value=""/>
	<div id="btns">
		<a href="javascript:void(0);" rel="1">Exportar emails y nombres</a>
		<a href="javascript:void(0);" rel="2">Exportar emails, nombres, telefonos y provincias</a>
	</div>
</form>

<?php
	require( THEME . 'html/footer.php' );
	require( DIR_WS_INCLUDES . 'application_bottom.php' );
?>

<script type="text/javascript">
	$(document).ready(function()
	{
		$("#btns a").click(function()
		{
			$("#type").val( this.rel );
			$("#form-expr").submit();
		});
		
		$("#countries").change(function()
		{
			if( this.value != 195 )
			{
				document.getElementById("zones").selectedIndex = 0;
				$("#zones_p").hide();
			}
			else
				$("#zones_p").show();
		});
	});
</script>