<?php
	// Variables
	$sHtml = '';

	// Obtenemos todos los productos del pedido
	$aDatosOrdersProducts = tep_db_query( 'select p.products_id, p.products_image, op.products_name
										   from products p
										   inner join orders_products op on (p.products_id = op.products_id)
										   where op.orders_id = ' . $aDatoOpinion['orders_id'] );

	// Recreamos la tabla
	while( $aDatoOrderProduct = tep_db_fetch_array( $aDatosOrdersProducts ) )
	{
		$sHtml .= '<tr>
			<td width="148"><img border="0" alt="' . $aDatoOrderProduct['products_name'] . '" src="' . HTTP_SERVER . DIR_WS_CATALOG . 'product_thumb.php?img=images/productos/' . $aDatoOrderProduct['products_image'] . '&w=120&h=120"/></td>
			<td width="196">' . $aDatoOrderProduct['products_name'] . '</td>
			<td width="240"><a href="' . HTTP_SERVER . DIR_WS_CATALOG . 'product_info.php?products_id=' . $aDatoOrderProduct["products_id"]  . '#cmtr-buttn"><img width="189" height="48" src="' . HTTP_SERVER . DIR_WS_CATALOG . 'theme/web/images/general/email-opinion-boton.png"/></a></td>
		</tr>';
	}	
	
	$sHtmlEmail = '
<html>
	<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
	<body style="margin:0; padding:0;">
		<table width="100%"  border="0" cellpadding="0" cellspacing="0" style="border:black solid 0px; height:100%;">
			<tr>
				<td background="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES . 'UHtmlEmails/Stripes/bg_red.jpg">&nbsp;</td>
				<td width="600" align="center">
					<div align="left" style="font-size: 14px; width:600; padding:0 2em; border: black solid 0px; background-color:#FFFFFF; height:100%;">
						<br/>
						<a href="' . HTTP_SERVER . DIR_WS_CATALOG . '"><img src="' . HTTP_SERVER . DIR_WS_CATALOG . 'theme/web/logo-trans.png"/></a><br/><br/><br/>
						<font face="Times New Roman, Times, serif" style="font-size:14px;">
							<span style="font-size: 24px;">' . sprintf( OPINION_EMAIL_SALUDO, $aDatoOrder['customers_name'] ) .',</span><br/><br/>
						</font>';
				
						$sHtmlEmail .= sprintf( OPINION_EMAIL_POSTERIOR_CUERPO,
							tep_href_link( '/', '', 'NONSSL', false ),
							$sHtml
						);

						$sHtmlEmail .= OPINION_EMAIL_PIE;

					$sHtmlEmail .='</div>
				</td>
				<td background="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES . 'UHtmlEmails/Stripes/bg_red.jpg">&nbsp;</td>
			</tr>
		</table>
	</body>
</html>';
?>