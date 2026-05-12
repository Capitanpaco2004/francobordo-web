<?php

define('ADMIN_MEMBERS_HEADING_TITLE_INDEX', 'Miembros y grupos de aministración');
define('ADMIN_MEMBERS_HEADING_TITLE_MEMBERS', 'Miembros de aministración');
define('ADMIN_MEMBERS_HEADING_TITLE_GROUPS', 'Grupos de aministración');
define('ADMIN_MEMBERS_HEADING_SUBTITLE_INDEX', 'Listado de los miembros y grupos de aministración');
define('ADMIN_MEMBERS_HEADING_SUBTITLE_MEMBERS_LIST', 'Lista de miembros de administración');
define('ADMIN_MEMBERS_HEADING_SUBTITLE_GROUPS_LIST', 'Lista de grupos de administración');
define('ADMIN_MEMBERS_TEXT_APPLY_ACTION', 'Aplicar acción');
define('ADMIN_MEMBERS_TABLE_ACTIONS', 'Acciones');
define('ADMIN_MEMBERS_TEXT_DELETES_CONFIRM', '¿Realmente deseas eliminar los registros?');
define('ADMIN_MEMBERS_TEXT_DELETE_ERROR', 'Para realizar alguna de estas operaciones necesitas seleccionar algún registro');
define('ADMIN_MEMBERS_TEXT_DELETES', 'Eliminar registros');
define('ADMIN_MEMBERS_NO_RECORDS', 'No existe ningun registro para mostrar.');
define('ADMIN_MEMBERS_TABLE_HEADING_FULLNAME', 'Nombre completo');
define('ADMIN_MEMBERS_TABLE_HEADING_FIRSTNAME', 'Nombre');
define('ADMIN_MEMBERS_TABLE_HEADING_FIRSTNAME_HELP', 'Nombre del miembro administrador.');
define('ADMIN_MEMBERS_TABLE_HEADING_LASTNAME', 'Apellido');
define('ADMIN_MEMBERS_TABLE_HEADING_LASTNAME_HELP', 'Apellido del miembro administrador.');
define('ADMIN_MEMBERS_TABLE_HEADING_EMAIL', 'Email');
define('ADMIN_MEMBERS_TABLE_HEADING_EMAIL_HELP', 'Email del miembro administrador.');
define('ADMIN_MEMBERS_TABLE_HEADING_PASSWORD', 'Password');
define('ADMIN_MEMBERS_TABLE_HEADING_CONFIRM', 'Confirma Password');
define('ADMIN_MEMBERS_TABLE_HEADING_GROUPS', 'Nivel de grupo');
define('ADMIN_MEMBERS_TABLE_HEADING_GROUP', 'Nombre del grupo');
define('ADMIN_MEMBERS_TABLE_HEADING_GROUP_HELP', 'Elige el nombre del grupo de administración.');
define('ADMIN_MEMBERS_TABLE_HEADING_GROUPS_HELP', 'Seleccione el nivel del grupo de administrador.');
define('ADMIN_MEMBERS_TABLE_HEADING_CREATED', 'Cuenta Creada');
define('ADMIN_MEMBERS_TABLE_HEADING_MODIFIED', 'Cuenta Modificada');
define('ADMIN_MEMBERS_TABLE_HEADING_LOGDATE', 'Ultimo Acceso');
define('ADMIN_MEMBERS_TABLE_HEADING_LOG_NUM', 'Número de log');
define('ADMIN_MEMBERS_TEXT_INFO_HEADING_MEMBERS', 'Nuevo miembro');
define('ADMIN_MEMBERS_TEXT_INFO_HEADING_GROUPS', 'Nuevo grupo');
define('ADMIN_MEMBERS_TEXT_EDIT', 'Editar registro');
define('ADMIN_MEMBERS_TEXT_PERMISSIONS', 'Permisos a ficheros');
define('ADMIN_MEMBERS_TEXT_DELETE', 'Eliminar registro');
define('ADMIN_MEMBERS_TEXT_ADD', 'Añadir');
define('ADMIN_MEMBERS_TEXT_EDITED', 'Editar');
define('ADMIN_MEMBERS_TEXT_SEARCH', 'Buscar');
define('ADMIN_MEMBERS_TEXT_DELETE_ERROR', 'Para realizar alguna de estas operaciones necesitas seleccionar algún registro');
define('ADMIN_MEMBERS_TEXT_DELETE_SINGLE', 'Eliminar');
define('ADMIN_MEMBERS_TITLE_ADD_EDIT_MEMBER', 'administrador');
define('ADMIN_MEMBERS_TITLE_ADD_EDIT_GROUP', 'grupo');
define('ADMIN_MEMBERS_TEXT_CONFIGURATION', 'Configuración');
define('ADMIN_MEMBERS_TEXT_INFO_HEADING_MEMBERS_LIST', 'Lista de miembros');
define('ADMIN_MEMBERS_TEXT_INFO_HEADING_MEMBER_CHANGE_PASSWORD', 'Cambia la contraseña de la cuenta de administración.');
define('ADMIN_MEMBERS_TEXT_INFO_HELP_MEMBERS_LIST', 'Listado de todos los miembros de administración, como la posibilidad de añadir nuevos, editar o eliminar existentes.');
define('ADMIN_MEMBERS_TEXT_INFO_HEADING_GROUPS_LIST', 'Lista de grupos');
define('ADMIN_MEMBERS_TEXT_INFO_HELP_GROUPS_LIST', 'Listado de todos los grupos de administración, como la posibilidad de añadir nuevos, editar o eliminar existentes. Además de modificar los permisos a ficheros.');
define('ADMIN_MEMBERS_BUTTON_ENTER_SECTION', 'Entrar en esta sección');
define('ADMIN_MEMBERS_MEMBER_DELETE_SUCCESS', 'Los registros se han eliminado correctamente');
define('ADMIN_MEMBERS_MEMBER_EDIT_SUCCESS', 'El miembro de administración se ha editado correctamente');
define('ADMIN_MEMBERS_MEMBER_ADD_SUCCESS', 'El miembro de administración se añadido correctamente');
define('ADMIN_MEMBERS_MEMBER_NO_EXISTS', 'El registro que intentas editar no existe');
define('ADMIN_MEMBERS_GROUP_ALREADY_EXISTS', 'Ya existe un grupo con el mismo nombre');
define('ADMIN_MEMBERS_GROUP_EDIT_SUCCESS', 'El grupo de administración se ha editado correctamente');
define('ADMIN_MEMBERS_GROUP_ADD_SUCCESS', 'El grupo de administración se añadido correctamente');

