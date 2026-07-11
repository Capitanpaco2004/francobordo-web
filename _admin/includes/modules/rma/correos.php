<?php
/**
 * Integración Correos (de España) en el módulo RMA del admin.
 *
 * Flujo: etiqueta de devolución (Preregister PAAZE/DOURUA + Labels PDF) que el
 * cliente deposita en CUALQUIER oficina de Correos. No hay recogida a domicilio
 * (la API "requests" no está suscrita en el Portal de Desarrolladores).
 *
 * Acciones (añadidas al switch de rmaSection en functions.php):
 *   - correos-label-gen   : preregistra la devolución + genera etiqueta PDF
 *   - correos-label       : descarga el PDF de etiqueta almacenado
 *   - correos-email-label : envía el PDF de etiqueta por email al cliente
 *   - correos-cancel      : anula el preregistro (annulment por packageCode)
 *
 * Render de la caja del operador: rmaCorreosRenderBox($rmaDetail) — desde views/view.php.
 *
 * Entorno: SIEMPRE 'pro' (la app del Portal de PRUEBAS solo tiene preregister,
 * Labels rechazaría sus cabeceras; el preregistro es anulable y no se factura
 * hasta que el paquete entra en la red). Sin interruptor test/pro.
 *
 * Doc/credenciales/gotchas: memoria francobordo_correos_api.
 */

require_once DIR_FS_CATALOG . 'includes/classes/correos.php';

/* ====================================================================== *
 *  Helpers                                                               *
 * ====================================================================== */

/** Fila cruda de rma (para construir la petición a Correos). */
function correosRmaGet($id_rma) {
    $q = tep_db_query('SELECT * FROM ' . TABLE_RMA . ' WHERE id_rma = ' . (int) $id_rma);
    return tep_db_fetch_array($q);
}

/** Envíos Correos de un RMA, más reciente primero. */
function correosShipmentsFor($id_rma) {
    $rows = array();
    $q = tep_db_query('SELECT * FROM correos_shipments WHERE id_rma = ' . (int) $id_rma . ' ORDER BY id DESC');
    while ($r = tep_db_fetch_array($q)) $rows[] = $r;
    return $rows;
}

/** Identifica al operador logueado (best-effort, solo para traza). */
function correosOperator() {
    foreach (array('login_email_address', 'login_id', 'login_first_name') as $k) {
        if (!empty($_SESSION[$k])) return (string) $_SESSION[$k];
    }
    return '';
}

/** Anota una nota privada en el histórico del RMA (sin cambiar de estado ni notificar). */
function correosNote($id_rma, $texto) {
    $rma = correosRmaGet($id_rma);
    $status = $rma ? (int) $rma['status'] : 0;
    tep_db_perform(TABLE_RMA_STATUS_HISTORY, array(
        'email_text'      => '',
        'notify'          => 0,
        'id_rma'          => (int) $id_rma,
        'id_status'       => $status,
        'message'         => '',
        'private_message' => $texto,
        'date_added'      => 'now()',
    ));
}

/** Guarda el PDF (binario) bajo images/rma/{id}/ con nombre aleatorio. Devuelve ruta relativa o null. */
function correosSaveLabelBin($id_rma, $pdfBin) {
    if (!is_string($pdfBin) || strncmp($pdfBin, '%PDF', 4) !== 0) return null;
    $dir = DIR_FS_CATALOG . 'images/rma/' . (int) $id_rma . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = 'correos_label_' . (int) $id_rma . '_' . substr(md5($pdfBin . microtime(true)), 0, 10) . '.pdf';
    if (@file_put_contents($dir . $name, $pdfBin) === false) return null;
    return (int) $id_rma . '/' . $name; // relativa bajo images/rma/
}

