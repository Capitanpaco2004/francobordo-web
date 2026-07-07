<?php
/**
 * staff_stock_panel.php
 *
 * Panel INTERNO (solo IP Francobordo 217.127.199.171) para product_info.php.
 * Muestra stock web + ubicaciones físicas por variante (VStock), alimentado por
 * product_warehouse_locations (sync_locations_to_web.py cada 15 min).
 *
 * Seguridad: usa $_SERVER['REMOTE_ADDR'] DIRECTO (no tep_get_ip_address(), que
 * prioriza X-Forwarded-For y es spoofeable). No hay CDN delante, así que
 * REMOTE_ADDR es la IP real y no falsificable a nivel TCP.
 *
 * Define $sStaffStockPanel (string HTML, vacío si no aplica) para el template.
 */

$sStaffStockPanel = '';

if ( ( $_SERVER['REMOTE_ADDR'] ?? '' ) === '217.127.199.171'
     && isset( $aProductoInfo['products_id'] ) ) {

    $nPid = (int)$aProductoInfo['products_id'];

    $rSku = tep_db_fetch_array( tep_db_query(
        'SELECT CCODIART FROM ' . TABLE_PRODUCTS . ' WHERE products_id = "' . $nPid . '"'
    ) );
    $sCcodiart = trim( (string)( $rSku['CCODIART'] ?? '' ) );
    $nStockWeb = (int)( $aProductoInfo['products_quantity'] ?? 0 );

    // Ubicaciones físicas desde VStock (product_warehouse_locations)
    $aVariantes = array(); // clave variante -> ['unidades'=>x, 'ubis'=>[[ubi,uds],...]]
    if ( $sCcodiart !== '' ) {
        $qLoc = tep_db_query(
            'SELECT prop1, prop2, prop3, variante, ubicacion, unidades, disponible'
            . ' FROM product_warehouse_locations'
            . ' WHERE sku = "' . tep_db_input( $sCcodiart ) . '"'
            . ' ORDER BY variante, prop1, prop2, prop3, ubicacion'
        );
        while ( $rl = tep_db_fetch_array( $qLoc ) ) {
            // Clave de agrupación = CCODIVAL (prop1/2/3), NO el nombre legible:
            // en QFac puede haber DOS variantes distintas con el mismo nombre
            // (visto en 21604000: '19MM.' y '20MM.-3' ambas llamadas "19 mm.").
            $sProps = trim( implode( ' / ', array_filter( array(
                trim( (string)$rl['prop1'] ),
                trim( (string)$rl['prop2'] ),
                trim( (string)$rl['prop3'] ),
            ) ) ) );
            $sKey = ( $sProps !== '' ) ? $sProps : '(sin variante)';
            $sLabel = trim( (string)( $rl['variante'] ?? '' ) );
            if ( $sLabel === '' ) $sLabel = $sKey;
            if ( ! isset( $aVariantes[ $sKey ] ) )
                $aVariantes[ $sKey ] = array( 'label' => $sLabel, 'props' => $sProps, 'unidades' => 0.0, 'disponible' => 0.0, 'ubis' => array() );
            $aVariantes[ $sKey ]['disponible'] = (float)( $rl['disponible'] ?? 0 );
            $fU = (float)$rl['unidades'];
            $aVariantes[ $sKey ]['unidades'] += $fU;
            $aVariantes[ $sKey ]['ubis'][] = array( trim( (string)$rl['ubicacion'] ), $fU );
        }
        // Nombres duplicados (variantes distintas con mismo nombre en QFac):
        // añadir el código CCODIVAL al label para distinguirlas visualmente.
        $aLabelCount = array();
        foreach ( $aVariantes as $aV )
            $aLabelCount[ $aV['label'] ] = ( $aLabelCount[ $aV['label'] ] ?? 0 ) + 1;
        foreach ( $aVariantes as $k => $aV ) {
            if ( $aLabelCount[ $aV['label'] ] > 1 && $aV['props'] !== '' )
                $aVariantes[ $k ]['label'] .= ' [' . $aV['props'] . '] ⚠️';
        }
    }

    $fmt = function( $q ) {
        return ( abs( $q - round( $q ) ) < 0.001 )
            ? (string)(int)round( $q )
            : rtrim( rtrim( number_format( $q, 3, ',', '' ), '0' ), ',' );
    };

    ob_start();
    ?>
    <div style="border:2px dashed #e6a700;background:#fff8e1;border-radius:8px;padding:14px 16px;margin:16px 0;font-size:13px;color:#333;line-height:1.5;">
        <div style="font-weight:700;color:#b8860b;margin-bottom:8px;font-size:13px;letter-spacing:.3px;">
            🔒 ALMACÉN — VISTA INTERNA (solo Francobordo)
        </div>
        <div style="margin-bottom:10px;">
            SKU: <strong><?php echo htmlspecialchars( $sCcodiart !== '' ? $sCcodiart : '—', ENT_QUOTES, 'UTF-8' ); ?></strong>
            &nbsp;·&nbsp; Stock web (tienda): <strong><?php echo $nStockWeb; ?></strong> uds
        </div>
        <?php if ( empty( $aVariantes ) ): ?>
            <div style="color:#a06600;">Sin ubicaciones registradas en VStock para este artículo.</div>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #e0c060;color:#8a6d00;">
                        <th style="padding:4px 8px;">Variante</th>
                        <th style="padding:4px 8px;">Ubicaciones (uds)</th>
                        <th style="padding:4px 8px;text-align:right;">Disponible</th>
                        <th style="padding:4px 8px;text-align:right;">Stock físico</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $aVariantes as $aV ): ?>
                    <tr style="border-bottom:1px solid #f0e2a8;">
                        <td style="padding:4px 8px;vertical-align:top;"><?php echo htmlspecialchars( $aV['label'], ENT_QUOTES, 'UTF-8' ); ?></td>
                        <td style="padding:4px 8px;vertical-align:top;">
                            <?php
                            $aPieces = array();
                            foreach ( $aV['ubis'] as $ub ) {
                                $aPieces[] = '<span style="display:inline-block;background:#fff;border:1px solid #e0c060;border-radius:4px;padding:1px 7px;margin:1px 2px;">'
                                    . htmlspecialchars( $ub[0], ENT_QUOTES, 'UTF-8' )
                                    . ' <span style="color:#8a6d00;">(' . $fmt( $ub[1] ) . ')</span></span>';
                            }
                            echo implode( ' ', $aPieces );
                            ?>
                        </td>
                        <td style="padding:4px 8px;text-align:right;vertical-align:top;"><?php
                            $bDif = abs( $aV['disponible'] - $aV['unidades'] ) > 0.001;
                            echo '<span style="font-weight:600;color:' . ( $bDif ? '#c0392b' : '#2e7d32' ) . ';">' . $fmt( $aV['disponible'] ) . '</span>';
                        ?></td>
                        <td style="padding:4px 8px;text-align:right;vertical-align:top;font-weight:600;color:#555;"><?php echo $fmt( $aV['unidades'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    $sStaffStockPanel = ob_get_clean();
}
