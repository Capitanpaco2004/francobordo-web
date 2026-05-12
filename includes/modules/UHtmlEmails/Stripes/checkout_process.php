<?php
require((preg_match( '/(\_admin)/i', $_SERVER['PHP_SELF'] ) ? '../' : '') . DIR_WS_LANGUAGES . $language . '/modules/UHtmlEmails/Standard/checkout_process.php');

$HTML_Email_product_attributes = array();

for( $i = 0, $n = sizeof( $order->products ); $i < $n; $i++ )
{
	if( isset( $order->products[$i]['attributes'] ) )
	{
		$HTML_Email_product_attributes[$i] = '';

		for( $j = 0, $n2 = sizeof( $order->products[$i]['attributes'] ); $j < $n2; $j++ )
		{
			if( DOWNLOAD_ENABLED == 'true' )
			{
				$attributes = tep_db_query( 'SELECT popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
											 FROM ' . TABLE_PRODUCTS_OPTIONS . ' popt
											 INNER JOIN ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa ON (pa.options_id = popt.products_options_id )
											 INNER JOIN ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' poval ON (pa.options_values_id = poval.products_options_values_id )
											 LEFT JOIN ' . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . ' pad ON (pa.products_attributes_id=pad.products_attributes_id)
											 WHERE pa.products_id = "' . $order->products[$i]['id'] . '"
											 AND pa.options_id = "' . $order->products[$i]['attributes'][$j]['option_id'] . '"
											 AND pa.options_values_id = "' . $order->products[$i]['attributes'][$j]['value_id'] . '"
											 AND popt.language_id = "' . $languages_id . '"
											 AND poval.language_id = "' . $languages_id . '"' );
			}
			else
			{
				$attributes = tep_db_query( 'SELECT popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix
											 FROM ' . TABLE_PRODUCTS_OPTIONS . ' popt
											 INNER JOIN ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa ON (pa.options_id = popt.products_options_id)
											 INNER JOIN ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' poval ON (pa.options_values_id = poval.products_options_values_id)
											 WHERE pa.products_id = "' . $order->products[$i]['id'] . '"
											 AND pa.options_id = "' . $order->products[$i]['attributes'][$j]['option_id'] . '"
											 AND pa.options_values_id = "' . $order->products[$i]['attributes'][$j]['value_id'] . '"
											 AND popt.language_id = "' . $languages_id . '"
											 AND poval.language_id = "' . $languages_id . '"' );
			}

			$attributes_values = tep_db_fetch_array( $attributes );

			$HTML_Email_product_attributes[$i] .= '
			<table style="padding-left: 8px; color: #5f5f5f; font-family: Arial; line-height: 14px; font-size:13px;" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td valign="top"><br>-&nbsp;</td>
					<td><i><br>' . $attributes_values['products_options_name'] . ':<br>' . $attributes_values['products_options_values_name'] . '</i></td>
				</tr>
			</table>';
		}
	}
	else
		$HTML_Email_product_attributes[$i] = '';
}

$url = HTTP_SERVER . DIR_WS_CATALOG ;
$ArrayLNTargets = array( "\r\n", "\n\r", "\n", "\r", "\t" );
$bg_sides_url = HTTP_SERVER . DIR_WS_CATALOG . DIR_WS_MODULES . 'UHtmlEmails/' . ULTIMATE_HTML_EMAIL_LAYOUT . '/bg_red.jpg';

// Bloque "confirmación del pedido": fecha estimada de entrega + texto de sostenibilidad
// solicitado por el cliente. Se incluye siempre que haya $insert_id válido.
$confirmation_block_html = '';
$estimated_pretty = '';
if( isset( $insert_id ) && (int)$insert_id > 0 && file_exists( DIR_FS_CATALOG . DIR_WS_MODULES . 'delivery_estimate/delivery_estimate.php' ) ) {
	require_once( DIR_FS_CATALOG . DIR_WS_MODULES . 'delivery_estimate/delivery_estimate.php' );
	$de_row = delivery_estimate::getCurrent( (int)$insert_id );
	if( $de_row && ! empty( $de_row['estimated_date'] ) && $de_row['estimated_date'] !== '0000-00-00' ) {
		$estimated_pretty = date( 'd/m/Y', strtotime( $de_row['estimated_date'] ) );
	}
}

$confirmation_block_html = '
	<table width="100%" style="margin: 0; padding: 0 30px 25px; line-height: 22px; font-size: 14px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f;" border="0" cellspacing="0" cellpadding="0">';
if( $estimated_pretty != '' ) {
	$confirmation_block_html .= '
		<tr>
			<td style="padding: 18px 22px; background-color: #f4fbfe; border-left: 4px solid #2bb0e2;">
				<div style="font-size: 16px; font-weight: bold; color: #2bb0e2; margin-bottom: 6px;">' . UHE_TEXT_ESTIMATED_DELIVERY_TITLE . '</div>
				<div>' . UHE_TEXT_ESTIMATED_DELIVERY_INTRO . ' <b style="color: #2f2f2f; font-size: 17px;">' . $estimated_pretty . '</b></div>
			</td>
		</tr>
		<tr><td style="height: 18px;">&nbsp;</td></tr>';
}
$confirmation_block_html .= '
		<tr>
			<td style="padding: 18px 22px; background-color: #f7faf3; border-left: 4px solid #58d972;">
				<div style="font-size: 15px; font-weight: bold; color: #3d8b46; margin-bottom: 8px;">' . UHE_TEXT_ENVIRONMENT_TITLE . '</div>
				<div style="font-size: 13px; color: #4a4a4a; line-height: 19px; font-style: italic;">' . UHE_TEXT_ENVIRONMENT_BODY . '</div>
			</td>
		</tr>
	</table>';

$html_email = '
<html>
	<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>
	<body style="margin:0; padding:0;">
		<table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
			<tbody>
				<tr>
					<td background="'.$bg_sides_url.'">&nbsp;</td>
					<td bgcolor="#FFFFFF" width="780" valign="top" align="left" style="vertical-align:top; width: 728px;">
						<table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
							<tbody>
								<tr>
									<td colspan="2" bgcolor="#FFFFFF" align="center" height="121" valign="middle">
										<a href="' . $url . '">
											<img width="780" height="121" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/cbcr.jpg" style="display:block; border: 0;">
										</a>
									</td>
								</tr>
								<tr>
									<td colspan="2" style="padding: 35px 0 25px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f; line-height: 24px;">
										<span style="padding: 0 30px; font-size: 18px; font-weight: bold; line-height: 14px; color: #1fa1d0;">' . UHE_TEXT_DEAR . ' ' . $order->customer['firstname'] . ' ' . $order->customer['lastname'] . '</span>
										<br>
										<span style="padding: 0 30px; line-height: 37px;">' . UHE_MESSAGE_GREETING . '
										<br><br>
										<table width="100%" style="line-height: 14px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #23a2d1;" border="0" cellspacing="0" cellpadding="0">
											<tr>
												<td width="50%" style="vertical-align: top; border-top: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4; border-right: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
													' . UHE_TEXT_ORDER_NUMBER . ' &nbsp;<b style="color: #4f4f4f;">' . $insert_id . '</b>
												</td>
												<td style="padding: 13px 23px; border-top: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4;">' . UHE_TEXT_DATE_ORDERED . ' &nbsp;<font style="color: #5f5f5f;">' . date('d/m/Y') . '</font></td>
											</tr>';
											if( $order->info['comments'] )
											{
											$html_email .= '
											<tr>
												<td colspan="2" style="vertical-align: top; border-bottom: 1px solid #e4e4e4; padding: 13px 0 13px 16px; text-align: left;">
													' . UHE_TEXT_COMMENTS . ' &nbsp;<i style="color: #606060;">' . str_replace( $ArrayLNTargets, '<br />', tep_db_output( $order->info['comments'] ) ) . '</i>
												</td>
											</tr>';
											}
										$html_email .= '
										</table>
										<br>
										<table border="0" cellspacing="0" cellpadding="0">
											<tr>';
											if ($order->content_type != 'virtual')
											{
												$html_email .= '
												<td>
													<table style="padding: 0 0 0 30px; line-height: 24px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f;" border="0" cellspacing="0" cellpadding="0">
														<tr>
															<td width="324" style="padding: 0 0 10px 30px; border-bottom: 1px solid #e4e4e4; text-align: left;">
																<span style="display: inline-block;">
																	<img width="26" height="24" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/point.jpg" style="display: block; border: 0;">
																</span>
																<b style="color: #b4b4b4; font-weight: bold">&nbsp;' . UHE_TEXT_DELIVERY_ADDRESS . '</b>
															</td>
														</tr>
														<tr>
															<td style="padding: 0 0 0 30px;">
																<br>
																' . tep_address_label( $customer_id, $sendto, 0, '', '<br />' ) . '
															</td>
														</tr>
													</table>
												</td>';
											}
												$html_email .= '
												<td>
													<table style="padding: 0 0 0 30px; line-height: 24px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f;" border="0" cellspacing="0" cellpadding="0">
														<tr>
															<td width="324" style="padding: 0 0 10px 30px; border-bottom: 1px solid #e4e4e4; text-align: left;">
																<span style="display: inline-block; vertical-align: middle;">
																	<img width="26" height="24" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/factu.jpg" style="display: block; border: 0;">
																</span>
																<b style="color: #b4b4b4; font-weight: bold">&nbsp;' . UHE_TEXT_BILLING_ADDRESS . '</b>
															</td>
														</tr>
														<tr>
															<td style="padding: 0 0 0 30px;">
																<br>
																' . tep_address_label( $customer_id, $billto, 0, '', '<br />' ) . '
															</td>
														</tr>
													</table>
												</td>
											</tr>
										</table><br>
										<table style="font-family: Arial;" border="0" cellpadding="18" cellspacing="0">
											<tr style="color:#fff; font-weight:bold; font-size: 16px; line-height: 14px;">
												<td align="center" width="216" bgcolor="#2bb0e2" style="border-right: 1px solid #fff;">' . UHE_TEXT_PRODUCTS_ARTICLE . '</td>
												<td align="center" width="145" bgcolor="#2bb0e2" style="border-right: 1px solid #fff;">' . UHE_TEXT_PRODUCTS_MODEL . '</td>
												<td align="center" width="134" bgcolor="#2bb0e2" style="border-right: 1px solid #fff;">' . UHE_TEXT_PRODUCTS_PRICE . '</td>
												<td align="center" width="155" bgcolor="#2bb0e2" style="border-right: 1px solid #fff;">' . UHE_TEXT_PRODUCTS_QTY . '</td>
												<td align="center" width="126" bgcolor="#2bb0e2">' . UHE_TEXT_PRODUCTS_TOTAL . '</td>
											</tr>';

											for( $i = 0, $n = sizeof( $order->products ); $i < $n; $i++ )
											{
											$html_email .='
											<tr style="color: #5f5f5f; font-size: 15px; line-height: 18px;">
												<td valign="top" align="left" style="border-right: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4;">' . $order->products[$i]['name'] . $HTML_Email_product_attributes[$i].'</font></td>
												<td valign="top" align="center" style="border-right: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4;">' . $order->products[$i]['model'] . '</td>
												<td valign="top" align="center" style="border-right: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4;">' . $currencies->display_price( $order->products[$i]['final_price'], $order->products[$i]['tax'], 1 ) . '</td>
												<td valign="top" align="center" style="border-right: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4;">' . $order->products[$i]['qty'] . '</td>
												<td valign="top" align="center" style="color: #3e3e3e; border-bottom: 1px solid #e4e4e4;"><b>' . $currencies->display_price( $order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty'] ) . '</b></td>
											</tr>';
											}

											for( $i = 0, $n = sizeof( $order_totals ); $i < $n; $i++ )
											{
												$temp_title = trim( strip_tags( $order_totals[$i]['title'] ) );
												if( substr( $temp_title, -1 ) == ':' )
													$temp_title = substr( $temp_title, 0, -1 );

												if( $i+1 == $n )
												{
												$html_email .= '
												<tr style="color: #5f5f5f; font-size: 15px; line-height: 18px; color: #5f5f5f;">
													<td align="center" width="134" bgcolor="#2bb0e2" style="border-right: 1px solid #fff; color: #fff; font-size: 16px;"><b>'. $temp_title . '</b></td>
													<td align="center" colspan="2" bgcolor="#008ec3" style="color: #fff; font-size: 22px;"><b>' . strip_tags( $order_totals[$i]['text'] ) . '</b></td>
												</tr>';
												}
												else
												{
												$html_email .= '
												<tr style="color: #5f5f5f; font-size: 15px; line-height: 18px; color: #5f5f5f;">' .
										($i == 0 ? '<td rowspan="4" colspan="2" bgcolor="#d2f3ff" align="center"><img width="227" height="154" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/logorder.jpg" style="display:block; border: 0;"></td>' : '') . '
													<td align="center" width="134" style="border-right: 1px solid #e4e4e4; border-bottom: 1px solid #e4e4e4; color: #22a2d1;">'. $temp_title . ':</td>
													<td align="center" colspan="2" valign="top" style="border-bottom: 1px solid #e4e4e4;">' . strip_tags( $order_totals[$i]['text'] ) . '</td>
												</tr>';
												}
											}

										$html_email .= '
										</table>
										<br>
										<font style="padding: 0 30px; color: #21a1d1; line-height: 14px;">' . UHE_TEXT_INVOICE_URL . ' &nbsp;&nbsp;<a href="' . tep_href_link( FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $insert_id, 'SSL', false ) .'" style="color: #606060;">' . tep_href_link( FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $insert_id, 'SSL', false ) . '</a></font>
										<br>
									</td>
								</tr>';

								if( is_object( $$payment ) )
								{
								$html_email .= '
								<tr>
									<td colspan="2" align="center" style="border-top: 1px solid #e4e4e4; padding: 35px 55px 30px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #5f5f5f; line-height: 18px;">
										<font color="#27a5d2">' . UHE_TEXT_PAYMENT_METHOD . '</font> <b>' . $order->info['payment_method'] . '</b><br>
										<br>';
										$payment_class = $$payment;
										if( $payment_class->email_footer )
											$html_email .= str_replace( $ArrayLNTargets, '<br />', $payment_class->email_footer ) . '<br />';
								$html_email .= '
									</td>
								</tr>';
								}

								$html_email .= '
								<tr>
									<td colspan="2" style="border-top: 1px solid #e4e4e4; padding: 25px 0 0;">
										' . $confirmation_block_html . '
									</td>
								</tr>
								<tr>
									<td colspan="2" style="border-top: 1px solid #e4e4e4; padding: 25px 30px 100px; font-size: 15px; font-family: Arial; letter-spacing: 0.2px; color: #606060; line-height: 14px;">
										' . UHE_TEXT_CONTACT . '
									</td>
								</tr>
								<tr>
									<td colspan="2" align="center" style="border-top: 1px solid #e4e4e4; padding: 25px 35px; font-family: Arial; font-size: 13px; font-style: italic; color: #908e8c; line-height: 18px;">
										' . EMAIL_POLITICA . '
									</td>
								</tr>
								<tr>
									<td align="center" height="176">
										<a href="' . $url . '">
											<img width="321" height="176" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/footer1.jpg" style="display:block; border: 0;">
										</a>
									</td>
									<td align="center" height="176">
										<table width="100%" align="center"  border="0" cellpadding="0" cellspacing="0">
											<tr>
												<td>
													<a href="' . $url . 'nautica-c-482.html">
														<img width="137" height="93" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/nautica.jpg" style="display:block; border: 0;">
													</a>
												</td>
												<td>
													<a href="' . $url . 'pesca-c-56.html">
														<img width="98" height="93" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/pesca.jpg" style="display:block; border: 0;">
													</a>
												</td>
												<td>
													<a href="' . $url . 'tiempo-libre-c-373.html">
														<img width="98" height="93" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/tiempo.jpg" style="display:block; border: 0;">
													</a>
												</td>
												<td>
													<a href="' . $url . 'submarinismo-c-491.html">
														<img width="126" height="93" border="0" src="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/submarinismo.jpg" style="display:block; border: 0;">
													</a>
												</td>
											</tr>
											<tr>
												<td colspan="4" align="right" width="459" height="83" background="' . HTTP_SERVER . DIR_WS_CATALOG . DIR_THEME . 'images/email/fotr-ctct.jpg" style="position: relative;">
													<span style="font-size: 13px; color: rgb(150, 147, 146); font-family: arial; letter-spacing: -0.1px; display: block; padding-right: 29px;">
													' . PIE_EMAIL . '
													</span>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</tbody>
						</table>
					</td>
					<td background="'.$bg_sides_url.'">&nbsp;</td>
				</tr>
			</tbody>
		</table>
	</body>
</html>';

$html_email = str_replace( $ArrayLNTargets, '', $html_email );

?>