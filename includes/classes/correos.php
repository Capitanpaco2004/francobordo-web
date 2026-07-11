<?php
/**
 * Cliente REST para las APIs de Correos (de España).
 *
 * Cubre: obtención de token OAuth2 (CorreosID), pre-registro de envíos
 * (Preregister), generación de etiquetas (Labels) y anulación. El seguimiento
 * (Trackpub) se añadirá en la fase de tracking.
 *
 * Réplica del patrón de includes/classes/correos_express.php, pero el transporte
 * cambia: Correos usa OAuth2 Bearer (CorreosID) en lugar de HTTP Basic.
 *
 * Auth (doc "Obtención y uso de Token para acceder a las APIs de Correos"):
 *   POST https://apioauthcid.correos.es/Api/Authorize/Token
 *   Content-Type: application/x-www-form-urlencoded ; Accept-Language: es
 *   body: grant_type=client_credentials & client_id & client_secret & scope=<api>
 *   resp: { idToken, tokenType:"Bearer", expiresIn:<minutos> }   (sin refresh token)
 *   Las llamadas a la API van con cabecera  Authorization: Bearer <idToken>.
 *   Estrategia de renovación: re-pedir token al caducar o ante HTTP 401/403.
 *
 * Entornos: NO hay sandbox separado; ambas apps van a api1.correos.es (Producción).
 *   'pruebas' = app de tier PRUEBAS (solo preregister) · 'pro' = app de Producción.
 * El pre-registro no se factura hasta que el paquete entra físicamente en la red,
 * así que se puede probar creando pre-registros y no usándolos.
 *
 * Credenciales y notas de integración: ver memoria francobordo_correos_api.
 */
class correos {

    /* ------------------------------------------------------------------ *
     *  DOS pares de credenciales (modelo confirmado 2026-06-08):          *
     *   1) CorreosID (identidad.correos.es) → SOLO para obtener el TOKEN  *
     *      en apioauthcid. App "Francobordo API". client_id = GUID.       *
     *   2) Portal Desarrolladores (developers.correos.es) → cabeceras     *
     *      client_id/client_secret de la API (Client ID Enforcement).     *
     *  Los valores viven en correos_credentials.php (gitignored por el    *
     *  patrón "credentials" del .gitignore — NO entra en el mirror de     *
     *  GitHub), cargado al final de este fichero. Define:                 *
     *  CORREOS_CID_TOKEN_ID/SECRET, CORREOS_API_PRUEBAS_ID/SECRET,        *
     *  CORREOS_API_PRO_ID/SECRET.                                         *
     * ------------------------------------------------------------------ */

    const TOKEN_URL = 'https://apioauthcid.correos.es/Api/Authorize/Token';

    /* Bases de las APIs (todas en Producción, api1.correos.es) */
    const BASE_PREREGISTER = 'https://api1.correos.es/admissions/preregister/api/v1/';
    const BASE_LABELS      = 'https://api1.correos.es/support/labels/api/v1/';

    /* Scope por API. El string exacto está PENDIENTE de confirmar tras la
     * activación de la app por el comercial (ahora mismo el token da 403
     * "No autorizado" porque el acceso no está activado todavía). Se asume el
     * nombre de la API tal cual figura en el portal. */
    /* applicationCode reales confirmados (token 200) 2026-06-10:
     * AP3=preregister, LBS=labels, EXP=trackpub, RCG=requests/recogidas. */
    const SCOPE_PREREGISTER = 'AP3';
    const SCOPE_LABELS      = 'LBS';
    const SCOPE_TRACKPUB    = 'EXP';
    const SCOPE_REQUESTS    = 'RCG';

    /* ------------------------------------------------------------------ *
     *  Datos de cuenta / contrato                                         *
     * ------------------------------------------------------------------ */
    const CONTRACT      = '54002749';     // contractNumber
    const LABELLER      = '10BC';         // labellerCode
    const CLIENT_NUMBER = '80123054';     // clientNumber oficial del contrato (Almudena 2026-06-10) — OBLIGATORIO.
                                          // OJO: en facturas aparece con prefijo 99 (9980123054); ambos validan, usar el oficial.
    const LABEL_APPLICATION = 'FRANCOBORDO'; // campo 'application' (obligatorio en Labels)