/**
 * Estampa con Ghostscript (petición usuario 07-10) el nº de RMA en la etiqueta y la nota
 * de reparación/devolución en el CN23, porque la plantilla de Correos NO imprime ninguno
 * de los campos de texto del preregistro (observations no existe en POST /delivery;
 * shipmentNotes/dispatchReference se aceptan pero no se imprimen — validado con etiquetas
 * reales). Geometría calibrada sobre PDFs reales de Paq Retorno:
 *   - pág CN23 (595×421, solo si aduanas): coletilla como 2ª línea bajo el artículo (30,197).
 *   - pág etiqueta (595×842 con /Rotate 90): "RMA {id}" tras "Ref.:" (visual 50,91) y tras
 *     "Observaciones:" (visual 80,175); mapeo rotación: translate(y_visual, x_visual)+90 rotate.
 * EndPage (no BeginPage: pdfwrite lo borra/duplica al reinstalar el device por página) y
 * cada página en una pasada separada (el contador de BeginPage/EndPage no es fiable entre
 * páginas). FAIL-OPEN: ante cualquier fallo devuelve el PDF original sin estampar.
 * ⚠️ gs se lanza con proc_open (array de argumentos, sin shell): el FPM del web tiene
 * disable_functions=exec,passthru,shell_exec,system pero proc_open/popen PERMITIDOS
 * (descubierto 07-10: la versión con exec hacía fail-open silencioso en el admin).
 */

/** Ejecuta gs con pdfwrite y los argumentos dados (array, sin shell). Devuelve el exit code. */
function correosGsRun(array $args) {
    $cmd = array_merge(array('/usr/bin/gs', '-q', '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5'), $args);
    $pipes = array();
    $p = @proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($p)) return -1;
    @stream_get_contents($pipes[1]); @fclose($pipes[1]);
    @stream_get_contents($pipes[2]); @fclose($pipes[2]);
    return (int) proc_close($p);
}

function correosStampLabel($pdfBin, $id_rma, $conAduanas) {
    if (!is_string($pdfBin) || strncmp($pdfBin, '%PDF', 4) !== 0) return $pdfBin;
    if (!function_exists('proc_open') || !is_file('/usr/bin/gs')) {
        error_log('correosStampLabel rma=' . (int) $id_rma . ': sin proc_open o sin gs, PDF sin estampar');
        return $pdfBin;
    }
    $in  = @tempnam(sys_get_temp_dir(), 'crl');
    if ($in === false) { error_log('correosStampLabel rma=' . (int) $id_rma . ': tempnam fallo'); return $pdfBin; }
    $out = $in . '.out.pdf';
    if (@file_put_contents($in, $pdfBin) === false) { @unlink($in); error_log('correosStampLabel rma=' . (int) $id_rma . ': tmp no escribible'); return $pdfBin; }

    $rmaTxt = 'RMA ' . (int) $id_rma;   // solo [A-Z0-9 ], seguro dentro del literal PostScript
    // Helvetica reencodada a ISOLatin1 para los acentos (í=\355, ó=\363 en octal Latin1).
    $font = '/Helv-Lat /Helvetica findfont dup length dict begin {def} forall /Encoding ISOLatin1Encoding def currentdict end definefont pop';
    $cole = '<</EndPage { exch pop 2 ne { gsave /Helv-Lat 8 selectfont 30 197 moveto (\(Mercanc\355a para Reparaci\363n/Devoluci\363n sin valor comercial\)) show grestore true } { false } ifelse }>> setpagedevice';
    $rmas = '<</EndPage { exch pop 2 ne { gsave /Helv-Lat 8 selectfont 91 50 translate 90 rotate 0 0 moveto (' . $rmaTxt . ') show grestore gsave /Helv-Lat 8 selectfont 175 80 translate 90 rotate 0 0 moveto (' . $rmaTxt . ') show grestore true } { false } ifelse }>> setpagedevice';

    $ok = false;
    if ($conAduanas) {
        // 2 páginas: 1=CN23 (coletilla), 2=etiqueta (RMA). Pasadas separadas + concatenación.
        $p1 = $in . '.p1.pdf'; $p2 = $in . '.p2.pdf';
        $r1 = correosGsRun(array('-o', $p1, '-dFirstPage=1', '-dLastPage=1', '-c', $font . ' ' . $cole, '-f', $in));
        $r2 = correosGsRun(array('-o', $p2, '-dFirstPage=2', '-dLastPage=2', '-c', $font . ' ' . $rmas, '-f', $in));
        if ($r1 === 0 && $r2 === 0 && is_file($p1) && is_file($p2)) {
            $ok = (correosGsRun(array('-o', $out, $p1, $p2)) === 0);
        }
        @unlink($p1); @unlink($p2);
    } else {
        // 1 página (peninsular, sin CN23): solo el RMA en la etiqueta.
        $ok = (correosGsRun(array('-o', $out, '-c', $font . ' ' . $rmas, '-f', $in)) === 0);
    }
    $bin = ($ok && is_file($out)) ? @file_get_contents($out) : false;
    @unlink($in); @unlink($out);
    // Sanidad: gs "repara" PDFs corruptos devolviendo rc=0 con páginas vacías (~3KB); una
    // etiqueta real de Correos pesa ≥95KB y estampada aún más (incrusta la fuente).
    if (is_string($bin) && strncmp($bin, '%PDF', 4) === 0 && strlen($bin) > 50000 && strlen($bin) > strlen($pdfBin) / 4) return $bin;
    error_log('correosStampLabel rma=' . (int) $id_rma . ': fallo al estampar, se usa el PDF original');
    return $pdfBin;
}

