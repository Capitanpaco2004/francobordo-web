<?php
/**
 * Cliente REST para la API PIC de SEUR (servicios.api(pre).seur.io).
 *
 * Cubre: obtención de token OAuth2 (Keycloak, grant_type=password → JWT Bearer),
 * consulta de poblaciones por CP (cities), grabación de envíos (shipments),
 * generación de etiquetas (labels: PDF/ZPL/GEOLABEL) y seguimiento (tracking:
 * simplificado y extendido).
 *
 * Réplica del patrón de includes/classes/correos_express.php, pero el transporte
 * cambia: SEUR usa OAuth2 Bearer (token JWT) en lugar de HTTP Basic, las etiquetas
 * y el tracking son GET con query-string, y los envíos POST JSON.
 *
 * Auth (fichero TOKEN_PRE de SEUR):
 *   POST {base}/pic_token
 *   Content-Type: application/x-www-form-urlencoded
 *   body: grant_type=password & client_id & client_secret & username & password
 *   resp (Keycloak): { access_token, expires_in:<segundos ~3600>, refresh_token,
 *                      token_type:"Bearer", ... }
 *   Las llamadas a la API van con cabecera  Authorization: Bearer <access_token>.
 *   Estrategia de renovación: re-pedir token al caducar o ante HTTP 401/403.
 *
 * Entornos:
 *   'pre' = PREproducción → servicios.apipre.seur.io (token solo da estado inicial
 *           en tracking; los envíos no entran en la red real)
 *   'pro' = Producción     → servicios.api.seur.io
 *   Los datos de cuenta (CCC, NIF) son los mismos en PRE y PRO; las credenciales
 *   del token (client_id/secret/username/password) son DISTINTAS por entorno.
 *
 * Códigos de servicio/producto (de SEUR, email de alta):
 *   B2C Nacional       → service 031 / product 002
 *   B2C Internacional  → service 077 / product 104
 *   (otros servicios: pedir a SEUR los códigos correspondientes)
 *
 * Credenciales y notas de integración: ver memoria francobordo_seur_api.
 */
class seur {

    /* ------------------------------------------------------------------ *
     *  Credenciales del token, por entorno (OAuth password grant).        *
     *  Viven FUERA de public_html (el repo se espeja a GitHub):           *
     *  /home/francobordo/seur_credentials.php devuelve                    *
     *  array('pre'=>array(client_id,client_secret,username,password),     *
     *        'pro'=>array(...)).                                          *
     * ------------------------------------------------------------------ */
    const CREDS_FILE = '/home/francobordo/seur_credentials.php';

    /** @var array|null cache estático del fichero de credenciales */
    private static $credsFile = null;

    /** Credenciales del token para un entorno ('pre'|'pro'). */
    protected static function tokenCreds($env) {
        if (self::$credsFile === null) {
            if (!is_readable(self::CREDS_FILE)) {
                throw new Exception('seur: falta el fichero de credenciales ' . self::CREDS_FILE);
            }
            self::$credsFile = include self::CREDS_FILE;
        }
        if (empty(self::$credsFile[$env]['client_id'])) {
            throw new Exception('seur: sin credenciales para el entorno ' . $env . ' en ' . self::CREDS_FILE);
        }
        return self::$credsFile[$env];
    }

    /* Bases de la API por entorno. */
    const BASE_PRE = 'https://servicios.apipre.seur.io';
    const BASE_PRO = 'https://servicios.api.seur.io';

    /* ------------------------------------------------------------------ *
     *  Datos de cuenta / contrato (iguales en PRE y PRO)                  *
     * ------------------------------------------------------------------ */
    const CCC          = '36598-28';      // Código Cuenta Contable (sender.ccc / customer.accountNumber)
    const NIF          = 'B82574690';     // CIF Francobordo (customer.idNumber / tracking idNumber)
    const BUSINESS_UNIT = '28';           // FranquiciaOrigen (salidas desde Madrid)

    /** El tracking exige accountNumber NUMÉRICO: la parte de la CCC anterior al '-'
     *  ('36598-28' → '36598'); el sufijo '28' va aparte como businessUnit. */
    public static function accountNumber() {
        $p = explode('-', self::CCC);
        return $p[0];
    }

