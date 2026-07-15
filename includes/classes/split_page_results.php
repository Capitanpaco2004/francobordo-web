<?php

  class splitPageResults {
    var $sql_query, $number_of_rows, $current_page_number, $number_of_pages, $number_of_rows_per_page, $page_name;
    public $from;

/* class constructor */
    function __construct($query, $max_rows, $count_key = '*', $page_holder = 'page', $sQueryCount = false) {
      global $_GET, $_POST;

      $this->sql_query = $query;
      $this->page_name = $page_holder;

      if (isset($_GET[$page_holder])) {
        $page = $_GET[$page_holder];
      } elseif (isset($_POST[$page_holder])) {
        $page = $_POST[$page_holder];
      } else {
        $page = '';
      }

      if (empty($page) || !is_numeric($page)) $page = 1;
      $this->current_page_number = $page;

      $this->number_of_rows_per_page = $max_rows;

	  $query = preg_replace( '/(GROUP BY)(.*)/i', '', $query );

      $pos_to = strlen($query);
      $pos_from = strpos($query, ' from', 0);

      $pos_group_by = strpos($query, ' group by', $pos_from);
      if (($pos_group_by < $pos_to) && ($pos_group_by != false)) $pos_to = $pos_group_by;

      $pos_having = strpos($query, ' having', $pos_from);
      if (($pos_having < $pos_to) && ($pos_having != false)) $pos_to = $pos_having;

      $pos_order_by = strpos($query, ' order by', $pos_from);
      if (($pos_order_by < $pos_to) && ($pos_order_by != false)) $pos_to = $pos_order_by;

      if (strpos($query, 'distinct') || strpos($query, 'group by')) {
        $count_string = 'distinct ' . tep_db_input($count_key);
      } else {
        $count_string = tep_db_input($count_key);
      }

	 // Resiliencia: una query COUNT malformada (típico de bots de SQLi contra el buscador con
	 // caracteres que rompen la sintaxis FULLTEXT/filtros) lanzaba PDOException → Fatal 500.
	 // La capturamos y degradamos a 0 filas: el listado renderiza vacío en vez de petar. La query
	 // PRINCIPAL corre aparte; un bug real de listado seguiría siendo visible por otra vía.
	 try {
		 if( $sQueryCount === false )
		 {
			if( preg_match( '/union/i', $query ) )
				$count_query = tep_db_query("select count(" . $count_string . ") as total FROM (" . substr($query, 0, $pos_to) . ') AS count_query');
			else
			  $count_query = tep_db_query("select count(" . $count_string . ") as total " . substr($query, $pos_from, ($pos_to - $pos_from)));
		 }
		 else
			  $count_query = tep_db_query( $sQueryCount );
	 } catch ( \Throwable $__eCount ) {
		 $count_query = false;
	 }

		// Obtenemos el total de filas //

		// Si no tenemos una agrupacion, obtenemos el total
		//if( ! preg_match( '/group by/i', $this->sql_query ) )
		{
			// Registro
			$count = tep_db_fetch_array($count_query);
			// Obtenemos el total (0 si el count falló/degradó → evita null en el cálculo de páginas)
			$this->number_of_rows = is_array($count) ? (int)$count['total'] : 0;
		}
		// Si tenemos una agrupacion, recorremos los registros y los sumamos
		/*else
		{
			// Recorremos los registros y los vamos sumando
			while( $count = tep_db_fetch_array($count_query) )
				$this->number_of_rows++;
		}*/

      $this->number_of_pages = ceil($this->number_of_rows / $this->number_of_rows_per_page);

      if ($this->current_page_number > $this->number_of_pages) {
        $this->current_page_number = $this->number_of_pages;
      }

		$this->from = $this->current_page_number * $this->number_of_rows_per_page;
		
		if( $this->from > $this->number_of_rows )
			$this->from = $this->number_of_rows;

      $offset = ($this->number_of_rows_per_page * ($this->current_page_number - 1));

      $this->sql_query .= " limit " . max($offset, 0) . ", " . $this->number_of_rows_per_page;
    }

/* class functions */

// display split-page-number-links
    function display_links($max_page_links, $parameters = '') {
      global $PHP_SELF, $request_type;

      $display_links_string = '';

      $class = 'class="pageResults"';

      if (tep_not_null($parameters) && (substr($parameters, -1) != '&')) $parameters .= '&';

// previous button - not displayed on first page
      if ($this->current_page_number > 1) $display_links_string .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $this->page_name . '=' . ($this->current_page_number - 1), $request_type) . '" class="pageResults paginacion_anterior" title=" ' . PREVNEXT_TITLE_PREVIOUS_PAGE . ' ">' . PREVNEXT_BUTTON_PREV . '</a>';

// check if number_of_pages > $max_page_links
      $cur_window_num = intval($this->current_page_number / $max_page_links);
      if ($this->current_page_number % $max_page_links) $cur_window_num++;

      $max_window_num = intval($this->number_of_pages / $max_page_links);
      if ($this->number_of_pages % $max_page_links) $max_window_num++;

// previous window of pages
      if ($cur_window_num > 1) $display_links_string .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $this->page_name . '=' . (($cur_window_num - 1) * $max_page_links), $request_type) . '" class="pageResults" title=" ' . sprintf(PREVNEXT_TITLE_PREV_SET_OF_NO_PAGE, $max_page_links) . ' ">...</a>';

// page nn button
      for ($jump_to_page = 1 + (($cur_window_num - 1) * $max_page_links); ($jump_to_page <= ($cur_window_num * $max_page_links)) && ($jump_to_page <= $this->number_of_pages); $jump_to_page++) {
		if( $jump_to_page < 0 )
			continue;
        if ($jump_to_page == $this->current_page_number) {
          $display_links_string .= '<strong>' . $jump_to_page . '</strong>';
        } else {
          $display_links_string .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $this->page_name . '=' . $jump_to_page, $request_type) . '" class="pageResults" title=" ' . sprintf(PREVNEXT_TITLE_PAGE_NO, $jump_to_page) . ' ">' . $jump_to_page . '</a>';
        }
      }

// next window of pages
      if ($cur_window_num < $max_window_num) $display_links_string .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . $this->page_name . '=' . (($cur_window_num) * $max_page_links + 1), $request_type) . '" class="pageResults" title=" ' . sprintf(PREVNEXT_TITLE_NEXT_SET_OF_NO_PAGE, $max_page_links) . ' ">...</a>';

// next button
      if (($this->current_page_number < $this->number_of_pages) && ($this->number_of_pages != 1)) $display_links_string .= '<a href="' . tep_href_link(basename($PHP_SELF), $parameters . 'page=' . ($this->current_page_number + 1), $request_type) . '" class="pageResults paginacion_siguiente" title=" ' . PREVNEXT_TITLE_NEXT_PAGE . ' ">' . PREVNEXT_BUTTON_NEXT . '</a>';

      return $display_links_string;
    }

// display number of total products found
    function display_count($text_output) {
      $to_num = ($this->number_of_rows_per_page * $this->current_page_number);
      if ($to_num > $this->number_of_rows) $to_num = $this->number_of_rows;

      $from_num = ($this->number_of_rows_per_page * ($this->current_page_number - 1));

      if ($to_num == 0) {
        $from_num = 0;
      } else {
        $from_num++;
      }

      return sprintf($text_output, $from_num, $to_num, $this->number_of_rows);
    }
	
	function ajax()
	{		
		return '<div data-max="' . ceil($this->number_of_rows / $this->number_of_rows_per_page ) . '" id="bton-ver-mas" style="display: none;"></div>';	
	}
  }
?>