/** Código de provincia (Anexo V) desde un CP español de 5 dígitos = sus 2 primeros dígitos. */
function correosProvinciaFromCp($cp) {
    $cp = trim((string) $cp);
    return preg_match('/^\d{5}$/', $cp) ? substr($cp, 0, 2) : '';
}

/** ¿El REMITENTE (en devoluciones, el cliente) está en un territorio español fuera de la
 *  unión aduanera? Canarias (35/38), Ceuta (51), Melilla (52). Esas devoluciones exigen
 *  declaración de aduanas (packageContents) o el preregistro PAAZE falla con 6069. */
function correosDevolucionConAduanas($cp) {
    return in_array(correosProvinciaFromCp($cp), array('35', '38', '51', '52'), true);
}

/**
 * Construye el bloque `packageContents` (declaración DUA/CN23) de una DEVOLUCIÓN cuyo
 * remitente está en Canarias/Ceuta/Melilla. Sin él, Correos responde 6069 "Las información
 * de aduanas es obligatoria [packageContents]"; sin `tariffNumber`, 6021 "el nTarifario no
 * existe". Importe y descripción salen de la línea del pedido original (denormalizada en
 * orders_products) y el peso del maestro de productos; hay respaldos para que la declaración
 * NUNCA quede incompleta (Correos exige netValue>0 y una descripción). Una sola declaración:
 * las devoluciones PAAZE/PAAZV son de 1 bulto. Réplica de la lógica ya validada en producción
 * en correos_albaran.php (salida), con shipmentType='5' (mercancía devuelta) en vez de '2'.
 * $envioGramos = peso TOTAL del envío que tecleó el operador: el netWeight declarado se
 * acota a ese total o Correos rechaza con 6015 "El peso de los artículos es mayor que el
 * peso total del envío". $over = overrides del operador desde el formulario (todas opcionales):
 * 'uds' (unidades que REALMENTE devuelve, puede ser menos que rma.quantity), 'valor' (valor
 * declarado TOTAL en EUR — p.ej. 1,50 para mercancía sin valor comercial que va a reparar) y
 * 'desc' (contenido; p.ej. "Mercancia para reparacion sin valor comercial"). No hay
 * shipmentType de "reparación" en el esquema (1 Documents/2 Goods/3 Gift/4 Samples/
 * 5 Returned/6 Other/7 Dangerous): una devolución para reparar sigue siendo '5' y el matiz
 * va en la descripción. Devuelve el array packageContents.
 */
