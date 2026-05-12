<?php
class splitPageResults
{
	public $query_num_rows;
	public $max_rows_per_page;
	public $current_page_number;
	public $total_pages;

	function __construct(&$current_page_number, $max_rows_per_page, &$sql_query, &$query_num_rows, $sConsulta = false)
	{
		if( empty($current_page_number) )
			$current_page_number = 1;

		$sql_query = (string)$sql_query;

		$pos_to = strlen($sql_query);
		$pos_from = strpos($sql_query, ' from', 0);

		$pos_group_by = strpos($sql_query, ' group by', $pos_from);
		
		if( $pos_group_by < $pos_to && $pos_group_by != false )
			$pos_to = $pos_group_by;

		$pos_having = strpos($sql_query, ' having', $pos_from);
		
		if( $pos_having < $pos_to && $pos_having != false)
			$pos_to = $pos_having;

		$pos_order_by = strpos($sql_query, ' order by', $pos_from);

		if( $pos_order_by < $pos_to && $pos_order_by != false )
			$pos_to = $pos_order_by;

		if( !$sConsulta )
			$reviews_count_query = tep_db_query("select count(*) as total " . substr($sql_query, $pos_from, ($pos_to - $pos_from)));
		else
			$reviews_count_query = tep_db_query( $sConsulta );
		
		$reviews_count = tep_db_fetch_array($reviews_count_query);
		$query_num_rows = $reviews_count['total'];
		
		$this->query_num_rows = $reviews_count['total'];
		$this->max_rows_per_page = $max_rows_per_page;
		$this->current_page_number = $current_page_number;

		$num_pages = ceil($query_num_rows / $max_rows_per_page);
		
		if ($current_page_number > $num_pages)
			$current_page_number = $num_pages;
		
		$offset = (intval($max_rows_per_page) * (intval($current_page_number) - 1));
		$sql_query .= " limit " . max($offset, 0) . ", " . $max_rows_per_page;
	}

	function display_links($query_numrows, $max_rows_per_page, $max_page_links, $current_page_number, $parameters = '', $page_name = 'page')
	{
		global $PHP_SELF;

		if( tep_not_null($parameters) && (substr($parameters, -1) != '&') )
			$parameters .= '&';

		// calculate number of pages needing links
		$num_pages = ceil($query_numrows / $max_rows_per_page);

		$pages_array = array();

		for ($i=1; $i<=$num_pages; $i++) 
			$pages_array[] = array('id' => $i, 'text' => $i);
		

		if( $num_pages > 1 )
		{
			$display_links = tep_draw_form('pages', basename($PHP_SELF), '', 'get');

			if( $current_page_number > 1 )
				$display_links .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $page_name . '=' . ($current_page_number - 1), 'NONSSL') . '" class="splitPageLink">' . PREVNEXT_BUTTON_PREV . '</a>&nbsp;&nbsp;';
			else
				$display_links .= PREVNEXT_BUTTON_PREV . '&nbsp;&nbsp;';

			$display_links .= sprintf(TEXT_RESULT_PAGE, tep_draw_pull_down_menu($page_name, $pages_array, $current_page_number, 'onChange="this.form.submit();"'), $num_pages);

			if( $current_page_number < $num_pages && $num_pages != 1 )
				$display_links .= '&nbsp;&nbsp;<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $page_name . '=' . ($current_page_number + 1), 'NONSSL') . '" class="splitPageLink">' . PREVNEXT_BUTTON_NEXT . '</a>';
			else
				$display_links .= '&nbsp;&nbsp;' . PREVNEXT_BUTTON_NEXT;

			if( $parameters != '' )
			{
				if( substr($parameters, -1) == '&' )
					$parameters = substr($parameters, 0, -1);
				
				$pairs = explode('&', $parameters);

				foreach($pairs as $pair)
				{
					$parts = explode('=', $pair, 2);
					$key = $parts[0];
					$value = $parts[1] ?? '';
					$display_links .= tep_draw_hidden_field(rawurldecode($key), rawurldecode($value));
				}
			}

			$display_links .= tep_hide_session_id() . '</form>';
		}
		else 
			$display_links = sprintf(TEXT_RESULT_PAGE, $num_pages, $num_pages);

