<?php
	define( 'OPINION_EMAIL_SUBJECT', 'Califica tu compra en ' . STORE_NAME );

	define( 'OPINION_EMAIL_ENCABEZADO', '<p>Una vez más gracias por comprar en ' . STORE_NAME . '.</p>' );
	define( 'OPINION_EMAIL_CUERPO', '<p>Trabajamos para ofrecerte el mejor servicio posible, y por eso queremos conocer tu opinión sobre tu experiencia de compra. </p><br/><a href="%s"><img width="591" height="129" src="' . HTTP_SERVER . DIR_WS_CATALOG . 'theme/web/images/general/email-opinion-escribe.png"/></a><br/><br/>' );
	define( 'OPINION_EMAIL_PIE', '<p>Tu valoración nos ayudará a mejorar. Muchísimas gracias por tu colaboración.</p><p>Un Saludo,</p><p>' . STORE_NAME . '.</p>' );

	define( 'OPINION_EMAIL_POSTERIOR_SUBJECT', 'Queremos saber tu opinion sobre tu ultima compra en ' . STORE_NAME . ' ...' );

	define( 'OPINION_EMAIL_SALUDO', 'Hola %s' );
	define( 'OPINION_EMAIL_POSTERIOR_CUERPO', '<p>Hace unos días realizaste un pedido en nuestra web <a href="%s">' . STORE_NAME . '</a> Esperamos que hayas tenido tiempo de probar los productos que compraste.</p><p>Por ese motivo, queremos saber qué te parecen y que puntuación le darías a  cada uno de ellos (y dar tu opinión personal si quieres). De este modo podrás ayudar a otros usuarios que se muestren indecisos a la hora de comprar un producto y entre todos poder conseguir una web más fiable, cómoda y completa  para todos vosotros.</p><p>Pincha en cada artículo y anota tu puntuación  y tu opinión sobre el producto en la pestaña Comentarios de la ficha del producto.</p><br/><table width="100%%">%s</table>' );
	define( 'OPINION_EMAIL_PIE', '<br/><br/><p>Una vez más, muchas gracias por tu colaboración y por ayudarnos a mejorar nuestra web cada día.</p><br/><p>' . STORE_NAME . ' - Dpto. Atención al Cliente</p>' );
?>