function correosDevolucionPackageContents($rma, $envioGramos, $over = array()) {
    $pid = (int) ($rma['products_id'] ?? 0);
    $oid = (int) ($rma['orders_id'] ?? 0);
    $qty = max(1, (int) ($rma['quantity'] ?? 1));
    if (!empty($over['uds']) && (int) $over['uds'] > 0) $qty = (int) $over['uds'];

    $desc = ''; $unit = 0.0; $grams = 0;

    // 1) Línea del pedido original: nombre + precio de venta NETO (valor de la mercancía).
    if ($pid > 0 && $oid > 0) {
        $q = tep_db_query("SELECT products_name, products_price, final_price
                           FROM orders_products
                           WHERE orders_id = " . $oid . " AND products_id = " . $pid . " LIMIT 1");
        if ($row = tep_db_fetch_array($q)) {
            $desc = trim((string) $row['products_name']);
            $unit = ((float) $row['products_price'] > 0) ? (float) $row['products_price'] : (float) $row['final_price'];
        }
    }
    // 2) Maestro de productos: el peso (no está en la línea) y respaldo de precio/descr.
    if ($pid > 0) {
        $q = tep_db_query("SELECT products_model, products_weight, products_price
                           FROM products WHERE products_id = " . $pid . " LIMIT 1");
        if ($row = tep_db_fetch_array($q)) {
            $grams = (int) round(((float) $row['products_weight']) * 1000 * $qty);
            if ($unit <= 0)   $unit = (float) $row['products_price'];
            if ($desc === '') $desc = trim((string) $row['products_model']);
        }
    }
    // 3) Respaldo de VALOR: total del pedido (si el producto no tenía precio en ningún sitio).
    $val = round($unit * $qty, 2);
    if ($val <= 0 && $oid > 0) {
        $q = tep_db_query("SELECT value FROM orders_total
                           WHERE orders_id = " . $oid . " AND class = 'ot_total' LIMIT 1");
        if ($row = tep_db_fetch_array($q)) $val = round((float) $row['value'], 2);
    }

    if ($desc === '') $desc = 'Articulos nauticos';
    /* El Contenido declara SOLO el artículo (1ª línea del CN23); la coletilla "(Mercancía
     * para Reparación/Devolución sin valor comercial)" se ESTAMPA como 2ª línea en el PDF
     * (correosStampLabel) porque el CN23 impreso trunca este campo a ~80c y quedaría cortada.
     * ⚠️ 6018: description admite 100 caracteres MÁXIMO (validado con etiqueta real 07-10). */

    // Overrides del operador: valor declarado TOTAL y contenido (el campo del formulario
    // viene precargado con el artículo; si el operador lo edita, manda su texto).
    if (isset($over['valor']) && (float) $over['valor'] > 0) $val = round((float) $over['valor'], 2);
    if (!empty($over['desc'])) $desc = trim((string) $over['desc']);

    $desc = mb_substr(preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $desc), 0, 100);   // sin emoji; tope Correos 6018
    if ($grams < 1) $grams = 1;
    // 6015: el neto declarado no puede superar el peso total del envío (Correos repesa igual).
    $envioGramos = (int) $envioGramos;
    if ($envioGramos >= 1 && $grams > $envioGramos) $grams = $envioGramos;
    if ($val   <= 0) $val   = 1.0;   // Correos exige netValue>0; respaldo simbólico (no debería darse)

    $items = array(array(
        'quantity'     => (string) $qty,
        'description'  => $desc,
        'netWeight'    => (string) $grams,                  // gramos
        'netValue'     => number_format($val, 2, '.', ''),  // EUR, sin separador de miles
        'tariffNumber' => correos::TARIFA_ADUANA_DEF,       // código HS genérico aprobado
    ));

    return array(
        'shipmentType'            => '5',   // 5 = Returned Merchandise (mercancía devuelta)
        'indDangerousGoods'       => 'N',
        'indCommercialDelivery'   => 'S',
        'indInvoiceExceedsAmount' => ($val > 500 ? 'S' : 'N'),
        'indDUAwithCorreos'       => 'S',   // Correos gestiona el DUA de exportación
        /* IMPORTADOR en la Custom Declaration: en una devolución quien reintroduce la
         * mercancía en península es FRANCOBORDO (el destinatario), no el cliente. */
        'importerTaxReference'    => correos::FB_NIF,     // B82574690
        'importerVatNumber'       => correos::FB_NIF,
        'phoneNumber'             => correos::FB_TLFNO,   // 916528858 (el OAS lo define como "Importer phone number")
        'importerEmail'           => correos::FB_EMAIL,   // info@francobordo.com
        'customsData'             => $items,
    );
}

/** Peso sugerido (kg, 1 decimal) para la etiqueta: peso del maestro × cantidad del RMA.
 *  Precarga el campo del formulario para que el total case con la declaración de aduanas
 *  (si el operador deja 1 kg con 2 chalecos de 1 kg, el neto se acota igual — ver 6015). */
function correosPesoSugeridoKg($rma) {
    $pid = (int) ($rma['products_id'] ?? 0);
    $qty = max(1, (int) ($rma['quantity'] ?? 1));
    if ($pid > 0) {
        $q = tep_db_query('SELECT products_weight FROM products WHERE products_id = ' . $pid . ' LIMIT 1');
        if ($row = tep_db_fetch_array($q)) {
            $kg = round((float) $row['products_weight'] * $qty, 1);
            if ($kg >= 0.1) return number_format($kg, 1, '.', '');
        }
    }
    return '1';
}

/** Estado de tracking (correos_tracking, poblada por cron_correos_tracking) de una referencia RMA{id}; null si no hay. */
function correosTrackEstado($ref) {
    $ref = trim((string) $ref);
    if ($ref === '') return null;
    $q = tep_db_query("SELECT estado_desc, entregado, last_checked FROM correos_tracking WHERE referencia = '" . tep_db_input($ref) . "' LIMIT 1");
    return tep_db_num_rows($q) ? tep_db_fetch_array($q) : null;
}

/* ====================================================================== *
 *  Acción: preregistrar devolución + generar etiqueta                   *
 * ====================================================================== */

function rmaCorreosGenerateLabel() {
    $id  = (int) ($_POST['id'] ?? 0);
    $rma = correosRmaGet($id);
    if (!$rma) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id)); }

    $kilos = str_replace(',', '.', trim((string) ($_POST['kilos'] ?? '1')));
    if ($kilos === '' || (float) $kilos <= 0) $kilos = '1';
    $gramos = (int) round((float) $kilos * 1000);

    $c = new correos('pro');
    $c->setTimeout(60);

    // Devolución: remitente = cliente, destinatario = Francobordo (PAAZE + DOURUA).
    $opts = array(
        'weightGrams' => (string) $gramos,
        'province'    => correosProvinciaFromCp($rma['customers_postcode']),
    );
    // Canarias/Ceuta/Melilla: adjuntar la declaración de aduanas o el preregistro da 6069.
    // El operador puede ajustar unidades/valor/contenido desde el formulario (p.ej. el
    // cliente devuelve 1 de 2, o va a reparación sin valor comercial → valor simbólico).
    $aduanas = correosDevolucionConAduanas($rma['customers_postcode']);
    if ($aduanas) {
        $over = array();
        $u = (int) ($_POST['aduana_uds'] ?? 0);
        if ($u > 0) $over['uds'] = $u;
        $v = str_replace(',', '.', trim((string) ($_POST['aduana_valor'] ?? '')));
        if (preg_match('/^\d{1,9}(\.\d{1,2})?$/', $v) && (float) $v > 0) $over['valor'] = (float) $v;
        $d = trim((string) ($_POST['aduana_desc'] ?? ''));
        if ($d !== '') $over['desc'] = $d;
        $opts['packageContents'] = correosDevolucionPackageContents($rma, $gramos, $over);
    }
    $out = $c->etiquetaDevolucionRma($rma, $opts);

    // Estampar RMA en la etiqueta (+ coletilla en el CN23 si aduanas) antes de guardar:
    // así descarga, email y reimpresiones sirven siempre el PDF sellado.
    $labelPath = !empty($out['pdf_bin']) ? correosSaveLabelBin($id, correosStampLabel($out['pdf_bin'], $id, $aduanas)) : null;

    tep_db_perform('correos_shipments', array(
        'id_rma'          => $id,
        'entorno'         => 'pro',
        'shipment_code'   => $out['shipmentCode'],
        'package_code'    => $out['packageCode'],
        'producto'        => correos::PROD_RETORNO,
        'ref'             => 'RMA' . str_pad((string) $id, 8, '0', STR_PAD_LEFT),
        'kilos'           => (float) $kilos,
        'label_format'    => 'pdf',
        'label_path'      => $labelPath,
        'http_code'       => (string) ($out['preregister']['http'] ?? ''),
        'mensaje_retorno' => $out['ok'] ? '' : $out['error'],
        'ok'              => $out['ok'] ? 1 : 0,
        'request_json'    => json_encode($c->lastRequest, JSON_UNESCAPED_UNICODE),
        'response_json'   => ($out['preregister']['raw'] ?? '') . "\n---LABEL---\n" . ($out['label']['raw'] ?? ''),
        'operator'        => correosOperator(),
        'date_added'      => 'now()',
    ));

    correosNote($id, $out['ok']
        ? 'Correos: devolución ' . $out['shipmentCode'] . ' preregistrada (etiqueta disponible, depósito en oficina).'
        : 'Correos ERROR: ' . $out['error']);

    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . $id));
}