    /* Producto de devolución (Anexo I). modalidad de envío = DOURUA.
     * PAAZE = Paq Retorno ; PAAZV = Paq Retorno Premium. Cuál está contratado:
     * PENDIENTE de confirmar (Almudena). */
    const PROD_RETORNO        = 'PAAZE';
    const PROD_RETORNO_PREM   = 'PAAZV';
    const DELIVERY_DEVOLUCION = 'DOURUA';

    // Codigo arancelario (HS/TARIC) por defecto para el DUA de Canarias/Ceuta/Melilla.
    // Generico (otros articulos de plastico); el operador puede afinarlo por producto mas adelante.
    const TARIFA_ADUANA_DEF = '39269097';

    /* ------------------------------------------------------------------ *
     *  Dirección de Francobordo                                           *
     *  - Devoluciones (RMA): Francobordo es el DESTINATARIO (addressee)   *
     *  - Envíos salientes:   Francobordo es el REMITENTE (sender)         *
     * ------------------------------------------------------------------ */
    const FB_NOMBRE   = 'Francobordo Articulos Nauticos SL';
    const FB_DIR      = 'Calle San Rafael 8';
    const FB_POBL     = 'Alcobendas';
    const FB_CP       = '28108';
    const FB_PROV     = '28';           // Madrid (Anexo V) — opcional
    const FB_PAIS_ISO = 'ESP';          // ⚠️ ISO de 3 letras (no 'ES'), si no: error 1007/1113/2019
    const FB_CONTACTO = 'Francobordo';
    const FB_TLFNO    = '916528858';    // Almacén devoluciones (Alcobendas)
    const FB_EMAIL    = 'info@francobordo.com';
    const FB_NIF      = 'B82574690';    // CIF Francobordo (doiType 10)

    /* Entorno por defecto. 'pro' desde el E2E validado 2026-06-10: la app del
     * Portal de PRUEBAS solo está suscrita a preregister (Labels rechazaría sus
     * cabeceras), y el preregistro en pro es anulable y no se factura hasta que
     * el paquete entra en la red. 'pruebas' queda para tests de preregistro. */
    const DEFAULT_ENV = 'pro';

    /* ⚠️ El WAF (CloudFront) de api1.correos.es bloquea con 403 "Request
     * blocked" las peticiones SIN User-Agent (el curl de PHP no manda ninguno
     * por defecto). Cualquier UA no vacío pasa. Detectado 2026-06-10 en nic1. */
    const USER_AGENT = 'Francobordo-osCommerce/1.0 (+https://www.francobordo.com)';

    /** @var string 'pruebas'|'pro' */
    protected $env;
    /** @var array{client_id:string,client_secret:string} */
    protected $creds;
    /** @var bool */
    protected $verifyTls = true;       // api1.correos.es tiene certificado válido
    /** @var int */
    protected $timeout = 30;
    /** @var array<string,array{token:string,exp:int}> token cacheado por scope */
    protected $tokens = array();
    /** @var array|null Última petición (debug) */
    public $lastRequest = null;
    /** @var array|null Última respuesta cruda normalizada (debug) */
    public $lastResponse = null;

    public function __construct($env = null) {
        if ($env === null) $env = self::DEFAULT_ENV;
        $this->env   = ($env === 'pro') ? 'pro' : 'pruebas';
        $this->creds = ($this->env === 'pro')
            ? array('client_id' => CORREOS_API_PRO_ID,     'client_secret' => CORREOS_API_PRO_SECRET)
            : array('client_id' => CORREOS_API_PRUEBAS_ID, 'client_secret' => CORREOS_API_PRUEBAS_SECRET);
    }

    public function getEnv() { return $this->env; }

    /** Ajusta el timeout total (segundos). Útil para llamadas de cara al cliente. */
    public function setTimeout($s) { $this->timeout = max(1, (int) $s); return $this; }

    /* ================================================================== *
     *  OAuth2 — obtención y cacheo del token (por scope)                  *
     * ================================================================== */

