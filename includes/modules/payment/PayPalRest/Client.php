<?php
/**
 * PayPalRest\Client
 *
 * Cliente minimo de la REST API de PayPal Orders v2.
 * - OAuth client_credentials → access_token (cacheado en APCu si esta disponible,
 *   en una propiedad estatica si no).
 * - Crear orden (POST /v2/checkout/orders)
 * - Capturar orden (POST /v2/checkout/orders/{id}/capture)
 * - Autorizar orden (POST /v2/checkout/orders/{id}/authorize)
 * - Consultar orden (GET /v2/checkout/orders/{id})
 *
 * Las credenciales y el entorno (sandbox/live) se leen de los defines
 * MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID / _SECRET / _ENVIRONMENT instalados
 * por la clase paypal_rest.
 *
 * Si MODULE_PAYMENT_PAYPAL_REST_DEBUG = 'True', se hace error_log() de cada
 * request/response. Desactivar en produccion.
 *
 * Path en disco: includes/modules/payment/PayPalRest/Client.php
 */

namespace PayPalRest;

class Client {

    const SANDBOX_BASE = 'https://api-m.sandbox.paypal.com';
    const LIVE_BASE    = 'https://api-m.paypal.com';

    /** @var string */
    private $sBase;
    /** @var string */
    private $sClientId;
    /** @var string */
    private $sSecret;
    /** @var bool */
    private $bDebug;
    /** @var string|null cache del access_token en proceso */
    private static $sCachedToken      = null;
    /** @var int 0 = epoch en que expira */
    private static $nCachedTokenExp   = 0;

    public function __construct() {
        if ( ! defined('MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID') || MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID === '' ) {
            throw new \RuntimeException('paypal_rest: falta MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID');
        }
        if ( ! defined('MODULE_PAYMENT_PAYPAL_REST_SECRET') || MODULE_PAYMENT_PAYPAL_REST_SECRET === '' ) {
            throw new \RuntimeException('paypal_rest: falta MODULE_PAYMENT_PAYPAL_REST_SECRET');
        }
        $this->sClientId = MODULE_PAYMENT_PAYPAL_REST_CLIENT_ID;
        $this->sSecret   = MODULE_PAYMENT_PAYPAL_REST_SECRET;
        $sEnv = defined('MODULE_PAYMENT_PAYPAL_REST_ENVIRONMENT') ? MODULE_PAYMENT_PAYPAL_REST_ENVIRONMENT : 'sandbox';
        $this->sBase = ( $sEnv === 'live' ) ? self::LIVE_BASE : self::SANDBOX_BASE;
        $this->bDebug = defined('MODULE_PAYMENT_PAYPAL_REST_DEBUG') && MODULE_PAYMENT_PAYPAL_REST_DEBUG === 'True';
    }

