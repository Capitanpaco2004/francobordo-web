<?php
	use util\tools as tools;

	class rgpd
	{
		/**
		* Termino general actual
		* @var string
		*/
		public $aTermGeneral;

		/**
		* Constructor
		*/
		public function __construct()
		{
			// Si la accion es install pasamos
			if( array_key_exists( 'action', $_GET ) && in_array( $_GET['action'], array( 'install',  ) ) )
				return false;

			// Variables
			global $languages_id, $nCustomerIdTermPivacyGeneral;
			$sAction = array_key_exists( 'a', $_GET ) ? $_GET['a'] : '';

			// Obtenemos los terminimos generales
			$this->aTermGeneral = pharaonix_queryOne( 'select tpd.text, tpd.title, tpd.info, tp.id_term_pivacy_general
													   from rgpd_term_privacy_general_description tpd
													   inner join rgpd_term_privacy_general tp on (tp.id_term_pivacy_general = tpd.id_term_pivacy_general)
													   where tpd.language_id = "' . (int)$languages_id . '" ORDER BY tp.id_term_pivacy_general DESC limit 1' )->records;

			// Si contenemos alguna opcion
			switch( $sAction )
			{
				case 'rgpd_accept':
					if( tep_session_is_registered('customer_id') )
					{
						// Variables
						global $customer_id;

						// Obtenemos email del cliente
						$sEmail = pharaonix_queryOne( 'select customers_email_address from customers where customers_id = "' . $customer_id . '"' )->records['customers_email_address'];

						// Actualizamos
						tep_db_perform( 'customers', array( 'id_term_pivacy_general' => $this->aTermGeneral['id_term_pivacy_general'] ), 'update', 'customers_id = "' . (int)$customer_id . '"' );

						// Obtenemos si tiene configurado el customers_newsletter y cantidad de newsletter configurado
						$nCustomersNewsletter = pharaonix_queryOne( 'select customers_newsletter from customers where customers_id = "' . $customer_id . '"' )->records['customers_newsletter'];
						$aSubscribed = array_values( pharaonix_getArrayAssociativeSql( 'select id_term_pivacy_trade from rgpd_account_term where customers_id = "' . (int)$customer_id . '"', 'id_term_pivacy_trade', 'id_term_pivacy_trade', false, 1 ) );

						// Si el cliente no tiene nigun termino configurado y tiene el customers_newsletter activo, le añadimos todos
						if( $nCustomersNewsletter == "1" && count($aSubscribed) == 0 )
						{
							$aSubscribedAll = array_values( pharaonix_getArrayAssociativeSql( 'SELECT id_term_pivacy_trade, title, info FROM rgpd_term_privacy_trade WHERE language_id = "' . $languages_id . '"', 'id_term_pivacy_trade', 'title', false ) );

							foreach( $aSubscribedAll as $aAux )
							{
								$nIdAll = $aAux['id'];
								$sTitle = $aAux['text'];

								tep_db_perform( 'rgpd_account_term', array( 'customers_id' => $customer_id, 'id_term_pivacy_trade' => $nIdAll ) );

								tep_db_perform( 'rgpd_log_term_privacy', array(
									'customers_id' => $customer_id,
									'customers_mail' => $sEmail,
									'ip' => tools::getIP(),
									'date' => date( 'Y-m-d H:i:s' ),
									'type' => 'comercial',
									'term_name' => $sTitle,
									'id_term_pivacy' => $nIdAll,
									'status' => 1
								) );
							}
						}

						// Añadimos log termino
						tep_db_perform( 'rgpd_log_term_privacy', array(
							'customers_id' => $customer_id,
							'customers_mail' => $sEmail,
							'ip' => tools::getIP(),
							'date' => date( 'Y-m-d H:i:s' ),
							'type' => 'general',
							'term_name' => $this->aTermGeneral['title'],
							'id_term_pivacy' => $this->aTermGeneral['id_term_pivacy_general'],
							'status' => 1
						) );
					}

					// Detenemos
					exit();
				break;
			}
		}

		/**
		* Devuelve el termino comercial si se ha aceptado o no en los formularios mediante post. Comprobara si tenemos la aceptacióna utomatica
		*/
		public function postFormCheckTermsTrade($nIdTrade)
		{
			// Variables
			global $customer_id, $languages_id;
			$termsAgree = isset($_POST['term_trade_' . $nIdTrade]) ? tep_db_prepare_input($_POST['term_trade_' . $nIdTrade]) : '';

			// Obtenemos el termino
			$aTerm = pharaonix_queryOne( 'select title, info from rgpd_term_privacy_trade where language_id = "' . $languages_id . '" and id_term_pivacy_trade = "' . (int)$nIdTrade . '"' )->records;

			// Si estamos logueados y tenemos ocultar terms
			if( tep_session_is_registered('customer_id') && RGPD_TERMS_SHOW == 'false' )
			{
				// Obtenemos si el cliente tiene aceptado el termino
				$bExistst = pharaonix_queryOne( 'select customers_id from rgpd_account_term where customers_id = "' . (int)$customer_id . '" and id_term_pivacy_trade = "' . (int)$nIdTrade . '"' )->num_rows > 0 ? true : false;

				// Si tenemos activado
				if( $bExistst )
					return array( 'RESULT' => true, 'TERM' => $aTerm );
			}

			return array( 'RESULT' => ($termsAgree == '' ? false : true), 'MESSAGE_ERROR' =>  str_replace( '{TITLE}', $aTerm['title'], RGPD_CHECKBOX_TERMINO_TRADE_ERROR), 'TERM' => $aTerm );
		}

		/**
		* Muestra en los formularios el checkbox sobre aceptar los terminos comercial pasado por argumento
		*/
		public function formCheckTermsTrade($nIdTrade)
		{
			// Variables
			global $customer_id, $languages_id;

			// Si estamos logueados y tenemos ocultar terms
			if (tep_session_is_registered('customer_id') && RGPD_TERMS_SHOW == 'false') {
				// Obtenemos si el cliente tiene aceptado el termino
				$bExistst = pharaonix_queryOne('select customers_id from rgpd_account_term where customers_id = "' . (int)$customer_id . '" and id_term_pivacy_trade = "' . (int)$nIdTrade . '"')->num_rows > 0 ? true : false;

				// Comprobamos si tenemos la ultima versión
				if ($bExistst) {
					return '';
				}
			}

			// Obtenemos el termino
			$aTerm = pharaonix_queryOne( 'select title, info from rgpd_term_privacy_trade where language_id = "' . $languages_id . '" and id_term_pivacy_trade = "' . (int)$nIdTrade . '"' )->records;

			$aText = json_decode( str_replace( array( '\"' ), array('"'), RGPD_TERMS_TRADE_TEXT_CHECK ), true );
			$aTextTooltop = json_decode( str_replace( array( '\"' ), array('"'), RGPD_TERMS_TRADE_TEXT ), true );

			return '<div class="column a12 xform check rgpd-check"><input type="checkbox" name="term_trade_' . $nIdTrade . '" value="true" id="term_trade_' . $nIdTrade . '"><label style="margin-right: 0px;" for="term_trade_' . $nIdTrade . '"><span></span>' . str_replace( '{LINK}', tep_href_link('information.php', 'info_id=' . RGPD_TERMS_TRADE_INFO_ID),  html_entity_decode( $aText[$languages_id] ) ) . ' <i title="' . $aTextTooltop[$languages_id] . '" class="fa fa-exclamation-circle"></i></label></div>';
		}

		/**
		* Devuelve el termino general si se ha aceptado o no en los formularios mediante post. Comprobara si tenemos la aceptacióna utomatica
		*/
		public function postFormCheckTermsGeneral($sName = 'termsAgree')
		{
			// Variables
			global $customer_id;
			$termsAgree = tep_db_prepare_input($_POST[$sName]);

			// Si estamos logueados y tenemos ocultar terms
			if( tep_session_is_registered('customer_id') && RGPD_TERMS_SHOW == 'false' )
			{
				// Obtenemos el termino que tiene aceptado el cliente
				$nCustomerIdTermPivacyGeneral = pharaonix_queryOne( 'select id_term_pivacy_general from customers where customers_id = "' . $customer_id . '"' )->records['id_term_pivacy_general'];

				// Comprobamos si tenemos la ultima versión
				if( $nCustomerIdTermPivacyGeneral == $this->aTermGeneral['id_term_pivacy_general'] )
					return 'true';
			}

			return $termsAgree;
		}

		/**
		* Muestra en los formularios el checkbox sobre aceptar los terminos generales
		*/
		public function formCheckTermsGeneral($sName = 'termsAgree', $bAffiliate = false)
		{
			// Variables
			global $languages_id, $customer_id;
			$aText = json_decode( str_replace( array( '\"', "\\\'" ), array('"', "'"), RGPD_TERMS_TEXT_CHECK ), true );

			// Si estamos logueados y tenemos ocultar terms
			if( tep_session_is_registered('customer_id') && RGPD_TERMS_SHOW == 'false' )
			{
				// Obtenemos el termino que tiene aceptado el cliente
				$nCustomerIdTermPivacyGeneral = pharaonix_queryOne( 'select id_term_pivacy_general from customers where customers_id = "' . $customer_id . '"' )->records['id_term_pivacy_general'];

				// Comprobamos si tenemos la ultima versión
				if( $nCustomerIdTermPivacyGeneral == $this->aTermGeneral['id_term_pivacy_general'] )
					return '';
			}

			return '<div id="CAparagraph" class="col a12 xform check rgpd-check"><input type="checkbox" name="' . $sName . '" value="true" id="' . $sName . '"><label style="margin-right: 0px;" for="' . $sName . '"><span></span>' . str_replace( array('{LINK}', '{LINK_PRIVACY}'), array( tep_href_link('information.php', 'info_id=' . RGPD_TERMS_INFO_ID), tep_href_link('information.php', 'info_id=' . RGPD_TERMS_INFO_ID_PRIVACY) ),  html_entity_decode( $aText[$languages_id] ) ) . ($bAffiliate ? '<a style="display:inline-block" href="' . tep_href_link('information.php', 'info_id=49') . '" target="_blank"><strong><u>' . RGPD_INGRESOS . '</u></strong></a>' : '') . ' <i title="' . htmlentities($this->aTermGeneral['info']) . '" class="fa fa-exclamation-circle"></i></label></div>';
		}

		/**
		* Comprueba si esta logueado y si tiene la ultima version de los terminos generales, si no mostrara una ventana para aceptarla
		*/
		public function checkShowInformationTermsGeneralCustomer()
		{
			// Variables
			global $customer_id;

			// Si estamos registrados
			if( tep_session_is_registered('customer_id') )
			{
				// Obtenemos si tiene configurado el customers_newsletter
				$nCustomersNewsletter = pharaonix_queryOne( 'select customers_newsletter from customers where customers_id = "' . $customer_id . '"' )->records['customers_newsletter'];

				// Obtenemos el termino que tiene aceptado el cliente
				$nCustomerIdTermPivacyGeneral = pharaonix_queryOne( 'select id_term_pivacy_general from customers where customers_id = "' . $customer_id . '"' )->records['id_term_pivacy_general'];

				// Comprobamos si el cliente no tiene el ultima termino general aceptado
				if (isset($this->aTermGeneral['id_term_pivacy_general'])) {
					if( $nCustomerIdTermPivacyGeneral != $this->aTermGeneral['id_term_pivacy_general'] )
					{
						$sHtml = '<div data-newsletter="' . $nCustomersNewsletter . '" id="rgpd-wndw" class="mfp-hide zoom-anim-dialog win-repn mfp-white">';
							$sHtml .= '<div class="cntd">';
								$sHtml .= '<div class="rgpd-cntd">';
									$sHtml .= '<div class="rgpd-extr">';
										$sHtml .= '<span>' . RGPD_WINDOW_MODAL_TITLE . '</span>';
										$sHtml .= '<i><img src="theme/web/images/general/rgpd-shield.jpg" /></i>';
										$sHtml .= '<small>' . RGPD_WINDOW_MODAL_SUBTITLE . '</small>';
									$sHtml .= '</div>';
									$sHtml .= '<div class="ccEditor">';
										// Parseamos los shortcodes del texto (igual que information.php / product_info.php); en el footer global no estan cargados
											if( !function_exists('do_shortcode') ) include( DIR_WS_INCLUDES . 'functions/shortcodes.php' );
											if( !function_exists('list_backgr') ) include( DIR_THEME_ROOT . 'functions/shortcodes.php' );
											$sHtml .= function_exists('do_shortcode') ? do_shortcode( $this->aTermGeneral['text'] ) : $this->aTermGeneral['text'];
									$sHtml .= '</div>';
								$sHtml .= '</div>';
								$sHtml .= '<div class="rgpd-btn">';
									$sHtml .= '<div id="rgpd-accp">' . RGPD_WINDOW_MODAL_ACCEPT . '</div>';
								$sHtml .= '</div>';
							$sHtml .= '</div>';
						$sHtml .= '</div>';

						echo $sHtml;
						echo '<a href="#rgpd-wndw" data-modal="true" class="mgp-inln mgp-auto" style="display: none;"></a>';
					}
				}
			}
		}

		/**
		* Muestra los terminimos de condiciones generales en el information
		*/
		public function showInformationTermsGeneral()
		{
			// Variables
			global $information, $info_id, $languages_id;

			// Comprobamos si tenemos que modificar la información por las condiciones generales
			if( $info_id == RGPD_TERMS_INFO_ID )
			{
				// Obtenemos
				$aRow = pharaonix_queryOne( 'select tpd.text, tpd.title
											 from rgpd_term_privacy_general_description tpd
											 inner join rgpd_term_privacy_general tp on (tp.id_term_pivacy_general = tpd.id_term_pivacy_general)
											 where tpd.language_id = "' . (int)$languages_id . '" ORDER BY tp.id_term_pivacy_general DESC limit 1' );
				// Comprobamos si existe
				if( $aRow->num_rows > 0 )
				{
					$information['information_title'] = $aRow->records['title'];
					$information['information_description'] = $aRow->records['text'];
				}
			}
		}

		/**
		* Elimina los avisos de reposición del cliente
		*/
		public function notifyStockExecute($customer_id)
		{
			$aCustomer = pharaonix_queryOne( 'select LCASE( customers_email_address ) from customers where customers_id = "' . (int)$customer_id . '"' );

			// Eliminamos notificaciones
			tep_db_query( 'delete from products_notifications where LCASE( customers_email_address ) = "' . $aCustomer->records['customers_email_address'] . '"');
		}

		/**
		* Elimina, anonimiza o mantiene los comentarios del cliente
		*/
		public function commentsExecute($customer_id)
		{
			switch( RGPD_ACCOUNT_DELETE_ACTION_COMMENTS )
			{
				case 'mantener':break;

				case 'anonimizar':
					// Obtenemos un comentario para obtener su nombre
					$sName = preg_replace( '/ .+$/i', '', (string)(pharaonix_queryOne( 'select customers_name from reviews where customers_id = "' . (int)$customer_id . '"')->records['customers_name'] ?? '') );

					// Comprobamos si queremos que salga "anonimo"
					if( RGPD_ACCOUNT_DELETE_ACTION_COMMENTS_OPINIONS == 'anonimo' )
						$sName = 'Anónimo';

					// Actualizamos todos los comentarios con su nombre solo para animizarlo
					pharaonix_query( 'update reviews set customers_name = "' . $sName . '" where customers_id = "' . (int)$customer_id . '"' );
				break;

				case 'eliminar':
					// Eliminamos comentarios
					$aDatos = tep_db_query( 'select reviews_id from ' . TABLE_REVIEWS . ' where customers_id = "' . (int)$customer_id . '"' );

					while( $aDato = tep_db_fetch_array( $aDatos ) )
						tep_db_query( 'delete from ' . TABLE_REVIEWS_DESCRIPTION . ' where reviews_id = "' . $reviews['reviews_id'] . '"' );

					tep_db_query( 'delete from ' . TABLE_REVIEWS . ' where customers_id = "' . (int)$customer_id . '"');
				break;
			}
		}

		/**
		* Elimina, anonimiza o mantiene las opiniones del cliente
		*/
		public function opinionsExecute($customer_id)
		{
			// Opiniones
			switch( RGPD_ACCOUNT_DELETE_ACTION_OPINIONS )
			{
				case 'mantener':break;
				case 'anonimizar':break;

				case 'eliminar':
					// Eliminamos opiniones
					tep_db_query( 'delete from opinion where customers_id = "' . (int)$customer_id . '"');
				break;
			}
		}

		/**
		* Anonimiza las direcciones de un cliente
		*/
		public function adddresBookExecute($customer_id)
		{
			tep_db_query( 'update address_book
							set entry_company = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							entry_NIF = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							entry_firstname = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							entry_lastname = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							entry_street_address = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							entry_suburb = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							entry_postcode  = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '"
							where customers_id = "' . (int)$customer_id . '"' );
		}

		/**
		* Elimina, anonimiza o mantiene los pedidos del cliente
		*/
		public function ordersExecute($customer_id)
		{
			// Obtenemos los pedidos pasado X años
			$aRows = tep_db_query( 'select o.orders_id, DATE_FORMAT( f.facturas_fecha, "%Y" ) as factura_ano
									from orders o
									left outer join facturas f on (f.facturas_pedido_id = o.orders_id)
									where DATE_FORMAT( o.date_purchased, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), - ' . (365 * (int)RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER) . ')
									and o.customers_id = "' . (int)$customer_id . '"' );

			// Si contenemos datos
			if( tep_db_num_rows( $aRows ) > 0 )
			{
				// Variables
				$aIds = array();
				$sYear = date( 'Y', strtotime( '-' . (365 * (int)RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER) . ' days' ) );

				// Recorremos en busca de las id
				while( $aRow = tep_db_fetch_array( $aRows ) )
				{
					if( $aRow['factura_ano'] != '' && $aRow['factura_ano'] >= $sYear )
						continue;

					$aIds[] = $aRow['orders_id'];
				}

				if (empty($aIds)) {
					return;
				}

				// Pedidos
				switch( RGPD_ACCOUNT_DELETE_ACTION_ORDER )
				{
					case 'mantener':break;

					case 'anonimizar':
						// Variables
						$sSql = '';

						// Obtenemos los campos a anonimizar
						$aOrdersRowsCheck = explode( ',', RGPD_ACCOUNT_DELETE_ORDER_ROWS );

						// Recorremos para crear SQL
						foreach( $aOrdersRowsCheck as $value )
							$sSql .= $value . ' = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",';

						// Actualizamos orders
						pharaonix_query( 'update orders set ' . substr( $sSql, 0, -1 ) . ' where orders_id IN (' . implode( ',', $aIds ) . ')' );

						// Obtenemos los pedidos backup del editor de pedidos si existe
						if( pharaonix_checkTableExists( 'orders_edit_backup' ) )
						{
							$aRows = tep_db_query( 'select orders_backup_id, content from orders_edit_backup where orders_id IN (' . implode( ',', $aIds ) . ')' );

							while( $aRow = tep_db_fetch_array( $aRows ) )
							{
								$aRow['content'] = json_decode( $aRow['content'], true );

								// Recorremos los campos que queremos anonimizar
								foreach( $aOrdersRowsCheck as $sRow )
									$aRow['content'][$sRow] = RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR;

								// Actualizamos
								tep_db_perform( 'orders_edit_backup', array( 'content' => json_encode( $aRow['content'] ) ), 'update', 'orders_backup_id = "' . (int)$aRow['orders_backup_id'] . '"' );
							}
						}
					break;

					case 'eliminar':
						tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id IN (' . implode( ',', $aIds ) . ')');
						tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id IN (' . implode( ',', $aIds ) . ')');
						tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id IN (' . implode( ',', $aIds ) . ')');
						tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id IN (' . implode( ',', $aIds ) . ')');
						tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id IN (' . implode( ',', $aIds ) . ')');
						tep_db_query('delete from ' . TABLE_CUSTOMERS_POINTS_PENDING . ' where orders_id IN (' . implode( ',', $aIds ) . ')');
					break;
				}
			}
		}

		/**
		* Anonimiza los datos del cliente
		*/
		public function accountExecute($customer_id)
		{
			tep_db_query( 'update customers
							set customers_firstname = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							customers_lastname = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							customers_dob = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							customers_telephone = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							customers_fax = "' . RGPD_ACCOUNT_DELETE_ORDER_TEXT_ANONIMIZAR . '",
							customers_newsletter = 0
							where customers_id = "' . (int)$customer_id . '"' );
		}

		/**
		* Ejecuta la accion de la seccion desactivar mi cuenta
		*/
		public function accountDisableExecute($bPassword = true)
		{
			// Variables
			global $customer_id, $messageStack;
			$sPassword = tep_db_prepare_input( $_POST['password'] );
			$aBackups = array();

			// Comprobamos si tenemos que mirar la contraseña
			if( $bPassword && RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD == 'true' )
			{
				$sPasswordDb = pharaonix_queryOne( 'select customers_password from customers where customers_id = "' . (int)$customer_id . '"')->records['customers_password'];

				// Comprobamos si es correcto
				if( ! tep_validate_password( $sPassword, $sPasswordDb ) )
				{
					$messageStack->addSession( 'account_delete', ACCOUNT_ERROR_PASSWORD, 'error', true );
					tep_redirect( tep_href_link( 'account/account_disable.php', '', 'SSL' ) );
				}
			}

			// Obtenemos comentarios
			$aRows = tep_db_query( 'select reviews_id, customers_name from reviews where customers_id = "' . (int)$customer_id . '"' );
			$aBackups['reviews'] =  array( 'key' => 'reviews_id', 'rows' => array() );

			while( $aRow = tep_db_fetch_array( $aRows ) )
			{
				$sId = $aRow['reviews_id'];
				unset( $aRow['reviews_id'] );
				$aBackups['reviews']['rows'][] = array( 'id' => $sId, 'row' => $aRow );
			}

			// Obtenemos los pedidos pasado X años
			$aRows = tep_db_query( 'select orders_id
									from orders
									where DATE_FORMAT( date_purchased, "%Y-%m-%d" ) <= adddate( DATE_FORMAT( NOW(), "%Y-%m-%d" ), - ' . (365 * (int)RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER) . ')
									and customers_id = "' . (int)$customer_id . '"' );

			// Si contenemos datos
			if( tep_db_num_rows( $aRows ) > 0 )
			{
				// Variables
				$aIds = array();

				// Recorremos en busca de las id
				while( $aRow = tep_db_fetch_array( $aRows ) )
					$aIds[] = $aRow['orders_id'];

				// Obtenemos
				$aRows = tep_db_query( 'select orders_id, ' . RGPD_ACCOUNT_DELETE_ORDER_ROWS . ' from orders where orders_id IN (' . implode( ',', $aIds ) . ')' );
				$aBackups['orders'] =  array( 'key' => 'orders_id', 'rows' => array() );

				// Recorremos, nos quedamos con el id y los campos a guardar para backup
				while( $aRow = tep_db_fetch_array( $aRows ) )
				{
					$sId = $aRow['orders_id'];
					unset( $aRow['orders_id'] );
					$aBackups['orders']['rows'][] = array( 'id' => $sId, 'row' => $aRow );
				}

				// Obtenemos los pedidos backup del editor de pedidos si existe
				if( pharaonix_checkTableExists( 'orders_edit_backup' ) )
				{
					$aRows = tep_db_query( 'select orders_backup_id, content from orders_edit_backup where orders_id IN (' . implode( ',', $aIds ) . ')' );
					$aBackups['orders_edit_backup'] =  array( 'key' => 'orders_backup_id', 'rows' => array( ) );

					while( $aRow = tep_db_fetch_array( $aRows ) )
					{
						$sId = $aRow['orders_backup_id'];
						unset( $aRow['orders_backup_id'] );
						$aBackups['orders_edit_backup']['rows'][] = array( 'id' => $sId, 'row' => $aRow );
					}
				}
			}

			// Obtenemos datos el cliente
			$aRows = tep_db_query( 'select customers_firstname, customers_email_address, customers_lastname, customers_dob, customers_telephone, customers_fax from customers where customers_id = "' . (int)$customer_id . '"' );
			$aBackups['customers'] =  array( 'key' => 'customers_id', 'rows' => array() );

			while( $aRow = tep_db_fetch_array( $aRows ) )
				$aBackups['customers']['rows'][] = array( 'id' => $customer_id, 'row' => $aRow );

			// Obtenemos direcciones del cliente
			$aRows = tep_db_query( 'select address_book_id, entry_company, entry_NIF, entry_firstname, entry_lastname, entry_street_address, entry_suburb, entry_postcode from address_book where customers_id = "' . (int)$customer_id . '"' );
			$aBackups['address_book'] =  array( 'key' => 'address_book_id', 'rows' => array() );

			while( $aRow = tep_db_fetch_array( $aRows ) )
			{
				$sId = $aRow['address_book_id'];
				unset( $aRow['address_book_id'] );
				$aBackups['address_book']['rows'][] = array( 'id' => $sId, 'row' => $aRow );
			}

			// Obtenemos los newsletter del cliente
			$aRows = tep_db_query( 'select customers_id, id_term_pivacy_trade from rgpd_account_term where customers_id = "' . (int)$customer_id . '"' );
			$aBackups['rgpd_account_term'] =  array( 'key' => 'id_account_term', 'rows' => array() );

			while( $aRow = tep_db_fetch_array( $aRows ) )
				$aBackups['rgpd_account_term']['rows'][] = array( 'row' => $aRow );

			// Eliminamos
			tep_db_query( 'delete from rgpd_account_term where customers_id = "' . (int)$customer_id . '"' );

			// Guardamos los backups
			tep_db_perform( 'rgpd_backup', array( 'customers_id' => $customer_id, 'backup' => json_encode( $aBackups ) ) );

			// Direcciones
			$this->adddresBookExecute( $customer_id );

			// Account
			$this->accountExecute( $customer_id );

			// Comentarios
			$this->commentsExecute( $customer_id );

			// Notifaciones stock
			$this->notifyStockExecute( $customer_id );

			// Opiniones
			$this->opinionsExecute( $customer_id );

			// Pedidos
			$this->ordersExecute( $customer_id );

			// Metemos el log
			tep_db_perform( 'rgpd_log_account', array(
				'customers_id' => $customer_id,
				'name' => $aBackups['customers']['rows'][0]['row']['customers_firstname'] . ' ' . $aBackups['customers']['rows'][0]['row']['customers_lastname'],
				'disable' => 1,
				'email' => $aBackups['customers']['rows'][0]['row']['customers_email_address'],
				'ip' => tools::getIP(),
				'date' => date( 'Y-m-d H:i:s' )
			) );

			// Ponemos el cliente disabled
			tep_db_query( 'update customers set status_disabled = "1" where customers_id = "' . $customer_id . '"' );

			// Email
			$mail = new util\mail();

			$sHtmlEmail = str_replace( array(
				'{USERNAME}',
				'{DATE}'
			), array(
				$aBackups['customers']['rows'][0]['row']['customers_firstname'] . ' ' . $aBackups['customers']['rows'][0]['row']['customers_lastname'],
				date( 'd-m-Y H:i:s' )
			), RGPD_EMAIL_DISABLE );

			// Html del email
			$mail->includeEmail( 'various.php', array(
				'content' => $sHtmlEmail
			) );

			// Enviamos
			tep_mail( $aBackups['customers']['rows'][0]['row']['customers_firstname'], $aBackups['customers']['rows'][0]['row']['customers_email_address'], RGPD_EMAIL_DISABLE_SUBJECT, preg_replace("/[\r\n\t]+/", "", $mail->html), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
		}

		/**
		* Ejecuta la accion de la seccion eliminar mi cuenta
		*/
		public function accountDeleteExecute($bPassword = true)
		{
			// Variables
			global $customer_id, $messageStack;
			$sPassword = tep_db_prepare_input( $_POST['password'] );

			// Comprobamos si tenemos que mirar la contraseña
			if( $bPassword && RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD == 'true' )
			{
				$sPasswordDb = pharaonix_queryOne( 'select customers_password from customers where customers_id = "' . (int)$customer_id . '"')->records['customers_password'];

				// Comprobamos si es correcto
				if( ! tep_validate_password( $sPassword, $sPasswordDb ) )
				{
					$messageStack->addSession( 'account_delete', ACCOUNT_ERROR_PASSWORD, 'error', true );
					tep_redirect( tep_href_link( FILENAME_ACCOUNT_DELETE, '', 'SSL' ) );
				}
			}

			// Comentarios
			$this->commentsExecute( $customer_id );

			// Notifaciones stock
			$this->notifyStockExecute( $customer_id );

			// Opiniones
			$this->opinionsExecute( $customer_id );

			// Pedidos
			$this->ordersExecute( $customer_id );

			// Eliminamo los logs de cliente
			tep_db_query( 'delete from rgpd_log_account where customers_id = "' . $customer_id . '"' );
			tep_db_query( 'delete from rgpd_log_term_privacy where customers_id = "' . $customer_id . '"' );

			// Eliminamos los terminos
			tep_db_query( 'delete from rgpd_account_term where customers_id = "' . $customer_id . '"' );
		}

		/**
		* Restaura los datos del cliente
		*/
		public function restoreCustomer($customer_id)
		{
			// Obtenemos el backup
			$aBackup = pharaonix_queryOne('select backup from rgpd_backup where customers_id = "' . $customer_id . '"')->records['backup'];

			// Si contenemos datos para restaurar
			if (is_array($aBackup) && count($aBackup) > 0) {
				{
					// Obtenemos
					$aJson = json_decode($aBackup, true);

					// Recorremos tablas
					foreach ($aJson as $sTable => $aData) {
						// Recorremos
						foreach ($aData['rows'] as $aRows) {
							// Insertamos
							if (in_array($sTable, array('rgpd_account_term'))) {
								tep_db_perform($sTable, $aRows['row']);
								continue;
							}

							// Actualizamos
							tep_db_perform($sTable, $aRows['row'], 'update', $aData['key'] . ' = "' . $aRows['id'] . '"');
						}
					}

					// Eliminamos backcup
					tep_db_query('delete from rgpd_backup where customers_id = "' . $customer_id . '"');
				}

				// Obtenemos el nombre del cliente y email
				$aCustomer = pharaonix_queryOne('select concat( customers_firstname, " ", customers_lastname ) as name, customers_email_address from customers where customers_id = "' . $customer_id . '"');

				// Metemos el log
				tep_db_perform('rgpd_log_account', array(
					'customers_id' => $customer_id,
					'name' => $aCustomer->records['name'],
					'disable' => 0,
					'email' => $aCustomer->records['customers_email_address'],
					'ip' => tools::getIP(),
					'date' => date('Y-m-d H:i:s')
				));

				// Ponemos el cliente activo
				tep_db_query('update customers set status_disabled = "0" where customers_id = "' . $customer_id . '"');

				// Reseteamos
				global $check_customer_query, $check_customer;
				$check_customer_query = tep_db_query("select customers_id, status_disabled, customers_firstname, customers_lastname, member_level, proveedor, customers_group_id, customers_password, customers_email_address, customers_default_address_id, customers_specific_taxes_exempt, member_level from " . TABLE_CUSTOMERS . " where customers_id = '" . $customer_id . "'");
				$check_customer = tep_db_fetch_array($check_customer_query);

				// Email
				$mail = new util\mail();

				$sHtmlEmail = str_replace(array(
					'{USERNAME}',
					'{DATE}'
				), array(
					$check_customer['customers_firstname'] . ' ' . $check_customer['customers_lastname'],
					date('d-m-Y H:i:s')
				), RGPD_EMAIL_ACTIVE);

				// Html del email
				$mail->includeEmail('various.php', array(
					'content' => $sHtmlEmail
				));

				// Enviamos
				tep_mail( $check_customer['customers_firstname'], $check_customer['customers_email_address'], RGPD_EMAIL_ACTIVE_SUBJECT, preg_replace("/[\r\n\t]+/", "", $mail->html), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS );
			}
		}

		/**
		* Cuando un cliente en el login se encuentra desactivado mostramos
		*/
		public function loginExecute()
		{
			global $aProducto, $aProductos, $order, $aListas;
			extract( $GLOBALS );

			// Obtenemos la fecha
			$sDate = pharaonix_queryOne( 'select date_format(date,"%d/%m/%Y %H:%i") as date from rgpd_log_account where customers_id = "' . $check_customer['customers_id'] . '" order by id desc limit 1' )->records['date'];

			require(DIR_THEME. 'html/header.php');
			require(DIR_THEME. 'html/column_left.php');

			echo '<h1 class="pageHeading">' . HEADING_TITLE . '</h1>';

			echo '<form class="row ax" method="post">';
				echo '<div class="column a12">';
					echo '<h4>' . RGPD_ACCOUNT_DISABLE_TITLE . '</h4>';
					echo $messageStack->show( array( 'class' => 'wrng', 'text' => str_replace( '{DATE}', $sDate, RGPD_ACCOUNT_DISABLE_TEXT ) ) );

					echo '<input type="hidden" name="email_address" value="' . $_POST['email_address'] . '"/>';
					echo '<input type="hidden" name="skip_disabled" value="true"/>';
					echo '<input type="hidden" name="password" value="' . $_POST['password'] . '"/>';

					echo '<div class="botonera">';
						echo tep_image_submit('button_login.gif', IMAGE_BUTTON_CONTINUE);
					echo '</div>';
				echo '</div>';
			echo '</form>';

			require(DIR_THEME. 'html/column_right.php');
			require(DIR_THEME. 'html/footer.php');
			require(DIR_WS_INCLUDES . 'application_bottom.php');

			exit();
		}


		/**
		* Muestra el texto de información en al seccion eliminar/desactivar mi cuenta
		*/
		public function accountDeleteShowText($bDelete = true)
		{
			// Variables
			global $languages_id, $messageStack;
			$sHtmlForm = '';

			// Obtenemos el texto
			$sText = htmlspecialchars_decode( json_decode( str_replace( array( '\"' ), array('"'), ($bDelete ? RGPD_ACCOUNT_DELETE_TEXT_DELETE : RGPD_ACCOUNT_DELETE_TEXT_DISABLE) ), true )[$languages_id] );

			// Formulario recordar contraseña
			if( RGPD_ACCOUNT_DELETE_CONFIRM_PASSWORD == 'true' )
			{
				$sHtmlForm = '<form method="post" action="' . tep_href_link( ($bDelete ? FILENAME_ACCOUNT_DELETE : 'account/account_disable.php'), 'delete=true', 'SSL' ) . '" class="row dx tx xform atop ccquestion">';
					$sHtmlForm .= '<div class="column a06">';
						$sHtmlForm .= '<input type="password" placeholder="' . str_replace( ':', '', ENTRY_PASSWORD_CONFIRMATION ) . '" name="password" value=""/>';
					$sHtmlForm .= '</div>';
					$sHtmlForm .= '<div class="column a06 tright">';
						$sHtmlForm .= '<a style="width: 49%;" href="' . FILENAME_ACCOUNT . '" class="button small ccbutton tblanco">' . IMAGE_BUTTON_BACK . '</a><span style="width: 2%; display: inline-block;"></span>';
						$sHtmlForm .= '<input style="width: 49%;" class="button small rojo ccbutton" type="submit" value="' . ($bDelete ? IMAGE_BUTTON_DELETE : BUTTON_DISABLE) . '">';
					$sHtmlForm .= '</div>';
				$sHtmlForm .= '</form>';
			}
			else
			{
				$sHtmlForm .= '<div class="tright" style="margin: 20px 0px;">';
					$sHtmlForm .= '<a href="' . tep_href_link( FILENAME_ACCOUNT, '', 'SSL' ) . '" class="button ccbutton small"><i class="fa fa-arrow-left"></i> ' . IMAGE_BUTTON_BACK . '</a> ';
					$sHtmlForm .= '<a href="' . tep_href_link( ($bDelete ? FILENAME_ACCOUNT_DELETE : 'account/account_disable.php'), 'delete=true', 'SSL' ) . '" class="button ccbutton small rojo"><i class="fa fa-trash"></i> ' . ($bDelete ? IMAGE_BUTTON_DELETE : BUTTON_DISABLE) . '</a>';
				$sHtmlForm .= '</div>';
			}

			// Remplazamos
			$sText = str_replace( array( '{TAX_TIME_DATA_ORDER}', '{CONFIRM_PASSWORD}' ), array( RGPD_ACCOUNT_DELETE_TAX_TIME_DATA_ORDER, $sHtmlForm ), $sText );

			// Retornamos
			return '<div class="ccEditor">' . $messageStack->show( 'account_delete' ) . $sText . '</div>';
		}
	}
?>