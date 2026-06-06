<?php

require( DIR_WS_LANGUAGES . $language . '/' . FILENAME_RMA );
//error_reporting(E_ALL);
//ini_set('display_errors', '1');

class rma {

    public $ordersID;
    public $productsID;
    private $customerID;
    public $Antiguedad = false;
    private $Valid = false;
    private $languagesID;
    public $optionsReturn;
    private $optionsReturnValidsID;
    public $Product;
    public $Addresses = array();
    public $typesReturn = array();
    public $paymentMethodDefault;
    public $paymentMethods;
    private $validFields = array('option_return', 'quantity','comments', 'type_return', 'address', 'address_return', 'date_return', 'schedule_return', 'payment_method', 'payment_method_default', 'cex_metodo');
    // type_return que activa las opciones de recogida Correos Express ("Envío a cargo de Francobordo")
    const CEX_TYPE_RETURN_ID = 2;
    public $idRma;
    public $idRmaInt = 0;
    private $orderStatus;
    private $orderStatusAccepted = array();
    private $textNoReturn;
    private $totalOptionsReturnActive = 0;
    public $Exists = false;
    public $historyStatus = array();
    private $formatDate = '%d/%m/%Y %h:%m:%s';

    function __construct($ordersID, $productsID = false, $forceCustomerID = false) {
        global $customer_id, $languages_id;

        $this->ordersID = (int)$ordersID;
        $this->productsID = (int)$productsID;
        $this->customerID = ($forceCustomerID ? $forceCustomerID : $customer_id);
        $this->languagesID = (int)$languages_id;
        $this->orderStatusAccepted = array_map('intval', explode(',', RMA_ORDER_STATUS_ID));
        if ($this->languagesID <> 3) {
            $this->formatDate = '%Y-%m-%d %h:%m:%s';
        }

        //Comprobamos que el pedido pertenezca al cliente
        $this->checkOrderCustomer();
        $this->getHistoryStatus();

        if ($this->Antiguedad !== false) {
            $this->checkOptionsReturns();
            if ($this->totalOptionsReturnActive > 0 && $this->checkOrderStatus() && !$this->Exists) {
                $this->Valid = true;
                $this->getOrderProduct();
                $this->getAdressCustomer();
                $this->checkTypesReturns();
                $this->checkPaymentMehtods();
            }
        }

    }

    /**
     *
     * Obtiene el producto del pedido del cliente
     *
     */
    private function getOrderProduct() {
        if ($this->productsID > 0) {
            $sSql = "SELECT products_name, products_price, products_quantity  FROM " . TABLE_ORDERS_PRODUCTS . " WHERE products_id = '" . $this->productsID . "' and orders_id = '" . $this->ordersID . "'";
            $aDatos = tep_db_query($sSql);
            if (tep_db_num_rows($aDatos)) {
                $this->Product = tep_db_fetch_array( $aDatos );
            }
        }
    }

    /*
    *
    * Obtenemos las direcciones del cliente
    *
    */
    private function getAdressCustomer() {
        $sSql = "SELECT ab.address_book_id, CONCAT(ab.entry_firstname,' ' , ab.entry_lastname,', ' , ab.entry_street_address,', ' , ab.entry_city,' (', ab.entry_postcode, '), ' , ab.entry_state,', ' , c.countries_name,' ' ) as address
        FROM " . TABLE_ADDRESS_BOOK . " ab
        LEFT JOIN " . TABLE_COUNTRIES . " c ON c.countries_id = ab.entry_country_id
        WHERE ab.customers_id = '" . $this->customerID . "'";
        $aDatos = tep_db_query($sSql);
        $this->Addresses[] = array('address_book_id' => '', 'address' => RMA_SELECT_ADDRESS);
        if (tep_db_num_rows($aDatos)) {
            $this->Addresses[] = tep_db_fetch_array( $aDatos );
        }
    }

    /*
    *
    * Checkeamos que el pedido cumple las condiciones para que pueda ser devuelto
    *
    */

