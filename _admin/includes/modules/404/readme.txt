Módulo 404: Instalación
-------

_Cree su página personalizada 404 como cualquier otra página más..._

###Instalación

####1) Añadir a /.htaccess de la raiz
```php
# Error 404
RewriteRule ^error404.html$ includes/modules/404/index.php [L,QSA,NC]
```

####2) Añadir a /_admin/includes/boxes/promocion.php
```php
'<a href="' . tep_href_link('404.php') . '" class="menuBoxContentLink2">Errores 404</a>' .
```

####3) _admin/includes/application_top.php
Linea `~173`: comprobar que existe la llamada a la clase tools y date si no añadirla:
```php
  // Tools
  include( '../includes/classes/tools.php' );
```