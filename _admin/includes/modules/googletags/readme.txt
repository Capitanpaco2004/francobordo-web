Instrucciones para la instalación del Módulo de Google Tags
-------------------------------

_Este módulo nos permitirá integrar todas las etiquetas de las herramientas de Google Analytics y Adwords desde la configuración de nuestra tienda para hacer el seguimiento de visitas y de las campañas de Adwords..._

_Toda su programación se ha basado en la documentación oficial de Google disponible en el momento: https://developers.google.com/analytics/devguides/collection/gtagjs/?hl=es-419_

###Instalación

####1) /_admin/includes/boxes/promotions.php
Añadimos la siguiente línea para tener accesible el módulo desde el menú de Promociones (si es una versión antigua de base tendrás que adaptarlo)
```php
<a href="<?php echo tep_href_link('googletags.php'); ?>"> <i class="bullet"></i> Google Tags</a>
```

####2) /_admin/admin_files.php
Entramos en esta sección para dar permisos al archivo googletags.php en la carpeta de Promociones para que sea accesible por los administradores

####3) /_admin/includes/application_top.php
Linea `~173`: comprobar que existe la llamada a la clase tools y date si no añadirla:
```php
// Tools
include( '../includes/classes/tools.php' );
```

####4) /includes/classes/tools.php
Comprobamos si existe la función para comprobar si opCache está instalado, si no añadirla:
```php
/**
* Comprueba si la extensión de opCache está instalada en el servidor
*/
public static function checkOpcache()
{
	if( function_exists('opcache_get_status') )
	{
		$aInfo = opcache_get_status();

		if( $aInfo['opcache_enabled'] === true )
			return true;
	}

	return false;
}
```

####5) /includes/application_top.php
Buscar `canonDigital` o `rgpd` o `whislist`: donde se añade las funciones a las clases, añadir para llamar a la clase del módulo
```php
// Google Tags - Iniciamos el Módulo para el Seguimiento de Google
include( 'includes/modules/googletags/includes/classes/googleTags.php' );
$dxGoogleTags = new denox\googleTags();
```

####5) /theme/web/functions/functions.php
Función `getHeader()`: añadir en las variables globales:
```
global $dxGoogleTags;
```

Función `getHeader()`: añadir debajo de la etiqueta `<base href=...`:
```php
$dxGoogleTags->AnalyticsTrackingGlobal();
$dxGoogleTags->SearchConsoleVerification();
```

####6) /product_info.php
Para hacer el seguimiento de las vistas de producto, se debe de meter el Evento view_item justo por encima de la carga del /theme/
```php
//GoogleTags: Registramos la vista del producto en Google Analytics
$dxGoogleTags->AnalyticsEventViewProduct();
```

###Instrucciones Tiendas Checkout Nuevo (Modulo)

####1) /modules/checkout/success.php
Añadimos en la function `index()` la etiqueta global:
```php
	global $dxGoogleTags;
```

####2) /modules/checkout/template/success.php
Sustituimos la siguiente línea:
```php
<?php include_once DIR_WS_MODULES . 'analytics/analytics_commerce_tracking.php';?>
```

por esta
```php
	// GoogleTags: Cargamos el código de Google Ads para el tracking de Conversion
	$dxGoogleTags->AdsConversionTracking();

	//GoogleTags: Cargo el Código de Analytics para Comercio Electronico Mejorado
	$dxGoogleTags->AnalyticsEventPurchased();
 ```


###Instrucciones Tiendas Checkout Antigüo

####1) /checkout_success.php
Añadimos la carga del código de Seguimiento de Conversiones de Google Ads después del include de `header.php`:
```php
// GoogleTags: Cargamos el código de Google Ads para el tracking de Conversion
$dxGoogleTags->AdsConversionTracking();
```



Además para el seguimiento del Comercio Electrónico Mejorado de Analytics debemos de añadir lo siguiente para cargar el evento de conversión:
```php
//GoogleTags: Cargo el Código de Analytics para Comercio Electronico Mejorado
$dxGoogleTags->AnalyticsEventPurchased();
```


#### POR AHORA NO HACER MÁS DE AQUI HACIA ABAJO
Además, para recopilar las Dimensiones Personalizadas para realizar Remarketing Dinámico desde Analytics (debes de configurar las dimensiones personalizadas y la audiencia personalizada para comercio minorista antes). Recuerda que es importante que tengas un feed de datos de Shopping configurado previamente y validado para poder realiar las campañas.

Mas info para completar una guia: https://www.digishuffle.com/blogs/dynamic-remarketing/
```php
//GoogleTags: Etiqueta para Remarketing Dinamico (Ads + Shopping Merchant)
$dxGoogleTags->RemarketingDynamics();
```