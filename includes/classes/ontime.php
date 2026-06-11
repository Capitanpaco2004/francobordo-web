<?php
/**
 * Cliente REST para el WebService de Ontime (GTS / Alertran).
 *
 * Cubre: documentación de envíos (con etiqueta PDF/ZPL), entrega a recogedor
 * (cierre + creación de recogida + manifiesto) y detalle/tracking de
 * expediciones (por número, por referencia de cliente o por rango de fechas).
 *
 * Docs: Documento WS_Ontime_Documentar_Envios v2.0,
 *       WS_Ontime_Entrega_Recogedor_Conjuntas v1.1,
 *       WS_Ontime_Detalle_Expediciones v3.3.
 * Notas de integración: ver memoria francobordo_ontime_api.
 *
 * Auth: HTTP Basic (usuario/contraseña) en cada llamada. JSON sobre HTTPS.
 *
 * Gotchas:
 *  - listaEventos / listaBultos / listaIncidencias llegan como OBJETO cuando
 *    hay un solo elemento y como ARRAY cuando hay varios → usar normLista().
 *  - La clave de respuesta de documentarEnvio es "respuestaDocuemtarEnvio"
 *    (typo del propio WS, documentado como correcto). Se aceptan ambas grafías.
 *  - Una expedición creada con documentarEnvio NO aparece en el tracking
 *    (error 3) hasta que se comunica con entregaRecogedor.
 *  - El filtro por fechas admite un rango máximo de 7 días y pagina de 150
 *    en 150 (parámetro PAGINA).
 */
class ontime {

    /* ------------------------------------------------------------------ *
     *  Entornos                                                           *
     * ------------------------------------------------------------------ */

    /** Entorno por defecto. Cambiar a 'pro' cuando Ontime dé credenciales
     *  de producción y estén validadas (o usar la tabla ontime_config). */
    const DEFAULT_ENV = 'pre';

    const PRE_BASE    = 'https://gtsontimepre.alertran.net/gts/seam/resource/restv1/auth/';
    const PRE_USER    = 'pruebas_francobordo';
    const PRE_PASS    = 'pruebas_francobordo';
    const PRE_CLIENTE = '02899990';
    const PRE_CENTRO  = '03';

    /* PRODUCCIÓN — credenciales facilitadas por Ontime (Joaquín Sánchez,
     * Soporte IT) el 2026-06-10. El usuario WS solo tiene relación con el
     * CENTRO 03 (el 02 devuelve error 4 "no tiene relación", verificado
     * 2026-06-10): es el centro donde Vstock documenta las salidas
     * (producto 70 PAQUETERIA IND). Si Ontime habilita el 02, ampliar. */
    const PRO_BASE    = 'https://ontimegts.alertran.net/gts/seam/resource/restv1/auth/';
    const PRO_USER    = 'WS71946506';
    const PRO_PASS    = 'WS71946506';
    const PRO_CLIENTE = '71946506';
    const PRO_CENTRO  = '03';

    /* Página pública de seguimiento (la misma que enlaza Vstock en los
     * históricos de pedido: ?cliente=...&referencia=...&rango=360). */
    const PRE_PUB = 'https://gtsontimepre.alertran.net/gts/pub/locNumSeguimiento.seam';
    const PRO_PUB = 'https://ontimegts.alertran.net/gts/pub/locNumSeguimiento.seam';

    /* ------------------------------------------------------------------ *
     *  Dirección de Francobordo                                          *
     *  - Devoluciones (RMA): Francobordo es el DESTINATARIO              *
     *  - Envíos salientes:   Francobordo es el REMITENTE                 *
     * ------------------------------------------------------------------ */
    const FB_NOMBRE   = 'Francobordo Articulos Nauticos SL';
    const FB_DIR      = 'Calle San Rafael 8';
    const FB_POBL     = 'Alcobendas';
    const FB_CP       = '28108';
    const FB_PAIS_ISO = 'ES';
    const FB_CONTACTO = 'Francobordo';
    const FB_TLFNO    = '916528858';            // Almacén devoluciones (Alcobendas)
    const FB_EMAIL    = 'info@francobordo.com';