/* ====================================================================== *
 *  Acción: descargar etiqueta PDF                                       *
 * ====================================================================== */

function rmaCorreosDownloadLabel() {
    $shipId = (int) ($_GET['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM correos_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    if (!is_file($path)) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma'])); }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="etiqueta_correos_rma' . (int) $s['id_rma'] . '.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/* ====================================================================== *
 *  Acción: enviar etiqueta por email al cliente                         *
 * ====================================================================== */

function rmaCorreosEmailLabel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM correos_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['label_path'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    $rma  = correosRmaGet($s['id_rma']);
    $path = DIR_FS_CATALOG . 'images/rma/' . $s['label_path'];
    $name = trim($rma['customers_name']) ?: 'Cliente';
    $mailTo = trim($rma['customers_email_address']);

    if ($mailTo !== '' && is_file($path)) {
        $idFmt = str_pad((string) $s['id_rma'], 10, '0', STR_PAD_LEFT);
        $html = '<p>Hola ' . htmlspecialchars($name) . ',</p>'
              . '<p>Adjuntamos la etiqueta de Correos para la devolución de su RMA <strong>' . $idFmt . '</strong>.</p>'
              . '<p>Por favor, imprímala, péguela en el paquete y <strong>deposítelo en cualquier oficina de Correos</strong>.</p>'
              . '<p>Puede localizar su oficina más cercana aquí: '
              . '<a href="https://www.correos.es/es/es/herramientas/oficinas-buzones-citypaq">Buscador de oficinas de Correos</a></p>'
              . '<p>Gracias,<br>' . STORE_NAME . '</p>';
        // tep_mail() = PHPMailer (ruta de entrega real, con bypass SendGrid para internos
        // y soporte de adjunto). NO usar la clase email antigua (caché de bounces).
        $attach = array(
            'tmp_name' => $path,
            'name'     => 'etiqueta_correos_rma' . (int) $s['id_rma'] . '.pdf',
        );
        tep_mail($name, $mailTo, 'Etiqueta de devolución Correos - RMA ' . $idFmt,
                 $html, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $attach);

        tep_db_perform('correos_shipments', array('emailed_at' => 'now()'), 'update', 'id = ' . $shipId);
        correosNote($s['id_rma'], 'Correos: etiqueta de la devolución ' . ($s['shipment_code'] ?: '') . ' enviada por email a ' . $mailTo . '.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Acción: anular preregistro                                           *
 * ====================================================================== */

function rmaCorreosCancel() {
    $shipId = (int) ($_POST['ship'] ?? 0);
    $q = tep_db_query('SELECT * FROM correos_shipments WHERE id = ' . $shipId);
    $s = tep_db_fetch_array($q);
    if (!$s || empty($s['package_code']) || !empty($s['cancelled_at'])) { tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) ($s['id_rma'] ?? 0))); }

    // Todos los bultos del envío (multibulto): packageCodes de response_json o el package_code.
    $pkgs = array();
    $rj = json_decode((string) ($s['response_json'] ?? ''), true);
    $pk = $rj['data']['shipments'][0]['packages'] ?? null;
    if (is_array($pk)) foreach ($pk as $pp) if (!empty($pp['packageCode'])) $pkgs[] = (string) $pp['packageCode'];
    if (!$pkgs) $pkgs = array((string) $s['package_code']);
    $pkgs = array_values(array_unique($pkgs));

    // Intento RÁPIDO en la petición del admin (no bloquear). Si falla, se ENCOLA y el
    // cron de tracking reintenta cada hora (el endpoint de anulación es inestable: M5).
    $c = new correos($s['entorno'] ?: 'pro');
    $c->setTimeout(12);                 // intento RÁPIDO acotado; el cron reintenta lo que falle
    $allOk = true; $lastMsg = '';
    foreach ($pkgs as $pc) {
        $res = $c->annulment($pc, 'spa', 2);
        if (!correos::annulmentOk($res)) {
            $allOk = false;
            $lastMsg = is_array($res['data'] ?? null) ? (string) ($res['data']['message'] ?? ($res['data']['error'] ?? '')) : ('HTTP ' . ($res['http'] ?? '?'));
        }
    }

    if ($allOk) {
        tep_db_perform('correos_shipments', array('cancelled_at' => 'now()'), 'update', 'id = ' . $shipId);
        correosNote($s['id_rma'], 'Correos: devolución ' . $s['shipment_code'] . ' anulada.');
    } else {
        tep_db_perform('correos_shipments', array('cancel_requested_at' => 'now()'), 'update', 'id = ' . $shipId);
        correosNote($s['id_rma'], 'Correos: la anulación de ' . $s['shipment_code'] . ' no se pudo completar ahora (' . $lastMsg . '); se REINTENTARÁ automáticamente cada hora. Si urge, anular a mano.');
    }
    tep_redirect(tep_href_link('rma.php', 'action=view&id=' . (int) $s['id_rma']));
}