    /**
     * Devuelve un token Bearer válido para el scope dado, cacheándolo según
     * su 'expiresIn' (minutos). Devuelve null si no se pudo obtener.
     * @return string|null
     */
    public function getToken($scope, $force = false) {
        if (!$force && isset($this->tokens[$scope]) && $this->tokens[$scope]['exp'] > (time() + 15)) {
            return $this->tokens[$scope]['token'];
        }
        // El token se pide con las credenciales de CorreosID (NO las del Portal).
        $post = http_build_query(array(
            'grant_type'    => 'client_credentials',
            'client_id'     => CORREOS_CID_TOKEN_ID,
            'client_secret' => CORREOS_CID_TOKEN_SECRET,
            'scope'         => $scope,
        ));
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Accept-Language: es',
            ),
            CURLOPT_USERAGENT      => self::USER_AGENT,
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

        // El doc usa "idToken"; se contempla "access_token" por compatibilidad.
        $token = null;
        if (is_array($data)) {
            $token = $data['idToken'] ?? ($data['access_token'] ?? null);
        }
        if ($http >= 200 && $http < 300 && $token) {
            $mins = isset($data['expiresIn']) ? (int) $data['expiresIn'] : 30;
            if ($mins <= 0) $mins = 30;
            $this->tokens[$scope] = array('token' => $token, 'exp' => time() + $mins * 60);
            return $token;
        }
        return null;
    }

    /* ================================================================== *
     *  Núcleo HTTP — request autenticada (Bearer) con reintento           *
     * ================================================================== */

    /**
     * Llama a un endpoint de una API de Correos con token Bearer del scope dado.
     * Reintenta UNA vez re-pidiendo token si la API responde 401/403.
     * @return array{ok:bool,http:int,error:string,raw:string,data:?array}
     */
    protected function request($base, $path, ?array $payload = null, $scope = null, $method = 'POST') {
        $attempt = 0;
        do {
            $token = $this->getToken($scope, $attempt > 0);
            if (!$token) {
                // No hay token: devolvemos lo último que dijo el servidor de auth.
                $r = $this->lastResponse ?: array('ok' => false, 'http' => 0, 'error' => 'token', 'raw' => '', 'data' => null);
                $r['ok'] = false;
                $r['error'] = 'No se pudo obtener token OAuth (' . ($r['http'] ?? '0') . '). ' . ($r['error'] ?? '');
                return $r;
            }
            $url  = $base . ltrim($path, '/');
            $json = ($payload === null) ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
            $this->lastRequest = array('url' => $url, 'method' => $method, 'payload' => $payload);

            // Preregister/Labels combinan DOS políticas de seguridad: Validación
            // JWT (Authorization: Bearer, token de CorreosID) + Client ID Enforcement
            // (cabeceras client_id/client_secret de la app del Portal). Se envían
            // AMBAS (doc "Uso OAuth 2.0", paso 3): el Bearer viene de CID_TOKEN y las
            // cabeceras de $this->creds (API_PRUEBAS/API_PRO según entorno).
            $headers = array(
                'Authorization: Bearer ' . $token,
                'client_id: ' . $this->creds['client_id'],
                'client_secret: ' . $this->creds['client_secret'],
                'Accept: application/json',
            );
            if ($json !== null) $headers[] = 'Content-Type: application/json; charset=UTF-8';

            $ch = curl_init($url);
            $opts = array(
                CURLOPT_CUSTOMREQUEST   => $method,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_HTTPHEADER      => $headers,
                CURLOPT_USERAGENT       => self::USER_AGENT,
                CURLOPT_SSL_VERIFYPEER  => $this->verifyTls,
                CURLOPT_SSL_VERIFYHOST  => $this->verifyTls ? 2 : 0,
                CURLOPT_CONNECTTIMEOUT  => min(15, $this->timeout),
                CURLOPT_TIMEOUT         => $this->timeout,
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

            // Token caducado/incorrecto para esta API → re-pedir y reintentar una vez.
            if (($http === 401 || $http === 403) && $attempt === 0) {
                unset($this->tokens[$scope]);
                $attempt++;
                continue;
            }
            return $result;
        } while ($attempt <= 1);

        return $this->lastResponse;
    }

    /* ================================================================== *
     *  Preregister                                                        *
     * ================================================================== */

    /**
     * Pre-registra uno o varios envíos. $shipments es la lista de objetos
     * "shipment" (ver devolucionDesdeRma() para construir uno de devolución).
     * Respuesta data: { fileIdentifier, result('1'=OK), shipments:[{ shipmentCode,
     * packages:[{packageId,packageCode}], validationErrorCount, error:[...] }] }.
     */
    public function preregister(array $shipments, $errorLang = 'spa') {
        return $this->request(self::BASE_PREREGISTER, 'delivery', array(
            'errorCodeLanguage' => $errorLang,
            'shipments'         => array_values($shipments),
        ), self::SCOPE_PREREGISTER);
    }

    /** Anula un envío pre-registrado por su packageCode.
     * El endpoint es INESTABLE: devuelve transitorios "Unable to process payload."
     * y "Ha ocurrido un error al tratar de anular el preregistro" que se resuelven
     * solos reintentando (2026-06-10: éxito al 3er intento). Se reintenta hasta 4
     * veces con pausa de 2s; se corta en cuanto llega "Anulado con éxito".
     * Éxito: data = {message:"Preregistro Anulado con éxito", errors:[]}. */
    public function annulment($packageCode, $errorLang = 'spa', $maxTries = 4) {
        $payload = array('errorCodeLanguage' => $errorLang, 'packageCode' => (string) $packageCode);
        $r = null;
        $n = max(1, (int) $maxTries);   // web: pocos intentos (no bloquear); cron: reintenta cada hora
        for ($i = 0; $i < $n; $i++) {
            if ($i > 0) sleep(2);
            $r = $this->request(self::BASE_PREREGISTER, 'delivery/annulment', $payload, self::SCOPE_PREREGISTER);
            $msg = is_array($r['data'] ?? null)
                 ? (string) ($r['data']['message'] ?? ($r['data']['error'] ?? ''))
                 : '';
            if (stripos($msg, 'xito') !== false) break;   // "Anulado con éxito"
        }
        return $r;
    }

    /** ¿La respuesta de annulment() es un éxito? */
    public static function annulmentOk(array $resp) {
        $msg = is_array($resp['data'] ?? null) ? (string) ($resp['data']['message'] ?? '') : '';
        return stripos($msg, 'xito') !== false;
    }

    /* ================================================================== *
     *  Labels                                                             *
     * ================================================================== */

    /**
     * Genera la(s) etiqueta(s). ⚠️ GOTCHAS validados 2026-06-10:
     *  - `print.shipments` espera el **packageCode** (NO el shipmentCode); con
     *    shipmentCode da 404 "Uno o más paquetes no pertenecen al oid/eid".
     *  - `application` es OBLIGATORIO (string libre); si falta → "campos obligatorios".
     *  - labelPrintMode=2 (etiquetadora) dio HTTP 500; el combo validado es
     *    documentationType=0 + labelPrintMode=1 (A4) + labelOrderType=2.
     * $opts: documentationType (0=todo[def],1=etiqueta,2=CN22/23), labelFormat
     * (1=XML,2=PDF[def],3=ZPL), labelPrintMode (1=A4[def],2=etiquetadora), application.
     * Si la respuesta trae PDF en base64, se añade 'pdf_bin' (binario) al array.
     */
    public function getLabel(array $packageCodes, array $opts = array()) {
        $print = array(
            'shipments'                 => array_values($packageCodes),   // ¡packageCode!
            'labelFormat'               => (int) ($opts['labelFormat'] ?? 2),
            'labelPrintMode'            => (int) ($opts['labelPrintMode'] ?? 1),
            'labelOrderType'            => (int) ($opts['labelOrderType'] ?? 2),
            'labelPrintInitialPosition' => (int) ($opts['labelPrintInitialPosition'] ?? 1),
        );
        $payload = array(
            'application'       => (string) ($opts['application'] ?? self::LABEL_APPLICATION),
            'documentationType' => (int) ($opts['documentationType'] ?? 0),
            'print'             => $print,
        );

        $r = $this->request(self::BASE_LABELS, 'labels/print', $payload, self::SCOPE_LABELS);
        if ($r['ok'] && is_array($r['data']) && !empty($r['data']['pdf'])) {
            $bin = base64_decode($r['data']['pdf'], true);
            if ($bin !== false) $r['pdf_bin'] = $bin;
        }
        return $r;
    }

    /* ================================================================== *
     *  Seguimiento (trazabilidad canónica)                                *
     * ================================================================== */

    /* Endpoint canónico PÚBLICO del localizador (sin auth; mismo modelo de la
     * Matriz de eventos que la API trackpub del gateway). Se usa este porque el
     * contrato de trackpub en el Portal devuelve "Invalid Client" (pendiente de
     * aprobación 2026-06-10); cuando se apruebe, basta cambiar el transporte. */
    const TRACK_PUBLIC_URL = 'https://localizador.correos.es/canonico/eventos_envio_servicio/';

    /**
     * Trazabilidad de un envío por su packageCode (código de bulto, 23 díg).
     * Devuelve array{ok,http,error,raw,data} — data[0] = {codEnvio, eventos:[
     * {fecEvento dd/mm/yyyy, horEvento, codEvento, desFase, desTextoResumen,
     * desTextoAmpliado}], cod_evento_unico, resumen_ultimo, codExpedicion...}.
     */
    public function seguimiento($packageCode, $idioma = 'ES') {
        $url = self::TRACK_PUBLIC_URL . rawurlencode((string) $packageCode) . '?codIdioma=' . rawurlencode($idioma);
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array('Accept: application/json'),
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => min(15, $this->timeout),
            CURLOPT_TIMEOUT        => $this->timeout,
        ));
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        $data = ($body === false) ? null : json_decode($body, true);
        return array(
            'ok'    => ($http >= 200 && $http < 300 && is_array($data)),
            'http'  => $http,
            'error' => $err,
            'raw'   => ($body === false ? '' : $body),
            'data'  => $data,
        );
    }

    /** Timestamp ordenable (YmdHis) de un evento (fecEvento dd/mm/yyyy + horEvento). */
    public static function eventoTs($e) {
        $f = is_array($e) ? (string) ($e['fecEvento'] ?? '') : '';
        $h = is_array($e) ? (string) ($e['horEvento'] ?? '') : '';
        $fs = '00000000';
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $f, $m)) $fs = $m[3] . $m[2] . $m[1];
        $hs = preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?/', $h, $mm) ? ($mm[1] . $mm[2] . ($mm[3] ?? '00')) : '000000';
        return $fs . $hs;
    }

    /** Eventos de una respuesta de seguimiento() ordenados cronológicamente
     *  ASCENDENTE. El localizador canónico público NO garantiza el orden, así
     *  que ordenar es imprescindible para leer el estado ACTUAL correctamente. */
    public static function eventosOrdenados(array $resp) {
        $env = $resp['data'][0] ?? null;
        if (!is_array($env) || empty($env['eventos']) || !is_array($env['eventos'])) return array();
        $evs = array_values($env['eventos']);
        usort($evs, function ($a, $b) { return strcmp(correos::eventoTs($a), correos::eventoTs($b)); });
        return $evs;
    }

    /** Último evento (CRONOLÓGICO) de una respuesta de seguimiento(); null si no hay. */
    public static function ultimoEvento(array $resp) {
        $evs = self::eventosOrdenados($resp);
        return $evs ? $evs[count($evs) - 1] : null;
    }

    /** ¿El evento pertenece a la fase 4 ENTREGADO de la Matriz? */
    public static function esEntregado($evento) {
        $fase = is_array($evento) ? (string) ($evento['desFase'] ?? '') : '';
        return stripos($fase, 'ENTREGAD') !== false;   // "ENTREGADO" / "ENTREGADA"
    }

    /** ¿Algún evento del histórico está en fase ENTREGADO? Más robusto que mirar
     *  solo el último evento (un ENTREGADO sin hora podría no quedar el último al
     *  ordenar). ENTREGADO es terminal, así que "algún ENTREGADO" = entregado. */
    public static function algunEntregado(array $resp) {
        foreach (self::eventosOrdenados($resp) as $e) {
            if (self::esEntregado($e)) return true;
        }
        return false;
    }

    /** ¿El envío entró en la FASE de DEVOLUCIÓN (return-to-sender)? Un envío
     *  devuelto al remitente CIERRA en fase ENTREGADO (de vuelta a Francobordo),
     *  así que esEntregado() daría falso positivo y completaría el pedido del
     *  cliente cuando NUNCA lo recibió. Se detecta SOLO por la FASE 'DEVOLUCIÓN'
     *  de la Matriz de Correos (fiable). NO se mira el texto del evento: avisos
     *  pre-entrega tipo "...si no se retira será devuelto al remitente" aparecen
     *  mientras el paquete SIGUE disponible para recoger, y casarían en falso
     *  bloqueando un pedido que el cliente sí recogió luego. */
    public static function huboDevolucion(array $resp) {
        foreach (self::eventosOrdenados($resp) as $e) {
            if (stripos((string) ($e['desFase'] ?? ''), 'DEVOLUC') !== false) return true;
        }
        return false;
    }

    /* ================================================================== *
     *  Helpers para construir desde una fila de la tabla `rma`            *
     * ================================================================== */

    /**
     * Construye el objeto "shipment" de una DEVOLUCIÓN (logística inversa):
     * remitente (sender) = cliente que devuelve ; destinatario (addressee) =
     * Francobordo. $opts: product, weightGrams, height/width/length (mm),
     * province (cód. Anexo V del cliente), packageContents (declaración DUA/CN23
     * cuando el REMITENTE está en Canarias/Ceuta/Melilla; sin ella el preregistro
     * PAAZE falla con 6069 "Las información de aduanas es obligatoria").
     */
    public static function devolucionDesdeRma(array $rma, array $opts = array()) {
        $w = (string) ($opts['weightGrams'] ?? '1000');   // 1 kg por defecto
        $sender = array(
            'name'         => trim((string) ($rma['customers_name'] ?? '')),
            'address'      => trim((string) ($rma['customers_street_address'] ?? '') . ' ' . (string) ($rma['customers_suburb'] ?? '')),
            'locality'     => trim((string) ($rma['customers_city'] ?? '')),
            'cp'           => trim((string) ($rma['customers_postcode'] ?? '')),
            'country'      => 'ESP',   // ISO de 3 letras (Anexo III)
            'contactPerson' => trim((string) ($rma['customers_name'] ?? '')),
            'contactPhone' => trim((string) ($rma['customers_telephone'] ?? '')),
            'email'        => trim((string) ($rma['customers_email_address'] ?? '')),
            'language'     => 'spa',
        );
        if (!empty($opts['province'])) $sender['province'] = (string) $opts['province'];

        $addressee = array(
            'name'         => self::FB_NOMBRE,
            'company'      => self::FB_NOMBRE,
            'address'      => self::FB_DIR,
            'locality'     => self::FB_POBL,
            'cp'           => self::FB_CP,
            'province'     => self::FB_PROV,
            'country'      => self::FB_PAIS_ISO,
            'contactPerson' => self::FB_CONTACTO,
            'contactPhone' => self::FB_TLFNO,
            'email'        => self::FB_EMAIL,
            'language'     => 'spa',
        );
        if (self::FB_NIF !== '') { $addressee['doiType'] = '10'; $addressee['doiNumber'] = self::FB_NIF; }

        $package = array(
            'packageId'          => '1',
            'packageWeightGrams' => $w,
            'packageHeight'      => (string) ($opts['height'] ?? '150'),  // mm
            'packageWidth'       => (string) ($opts['width']  ?? '200'),  // mm
            'packageLength'      => (string) ($opts['length'] ?? '300'),  // mm
        );
        /* ADUANAS (Canarias/Ceuta/Melilla): la declaración va COMPLETA en el único bulto.
         * Las devoluciones PAAZE/PAAZV son siempre de 1 bulto (Anexo I: máx bultos = 1),
         * así que no hay conflicto multibulto. El bloque lo construye el llamador (el
         * módulo RMA, que tiene acceso a la BD del pedido) y lo pasa en opts. */
        if (!empty($opts['packageContents']) && is_array($opts['packageContents'])) {
            $package['packageContents'] = $opts['packageContents'];
        }

        return array(
            'product'        => (string) ($opts['product'] ?? self::PROD_RETORNO),
            'deliveryMethod' => self::DELIVERY_DEVOLUCION,
            'contractNumber' => self::CONTRACT,
            'clientNumber'   => self::CLIENT_NUMBER,
            'labellerCode'   => self::LABELLER,
            'packagesNumber' => '1',
            'totalWeight'    => $w,
            'shipmentReference1' => 'RMA' . str_pad((string) ($rma['id_rma'] ?? ''), 8, '0', STR_PAD_LEFT),
            /* Nº de RMA visible en la etiqueta, para que el almacén lo identifique al recibir.
             * OJO: el esquema de POST /delivery NO tiene 'observations' (ese campo es de la
             * variante /delivery/package y Correos lo IGNORA aquí en silencio — comprobado
             * 2026-07-10 con etiqueta real). Los campos que existen: 'shipmentNotes' (100c,
             * candidato al recuadro Observaciones) y 'dispatchReference' (30c, candidato al
             * "Ref.:"). "Does not apply to all products" según el OAS: verificar en el PDF. */
            'shipmentNotes'     => 'RMA ' . (int) ($rma['id_rma'] ?? 0),
            'dispatchReference' => 'RMA ' . (int) ($rma['id_rma'] ?? 0),
            'sender'         => $sender,
            'addressee'      => $addressee,
            'packages'       => array($package),
        );
    }

    /**
     * Flujo completo de etiqueta de devolución para un RMA:
     * pre-registra + genera la etiqueta PDF en una sola llamada lógica.
     * Devuelve array con ok, shipmentCode, packageCode, pdf_bin (binario) y las
     * respuestas crudas 'preregister' y 'label' para depurar.
     */
    public function etiquetaDevolucionRma(array $rma, array $opts = array()) {
        $pre = $this->preregister(array(self::devolucionDesdeRma($rma, $opts)), 'spa');
        $out = array('ok' => false, 'shipmentCode' => null, 'packageCode' => null,
                     'pdf_bin' => null, 'preregister' => $pre, 'label' => null, 'error' => '');

        $sh = $pre['data']['shipments'][0] ?? null;
        if (!$pre['ok'] || !$sh || empty($sh['shipmentCode'])) {
            $out['error'] = self::primerError($pre);
            return $out;
        }
        $out['shipmentCode'] = (string) $sh['shipmentCode'];
        $out['packageCode']  = (string) ($sh['packages'][0]['packageCode'] ?? '');
        if ($out['packageCode'] === '') { $out['error'] = 'Preregistro sin packageCode'; return $out; }

        // Labels usa el packageCode (no el shipmentCode).
        $lab = $this->getLabel(array($out['packageCode']), $opts);
        $out['label'] = $lab;
        if ($lab['ok'] && !empty($lab['pdf_bin'])) {
            $out['pdf_bin'] = $lab['pdf_bin'];
            $out['ok'] = true;
        } else {
            $out['error'] = $lab['data']['error'] ?? ('Etiqueta no generada (HTTP ' . $lab['http'] . ')');
        }
        return $out;
    }

    /** Extrae un mensaje de error legible de una respuesta de preregister. */
    public static function primerError(array $resp) {
        if (!empty($resp['data']['shipments'][0]['error'][0]['description'])) {
            $e = $resp['data']['shipments'][0]['error'][0];
            return trim(($e['errorCode'] ?? '') . ' ' . $e['description'] . (isset($e['errorFieldName']) ? ' [' . $e['errorFieldName'] . ']' : ''));
        }
        if (!empty($resp['data']['error']))  return (string) $resp['data']['error'];
        if (!empty($resp['error']))          return (string) $resp['error'];
        return 'Error HTTP ' . ($resp['http'] ?? '0');
    }
}

/* Credenciales (gitignored, fuera del mirror de GitHub). */
require_once(__DIR__ . '/correos_credentials.php');
