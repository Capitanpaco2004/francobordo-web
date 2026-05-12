<?php
/*
  $Id: banner_manager.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE', 'Administrador de Banners');

define('TABLE_HEADING_BANNERS', 'Banners');
define('TABLE_HEADING_GROUPS', 'Grupos');
define('TABLE_HEADING_STATISTICS', 'Vistas / Clicks');
define('TABLE_HEADING_STATUS', 'Estado');
define('TABLE_HEADING_ACTION', 'Acci&oacute;n');

define('TEXT_BANNERS_TITLE', 'T&iacute;tulo:');
define('TEXT_BANNERS_URL', 'URL:');
define('TEXT_BANNERS_GROUP', 'Grupo:');
define('TEXT_BANNERS_NEW_GROUP', ', o introduzca un grupo nuevo');
define('TEXT_BANNERS_IMAGE', 'Imagen:');
define('TEXT_BANNERS_IMAGE_LOCAL', ', o introduzca un fichero local');
define('TEXT_BANNERS_IMAGE_TARGET', 'Destino de la Imagen (Grabar en):');
define('TEXT_BANNERS_HTML_TEXT', 'Texto HTML:');
define('TEXT_BANNERS_EXPIRES_ON', 'Caduca el:');
define('TEXT_BANNERS_OR_AT', ', o tras');
define('TEXT_BANNERS_IMPRESSIONS', 'vistas.');
define('TEXT_BANNERS_SCHEDULED_AT', 'Programado el:');
define('TEXT_BANNERS_BANNER_NOTE', '<b>Notas sobre el Banner:</b><ul><li>Use una imagen o texto HTML para el banner - no ambos.</li><li>Texto HTML tiene prioridad sobre una imagen</li></ul>');
define('TEXT_BANNERS_INSERT_NOTE', '<b>Notas sobre la Imagen:</b><ul><li>El directorio donde suba la imagen debe de tener configurados los permisos de escritura necesarios!</li><li>No rellene el campo \'Grabar en\' si no va a subir una imagen al servidor (por ejemplo, cuando use una imagen ya existente en el servidor -fichero local).</li><li>El campo \'Grabar en\' debe de ser un directorio que exista y terminado en una barra (por ejemplo: banners/).</li></ul>');
define('TEXT_BANNERS_EXPIRCY_NOTE', '<b>Notas sobre la Caducidad:</b><ul><li>Solo se debe de rellenar uno de los dos campos</li><li>Si el banner no debe de caducar no rellene ninguno de los campos</li></ul>');
define('TEXT_BANNERS_SCHEDULE_NOTE', '<b>Notas sobre la Programaci&oacute;n:</b><ul><li>Si se configura una fecha de programaci&oacute;n el banner se activara en esa fecha.</li><li>Todos los banners programados se marcan como inactivos hasta que llegue su fecha, cuando se marcan activos.</li></ul>');

define('TEXT_BANNERS_DATE_ADDED', 'A&ntilde;adido el:');
define('TEXT_BANNERS_SCHEDULED_AT_DATE', 'Programado el: <b>%s</b>');
define('TEXT_BANNERS_EXPIRES_AT_DATE', 'Caduca el: <b>%s</b>');
define('TEXT_BANNERS_EXPIRES_AT_IMPRESSIONS', 'Caduca tras: <b>%s</b> vistas');
define('TEXT_BANNERS_STATUS_CHANGE', 'Cambio Estado: %s');

define('TEXT_BANNERS_DATA', 'D<br>A<br>T<br>O<br>S');
define('TEXT_BANNERS_LAST_3_DAYS', 'Ultimos 3 dias');
define('TEXT_BANNERS_BANNER_VIEWS', 'Vistas');
define('TEXT_BANNERS_BANNER_CLICKS', 'Clicks');

define('TEXT_INFO_DELETE_INTRO', 'Seguro que quiere eliminar este banner?');
define('TEXT_INFO_DELETE_IMAGE', 'Borrar imagen');

define('SUCCESS_BANNER_INSERTED', 'Exito: Se ha a&ntilde;adido el banner.');
define('SUCCESS_BANNER_UPDATED', 'Exito: Se ha actualizado el banner.');
define('SUCCESS_BANNER_REMOVED', 'Exito: Se ha eliminado el banner.');
define('SUCCESS_BANNER_STATUS_UPDATED', 'Exito: El estado del banner se ha actualizado.');

define('ERROR_BANNER_TITLE_REQUIRED', 'Error: Es necesario el t&iacute;tulo del banner.');
define('ERROR_BANNER_GROUP_REQUIRED', 'Error: Es necesario el grupo del banner.');
define('ERROR_IMAGE_DIRECTORY_DOES_NOT_EXIST', 'Error: No existe el directorio destino: %s');
define('ERROR_IMAGE_DIRECTORY_NOT_WRITEABLE', 'Error: No se puede escribir en el directorio destino: %s');
define('ERROR_IMAGE_DOES_NOT_EXIST', 'Error: No existe imagen.');
define('ERROR_IMAGE_IS_NOT_WRITEABLE', 'Error: No se puede eliminar la imagen.');
define('ERROR_UNKNOWN_STATUS_FLAG', 'Error: Estado desconocido.');

define('ERROR_GRAPHS_DIRECTORY_DOES_NOT_EXIST', 'Error: No existe el directorio de gr&aacute;ficos. Por favor cree un directorio llamado \'graphs\' dentro de \'images\'.');
define('ERROR_GRAPHS_DIRECTORY_NOT_WRITEABLE', 'Error: No se puede escribir en el directorio de gr&aacute;ficos.');

define('BANNER_MANAGER_TEXT_IMAGE_WEB', 'Imagen Web');
define('BANNER_MANAGER_TEXT_IMAGE_TABLET', 'Imagen Tablet');
define('BANNER_MANAGER_TEXT_IMAGE_MOBILE', 'Imagen Móvil');
define('BANNER_MANAGER_ONLY_RESPONSIVE', '(Solo web responsive)');

// Variables para el nuevo módulo
define('TEXT_BANNERS_LIST', 'Listado de Banners');
define('TEXT_NO_BANNERS', 'No hay banners registrados.');
define('TEXT_DELETE_BANNERS', 'Eliminar Banners');
define('TEXT_BANNER_NOT_FOUND', 'El banner solicitado no existe.');
define('TABLE_HEADING_IMAGE', 'Imagen');

// Textos de ayuda para el formulario
define('TEXT_BANNERS_TITLE_HELP', 'Introduzca el título del banner.');
define('TEXT_BANNERS_URL_HELP', 'URL de destino al hacer clic en el banner (opcional).');
define('TEXT_BANNERS_GROUP_HELP', 'Seleccione el grupo donde se mostrará el banner.');
define('TEXT_BANNERS_NEW_GROUP_HELP', 'O introduzca un nuevo nombre de grupo.');
define('TEXT_BANNERS_SCHEDULED_AT_HELP', 'Fecha en la que el banner se activará automáticamente.');
define('TEXT_BANNERS_EXPIRES_ON_HELP', 'Fecha en la que el banner caducará automáticamente.');

// Constantes adicionales del módulo (si no están definidas globalmente)
if (!defined('TEXT_APPLY_ACTION')) define('TEXT_APPLY_ACTION', 'Aplicar acción');
if (!defined('TEXT_ACTIONS')) define('TEXT_ACTIONS', 'Acciones');
if (!defined('TEXT_SEARCH')) define('TEXT_SEARCH', 'Buscar');
if (!defined('TEXT_SEARCH_PLACEHOLDER')) define('TEXT_SEARCH_PLACEHOLDER', 'Buscar...');
if (!defined('TEXT_CLEAN_FILTER')) define('TEXT_CLEAN_FILTER', 'Limpiar filtro');
if (!defined('TEXT_FILTER_NO_RESULTS')) define('TEXT_FILTER_NO_RESULTS', 'No se encontraron resultados con los filtros aplicados.');
if (!defined('TEXT_BACK')) define('TEXT_BACK', 'Volver');
if (!defined('TEXT_SAVE')) define('TEXT_SAVE', 'Guardar');
if (!defined('TEXT_ADD')) define('TEXT_ADD', 'Añadir');
if (!defined('TEXT_EDIT')) define('TEXT_EDIT', 'Editar');
if (!defined('IMAGE_EDIT')) define('IMAGE_EDIT', 'Editar');
if (!defined('IMAGE_DELETE')) define('IMAGE_DELETE', 'Eliminar');

?>
