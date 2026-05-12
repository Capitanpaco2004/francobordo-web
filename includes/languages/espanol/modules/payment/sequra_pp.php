<?php
define('MODULE_PAYMENT_SEQURA_PP_TEXT_TITLE', 'SeQura PP');
define('MODULE_PAYMENT_SEQURA_PP_TEXT_PUBLIC_TITLE', 'Fracciona tu pago con SeQura');
define('MODULE_PAYMENT_SEQURA_PP_TEXT_DESCRIPTION', '<img src="images/icon_info.gif" border="0" />&nbsp;<a href="https://sequra.es/es/ops/oscommerce" target="_blank" style="text-decoration: underline; font-weight: bold;">View Online Documentation</a>');
define('MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_TITLE','Fraccionar pagos');
define('MODULE_PAYMENT_SEQURA_PP_TEXT_PUBLIC_DESCRIPTION',"");
if (!defined('MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_TITLE')) define('MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_TITLE',"<img src='" . DIR_WS_MODULES . "payment/SeQura/view/img/mrq.png' alt='P&aacute;galo en 3, 6, o 12 cuotas *sin intereses*'/>Fraccionar tu pago. Sin intereses y s&oacute;lo un coste por cuota");
define('MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_HEADER','
<p id="sequra_additional_text">
                <ul>
                    <li>La aprobaci&oacute;n es instant&aacute;nea, por lo que los productos se env&iacute;an de forma inmediata</li>
                    <li>El &uacute;nico coste es de 3&euro; por cuota o de 5&euro; si el pedido es superior a 200&euro;. Sin intereses ocultos ni letra peque&ntilde;a.</li>
                    <li>El primer pago se hace con tarjeta en el momento de la realizaci&oacute;n del pedido. Los pagos siguientes se cargar&aacute;n autom&aacute;ticamente cada mes en la tarjeta.</li>
                    <li>Puedes pagar la totalidad cuando t&uacute; quieras</li>
                    <li>El servicio es ofrecido junto con <a target="_blank" href="https://sequra.es/es/fraccionados">SeQura</a></li>
                </ul>
                </br>
&iquest;Tienes alguna pregunta? Habla con SeQura a trav&eacute;s del 93 176 00 08
</p>
');
define('MODULE_PAYMENT_SEQURA_PP_TEXT_CONFIRMATION_OTHER_METHODS','&#9668; Otros m&eacute;todos de pago');
define('MODULE_PAYMENT_SEQURA_PP_TEXT_ERROR_SOLICITATION','Forma de pago no disponible en estos momentos. Seleccione otra, por favor.');
define('MODULE_PAYMENT_SEQURA_PP_TEXT_ERROR_CART_CHANGED','El contenido del pedido ha cambiado.');