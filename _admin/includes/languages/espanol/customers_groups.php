<?php
/*
   for Separate Pricing Per Customer v4.2.1 2008/04/12
*/

define('HEADING_TITLE', 'Grupos de Clientes');
define('HEADING_TITLE_SEARCH', 'Buscar:');

define('TABLE_HEADING_NAME', 'Nombre');
define('TABLE_HEADING_ACTION', 'Acción');

define('ENTRY_GROUPS_NAME', 'Nombre&#160;grupo:');
define('ENTRY_GROUP_NAME_MAX_LENGTH', '&#160;&#160;Tamaño máximo: 32 caracteres');
define('ENTRY_GROUP_SHOW_TAX', 'Enseñar&#160;precios&#160;con/sin&#160;impuesto:');
define('ENTRY_GROUP_SHOW_TAX_EXPLAIN_1', '&#160;&#160;Esta configuración solo funciona cuando muestras precios con impuestos');
define('ENTRY_GROUP_SHOW_TAX_EXPLAIN_2', 'Si está activado en tu configuración \'Exempto de impuesto\' a \'No\'.');
define('ENTRY_GROUP_SHOW_TAX_YES', 'Enseñar precios con impuesto');
define('ENTRY_GROUP_SHOW_TAX_NO', 'Enseñar precios sin impuesto');

define('ENTRY_GROUP_TAX_EXEMPT', 'Exempto de impuesto:');
define('ENTRY_GROUP_TAX_EXEMPT_YES', 'Si');
define('ENTRY_GROUP_TAX_EXEMPT_NO', 'No');
define('ENTRY_GROUP_TEXT_SORT', 'Ordenar por');
define('ENTRY_GROUP_TEXT_FROM_TOP', 'Desde arriba');
define('ENTRY_GROUP_DELETE_CONFIRM_NOT_ALLOWED', 'No tienes permiso para eliminar este grupo:');

define('ENTRY_GROUP_PAYMENT_SET', 'Configurar modulos de pago para el Grupo de Clientes');
define('ENTRY_GROUP_PAYMENT_DEFAULT', 'Usar configuraciones desde la Configuració general');
define('ENTRY_PAYMENT_SET_EXPLAIN', 'Si escoges <b><i>Configurar modulos de pago para el Grupo de Clientes</i></b> la configuracion por defecto estará todavia en uso.');

define('ENTRY_GROUP_SHIPPING_SET', 'Configurar modulos de envío para el Grupo de Clientes');
define('ENTRY_GROUP_SHIPPING_DEFAULT', 'Usar configuraciones desde la Configuració general');
define('ENTRY_SHIPPING_SET_EXPLAIN', 'Si escoges <b><i>Configurar modulos de envío para el Grupo de Clientes</i></b> la configuracion por defecto estará todavia en uso.');
define('ENTRY_SHIPPING_SET_EXPLAIN_2', 'Esta configuración solo funcionará cuando esté marcado la opción de mostrar precios sin impuestos.');

define('ENTRY_GROUP_ORDER_TOTAL_SET', 'Establecer módulos de total de pedidos para el grupo de clientes');
define('ENTRY_GROUP_ORDER_TOTAL_DEFAULT', 'Usar los parámetros de configuración');
define('ENTRY_ORDER_TOTAL_SET_EXPLAIN', 'Si escoges <b><i>Establecer módulos de total de pedidos para el grupo de clientes</i></b> la configuracion por defecto estará todavia en uso.');

define('TEXT_DELETE_INTRO', '¿Estás seguro de querer borrar este Grupo de Clientes?');
define('TEXT_DISPLAY_NUMBER_OF_CUSTOMERS_GROUPS', 'Enseñando <b>%d</b> a <b>%d</b> (de <b>%d</b> Grupos de Clientes)');
define('TEXT_INFO_HEADING_DELETE_GROUP', 'Borrar Grupo');

define('ERROR_CUSTOMERS_GROUP_NAME', 'Por favor, pon un nombre al Grupo');

define('HEADING_TITLE_GROUP_TAX_RATES_EXEMPT', 'Grupo exento de impuestos específicos');
define('ENTRY_GROUP_TAX_RATES_EXEMPT', 'Excepto impuestos del grupo de clientes');
define('ENTRY_GROUP_TAX_RATES_DEFAULT', 'Usar los parámetros de configuración (por zonas)');
define('ENTRY_TAX_RATES_EXEMPT_EXPLAIN', 'Si seleccionas <b><i>exento de impuestos del grupo de clientes/i></b> pero no marque ninguna de las casillas, la configuración predeterminada (basada en zonas) se seguirá utilizando.<br />Si ha configurado este grupo en Exempto de impuesto como "Si", ninguna de estas configuraciones tendrá ningún efecto.');
?>
