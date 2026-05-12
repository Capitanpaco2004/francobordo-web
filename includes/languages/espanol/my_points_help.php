<?php
/*
  $Id: my_answer.php, V2.1rc2a 2008/OCT/01 16:04:22 dsa_ Exp $
  created by Ben Zukrel, Deep Silver Accessories
  http://www.deep-silver.com
  Reformatted by phocea to use CSS display

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  Released under the GNU General Public License
************************************************************/

// Initialisation of some required parameters for the FAQ answers
 if (tep_not_null(POINTS_AUTO_EXPIRES)){
   $answer_expire = 'Sus puntos expiran a los ' . POINTS_AUTO_EXPIRES . ' meses a contar desde la emisión de los mismos.';
 } else {
   $answer_expire = 'Los puntos no caducan y puden ser acumulados hasta que usted decida usarlos.';
 }

if (POINTS_PER_AMOUNT_PURCHASE > 1) {
  $point_or_answer = 'Respuesta';
} else {
  $point_or_answer = 'Punto';
}

// Definition if the navigation bar and page title
define('NAVBAR_TITLE', 'Promoción de Puntos-Francobordo');
define('HEADING_TITLE', 'Promoción de Puntos-Francobordo');

// Definitions of the FAQ questions
define('POINTS_FAQ_1', '¿En qué consiste la promoción de Puntos-Francobordo?');
define('POINTS_FAQ_2', '¿Cómo puedo participar en la promoción de Puntos-Francobordo?');
define('POINTS_FAQ_3', '¿A cuánto equivale un Punto-Francobordo?');
define('POINTS_FAQ_4', '¿Cómo puedo canjear los Puntos-Francobordo en las compras?');
define('POINTS_FAQ_5', '¿Existe un mínimo de Puntos-Francobordo requerido?');
define('POINTS_FAQ_6', '¿Tengo que realizar una compra mínima para usar los Puntos-Francobordo?');
define('POINTS_FAQ_7', '¿Qué máximo de Puntos-Francobordo puedo usar por pedido?');
define('POINTS_FAQ_8', '¿Puedo ganar Puntos-Francobordo con los gastos de envío?');
define('POINTS_FAQ_9', '¿Puedo ganar Puntos-Francobordo con las tasas e impuestos?');
define('POINTS_FAQ_10', '¿Puedo ganar Puntos-Francobordo en los productos con descuentos?');
define('POINTS_FAQ_11', '¿Puedo ganar Puntos-Francobordo en las compras que se pagan con puntos?');
define('POINTS_FAQ_12', '¿Cómo puedo ganar Puntos-Francobordo por ACONSEJAR COMPRAR EN FRANCOBORDO A MIS AMIGOS?');
define('POINTS_FAQ_13', '¿Cómo puedo ganar Puntos-Francobordo por ESCRIBIR UN COMENTARIO SOBRE UN PRODUCTO?');
define('POINTS_FAQ_14', '¿Qué productos puedo comprar usando los Puntos-Francobordo?');
define('POINTS_FAQ_15', '¿Qué productos NO puedo comprar usando los Puntos-Francobordo?');
define('POINTS_FAQ_16', '¿Cuáles son las condiciones de uso de los Puntos-Francobordo?');
define('POINTS_FAQ_17', '¿Qué hago si algo no funciona o si tengo dudas con los Puntos-Francobordo?');
define('POINTS_FAQ_18', '¿Qué sucede cuando devuelvo un producto?');
// Definition of the answer for each of the questions:

// FAQ1
define('TEXT_FAQ_1', '<em>Cuantos más puntos, mayor descuento en tus compras</em>
	<br >Para agradecer a todos nuestros clientes su apoyo todo este tiempo, hemos lanzado esta gran promoción.
<br ><br >Nuestra recompensa por puntos es tan simple como parece. Mientras que usted compre en ' . STORE_NAME . ' usted acumulará puntos para poder cambiarlos por productos.
<br >Una vez que haya obtenido los puntos, usted los podrá usar para futuras compras  ' . STORE_NAME . '.
<br ><br >Estos puntos solo se obtendrán y se podran gastar comprando a traves de nuestra página Web y no se aplicaran a las compras hechas en Tienda o por Teléfono.
<br ><br >' . $answer_expire . '
<br ><br >Esta promoción se inicia a partir del 01/03/2010 . Todas las compras hechas a partir de esta fecha obtendrán puntos.');

// FAQ2
define('TEXT_FAQ_2', '<em>Compra, comenta o recomiéndanos a tus amigos y ganarás puntos</em>
	<br >Cuando se realiza un pedido, el importe total<small><font color="FF6633">*</font></small> del pedido, será usado para calcular el total de los puntos ganados, -actualmente el 5% del total del pedido sin incluir impuestos y gastos de envío-. Asimismo, podrá conseguir puntos al referenciarnos un amigo o realizar un comentario de calidad sobre nuestros productos.<br>Esos puntos se añadirán a su cuenta como "puntos pendientes".
<br >Todos los puntos pendientes se encuentran en <a href="' . tep_href_link(FILENAME_MY_POINTS) . '"> <u>Mi cuenta >>> Mis puntos y puntos canjeados</u></a> hasta ser validados y aprobados por ' . STORE_NAME . '.
<br ><br >Cuando los puntos pendientes aparezcan como "confirmados", estarán disponibles para ser canjeados por usted en posteriores compras.
<br >' . $points_expire . '
<br >Deberá acceder a su cuenta para poder comprobar el estado de sus puntos.
<br ><br >Durante el proceso de compra (escoja el metodo de envío y forma de pago, etc) usted podrá pagar su pedido usando el saldo de sus puntos.
<p align="right"<small><font color="FF6633">*</font> Los gastos de envío e impuestos no estarán incluidos a la hora de obtener puntos .</small></p>');

// FAQ3
define('TEXT_FAQ_3', '<em>VALOR HOY: 1 PUNTO-FRANCOBORDO = '. number_format(REDEEM_POINT_VALUE, 2, ',', '.') .' €</em>
	<br ><br > En los productos con puntos, con cada ' .$currencies->format(1) . ' del producto sin IVA usted obtendrá ' . number_format(POINTS_PER_AMOUNT_PURCHASE,POINTS_DECIMAL_PLACES)  . ' ' . $point_or_points . ' punto.
<br ><br >
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Precio sin IVA del producto:</b>&nbsp; ' .  $currencies->format(100) . '<br >
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Puntos-Francobordo obtenidos:</b>&nbsp;100<br >
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Valor de los puntos obtenidos:</b>&nbsp; ' .  $currencies->format(tep_calc_shopping_pvalue(100 * POINTS_PER_AMOUNT_PURCHASE)) . ' <br ><br >
IMPORTANTE: Francobordo.com se reserva el derecho de modificar el valor de los Puntos-Francobordo en cualquier momento y sin previo aviso. El valor de puntos que se muestra aquí siempre estará actualizado.
<p align="right"><small>Última modificación: ' . tep_get_last_date('REDEEM_POINT_VALUE') . '</small><p>');

// FAQ4
define('TEXT_FAQ_4', '<em>Canjea tus puntos por artículos cuando quieras</em>
	<br >Si usted tiene puntos acumulados en su cuenta de Puntos para Compras, usted puede usar esos puntos como parte del pago de las compras que realice en ' . STORE_NAME . '.
<br >Durante el proceso de compra, en la misma página donde usted seleciona el medio de pago, habrá una opción que podrá seleccionar si desea canjear los Puntos-Francobordo como parte del pago. Marque la casilla para usar la totalidad de puntos de los que dispone.
Por favor, tenga en cuenta que siempre tendrá que seleccionar un medio de pago para poder completar el proceso de compra, incluso cuando tenga puntos suficientes para pagar la totalidad de la compra. En este caso, deberá seleccionar como forma de pago "Transferencia" para completar el proceso, pero no tendrá que realizar el pago correspondiente.
<br >Continuar con el proceso de compra hasta llegar a la página de Confirmación de Pedido, y compruebe que el valor de sus puntos ha sido descontado del importe total de su compra. Después de confirmar su pedido, su cuenta de puntos será actualizada y los puntos usados serán deducidos de su saldo.
<br >Tenga en cuenta  que cualquier compra hecha con Puntos-Francobordo también será bonificada con más Puntos-Francobordo sobre el importe de la compra no pagado con puntos.');


// FAQ5 - conditionnal depending on the point limit value set in admin
if (POINTS_LIMIT_VALUE  > 0)  {
	define('TEXT_FAQ_5', '<em>Cuando tengas más de' . number_format(POINTS_LIMIT_VALUE) . ' puntos, podrás canjearlos </em>
	<br >Actualmente, un saldo mínimo de<b>' . number_format(POINTS_LIMIT_VALUE) . '</b> puntos <b>(' . $currencies->format(tep_calc_shopping_pvalue(POINTS_LIMIT_VALUE)) . ')' . '</b> es necesario antes de que ustes pueda usarlos como descuento.
	<br >Le aconsejamos que visite esta página con cierta frecuencia, ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Modificación: ' . tep_get_last_date('POINTS_LIMIT_VALUE') . '</small></p>');
} else {
	define('TEXT_FAQ_5', '<em>Canjea tus puntos en cualquier momento</em>
	<br >Actualmente, no hay saldo mínimo necesario para descontar sus puntos. Por favor, tenga en cuenta que tendrá que escoger otro medio de pago para poder cubrir el total de su compra.<br >
	<br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Modificación ' . tep_get_last_date('POINTS_LIMIT_VALUE') . '</small></p>');
}

// FAQ6 - conditionnal depending on the point min amount value set in admin
if(tep_not_null(POINTS_MIN_AMOUNT))  {
	define('TEXT_FAQ_6', '<em>Canjea tus puntos a partir de compras por valor de' . $currencies->format(POINTS_MIN_AMOUNT) . '</em>
	<br >Actualmente, un mínimo de <b>' . $currencies->format(POINTS_MIN_AMOUNT) . '</b> en el total (por compra) es necesario antes de poder descontar los puntos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Modificación: ' . tep_get_last_date('POINTS_MIN_AMOUNT') . '</small></p>');
} else {
	define('TEXT_FAQ_6', '<em>Compra lo que quieras con tus puntos</em>
	<br >Actualmente, no hay importe mínimo necesario para descontar sus puntos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Modificación ' . tep_get_last_date('POINTS_MIN_AMOUNT') . '</small></p>');
}

// FAQ7
define('TEXT_FAQ_7', '<em>Canjea el 100% de tus puntos en la compra que quieras</em>
	<br >Un máximo de <b>' . number_format(POINTS_MAX_VALUE) . '</b> puntos <b>' . '</b> esta permitido para descontar por pedido.
<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
<p align="right"><small>Última Modificación ' . tep_get_last_date('POINTS_MAX_VALUE') . '</small></p>');

// FAQ8 - conditionnal depending on the use point for shipping value set in admin
if(USE_POINTS_FOR_SHIPPING == 'false')  {
	define('TEXT_FAQ_8', 'No. Al calcular la cantidad de puntos obtenidos, se excluyen los gastos de envío.
	<p align="right"><small>Última Modificación ' . tep_get_last_date('USE_POINTS_FOR_SHIPPING') . '</small></p>');
} else {
#---------------------- DO NOT EDIT  EOF ----------------------------  
 	define('TEXT_FAQ_8', 'Si. Al calcular la cantidad de puntos obtenidos, los gastos de envío estan incluidos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_SHIPPING') . '</small></p>');
}

// FAQ9 - conditionnal depending on the value set in admin for ginving point for tax value
if(USE_POINTS_FOR_TAX == 'false')  {
	define('TEXT_FAQ_9', 'No. Al calcular el total de los puntos obtenidos, los impuestos están excluidos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_TAX') . '</small></p>');
} else {
	define('TEXT_FAQ_9', 'Si. Al calcular la cantidad de los puntos obtenidos, los impuestos están incluidos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_TAX') . '</small></p>');
}	 

// FAQ10 - conditionnal depending on value set in admin for giving point on specials
if(USE_POINTS_FOR_SPECIALS == 'false')  {
	define('TEXT_FAQ_10', 	'No. Al calcular la cantidad de puntos obtenidos, todos estos artículos serán excluidos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_SPECIALS') . '</small></p>');
} else {
	define('TEXT_FAQ_10', 'Si. Al calcular el total de puntos obtenidos, todos estos artículos serán incluidos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_SPECIALS') . '</small></p>');
}

// FAQ11
if(USE_POINTS_FOR_REDEEMED == 'false')  {
	define('TEXT_FAQ_11', 'No. Cuando se calculan los puntos obtenidos, cualquier compra hecha mediante puntos ganados, serán excluidos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_REDEEMED') . '</small></p>');
} else {
	define('TEXT_FAQ_11', 'Si. Por favor tenga en cuenta que cualquier compra hecha con puntos ganados con anteriores compras, serán recompensados con puntos adicionales para canjear en futuras compras.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_REDEEMED') . '</small></p>');
}

// FAQ12
if (tep_not_null(USE_REFERRAL_SYSTEM)){
	define('TEXT_FAQ_12', '<em>Te recompensamos con Puntos-Francobordo por cada cliente referenciado por ti</em>
	<br >Si tus amigos y conocidos compran en Francobordo.com e indican tu e-mail en el campo <b>"Bonificación de puntos por clientes referenciados"</b> del proceso de compra, podrás conseguir <b>' .  USE_REFERRAL_SYSTEM . '</b> PUNTOS EXTRA, una vez el pedido haya sido procesado.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_REFERRAL_SYSTEM') . '</small></p>');
} else {
	define('TEXT_FAQ_12', 'Actualmente este recurso está desactivado.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_REFERRAL_SYSTEM') . '</small></p>');
}

// FAQ13
if (tep_not_null(USE_POINTS_FOR_REVIEWS)){
	define('TEXT_FAQ_13', '<em>"Escriba su opinión e impulse a otros a leer sus consejos."</em>
	<br >Intercambio de opiniones sobre nuestros productos nos ayudará a mejorar continuamente nuestras ofertas y servicios para usted, y ayudar a otros clientes a elegir los productos adecuados.
	<br >Queremos agradecer los comentarios que usted realice sobre nuestros productos, gratificándole con<b> 20 puntos</b> por cada <i>comentario de calidad</i> que escriba, que ingresaremos en su cuenta de Puntos-Francobordo
	<br >La <i>calidad de los comentarios</i> sobre los productos estará determinada por cumplimiento de las siguientes condiciones:
	<ul>
  	   <li>Los comentarios deberán ser originales.</li>
  	   <li>Los comentarios deberán ser concisos y concretos, en el análisis de productos.</li>
	   <li>Los comentarios deberán ser realistas y objetivos.</li>
  	   <li>Los comentarios no podrán duplicar el contenido ya publicado.</li>
  	   <li>Los comentarios no podrán incluir publicidad, contenidos comerciales, enlaces a otras páginas web, o cualquier otro contenido susceptible de ser calificado como SPAM.</li>
  	   <li>Los comentarios no podrán incluir contenido abusivo, amenazante, insultante, provocativo, vulgar o difamatorio.</li>
	   <li>Los comentarios no prdrán superar los cinco mensuales .</li>
	</ul>
	' . STORE_NAME .' se reserva el derecho de rechazar o eliminar los comentarios que no cumplan las condiciones anteriores.
	<br >El equipo de' . STORE_NAME .' podrá corregir errores ortográficos y errores gramaticales.
	<br >' . STORE_NAME .' no se responsabilizará de ninguna manera por las opiniones y comentarios enviados por sus clientes.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_REVIEWS') . '</small></p>');
} else {
	define('TEXT_FAQ_13', 'Actualmente este recurso está desactivado.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('USE_POINTS_FOR_REVIEWS') . '</small></p>');
}

// FAQ14
if (tep_not_null(RESTRICTION_MODEL)) {
	define('TEXT_FAQ_14', 'Actualmente, los artículos con los modelos <b>[' . RESTRICTION_MODEL . ']</b> pueden comprados usando su saldo de puntos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('RESTRICTION_MODEL') . '</small></p>');
}
if (tep_not_null(RESTRICTION_PID)) { 
	$p_ids = preg_split("/[,]/", RESTRICTION_PID);
	for ($i = 0; $i < count($p_ids); $i++) {
		$prods_query = tep_db_query("SELECT * FROM products, products_description WHERE products.products_id = products_description.products_id and products_description.language_id = '" . $languages_id . "'and products.products_id = '" . $p_ids[$i] . "'");
		if ($list = tep_db_fetch_array($prods_query)) {
			$prods .= '<li>' . $list['products_name'] .'</li>';
		}
	}
	
	define('TEXT_FAQ_14', 'Actualmente, únicamente los siguientes productos pueden ser comprados usando su saldo de puntos.<ul>' . $prods . '</ul>
	<br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('RESTRICTION_PID') . '</small></p>');
}
if (tep_not_null(RESTRICTION_PATH)) {
	$cat_path = preg_split("/[,]/", RESTRICTION_PATH);
        for ($i = 0; $i < count($cat_path); $i++) {
        	$cat_path_query = tep_db_query("select * from " . TABLE_CATEGORIES . ", " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories.categories_id = categories_description.categories_id and categories_description.language_id = '" . $languages_id . "' and categories.categories_id='" . $cat_path[$i] . "'");
        	if ($list = tep_db_fetch_array($cat_path_query)) {
        		$cats .= '<li>' . $list['categories_name'] .'</li>';
        	}
        }
	define('TEXT_FAQ_14', 'Actualmente, solo los productos pertenecientes a las siguientes categorías y sus correspondientes subcategorías pueden ser comprados usando su saldo de puntos.<ul>' . $cats . '</ul>
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('RESTRICTION_PATH') . '</small></p>');
 } else {
	define('TEXT_FAQ_14', 'Actualmente, no hay ninguna restricción sobre los artículos que pueden ser comprados usando su saldo de puntos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('RESTRICTION_PATH') . '</small></p>');
}

// FAQ15
if (REDEMPTION_DISCOUNTED == 'true') {
	define('TEXT_FAQ_15', 'Actualmente, existen artículos que fueron descontados para poder comprarlos utilizando su saldo de puntos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('REDEMPTION_DISCOUNTED') . '</small></p>');
} else {
	define('TEXT_FAQ_15', 'Actualmente, no hay ninguna restricción sobre ningún artículo para que puedan ser comprados usando su saldo de puntos.
	<br ><br >Le aconsejamos que visite esta página con cierta frecuencia ya que podría haber modificaciones sobre esta política.
	<p align="right"><small>Última Actualización: ' . tep_get_last_date('REDEMPTION_DISCOUNTED') . '</small></p>');
}

// FAQ16
define('TEXT_FAQ_16', '
<ul>
  <li>Los puntos están disponibles solo para clientes registrados en ' . STORE_NAME . ' </li>
  <li>Los puntos sólo pueden ser obtenidos realizando compras online, escribiendo comentarios y referenciando clientes. Los puntos sólo pueden ser canjeados en compras online y solo son válidos en ' . STORE_NAME . '.</li>
  <li>Los puntos no pueden ser reembolsables y no pueden transferirse a otras personas.</li>
  <li>Los puntos no podrán ser canjeables por dinero en efectivo bajo ninguna circunstancia.</li>
  <li>Los puntos generados por pedidos que posteriormente se cancelen, serán eliminados.</li>
  <li>Al comprar con puntos, usted tendrá que escoger otro medio de pago si no tiene suficientes puntos para cubrir el importe total de su compra.</li>
  <li>Al calcular el importe total de los puntos obtenidos los impuestos y gastos de envío serán excluidos a efectos de cálculo.</li>
</ul>
Por favor, tenga en cuenta que nos reservamos el derecho de hacer alteraciones sobre esta política en cualquier momento, sin aviso previo o responsabilidad.');

// FAQ17
define('TEXT_FAQ_17', 'Para todas las preguntas a respecto a nuestro programa de puntos, por favor <a href="' . tep_href_link(FILENAME_CONTACT_US) . '"> <u>contáctenos </u></a>.  Asegúrese de proporcionar tanta información como sea posible en el e-mail.');
// FAQ18
define('TEXT_FAQ_18', '
<ul>
<li>Los puntos obtenidos por la compra de ese producto serán descontados de su cuenta de puntos.</li>
<li>Si utilizó sus puntos como forma de pago, en el caso de devolver alguno de los productos incluidos en ese pedido, los puntos utilizados no se retornarán a su cuenta de puntos.</li>
</ul>
');
//BOF Mobile
define('TEXT_INFORMATION_MOBILE', '<b>Por favor, escoja uno de los temas de a continuación:</b>');
//EOF Mobile

// Below is the section that will actually displax on the FAQ page
define('TEXT_INFORMATION2', '<a name="Top"></a><span class="pointWarning"><b>Por favor, escoja uno de los temas de a continuación:</b></span>');
?>