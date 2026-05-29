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

    /** Helper interno: request autenticado */
    private function request( $sMethod, $sPath, $mBody = null ) {
        $sToken = $this->getAccessToken();

        $aHeaders = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $sToken,
            // PayPal recomienda un Prefer: return=representation para que devuelva el detalle completo
            'Prefer: return=representation',
        );

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
}