    /* Productos/servicios contratados (tabla TIPOS_SERVICIOS_ONTIME de
     * Vstock; el defecto de la agencia ONTIME en Vstock es el 70). */
    const PROD_PAQ_IND    = '70';   // PAQUETERIA IND (defecto salida almacén)
    const PROD_PALET_EXP  = '26';   // PALET EXPRESS
    const PROD_ECONOMY    = '48';   // ECONOMY 24-48 HORAS
    const PROD_ADR        = '95';   // ADR PAQ EXPRESS
    const PROD_RECO_DELEG = '80';   // RECOGIDA EN DELEGACION
    const PROD_LARGOS     = '60';   // Largos / planchas

    /* Producto por defecto para devoluciones RMA */
    const PROD_DEVOLUCION = self::PROD_PAQ_IND;

    /* Estados de expedición que consideramos "entregado" */
    const ESTADOS_ENTREGADO = array('ENTR', 'EFEC', 'DIAE', 'EAGE');

    /** @var string 'pre'|'pro' */
    protected $env;
    /** @var string */
    protected $base;
    /** @var string */
    protected $user;
    /** @var string */
    protected $pass;
    /** @var string */
    protected $cliente;
    /** @var string */
    protected $centro;
    /** @var int */
    protected $timeout = 30;
    /** @var array|null Última petición enviada (debug) */
    public $lastRequest = null;

    public function __construct($env = null) {
        if ($env === null) $env = self::DEFAULT_ENV;
        $this->env = ($env === 'pro') ? 'pro' : 'pre';
        if ($this->env === 'pro') {
            $this->base    = self::PRO_BASE;
            $this->user    = self::PRO_USER;
            $this->pass    = self::PRO_PASS;
            $this->cliente = self::PRO_CLIENTE;
            $this->centro  = self::PRO_CENTRO;
        } else {
            $this->base    = self::PRE_BASE;
            $this->user    = self::PRE_USER;
            $this->pass    = self::PRE_PASS;
            $this->cliente = self::PRE_CLIENTE;
            $this->centro  = self::PRE_CENTRO;
        }
    }

    public function getEnv()     { return $this->env; }
    public function getCliente() { return $this->cliente; }
    public function getCentro()  { return $this->centro; }

    /** true si el entorno activo tiene credenciales configuradas. */
    public function hasCredentials() { return $this->user !== '' && $this->pass !== ''; }

    /** Ajusta el timeout total (segundos). */
    public function setTimeout($s) { $this->timeout = max(1, (int) $s); return $this; }

    /** Cambia el centro para las siguientes llamadas (p.ej. si Ontime habilita más centros). */
    public function setCentro($c) { $this->centro = (string) $c; return $this; }

    /* ================================================================== *
     *  Núcleo HTTP                                                        *
     * ================================================================== */