    private function checkOrderStatus() {
        $salida = in_array($this->orderStatus, $this->orderStatusAccepted);

        if (!$salida) {
            $this->Valid = false;
            $this->textNoReturn = 'El estado de su pedido no le permite hacer una devolución';
        }

        return $salida;
    }

    /*
    *
    * Obtenemos el historial de estados
    *
    */

    private function getHistoryStatus() {
        $sSql = 'SELECT rsh.message, rs.color, rsd.text as status, DATE_FORMAT(rsh.date_added, "'.$this->formatDate.'") as date
        FROM ' . TABLE_RMA_STATUS_HISTORY . ' rsh
        LEFT JOIN ' . TABLE_RMA_STATUS .' rs ON rs.id = rsh.id_status
        LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION .' rsd ON rsd.id_status = rsh.id_status AND rsd.languages_id = "' . $this->languagesID . '"
        WHERE rsh.id_rma = "' . $this->idRma . '" ORDER BY rsh.id DESC';
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            while ($aHistoryStatus = tep_db_fetch_array( $aSql )) {
                $this->historyStatus[] = $aHistoryStatus;
            }
        }

    }

    /*
    *
    * Checkeamos que el pedido es correcto y cumple las condiciones
    *
    */

    private function checkOrderCustomer() {
        $sSql = "SELECT orders_status, date_purchased, payment_method FROM " . TABLE_ORDERS . " WHERE customers_id = '" . $this->customerID . "' and orders_id = '" . $this->ordersID . "'";
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
			// Registro
            $aOrder = tep_db_fetch_array( $aSql );

			// Obtenemos fecha de entrega
			$aReceived = tep_db_query( 'SELECT date_added FROM orders_status_history WHERE orders_id = "' . $this->ordersID . '" AND orders_status_id = "3"' );

			// Si SI tenemos fecha de entrega
			if( tep_db_num_rows( $aReceived ) > 0 )
			{
				// Registro
				$aReceived = tep_db_fetch_array( $aReceived );

				// Antiguedad
				$dCurrentDate = new DateTime();
				$dDatePurchased = new DateTime($aReceived['date_added']);
				$dAntiguedad = $dCurrentDate->diff($dDatePurchased);
			}
			// Si NO, fecha de compra
			else
			{
				// Antiguedad
				$dCurrentDate = new DateTime();
				$dDatePurchased = new DateTime($aOrder['date_purchased']);
				$dAntiguedad = $dCurrentDate->diff($dDatePurchased);
			}

            $this->Antiguedad = $dAntiguedad->days;
            $this->orderStatus = intval($aOrder['orders_status']);
            $this->paymentMethodDefault = $aOrder['payment_method'];
        } else {
            $this->Antiguedad = false;
        }



        $sSql = 'SELECT r.id_rma, rsd.text as status, rs.color FROM ' . TABLE_RMA . ' r
        LEFT JOIN ' . TABLE_RMA_STATUS .' rs ON rs.id = r.status
        LEFT JOIN ' . TABLE_RMA_STATUS_DESCRIPTION .' rsd ON rsd.id_status = r.status AND rsd.languages_id = "' . $this->languagesID . '"
        WHERE r.customers_id = "' . $this->customerID . '" and r.orders_id = "' . $this->ordersID . '" AND r.products_id = "' .$this->productsID.'"';
        $aSql = tep_db_query($sSql);
        if (tep_db_num_rows($aSql)) {
            $aRma = tep_db_fetch_array( $aSql );
            $this->idRma = str_pad($aRma['id_rma'], 10, "0", STR_PAD_LEFT);
            $this->textNoReturn = sprintf(RMA_PROCESSING, $aRma['color'], $aRma['status'], $this->idRma);
            $this->Exists = true;
        }

    }

    /*
    *
    * Obtenemos las opciones de devolcuión dependiendo de la antiguedad del pedido
    *
    */

    private function checkOptionsReturns() {
        if ( $this->Antiguedad !== false ) {
            $sSql = 'SELECT ro.plazo_maximo, ro.id, rod.text, IF(ro.plazo_maximo >= ' . $this->Antiguedad .', 1, 0) as active FROM '.TABLE_RMA_OPTIONS.' ro LEFT JOIN '.TABLE_RMA_OPTIONS_DESCRIPTION.' rod on ro.id = rod.id_rma WHERE rod.languages_id = ' . $this->languagesID .' AND ro.active = 1 ORDER BY active DESC';
            $aSql = tep_db_query($sSql);
            if (tep_db_num_rows($aSql)) {
                while ($aOptionReturn = tep_db_fetch_array( $aSql )) {
                    $this->optionsReturn[] = $aOptionReturn;
                    if ((int)$aOptionReturn['active'] == 1) {
                        $this->totalOptionsReturnActive++;
                        $this->optionsReturnValidsID[] = $aOptionReturn['id'];
                    }

                }
            }
        }
    }

    /*
    *
    * Obtenemos los tipos de retornos disponibles
    *
    */

    private function checkTypesReturns() {
        if ( $this->Antiguedad !== false ) {
            $sSql = 'SELECT tr.agencia, tr.id, tr.price_cost, trd.text FROM '.TABLE_RMA_TYPES_RETURN.' tr LEFT JOIN '.TABLE_RMA_TYPES_RETURN_DESCRIPTION.' trd on tr.id = trd.id_type_return WHERE trd.languages_id = ' . $this->languagesID .' AND tr.active = 1 ORDER BY tr.price_cost DESC';
            $aSql = tep_db_query($sSql);
            if (tep_db_num_rows($aSql)) {
                while ($aOptionReturn = tep_db_fetch_array( $aSql )) {
                    $this->typesReturn[] = $aOptionReturn;
                }
            }
        }
    }

    /*
    *
    * Obtenemos las formas de devolución disponibles
    *
    */

    private function checkPaymentMehtods() {
        if ( $this->Antiguedad !== false ) {
            $sSql = 'SELECT pm.is_address, pm.id, pmd.text FROM '.TABLE_RMA_PAYMENT_METHODS.' pm LEFT JOIN '.TABLE_RMA_PAYMENT_METHODS_DESCRIPTION.' pmd on pm.id = pmd.id_payment_method WHERE pmd.languages_id = ' . $this->languagesID .' AND pm.active = 1';
            $aSql = tep_db_query($sSql);
            if (tep_db_num_rows($aSql)) {
                while ($aPaymentMethod = tep_db_fetch_array( $aSql )) {
                    $this->paymentMethods[] = $aPaymentMethod;
                }
            }
        }
    }

    /*
    *
    * Mostramos un botón o un texto de información por cada producto
    * @return hml
    *
    */

    public function showReturnButton() {
        if ($this->textNoReturn) {
            // "Ver estado" → página dedicada (no modal). El modal queda solo para el wizard de creación.
            return '<br /><small style="font-size: 0.8em; opacity: .8;">' . $this->textNoReturn . '</small>' .(!empty($this->historyStatus) ? '<br /><a href="'.tep_href_link('account/rma_status.php','orders_id=' . $this->ordersID.'&products_id=' . $this->productsID, 'SSL').'" class="Button" style="background-color: #ee7f00;">'.RMA_VIEW_STATUS.'</a>' : '');
        }
        if ($this->Valid && $this->productsID > 0) {
            return '<br /><a href="'.tep_href_link('rma.php','orders_id=' . $this->ordersID.'&products_id=' . $this->productsID).'" class="Button initRma" style="background-color: #ee7f00;">'.RMA_RETURN.'</a>';
        }
    }

    /*
    *
    * Mostramos un checkbox para la selección múltiple
    * @return html
    *
    */

    public function showReturnCheckbox() {
        if ($this->Valid && $this->productsID > 0 && !$this->textNoReturn) {
            return tep_draw_checkbox_field('rma['.$this->productsID.']', $this->productsID, false, ' class="selectedRma" ');
        }
    }

    /*
    *
    * Devolvemos si es válido
    * @return boolean
    *
    */

    public function isValid() {
        return $this->Valid;
    }

    /*
    *
    * Calculamos la fecha que expiró las opciones de devolución
    * @return text
    *
    */

    public function calculateExpiration($nDays) {
        if ($nDays > 0) {
            $dCurrentDate = new DateTime();
            $dCurrentDate->modify('-'.$nDays.' days');
            $salida = $dCurrentDate->format(RMA_FORMAT_DATE);

            return sprintf(RMA_EXPIRATION, $salida);
        }

    }

    /*
    *
    * Obtenemos la imagen del producto
    * @return html
    *
    */

    public function getImageProduct() {
        if ($this->productsID > 0) {
            $sSql = 'SELECT pd.products_name, p.products_image FROM ' . TABLE_PRODUCTS .' p LEFT JOIN '.TABLE_PRODUCTS_DESCRIPTION.' pd ON p.products_id = pd.products_id WHERE p.products_id = ' . $this->productsID .' AND pd.language_id = ' . $this->languagesID;
            $aDatos = tep_db_query($sSql);
            $Producto = tep_db_fetch_array( $aDatos );
            return tep_image(DIR_WS_IMAGES . 'productos/' .$Producto['products_image'], $Producto['products_name'], 250, 250, '', false, false, false );
        }

        return false;

    }

    /*
    *
    * Obtenemos todos los campos enviados por post y los mostramos en format de input[type=hidden]
    * @return html
    *
    */

    /** CP de entrega del pedido (para buscar oficinas Correos Express cercanas). */
    public function getDeliveryPostcode() {
        $q = tep_db_query("SELECT delivery_postcode, customers_postcode FROM " . TABLE_ORDERS .
            " WHERE orders_id = '" . (int) $this->ordersID . "' AND customers_id = '" . (int) $this->customerID . "'");
        if (tep_db_num_rows($q)) {
            $r = tep_db_fetch_array($q);
            $cp = trim($r['delivery_postcode']) !== '' ? trim($r['delivery_postcode']) : trim($r['customers_postcode']);
            return preg_replace('/\s+/', '', $cp);
        }
        return '';
    }

    public function getFieldsForm($aFields = array()) {
        $excludeFields = array('step');
        foreach($aFields as $key => $value):
            if (!in_array($key, $excludeFields)): ?>
            <input type="hidden" name="<?php echo $key; ?>" value="<?php echo tep_db_prepare_input($value); ?>" />
        <?php endif;
        endforeach;
    }
    /*

    Añadimos los datos de facturación

    */
   private function addAddressInfo($aDatos) {
       $sSql = "SELECT
       o.customers_name, o.customers_company, o.customers_street_address, o.customers_suburb, o.customers_city, o.customers_postcode, o.customers_state, o.customers_country,
       o.delivery_name, o.delivery_company, o.delivery_street_address, o.delivery_suburb, o.delivery_city, o.delivery_postcode, o.delivery_state, o.delivery_country,
       o.billing_name, o.billing_company, o.billing_nif, o.billing_street_address, o.billing_suburb, o.billing_city, o.billing_postcode, o.billing_state, o.billing_country,
       c.customers_email_address, c.customers_telephone, c.customers_fax
       FROM " . TABLE_ORDERS . " o
       LEFT JOIN " . TABLE_CUSTOMERS. " c ON c.customers_id = o.customers_id
       WHERE o.customers_id = '" . $this->customerID . "' and o.orders_id = '" . $this->ordersID . "'";
       //mail('daniellucia84@gmail.com', 'query', $sSql);
       $aSql = tep_db_query($sSql);
       if (tep_db_num_rows($aSql)) {
           $aOrder = tep_db_fetch_array( $aSql );
           $aDatos = array_merge($aDatos, $aOrder);
       }

       if (intval($aDatos['address_return']) > 0) {
           $sSql = "SELECT
           CONCAT(ab.entry_firstname, ' ', ab.entry_lastname) as delivery_return_name,
           ab.entry_street_address as delivery_return_street_address,
           ab.entry_city as delivery_return_city,
           ab.entry_postcode as delivery_return_postcode,
           ab.entry_state as delivery_return_state,
           ab.entry_company as delivery_return_company,
           co.countries_name as delivery_return_country
           FROM " . TABLE_CUSTOMERS . " c
           LEFT JOIN " . TABLE_ADDRESS_BOOK . " ab ON c.customers_id = ab.customers_id
           LEFT JOIN " . TABLE_COUNTRIES . " co ON co.countries_id = ab.entry_country_id
           WHERE ab.address_book_id = '" . intval($aDatos['address_return']) . "'";
           $aAddressReturn = array();
           $aSql = tep_db_query($sSql);
           if (tep_db_num_rows($aSql)) {
               $aAddressReturn = tep_db_fetch_array( $aSql );
           }
           $aDatos = array_merge($aDatos, $aAddressReturn);
       }

       unset($aDatos['address']);
       unset($aDatos['address_return']);
       return $aDatos;
   }

    /*
    *
    * Procesamos los datos y guardamos en base de datos
    *
    */
    public function rmaProcess($aFields = array()) {
        if (empty($aFields)) {
            return;
        }
        $aDatos = array();
        $aDatos['products_id'] = $this->productsID;
        $aDatos['orders_id'] = $this->ordersID;
        $aDatos['customers_id'] = $this->customerID;
        $aDatos['status'] = RMA_DEFAULT_ID;
        $aDatos['date_added'] = 'now()';
        $aDatos['date_modify'] = 'now()';
        $aDatos['languages_id'] = $this->languagesID;
        if (intval($aFields['payment_method']) > 0) {
            $aDatos['payment_method_default'] = $this->paymentMethodDefault;
        }

        // Correos Express: si eligió "Envío a cargo de Francobordo" + recogida a
        // domicilio, trasladamos su fecha/franja a los campos estándar date_return /
        // schedule_return (el operador los verá precargados en el admin). El operador
        // confirma y genera la recogida/etiqueta real; aquí solo se registra la elección.
        if (intval($aFields['type_return'] ?? 0) === self::CEX_TYPE_RETURN_ID
            && ($aFields['cex_metodo'] ?? '') === 'domicilio') {
            if (!empty($aFields['cex_fecha']))  $aFields['date_return']     = $aFields['cex_fecha'];
            if (!empty($aFields['cex_franja'])) $aFields['schedule_return'] = intval($aFields['cex_franja']);
        }

        foreach ($aFields as $key => $value) {
            if (in_array($key, $this->validFields)) {
                $aDatos[$key] = $value;
            }
        }

        $aDatos = $this->addAddressInfo($aDatos);

        // Guardar email/nombre antes de reset (los necesita sendConfirmationEmail)
        $customerEmail = trim((string) ($aDatos['customers_email_address'] ?? ''));
        $customerName  = trim((string) ($aDatos['customers_name'] ?? ''));

        tep_db_perform(TABLE_RMA, $aDatos);

        $this->idRmaInt = (int) tep_db_insert_id();
        $this->idRma    = str_pad($this->idRmaInt, 10, "0", STR_PAD_LEFT);

        $aDatos = array();
        $aDatos['id_rma'] = $this->idRmaInt;
        $aDatos['id_status'] = RMA_DEFAULT_ID;
        $aDatos['date_added'] = 'now()';
        tep_db_perform(TABLE_RMA_STATUS_HISTORY, $aDatos);
        $this->getHistoryStatus();

        // Mover los archivos adjuntos (si los hay) de _tmp a la carpeta definitiva del RMA
        if (!empty($aFields['rma_upload_token'])) {
            $this->finalizeUploads($aFields['rma_upload_token'], $this->idRmaInt);
        }

        // Confirmación al cliente (bilingüe ES/EN según languages_id)
        $this->sendConfirmationEmail($customerEmail, $customerName);
    }

    /**
     * Envía email de confirmación al cliente tras grabar la solicitud de RMA.
     * Usa el layout UHtmlEmails/{LAYOUT}/rma.php (mismo que rmaChangeStatus).
     * Bilingüe ES/EN según languages.code del languages_id del cliente.
     */
    private function sendConfirmationEmail($customerEmail, $customerName) {
        if ($customerEmail === '' || $customerName === '' || !$this->idRmaInt) {
            return;
        }

        // Detectar idioma del cliente
        $r = tep_db_query("SELECT code, directory FROM languages WHERE languages_id = " . (int) $this->languagesID . " LIMIT 1");
        $row = tep_db_fetch_array($r);
        $langCode = (isset($row['code']) && $row['code'] !== '') ? strtolower($row['code']) : 'es';
        $langDir  = (isset($row['directory']) && $row['directory'] !== '') ? $row['directory'] : 'espanol';

        // Primer nombre (lo pidió el usuario: "Hola Jaime" en lugar de nombre completo)
        $firstName = trim(strtok($customerName, ' '));
        if ($firstName === '' || $firstName === false) $firstName = $customerName;

        // Link directo a la página de estado del RMA (no al historial del pedido)
        $url   = 'https://www.francobordo.com/account/rma_status.php?orders_id=' . (int) $this->ordersID . '&products_id=' . (int) $this->productsID;
        $rmaId = (int) $this->idRmaInt;
        $hName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');

        if ($langCode === 'en') {
            $subject = 'RMA request ' . $rmaId . ' registered - Francobordo';
            $customersName = 'Hello ' . $hName;
            $message  = 'We confirm your request has been registered correctly. '
                      . 'The case number is <strong>' . $rmaId . '</strong>.<br/><br/>'
                      . 'From now on we will start our review process and, within the next 2 or 3 days, '
                      . 'we will inform you by email about the progress.<br/><br/>'
                      . 'You can also check all this information in your customer area:<br/>'
                      . '<a href="' . $url . '">' . $url . '</a><br/><br/>'
                      . 'Thank you very much for your trust in Francobordo!';
        } else {
            $subject = 'Solicitud RMA ' . $rmaId . ' registrada - Francobordo';
            $customersName = 'Hola ' . $hName;
            $message  = 'Te confirmamos que tu solicitud está registrada correctamente. '
                      . 'El número de esta incidencia es <strong>' . $rmaId . '</strong>.<br/><br/>'
                      . 'A partir de ahora, iniciaremos nuestro proceso de revisión de la misma y, '
                      . 'en los próximos 2 o 3 días, te informaremos por email sobre los avances.<br/><br/>'
                      . 'También puedes consultar toda esta información en tu área de cliente:<br/>'
                      . '<a href="' . $url . '">' . $url . '</a><br/><br/>'
                      . '¡Muchas gracias por tu confianza en Francobordo!';
        }

        // Asegurar EMAIL_POLITICA y constantes generales del idioma del cliente
        // (el frontend ya carga la del cliente; rmaChangeStatus hardcodeaba 'espanol').
        $langGeneral = DIR_WS_LANGUAGES . $langDir . '.php';
        if (is_file($langGeneral)) {
            require_once($langGeneral);
        }

        // El template UHtmlEmails/{LAYOUT}/rma.php renderiza $message a 14px (heredado
        // del contenedor). Lo envolvemos para subirlo a 16px sólo en este correo —
        // no tocamos el template para no afectar a rmaChangeStatus.
        $message = '<span style="font-size:16px; line-height:1.55;">' . $message . '</span>';

        // Renderiza $sHtmlEmail usando el layout existente (mismo que cambio de estado).
        // En frontend usamos DIR_FS_CATALOG + DIR_WS_MODULES (estándar osCommerce).
        require(DIR_FS_CATALOG . DIR_WS_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/rma.php');

        @tep_mail($customerName, $customerEmail, $subject, $sHtmlEmail, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
    }

    public function checkOptionReturn() {
        return in_array(intval($_POST['option_return']), $this->optionsReturnValidsID);
    }

    // ---------- Adjuntos del cliente al crear el RMA ----------
    public static function getAllowedMimes() {
        return ['image/jpeg','image/png','image/gif','image/webp','image/heic','image/heif','application/pdf'];
    }
    public static function getAllowedExtensions() {
        return ['jpg','jpeg','png','gif','webp','heic','heif','pdf'];
    }
    public static function getMaxFileSizeBytes() { return 5 * 1024 * 1024; }   // 5 MB
    public static function getMaxFiles()         { return 5; }

    /**
     * Procesa $_FILES['attachments'] (input file multiple) y guarda los archivos
     * válidos en images/rma/_tmp/{token}/. Devuelve el token o '' si ninguno fue válido.
     * El token se pasa por hidden field hasta el step final, donde finalizeUploads()
     * los mueve a images/rma/{id_rma}/ y los registra en rma_attachments.
     */
    public function processUploads() {
        if (empty($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) return '';
        $files = $_FILES['attachments'];
        $count = min(count($files['name']), self::getMaxFiles());
        $baseDir = DIR_FS_CATALOG . 'images/rma/_tmp';
        if (!is_dir($baseDir)) @mkdir($baseDir, 0755, true);

        $token = bin2hex(random_bytes(16));
        $dir = $baseDir . '/' . $token;
        if (!@mkdir($dir, 0755, true)) return '';

        $valid = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ((int) $files['size'][$i] <= 0 || (int) $files['size'][$i] > self::getMaxFileSizeBytes()) continue;
            $orig = basename($files['name'][$i]);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, self::getAllowedExtensions(), true)) continue;
            // Verificar mime real (no solo el reportado por el cliente)
            $realMime = @mime_content_type($files['tmp_name'][$i]) ?: $files['type'][$i];
            if (!in_array($realMime, self::getAllowedMimes(), true)) continue;

            $stored = sprintf('%02d_%s.%s', $i, bin2hex(random_bytes(8)), $ext);
            $target = $dir . '/' . $stored;
            if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                @chmod($target, 0644);
                file_put_contents($target . '.meta', json_encode([
                    'original' => substr($orig, 0, 255),
                    'mime'     => $realMime,
                    'size'     => (int) $files['size'][$i],
                ], JSON_UNESCAPED_UNICODE));
                $valid++;
            }
        }
        if ($valid === 0) { @rmdir($dir); return ''; }
        return $token;
    }

    /**
     * Mueve los archivos de images/rma/_tmp/{token}/ a images/rma/{id_rma}/
     * y los registra en la tabla rma_attachments. Token se sanea para evitar path traversal.
     */
    public function finalizeUploads($token, $idRmaInt) {
        if (!$token || !$idRmaInt) return;
        $token = preg_replace('/[^a-f0-9]/i', '', (string) $token);
        if ($token === '') return;
        $srcDir = DIR_FS_CATALOG . 'images/rma/_tmp/' . $token;
        if (!is_dir($srcDir)) return;
        $dstDir = DIR_FS_CATALOG . 'images/rma/' . (int) $idRmaInt;
        if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);

        foreach (scandir($srcDir) as $f) {
            if ($f === '.' || $f === '..' || substr($f, -5) === '.meta') continue;
            $srcFile = $srcDir . '/' . $f;
            if (!is_file($srcFile)) continue;
            $dstFile = $dstDir . '/' . $f;

            $meta = ['original' => $f, 'mime' => @mime_content_type($srcFile) ?: 'application/octet-stream', 'size' => (int) filesize($srcFile)];
            if (is_file($srcFile . '.meta')) {
                $m = json_decode(@file_get_contents($srcFile . '.meta'), true);
                if (is_array($m)) $meta = array_merge($meta, $m);
                @unlink($srcFile . '.meta');
            }
            if (@rename($srcFile, $dstFile)) {
                tep_db_perform('rma_attachments', [
                    'id_rma'            => (int) $idRmaInt,
                    'filename_original' => substr((string) $meta['original'], 0, 255),
                    'filename_stored'   => substr($f, 0, 96),
                    'mime_type'         => substr((string) $meta['mime'], 0, 64),
                    'size_bytes'        => (int) $meta['size'],
                    'date_added'        => 'now()',
                ]);
            }
        }
        @rmdir($srcDir);
    }

    public function showReembolso($id_return) {
        $sSql = "SELECT muestra_reembolso FROM " . TABLE_RMA_OPTIONS . " WHERE id = '" . intval($id_return) . "'";
        $aDatos = tep_db_query($sSql);
        if (tep_db_num_rows($aDatos)) {
            $aDato = tep_db_fetch_array( $aDatos );
            return boolval($aDato['muestra_reembolso']);
        }

        return false;
    }
}
