Módulo Security: Instalación
-------

_Evite los hacks hacia su tienda, las brechas de seguridad, la fuerza bruta, escaneos de malware entre otros..._

###Instalación

####1) _admin/includes/application_top.php
Linea `~173`: añadir despues de `require(DIR_WS_CLASSES . 'upload.php');`:
```php
  // Security
  include( '../includes/modules/security/includes/classes/security.php' );
  $dxSecurity = new denox\security();
```

Linea `~173`: comprobar que existe la llamada a la clase tools y date si no añadirla antes de llamar a security:
```php
  // Tools
  include( '../includes/classes/tools.php' );
  include( '../includes/classes/date.php' );
```

####2) _admin/login.php
Linea `~23`: añadir despues de terminar este if:
```php
	if($_GET['login']!='fail')
	{
		tep_session_register('login_id');
		tep_session_register('login_groups_id');
		tep_session_register('login_first_name');
		:
		:
		:
		:
	}
	else // Seguridad
		$dxSecurity->loginAdminFailed();
```

Linea `~7`: añadir despues de la sentencia IF:
```php
	if( isset($_GET['action']) && ($_GET['action'] == 'process') )
		// Seguridad
		$dxSecurity->loginAdminPeriodLockouts();
```

Linea `~81`: Cambiar:
```php
	<?php echo $error; ?>
	------------------------------------- por -------------------------------------
	<?php echo $messageStack->output(); ?>
```

Linea `~27`: añadir despues de la sentencia IF:
```php
	if($_GET['login']!='fail')
	{
		// Seguridad
		$dxSecurity->loginSuccess();
```

####3) /includes/application_top.php frontend
Linea `~431`: comprobar que existe la llamada a la clase tools si no añadirla antes de la sentencia IF:
```php
  // Tools
  include( '../includes/classes/tools.php' );
  include( '../includes/classes/date.php' );

  // Acciones Shopping cart
  if( isset( $_GET['action'] ) )
  {
```

####4) Añadir a /.htaccess de la raiz
```php
# Seguridad
RewriteRule ^security.php$ includes/modules/security/index.php [L,QSA,NC]
```