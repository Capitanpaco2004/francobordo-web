<?php
/**
 * Cliente REST para el WebService de Correos Express.
 *
 * Cubre: grabación de envíos (con/sin etiqueta y con/sin recogida asociada),
 * grabación/anulación de recogida a domicilio, consulta de puntos de
 * conveniencia (PUDO), y seguimiento de envíos y recogidas.
 *
 * Credenciales y notas de integración: ver memoria francobordo_correos_express_api.
 * Documentación CEX: correos_express_docs/ (workstation).
 *
 * Gotcha TLS: el entorno TEST usa certificado self-signed → verificación OFF
 * solo en TEST; en PRO el certificado es válido y se verifica.
 */
class correos_express {

    /* ------------------------------------------------------------------ *
     *  Credenciales (idénticas en TEST y PRO)                            *
     * ------------------------------------------------------------------ */
    const WS_USER     = '632140001_WS';
    const WS_PASS     = 'nxk6T';
    const SOLICITANTE = 'I632140001';   // campo "solicitante"
    const CLIENTE     = '632140001';    // clienteRecogida / idCliente / codRte

    /* ------------------------------------------------------------------ *
     *  Dirección de Francobordo                                          *
     *  - Devoluciones (RMA): Francobordo es el DESTINATARIO              *
     *  - Envíos salientes:   Francobordo es el REMITENTE                 *
     *  TODO confirmar teléfono y CIF reales del almacén de devoluciones. *
     * ------------------------------------------------------------------ */
    const FB_NOMBRE   = 'Francobordo Articulos Nauticos SL';
    const FB_DIR      = 'Calle San Rafael 8';
    const FB_POBL     = 'Alcobendas';
    const FB_CP       = '28108';
    const FB_PAIS_ISO = 'ES';
    const FB_CONTACTO = 'Francobordo';
    const FB_TLFNO    = '916528858';            // Almacén devoluciones (Alcobendas)
    const FB_EMAIL    = 'info@francobordo.com';
    const FB_NIF      = '';                      // CIF, opcional en destinatario

    /* Producto por defecto: 63 = PAQ24 (peninsular, PT, Andorra, entre islas) */
    const PROD_PAQ24   = '63';
    const PROD_PAQPUNTO = '18';                  // entrega en PUDO / oficina

    /* Entorno por defecto cuando no se pasa al constructor.
     * Cambiar a 'pro' cuando la integración esté validada en producción. */
    const DEFAULT_ENV = 'test';

    /** @var string 'test'|'pro' */
    protected $env;
    /** @var string */
    protected $base;
    /** @var bool */
    protected $verifyTls;
    /** @var int */
    protected $timeout = 30;
    /** @var array|null Última petición enviada (debug) */
    public $lastRequest = null;

    public function __construct($env = null) {
        if ($env === null) $env = self::DEFAULT_ENV;
        $this->env  = ($env === 'test') ? 'test' : 'pro';
        $this->base = ($this->env === 'test')
            ? 'https://www.test.cexpr.es/wspsc/'
            : 'https://www.cexpr.es/wspsc/';
        // TEST = certificado self-signed; PRO = cert válido.
        $this->verifyTls = ($this->env === 'pro');
    }

    public function getEnv()  { return $this->env; }

    /** Ajusta el timeout total (segundos). Útil para llamadas de cara al cliente. */
    public function setTimeout($s) { $this->timeout = max(1, (int) $s); return $this; }

    /* ================================================================== *
     *  Núcleo HTTP                                                        *
     * ================================================================== */