define('ADMIN_MEMBERS_TEXT_ERROR_INPUTS', 'Se necesitas especificar todos los campos (Nombre, apellidos, y email).');
define('ADMIN_MEMBERS_TEXT_ERROR_EMAIL_IN_USE', 'Ya hay otro miembro con el mismo email.');
define('ADMIN_MEMBERS_TEXT_ERROR_GROUP_INPUT', 'Se necesitas especificar el nombre del grupo.');

define('ADMIN_MEMBERS_TABLE_HEADING_PASSWORD', 'Cambio de contraseña');
define('ADMIN_MEMBERS_TEXT_CHANGE_PASSWORD', 'Cambiar contraseña');
define('ADMIN_MEMBERS_TEXT_INFO_PASSWORD', 'Contraseña:');
define('ADMIN_MEMBERS_TEXT_INFO_PASSWORD_CONFIRM', 'Repetir contraseña:');
define('ADMIN_MEMBERS_TEXT_SUCCESS_PASSWORD', 'Has cambiado la contraseña correctamente a esta cuenta.');
define('ADMIN_MEMBERS_TEXT_ERROR_PASSWORD_CONFIRM', 'No has introducido la misma contraseña en repetir contraseña.');
define('ADMIN_MEMBERS_TEXT_ERROR_PASSWORD_SAME', 'Estás introduciendo la misma contraseña que ya tiene configurada la cuenta.');
define('ADMIN_MEMBERS_TEXT_ERROR_PASSWORD_REGEX', 'La contraseña debe tener al menos un carácter minúscula, un carácter mayúscula, un dígito numérico y un signo especial @#-_$%^&+=§!?');

define('ADMIN_EMAIL_SUBJECT_PASSWORD', 'Cambio de contraseña en %s para %s %s');
define('ADMIN_EMAIL_TEXT_PASSWORD', 'Hola %s,' . "\n\n" . 'Esto es un e-mail automático para informarte de que tu contraseña ha sido modificada. Si esto no era tu intención, contacta con el administrador inmediatamente para ver quien ha realizado los cambios oportunos.' . "\n\n" . 'Web : %s' . "\n" . 'Usuario: %s' . "\n" . 'Contraseña: %s' . "\n\n" . '¡Gracias!' . "\n" . '%s' . "\n\n" . 'Esto es un mail automático, por favor no respondas!');