    /**
     * Devuelve un access_token valido. Lo cachea en memoria del proceso PHP
     * hasta su expiracion (PayPal devuelve ~9 horas de validez).
     */
    public function getAccessToken() {
        if ( self::$sCachedToken !== null && self::$nCachedTokenExp > time() + 30 ) {
            return self::$sCachedToken;
        }

        $rCh = curl_init($this->sBase . '/v1/oauth2/token');
        curl_setopt_array($rCh, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_USERPWD        => $this->sClientId . ':' . $this->sSecret,
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json',
                'Accept-Language: en_US',
                'Content-Type: application/x-www-form-urlencoded',
            ),
            CURLOPT_TIMEOUT        => 20,
        ));
        $sResp = curl_exec($rCh);
        $nCode = curl_getinfo($rCh, CURLINFO_HTTP_CODE);
        $sErr  = curl_error($rCh);
        // curl_close obsoleto en PHP 8.5 (no-op desde PHP 8.0); el GC limpia el handle

        if ( $this->bDebug ) {
            error_log('[paypal_rest] OAuth ' . $nCode . ' ' . substr((string)$sResp, 0, 500));
        }

        if ( $nCode !== 200 || ! $sResp ) {
            throw new \RuntimeException('paypal_rest OAuth failed (' . $nCode . '): ' . $sErr . ' / ' . substr((string)$sResp, 0, 200));
        }

        $aData = json_decode((string)$sResp, true);
        if ( ! is_array($aData) || empty($aData['access_token']) ) {
            throw new \RuntimeException('paypal_rest OAuth: respuesta sin access_token');
        }

        self::$sCachedToken    = (string)$aData['access_token'];
        self::$nCachedTokenExp = time() + (int)($aData['expires_in'] ?? 3600);

        return self::$sCachedToken;
    }

    /**
     * Crea una orden en PayPal.
     * @param float  $fAmount    Importe IVA incluido en EUR
     * @param string $sIntent    'CAPTURE' (cobro inmediato) o 'AUTHORIZE'
     * @param string $sReference Numero/referencia interna del carrito (para debug)
     * @param array  $aItems     Opcional: lineas del carrito
     * @return array{id:string,status:string,...}
     */
    public function createOrder( $fAmount, $sIntent = 'CAPTURE', $sReference = '', $aItems = array() ) {
        $aPayload = array(
            'intent'         => $sIntent,
            'purchase_units' => array(array(
                'reference_id' => $sReference !== '' ? substr($sReference, 0, 256) : 'cart',
                'amount'       => array(
                    'currency_code' => 'EUR',
                    'value'         => number_format((float)$fAmount, 2, '.', ''),
                ),
            )),
        );

        // Item breakdown opcional (PayPal lo prefiere para anti-fraude pero no exige)
        if ( ! empty($aItems) ) {
            $aLines    = array();
            $fItemsSum = 0.0;
            foreach ( $aItems as $a ) {
                $f = (float)$a['unit_amount'] * (int)$a['quantity'];
                $fItemsSum += $f;
                $aLines[] = array(
                    'name'        => substr((string)$a['name'], 0, 127),
                    'quantity'    => (string)(int)$a['quantity'],
                    'unit_amount' => array(
                        'currency_code' => 'EUR',
                        'value'         => number_format((float)$a['unit_amount'], 2, '.', ''),
                    ),
                );
            }
            $aPayload['purchase_units'][0]['items']               = $aLines;
            $aPayload['purchase_units'][0]['amount']['breakdown'] = array(
                'item_total' => array(
                    'currency_code' => 'EUR',
                    'value'         => number_format($fItemsSum, 2, '.', ''),
                ),
            );
            // Si hay diferencia entre items y total, PayPal exige declararla (shipping/handling/tax)
            $fDiff = round((float)$fAmount - $fItemsSum, 2);
            if ( abs($fDiff) >= 0.01 ) {
                $aPayload['purchase_units'][0]['amount']['breakdown']['shipping'] = array(
                    'currency_code' => 'EUR',
                    'value'         => number_format(max(0, $fDiff), 2, '.', ''),
                );
            }
        }

        return $this->request('POST', '/v2/checkout/orders', $aPayload);
    }

    /** GET /v2/checkout/orders/{id} */
    public function getOrder( $sOrderId ) {
        return $this->request('GET', '/v2/checkout/orders/' . rawurlencode($sOrderId));
    }

    /** POST /v2/checkout/orders/{id}/capture */
    public function captureOrder( $sOrderId ) {
        return $this->request('POST', '/v2/checkout/orders/' . rawurlencode($sOrderId) . '/capture', new \stdClass());
    }

    /** POST /v2/checkout/orders/{id}/authorize (solo si intent=AUTHORIZE) */
    public function authorizeOrder( $sOrderId ) {
        return $this->request('POST', '/v2/checkout/orders/' . rawurlencode($sOrderId) . '/authorize', new \stdClass());
    }

    /** GET /v2/payments/captures/{id} — consulta el estado de un capture (para saber si es refundable) */
    public function getCapture( $sCaptureId ) {
        return $this->request('GET', '/v2/payments/captures/' . rawurlencode($sCaptureId));
    }

    /**
     * Reembolsa un capture. POST /v2/payments/captures/{capture_id}/refund
     * @param string      $sCaptureId  ID del capture (orders.paypal_transaction_id)
     * @param float|null  $fAmount     Importe a devolver en EUR. null = reembolso TOTAL.
     * @param string      $sCurrency   Moneda (por defecto EUR)
     * @return array  Respuesta PayPal (status COMPLETED/PENDING, id del refund, ...)
     */
    public function refundCapture( $sCaptureId, $fAmount = null, $sCurrency = 'EUR', $sIdempotencyKey = '' ) {
        // Sin amount → PayPal reembolsa el importe total del capture.
        $mBody = new \stdClass();
        if ( $fAmount !== null ) {
            $mBody = array(
                'amount' => array(
                    'value'         => number_format((float)$fAmount, 2, '.', ''),
                    'currency_code' => $sCurrency,
                ),
            );
        }
        // PayPal-Request-Id: idempotencia. Si llegan dos peticiones identicas (doble clic,
        // recarga, reintento), PayPal procesa UNA sola devolucion y devuelve la misma respuesta.
        $aExtra = array();
        if ( $sIdempotencyKey !== '' ) {
            $aExtra[] = 'PayPal-Request-Id: ' . substr(preg_replace('/[^A-Za-z0-9_\-]/', '', $sIdempotencyKey), 0, 108);
        }
        return $this->request('POST', '/v2/payments/captures/' . rawurlencode($sCaptureId) . '/refund', $mBody, $aExtra);
    }

    /** Helper interno: request autenticado. $aExtraHeaders: cabeceras adicionales (ej. PayPal-Request-Id). */
    private function request( $sMethod, $sPath, $mBody = null, $aExtraHeaders = array() ) {
        $sToken = $this->getAccessToken();

        $aHeaders = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $sToken,
            // PayPal recomienda un Prefer: return=representation para que devuelva el detalle completo
            'Prefer: return=representation',
        );
        if ( ! empty($aExtraHeaders) ) {
            $aHeaders = array_merge($aHeaders, $aExtraHeaders);
        }

        $rCh = curl_init($this->sBase . $sPath);
        $aOpts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $sMethod,
            CURLOPT_HTTPHEADER     => $aHeaders,
            CURLOPT_TIMEOUT        => 30,
        );
        if ( $mBody !== null ) {
            $aOpts[CURLOPT_POSTFIELDS] = is_string($mBody) ? $mBody : json_encode($mBody);
        }
        curl_setopt_array($rCh, $aOpts);

        $sResp = curl_exec($rCh);
        $nCode = curl_getinfo($rCh, CURLINFO_HTTP_CODE);
        $sErr  = curl_error($rCh);
        // curl_close obsoleto en PHP 8.5 (no-op desde PHP 8.0); el GC limpia el handle

        if ( $this->bDebug ) {
            error_log('[paypal_rest] ' . $sMethod . ' ' . $sPath
                . ' req=' . (is_null($mBody) ? '-' : substr((string)json_encode($mBody), 0, 800))
                . ' resp=' . $nCode . ' ' . substr((string)$sResp, 0, 800));
        }

        if ( $nCode >= 400 ) {
            $aErr = json_decode((string)$sResp, true);
            $sMsg = is_array($aErr) ? ( $aErr['message'] ?? json_encode($aErr) ) : (string)$sResp;
            throw new \RuntimeException('paypal_rest ' . $sMethod . ' ' . $sPath . ' fallo (' . $nCode . '): ' . $sMsg);
        }
        if ( $nCode === 0 || $sResp === false ) {
            throw new \RuntimeException('paypal_rest ' . $sMethod . ' ' . $sPath . ' sin respuesta: ' . $sErr);
        }

        $aData = json_decode((string)$sResp, true);
        return is_array($aData) ? $aData : array('raw' => $sResp);
    }

    /**
     * Devuelve los COBROS REALES que PayPal tiene registrados en la unidad de
     * compra del pedido. NO son los datos que envia el navegador ni el importe
     * que pedimos nosotros al crear la orden: es lo que PayPal dice que ha
     * movido de verdad.
     *
     * - intent CAPTURE   -> capturas con status COMPLETED.
     * - intent AUTHORIZE -> autorizaciones vivas (CREATED / PARTIALLY_CAPTURED / CAPTURED).
     *
     * Un pedido en APPROVED (el comprador aprobo pero NADIE ha llamado a
     * /capture) no tiene ningun elemento aqui: devuelve array vacio. Idem si la
     * captura salio DECLINED (caso real observado en el log: order.status =
     * COMPLETED con captures[0].status = DECLINED).
     *
     * @param array  $aOrder  Respuesta de getOrder() / captureOrder() (Orders v2)
     * @param string $sIntent 'CAPTURE' (por defecto) o 'AUTHORIZE'
     * @return array Lista de array('id','status','amount','kind'); vacia si no hay cobro
     */
    public static function listSettledPayments( array $aOrder, $sIntent = 'CAPTURE' ) {
        $aUnit = isset($aOrder['purchase_units'][0]) ? $aOrder['purchase_units'][0] : null;
        if ( ! is_array($aUnit) ) {
            return array();
        }

        if ( strtoupper((string)$sIntent) === 'AUTHORIZE' ) {
            $sKind   = 'authorization';
            $aValid  = array('CREATED', 'PARTIALLY_CAPTURED', 'CAPTURED');
            $aList   = isset($aUnit['payments']['authorizations']) ? $aUnit['payments']['authorizations'] : null;
        } else {
            $sKind   = 'capture';
            $aValid  = array('COMPLETED');
            $aList   = isset($aUnit['payments']['captures']) ? $aUnit['payments']['captures'] : null;
        }
        if ( ! is_array($aList) ) {
            return array();
        }

        $aOut = array();
        foreach ( $aList as $aPay ) {
            if ( ! is_array($aPay) ) continue;
            if ( ! in_array((string)($aPay['status'] ?? ''), $aValid, true) ) continue;
            if ( ! isset($aPay['amount']['value']) ) continue;
            $aOut[] = array(
                'id'     => (string)($aPay['id'] ?? ''),
                'status' => (string)$aPay['status'],
                'amount' => $aPay['amount'],
                'kind'   => $sKind,
            );
        }
        return $aOut;
    }

    /** Primer cobro real registrado, o null si PayPal no ha cobrado nada. */
    public static function findSettledPayment( array $aOrder, $sIntent = 'CAPTURE' ) {
        $aPayments = self::listSettledPayments($aOrder, $sIntent);
        return empty($aPayments) ? null : $aPayments[0];
    }

    /**
     * Reconcilia el importe/moneda realmente capturado en PayPal contra el total
     * esperado del pedido (calculado en servidor). Devuelve true SOLO si la moneda
     * coincide y el importe cuadra al centimo. Defensa contra fraude por
     * manipulacion de importe: NO basta con fiarse del 'status' del pedido.
     *
     * IMPORTANTE: si PayPal no ha cobrado nada devuelve false. Antes se recaia
     * en purchase_units[0].amount — que es el importe que enviamos NOSOTROS al
     * crear la orden — con lo que la comprobacion cuadraba SIEMPRE y un pedido
     * meramente APPROVED (autorizacion que caduca en ~3h sin cobro) pasaba el
     * filtro.
     *
     * @param array      $aOrder         Respuesta de getOrder() (PayPal Orders v2)
     * @param float|int  $fExpectedTotal Total esperado del pedido (servidor)
     * @param string     $sExpectedCurrency Moneda esperada (por defecto EUR)
     * @param string     $sIntent        'CAPTURE' (por defecto) o 'AUTHORIZE'
     */
    public static function verifyCapturedAmount( array $aOrder, $fExpectedTotal, $sExpectedCurrency = 'EUR', $sIntent = 'CAPTURE' ) {
        $aPayment = self::findSettledPayment($aOrder, $sIntent);
        if ( $aPayment === null ) {
            return false;
        }

        $aAmount = $aPayment['amount'];
        if ( ! is_array($aAmount) || ! isset($aAmount['value']) ) {
            return false;
        }

        if ( strtoupper((string)($aAmount['currency_code'] ?? '')) !== strtoupper((string)$sExpectedCurrency) ) {
            return false;
        }

        // Tolerancia de 1 centimo por redondeos
        return abs( (float)$aAmount['value'] - (float)$fExpectedTotal ) <= 0.01;
    }
}
