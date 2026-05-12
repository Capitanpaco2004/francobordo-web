Módulo productos relacionados: Instalación
-------

###Instalación

####1) Realizar diff con _admin/includes/functions/database.php

####2) Realizar diff con _admin/includes/classes/message_stack.php

####3) Instalar el theme solenopsis:
`3.1: ` Copiar el theme solenopsis en _admin/theme/

`3.2: ` Añadir _admin/.htaccess
```php
  RewriteRule style_base.css$ theme/solenopsis/css/style.php [L,QSA,NC]
```

####4) _admin/includes/application_top.php
Linea `~173`: comprobar que existe la llamada a la clase tools y date si no añadirla:
```php
  // Tools
  include( '../includes/classes/tools.php' );
  include( '../includes/classes/date.php' );
```

####5) _admin/includes/boxes/catalog.php
Linea `~13`: Añadir al menu incio:
```php
	(defined( 'RELATED_PRODUCTS_ACTIVE' ) && RELATED_PRODUCTS_ACTIVE == 'true' ? '<a href="' . tep_href_link('related_products.php', '', 'NONSSL') . '" class="menuBoxContentLink2">Productos relacionados</a>' : '') 
```
		