<?php
	// Funcion utilizada en el attributermanager para ordenar la tabla products_stock al ordenar una opcion
	function am_arrayDown($a,$x) 
	{
		if( count($a)-1 > $x ) 
		{
			$b = array_slice($a,0,$x,true);
			$b[] = $a[$x+1];
			$b[] = $a[$x];
			$b += array_slice($a,$x+2,count($a),true);
			return($b);
		}
		else
			return $a;
	}
	
	// Funcion utilizada en el attributermanager para ordenar la tabla products_stock al ordenar una opcion
	function am_arrayUp($a,$x) 
	{
		if( $x > 0 and $x < count($a) ) 
		{
			$b = array_slice($a,0,($x-1),true);
			$b[] = $a[$x];
			$b[] = $a[$x-1];
			$b += array_slice($a,($x+1),count($a),true);
			return($b);
		}
		else
			return $a;
	} 

	// Funcion utilizada en el attributermanager para realizar combinaciones de atributos
	function am_multiplecombinations($arrays, $i = 0)
	{
		if (!isset($arrays[$i]))
			return array();

		if ($i == count($arrays) - 1)
			return $arrays[$i];

		$tmp = am_multiplecombinations($arrays, $i + 1);

		$result = array();

		foreach ($arrays[$i] as $v) 
			foreach ($tmp as $t) 
				$result[] = is_array($t) ? array_merge(array($v), $t) : array($v, $t);

		return $result;
	}

	// Funcion utilizada en el attributermanager para realizar combinaciones de atributos
	function am_combinationsAttributes($aAtributes, $nCombination)
	{
		// Variables
		$sCode = '';
		$nTotal = count( $aAtributes );
		$aReturn = array();
		
		// Restamos combinacion ya que si nos piden 3 sera 2 para empezar desde el 0
		$nCombination--;

		// Recorremos para crear el codigo
		for( $nCont = 0; $nCont <= $nCombination; $nCont++ )
		{
			$sCode .= 'for( $nCont' . $nCont . ' = ' . ($nCont == 0 ? '0' : '$nCont' . ($nCont - 1) . ' + 1') . '; $nCont' . $nCont . ' < ' . $nTotal . '; $nCont' . $nCont . '++ )';
		
			if( $nCont == $nCombination )
			{
				$sCode .= '{';
					$sAux = '';
					$sOpciones = '';

					for( $nCont1 = 0; $nCont1 <= $nCombination; $nCont1++ )
					{
						$sAux .= 'array( $aAtributes[$nCont' . $nCont1 . '] ),';
						$sOpciones .= '$nCont' . $nCont1 . '.\',\'.';
					}
				
					$sCode .= '$aAux = array_merge(' . substr( $sAux, 0, -1 ) . ');';
					$sCode .= '$aAux = am_multiplecombinations($aAux);';
					
					$sCode .= '$aReturn[] = array("opciones" => ' . substr($sOpciones, 0, -5) . ', "valores" => $aAux);';
				$sCode .= '}';
			}
		}
		
		// Procesamos código
		eval( $sCode );
		
		// Retornamos
		return $aReturn;
	}

	// Funcion para el admin que muestra el campo de tabla a ordenar
	function admin_setTableTdSort($sUrlPage, $sId, $sName, $aHref = array('page', 'orderby', 'sort') )
	{
		// Variables
		global $sGetOrderby, $sGetSort;
		$sSort = 'DESC';

		if( $sGetOrderby == $sId && $sGetSort == 'DESC' )
			$sSort = 'ASC';

		if( $sGetOrderby == $sId )
			$sClass = 'srtg_' . ($sSort == 'DESC' ? 'ASC' : 'DESC');

		return '<a href="' . tep_href_link( $sUrlPage, tep_get_all_get_params($aHref) . 'orderby=' . $sId . '&amp;sort='. $sSort) . '">' . $sName . '<span class="sorting ' . $sClass . '"></span></a>';
	}
	
	// Funcion usada en el admin, devuelve un array preparado para usar en un combobox con idiomas
	function admin_getComboIdiomas()
	{	
		$aIdiomas = tep_get_languages();
		$aComboIdiomas = array();

		// Obtenemos un array para el combobox de idiomas
		foreach( $aIdiomas as $aIdioma )
			$aComboIdiomas[] = array( 'id' => $aIdioma['id'], 'text' => $aIdioma['name'] );
			
		// Retornamos
		return $aComboIdiomas;
	}
	
	// Funcion usada en el admin, devuelve un array preparado para usar en un combobox con SI,NO
	function admin_getComboSiNo()
	{
		return array( array( 'id' => '', 'text' => 'Todos' ), array( 'id' => '0', 'text' => 'NO' ), array( 'id' => '1', 'text' => 'SI' ) );	
	}

	// Funcion usada en el admin que busca un valor dentro de un array tipo combobox 'id', 'text'
	function admin_getValueKeyArrayCombo($aArrayCombo, $sKey, $sKeyReturn, $sValue)
	{
		foreach( $aArrayCombo as $aAux )
		{
			if( $aAux[$sKey] == $sValue )
				return $aAux[$sKeyReturn];
		}

		return false;
	}
	
	// Funcion para el admin para mostrar las acciones de una fila como editar, eliminar, etc
	function admin_setTableTdAction($aOpciones)
	{
		// Variables
		$sHtml = '';

		$sHtml .= '<td align="center">';
			$sHtml .= '<div style="display: inline-block; margin-bottom: -7px;" class="btn-group">';
				$sHtml .= '<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>';
				$sHtml .= '<ul class="dropdown-menu" style="left: -70px;">';
					foreach( $aOpciones as $aOpcion )
					{
						$sClassHref = array_key_exists('CLASS_HREF', $aOpcion) ? 'class="' . $aOpcion['CLASS_HREF'] . '"' : '';
						$sAttrHref = array_key_exists('ATTR_HREF', $aOpcion) ? $aOpcion['ATTR_HREF'] : '';

						$sHtml .= '<li><a ' . $sAttrHref . ' ' . $sClassHref . ' href="' . $aOpcion['HREF'] . '"><span style="padding-top: 1px;" class="' . $aOpcion['CLASS'] . '"></span>' . $aOpcion['TEXT'] . '</a></li>';
					}
				$sHtml .= '</ul>';
			$sHtml .= '</div>';
		$sHtml .= '</td>';
		
		return $sHtml;
	}
?>