define('ADMIN_MEMBERS_CATEGORIES_AVAILABLES', 'Categorias en las que puede actuar');
define('ADMIN_MEMBERS_RIGHTS_AVAILABLES', 'Acceso a categorías y productos');
define('ADMIN_MEMBERS_TEXT_RIGHTS_CNEW','Crear Categoría');
define('ADMIN_MEMBERS_TEXT_RIGHTS_CEDIT','Editar Categoría');
define('ADMIN_MEMBERS_TEXT_RIGHTS_CMOVE','Mover Categoría');
define('ADMIN_MEMBERS_TEXT_RIGHTS_CDELETE','Borrar categoría');
define('ADMIN_MEMBERS_TEXT_RIGHTS_PNEW','Crear Producto');
define('ADMIN_MEMBERS_TEXT_RIGHTS_PEDIT','Editar Producto');
define('ADMIN_MEMBERS_TEXT_RIGHTS_PMOVE','Mover Producto');
define('ADMIN_MEMBERS_TEXT_RIGHTS_PCOPY','Copiar Producto');
define('ADMIN_MEMBERS_TEXT_RIGHTS_PDELETE','Borrar Producto');

define('ADMIN_MEMBERS_TABLE_HEADING_GROUPS_DEFINE', 'Selección de cajas y Ficheros');

define('ADMIN_EMAIL_SUBJECT', 'Nuevo Miembro Administrador en %s: %s %s');
define('ADMIN_EMAIL_EDIT_SUBJECT', 'Cambios en la página %s para el administrador %s %s');
define('ADMIN_EMAIL_TEXT', 'Hola %s,<br /><br />Puedes entrar en el Panel de administración con la siguiente contraseña. Te recomendamos que nada más acceder al panel de administración por primera vez, modifiques tu contraseña por una más segura que la generada aleatoriamente y que recuerdes para tus futuros ingresos.<br /><br />URL Administración: %s<br />Usuario: %s<br />Contraseña: %s<br /><br />¡Muchas gracias!<br />%s<br /><br />' . "$s<br /><br />" . 'Nota: Le recordamos que esto es un e-mail automático que genera el sistema para enviar la contraseña de acceso de un nuevo usuario administrador.');
define('ADMIN_EMAIL_EDIT_TEXT', 'Hola %s,<br /><br />Te informamos que los datos de tu cuenta de Administrador han sido modificados (Datos personales, Contraseñas o Nivel de Acceso).<br /><br />URL Administración: %s<br />Usuario: %s<br />Contraseña: %s<br /><br />¡Muchas gracias!!<br />%s<br /><br />Nota: Le recordamos que esto es un e-mail automático que genera el sistema para enviar la contraseña de acceso de un nuevo usuario administrador.');

define("ADMIN_MEMBERS_SUBMODULE_DELETE","El submodulo se ha borrado correctamente");

define("ADMIN_MEMBERS_SUBMODULE_HEADING_TITLE_GROUPS","Grupos de aministacion: submodulos");
define("ADMIN_MEMBERS_SUBMODULE_TEXT_EDITED","Editar submodulos");
define("ADMIN_MEMBERS_SUBMODULE_NO_SUBMODULES_EXIST","No existen submodulos validos para este usuario.");
define("ADMIN_MEMBERS_SUBMODULE_TITLE_ADD_EDIT_MEMBER","submodulos");
define("ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_ARG_HELP","Indica el argumento GET que tendra que comprobar el modulo para los permisos. Tiene que tener el siguiente formato: key=value. Por ejemplo action=store");
define("ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_ARG_NAME","Argumento del módulo");
define("ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_ADMIN_FILE_HELP","Indica el fichero que contiene al submodulo");
define("ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_ADMIN_FILE_NAME","Fichero");
define("ADMIN_MEMBERS_SUBMODULE_EDIT_SUCCESS", 'El submodulo de administración se ha editado correctamente');
define("ADMIN_MEMBERS_SUBMODULE_ADD_SUCCESS", 'El submodulo de administración se añadido correctamente');
define("ADMIN_MEMBERS_SUBMODULE_LIST_SUBTITLE","Lista de submodulos");
define("ADMIN_MEMBERS_SUBMODULE_LIST_NEW","Añadir submodulos");
define("ADMIN_MEMBERS_SUBMODULE_NO_RECORDS","No se han encontrado submodulos");
define("ADMIN_MEMBERS_SUBMODULE_SUBTITLE_LIST","Lista de submodulos");
define("ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_SUBMODULE_NAME","Nombre del submodulo");
define("ADMIN_MEMBERS_SUBMODULE_TABLE_HEADING_FILE_NAME","Fichero");
define("ADMIN_MEMBERS_SUBMODULE_DELETE_SUCCESS","Se han eliminado los submodulos correctamente");
?>