    /**
     * POST JSON a un servicio del WS (ruta relativa a .../restv1/auth/).
     * Devuelve ['ok'=>bool, 'http'=>int, 'data'=>mixed|null, 'error'=>string|null, 'raw'=>string].
     * 'data' es el JSON decodificado tal cual (normalmente un array-lista de
     * objetos respuestaXxx); los métodos públicos lo destilan.
     */
    public function request($service, array $payload) {
        $url  = $this->base . ltrim($service, '/');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->lastRequest = array('url' => $url, 'body' => $payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
            CURLOPT_USERPWD        => $this->user . ':' . $this->pass,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($raw === false) {
            return array('ok' => false, 'http' => $http, 'data' => null, 'error' => 'cURL: ' . $err, 'raw' => '');
        }
        $data = json_decode($raw, true);
        if ($data === null && trim($raw) !== 'null') {
            return array('ok' => false, 'http' => $http, 'data' => null,
                         'error' => 'Respuesta no-JSON (HTTP ' . $http . '): ' . substr($raw, 0, 300), 'raw' => $raw);
        }
        return array('ok' => ($http >= 200 && $http < 300), 'http' => $http, 'data' => $data, 'error' => null, 'raw' => $raw);
    }

    /**
     * Extrae de la respuesta cruda la lista de objetos bajo la clave dada
     * (p.ej. 'respuestaDetalleExpediciones'). Acepta lista u objeto suelto
     * y la variante con typo de documentarEnvio.
     */
    protected static function extraer($data, $clave) {
        $out = array();
        if (!is_array($data)) return $out;
        $items = self::esLista($data) ? $data : array($data);
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            foreach ((array) $item as $k => $v) {
                if (strcasecmp($k, $clave) === 0 || ($clave === 'respuestaDocumentarEnvio' && strcasecmp($k, 'respuestaDocuemtarEnvio') === 0)) {
                    $out[] = $v;
                }
            }
        }
        return $out;
    }

    /** ¿$a es un array-lista (índices 0..n) y no un objeto asociativo? */
    protected static function esLista($a) {
        if (!is_array($a)) return false;
        if ($a === array()) return true;
        return array_keys($a) === range(0, count($a) - 1);
    }

    /**
     * Normaliza listaEventos / listaBultos / listaIncidencias: el WS devuelve
     * un OBJETO cuando hay un elemento y un ARRAY cuando hay varios.
     */
    public static function normLista($v) {
        if (empty($v) || !is_array($v)) return array();
        return self::esLista($v) ? $v : array($v);
    }

    /* ================================================================== *
     *  Documentar envío                                                   *
     * ================================================================== */

    /**
     * Documenta (crea) uno o varios envíos. Cada $envio es el array de
     * etiquetas DOCUMENTAR_ENVIO (CODIGO_ADMISION, NUMERO_BULTOS, ...).
     * Rellena por defecto cliente/centro remitente del entorno y los
     * flags fijos (ENVIO_CON_RECOGIDA=N, ENVIO_DEFINITIVO=N).
     *
     * Devuelve ['ok','http','error','envios'=>[ [resultado, numero_envio,
     * etiqueta, etiqueta_bin, ...], ... ] ] — 'ok' exige resultado OK en todos.
     */
    public function documentarEnvios(array $envios) {
        $lista = array();
        foreach ($envios as $envio) {
            $envio = array_merge(array(
                'CLIENTE_REMITENTE'  => $this->cliente,
                'CENTRO_REMITENTE'   => $this->centro,
                'ENVIO_CON_RECOGIDA' => 'N',   // fijo según doc; la recogida se pide en entregaRecogedor
                'IMPRIMIR_ETIQUETA'  => 'S',
                'ENVIO_DEFINITIVO'   => 'N',   // fijo según doc
                'TIPO_FORMATO'       => 'PDF',
            ), $envio);
            $lista[] = $envio;
        }
        $res = $this->request('documentarEnvio/json', array(
            'DOCUMENTAR_ENVIOS' => array(
                'VERSION'          => '3',
                'DOCUMENTAR_ENVIO' => $lista,
            ),
        ));
        $envs = self::extraer($res['data'], 'respuestaDocumentarEnvio');
        foreach ($envs as &$e) {
            if (!empty($e['etiqueta'])) {
                $bin = base64_decode($e['etiqueta'], true);
                if ($bin !== false) $e['etiqueta_bin'] = $bin;
            }
        }
        unset($e);
        $okTodos = !empty($envs);
        foreach ($envs as $e) {
            if (!isset($e['resultado']) || strtoupper($e['resultado']) !== 'OK') $okTodos = false;
        }
        return array(
            'ok'     => $res['ok'] && $okTodos,
            'http'   => $res['http'],
            'error'  => $res['error'],
            'envios' => $envs,
            'raw'    => $res['data'],
        );
    }

