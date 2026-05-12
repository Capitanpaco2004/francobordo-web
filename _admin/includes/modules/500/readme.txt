Módulo 500: Instalación
-------

_Cree su página personalizada 500 como cualquier otra página más..._

###Instalación

####1) Añadir a /.htaccess de la raiz
```php
# Error 500
RewriteRule ^error500.html$ includes/modules/500/index.php [L,QSA,NC]
```

####2) Añadir a /_admin/includes/boxes/promocion.php
```php
'<a href="' . tep_href_link('500.php') . '" class="menuBoxContentLink2">Errores 500</a>' .
```

####3) _admin/includes/application_top.php
Linea `~173`: comprobar que existe la llamada a la clase tools y date si no añadirla:
```php
  // Tools
  include( '../includes/classes/tools.php' );
```