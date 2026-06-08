<?php
use util\tools as tools;

// Librerias
include( 'includes/application_top.php' );
include( 'includes/functions/ajax.php' );

// Variables
$action = ($_GET['action'] ?? '');
$error = false;
$processed = false;

// Orden para la lista de clientes
$orderby = isset($_GET['orderby']) ? tep_db_prepare_input($_GET['orderby']) : 'date_created';
$sort = isset($_GET['sort']) ? tep_db_prepare_input($_GET['sort']) : 'DESC';
$default_zone = ''; // Asigna un valor predeterminado vacío

// Acciones
if( tep_not_null($action) )
{
	switch ($action)
	{
		case 'massAction':
			$mass_action = tep_db_prepare_input($_POST['mass_action']);
			$ids = isset($_POST['selected']) ? array_map('intval', $_POST['selected']) : [];

			if ($mass_action == 'delete' && !empty($ids)) {
				// Procesar en bloques de 1000
				$chunks = array_chunk($ids, 1000);

				tep_db_query("START TRANSACTION");

				foreach ($chunks as $chunk) {
					$ids_list = implode(',', $chunk);

					// Borrados por lotes
					tep_db_query("DELETE FROM " . TABLE_ADDRESS_BOOK . " WHERE customers_id IN ($ids_list)");
					tep_db_query("DELETE FROM " . TABLE_CUSTOMERS . " WHERE customers_id IN ($ids_list)");
					tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_INFO . " WHERE customers_info_id IN ($ids_list)");
					tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE customers_id IN ($ids_list)");
					tep_db_query("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE customers_id IN ($ids_list)");
					tep_db_query("DELETE FROM " . TABLE_WHOS_ONLINE . " WHERE customer_id IN ($ids_list)");

					// Tablas RGPD
					tep_db_query("DELETE FROM rgpd_log_account WHERE customers_id IN ($ids_list)");
					tep_db_query("DELETE FROM rgpd_log_term_privacy WHERE customers_id IN ($ids_list)");
					tep_db_query("DELETE FROM rgpd_account_term WHERE customers_id IN ($ids_list)");
				}

				tep_db_query("COMMIT");

				$messageStack->addSession('mensaje', 'Clientes eliminados correctamente', 'success');
			}

			tep_redirect(tep_href_link(FILENAME_CUSTOMERS));
			break;

		case 'delete_customers_note':
			// Variables
			$sGetId = tep_db_prepare_input( $_GET['id'] );
			$sGetCustomersId = tep_db_prepare_input( $_GET['customers_id'] );

			// Obtenemos la ultima nota del cliente
			$aDatos = tep_db_query( 'select id_customers_notes from customers_notes where customers_id = ' . $sGetCustomersId . ' order by customers_notes_date desc limit 1' );
			$aDato = tep_db_fetch_array( $aDatos );

			// Eliminamos la nota al cliente
			tep_db_query( 'delete from customers_notes where id_customers_notes = ' . $sGetId );

			// Si el id de la nota a borar coincide con la ultima obtenemos otra vez la ultima despues de haber borrado para asignarme el estado
			if( $aDato['id_customers_notes'] == $sGetId )
			{
				$sEstadoNuevo = 'NULL';
				$aDatos = tep_db_query( 'select id_customers_notes_status from customers_notes where customers_id = ' . $sGetCustomersId . ' order by customers_notes_date desc limit 1' );

				// Si contenemos estado
				if( tep_db_num_rows( $aDatos ) > 0 )
				{
					$aDato = tep_db_fetch_array( $aDatos );
					$sEstadoNuevo = $aDato['id_customers_notes_status'];
				}

				// Actualizamos el cliente con su nuevo estado
				tep_db_query( 'update customers set id_customers_notes_status = ' . $sEstadoNuevo . ' where customers_id = ' . $sGetCustomersId );
			}
			exit();

		case 'add_customers_note':
			// Variables
			$sPostCustomersNotes = tep_db_prepare_input( $_POST['customers_notes'] );
			$sPostCustomersNotesStatus = tep_db_prepare_input( $_POST['id_customers_notes_status'] );
			$sPostCustomersId = tep_db_prepare_input( $_POST['customers_id'] );

			$aSql = [
				'customers_notes' => nl2br( $sPostCustomersNotes ),
				'id_customers_notes_status' => $sPostCustomersNotesStatus,
				'customers_id' => $sPostCustomersId
			];

			// Insertamos
			tep_db_perform( 'customers_notes', $aSql );

			// Obtenemos el id insertado
			$sId = tep_db_insert_id();

			// Actualizamos el cliente con su nuevo estado
			tep_db_query( 'update customers set id_customers_notes_status = ' . $sPostCustomersNotesStatus . ' where customers_id = ' . $sPostCustomersId );

			// Obtenemos el registro
			$aDato = tep_db_query( 'select cn.id_customers_notes, cn.customers_notes, cns.customers_notes_status, cns.customers_notes_color, cn.customers_notes_date
									from customers_notes cn
									inner join customers_notes_status cns on(cn.id_customers_notes_status = cns.id_customers_notes_status)
									where id_customers_notes = "' . (int)$sId . '"' );
			$aDato = tep_db_fetch_array( $aDato );

			// Pintamos la nota
			echo showListCustomersNotes( $aDato['customers_notes_status'], $aDato['customers_notes'], $aDato['customers_notes_date'], $aDato['customers_notes_color'], $aDato['id_customers_notes'], $sPostCustomersId );

			exit();

		case 'ajax_get_zones_html':
				ajax_get_zones_html( tep_db_prepare_input( $_GET['country'] ), true );
				exit();
			break;
			case 'ajax_get_cities_html':
				ajax_get_cities_html( (int)$_GET['country'], tep_db_prepare_input( $_GET['zone'] ) , tep_db_prepare_input( $_GET['cp'] ) );
				exit();
			break;

			case 'confirmaddressdelete':
				if( !isset($_GET['cID']) || !isset($_GET['add_id']))
					exit();

				$check_default_query = tep_db_query("select customers_default_address_id as defid from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$_GET['cID'] . "'");

				if( $default = tep_db_fetch_array($check_default_query) )
				{
					// Si es la dirección por defecto no eliminamos
					if( $_GET['add_id'] == $default['defid'] )
						exit();
					else
					{
						tep_db_query("delete from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$_GET['cID'] . "' and address_book_id = '" . (int)$_GET['add_id'] . "'");
						exit();
					}
				}

				exit();
			break;

			case 'update':
				// Variables
				$customers_id = tep_db_prepare_input($_GET['cID']);

				// Variables datos personales
				$customers_firstname = tep_db_prepare_input($_POST['customers_firstname']);
				$customers_lastname = tep_db_prepare_input($_POST['customers_lastname']);
				$entry_nif = tep_db_prepare_input($_POST['entry_NIF']);
				$customers_email_address = tep_db_prepare_input($_POST['customers_email_address']);
				$customers_telephone = tep_db_prepare_input($_POST['customers_telephone']);
				$customers_fax = tep_db_prepare_input($_POST['customers_fax']);
				$customers_gender = tep_db_prepare_input($_POST['customers_gender']);
				$customers_dob = tep_db_prepare_input($_POST['customers_dob']);
				$cuenta_cliente = intval($_POST['cuenta_cliente']);
				$customers_pyro_courier = intval($_POST['customers_pyro_courier']);

				// Variables direcciones
				$entry_firstname = tep_db_prepare_input($_POST['entry_firstname']);
				$entry_lastname = tep_db_prepare_input($_POST['entry_lastname']);
				$entry_company = tep_db_prepare_input($_POST['entry_company']);
				$entry_company_tax_id = tep_db_prepare_input($_POST['entry_company_tax_id']);
				$entry_street_address = tep_db_prepare_input($_POST['entry_street_address']);
				$entry_country_id = tep_db_prepare_input($_POST['entry_country_id']);
				$entry_zone_id = tep_db_prepare_input($_POST['entry_zone_id']);
				$entry_city = tep_db_prepare_input($_POST['entry_city']);
				$entry_city_id = tep_db_prepare_input($_POST['entry_city_id']);
				$default_address_id = (isset($_GET['add_id']) ? tep_db_prepare_input($_GET['add_id']) : tep_db_prepare_input($_POST['default_address_id']));
				$entry_suburb = tep_db_prepare_input($_POST['entry_suburb']);
				$entry_postcode = tep_db_prepare_input($_POST['entry_postcode']);
				$entry_state = tep_db_prepare_input($_POST['entry_state']);

				// Variables de opciones
				$customers_newsletter = tep_db_prepare_input($_POST['customers_newsletter']);
				$customers_group_id = tep_db_prepare_input($_POST['customers_group_id']);
				$id_customers_type = tep_db_prepare_input($_POST['id_customers_type']);
				$recargo_equivalencia = tep_db_prepare_input($_POST['recargo_equivalencia']);
				$status_disabled = tep_db_prepare_input($_POST['status_disabled']);
				$customers_group_ra = tep_db_prepare_input($_POST['customers_group_ra']);
				$especial = tep_db_prepare_input($_POST['especial']);
				$especial_razon = tep_db_prepare_input($_POST['especial_razon']);

				// Modulo pago
				if( $_POST['customers_payment_allowed'] && $_POST['customers_payment_settings'] == '1' )
					$customers_payment_allowed = tep_db_prepare_input($_POST['customers_payment_allowed']);
				else
				{
					$customers_payment_allowed = '';
					if ($_POST['payment_allowed'] && $_POST['customers_payment_settings'] == '1')
					{
						foreach ($_POST['payment_allowed'] as $val)
						{
							if ($val == true)
								$customers_payment_allowed .= tep_db_prepare_input($val).';';
						}

						$customers_payment_allowed = substr($customers_payment_allowed,0,strlen($customers_payment_allowed)-1);
					}
				}

				// Modulo envio
				if( $_POST['customers_shipment_allowed'] && $_POST['customers_shipment_settings'] == '1' )
					$customers_shipment_allowed = tep_db_prepare_input($_POST['customers_shipment_allowed']);
				else
				{
					$customers_shipment_allowed = '';

					if( $_POST['shipping_allowed'] && $_POST['customers_shipment_settings'] == '1' )
					{
						foreach($_POST['shipping_allowed'] as $val)
						{
							if( $val == true )
								$customers_shipment_allowed .= tep_db_prepare_input($val).';';

						}

						$customers_shipment_allowed = substr($customers_shipment_allowed,0,strlen($customers_shipment_allowed)-1);
					}
				}

				// Modulo total
				if( $_POST['customers_order_total_allowed'] && $_POST['customers_order_total_settings'] == '1' )
					$customers_order_total_allowed = tep_db_prepare_input($_POST['customers_order_total_allowed']);
				else
				{
					$customers_order_total_allowed = '';
					if( $_POST['order_total_allowed'] && $_POST['customers_order_total_settings'] == '1' )
					{
						foreach ($_POST['order_total_allowed'] as $val)
						{
							if( $val == true )
								$customers_order_total_allowed .= tep_db_prepare_input($val).';';
						}

						$customers_order_total_allowed = substr($customers_order_total_allowed,0,strlen($customers_order_total_allowed)-1);
					}
				}

				// Modulo taxes
				if( $_POST['customers_specific_taxes_exempt'] && $_POST['customers_tax_rate_exempt_settings'] == '1' )
					$customers_specific_taxes_exempt = tep_db_prepare_input($_POST['customers_specific_taxes_exempt']);
				else
				{
					$customers_specific_taxes_exempt = '';

					if( $_POST['customers_tax_rate_exempt_id'] && $_POST['customers_tax_rate_exempt_settings'] == '1' )
					{
						foreach($_POST['customers_tax_rate_exempt_id'] as $val)
						{
							if (tep_not_null($val))
								$customers_specific_taxes_exempt .= tep_db_prepare_input($val).',';
						}

						$customers_specific_taxes_exempt = substr($customers_specific_taxes_exempt,0,strlen($customers_specific_taxes_exempt)-1);
					}
				}

				// Comprobamos nombre
				if( strlen( $customers_firstname ) < ENTRY_FIRST_NAME_MIN_LENGTH )
					$messageStack->addSession( 'mensaje', 'El nombre del cliente no puede tener menos de ' . ENTRY_FIRST_NAME_MIN_LENGTH . ' caracteres.' );

				// Comprobamos el apellido
				if( strlen( $customers_lastname ) < ENTRY_LAST_NAME_MIN_LENGTH )
					$messageStack->addSession( 'mensaje', 'El apellido del cliente no puede tener menos de ' . ENTRY_LAST_NAME_MIN_LENGTH . ' caracteres.' );

				// Comprobamos el email
				if( strlen( $customers_email_address ) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH )
					$messageStack->addSession( 'mensaje', 'El email del cliente no puede tener menos de ' . ENTRY_EMAIL_ADDRESS_MIN_LENGTH . ' caracteres.' );

				// Comprobamos si el email es valido
				if( !tep_validate_email($customers_email_address) )
					$messageStack->addSession( 'mensaje', 'El email ' . $customers_email_address . ' no es un email valido.' );

				// Comprobamos si el email ya existe
				$aDato = tep_db_query( "select customers_id, customers_email_address from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($customers_email_address) . "' and customers_id != '" . (int)$customers_id . "'" );

				if( tep_db_num_rows( $aDato ) > 0 )
				{
					$aDato = tep_db_fetch_array( $aDato );
					$messageStack->addSession( 'mensaje', 'El email ' . $customers_email_address . ' ya esta en uso por el cliente #' . $aDato['customers_id'] . '.' );
				}

				// Comprobamos el telefono
				if( strlen($customers_telephone) < ENTRY_TELEPHONE_MIN_LENGTH )
					$messageStack->addSession( 'mensaje', 'El telefono del cliente no puede tener menos de ' . ENTRY_TELEPHONE_MIN_LENGTH . ' caracteres.' );

				// Inicio, direcciones
				// Comprobamos, recorremos uno de los campos por ejemplo el nombre y asi podemos posicionarnos en cada campo sin tener que recorrer todos
				foreach( $entry_firstname as $key => $value )
				{
					// Comprobamos el nombre
					if( strlen( $entry_firstname[$key] ) < ENTRY_FIRST_NAME_MIN_LENGTH )
						$messageStack->addSession( 'mensaje', 'El nombre del cliente en la dirección #' . $key . ' no puede tener menos de ' . ENTRY_FIRST_NAME_MIN_LENGTH . ' caracteres.' );

					// Comprobamos el apellido
					if( strlen( $entry_lastname[$key] ) < ENTRY_LAST_NAME_MIN_LENGTH )
						$messageStack->addSession( 'mensaje', 'El apellido del cliente en la dirección #' . $key . ' no puede tener menos de ' . ENTRY_LAST_NAME_MIN_LENGTH . ' caracteres.' );

					// Comprobamos la direccion
					if( strlen( $entry_street_address[$key] ) < ENTRY_STREET_ADDRESS_MIN_LENGTH )
						$messageStack->addSession( 'mensaje', 'La dirección del cliente en la dirección #' . $key . ' no puede tener menos de ' . ENTRY_STREET_ADDRESS_MIN_LENGTH . ' caracteres.' );

					// Comprobamos el codigo postal
					if( strlen( $entry_postcode[$key] ) < ENTRY_POSTCODE_MIN_LENGTH )
						$messageStack->addSession( 'mensaje', 'El código postal del cliente en la dirección #' . $key . ' no puede tener menos de ' . ENTRY_POSTCODE_MIN_LENGTH . ' caracteres.' );

					// Comprobamos la ciudad
					if( intval( $entry_city_id[$key] ) == 0 && $entry_city[$key] == '' )
						$messageStack->addSession( 'mensaje', 'La ciudad del cliente en la dirección #' . $key . ' es obligatoria.' );

					// Comprobamos el pais
					if( $entry_country_id[$key] == false )
						$messageStack->addSession( 'mensaje', 'Debes seleccionar un pais para la dirección #' . $key . '.' );
				}
				// Fin, direcciones

				// Obtenemos al cliente
				$aCustomer = pharaonix_queryOne( 'select status_disabled, customers_newsletter, customers_language_id from customers where customers_id = "' . $customers_id . '"' )->records;

				// Si no existen errores actualizamos
				if( count($messageStack->errors) == 0 || ($aCustomer['status_disabled'] == 1 && $status_disabled == 0) )
				{
					$messageStack->reset();

					$aSql = array( 'customers_firstname' => $customers_firstname,
								   'customers_lastname' => $customers_lastname,
								   'recargo_equivalencia' => $recargo_equivalencia,
								   'customers_email_address' => $customers_email_address,
								   'customers_telephone' => $customers_telephone,
								   'customers_fax' => $customers_fax,
								   'customers_newsletter' => $customers_newsletter,
								   'especial' => $especial,
								   'especial_razon' => $especial_razon,
								   'customers_group_id' => $customers_group_id,
								   'id_customers_type' => $id_customers_type,
								   'customers_group_ra' => $customers_group_ra,
								   'customers_payment_allowed' => $customers_payment_allowed,
								   'customers_shipment_allowed' => $customers_shipment_allowed,
								   'customers_order_total_allowed' => $customers_order_total_allowed,
								   'customers_specific_taxes_exempt' => $customers_specific_taxes_exempt,
								   'cuenta_cliente' => $cuenta_cliente,
								   'customers_pyro_courier' => $customers_pyro_courier,
								   'entry_company_tax_id' => $entry_company_tax_id
					);


					if( ACCOUNT_GENDER == 'true' )
						$aSql['customers_gender'] = $customers_gender;

					if( ACCOUNT_DOB == 'true' )
						$aSql['customers_dob'] = tep_date_raw($customers_dob);

					// Actualizamos cliente
					tep_db_perform( TABLE_CUSTOMERS, $aSql, 'update', "customers_id = '" . (int)$customers_id . "'" );
					tep_db_query( "update " . TABLE_CUSTOMERS_INFO . " set customers_info_date_account_last_modified = now() where customers_info_id = '" . (int)$customers_id . "'" );

					// Direcciones
					// Recorremos uno de los campos por ejemplo el nombre y asi podemos posicionarnos en cada campo sin tener que recorrer todos
					foreach( $entry_firstname as $key => $value )
					{
						$aSql = array( 'entry_firstname' => $entry_firstname[$key],
									   'entry_lastname' => $entry_lastname[$key],
									   'entry_street_address' => $entry_street_address[$key],
									   'entry_postcode' => $entry_postcode[$key],
									   'entry_city' => $entry_city[$key],
									   'entry_city_id' => $entry_city_id[$key],
									   'entry_country_id' => $entry_country_id[$key]
						);

						if( ACCOUNT_COMPANY == 'true' )
							$aSql['entry_company'] = $entry_company[$key];

						if( ACCOUNT_NIF == 'true' )
							$aSql['entry_nif'] = $entry_nif[$key];

						if( ACCOUNT_SUBURB == 'true' )
							$aSql['entry_suburb'] = $entry_suburb[$key];

						//if( $entry_country_id[$key] == 195 )
						//{
							$aSql['entry_zone_id'] = $entry_zone_id[$key];
							$aSql['entry_state'] = '';
						//}
						//else
						//{
						//	$aSql['entry_zone_id'] = '0';
						//	$aSql['entry_state'] = $entry_state[$key];
						//}

						// Actualizamos
						tep_db_perform( TABLE_ADDRESS_BOOK, $aSql, 'update', "customers_id = '" . (int)$customers_id . "' and address_book_id = '" . (int)$key . "'" );
					}

					// Si cambiamos
					if( $aCustomer['customers_newsletter'] != $customers_newsletter )
					{
						// Eliminamos todo
						tep_db_query( 'DELETE FROM rgpd_account_term WHERE customers_id = "' . (int)$customers_id . '"' );

						// Activamos
						if( $customers_newsletter == '1' )
						{
							// Insertamos todo
							$aSubscribedAll = array_values( pharaonix_getArrayAssociativeSql( 'SELECT id_term_pivacy_trade, title, info FROM rgpd_term_privacy_trade WHERE language_id = 3', 'id_term_pivacy_trade', 'title', false ) );

							foreach( $aSubscribedAll as $aAux )
							{
								$nIdAll = $aAux['id'];
								$sTitle = $aAux['text'];

								tep_db_perform( 'rgpd_account_term', array( 'customers_id' => $customers_id, 'id_term_pivacy_trade' => $nIdAll ) );

								tep_db_perform( 'rgpd_log_term_privacy', array(
									'customers_id' => $customers_id,
									'customers_mail' => $customers_email_address,
									'ip' => tools::getIP(),
									'date' => date( 'Y-m-d H:i:s' ),
									'type' => 'comercial',
									'term_name' => $sTitle . ' - Cuenta modificada por administrador. Aceptación telefonica',
									'id_term_pivacy' => $nIdAll,
									'status' => 1
								) );
							}
						}
						else
						{
							// Insertamos todo
							$aSubscribedAll = array_values( pharaonix_getArrayAssociativeSql( 'SELECT id_term_pivacy_trade, title, info FROM rgpd_term_privacy_trade WHERE language_id = 3', 'id_term_pivacy_trade', 'title', false ) );

							foreach( $aSubscribedAll as $aAux )
							{
								$nIdAll = $aAux['id'];
								$sTitle = $aAux['text'];

								tep_db_perform( 'rgpd_log_term_privacy', array(
									'customers_id' => $customers_id,
									'customers_mail' => $customers_email_address,
									'ip' => tools::getIP(),
									'date' => date( 'Y-m-d H:i:s' ),
									'type' => 'comercial',
									'term_name' => $sTitle . ' - Cuenta modificada por administrador. Aceptación telefonica',
									'id_term_pivacy' => $nIdAll,
									'status' => 0
								) );
							}
						}
					}

					// Si cambiamos que el cliente este activo o no
					if( $aCustomer['status_disabled'] != $status_disabled )
					{
						// Creamos varaible para que funcione los metodos de la rgpd
						$customer_id = $customers_id;

						// Obtenemos un array con el id y idioma
						$aLanguage = pharaonix_getArrayAssociativeSql( 'select languages_id, directory from languages', 'languages_id', 'directory', false, 1 );

						// Desactivamos la cuenta
						if( $status_disabled == 1 )
						{
							// Obtenemos los archivos de idiomas
							include( getcwd() . '/../includes/languages/' . ($aLanguage[$aCustomer['customers_language_id']] != '' ? $aLanguage[$aCustomer['customers_language_id']] : 'espanol') . '/account_disable.php' );

							// Desactivamos
							$rgpd->accountDisableExecute(false);
						}
						else
						{
							// Obtenemos los archivos de idiomas
							include( getcwd() . '/../includes/languages/' . ($aLanguage[$aCustomer['customers_language_id']] != '' ? $aLanguage[$aCustomer['customers_language_id']] : 'espanol') . '/login.php' );

							// Desactivamos
							$rgpd->restoreCustomer($customers_id);
						}
					}
				}

				// Redireccionamos
				if( isAjax() )
				{
					echo $messageStack->show( array( 'class' => 'crrt', 'text' => 'El cliente (#' . $customers_id . ') ' . $customers_firstname . ' ' . $customers_lastname . ' se ha editado correctamente', 'success' ) );
					exit();
				}
				else
				{
					$messageStack->addSession( 'mensaje', 'El cliente (#' . $customers_id . ') ' . $customers_firstname . ' ' . $customers_lastname . ' se ha editado correctamente', 'success' );
					tep_redirect( tep_href_link( FILENAME_CUSTOMERS, tep_get_all_get_params( array( 'cID', 'action' ) ) ) );
				}
			break;

			case 'deleteconfirm':
				$customers_id = tep_db_prepare_input($_GET['cID']);

				if( isset($_POST['delete_reviews']) && ($_POST['delete_reviews'] == 'on'))
				{
					$reviews_query = tep_db_query("select reviews_id from " . TABLE_REVIEWS . " where customers_id = '" . (int)$customers_id . "'");

					while( $reviews = tep_db_fetch_array($reviews_query))
						tep_db_query("delete from " . TABLE_REVIEWS_DESCRIPTION . " where reviews_id = '" . (int)$reviews['reviews_id'] . "'");

					tep_db_query("delete from " . TABLE_REVIEWS . " where customers_id = '" . (int)$customers_id . "'");
				}
				else
					tep_db_query("update " . TABLE_REVIEWS . " set customers_id = null where customers_id = '" . (int)$customers_id . "'");

				tep_db_query("delete from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int)$customers_id . "'");
				tep_db_query("delete from " . TABLE_CUSTOMERS . " where customers_id = '" . (int)$customers_id . "'");
				tep_db_query("delete from " . TABLE_CUSTOMERS_INFO . " where customers_info_id = '" . (int)$customers_id . "'");
				tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int)$customers_id . "'");
				tep_db_query("delete from " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " where customers_id = '" . (int)$customers_id . "'");
				tep_db_query("delete from " . TABLE_WHOS_ONLINE . " where customer_id = '" . (int)$customers_id . "'");

				//#XCC-313-91043
				tep_db_query("delete from affiliates where customers_id = '" . (int)$customers_id . "'");

				// Eliminamo los logs de cliente
				tep_db_query( 'delete from rgpd_log_account where customers_id = "' . $customers_id . '"' );
				tep_db_query( 'delete from rgpd_log_term_privacy where customers_id = "' . $customers_id . '"' );

				// Eliminamos los terminos
				tep_db_query( 'delete from rgpd_account_term where customers_id = "' . $customers_id . '"' );

				$messageStack->addSession( 'mensaje', 'El cliente #' . $customers_id . ' se ha eliminado correctamente', 'success' );
				tep_redirect(tep_href_link(FILENAME_CUSTOMERS, tep_get_all_get_params(array('cID', 'action'))));
			break;
		}
	}

// Si estmos editando
if( in_array( $action, [ 'edit', 'update' ] ) )
{
		$customers_query = tep_db_query("select c.customers_id, c.id_customers_type, c.especial, c.especial_razon, c.recargo_equivalencia, c.customers_gender, c.customers_firstname, c.customers_lastname, date_format(c.customers_dob, '%d/%m%/%Y') as customers_dob, c.cuenta_cliente, c.customers_pyro_courier, c.customers_email_address, a.entry_firstname, a.entry_lastname, a.entry_company, a.entry_nif, a.entry_street_address, a.entry_suburb, a.entry_postcode, a.entry_city, a.entry_state, a.entry_zone_id, a.entry_country_id, c.customers_telephone, c.customers_fax, c.customers_newsletter,c.customers_group_id,  c.customers_group_ra, c.customers_payment_allowed, c.customers_shipment_allowed, c.customers_order_total_allowed, c.customers_specific_taxes_exempt, c.customers_default_address_id from " . TABLE_CUSTOMERS . " c left join " . TABLE_ADDRESS_BOOK . " a on a.address_book_id = " . (isset($_GET['add_id']) ? (int)$_GET['add_id'] : 'c.customers_default_address_id') . " where a.customers_id = c.customers_id and c.customers_id = '" . (int)$_GET['cID'] . "'");
		$customers = tep_db_fetch_array($customers_query);
		$cInfo = new objectInfo($customers);

		$module_directory = DIR_FS_CATALOG_MODULES . 'payment/';
		$ship_module_directory = DIR_FS_CATALOG_MODULES . 'shipping/';
		$order_total_module_directory = DIR_FS_CATALOG_MODULES . 'order_total/';

		$file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));
		$directory_array = array();

		if( $dir = @dir($module_directory) )
		{
			while($file = $dir->read())
			{
				if (!is_dir($module_directory . $file))
				{
					if (substr($file, strrpos($file, '.')) == $file_extension)
						$directory_array[] = $file; // array of all the payment modules present in includes/modules/payment
				}
			}

			sort($directory_array);
			$dir->close();
		}

		$ship_directory_array = array();

		if( $dir = @dir($ship_module_directory) )
		{
			while( $file = $dir->read() )
			{
				if( !is_dir($ship_module_directory . $file) )
				{
					if( substr($file, strrpos($file, '.')) == $file_extension )
						$ship_directory_array[] = $file; // array of all shipping modules present in includes/modules/shipping
				}
			}

			sort($ship_directory_array);
			$dir->close();
		}

		$order_total_directory_array = array();

		if( $dir = @dir($order_total_module_directory) )
		{
			while( $file = $dir->read() )
			{
				if( !is_dir($order_total_module_directory . $file) )
				{
					if( substr($file, strrpos($file, '.')) == $file_extension )
						$order_total_directory_array[] = $file; // array of all order total modules present in includes/modules/order_total
				}
			}

			sort($order_total_directory_array);
			$dir->close();
		}
	}

	// Funciones
	function showListCustomersNotes($sEstado, $sNota, $sFecha, $sColor, $sId, $sCustomersId)
	{
		$sHtml = '';

		$sHtml .= '<li style="position: relative; background: ' . $sColor . ';">';
			$sHtml .= '<span data-id="' . $sId . '" data-customersid="' . $sCustomersId . '" class="icos-cross" style="display: none; position: absolute; padding-top: 0px; right: -8px; top: 3px; cursor: pointer;"></span>';
			$sHtml .= '<span class="uNotice">';
				$sHtml .= '<p>' . $sEstado . '</p>';
				$sHtml .= '<span>' . $sNota . '</span>';
			$sHtml .= '</span>';
			$sHtml .= '<span class="uDate" style="color: #777;"><span>' . tep_date_day_short( $sFecha ) . '</span>' . tep_date_month_short( $sFecha ) . '<br>' . tep_datetime_hour_short( $sFecha ) . '</span>';
			$sHtml .= '<span class="clear"></span>';
		$sHtml .= '</li>';

		return $sHtml;
	}

	function setSort($sId, $sName)
	{
		global $orderby, $sort;
		$sSort = 'DESC';

		if( $orderby == $sId && $sort == 'DESC' )
			$sSort = 'ASC';

		$sClass = '';
		if( $orderby == $sId )
			$sClass = 'srtg_' . ($sSort == 'DESC' ? 'ASC' : 'DESC');

		return '<a href="' . tep_href_link(FILENAME_CUSTOMERS, tep_get_all_get_params(array('page', 'info', 'x', 'y', 'cID', 'orderby', 'sort')) . 'orderby=' . $sId . '&amp;sort='. $sSort) . '">' . $sName . '<span class="sorting ' . $sClass . '"></span></a>';
	}

	function call_Form_getData_entry_country_id()
	{
		// Variables
		return tep_get_countries();
	}

	function call_Form_getData_id_customers_type()
	{
		// Variables
		$aReturn = array(array( 'id' => '', 'text' => 'Ninguno' ));

		$aDatos = tep_db_query( 'select id_customers_type, nombre
								 from customers_type
								 order by id_customers_type' );

		while( $aDato = tep_db_fetch_array( $aDatos ) )
			  $aReturn[] = array( 'id' => $aDato['id_customers_type'], 'text' => $aDato['nombre'] );

		return $aReturn;
	}

	function call_Form_getData_customers_group_id()
	{
		// Variables
		$aReturn = array();

		$aDatos = tep_db_query( 'select customers_group_id, customers_group_name
								 from customers_groups
								 order by customers_group_id' );

		while( $aDato = tep_db_fetch_array( $aDatos ) )
			  $aReturn[] = array( 'id' => $aDato['customers_group_id'], 'text' => $aDato['customers_group_name'] );

		return $aReturn;
	}

	function call_Form_getRow_entry_zone_id($aRow, $aInfo, $sValueInfo)
	{
		// Variables
		global $cInfo;

		//if( $aInfo->entry_country_id == 195 )
		//{
			$zones_array = array();
			$sql = "select zone_name, zone_id from zones where zone_country_id = '" . tep_db_input( $aInfo->entry_country_id ) . "' order by zone_name";
			$zones_query = tep_db_query($sql );
			//echo '<pre style="color: red;">'.$sql.'</pre>';

			while ($zones_values = tep_db_fetch_array($zones_query))
				$zones_array[] = array('id' => $zones_values['zone_id'], 'text' => $zones_values['zone_name']);

			return tep_draw_pull_down_menu('entry_zone_id[' . $sValueInfo . ']', $zones_array, $aInfo->entry_zone_id );
		//}
		//else
		//	return '<input type="text" value="' . $aInfo->entry_state . '" name="entry_state[' . $sValueInfo . ']">';
	}

	function call_Form_getRow_entry_city_id($aRow, $aInfo, $sValueInfo)
	{
		// Variables
		global $cInfo;

		$zones_array = array();
		$sql = "select name, id, cp from cities where id_zone = '" . tep_db_input( $aInfo->entry_zone_id ) . "' order by name";
		//echo '<pre>'.print_r($aInfo, 1).'</pre>';
		//echo '<pre>'.$sql.'</pre>';
		$zones_query = tep_db_query( $sql );

		while ($zones_values = tep_db_fetch_array($zones_query))
			$zones_array[] = array('id' => $zones_values['id'], 'text' => $zones_values['name'].' ['.$zones_values['cp'].']');

		if (!empty($zones_array)) {
			return tep_draw_pull_down_menu('entry_city_id[' . $sValueInfo . ']', $zones_array, $aInfo->entry_city_id );
		} else {
			return '<input type="text" value="' . $aInfo->entry_city . '" name="entry_city[' . $sValueInfo . ']">';
		}
	}

	require(THEME . 'html/header.php');
?>
<link rel="stylesheet" type="text/css" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/cupertino/jquery-ui.css"/>
<table border="0" width="100%" cellspacing="0" cellpadding="2"><tr><td>

<?php if ($action == 'edit' || $action == 'update'): ?>
	<?php echo tep_draw_form('customers', FILENAME_CUSTOMERS, tep_get_all_get_params(array('action')) . 'action=update', 'post', 'id="form"'); ?>
		<input type="hidden" name="dxsendform" value="true" />
		<?php echo tep_draw_hidden_field('default_address_id', $cInfo->customers_default_address_id);?>
		<input type="submit" class="submit" style="display:none;">

		<div id="box-left">
			<ul class="nav">
				<li><a href="javascript:void(0);" class="active" data-id="1"><img src="images/icons/panel_icon_personal.png" alt=""><span>Datos</span></a></li>
				<li><a href="javascript:void(0);" data-id="2"><img src="images/icons/panel_icon_direccion.png" alt=""><span>Direcciones</span></a></li>
				<li><a href="javascript:void(0);" data-id="3"><img src="images/icons/panel_icon_order.png" alt=""><span>Pedidos</span></a></li>
				<li><a href="javascript:void(0);" data-id="4"><img src="images/icons/productos_opciones.png" alt=""><span>Opciones</span></a></li>
				<li><a href="javascript:void(0);" data-id="5"><img src="images/icons/panel_icon_modules.png" alt=""><span>Modulos</span></a></li>
				<li><a href="javascript:void(0);" data-id="6"><img src="images/icons/productos_datos_generales.png" alt=""><span>Notas</span></a></li>
				<li><a href="customers_points_history.php?cID=<?php echo (int) $cInfo->customers_id; ?>" title="Ver historial de puntos del cliente"><img src="images/icons/panel_icon_points.svg" width="44" height="44" alt=""><span>Puntos</span></a></li>
			</ul>
		</div>

		<div id="box-right">
			<div>
				<div class="toolbarHead">
					<div class="hdr-tlbr">
						<h1 class="pageHeading ftitl" style="top: 13px;">Editar cliente</h1>
						<h2 class="stitl" style="top: 13px;">#<?php echo $cInfo->customers_id . ' - ' . $cInfo->customers_firstname . ' ' . $cInfo->customers_lastname; ?></h2>
						<div class="btn-right">
							<a id="connect" href="#" onclick="connectAsCustomer('<?php echo htmlspecialchars($cInfo->customers_email_address, ENT_QUOTES); ?>', '<?php echo tep_master_connect_token($cInfo->customers_email_address); ?>');return false;"><img src="images/icons/cnct_user<?php echo ($language == 'espanol' ? '' : '_' . $language); ?>.png" class="dx-hovr"></a>
							<a title="Guardar cambios" id="save_return" href="javascript:void(0);"><img src="images/icons/icon_save.png" class="dx-hovr"></a>
							<a title="Guardar y volver" onclick="$('form .submit').click();" href="javascript:void(0);"><img src="images/icons/icon_save_return.png" class="dx-hovr"></a>
							<a href="<?php echo tep_href_link("customers.php", tep_get_all_get_params(array('info', 'x', 'y', 'cID', 'action'))); ?>"><img title="Volver sin guardar" src="images/icons/icon_back.png" class="dx-hovr"></a>
						</div>
					</div>
				</div>
			</div>

			<?php echo $messageStack->output(); ?>

			<div class="tab-new" data-id="1" style="display: block;">
				<?php echo showForm( 'includes/forms/customers_datos_personales.yml' );  ?>
			</div>

			<div class="tab-new" id="tab-dmcl" data-id="2" style="display: none;">
				<?php
					// Variables
					$aForm = loadForm( 'includes/forms/customers_direccion.yml' );
					$sHtmlDireccionPrincipal = '';
					$sHtmlDirecciones = '';

					// Obtenemos las direcciones
					$aDatos = tep_db_query( 'select * from address_book where customers_id = "' . (int)$_GET['cID'] . '"' );
					$nCont = 1;

					while( $aDato = tep_db_fetch_array( $aDatos ) )
					{
						// Obtenemos el objet info
						$aDato = new objectInfo($aDato);

						// Copiamos el form
						$aAux = $aForm;

						// Modificamos
						$aAux['info'] = '$aDato';

						// Si es la dirección principal
						if( $cInfo->customers_default_address_id == $aDato->address_book_id )
						{
							// Titulo
							$aAux['blocks'][0]['title'] = 'Dirección principal (<span data-id="' . $aDato->address_book_id . '">#' . $aDato->address_book_id . '</span>)';

							// Obtenemos el form de direccion
							$sHtmlDireccionPrincipal = showForm($aAux);
						}
						else // Si es una direccion extra
						{
							// Titulo
							$aAux['blocks'][0]['title'] = 'Dirección ' . $nCont . ' (<span data-id="' . $aDato->address_book_id . '">#' . $aDato->address_book_id . '</span>)';

							// Pintamos para mostrar el icono de eliminar
							$aAux['blocks'][0]['html_head'] = '<a data-add_id="' . $aDato->address_book_id . '" data-cid="' . $cInfo->customers_id . '" class="tOptions delete_direccion" href="javascript:void(0);" data-id="1" title="Eliminar dirección"><img src="theme/web/images/icons/usual/icon-trash.png" alt=""></a>';

							// Obtenemos el form de direccion
							$sHtmlDirecciones .= showForm($aAux);

							// Aumentamos direccion
							$nCont++;
						}
					}

					// Pintamos las direcciones
					echo $sHtmlDireccionPrincipal;
					echo $sHtmlDirecciones;
				?>
			</div>

			<div class="tab-new" data-id="3" style="display: none;">
				<?php
					// Variables
					$cID = tep_db_prepare_input($_GET['cID']);

					// Obtenemos los pedidos
					$aDatos = tep_db_query( 'select distinct(o.orders_id), o.customers_name, o.customers_id, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total, cg.customers_group_name
											 from orders o
											 left outer join orders_total ot on (o.orders_id = ot.orders_id)
											 left outer join customers c on (c.customers_id = o.customers_id)
											 left outer join customers_groups cg using(customers_group_id)
											 left outer join orders_status s on (o.orders_status = s.orders_status_id and s.language_id = 3)
											 where o.customers_id = "' . (int)$cID . '" and ot.class = "ot_total"
											 order by orders_id DESC' );

					// Si contenemos pedidos
					if( tep_db_num_rows( $aDatos ) > 0 )
					{
						echo '<div class="box-tbl" style="width: 100%">';
							echo '<div class="box-head">';
								echo '<h6>Listado de pedidos</h6>';
								echo '<div class="clear"></div>';
							echo '</div>';

							echo '<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
								<thead>
									<tr>
										<td style="text-align: left">Núm.</td>
										<td>Total Pedido</td>
										<td>Fecha de Compra</td>
										<td>F. Pago</td>
										<td>Estado</td>
										<td>Acción</td>
									</tr>
								</thead>
								<tbody>';
									// Recorremos los pedidos
									while( $aDato = tep_db_fetch_array( $aDatos ) )
									{
										echo '<tr>
											<td style="text-align: left">' . $aDato['orders_id'] . '</td>
											<td align="center">' . strip_tags( $aDato['order_total'] ) . '</td>
											<td align="center">' . tep_datetime_short( $aDato['date_purchased'] ) . '</td>
											<td align="center">' . strip_tags( $aDato['payment_method'] ) . '</td>
											<td align="center">' . $aDato['orders_status_name'] . '</td>
											<td align="center">
												<div class="btn-group" style="display: inline-block; margin-bottom: -7px;">
													<a class="buttonS bDefault" data-toggle="dropdown" href="#">Acciones<span class="caret"></span></a>
													<ul style="left: -70px;" class="dropdown-menu">
														<li><a href="' . tep_href_link( 'edit_orders.php', 'action=edit&oID=' . $aDato['orders_id'] ) . '" target="_blank"><span style="padding-top: 1px;" class="icos-search"></span>Editar pedido</a></li>
														<li><a href="' . tep_href_link( 'orders.php', 'action=edit&oID=' . $aDato['orders_id'] ) . '" target="_blank"><span style="padding-top: 1px;" class="icos-preview"></span>Ver pedido</a></li>
													</ul>
												</div>
											</td>
										</tr>';
									}
								echo '</tbody>';
							echo '</table>';
						echo '</div>';
					}
					else
						echo $messageStack->show( array( 'class' => 'wrng', 'text' => 'El cliente no ha realizado ningun pedido.' ) );
				?>
			</div>

			<div class="tab-new" data-id="4" style="display: none;">
				<?php echo showForm( 'includes/forms/customers_opciones.yml' );  ?>
			</div>

			<div class="tab-new" data-id="5" style="display: none;">
				<!-- MODULOS DE PAGO -->
				<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<h6>Módulos de Pago</h6>
							<div class="clear"></div>
						</div>

						<?php
							$payments_allowed = explode (";",$cInfo->customers_payment_allowed);
							$module_active = explode (";",MODULE_PAYMENT_INSTALLED);
							$installed_modules = array();
						?>

						<div class="formRow">
							<div class="grid12">
								<?php
									echo tep_draw_radio_field( 'customers_payment_settings', '1', false, (tep_not_null($cInfo->customers_payment_allowed) ? '1' : '0' ) );
									echo '<label style="padding-right: 25px;">' . ENTRY_CUSTOMERS_PAYMENT_SET . '</label>';
									echo tep_draw_radio_field( 'customers_payment_settings', '0', false, (tep_not_null($cInfo->customers_payment_allowed) ? '1' : '0' ) );
									echo '<label>' . ENTRY_CUSTOMERS_PAYMENT_DEFAULT . '</label>';
								?>
							</div>
							<div class="clear"></div>
						</div>

						<div class="formRow">
							<?php
								for ($i = 0, $n = sizeof($directory_array); $i < $n; $i++)
								{
									$file = $directory_array[$i];

									if( in_array ($directory_array[$i], $module_active) )
									{
										include(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/payment/' . $file);
										include($module_directory . $file);

										$class = substr($file, 0, strrpos($file, '.'));

										if( tep_class_exists($class) )
										{
											$module = new $class;
											if( $module->check() > 0 )
												$installed_modules[] = $file;
										}

										echo '<div class="grid12" style="margin: 0px;">';
											echo '<span class="check">' . tep_draw_checkbox_field('payment_allowed[' . $i . ']', $module->code.".php" , (in_array ($module->code.".php", $payments_allowed)) ?  1 : 0) . '</span>';
											echo '<label style="line-height: 22px;" for="payment_allowed[' . $i . ']">' . $module->title . '</label>';
										echo '</div>';
									}
								}
							?>
							<div class="clear"></div>
						</div>

						<div class="formRow" style="padding: 0px 16px; border: 0px none; background: rgb(247, 247, 247); position: relative; top: -6px;">
							<div class="grid12" style="margin: 0px;">
								<?php echo $messageStack->show( array( 'class' => 'info', 'text' => ENTRY_CUSTOMERS_PAYMENT_SET_EXPLAIN ) ); ?>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
				<!-- /MODULOS DE PAGO -->

				<!-- MODULOS DE ENVIO -->
				<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<h6>Módulos de Envío</h6>
							<div class="clear"></div>
						</div>

						<?php
							$shipment_allowed = explode (";",$cInfo->customers_shipment_allowed);
							$ship_module_active = explode (";",MODULE_SHIPPING_INSTALLED);
							$installed_shipping_modules = array();
						?>

						<div class="formRow">
							<div class="grid12">
								<?php
									echo tep_draw_radio_field('customers_shipment_settings', '1', false, (tep_not_null($cInfo->customers_shipment_allowed) ? '1' : '0' ));
									echo '<label style="padding-right: 25px;">' . ENTRY_CUSTOMERS_SHIPPING_SET . '</label>';
									echo tep_draw_radio_field( 'customers_shipment_settings', '0', false, (tep_not_null($cInfo->customers_shipment_allowed)? '1' : '0' ));
									echo '<label>' . ENTRY_CUSTOMERS_SHIPPING_DEFAULT . '</label>';
								?>
							</div>
							<div class="clear"></div>
						</div>

						<div class="formRow">
							<?php
								for ($i = 0, $n = sizeof($ship_directory_array); $i < $n; $i++)
								{
									$file = $ship_directory_array[$i];

									if( in_array ($ship_directory_array[$i], $ship_module_active))
									{
										include(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/shipping/' . $file);
										include($ship_module_directory . $file);

										$ship_class = substr($file, 0, strrpos($file, '.'));

										if( tep_class_exists($ship_class) )
										{
											$ship_module = new $ship_class;
											if( $ship_module->check() > 0 )
												$installed_shipping_modules[] = $file;
										}

										echo '<div class="grid12" style="margin: 0px;">';
											echo '<span class="check">' . tep_draw_checkbox_field('shipping_allowed[' . $i . ']', $ship_module->code.".php" , (in_array ($ship_module->code.".php", $shipment_allowed)) ?  1 : 0) . '</span>';
											echo '<label style="line-height: 22px;" for="payment_allowed[' . $i . ']">' . $ship_module->title . '</label>';
										echo '</div>';
									}
								}
							?>
							<div class="clear"></div>
						</div>

						<div class="formRow" style="padding: 0px 16px; border: 0px none; background: rgb(247, 247, 247); position: relative; top: -6px;">
							<div class="grid12" style="margin: 0px;">
								<?php echo $messageStack->show( array( 'class' => 'info', 'text' => ENTRY_CUSTOMERS_SHIPPING_SET_EXPLAIN ) ); ?>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
				<!-- /MODULOS DE ENVIO -->

				<!-- MODULOS DE TOTALIZACION -->
				<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<h6>Módulos de totalización</h6>
							<div class="clear"></div>
						</div>

						<?php
							$order_total_allowed = explode (";",$cInfo->customers_order_total_allowed);
							$order_total_module_active = explode (";",MODULE_ORDER_TOTAL_INSTALLED);
							$installed_order_total_modules = array();
						?>

						<div class="formRow">
							<div class="grid12">
								<?php
									echo tep_draw_radio_field('customers_order_total_settings', '1', false, (tep_not_null($cInfo->customers_order_total_allowed)? '1' : '0' ));
									echo '<label style="padding-right: 25px;">' . ENTRY_CUSTOMERS_ORDER_TOTAL_SET . '</label>';
									echo tep_draw_radio_field('customers_order_total_settings', '0', false, (tep_not_null($cInfo->customers_order_total_allowed)? '1' : '0' ));
									echo '<label>' . ENTRY_CUSTOMERS_ORDER_TOTAL_DEFAULT . '</label>';
								?>
							</div>
							<div class="clear"></div>
						</div>

						<div class="formRow">
							<?php
								for( $i = 0, $n = sizeof($order_total_directory_array); $i < $n; $i++ )
								{
									$file = $order_total_directory_array[$i];

									if( in_array ($order_total_directory_array[$i], $order_total_module_active) )
									{
										include(DIR_FS_CATALOG_LANGUAGES . $language . '/modules/order_total/' . $file);
										include($order_total_module_directory . $file);

										$order_total_class = substr($file, 0, strrpos($file, '.'));

										if( tep_class_exists($order_total_class) )
										{
											$order_total_module = new $order_total_class;

											if( $order_total_module->check() > 0 )
												$installed_order_total_modules[] = $file;
										}

										echo '<div class="grid12" style="margin: 0px;">';
											echo '<span class="check">' . tep_draw_checkbox_field('order_total_allowed[' . $i . ']', $order_total_module->code.".php" , (in_array ($order_total_module->code.".php", $order_total_allowed)) ?  1 : 0) . '</span>';
											echo '<label style="line-height: 22px;" for="payment_allowed[' . $i . ']">' . $order_total_module->title . '</label>';
										echo '</div>';
									}
								}
							?>
							<div class="clear"></div>
						</div>

						<div class="formRow" style="padding: 0px 16px; border: 0px none; background: rgb(247, 247, 247); position: relative; top: -6px;">
							<div class="grid12" style="margin: 0px;">
								<?php echo $messageStack->show( array( 'class' => 'info', 'text' => ENTRY_CUSTOMERS_ORDER_TOTAL_SET_EXPLAIN ) ); ?>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
				<!-- /MODULOS DE TOTALIZACION -->

				<!-- MODULOS DE TAX -->
				<div class="fluid grid">
					<div class="box-tbl grid12">
						<div class="box-head">
							<h6>Exempt Customer from Specific Tax Rates</h6>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid12">
								<?php
									echo tep_draw_radio_field('customers_tax_rate_exempt_settings', '1', false, (tep_not_null($cInfo->customers_specific_taxes_exempt)? '1' : '0' ));
									echo '<label style="padding-right: 25px;">' . ENTRY_CUSTOMERS_TAX_RATES_EXEMPT . '</label>';
									echo tep_draw_radio_field('customers_tax_rate_exempt_settings', '0', false, (tep_not_null($cInfo->customers_specific_taxes_exempt)? '1' : '0' ));
									echo '<label>' . ENTRY_CUSTOMERS_TAX_RATES_DEFAULT . '</label>';
								?>
							</div>
							<div class="clear"></div>
						</div>

						<div class="formRow">
							<?php
								$customers_tax_ids_exempt = explode (",",$cInfo->customers_specific_taxes_exempt);
								$tax_query = tep_db_query("select tax_rates_id, tax_rate, tax_description from " . TABLE_TAX_RATES . " order by tax_rates_id");

								while ($tax_rate = tep_db_fetch_array($tax_query))
								{
									echo '<div class="grid12" style="margin: 0px;">';
										echo '<span class="check">' . tep_draw_checkbox_field('customers_tax_rate_exempt_id[' . $tax_rate['tax_rates_id'] . ']', $tax_rate['tax_rates_id'] , (in_array($tax_rate['tax_rates_id'], $customers_tax_ids_exempt)) ? 1 : 0) . '</span>';
										echo '<label style="line-height: 22px;" for="payment_allowed[' . $i . ']">' . $tax_rate['tax_description'] . '</label>';
									echo '</div>';
								}
							?>
							<div class="clear"></div>
						</div>

						<div class="formRow" style="padding: 0px 16px; border: 0px none; background: rgb(247, 247, 247); position: relative; top: -6px;">
							<div class="grid12" style="margin: 0px;">
								<?php echo $messageStack->show( array( 'class' => 'info', 'text' => ENTRY_CUSTOMERS_TAX_RATES_EXEMPT_EXPLAIN ) ); ?>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
				<!-- /MODULOS DE TAX -->
			</div>

			<div class="tab-new" data-id="6" style="display: none;">
				<?php echo $messageStack->show( array( 'class' => 'info', 'text' => 'El primer estado que se muestra sera el estado actual del cliente.', 'success' ) ); ?>

				<div class="fluid grid">
					<div class="box-tbl grid6" id="add-customers-note-form">
						<div class="box-head">
							<h6>Añadir nota</h6>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label for="entry_firstname">Nota:</label>
							</div>
							<div class="grid10">
								<textarea rows="10" cols="80" name="customers_notes" id="customers_notes"></textarea>
								<input type="hidden" name="customers_id" id="customers_id" value="<?php echo $_GET['cID']; ?>" />
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="grid2">
								<label for="entry_firstname">Estado:</label>
							</div>
							<div class="grid10">
								<?php
									// Obtenemos todos los estados
									$aDatos = tep_db_query( 'select id_customers_notes_status, customers_notes_status from customers_notes_status order by customers_notes_status asc' );
									$aReturn = array();

									while( $aDato = tep_db_fetch_array($aDatos) )
										$aReturn[] = array( 'id' => $aDato['id_customers_notes_status'], 'text' => $aDato['customers_notes_status'] );

									echo tep_draw_pull_down_menu( 'id_customers_notes_status', $aReturn, '', 'id="id_customers_notes_status"' );
								?>
							</div>
							<div class="clear"></div>
						</div>
						<div class="formRow">
							<div class="wButton grid6">
								<a id="add-customers-note-sbmt" class="buttonL bGreen" href="javascript:void(0);">Insertar nota</a>
							</div>
							<div class="clear"></div>
						</div>
					</div>

					<div class="sResults grid6" style="border-top: none;">
                        <ul class="updates" id="list-customers-notes">
							<?php
								$aDatos = tep_db_query( 'select cn.id_customers_notes, cn.customers_notes, cns.customers_notes_status, cns.customers_notes_color, cn.customers_notes_date
														 from customers_notes cn
														 inner join customers_notes_status cns on(cn.id_customers_notes_status = cns.id_customers_notes_status)
														 where customers_id = "' . (int)$_GET['cID'] . '"
														 order by cn.customers_notes_date desc' );

								$nCantidadNotas = tep_db_num_rows( $aDatos );

								while( $aDato = tep_db_fetch_array( $aDatos ) )
									echo showListCustomersNotes( $aDato['customers_notes_status'], $aDato['customers_notes'], $aDato['customers_notes_date'], $aDato['customers_notes_color'], $aDato['id_customers_notes'], $_GET['cID'] );
							?>
						</ul>

						<div id="sResults_msj" style="overflow: hidden; margin-bottom: -8px; <?php echo ($nCantidadNotas > 0 ? 'display: none;' : ''); ?>">
							<?php echo $messageStack->show( array( 'class' => 'wrng', 'text' => 'No existe ninguna nota. Inserta alguna nota para que aparezca en la lista.', 'success' ) ); ?>
						</div>
                    </div>
				</div>

			</div>
		</div>
	</form>

<?php else: ?>
	<?php
		// Retrieve customer group data and their totals
		$aGrupoClientes = [];

		$aDatos = tep_db_query(" SELECT cg.customers_group_id, cg.customers_group_name, COUNT(c.customers_group_id) AS total
										FROM
											" . TABLE_CUSTOMERS_GROUPS . " AS cg
										LEFT JOIN
											" . TABLE_CUSTOMERS . " AS c ON cg.customers_group_id = c.customers_group_id
										GROUP BY
											cg.customers_group_id
										ORDER BY
											cg.customers_group_id
									");

		while ($aDato = tep_db_fetch_array($aDatos)) {
			$aGrupoClientes[$aDato['customers_group_id']] = ['text' => $aDato['customers_group_name'], 'total' => $aDato['total']];
		}

		// Busqueda
		$search = '';
		$sGetSearchCg = isset($_GET['search_cg']) ? tep_db_prepare_input($_GET['search_cg']) : '';
		$sGetSearchCt = isset($_GET['search_ct']) ? tep_db_prepare_input($_GET['search_ct']) : '';
		$sGetSearchCs = isset($_GET['search_cs']) ? tep_db_prepare_input($_GET['search_cs']) : '';
		$sGetSearch = isset($_GET['search']) ? tep_db_prepare_input($_GET['search']) : '';

		// Setup column sorting
		switch ($orderby) {
			case 'lastname':
				$db_orderby = 'c.customers_lastname ' . $sort . ', c.customers_firstname';
				break;
			case 'firstname':
				$db_orderby = 'c.customers_firstname ' . $sort . ', c.customers_lastname';
				break;
			case 'company':
				$db_orderby = 'a.entry_company ' . $sort . ', a.entry_company';
				break;
			case 'groupid':
				$db_orderby = 'c.customers_group_id ' . $sort . ', c.customers_firstname';
				break;
			case 'tipoid':
				$db_orderby = 'c.id_customers_type ' . $sort . ', c.customers_firstname';
				break;
			case 'estadoid':
				$db_orderby = 'c.id_customers_notes_status ' . $sort . ', c.customers_firstname';
				break;
			case 'date_created':
				$db_orderby = 'c.customers_id';
				break;
			case 'newsletter':
				$db_orderby = 'sub.customers_newsletter ' . $sort . ', c.customers_lastname';
				break;
			case 'num_logins':
				$db_orderby = 'ci.customers_info_number_of_logons ' . $sort . ', c.customers_lastname';
				break;
			case 'dob':
				$db_orderby = 'customers_dob ' . $sort . ', c.customers_lastname';
				break;
			case 'orders_qty':
				$db_orderby = 'orders_qty ' . $sort . ', c.customers_lastname';
				break;
			case 'ultimo_pedido':
				$db_orderby = 'ultimo_pedido ' . $sort . ', c.customers_lastname';
				break;
			default:
				$db_orderby = 'c.customers_lastname ASC, c.customers_firstname';
				break;
		}

		if( !$sort )
			$sort = 'ASC';

		if( $sGetSearch != '' || $sGetSearchCg != '' || $sGetSearchCt != '' || $sGetSearchCs != '' )
			$search = 'where ';

		if (isset($_GET['search']) && tep_not_null($_GET['search']))
		{
			// Obtenemos las keywords
			$keywords = tep_db_input(tep_db_prepare_input($_GET['search']));

			// Obtenemos todas las combinaciones
			$aBusquedas = combinations( $keywords );

			// Componemos el where
			if( count( $aBusquedas ) == 0 )
				$aBusquedas[] = array($keywords);

			foreach( $aBusquedas as $aCadena )
				$search .= '(a.entry_nif like "%' . implode( '%', $aCadena ) . '%"  or a.entry_company like "%' . implode( '%', $aCadena ) . '%" or c.customers_email_address like "%' . implode( '%', $aCadena ) . '%" or LOWER(c.customers_email_address) like "%' . strtolower(implode( '%', $aCadena )) . '%" or replace( replace( replace( replace( replace( lower( CONCAT(c.customers_firstname, " ", c.customers_lastname) ), "á", "a" ), "é", "e" ), "í", "i"), "ó", "o"), "ú", "u" ) like "%' . str_replace( array('á','é','í','ó','ú'), array('a','e','i','o','u'), strtolower( implode( '%', $aCadena ) ) ) . '%") OR ';

			$search = substr( $search, 0, -4 );
		}

		if( $sGetSearchCg != '' )
		{
			if( $search != 'where ' )
				$search .= ' and';

			$search .= ' c.customers_group_id = "' . $sGetSearchCg . '"';
		}

		if( $sGetSearchCt != '' )
		{
			if( $search != 'where ' )
				$search .= ' and';

			$search .= ' c.id_customers_type = "' . $sGetSearchCt . '"';
		}

		if( $sGetSearchCs != '' )
		{
			if( $search != 'where ' )
				$search .= ' and';

			$search .= ' c.id_customers_notes_status = "' . $sGetSearchCs . '"';
		}

		$customers_query_raw = 'select c.customers_id
								from customers c
								left outer join address_book a on (c.customers_id = a.customers_id and c.customers_default_address_id = a.address_book_id)
								' . $search . ' order by ' . $db_orderby . ' ' . $sort;

		$customers_query_raw = preg_replace("/[\r\n\t]+/", " ", $customers_query_raw );

		$customers_split = new splitPageResults($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS, $customers_query_raw, $customers_query_numrows, 'select count(distinct c.customers_id) as total from customers c inner join address_book a on (a.customers_id = c.customers_id) ' . $search );
		$customers_query = tep_db_query($customers_query_raw);

		if (tep_db_num_rows($customers_query) > 0) {
			$customersList = '';

			while ($customers = tep_db_fetch_array($customers_query)) {
				$customersList .= $customers['customers_id'] . ', ';
			}

			$customersList = substr($customersList, 0, -2);

			$customers_query_raw = 'SELECT a.entry_postcode,
										   sub.customers_newsletter,
										   cit.name AS nombre_ciudad,
										   c.customers_id,
										   cg.customers_group_name,
										   ct.color,
										   ct.nombre,
										   cns.customers_notes_status,
										   o.orders_qty,
										   o.ultimo_pedido,
										   c.especial,
										   c.especial_razon,
										   a.entry_company,
										   c.customers_lastname,
										   c.customers_group_id,
										   c.customers_firstname,
										   a.entry_nif,
										   c.proveedor,
										   c.proveedor_iae,
										   c.member_level,
										   c.customers_email_address,
										   c.customers_telephone,
										   c.customers_dob,
										   ci.customers_info_date_account_last_modified AS date_account_last_modified,
										   ci.customers_info_date_of_last_logon AS last_logon,
										   ci.customers_info_number_of_logons AS number_of_logons,
										   ci.customers_info_date_account_created AS date_account_created,
										   a.entry_city AS city,
										   a.entry_state AS state_alt,
										   z.zone_name AS state,
										   ctry.countries_iso_code_2 AS country,
										   a.entry_country_id
									FROM customers c
									LEFT JOIN address_book a
										   ON c.customers_id = a.customers_id
										  AND c.customers_default_address_id = a.address_book_id
									LEFT JOIN customers_groups cg
										   ON cg.customers_group_id = c.customers_group_id
									LEFT JOIN subscribers sub
										   ON c.customers_id = sub.customers_id
									LEFT JOIN customers_type ct
										   ON ct.id_customers_type = c.id_customers_type
									LEFT JOIN customers_notes_status cns
										   ON cns.id_customers_notes_status = c.id_customers_notes_status
									LEFT JOIN (
										SELECT customers_id,
											   COUNT(*) AS orders_qty,
											   MAX(date_purchased) AS ultimo_pedido
										FROM orders
										 WHERE customers_id IN (' . $customersList . ')
										GROUP BY customers_id
									) o ON c.customers_id = o.customers_id
									LEFT JOIN customers_info ci
										   ON c.customers_id = ci.customers_info_id
									LEFT JOIN countries ctry
										   ON a.entry_country_id = ctry.countries_id
									LEFT JOIN zones z
										   ON a.entry_zone_id = z.zone_id
									LEFT JOIN cities cit
										   ON a.entry_city_id = cit.id
									WHERE c.customers_id IN (' . $customersList . ')
									ORDER BY ' . $db_orderby . ' ' . $sort;

			$customers_query = tep_db_query($customers_query_raw);
		}
	?>

	<div>
		<div class="toolbarHead">
			<div class="hdr-tlbr">
				<h1 class="pageHeading" style="top: 13px;">Listado de clientes</h1>
				<div class="btn-right">
					<a href="create_account.php" title="Crear cliente"><img class="dx-hovr" src="images/icons/add_user.png"></a>
					<a href="exportador_clientes.php" title="Exportar clientes"><img class="dx-hovr" src="images/icons/icon_excel.png"></a>

					<?php
						if( $search != '' )
							echo '<a href="' . tep_href_link( FILENAME_CUSTOMERS, tep_get_all_get_params( array( 'cID', 'action', 'search', 'search_cg', 'search_ct', 'search_cs', 'page', 'orderby', 'sort' ) ) ) . '" title="Limpiar filtro"><img class="dx-hovr" src="images/icons/icon_clear_filter.png"></a>';
					?>
				</div>
			</div>
		</div>
	</div>

	<?php
		echo $messageStack->output();

		if( tep_db_num_rows( $customers_query ) == 0 )
			echo $messageStack->show( array( 'class' => 'eror', 'text' => 'No existen clientes con el filtro seleccionado', 'success' ) );
	?>

	<div class="box-tbl" style="width: 100%">
		<div class="box-head">
			<h6>Listado de clientes</h6>
			<a title="Filtrar" data-id="1" href="javascript:void(0);" class="tOptions filter-togle"><img alt="" src="theme/web/images/icons/usual/icon-cog3.png"></a>
			<div class="clear"></div>
		</div>

		<div data-id="1" class="fluid grid tablePars">
			<?php echo tep_draw_form('search', FILENAME_CUSTOMERS, '', 'get' ) . "\n"; ?>
				<div class="grid12">
					<div class="formRow" style="border: none; padding: 7px 16px;">
						<div class="grid2"><label>Buscar:</label></div>
						<div class="grid10"><?php echo tep_draw_input_field( 'search', $search, 'placeholder="Introduce búsqueda..."' ); ?></div>
						<div class="clear"></div>
					</div>
					<div class="formRow" style="border: none; padding: 7px 16px;">
						<div class="grid2"><label>Grupo de cliente:</label></div>
						<div class="grid10">
							<?php
								// Inicializamos el array de opciones para el menú desplegable
								$aReturn = [['id' => '', 'text' => 'Seleccione']];

								foreach ($aGrupoClientes as $key => $group) {
									$aReturn[] = ['id' => $key, 'text' => $group['text']]; // Usamos $key como ID y 'text' del grupo
								}

								echo tep_draw_pull_down_menu('search_cg', $aReturn, $sGetSearchCg ?? '');
							?>
						</div>
						<div class="clear"></div>
					</div>
					<div class="formRow" style="border: none; padding: 7px 16px;">
						<div class="grid2"><label>Tipo cliente:</label></div>
						<div class="grid10">
							<?php
							// Inicializamos el array de opciones para el menú desplegable
							$aReturn = [['id' => '', 'text' => 'Seleccione']];

							// Consulta para obtener los datos de la tabla customers_type
							$query = tep_db_query("SELECT id_customers_type, nombre FROM customers_type");

							// Recorremos los resultados y construimos el array para el menú
							while ($row = tep_db_fetch_array($query)) {
								$aReturn[] = ['id' => $row['id_customers_type'], 'text' => $row['nombre']];
							}

							// Imprimimos el menú desplegable
							echo tep_draw_pull_down_menu('search_ct', $aReturn, $sGetSearchCt ?? '');
							?>
						</div>
						<div class="clear"></div>
					</div>
					<div class="formRow" style="border: none; padding: 7px 16px;">
						<div class="grid2"><label>Estado cliente:</label></div>
						<div class="grid10">
							<?php
								$aReturn = array( array( 'id' => '', 'text' => 'Seleccione' ) );
								$aDatos = tep_db_query( 'select id_customers_notes_status, customers_notes_status
														 from customers_notes_status
														 order by customers_notes_status' );

								while( $aDato = tep_db_fetch_array( $aDatos ) )
									  $aReturn[] = array( 'id' => $aDato['id_customers_notes_status'], 'text' => $aDato['customers_notes_status'] );
								echo tep_draw_pull_down_menu( 'search_cs', $aReturn, $sGetSearchCt );
							?>
						</div>
						<div class="clear"></div>
					</div>
					<div class="formRow" style="border: none; padding: 7px 16px;">
						<div class="grid12">
							<input type="submit" value="Filtrar" class="buttonS bGreen" style="cursor: pointer;"/>
						</div>
						<div class="clear"></div>
					</div>
				</div>
			</form>
		</div>

		<form id="massActionsForm" method="post" action="customers.php?action=massAction">

			<div style="float:left; margin-right:15px;">
			<select name="mass_action" id="mass_action">
				<option value="">-- Acciones masivas --</option>
				<option value="delete">Eliminar seleccionados</option>
			</select>
			<button type="submit" class="buttonS bGreen">Aplicar</button>
		</div>

			<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
			<thead>
				<tr>
					<td width="20" style="text-align:center">
						<input type="checkbox" id="checkAll">
					</td>
					<td style="text-align: left"><?php echo setSort( 'firstname', 'Nombre' ); ?></td>
					<td width=""><?php echo setSort( 'lastname', 'Apellidos' ); ?></td>
					<td width=""><?php echo setSort( 'company', 'Empresa' ); ?></td>
					<td width=""><?php echo setSort( 'groupid', 'Grupo de cliente' ); ?></td>
					<td width=""><?php echo setSort( 'tipoid', 'Tipo de cliente' ); ?></td>
					<td width=""><?php echo setSort( 'estadoid', 'Estado del cliente' ); ?></td>
					<td width=""><?php echo setSort( 'date_created', 'Fecha Reg.' ); ?></td>
					<td width=""><?php echo setSort( 'date_login', 'Últ. acceso' ); ?></td>
					<td width=""><?php echo setSort( 'num_logins', 'Veces' ); ?></td>
					<td width=""><?php echo setSort( 'state', 'Ubicación' ); ?></td>
					<td width=""><?php echo setSort( 'newsletter', 'Boletín' ); ?></td>
					<td width=""><?php echo setSort( 'dob', 'Nacimiento' ); ?></td>
					<td width=""><?php echo setSort( 'orders_qty', 'Cantidad de pedidos' ); ?></td>
					<td width=""><?php echo setSort( 'ultimo_pedido', 'Último pedido' ); ?></td>
					<td width="125">Acciones</td>
				</tr>
			</thead>
			<tbody>
				<?php while( $customers = tep_db_fetch_array($customers_query) ): ?>
					<?php
						$sColor = '';

						if( $customers['color'] != '' || $customers['color'] != 'NULL' || $customers['color'] != 0 )
							$sColor = 'style="background: ' . $customers['color'] . ' !important;"';
					?>

					<tr data-id="<?php echo $customers['customers_id']; ?>" class="dbclick">
						<td style="text-align:center">
							<input type="checkbox" class="chkItem" name="selected[]" value="<?php echo $customers['customers_id']; ?>">
						</td>
						<td <?php echo $sColor; ?>>
							<?php if( $customers['member_level'] == '0' && $customers['proveedor'] =='1' ): ?>
								<a href="members.php?cID=<?php echo $customers['customers_id']; ?>&action=accept"><span class="icos-admin"  style="padding-top: 1px;"></span></a>
							<?php endif; ?>

							<?php if( $customers['proveedor_iae'] != '' ): ?>
								<a href="/<?php echo $customers['proveedor_iae']; ?>" target="_blank"><span class="icos-pdf"  style="padding-top: 1px;"></span></a>
							<?php endif; ?>

							<?php if( $customers['especial'] == 1 ): ?>
								<span class="icos-star" style="padding-top: 1px;"></span>
							<?php endif; ?>

							<?php echo htmlspecialchars( $customers['customers_firstname'] ); ?>
						</td>
						<td <?php echo $sColor; ?>><?php echo htmlspecialchars($customers['customers_lastname']); ?></td>
						<td <?php echo $sColor; ?>><?php echo htmlspecialchars($customers['entry_company'] ?? ''); ?></td>
						<td <?php echo $sColor; ?>><?php echo htmlspecialchars($customers['customers_group_name'] ?? ''); ?></td>
						<td <?php echo $sColor; ?>><?php echo htmlspecialchars((string)($customers['nombre'] ?? '')); ?></td>
						<td <?php echo $sColor; ?>><?php echo htmlspecialchars((string)($customers['customers_notes_status'] ?? '')); ?></td>
						<td <?php echo $sColor; ?>><?php echo tep_date_short($customers['date_account_created']); ?></td>
						<td <?php echo $sColor; ?>><?php echo tep_date_short($customers['last_logon']); ?></td>
						<td <?php echo $sColor; ?>><?php echo ($customers['number_of_logons']); ?></td>
						<td <?php echo $sColor; ?>>
							<?php
								echo $customers['country'] ? '(' . htmlspecialchars($customers['country']) . ') ' : '';

								if( isset($customers['state']) )
									echo htmlspecialchars(html_entity_decode(ucwords($customers['state'])));
								else if( ! empty($customers['state_alt']) )
									echo htmlspecialchars(html_entity_decode(ucwords($customers['state_alt'])));

								echo ucwords( $customers['city'] ? ', ' . htmlspecialchars($customers['city']) : '' );

								if( $customers['nombre_ciudad'] != '' )
									echo '<br /><small style="font-size: 0.9em;">' . $customers['nombre_ciudad'] . ' (' . $customers['entry_postcode'] . ')</small>';
								else
								{
									if($customers['entry_country_id'] == 195)
										echo '<br /><small style="font-size: 0.9em; color: red;">Datos erroneos, revisar</strong></small>';
								}
							?>
						</td>
						<td <?php echo $sColor; ?>>
							<div align="center">
								<?php
									if ($customers['customers_newsletter'] > '0')
										echo '<span style="color: #98ba47">' . ENTRY_NEWSLETTER_YES . '</span>';
									else
										echo '<span style="color: #c76262">' . ENTRY_NEWSLETTER_NO . '</span>';
								?>
							</div>
						</td>
						<td <?php echo $sColor; ?>><?php echo tep_date_short($customers['customers_dob']); ?></td>
						<td <?php echo $sColor; ?>><?php echo $customers['orders_qty']; ?></td>
						<td <?php echo $sColor; ?>><?php echo tep_date_short( $customers['ultimo_pedido']); ?></td>
						<td  <?php echo $sColor; ?> align="center">
							<div style="display: inline-block; margin-bottom: -7px;" class="btn-group">
								<a href="#" data-toggle="dropdown" class="buttonS bDefault">Acciones<span class="caret"></span></a>
								<ul class="dropdown-menu" style="left: -70px;">
									<li><a href="<?php echo tep_href_link(FILENAME_CUSTOMERS, tep_get_all_get_params(['cID', 'action']) . 'cID=' . $customers['customers_id'] . '&amp;action=edit'); ?>"><i class="fas fa-user-edit"></i> <? echo CUSTOMERS_ACTIONS_EDIT; ?></a></li>
									<li><a href="<?php echo tep_href_link(FILENAME_CUSTOMERS, tep_get_all_get_params(['cID', 'action']) . 'cID=' . $customers['customers_id'] . '&amp;action=deleteconfirm'); ?>" class="dlet"><i class="fas fa-trash-alt"></i> <? echo CUSTOMERS_ACTIONS_DELETE; ?></a></li>
									<li><a href="<?php echo tep_href_link(FILENAME_ORDERS, 'cID=' . $customers['customers_id']); ?>"><i class="fas fa-list-alt"></i> <? echo CUSTOMERS_ACTIONS_SEE_ORDERS; ?></a></li>
									<li><a href="<?php echo tep_href_link('create_order.php', 'Customer=' . $customers['customers_id']); ?>"><i class="fas fa-cart-plus"></i> <? echo CUSTOMERS_ACTIONS_NEW_ORDER; ?></a></li>
									<li><a target="_blank" href="<?php echo tep_href_link('change_password.php', 'cID=' . $customers['customers_id']); ?>"><i class="fas fa-key"></i> <? echo CUSTOMERS_ACTIONS_CHANGE_PASS; ?></a></li>
									<li><a href="<?php echo tep_href_link(FILENAME_MAIL, 'selected_box=tools&amp;customer=' . $customers['customers_email_address']); ?>"><span style="padding-top: 1px;" class="icos-email"></span>Enviar email al cliente</a></li>
									<li><a href="<?php echo tep_href_link('customers_points_history.php', 'cID=' . $customers['customers_id']); ?>"><i class="fas fa-ticket-alt"></i> Historial de puntos</a></li>
									<li>
										<a href="#" onclick="event.preventDefault();event.stopPropagation();connectAsCustomer('<?php echo htmlspecialchars($customers['customers_email_address'], ENT_QUOTES); ?>', '<?php echo tep_master_connect_token($customers['customers_email_address']); ?>');return false;"><i class="fas fa-sign-in-alt"></i> <?php echo CUSTOMERS_ACTIONS_CONNECT_AS; ?></a>
									</li>
								</ul>
							</div>
						</td>
					</tr>
				<?php endwhile; ?>
			</tbody>
		</table>
		</form>

		<?php echo $customers_split->showPaginateTable(tep_get_all_get_params(array('page', 'info', 'x', 'y', 'cID'))); ?>
	</div>

	<div class="box-tbl" style="width: 400px; margin-top: 35px;">
		<div class="box-head">
			<h6>Total de clientes por grupos</h6>
			<div class="clear"></div>
		</div>
		<table id="table" class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
			<thead>
				<tr>
					<td style="text-align: left">Grupo</td>
					<td width="">Total</td>
				</tr>
			</thead>
			<tbody>
				<?php
					foreach( $aGrupoClientes as $aGrupo )
						echo '<tr><td>' . $aGrupo['text'] . '</td><td>' . $aGrupo['total'] . '</td></tr>'
				?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<div id="dxload"></div>
<div id="dxbg"></div>

<?php require(THEME . 'html/footer.php');  ?>

<script type="text/javascript">

	// Conectar como cliente
	// - Abrimos window.open() PRIMERO en el gesto del click (popup-blocker safe)
	// - Creamos form en document.body (fuera de cualquier form padre)
	// - Apuntamos el form a la ventana abierta por su name para que el POST caiga ahí
	function connectAsCustomer(email, pass) {
		var winName = 'connectAsCustomer_' + Date.now();
		var newWin = window.open('', winName);
		if (!newWin) {
			alert('El navegador ha bloqueado la ventana emergente. Permite popups para este sitio.');
			return;
		}

		// URL absoluta para evitar cualquier problema con <base href> o resolución relativa
		var loginUrl = location.protocol + '//' + location.host + '/login.php?action=process';

		var form = document.createElement('form');
		form.setAttribute('method', 'post');
		form.setAttribute('action', loginUrl);
		form.setAttribute('target', winName);

		var inputEmail = document.createElement('input');
		inputEmail.type = 'hidden';
		inputEmail.name = 'email_address';
		inputEmail.value = email;
		form.appendChild(inputEmail);

		var inputPass = document.createElement('input');
		inputPass.type = 'hidden';
		inputPass.name = 'password';
		inputPass.value = pass;
		form.appendChild(inputPass);

		var inputModo = document.createElement('input');
		inputModo.type = 'hidden';
		inputModo.name = 'modo';
		inputModo.value = 'login';
		form.appendChild(inputModo);

		// Flag para que login.php ignore el snapshot de navegación y fuerce
		// logoff previo si había otra sesión abierta
		var inputMast = document.createElement('input');
		inputMast.type = 'hidden';
		inputMast.name = 'mast_connect';
		inputMast.value = '1';
		form.appendChild(inputMast);
		document.body.appendChild(form);
		form.submit();
		setTimeout(function() {
			if (form.parentNode) form.parentNode.removeChild(form);
		}, 2000);
	}

	// Eliminar dirección
	$(".dlet").click(function(e)
	{
		e.stopPropagation();

		if( confirm("¿Realmente deseas borrar el cliente?") )
			return true;

		return false;
	});

	// Añadir nota al usuario
	$("#add-customers-note-sbmt").click(function(e)
	{
		var aDatos = {
			customers_notes: $("#customers_notes").val(),
			id_customers_notes_status: $("#id_customers_notes_status").val(),
			customers_id: $("#customers_id").val()
		};

		$("#dxbg").fadeIn(400, function()
		{
			$("#dxload").fadeIn(400);
			$("#sResults_msj").css("display", "none");

			$.ajax({
				type: "POST",
				url: "customers.php?action=add_customers_note",
				data: aDatos
			}).done(function(sHtml)
			{
				$("#dxbg").fadeOut();
				$("#dxload").fadeOut();

				$( "#list-customers-notes" ).prepend( sHtml );
				$("#customers_notes").val("");

				eventDeleteNote();
			});
		});
	});

	// Inicio, eliminar nota al usuario
	function eventDeleteNote()
	{
		// Eliminamos los posibles eventos
		$("#list-customers-notes li").unbind();
		$("#list-customers-notes li .icos-cross").unbind();

		$("#list-customers-notes li").hover(
			function()
			{
				$(this).find(".icos-cross").css("display", "block");
			},
			function()
			{
				$(this).find(".icos-cross").css("display", "none");
			}
		);

		$("#list-customers-notes li .icos-cross").click(function()
		{
			if( confirm( "¿Realmente deseas eliminar la nota?" ) )
			{
				$.ajax({
					type: "POST",
					url: "customers.php?action=delete_customers_note&customers_id=" + $(this).data("customersid") + "&id=" + $(this).data("id")
				});

				$(this).parent().remove();

				if( $("#list-customers-notes li").length <= 0 )
					$("#sResults_msj").css("display", "block");
			}
		})
	}

	eventDeleteNote();
	// Fin, eliminar nota al usuario

	// Guardar usuario via ajax
	$("#save_return").click(function(e)
	{
		var dmForm = $(this).closest("form");

		$("#dxbg").fadeIn(400, function()
		{
			$("#dxload").fadeIn(400);

			$.ajax({
				type: "POST",
				url: dmForm.attr("action"),
				data: dmForm.serialize()
			}).done(function(sHtml)
			{
				$("#dxbg").fadeOut();
				$("#dxload").fadeOut();

				var dmElement = $('<div/>', {html: sHtml}).insertAfter($(".toolbarHead").parent());

				setTimeout(function()
				{
					dmElement.fadeOut( 700, function(){ $(this).remove; } );
				},3200 );
			});
		});
	});

	// Eliminar dirección
	$(".delete_direccion").click(function(e)
	{
		e.stopPropagation();

		if( confirm("¿Realmente deseas borrar la dirección?") )
		{
			$(this).closest(".fluid").remove();

			$.ajax({
				url: "customers.php?action=confirmaddressdelete&cID=" + $(this).data("cid") + "&add_id=" + $(this).data('add_id')
			});
		}
	});

	// Combobox de pais muestre las provincias
	$("#tab-dmcl select[name*='entry_country_id']").change(function()
	{
		var dmElement = $(this).closest(".formRow").find(".entry_zone_id");
		var sId = dmElement.closest(".box-tbl").find(".box-head h6 span").data("id");

		$.ajax({
			url: "customers.php?action=ajax_get_zones_html&country=" + $(this).val()
		}).done(function(sHtml)
		{
			dmElement.html(sHtml);
			dmElement.find("select").uniform();

			if( dmElement.find("select").length > 0 )
				dmElement.find("select").attr( "name", dmElement.find("select").attr("name") + "[" + sId + "]" );
			else
				dmElement.find("input").attr( "name", dmElement.find("input").attr("name") + "[" + sId + "]" );
		});
	});

	//Carga de ciudades a partir de Provincia
	$( ".entry_zone_id" ).on( "change", "select", function() {
		var dmElement = $(this).closest(".formRow").find(".entry_city_id");
		var country = $(this).closest(".formRow").find(".entry_country_id select").val();
		var sId = dmElement.closest(".box-tbl").find(".box-head h6 span").data("id")
		var name = "entry_city_id[" + sId + "]"
		zone = $(this).val()
		dmElement.html('')
		$.get('customers.php', {action: 'ajax_get_cities_html', zone: zone, name: name, country: country}, function(data) {
			data = $.parseJSON( data );
			dmElement.html(data.cities)
			dmElement.find("select").uniform()
		})
	});

	//Carga de ciudades a partir de codigo postal
	$( ".entry_postcode" ).on( "change", "input", function() {
		var dmElement = $(this).closest(".box-tbl").find(".entry_city_id");
		var postcode = $(this).val();
		var country = $(this).closest(".box-tbl").find(".entry_country_id select").val();
		$.get('customers.php', {action: 'ajax_get_cities_html', cp: postcode, country: country}, function(data) {
			data = $.parseJSON( data );
			dmElement.html(data.cities)
			dmElement.find("select").uniform()
		})
	});

	//Obtenemos el cp seleccionado para autorrelenarlo
	$( ".entry_city_id" ).on( "change", "select", function() {
		city = $(this).find('option:selected').text();
		postcode = city.match(/\[(.*?)\]/)
		$(this).closest(".box-tbl").find(".entry_postcode input").val(postcode[1]);
	});

	// Doble click en un registro de la tabla lo edita directamente
	$(".dbclick").dblclick(function()
	{
		document.location.href = "<?php echo tep_href_link( FILENAME_CUSTOMERS, tep_get_all_get_params( array( 'cID', 'action') ) ); ?>&cID=" + $(this).data("id") + "&action=edit";
	});

	// Calendario
	$(".datepicker").datepicker();

	// Al redimensionar la pantalla
	$( window ).resize(function()
	{
		// Poisicionamos el pie del admin
		$("#box-right").attr( "style", "" );

		if( $("#box-right").height() < $("#box-left").height() )
			$("#box-right").height( $("#box-left").height() - 345 );
	});

	$( window ).trigger("resize");

	// Calendario en español
	$.datepicker.regional['es'] =
	{
		closeText: 'Cerrar',
		prevText: '<Ant',
		nextText: 'Sig>',
		currentText: 'Hoy',
		monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
		monthNamesShort: ['Ene','Feb','Mar','Abr', 'May','Jun','Jul','Ago','Sep', 'Oct','Nov','Dic'],
		dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
		dayNamesShort: ['Dom','Lun','Mar','Mié','Juv','Vie','Sáb'],
		dayNamesMin: ['Do','Lu','Ma','Mi','Ju','Vi','Sá'],
		weekHeader: 'Sm',
		dateFormat: 'dd/mm/yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''
	};
	$.datepicker.setDefaults($.datepicker.regional['es']);

	// Seleccionar/Deseleccionar todos
	$("#checkAll").on("click", function() {
		$(".chkItem").prop("checked", $(this).prop("checked"));
	});

	// Confirmación al aplicar acción masiva
	$("#massActionsForm").on("submit", function(e) {
		if ($("#mass_action").val() == "delete") {
			if (!confirm("¿Seguro que quieres eliminar los clientes seleccionados?")) {
				e.preventDefault();
				return false;
			}
		}
	});

</script>

<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