    /** Atajo para un único envío: devuelve directamente su respuesta. */
    public function documentarEnvio(array $envio) {
        $res = $this->documentarEnvios(array($envio));
        $uno = !empty($res['envios']) ? $res['envios'][0] : array();
        return array(
            'ok'    => $res['ok'],
            'http'  => $res['http'],
            'error' => $res['error'],
            'data'  => $uno,
        );
    }

    /* ================================================================== *
     *  Entrega a recogedor (cierre + recogida + manifiesto)               *
     * ================================================================== */

    /**
     * Comunica expediciones a Ontime y opcionalmente crea/asocia recogida.
     *
     * @param array  $expediciones     números de expedición (strings)
     * @param string $envioConRecogida 'S' (crea/asocia recogida del día),
     *                                 'N' (recogida fija concertada) o un
     *                                 número de recogida existente
     * @param string $manifiesto       'S' devuelve el manifiesto PDF (base64)
     */
    public function entregaRecogedor(array $expediciones, $envioConRecogida = 'S', $manifiesto = 'S') {
        $exps = array();
        foreach ($expediciones as $e) $exps[] = array('EXPEDICION' => (string) $e);
        $res = $this->request('entregaRecogedorService/entregaRecogidas', array(
            'ENTREGAS' => array(
                'ENTREGA' => array(
                    array(
                        'CLIENTE'            => $this->cliente,
                        'CENTRO'             => $this->centro,
                        'EXPEDICIONES'       => $exps,
                        'ENVIO_CON_RECOGIDA' => (string) $envioConRecogida,
                        'MANIFIESTO'         => $manifiesto,
                    ),
                ),
            ),
        ));
        $items = self::extraer($res['data'], 'respuestaEntregaRecogida');
        $uno   = !empty($items) ? $items[0] : array();
        if (!empty($uno['manifiesto'])) {
            $bin = base64_decode($uno['manifiesto'], true);
            if ($bin !== false) $uno['manifiesto_bin'] = $bin;
        }
        $okItem = isset($uno['resultado']) && strtoupper($uno['resultado']) === 'OK';
        return array(
            'ok'    => $res['ok'] && $okItem,
            'http'  => $res['http'],
            'error' => $res['error'],
            'data'  => $uno,
        );
    }

    /* ================================================================== *
     *  Anular envío (solo ANTES de la entrega a recogedor)                *
     * ================================================================== */

    /**
     * Anula una web-expedición creada con documentarEnvio que AÚN NO se ha
     * comunicado con entregaRecogedor (después, solo Att. Cliente puede).
     * Solo admite una expedición por llamada (doc Anular_Envios v1.0).
     */
    public function anularEnvio($numeroWebExpedicion) {
        $res = $this->request('anularWebExpediciones/anular', array(
            'webExpediciones' => array(
                'numeroWebExpedicion' => (string) $numeroWebExpedicion,
                'clienteOrigen'       => $this->cliente,
            ),
        ));
        $items = self::extraer($res['data'], 'respuestaAnularWebExpediciones');
        $uno   = !empty($items) ? $items[0] : array();
        $okItem = isset($uno['resultado']) && strtoupper($uno['resultado']) === 'OK';
        return array(
            'ok'    => $res['ok'] && $okItem,
            'http'  => $res['http'],
            'error' => $res['error'],
            'data'  => $uno,
        );
    }

    /* ================================================================== *
     *  Orden de recogida (recogida en origen ajeno → uno o más destinos)  *
     * ================================================================== */