    /* ------------------------------------------------------------------ *
     *  Códigos de servicio/producto por flujo (email SEUR 2026-06-09).    *
     *  serviceCode/productCode son enteros; los ceros a la izquierda son  *
     *  cosméticos (la API acepta '31' y '031' igual).                     *
     * ------------------------------------------------------------------ */
    /* Nacional — salida */
    const SVC_NACIONAL      = '31';  const PRD_NACIONAL      = '2';   // B2C domicilio (def. salida)
    const SVC_NAC_B2B       = '1';   const PRD_NAC_B2B       = '2';   // B2B domicilio
    const SVC_NAC_2SHOP     = '1';   const PRD_NAC_2SHOP     = '48';  // 2shop (punto) — req. pickupCentreCode en receiver
    /* Internacional — salida */
    const SVC_INTERNAC      = '77';  const PRD_INTERNAC      = '104'; // Classic B2C (monobulto, máx 31,5 kg)
    const SVC_INT_B2B       = '77';  const PRD_INT_B2B       = '70';  // Classic B2B (monobulto, máx 31,5 kg)
    const SVC_INT_2SHOP     = '77';  const PRD_INT_2SHOP     = '48';  // 2shop Internacional — req. pickupCentreCode
    const SVC_NETEXPRESS    = '19';  const PRD_NETEXPRESS    = '70';  // NetExpress (multibulto, sin límite 31,5)
    const SVC_COURIER       = '7';   const PRD_COURIER_DOC   = '54';  // Courier documentos
                                     const PRD_COURIER_MUESTRA = '108'; // Courier muestras
    /* Devoluciones (logística inversa) */
    const SVC_DEVOLUCION       = '31';
    const PRD_DEVOL_DOMICILIO  = '86';  // Nacional desde domicilio (def. devolución)
    const PRD_DEVOL_PICKUP     = '88';  // Nacional/Internacional desde Punto (req. pickupCentreCode)

    /* ------------------------------------------------------------------ *
     *  Dirección de Francobordo                                           *
     *  - Envíos salientes (pedidos): Francobordo es el REMITENTE (sender) *
     *  - Devoluciones (RMA):         Francobordo es el DESTINATARIO       *
     * ------------------------------------------------------------------ */
    const FB_NOMBRE   = 'Francobordo Articulos Nauticos SL';
    const FB_DIR      = 'Calle San Rafael 8';
    const FB_POBL     = 'Alcobendas';
    const FB_CP       = '28108';
    const FB_PAIS_ISO = 'ES';
    const FB_CONTACTO = 'Francobordo';
    const FB_TLFNO    = '916528858';
    const FB_EMAIL    = 'info@francobordo.com';

    /* Entorno por defecto. Cambiar a 'pro' cuando se valide en producción. */
    const DEFAULT_ENV = 'pre';

    /** @var string 'pre'|'pro' */
    protected $env;
    /** @var string base URL del entorno */
    protected $base;
    /** @var array{client_id:string,client_secret:string,username:string,password:string} */
    protected $creds;
    /** @var bool */
    protected $verifyTls = true;     // servicios.api(pre).seur.io tiene cert válido (Incapsula)
    /** @var int */
    protected $timeout = 30;
    /** @var array{token:string,exp:int}|null token cacheado (en memoria, por instancia) */
    protected $tok = null;
    /** @var array|null Última petición (debug) */
    public $lastRequest = null;
    /** @var array|null Última respuesta cruda normalizada (debug) */
    public $lastResponse = null;

    public function __construct($env = null) {
        if ($env === null) $env = self::DEFAULT_ENV;
        $this->env   = ($env === 'pro') ? 'pro' : 'pre';
        $this->base  = ($this->env === 'pro') ? self::BASE_PRO : self::BASE_PRE;
        $this->creds = self::tokenCreds($this->env);
    }

    public function getEnv()  { return $this->env; }
    public function getBase() { return $this->base; }

    /** Ajusta el timeout total (segundos). Útil para llamadas de cara al cliente. */
    public function setTimeout($s) { $this->timeout = max(1, (int) $s); return $this; }

    /* ================================================================== *
     *  OAuth2 — obtención y cacheo del token (Bearer JWT)                 *
     * ================================================================== */