/* ====================================================================== *
 *  Render: caja del operador en views/view.php                          *
 * ====================================================================== */

function rmaCorreosRenderBox($rmaDetail) {
    $id   = (int) $rmaDetail['id_rma'];
    $rows = correosShipmentsFor($id);
    ?>
    <div class="rows sp10 column a12" style="margin-top:8px;padding:10px;background:#fffbe6;border:1px solid #e8d27a;border-radius:4px">
        <div class="column a12" style="font-weight:bold;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between">
            <span>
                <i class="fa fa-envelope"></i> Correos
                <span style="color:#2e7d32;font-size:11px" title="No hay entorno de pruebas con etiquetas; el preregistro es anulable y no se factura hasta que el paquete entra en la red">&nbsp;(PRODUCCIÓN)</span>
            </span>
            <span style="font-size:11px;color:#8a6d1a;font-weight:normal">depósito en cualquier oficina de Correos</span>
        </div>

        <?php if ($rows): ?>
            <div class="column a12" style="margin-bottom:8px">
                <?php foreach ($rows as $s): ?>
                    <div style="font-size:12px;padding:5px 0;border-bottom:1px solid #ecdfa8;<?php echo $s['cancelled_at'] ? 'opacity:.5' : ''; ?>">
                        <strong>Devolución</strong>
                        <?php echo $s['ok'] ? '<span style="color:#2e7d32">✔</span>' : '<span style="color:#c0392b">✖ HTTP ' . htmlspecialchars($s['http_code']) . '</span>'; ?>
                        <?php if ($s['shipment_code']): ?> · env. <code><?php echo htmlspecialchars($s['shipment_code']); ?></code><?php endif; ?>
                        <?php if ($s['package_code']): ?> · bulto <code><?php echo htmlspecialchars($s['package_code']); ?></code><?php endif; ?>
                        <?php if ($s['cancelled_at']): ?> · <em>anulada</em><?php endif; ?>
                        <?php $trk = correosTrackEstado($s['ref']); if ($trk): ?> · <strong style="color:<?php echo $trk['entregado'] ? '#2e7d32' : '#0a6ebd'; ?>">📍 <?php echo htmlspecialchars($trk['estado_desc']); ?></strong><span style="color:#999;font-size:11px"> (<?php echo date('d/m H:i', strtotime($trk['last_checked'])); ?>)</span><?php endif; ?>
                        <br><span style="color:#777"><?php echo htmlspecialchars($s['date_added']); ?><?php if (!$s['ok'] && $s['mensaje_retorno']): ?> — <?php echo htmlspecialchars($s['mensaje_retorno']); ?><?php endif; ?></span>
                        <?php if ($s['ok']): ?>
                            <div style="margin-top:3px">
                                <?php if ($s['label_path']): ?>
                                    <a class="xbutton" style="font-size:11px;padding:2px 8px" href="<?php echo tep_href_link('rma.php', 'action=correos-label&ship=' . (int) $s['id']); ?>" target="_blank">⬇ Etiqueta</a>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=correos-email-label'); ?>" style="display:inline">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton verde" style="font-size:11px;padding:2px 8px" type="submit">✉ <?php echo $s['emailed_at'] ? 'Reenviar al cliente' : 'Enviar al cliente'; ?></button>
                                    </form>
                                    <?php if ($s['emailed_at']): ?><span style="color:#777;font-size:11px">enviada <?php echo htmlspecialchars($s['emailed_at']); ?></span><?php endif; ?>
                                <?php endif; ?>
                                <?php if (!$s['cancelled_at']): ?>
                                    <form method="post" action="<?php echo tep_href_link('rma.php', 'action=correos-cancel'); ?>" style="display:inline" onsubmit="return confirm('¿Anular la devolución <?php echo htmlspecialchars($s['shipment_code']); ?>?');">
                                        <input type="hidden" name="ship" value="<?php echo (int) $s['id']; ?>">
                                        <button class="xbutton rojo" style="font-size:11px;padding:2px 8px" type="submit">✕ Anular</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo tep_href_link('rma.php', 'action=correos-label-gen'); ?>" class="column a12" style="font-size:12px">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div style="margin-bottom:5px">
                Devolución desde: <strong><?php echo htmlspecialchars(trim($rmaDetail['customers_name'])); ?></strong>,
                <?php echo htmlspecialchars(trim($rmaDetail['customers_street_address'] . ' ' . $rmaDetail['customers_suburb'])); ?>,
                <?php echo htmlspecialchars(trim($rmaDetail['customers_postcode'] . ' ' . $rmaDetail['customers_city'])); ?>
                → Francobordo (Alcobendas)
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
                <label>Peso (kg): <input type="number" name="kilos" step="0.1" min="0.1" value="<?php echo correosPesoSugeridoKg($rmaDetail); ?>" style="width:60px;padding:3px;border:1px solid #aaa;border-radius:3px"></label>
            </div>
            <?php if (correosDevolucionConAduanas($rmaDetail['customers_postcode'])): ?>
                <?php $itDef = correosDevolucionPackageContents($rmaDetail, PHP_INT_MAX); $itDef = $itDef['customsData'][0]; ?>
                <div style="margin-bottom:6px;padding:6px;border:1px dashed #c8a24a;border-radius:3px;background:#fff7d9">
                    <div style="font-weight:bold;margin-bottom:4px">🛃 Declaración de aduanas (Canarias/Ceuta/Melilla) — lo que REALMENTE devuelve el cliente</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <label>Unidades <input type="number" name="aduana_uds" min="1" step="1" value="<?php echo (int) $itDef['quantity']; ?>" style="width:50px;padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                        <label>Valor declarado &euro; <input type="text" name="aduana_valor" value="<?php echo htmlspecialchars($itDef['netValue']); ?>" style="width:70px;padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                        <label>Contenido <input type="text" name="aduana_desc" maxlength="100" value="<?php echo htmlspecialchars($itDef['description']); ?>" style="width:440px;padding:3px;border:1px solid #aaa;border-radius:3px"></label>
                    </div>
                    <div style="color:#8a6d1a;font-size:11px;margin-top:3px">El PDF sale sellado automáticamente: nº de RMA en Ref./Observaciones de la etiqueta y "(Mercancía para Reparación/Devolución sin valor comercial)" como 2ª línea del CN23. Para valor simbólico pon Valor <code>1.50</code>; ajusta Unidades y Peso si devuelve menos artículos.</div>
                </div>
            <?php endif; ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="xbutton" style="background:#f2c200;color:#333" type="submit"
                        title="Preregistra la devolución y genera la etiqueta; el cliente deposita el paquete en cualquier oficina de Correos">🏤 Generar etiqueta de devolución Correos</button>
            </div>
        </form>
    </div>
    <?php
}