    /**
     * Crea una orden de recogida (doc Orden_Recogida v1.1): recogida en un
     * lugar distinto a las instalaciones del cliente con entrega a uno o
     * varios destinos. Admite FECHA (DD/MM/AAAA, obligatoria) y franjas
     * HORARIO_MA_ / HORARIO_TARDE_ (DESDE/HASTA). NO genera etiqueta previa.
     *
     * $recogida = etiquetas de la zona principal (NOMBRE, DIRECCION,
     * POBLACION, CODIGO_POSTAL, FECHA, ...) + 'DETALLES_RECOGIDA' => array
     * de destinos (NOMBRE, CONTACTO, DIRECCION, ..., PRODUCTO).
     * CLIENTE/CENTRO se añaden solos.
     */
    public function crearOrdenRecogida(array $recogida) {
        $recogida = array_merge(array(
            'CLIENTE' => $this->cliente,
            'CENTRO'  => $this->centro,
        ), $recogida);
        $res = $this->request('documentarRecogidaService/crearRecogida', array(
            'RECOGIDA' => $recogida,
        ));
        $items = self::extraer($res['data'], 'respuestaRecogidas');
        $uno   = !empty($items) ? $items[0] : array();
        $okItem = isset($uno['resultado']) && strtoupper($uno['resultado']) === 'OK';
        return array(
            'ok'    => $res['ok'] && $okItem,
            'http'  => $res['http'],
            'error' => $res['error'],
            'data'  => $uno,
        );
    }

    /* ================================================================== *
     *  Detalle / tracking de expediciones                                 *
     * ================================================================== */

    /**
     * Consulta genérica de detalle. $filtros admite EXPE_NUMERO,
     * REFERENCIA_CLIENTE, DESDE_FECHA, HASTA_FECHA (dd/MM/yyyy, rango máx
     * 7 días) y PAGINA. CLIENTE/CENTRO se añaden solos.
     *
     * Devuelve ['ok','http','error','expediciones'=>[...]] con una entrada
     * por expedición (clave respuestaDetalleExpediciones ya extraída).
     */
    public function detalle(array $filtros) {
        $filtros = array_merge(array(
            'CLIENTE' => $this->cliente,
            'CENTRO'  => $this->centro,
        ), $filtros);
        $res = $this->request('detalleExpedicioneService/detalles', array(
            'DETALLES_EXPEDICION' => array(
                'VERSION'            => '4',
                'DETALLE_EXPEDICION' => array($filtros),
            ),
        ));
        $exps = self::extraer($res['data'], 'respuestaDetalleExpediciones');
        return array(
            'ok'           => $res['ok'],
            'http'         => $res['http'],
            'error'        => $res['error'],
            'expediciones' => $exps,
        );
    }

    /** Detalle de una expedición por su número. Devuelve el objeto o null. */
    public function detalleExpedicion($expeNumero) {
        $res = $this->detalle(array('EXPE_NUMERO' => (string) $expeNumero));
        foreach ($res['expediciones'] as $e) {
            if (isset($e['resultado']) && strtoupper($e['resultado']) === 'OK') return $e;
        }
        return !empty($res['expediciones']) ? $res['expediciones'][0] : null;
    }

    /**
     * Detalle por referencia de cliente (obliga a rango de fechas).
     * Fechas en formato dd/MM/yyyy; si se omiten, últimos 7 días.
     */
    public function detallePorReferencia($referencia, $desde = null, $hasta = null) {
        if ($desde === null) $desde = date('d/m/Y', strtotime('-6 days'));
        if ($hasta === null) $hasta = date('d/m/Y');
        return $this->detalle(array(
            'REFERENCIA_CLIENTE' => (string) $referencia,
            'DESDE_FECHA'        => $desde,
            'HASTA_FECHA'        => $hasta,
        ));
    }

    /**
     * Barrido por rango de fechas (máx 7 días) con paginación de 150.
     * Fechas dd/MM/yyyy. Devuelve la lista completa de expediciones
     * iterando PAGINA hasta recibir menos de 150.
     */
    public function detallePorFechas($desde, $hasta, $maxPaginas = 30) {
        $todas  = array();
        $pagina = 1;
        do {
            $res = $this->detalle(array(
                'DESDE_FECHA' => $desde,
                'HASTA_FECHA' => $hasta,
                'PAGINA'      => (string) $pagina,
            ));
            if (!$res['ok']) {
                return array('ok' => false, 'http' => $res['http'], 'error' => $res['error'], 'expediciones' => $todas);
            }
            $lote = array();
            foreach ($res['expediciones'] as $e) {
                // Error 6 = sin resultados (fin normal cuando la página excede)
                if (isset($e['codigo_error']) && (int) $e['codigo_error'] === 6) continue;
                if (isset($e['resultado']) && strtoupper($e['resultado']) === 'ERROR') continue;
                $lote[] = $e;
            }
            $todas = array_merge($todas, $lote);
            $pagina++;
        } while (count($lote) >= 150 && $pagina <= $maxPaginas);
        return array('ok' => true, 'http' => 200, 'error' => null, 'expediciones' => $todas);
    }

