<?php
/**
 * paypal_paylater_banner.php
 *
 * Banner informativo PayPal Pay Later ("Banner de pago a plazos") para product_info.php.
 * Render inline estilo Sequra: el SDK oficial de PayPal Messages dibuja la linea de
 * cuotas y al clic abre su propio modal nativo con el detalle. Alineado a la derecha
 * dentro de la columna .seqr (misma columna que el teaser Sequra).
 *
 * App Live "Francobordo Pay Later" en developer.paypal.com. El Client ID es publico
 * (sale en el <script src> que entrega el navegador). El Secret NO se usa aqui — solo
 * haria falta para la fase 2 del checkout REST. Para sobreescribir sin tocar este
 * fichero, declarar PAYPAL_PAYLATER_CLIENT_ID antes del include.
 *
 * Define $sPayPalPayLaterBanner (string HTML, vacio si no aplica) para el template.
 *
 * Docs PayPal: https://developer.paypal.com/docs/checkout/pay-later/es/
 */

if ( ! defined( 'PAYPAL_PAYLATER_CLIENT_ID' ) )  define( 'PAYPAL_PAYLATER_CLIENT_ID', 'ARrw8pJ1czYnADTPieqeH4P4Un6vj8mVloKRZO5X9g0fnjkvK6gFIO8Rx_RE8Ads6FrPnzzpMjUv-Ocl' );
// Importe minimo (IVA incl., en EUR) a partir del cual mostrariamos el banner. Desactivado
// temporalmente durante la demo con PayPal (2026-05-28); reactivar descomentando el define
// y cambiando el if de abajo de '$fAmount > 0' a '$fAmount >= (float)PAYPAL_PAYLATER_MIN_AMOUNT'.
// if ( ! defined( 'PAYPAL_PAYLATER_MIN_AMOUNT' ) ) define( 'PAYPAL_PAYLATER_MIN_AMOUNT', 300.0 );

$sPayPalPayLaterBanner = '';

if ( isset( $aProductoInfo['products_id'] )
     && (int)$aProductoInfo['products_status'] === 1
     && PAYPAL_PAYLATER_CLIENT_ID !== '' ) {

    $fAmount = isset( $aProductoInfo['PRECIO_RICHSNIPPET'] )
        ? (float)$aProductoInfo['PRECIO_RICHSNIPPET']
        : 0.0;

    if ( $fAmount > 0 ) {

        $sAmount = number_format( $fAmount, 2, '.', '' );

        ob_start();
        ?>
        <style>
            /* Misma columna .seqr alinea sus hijos (teaser Sequra + banner PayPal) al borde derecho.
               Constrain .pp-paylater-line al mismo ancho que el widget de Sequra (320px) para que
               ambos teasers compartan exactamente el mismo borde derecho e izquierdo. */
            .seqr {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
            }
            .pp-paylater-line {
                width: 320px;
                max-width: 100%;
                margin: 6px 0 4px;
                font-size: 14px;
                line-height: 1.4;
                color: #555;
                text-align: right;
            }
            .pp-paylater-line > div { text-align: right; }
        </style>
        <div class="pp-paylater-line">
            <div data-pp-message
                 data-pp-amount="<?php echo htmlspecialchars( $sAmount, ENT_QUOTES, 'UTF-8' ); ?>"
                 data-pp-placement="product"
                 data-pp-style-layout="text"
                 data-pp-style-logo-type="inline"
                 data-pp-style-text-color="black"
                 data-pp-style-text-size="14"
                 data-pp-style-text-align="right"></div>
            <script async
                    src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode( PAYPAL_PAYLATER_CLIENT_ID ); ?>&components=messages&currency=EUR"
                    data-namespace="paypal_paylater"></script>
        </div>
        <?php
        $sPayPalPayLaterBanner = ob_get_clean();
    }
}