		return $display_links;
	}

	function display_count($query_numrows, $max_rows_per_page, $current_page_number, $text_output)
	{
		$to_num = (intval($max_rows_per_page) * intval($current_page_number));
		
		if( $to_num > $query_numrows )
			$to_num = $query_numrows;
	
		$from_num = (intval($max_rows_per_page) * (intval($current_page_number) - 1));

		if ($to_num == 0) 
			$from_num = 0;
		else
			$from_num++;

		return sprintf($text_output, $from_num, $to_num, $query_numrows);
	}

	function showPaginateTable($parameters = '', $page_name = 'page', $sHtmlExtra = false, $sTemplate = 'default')
	{
		// Variables
		global $PHP_SELF;
		$sHtml = '';
			
		$to_num = $this->max_rows_per_page * $this->current_page_number;

		if( $to_num > $this->query_num_rows )
			$to_num = $this->query_num_rows;
			
		$from_num = $this->max_rows_per_page * ($this->current_page_number - 1);
		
		if( $to_num == 0 )
			$from_num = 0;
		else
			$from_num++;
			
		
		if( tep_not_null($parameters) && (substr($parameters, -1) != '&') )
			$parameters .= '&';
		
		switch( $sTemplate )
		{
			case 'solenopsis':
				$aTemplate = array(
					'content' => '<div class="column a12 ax row xform oeTableBottom amiddle">{CONTENT}</div>',
					'view' => '<div class="column a06 ax row aflex amiddle">{HTML_EXTRA}<span>{VIEW}</span></div><div class="pgnt column a06 tright">',
					'page_first' => '<a href="{LINK}" class="unon"><span class="fa fa-chevron-left"></span><span class="fa fa-chevron-left"></span></a>',
					'page_last' => '<a href="{LINK}" class="fa fa-chevron-left"></a>',
					'page' => '<a href="{LINK}">{TEXT}</a>',
					'page_now' => '<a href="{LINK}" class="actv">{TEXT}</a>',
					'page_next' => '<a href="{LINK}" class="fa fa-chevron-right"></a>',
					'page_end' => '<a href="{LINK}" class="unon"><span class="fa fa-chevron-right"></span><span class="fa fa-chevron-right"></span></a>',
					'pages' => '{FIRST_PAGE}{LAST_PAGE}{PAGES}{NEXT_PAGE}{END_PAGE}</div>'
				);
			break;		

			case 'default':
				$aTemplate = array(
					'content' => '<div class="tableFooter">{CONTENT}<div class="clear"></div></div>',
					'view' => '<div class="dataTables_info" id="DataTables_Table_0_info">{HTML_EXTRA}{VIEW}</div>',
					'page_first' => '<a href="{LINK}" class="first paginate_button" id="DataTables_Table_0_first">Primero</a>',
					'page_last' => '<a href="{LINK}" class="previous paginate_button" id="DataTables_Table_0_previous">Anterior</a>',
					'page' => '<a href="{LINK}" class="paginate_button">{TEXT}</a>',
					'page_now' => '<a href="{LINK}" class="paginate_active">{TEXT}</a>',
					'page_next' => '<a href="{LINK}" class="paginate_button">Siguiente</a>',
					'page_end' => '<a href="{LINK}" class="paginate_button">Última</a>',
					'pages' => '<div class="dataTables_paginate paging_full_numbers" id="DataTables_Table_0_paginate">{FIRST_PAGE}{LAST_PAGE}{PAGES}{NEXT_PAGE}{END_PAGE}</div>'
				);
			break;
		}
		
		// calculate number of pages needing links
		$num_pages = ceil($this->query_num_rows / $this->max_rows_per_page);
		
		$aTemplate['view'] = str_replace( array( '{HTML_EXTRA}', '{VIEW}' ), array( ($sHtmlExtra !== false ? $sHtmlExtra : ''), 'Viendo <b>' . $from_num . '</b> de <b>' . $to_num . '</b> de <b>' . $this->query_num_rows . '</b> registros' ), $aTemplate['view'] );
		
		if( $num_pages > 1 )
		{
			// Controlamos que si estamos en la primera página y pulsamos "anterior" no siga restando
			if( $this->current_page_number - 1 < 1 )
				$nPaginaAnterior = 1;
			else
				$nPaginaAnterior = $this->current_page_number - 1;
		
			// Controlamos que si estamos en la ultima página y pulsamos "siguiente" no siga sumando
			if( $this->current_page_number + 1 > $num_pages )
				$nPaginaSiguiente = $num_pages;
			else
				$nPaginaSiguiente = $this->current_page_number + 1;

			// Posibilidad de ir a la primera pagina cuando la pagina actual esta lejos de la primera
			if( $this->current_page_number >= 5 )
				$aTemplate['pages'] = str_replace( '{FIRST_PAGE}', str_replace( '{LINK}', tep_href_link(basename($PHP_SELF), $parameters . $page_name  . '=0' ), $aTemplate['page_first'] ), $aTemplate['pages'] );

			// Boton anterior
			if( $this->current_page_number > 1 )
				$aTemplate['pages'] = str_replace( '{LAST_PAGE}', str_replace( '{LINK}', tep_href_link(basename($PHP_SELF), $parameters . $page_name  . '=' . $nPaginaAnterior, 'NONSSL'), $aTemplate['page_last'] ), $aTemplate['pages'] );
			
			// Pintamos desde la posicion actual 3 paginas hacia atras ( 1 2 3 4 5 6(PagAct) 7 8 9 )
			// Si estamos en la posicion 2 no podemos restar ya que pintaremos nuemros negativos
			// Asi que controlamos si la pagina actual - 3 es siempre mayor que 1
			if( $this->current_page_number - 3 < 1 )
				$nAux = 1;
			else
				$nAux = $this->current_page_number - 3;

			// Pintamos las paginas del lado izquierdo de la pagina actual y la pagina actual
			for( $nCont = $nAux; $nCont < $this->current_page_number; $nCont++ )
				$sHtml .= str_replace( array( '{LINK}', '{TEXT}' ), array( tep_href_link(basename($PHP_SELF), $parameters . $page_name  . '=' . $nCont ), $nCont ), $aTemplate['page'] );
				
			// Pagina actual
			$sHtml .= str_replace( array( '{LINK}', '{TEXT}' ), array( tep_href_link(basename($PHP_SELF), $parameters . $page_name  . '=' . $nCont ), $nCont ), $aTemplate['page_now'] );
								
			// Pintamos las paginas del lado derecho de la pagina actual
			for( $nCont = $this->current_page_number + 1; $nCont <= $this->current_page_number + 3; $nCont++ )
			{
				// Si hemos sobrepasado la cantidad de paginas permitida salimos
				if( $nCont >= $num_pages )
					break;

				$sHtml .= str_replace( array( '{LINK}', '{TEXT}' ), array( tep_href_link(basename($PHP_SELF), $parameters . $page_name  . '=' . $nCont ), $nCont ), $aTemplate['page'] );
			}

			// Botones
			$aTemplate['pages'] = str_replace( '{PAGES}', $sHtml, $aTemplate['pages'] );

			// Boton siguiente
			if( $this->current_page_number < $num_pages && $num_pages != 1 )
				$aTemplate['pages'] = str_replace( '{NEXT_PAGE}', str_replace( '{LINK}', tep_href_link(basename($PHP_SELF), $parameters . $page_name  . '=' . $nPaginaSiguiente ), $aTemplate['page_next'] ), $aTemplate['pages'] );
			
			// Posibilidad de ir a la última página cuando la pagina actual esta lejos de ella
			if( $this->current_page_number != $num_pages )
				$aTemplate['pages'] = str_replace( '{END_PAGE}', str_replace( '{LINK}', tep_href_link(basename($PHP_SELF), $parameters . $page_name  . '=' . $num_pages ), $aTemplate['page_end'] ), $aTemplate['pages'] );
		}
		
		// Limpiamos
		$aTemplate['pages'] = str_replace( array( '{FIRST_PAGE}', '{LAST_PAGE}', '{PAGES}', '{NEXT_PAGE}', '{END_PAGE}' ), array( '', '', '', '', '' ), $aTemplate['pages'] );
	
		// Retornamos
		return str_replace( '{CONTENT}', $aTemplate['view'] . $aTemplate['pages'], $aTemplate['content'] );
	}
}
?>