    /* ================================================================== *
     *  Estados                                                            *
     * ================================================================== */

    /** Mapa código de estado/evento → descripción humana. */
    public static function estadoDescripcion($code) {
        $code = strtoupper(trim((string) $code));
        $map = array(
            'ALTA' => 'Alta en el sistema',
            'COMP' => 'Completado/validado',
            'RECO' => 'Pendiente de recogida',
            'ANUL' => 'Envío anulado',
            'COCE' => 'Concertar entrega',
            'COOR' => 'En hub de coordinación',
            'CPCE' => 'En tránsito a destino',
            'CERC' => 'En tránsito',
            'DGEN' => 'Devolución de envío',
            'DEVU' => 'Devuelto a origen',
            'ORIG' => 'En delegación de origen',
            'DESTI' => 'En delegación de destino',
            'TRANS' => 'En tránsito',
            'FALT' => 'Incidencia: falta de mercancía',
            'CMAI' => 'Notificación por email',
            'LLEG' => 'En plataforma de destino',
            'REPA' => 'En reparto',
            'DIAE' => 'Entregado',
            'EAGE' => 'Entregado por colaborador',
            'EFEC' => 'Entregado',
            'ENTR' => 'Entregado',
            'ENPA' => 'Entrega parcial',
            'DIAP' => 'Entrega parcial',
            'DIAN' => 'No entregado (intento fallido)',
            'NOEF' => 'No entregado',
            'ENAP' => 'Entrega aplazada',
            'RCOB' => 'Reembolso cobrado',
            'WEBC' => 'Pendiente de comunicar',
            // Incidencias numéricas más comunes
            '1101' => 'Incidencia: falta total',
            '1201' => 'Incidencia: falta parcial',
            '2101' => 'Incidencia: avería total',
            '2201' => 'Incidencia: avería parcial',
            '4101' => 'Rehúse económico',
            '4201' => 'Rehúse de mercancía',
            '5101' => 'Incidencia de dirección',
            '6101' => 'Ausente/cerrado',
            '7301' => 'Entrega aplazada',
            '7404' => 'Imposible concertar entrega',
            '8104' => 'Mercancía en aduana',
        );
        if (isset($map[$code])) return $map[$code];
        return 'En curso (' . $code . ')';
    }

    /** ¿El estado de expedición equivale a entregado? */
    public static function esEntregado($estado) {
        return in_array(strtoupper(trim((string) $estado)), self::ESTADOS_ENTREGADO, true);
    }

    /** URL pública de seguimiento por referencia (la que usa Vstock). */
    public function publicTrackingUrl($referencia) {
        $pub = ($this->env === 'pro') ? self::PRO_PUB : self::PRE_PUB;
        return $pub . '?cliente=' . rawurlencode($this->cliente) . '&referencia=' . rawurlencode($referencia) . '&rango=360';
    }

    /* ================================================================== *
     *  Helpers RMA (devoluciones: remitente=cliente, destino=Francobordo)  *
     * ================================================================== */

    /**
     * Teléfono según doc Ontime: numérico; prefijos internacionales con el
     * "+" sustituido por un "0" (+34612... → 034612...).
     */
    public static function telefono($t) {
        $t = trim((string) $t);
        $plus = (strpos($t, '+') === 0);
        $t = preg_replace('/\D+/', '', $t);
        return ($plus ? '0' : '') . $t;
    }