    /**
     * Devuelve un token Bearer válido, cacheándolo según su 'expires_in'
     * (segundos). Devuelve null si no se pudo obtener.
     * @return string|null
     */
    public function getToken($force = false) {
        if (!$force && $this->tok && $this->tok['exp'] > (time() + 30)) {
            return $this->tok['token'];
        }
        $post = http_build_query(array(
            'grant_type'    => 'password',
            'client_id'     => $this->creds['client_id'],
            'client_secret' => $this->creds['client_secret'],
            'username'      => $this->creds['username'],
            'password'      => $this->creds['password'],
        ));
        $ch = curl_init($this->base . '/pic_token');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_CONNECTTIMEOUT => min(15, $this->timeout),
            CURLOPT_TIMEOUT        => $this->timeout,
        ));
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        $data = ($body === false) ? null : json_decode($body, true);

        $this->lastResponse = array('ok' => false, 'http' => $http, 'error' => $err,
                                    'raw' => ($body === false ? '' : $body), 'data' => $data);

        $token = is_array($data) ? ($data['access_token'] ?? ($data['idToken'] ?? null)) : null;
        if ($http >= 200 && $http < 300 && $token) {
            $secs = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
            if ($secs <= 0) $secs = 3600;
            $this->tok = array('token' => $token, 'exp' => time() + $secs);
            return $token;
        }
        return null;
    }

    /* ================================================================== *
     *  Núcleo HTTP — request autenticada (Bearer) con reintento           *
     * ================================================================== */

    /**
     * Llama a un endpoint de la API PIC con token Bearer.
     * Reintenta UNA vez re-pidiendo token si la API responde 401/403.
     * @param string     $path    p.ej. 'pic/v1/shipments'
     * @param array|null $payload body JSON (solo POST/PUT); null en GET
     * @param string     $method  'GET'|'POST'|'PUT'
     * @param array      $query   parámetros de query-string
     * @return array{ok:bool,http:int,error:string,raw:string,data:?array}
     */
    protected function request($path, ?array $payload = null, $method = 'POST', array $query = array()) {
        $attempt = 0;
        do {
            $token = $this->getToken($attempt > 0);
            if (!$token) {
                $r = $this->lastResponse ?: array('ok' => false, 'http' => 0, 'error' => 'token', 'raw' => '', 'data' => null);
                $r['ok']    = false;
                $r['error'] = 'No se pudo obtener token OAuth (' . ($r['http'] ?? '0') . '). ' . ($r['error'] ?? '');
                return $r;
            }
            $url = $this->base . '/' . ltrim($path, '/');
            if (!empty($query)) $url .= '?' . http_build_query($query);
            $json = ($payload === null) ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
            $this->lastRequest = array('url' => $url, 'method' => $method, 'payload' => $payload);

            $headers = array('Authorization: Bearer ' . $token, 'Accept: application/json');
            if ($json !== null) $headers[] = 'Content-Type: application/json';

            $ch = curl_init($url);
            $opts = array(
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
                CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
                CURLOPT_CONNECTTIMEOUT => min(15, $this->timeout),
                CURLOPT_TIMEOUT        => $this->timeout,
            );
            if ($json !== null) $opts[CURLOPT_POSTFIELDS] = $json;
            curl_setopt_array($ch, $opts);
            $body = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            $data = ($body === false) ? null : json_decode($body, true);

            $result = array(
                'ok'    => ($http >= 200 && $http < 300),
                'http'  => $http,
                'error' => $err,
                'raw'   => ($body === false ? '' : $body),
                'data'  => $data,
            );
            $this->lastResponse = $result;

            if (($http === 401 || $http === 403) && $attempt === 0) {
                $this->tok = null;
                $attempt++;
                continue;
            }
            return $result;
        } while ($attempt <= 1);

        return $this->lastResponse;
    }

    /* ================================================================== *
     *  Cities — validación CP/población                                   *
     * ================================================================== */

    /** Devuelve las poblaciones de un CP. Útil para validar CP↔población antes de grabar.
     *  OJO: es GET (no POST — el POST da 404 "No Mapping Rule matched"). Spec OpenAPI 2026-06-09. */
    public function cities($postalCode, $countryCode = 'ES', $cityName = null) {
        $q = array('countryCode' => (string) $countryCode, 'postalCode' => (string) $postalCode);
        if ($cityName !== null && $cityName !== '') $q['cityName'] = (string) $cityName;
        return $this->request('pic/v1/cities', null, 'GET', $q);
    }

    /** Puntos de recogida SEUR (2shop/PUDO) cercanos a un CP/población. Devuelve dirección,
     *  horarios e ID del punto (pickupCentreCode) para pintar el mapa y para envíos 2shop. */
    public function pickups($postalCode, $countryCode = 'ES', $cityName = null) {
        $q = array('countryCode' => (string) $countryCode, 'postalCode' => (string) $postalCode);
        if ($cityName !== null && $cityName !== '') $q['cityName'] = (string) $cityName;
        return $this->request('pic/v1/pickups', null, 'GET', $q);
    }

    /** Pares producto/servicio disponibles entre dos unidades de negocio para una población.
     *  cityCode = código de población (de cities()). */
    public function shipMethods($cityCode, $originBusinessUnit = self::BUSINESS_UNIT, $destinationBusinessUnit = null) {
        return $this->request('pic/v1/ship-methods', null, 'GET', array(
            'originBusinessUnit'      => (string) $originBusinessUnit,
            'destinationBusinessUnit' => (string) ($destinationBusinessUnit ?? $originBusinessUnit),
            'cityCode'                => (string) $cityCode,
        ));
    }

    /* ================================================================== *
     *  Shipments — grabación de envíos                                    *
     * ================================================================== */

    /**
     * Graba un envío. $shipment es el objeto completo (ver envioDesdePedido()
     * / devolucionDesdeRma() para construirlo). Respuesta: trae 'shipmentCode'
     * (→ etiqueta/tracking). Se normaliza en extraerShipmentCode().
     */
    public function createShipment(array $shipment) {
        return $this->request('pic/v1/shipments', $shipment, 'POST');
    }

    /**
     * Desenvuelve el "sobre" de la API: las respuestas OK vienen como
     * { "data": {...} } y los errores como { "errors": [...] }. Devuelve el
     * contenido útil (el interior de 'data') o el propio cuerpo si no hay sobre.
     * @return array|null
     */
    public static function payload(array $resp) {
        $d = $resp['data'] ?? null;
        if (!is_array($d)) return null;
        if (isset($d['data']) && is_array($d['data'])) return $d['data'];
        return $d;
    }

    /** Anula uno o varios envíos por shipmentCode. Resp: {data:[{expeditionCode,description}]}. */
    public function cancelShipment($shipmentCode) {
        $codes = is_array($shipmentCode) ? array_values($shipmentCode) : array((string) $shipmentCode);
        return $this->request('pic/v1/shipments/cancel', array('codes' => $codes), 'POST');
    }

    /** Extrae el shipmentCode de una respuesta de createShipment (defensivo). */
    public static function extraerShipmentCode(array $resp) {
        $d = self::payload($resp);
        if (!is_array($d)) return null;
        if (!empty($d['shipmentCode'])) return (string) $d['shipmentCode'];
        if (!empty($d['ecbCode']))      return (string) $d['ecbCode'];
        if (isset($d[0]) && is_array($d[0]) && !empty($d[0]['shipmentCode'])) return (string) $d[0]['shipmentCode'];
        if (!empty($d['shipment']['shipmentCode'])) return (string) $d['shipment']['shipmentCode'];
        return null;
    }

    /** Extrae los códigos de bulto (ecbs / parcelNumbers) de una respuesta de envío. */
    public static function extraerBultos(array $resp) {
        $d = self::payload($resp);
        return array(
            'ecbs'          => (is_array($d) && !empty($d['ecbs']))          ? $d['ecbs']          : array(),
            'parcelNumbers' => (is_array($d) && !empty($d['parcelNumbers'])) ? $d['parcelNumbers'] : array(),
        );
    }

    /* ================================================================== *
     *  Labels — generación de etiquetas                                   *
     * ================================================================== */

    /**
     * Genera la etiqueta de un envío por su shipmentCode.
     * $type: 'PDF' | 'ZPL' (def. PDF). entity=SHIPMENTS, templateType=GEOLABEL.
     * Si la respuesta trae la etiqueta PDF en base64, se añade 'pdf_bin' (binario).
     * El texto crudo de la etiqueta (PDF base64 o ZPL) se expone en 'label'.
     */
    public function getLabel($shipmentCode, $type = 'PDF', array $opts = array()) {
        $q = array(
            'code'   => (string) $shipmentCode,
            'type'   => strtoupper($type),
            'entity' => (string) ($opts['entity'] ?? 'SHIPMENTS'),
        );
        // templateType SOLO aplica a SHIPMENTS: con COLLECTIONS provoca un 400
        // engañoso ("only allowed for 31/88 and import pickups").
        $tpl = $opts['templateType'] ?? ($q['entity'] === 'SHIPMENTS' ? 'GEOLABEL' : null);
        if ($tpl !== null && $tpl !== '') $q['templateType'] = (string) $tpl;
        // Devoluciones desde punto pickup: SEUR pide solicitar el QR (el cliente
        // lo muestra en el punto sin necesidad de imprimir).
        if (!empty($opts['qr'])) $q['qr'] = 'true';
        $r = $this->request('pic/v1/labels', null, 'GET', $q);

        // La respuesta puede ser JSON {data:[{pdf|label}]} / {label:..} / lista, o crudo.
        // Para multi-bulto, 'data' es una lista de etiquetas por bulto.
        $label = null;
        $d = self::payload($r);
        $r['labels'] = (is_array($d) && isset($d[0])) ? $d : array();
        if (is_array($d)) {
            $label = $d['label'] ?? ($d['pdf'] ?? ($d['file'] ?? null));
            if ($label === null && isset($d[0]) && is_array($d[0])) {
                $label = $d[0]['label'] ?? ($d[0]['pdf'] ?? null);
            }
        }
        if ($label === null && $r['raw'] !== '' && !is_array($r['data'])) {
            $label = $r['raw']; // cuerpo crudo (ZPL o base64 sin envolver en JSON)
        }
        $r['label'] = $label;
        // QR (devolución desde punto): viene por bulto en LabelsData.qr
        $r['qr'] = (is_array($d) && isset($d[0]['qr'])) ? $d[0]['qr'] : (is_array($d) && isset($d['qr']) ? $d['qr'] : null);

        if ($label && strtoupper($type) === 'PDF') {
            $bin = base64_decode($label, true);
            if ($bin !== false && strncmp($bin, '%PDF', 4) === 0) $r['pdf_bin'] = $bin;
        }
        return $r;
    }

    /* ================================================================== *
     *  Collections — recogidas / DEVOLUCIONES                             *
     *  (Email SEUR 2026-06-10: las devoluciones NO se graban en           *
     *  /shipments; llevan recogida implícita y van por /collections.)     *
     * ================================================================== */

    /**
     * Graba una recogida/devolución. $c = CollectionRequestBody (ver
     * devolucionRecogidaDesdeRma()). Respuesta data: {collectionRef('REC...'),
     * fRec, reference, ecbs[], parcelNumbers[]}. El collectionRef es el
     * "localizador" → etiqueta con getLabel($ref,'PDF',['entity'=>'COLLECTIONS']).
     */
    public function createCollection(array $c) {
        return $this->request('pic/v1/collections', $c, 'POST');
    }

    /** Extrae el localizador (collectionRef) de una respuesta de createCollection. */
    public static function extraerCollectionRef(array $resp) {
        $d = self::payload($resp);
        if (!is_array($d)) return null;
        if (!empty($d['collectionRef'])) return (string) $d['collectionRef'];
        if (isset($d[0]['collectionRef'])) return (string) $d[0]['collectionRef'];
        return null;
    }

    /** Anula una o varias recogidas por su collectionRef (REC...). */
    public function cancelCollection($collectionRef) {
        $codes = is_array($collectionRef) ? array_values($collectionRef) : array((string) $collectionRef);
        return $this->request('pic/v1/collections/cancel', array('codes' => $codes), 'POST');
    }

    /** ¿La anulación se puede dar por BUENA? Éxito HTTP, o error "suave" (el envío
     *  ya estaba anulado / no existe → equivale a anulado). Un error DURO (sigue
     *  vivo) devuelve false: NO se debe marcar cancelled_at (mismo criterio que el
     *  panel _admin/seur_envios.php). */
    public static function cancelOk(array $resp) {
        if (!empty($resp['ok'])) return true;
        // Error "suave" SOLO si viene del cuerpo de NEGOCIO (data[0].description).
        // NO usar primerError(): un 404/WAF de transporte ("not found") dejaría VIVO
        // el envío y lo marcaría anulado por error (reintroduciría el bug). Sin cuerpo
        // de negocio → false → se encola y reintenta.
        $d = self::payload($resp);
        if (!is_array($d) || empty($d[0]['description'])) return false;
        $desc = mb_strtolower((string) $d[0]['description']);
        return (strpos($desc, 'no existe') !== false || strpos($desc, 'ya anulad') !== false
             || strpos($desc, 'inexist') !== false || strpos($desc, 'not found') !== false);
    }

    /** Próximo día laborable (L-V) en formato Y-m-d. Las recogidas con
     *  label=true se realizan siempre al día siguiente (indicación SEUR). */
    public static function proximoDiaLaborable($desde = null) {
        $ts = $desde ? strtotime($desde) : time();
        do { $ts += 86400; } while ((int) date('N', $ts) >= 6);
        return date('Y-m-d', $ts);
    }

    /**
     * DEVOLUCIÓN de un RMA vía /collections.
     * $opts:
     *   tipo:  'domicilio' (31/86: recogida en casa del cliente) |
     *          'punto'     (31/88: el cliente deposita en punto SEUR; requiere pickupCentreCode)
     *   pickupCentreCode: pudoId del punto (obligatorio con tipo=punto)
     *   fecha: Y-m-d (def. próximo día laborable)
     *   label: bool (def. true → obtenemos etiqueta y se la enviamos al cliente;
     *          false → el repartidor lleva la etiqueta impresa)
     *   weight/width/height/length, observations
     */
    public static function devolucionRecogidaDesdeRma(array $rma, array $opts = array()) {
        $ref   = 'RMA' . str_pad((string) ($rma['id_rma'] ?? ''), 8, '0', STR_PAD_LEFT);
        $punto = (($opts['tipo'] ?? 'domicilio') === 'punto');

        $senderAddr = array(
            'streetName' => trim((string) ($rma['customers_street_address'] ?? '') . ' ' . (string) ($rma['customers_suburb'] ?? '')),
            'cityName'   => trim((string) ($rma['customers_city'] ?? '')),
            'postalCode' => trim((string) ($rma['customers_postcode'] ?? '')),
            'country'    => 'ES',
        );
        if ($punto && !empty($opts['pickupCentreCode'])) {
            $senderAddr['pickupCentreCode'] = (string) $opts['pickupCentreCode'];
        }

        return array(
            'serviceCode'    => (int) ($opts['service'] ?? self::SVC_DEVOLUCION),               // 31
            'productCode'    => (int) ($opts['product'] ?? ($punto ? self::PRD_DEVOL_PICKUP : self::PRD_DEVOL_DOMICILIO)), // 88 / 86
            'ref'            => $ref,
            'collectionDate' => (string) ($opts['fecha'] ?? self::proximoDiaLaborable()),
            'label'          => (bool) ($opts['label'] ?? true),
            'payer'          => 'ORD',
            'customer'       => array(   // cuenta que paga = Francobordo
                'accountNumber' => self::CCC,
                'name'          => self::FB_NOMBRE,
                'idNumber'      => self::NIF,
                'phone'         => self::FB_TLFNO,
                'email'         => self::FB_EMAIL,
            ),
            'sender'         => array(   // quien devuelve = cliente
                'name'        => trim((string) ($rma['customers_name'] ?? '')),
                'phone'       => trim((string) ($rma['customers_telephone'] ?? '')),
                'email'       => trim((string) ($rma['customers_email_address'] ?? '')),
                'contactName' => trim((string) ($rma['customers_name'] ?? '')),
                'address'     => $senderAddr,
            ),
            'receiver'       => array(   // destino = Francobordo
                // El nº de RMA va PRIMERO en el NOMBRE y en el "Att:" para que salga GRANDE
                // en el bloque de destinatario de la etiqueta (la ref RMA000000NN ya salía,
                // pero en letra pequeña abajo) → identificación rápida del bulto al recibirlo.
                // OJO: SEUR limita receiver.name a 50 caracteres (400 si se pasa) → substr.
                'name'        => substr('RMA N. ' . (int) ($rma['id_rma'] ?? 0) . ' - ' . self::FB_NOMBRE, 0, 50),
                'phone'       => self::FB_TLFNO,
                'contactName' => 'RMA N. ' . (int) ($rma['id_rma'] ?? 0),
                'email'       => self::FB_EMAIL,
                'address'     => array(
                    'streetName' => self::FB_DIR,
                    'cityName'   => self::FB_POBL,
                    'postalCode' => self::FB_CP,
                    'country'    => self::FB_PAIS_ISO,
                ),
            ),
            'parcels'        => self::parcels($opts, $ref),
            // El spec los marca como obligatorios (numéricos); sin valor declarado → 0.
            'declaredValue'      => (float) ($opts['declaredValue'] ?? 0),
            'insuredValue'       => (float) ($opts['insuredValue'] ?? 0),
            'cashOnDeliveryValue' => (float) ($opts['cashOnDeliveryValue'] ?? 0),
            'observations'   => (string) ($opts['observations'] ?? ('Devolucion RMA ' . ($rma['id_rma'] ?? ''))),
        );
    }

    /* ================================================================== *
     *  Manifiesto de entrega (fin de día)                                 *
     * ================================================================== */

    /**
     * Manifiesto de los envíos grabados (documento que firma el repartidor).
     * $dateFrom/$dateTo: 'YYYY-MM-DD' (se convierte a rango ISO del día) o datetime
     * ISO completo 'YYYY-MM-DDTHH:mm:ssZ'. Por defecto, HOY.
     * Reglas API (validadas en PRE 2026-06-09): nif + (ccc O franchise, no ambos);
     * fechas obligatorias en formato datetime ISO. La respuesta es el PDF en
     * base64 como cuerpo crudo → se expone decodificado en 'pdf_bin'.
     */
    public function deliveryManifest($dateFrom = null, $dateTo = null) {
        if ($dateFrom === null) $dateFrom = date('Y-m-d');
        if ($dateTo   === null) $dateTo   = date('Y-m-d');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateFrom)) $dateFrom .= 'T00:00:00Z';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateTo))   $dateTo   .= 'T23:59:59Z';
        $q = array(
            'nif'      => self::NIF,
            'ccc'      => self::accountNumber(),   // ccc XOR franchise: con ambos la API da 400
            'dateFrom' => (string) $dateFrom,
            'dateTo'   => (string) $dateTo,
        );
        $r = $this->request('pic/v1/shipments/delivery-manifest', null, 'GET', $q);

        // Normalizar posible PDF base64 (en sobre data o crudo).
        $d = self::payload($r);
        $b64 = null;
        if (is_array($d)) {
            $b64 = $d['pdf'] ?? ($d['file'] ?? ($d['manifest'] ?? null));
            if ($b64 === null && isset($d[0]) && is_array($d[0])) $b64 = $d[0]['pdf'] ?? ($d[0]['file'] ?? null);
        } elseif ($r['raw'] !== '' && !is_array($r['data'])) {
            $b64 = $r['raw'];
        }
        if (is_string($b64)) {
            $bin = base64_decode($b64, true);
            if ($bin !== false && strncmp($bin, '%PDF', 4) === 0) $r['pdf_bin'] = $bin;
        }
        return $r;
    }

    /* ================================================================== *
     *  Tracking — seguimiento                                             *
     * ================================================================== */

    /**
     * Tracking simplificado (último estado) por referencia del envío.
     * En PRE solo devuelve el estado inicial.
     */
    public function trackingSimplified($ref, $refType = 'REFERENCE', $filtros = true) {
        $q = array('ref' => (string) $ref, 'refType' => (string) $refType);
        if ($filtros) {
            // Filtros de cuenta: encuentran nuestros envios API y los nacionales de la
            // integracion vieja; los INTERNACIONALES solo aparecen SIN filtros (la red
            // los re-registra bajo la businessUnit de destino). Ver cron_seur_tracking.
            $q += array('idNumber' => self::NIF, 'accountNumber' => self::accountNumber(), 'businessUnit' => self::BUSINESS_UNIT);
        }
        return $this->request('pic/v1/tracking-services/simplified', null, 'GET', $q);
    }

    /** Tracking extendido (todos los estados por los que ha pasado el envío). */
    public function trackingExtended($ref, $refType = 'REFERENCE', $filtros = true) {
        $q = array('ref' => (string) $ref, 'refType' => (string) $refType);
        if ($filtros) {
            // Filtros de cuenta: encuentran nuestros envios API y los nacionales de la
            // integracion vieja; los INTERNACIONALES solo aparecen SIN filtros (la red
            // los re-registra bajo la businessUnit de destino). Ver cron_seur_tracking.
            $q += array('idNumber' => self::NIF, 'accountNumber' => self::accountNumber(), 'businessUnit' => self::BUSINESS_UNIT);
        }
        return $this->request('pic/v1/tracking-services/extended', null, 'GET', $q);
    }

    /* ================================================================== *
     *  Helpers — construir el objeto shipment                             *
     * ================================================================== */

    /** Devuelve [serviceCode, productCode] según el país de destino (ES = nacional). */
    public static function serviceForCountry($country) {
        $c = strtoupper(trim((string) $country));
        if ($c === '' || $c === 'ES') return array(self::SVC_NACIONAL, self::PRD_NACIONAL);
        return array(self::SVC_INTERNAC, self::PRD_INTERNAC);
    }

    /**
     * Construye el bloque de parcels a partir de opciones.
     * Unidades: weight en kg, width/height/length en cm.
     * $opts['bultos'] (def. 1): nº de bultos; con bultos>1, 'weight' se trata como
     * peso TOTAL y se reparte a partes iguales entre los bultos.
     */
    protected static function parcels(array $opts, $ref) {
        $n = max(1, (int) ($opts['bultos'] ?? 1));
        $w = (float) ($opts['weight'] ?? 1);
        if ($w <= 0) $w = 1;
        $per = ($n > 1) ? max(0.1, round($w / $n, 3)) : $w;
        $out = array();
        for ($i = 1; $i <= $n; $i++) {
            $out[] = array(
                'weight'        => $per,
                'width'         => (float) ($opts['width']  ?? 10),
                'height'        => (float) ($opts['height'] ?? 10),
                'length'        => (float) ($opts['length'] ?? 10),
                'packReference' => (string) ($ref . '_' . $i),
            );
        }
        return $out;
    }

    /**
     * Envío SALIENTE de un pedido: remitente = Francobordo, destinatario = cliente.
     * $dest admite: name, idNumber, phone, contactName, email, streetName,
     *               cityName, postalCode, country.
     * $opts admite: weight/width/height/length, ref (def F{orders_id}), service/product,
     *               observations.
     */
    public static function envioDesdePedido(array $dest, array $opts = array()) {
        $ref = (string) ($opts['ref'] ?? ('F' . ($opts['orders_id'] ?? '')));
        $country = (string) ($dest['country'] ?? 'ES');
        list($svc, $prd) = self::serviceForCountry($country);

        $recvAddr = array(
            'streetName' => trim((string) ($dest['streetName'] ?? '')),
            'cityName'   => trim((string) ($dest['cityName'] ?? '')),
            'postalCode' => trim((string) ($dest['postalCode'] ?? '')),
            'country'    => strtoupper(trim($country)),
        );
        // Entrega 2shop (punto de recogida): obligatorio pickupCentreCode en receiver.
        if (!empty($dest['pickupCentreCode'])) $recvAddr['pickupCentreCode'] = (string) $dest['pickupCentreCode'];

        return array(
            'serviceCode' => (string) ($opts['service'] ?? $svc),
            'productCode' => (string) ($opts['product'] ?? $prd),
            'ref'         => $ref,
            'customer'    => array(
                'accountNumber' => self::CCC,
                'name'          => self::FB_NOMBRE,
                'idNumber'      => self::NIF,
                'email'         => self::FB_EMAIL,
            ),
            'sender'      => array(
                'name'        => self::FB_NOMBRE,
                'ccc'         => self::CCC,
                'phone'       => self::FB_TLFNO,
                'email'       => self::FB_EMAIL,
                'contactName' => self::FB_CONTACTO,
                'address'     => array(
                    'streetName' => self::FB_DIR,
                    'cityName'   => self::FB_POBL,
                    'postalCode' => self::FB_CP,
                    'country'    => self::FB_PAIS_ISO,
                ),
            ),
            'receiver'    => array(
                'name'        => trim((string) ($dest['name'] ?? '')),
                'idNumber'    => (string) ($dest['idNumber'] ?? ''),
                'phone'       => trim((string) ($dest['phone'] ?? '')),
                'contactName' => trim((string) ($dest['contactName'] ?? ($dest['name'] ?? ''))),
                'email'       => trim((string) ($dest['email'] ?? '')),
                'address'     => $recvAddr,
            ),
            'parcels'      => self::parcels($opts, $ref),
            'observations' => (string) ($opts['observations'] ?? ''),
        ) + (!empty($opts['dSat']) ? array('dSat' => true) : array());
        // dSat = servicio complementario "Entrega en Sábado" (+11,42 € s/tarifa). Desde el
        // contrato de julio-2026 el servicio dedicado 57 (SEUR SATURDAY) YA NO EXISTE en la
        // cuenta (ship-methods 2026-07-24): el sábado se contrata con dSat sobre un servicio
        // preferente con saturdayDeliveryCode=S (9/2 SEUR 13:30 ó 3/2 SEUR 10; el 31/2 NO).
    }

    /**
     * DEVOLUCIÓN (logística inversa) de un RMA: remitente = cliente que devuelve,
     * destinatario = Francobordo. $opts: weight/dims, service/product (def nacional).
     */
    public static function devolucionDesdeRma(array $rma, array $opts = array()) {
        $ref = 'RMA' . str_pad((string) ($rma['id_rma'] ?? ''), 8, '0', STR_PAD_LEFT);
        // Devolución nacional desde domicilio = 31/86 (email SEUR 2026-06-09).
        // Desde punto (PUDO) = 31/88 + pickupCentreCode (pasar $opts['pickup']=true + $opts['pickupCentreCode']).
        $pickup = !empty($opts['pickup']);
        $svc = self::SVC_DEVOLUCION;
        $prd = $pickup ? self::PRD_DEVOL_PICKUP : self::PRD_DEVOL_DOMICILIO;

        $senderAddr = array(
            'streetName' => trim((string) ($rma['customers_street_address'] ?? '') . ' ' . (string) ($rma['customers_suburb'] ?? '')),
            'cityName'   => trim((string) ($rma['customers_city'] ?? '')),
            'postalCode' => trim((string) ($rma['customers_postcode'] ?? '')),
            'country'    => 'ES',
        );
        // En devolución desde Punto, el origen es un punto SEUR cercano al cliente.
        if ($pickup && !empty($opts['pickupCentreCode'])) $senderAddr['pickupCentreCode'] = (string) $opts['pickupCentreCode'];

        return array(
            'serviceCode' => (string) ($opts['service'] ?? $svc),
            'productCode' => (string) ($opts['product'] ?? $prd),
            'ref'         => $ref,
            'customer'    => array(   // cuenta que factura el porte = Francobordo
                'accountNumber' => self::CCC,
                'name'          => self::FB_NOMBRE,
                'idNumber'      => self::NIF,
                'email'         => self::FB_EMAIL,
            ),
            'sender'      => array(   // remitente físico = cliente que devuelve
                'name'        => trim((string) ($rma['customers_name'] ?? '')),
                'accountNumber' => self::CCC,
                'phone'       => trim((string) ($rma['customers_telephone'] ?? '')),
                'email'       => trim((string) ($rma['customers_email_address'] ?? '')),
                'contactName' => trim((string) ($rma['customers_name'] ?? '')),
                'address'     => $senderAddr,
            ),
            'receiver'    => array(   // destinatario = Francobordo
                'name'        => self::FB_NOMBRE,
                'idNumber'    => self::NIF,
                'phone'       => self::FB_TLFNO,
                'contactName' => self::FB_CONTACTO,
                'email'       => self::FB_EMAIL,
                'address'     => array(
                    'streetName' => self::FB_DIR,
                    'cityName'   => self::FB_POBL,
                    'postalCode' => self::FB_CP,
                    'country'    => self::FB_PAIS_ISO,
                ),
            ),
            'parcels'      => self::parcels($opts, $ref),
            'observations' => 'Devolucion RMA ' . ($rma['id_rma'] ?? ''),
        );
    }

    /**
     * Flujo completo: graba el envío + genera la etiqueta en una llamada lógica.
     * Devuelve array con ok, shipmentCode, label (crudo), pdf_bin (si PDF) y las
     * respuestas crudas 'shipment' y 'label' para depurar.
     */
    public function grabarConEtiqueta(array $shipment, $labelType = 'PDF') {
        $ship = $this->createShipment($shipment);
        $out  = array('ok' => false, 'shipmentCode' => null, 'label' => null,
                      'pdf_bin' => null, 'shipment' => $ship, 'label_resp' => null, 'error' => '');

        $code = self::extraerShipmentCode($ship);
        if (!$ship['ok'] || !$code) {
            $out['error'] = self::primerError($ship);
            return $out;
        }
        $out['shipmentCode'] = $code;

        $lab = $this->getLabel($code, $labelType);
        $out['label_resp'] = $lab;
        if ($lab['ok'] && !empty($lab['label'])) {
            $out['label']   = $lab['label'];
            $out['pdf_bin'] = $lab['pdf_bin'] ?? null;
            $out['ok']      = true;
        } else {
            $out['error'] = self::primerError($lab) ?: ('Etiqueta no generada (HTTP ' . $lab['http'] . ')');
        }
        return $out;
    }

    /** Extrae un mensaje de error legible de una respuesta de la API. */
    public static function primerError(array $resp) {
        $d = $resp['data'] ?? null;
        if (is_array($d)) {
            // Forma de error de SEUR: { "errors": [ { title, status, detail } ] }
            if (!empty($d['errors'][0])) {
                $e = $d['errors'][0];
                if (is_array($e)) {
                    $msg = trim(((string) ($e['title'] ?? '')) . ' ' . ((string) ($e['detail'] ?? ($e['message'] ?? ''))));
                    if ($msg !== '') return $msg;
                }
            }
            if (!empty($d['message']))      return (string) $d['message'];
            if (!empty($d['error']))        return is_array($d['error']) ? json_encode($d['error']) : (string) $d['error'];
            if (!empty($d['errorMessage'])) return (string) $d['errorMessage'];
        }
        if (!empty($resp['error'])) return (string) $resp['error'];
        return 'Error HTTP ' . ($resp['http'] ?? '0');
    }
}