    /**
     * POST JSON a un endpoint del WS. Devuelve array normalizado.
     * @return array{ok:bool,http:int,error:string,raw:string,data:?array}
     */
    protected function post($path, array $payload) {
        $url  = $this->base . $path;
        $this->lastRequest = $payload;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json; charset=UTF-8'),
            CURLOPT_USERPWD        => self::WS_USER . ':' . self::WS_PASS,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_CONNECTTIMEOUT => min(15, $this->timeout),
            CURLOPT_TIMEOUT        => $this->timeout,
        ));
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        // curl_close() es deprecated desde PHP 8.5 (sin efecto desde 8.0); el handle
        // se libera solo al salir de ámbito.

        $data = ($body === false) ? null : json_decode($body, true);

        return array(
            'ok'    => ($http >= 200 && $http < 300 && is_array($data)),
            'http'  => $http,
            'error' => $err,
            'raw'   => ($body === false ? '' : $body),
            'data'  => $data,
        );
    }

    /* ================================================================== *
     *  Recogida a domicilio (sin etiqueta)                               *
     * ================================================================== */

    /**
     * Graba una recogida a domicilio.
     * $o admite: refRecogida, fechaRecogida(DDMMYYYY), horaDesde1, horaHasta1,
     *            nomRemit, nifRemit, dirRecog, poblRecog, cpRecog, contRecog,
     *            tlfnoRecog, oTlfnRecog, emailRecog, observ, producto, latente.
     */
    public function grabarRecogida(array $o) {
        $req = array(
            'solicitante'     => self::SOLICITANTE,
            'password'        => '',
            'canalEntrada'    => '',
            'refRecogida'     => (string) ($o['refRecogida'] ?? ''),
            'fechaRecogida'   => (string) ($o['fechaRecogida'] ?? ''),
            'horaDesde1'      => (string) ($o['horaDesde1'] ?? ''),
            'horaHasta1'      => (string) ($o['horaHasta1'] ?? ''),
            'horaDesde2'      => (string) ($o['horaDesde2'] ?? ''),
            'horaHasta2'      => (string) ($o['horaHasta2'] ?? ''),
            'clienteRecogida' => self::CLIENTE,
            'codRemit'        => '',
            'nomRemit'        => (string) ($o['nomRemit'] ?? ''),
            'nifRemit'        => (string) ($o['nifRemit'] ?? ''),
            'dirRecog'        => (string) ($o['dirRecog'] ?? ''),
            'poblRecog'       => (string) ($o['poblRecog'] ?? ''),
            'cpRecog'         => (string) ($o['cpRecog'] ?? ''),
            'contRecog'       => (string) ($o['contRecog'] ?? ''),
            'tlfnoRecog'      => (string) ($o['tlfnoRecog'] ?? ''),
            'oTlfnRecog'      => (string) ($o['oTlfnRecog'] ?? ($o['tlfnoRecog'] ?? '')),
            'emailRecog'      => (string) ($o['emailRecog'] ?? ''),
            'observ'          => (string) ($o['observ'] ?? ''),
            'tipoServ'        => '',
            // Destinatario (Francobordo) — opcional en recogida, lo informamos.
            'nomDest'         => self::FB_NOMBRE,
            'dirDest'         => self::FB_DIR,
            'pobDest'         => self::FB_POBL,
            'cpDest'          => self::FB_CP,
            'paisDest'        => self::FB_PAIS_ISO,
            'contactoDest'    => self::FB_CONTACTO,
            'tlfnoDest'       => self::FB_TLFNO,
            'emailDest'       => self::FB_EMAIL,
            'producto'        => (string) ($o['producto'] ?? self::PROD_PAQ24),
            'latente'         => (string) ($o['latente'] ?? '0'),
        );
        return $this->post('apiRestGrabacionRecogidaEnviok8s/json/grabarRecogida', $req);
    }

    public function anularRecogida($keyRecogida, $motivo = 'Anulada desde web') {
        return $this->post('apiRestGrabacionRecogidaEnviok8s/json/anularRecogida', array(
            'solicitante'       => self::SOLICITANTE,
            'password'          => '',
            'keyRecogida'       => (string) $keyRecogida,
            'strTextoAnulacion' => (string) $motivo,
        ));
    }

    public function horaCorte($cp, $pais = '34') {
        return $this->post('apiRestGrabacionRecogidaEnviok8s/json/horaCorte', array(
            'strCP'   => (string) $cp,
            'strPais' => (string) $pais,
        ));
    }

    /* ================================================================== *
     *  Grabación de envío (con etiqueta y/o recogida asociada)           *
     * ================================================================== */

    /**
     * Graba un envío. $o admite remitente (rte*) y destinatario (dest*),
     * numBultos, kilos, producto, portes, ref, observac, y opciones de
     * etiqueta/recogida via $o['etiqueta'] (1=PDF,2=ZPL...) y $o['recogida'].
     */
    public function grabacionEnvio(array $o) {
        $kilos   = (string) ($o['kilos']   ?? '1');
        $alto    = (string) ($o['alto']    ?? '1');
        $largo   = (string) ($o['largo']   ?? '1');
        $ancho   = (string) ($o['ancho']   ?? '1');
        $volumen = (string) ($o['volumen'] ?? '1');
        $info = array();
        if (!empty($o['etiqueta'])) {
            $info['tipoEtiqueta']        = (string) $o['etiqueta'];   // 1=PDF 10x15, 2=ZPL...
            $info['codificacionUnicaB64'] = '1';                      // 1 sola codificación base64
        }
        if (!empty($o['recogida'])) {
            $r = $o['recogida'];
            $info['creaRecogida']      = 'S';
            $info['fechaRecogida']     = (string) ($r['fecha'] ?? '');     // DDMMYYYY
            $info['horaDesdeRecogida'] = (string) ($r['horaDesde'] ?? '');
            $info['horaHastaRecogida'] = (string) ($r['horaHasta'] ?? '');
            $info['referenciaRecogida'] = (string) ($r['ref'] ?? ($o['ref'] ?? ''));
        }

        $req = array(
            'solicitante'   => self::SOLICITANTE,
            'canalEntrada'  => '',
            'numEnvio'      => (string) ($o['numEnvio'] ?? ''),
            'ref'           => (string) ($o['ref'] ?? ''),
            'refCliente'    => (string) ($o['refCliente'] ?? ''),
            'fecha'         => (string) ($o['fecha'] ?? ''),              // DDMMYYYY (vacío = hoy)
            // Remitente
            'codRte'        => (string) ($o['codRte'] ?? ''),
            'nomRte'        => (string) ($o['nomRte'] ?? ''),
            'nifRte'        => (string) ($o['nifRte'] ?? ''),
            'dirRte'        => (string) ($o['dirRte'] ?? ''),
            'pobRte'        => (string) ($o['pobRte'] ?? ''),
            'codPosNacRte'  => (string) ($o['codPosNacRte'] ?? ''),
            'paisISORte'    => (string) ($o['paisISORte'] ?? 'ES'),
            'codPosIntRte'  => (string) ($o['codPosIntRte'] ?? ''),
            'contacRte'     => (string) ($o['contacRte'] ?? ''),
            'telefRte'      => (string) ($o['telefRte'] ?? ''),
            'emailRte'      => (string) ($o['emailRte'] ?? ''),
            // Destinatario
            'codDest'       => (string) ($o['codDest'] ?? ''),
            'nomDest'       => (string) ($o['nomDest'] ?? ''),
            'nifDest'       => (string) ($o['nifDest'] ?? ''),
            'dirDest'       => (string) ($o['dirDest'] ?? ''),
            'pobDest'       => (string) ($o['pobDest'] ?? ''),
            'codPosNacDest' => (string) ($o['codPosNacDest'] ?? ''),
            'paisISODest'   => (string) ($o['paisISODest'] ?? 'ES'),
            'codPosIntDest' => (string) ($o['codPosIntDest'] ?? ''),
            'contacDest'    => (string) ($o['contacDest'] ?? ''),
            'telefDest'     => (string) ($o['telefDest'] ?? ''),
            'emailDest'     => (string) ($o['emailDest'] ?? ''),
            'contacOtrs'    => '',
            'telefOtrs'     => '',
            'emailOtrs'     => '',
            'observac'      => (string) ($o['observac'] ?? ''),
            'numBultos'     => (string) ($o['numBultos'] ?? '1'),
            'kilos'         => $kilos,
            'volumen'       => $volumen,
            'alto'          => $alto,
            'largo'         => $largo,
            'ancho'         => $ancho,
            'producto'      => (string) ($o['producto'] ?? self::PROD_PAQ24),
            'portes'        => (string) ($o['portes'] ?? 'P'),
            'reembolso'     => (string) ($o['reembolso'] ?? ''),
            'entrSabado'    => (string) ($o['entrSabado'] ?? ''),
            'seguro'        => (string) ($o['seguro'] ?? ''),
            'numEnvioVuelta' => (string) ($o['numEnvioVuelta'] ?? ''),
            // El validador estricto de CEX exige bulto completo: kilos + alto +
            // largo + ancho + volumen (omitir volumen => 999 VALIDA_FORMATO_DATOS_GE).
            'listaBultos'   => array(array(
                'orden' => '1', 'kilos' => $kilos,
                'alto' => $alto, 'largo' => $largo, 'ancho' => $ancho, 'volumen' => $volumen,
            )),
            'password'      => '',
            'listaInformacionAdicional' => array($info ?: array('etiquetaPDF' => '')),
        );
        // idPtoExterno solo para entrega en PUDO/oficina (producto 18).
        if (!empty($o['idPtoExterno'])) $req['idPtoExterno'] = (string) $o['idPtoExterno'];
        return $this->post('apiRestGrabacionEnviok8s/json/grabacionEnvio', $req);
    }

    /* ================================================================== *
     *  Puntos de conveniencia (PUDO)                                     *
     * ================================================================== */

    public function consultPudo(array $o) {
        $req = array(
            'Expedicion'       => '',
            'cpOrigen'         => '',
            'latitudOrigen'    => '',
            'longitudOrigen'   => '',
            'isoPaisOrigen'    => '',
            'cpDest'           => (string) ($o['cpDest'] ?? ''),
            'latitudDest'      => (string) ($o['latitudDest'] ?? ''),
            'longitudDest'     => (string) ($o['longitudDest'] ?? ''),
            'isoPaisDest'      => (string) ($o['isoPaisDest'] ?? 'ES'),
            'idCliente'        => self::CLIENTE,
            'idProducto'       => self::PROD_PAQPUNTO,
            'importeReembolso' => (string) ($o['importeReembolso'] ?? '0'),
            'valorMercancia'   => (string) ($o['valorMercancia'] ?? '0'),
            'valorAsegurado'   => (string) ($o['valorAsegurado'] ?? '0'),
            'nroBultos'        => (string) ($o['nroBultos'] ?? '1'),
            'pod'              => (string) ($o['pod'] ?? ''),
            'entregaSabado'    => (string) ($o['entregaSabado'] ?? '0'),
            'portes'           => (string) ($o['portes'] ?? 'P'),
            'largo'            => (string) ($o['largo'] ?? '1'),
            'ancho'            => (string) ($o['ancho'] ?? '1'),
            'alto'             => (string) ($o['alto'] ?? '1'),
            'peso'             => (string) ($o['peso'] ?? '1'),
            'volumen'          => '',
            'servicioEntrega'  => '',
            'servicioRecogida' => '',
        );
        if (isset($o['ratio'])) $req['ratio'] = (string) $o['ratio'];
        return $this->post('apiRestInterfacePuntosEntrega/json/consultPudo', $req);
    }

    /* ================================================================== *
     *  Oficinas Correos Express (depósito de devoluciones)               *
     * ================================================================== */

    /** Lista oficinas CEX por código postal (y opcional población). */
    public function consultarOficinas($cp, $poblacion = '') {
        return $this->post('apiRestOficina/v1/oficinas/listadoOficinasCoordenadas', array(
            'cod_postal' => (string) $cp,
            'poblacion'  => (string) $poblacion,
        ));
    }

    /** Filtra de una respuesta de consultarOficinas las que admiten depósito de devoluciones. */
    public static function oficinasDeposito(array $resp) {
        $out = array();
        if (!empty($resp['data']['oficinas']) && is_array($resp['data']['oficinas'])) {
            foreach ($resp['data']['oficinas'] as $o) {
                if (strcasecmp((string) ($o['admiteLogisticaInversaOficina'] ?? ''), 'Si') === 0) {
                    $out[] = $o;
                }
            }
        }
        return $out;
    }

    /* ================================================================== *
     *  Seguimiento                                                        *
     * ================================================================== */

    public function seguimientoEnvio($numEnvio, $idioma = 'ES') {
        return $this->post('apiRestSeguimientoEnviosk8s/json/seguimientoEnvio', array(
            'codigoCliente' => self::CLIENTE,
            'dato'          => (string) $numEnvio,
            'idioma'        => (string) $idioma,
        ));
    }

    public function seguimientoRecogida($numRecogida, $idioma = 'ES') {
        return $this->post('apiRestSeguimientoRecogidak8s/json/seguimientoRecogida', array(
            'solicitante' => self::SOLICITANTE,
            'dato'        => (string) $numRecogida,
            'codCliente'  => self::CLIENTE,
            'fecRecogida' => '',
            'idioma'      => (string) $idioma,
        ));
    }

    /* ================================================================== *
     *  Helpers para construir desde una fila de la tabla `rma`           *
     * ================================================================== */

    /**
     * Construye los datos de envío de RETORNO de un RMA:
     * remitente = cliente (quien devuelve), destinatario = Francobordo.
     */
    public static function envioRetornoDesdeRma(array $rma, array $opts = array()) {
        return array(
            'ref'          => 'RMA' . str_pad((string) $rma['id_rma'], 8, '0', STR_PAD_LEFT),
            'refCliente'   => (string) $rma['id_rma'],
            // Contrato/facturación = Francobordo (paga el porte de la devolución).
            // El remitente físico (nombre/dirección) es el cliente, pero codRte
            // identifica el contrato cuando portes=P.
            'codRte'       => self::CLIENTE,
            // Remitente = cliente
            'nomRte'       => trim($rma['customers_name']),
            'dirRte'       => trim($rma['customers_street_address'] . ' ' . ($rma['customers_suburb'] ?? '')),
            'pobRte'       => trim($rma['customers_city']),
            'codPosNacRte' => trim($rma['customers_postcode']),
            'paisISORte'   => 'ES',
            'contacRte'    => trim($rma['customers_name']),
            'telefRte'     => trim($rma['customers_telephone']),
            'emailRte'     => trim($rma['customers_email_address']),
            // Destinatario = Francobordo
            'nomDest'       => self::FB_NOMBRE,
            'dirDest'       => self::FB_DIR,
            'pobDest'       => self::FB_POBL,
            'codPosNacDest' => self::FB_CP,
            'paisISODest'   => self::FB_PAIS_ISO,
            'contacDest'    => self::FB_CONTACTO,
            'telefDest'     => self::FB_TLFNO,
            'emailDest'     => self::FB_EMAIL,
            'numBultos'    => '1',
            'kilos'        => (string) ($opts['kilos'] ?? '1'),
            'producto'     => (string) ($opts['producto'] ?? self::PROD_PAQ24),
            'portes'       => 'P',  // pagado por Francobordo
            'observac'     => 'Devolucion RMA ' . $rma['id_rma'],
        );
    }

    /** Recogida a domicilio del cliente para un RMA (sin etiqueta). */
    public static function recogidaDesdeRma(array $rma, array $opts = array()) {
        return array(
            'refRecogida' => 'RMA' . str_pad((string) $rma['id_rma'], 8, '0', STR_PAD_LEFT),
            'nomRemit'    => trim($rma['customers_name']),
            'dirRecog'    => trim($rma['customers_street_address'] . ' ' . ($rma['customers_suburb'] ?? '')),
            'poblRecog'   => trim($rma['customers_city']),
            'cpRecog'     => trim($rma['customers_postcode']),
            'contRecog'   => trim($rma['customers_name']),
            'tlfnoRecog'  => trim($rma['customers_telephone']),
            'oTlfnRecog'  => trim($rma['customers_telephone']),
            'emailRecog'  => trim($rma['customers_email_address']),
            'producto'    => (string) ($opts['producto'] ?? self::PROD_PAQ24),
            'latente'     => (string) ($opts['latente'] ?? '0'),
            'fechaRecogida' => (string) ($opts['fechaRecogida'] ?? ''),
            'horaDesde1'    => (string) ($opts['horaDesde1'] ?? ''),
            'horaHasta1'    => (string) ($opts['horaHasta1'] ?? ''),
        );
    }
}