    /**
     * Construye el array DOCUMENTAR_ENVIO de una devolución RMA a partir de
     * la fila cruda de la tabla rma (mismas columnas que usa el módulo CEX:
     * customers_name, customers_street_address, customers_suburb,
     * customers_city, customers_postcode, customers_telephone,
     * customers_email_address, id_rma).
     * $opts: KILOS, NUMERO_BULTOS, CODIGO_PRODUCTO_SERVICIO, OBSERVACIONES1...
     */
    public function envioDevolucionDesdeRma(array $rma, array $opts = array()) {
        $ref = 'RMA' . str_pad((string) $rma['id_rma'], 8, '0', STR_PAD_LEFT);
        return array_merge(array(
            'CODIGO_ADMISION'                 => $ref . '-' . date('ymdHis'),
            'NUMERO_BULTOS'                   => 1,
            'CLIENTE_REMITENTE'               => $this->cliente,
            'CENTRO_REMITENTE'                => $this->centro,
            // Remitente físico = cliente que devuelve
            'NOMBRE_REMITENTE'                => trim((string) $rma['customers_name']),
            'DIRECCION_REMITENTE'             => trim((string) $rma['customers_street_address'] . ' ' . (string) ($rma['customers_suburb'] ?? '')),
            'PAIS_REMITENTE'                  => 'ES',
            'CODIGO_POSTAL_REMITENTE'         => trim((string) $rma['customers_postcode']),
            'POBLACION_REMITENTE'             => trim((string) $rma['customers_city']),
            'TELEFONO_CONTACTO_REMITENTE'     => self::telefono($rma['customers_telephone']),
            'PERSONA_CONTACTO_REMITENTE'      => trim((string) $rma['customers_name']),
            'EMAIL_REMITENTE'                 => trim((string) $rma['customers_email_address']),
            // Destinatario = almacén Francobordo
            'NOMBRE_DESTINATARIO'             => self::FB_NOMBRE,
            'DIRECCION_DESTINATARIO'          => self::FB_DIR,
            'PAIS_DESTINATARIO'               => self::FB_PAIS_ISO,
            'CODIGO_POSTAL_DESTINATARIO'      => self::FB_CP,
            'POBLACION_DESTINATARIO'          => self::FB_POBL,
            'PERSONA_CONTACTO_DESTINATARIO'   => self::FB_CONTACTO,
            'TELEFONO_CONTACTO_DESTINATARIO'  => self::FB_TLFNO,
            'EMAIL_DESTINATARIO'              => self::FB_EMAIL,
            'CODIGO_PRODUCTO_SERVICIO'        => self::PROD_DEVOLUCION,
            'KILOS'                           => '1',
            'CLIENTE_REFERENCIA'              => $ref,
            'TIPO_PORTES'                     => 'P',
            'OBSERVACIONES1'                  => 'Devolucion RMA ' . (int) $rma['id_rma'],
        ), $opts);
    }

    /**
     * Flujo completo de recogida de devolución RMA:
     * documenta el envío (etiqueta PDF) y lo entrega a recogedor con
     * recogida ('S' → crea/asocia la recogida del día en la dirección
     * del remitente). Devuelve envío + recogida.
     */
    public function recogidaDevolucionRma(array $rma, array $opts = array()) {
        $envio = $this->documentarEnvio($this->envioDevolucionDesdeRma($rma, $opts));
        if (!$envio['ok'] || empty($envio['data']['numero_envio'])) {
            return array('ok' => false, 'paso' => 'documentarEnvio', 'envio' => $envio, 'recogida' => null);
        }
        $reco = $this->entregaRecogedor(array($envio['data']['numero_envio']), 'S', 'N');
        return array(
            'ok'       => $reco['ok'],
            'paso'     => $reco['ok'] ? 'completo' : 'entregaRecogedor',
            'envio'    => $envio,
            'recogida' => $reco,
        );
    }
}
