Módulo marcas: Instalación
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