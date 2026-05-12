<?php echo tep_draw_form( 'serchmrc', tep_href_link( FILENAME_ALLMANUFACTURERS, '', 'NONSSL', false ), 'get', 'id="form-mrcs" class="xform"' ); ?>
	<input type="text" id="buscar-mrcs" name="search" placeholder="<?php echo TEXT_BUSCAR_MARCAR; ?>" />
	<button title="Buscar" type="submit" class="BgicoN-Search"></button>
</form>

<div class="abc">
	<div class="row row1">
		<a href="javascript: void(0);" data-val="A">A</a>
		<a href="javascript: void(0);" data-val="B">B</a>
		<a href="javascript: void(0);" data-val="C">C</a>
		<a href="javascript: void(0);" data-val="D">D</a>
		<a href="javascript: void(0);" data-val="E">E</a>
		<a href="javascript: void(0);" data-val="F">F</a>
		<a href="javascript: void(0);" data-val="G">G</a>
		<a href="javascript: void(0);" data-val="H">H</a>
		<a href="javascript: void(0);" data-val="I">I</a>
		<a href="javascript: void(0);" data-val="J">J</a>
		<a href="javascript: void(0);" data-val="K">K</a>
		<a href="javascript: void(0);" data-val="L">L</a>
		<a href="javascript: void(0);" data-val="M">M</a>
	</div>
	<div class="row row2">
		<a href="javascript: void(0);" data-val="N">N</a>
		<a href="javascript: void(0);" data-val="O">O</a>
		<a href="javascript: void(0);" data-val="P">P</a>
		<a href="javascript: void(0);" data-val="Q">Q</a>
		<a href="javascript: void(0);" data-val="R">R</a>
		<a href="javascript: void(0);" data-val="S">S</a>
		<a href="javascript: void(0);" data-val="T">T</a>
		<a href="javascript: void(0);" data-val="U">U</a>
		<a href="javascript: void(0);" data-val="V">V</a>
		<a href="javascript: void(0);" data-val="W">W</a>
		<a href="javascript: void(0);" data-val="X">X</a>
		<a href="javascript: void(0);" data-val="Y">Y</a>
		<a href="javascript: void(0);" data-val="Z">Z</a>
	</div>
	<div class="clear"></div>
</div>

<div id="box-prdct">
<?php
	// Si existen fabricantes
	if( tep_db_num_rows( $aRowsManufacturers ) > 0 )
	{
		$sLetraAnt = '';
		while( $aRow = tep_db_fetch_array($aRowsManufacturers) )
		{
			$sLetra = strtoupper( substr( $aRow['manufacturers_name'], 0, 1 ) );
			if( is_numeric( $sLetra ) )
				$sLetra = 'Nº';

			if( $sLetraAnt == '' || $sLetraAnt != $sLetra )
			{
				if( $sLetraAnt != '' )
					echo '</ul>';

				echo '<div class="ltra" id="brn-' . $sLetra . '">' . $sLetra . '</div>';
				echo '<ul>';
			}

			$sLetraAnt = $sLetra;

			echo '<li><a href="' . tep_href_link( FILENAME_MANUFACTURERS, 'manufacturers_id=' . $aRow['manufacturers_id'] ) . '" title="Comprar '. $aRow['manufacturers_name'] . '">
				' . ucfirst( $aRow['manufacturers_name'] ) . '
			</a></li>';
		}
		echo '</ul>';
	}
	else
		echo '<div class="mensaje">' . ALLMANUFACTURERS_NO_FOUND . '</div>';
?>
	<div class="clear"></div>
</div>