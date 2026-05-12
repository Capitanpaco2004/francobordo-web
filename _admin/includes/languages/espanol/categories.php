<?php
/*
  $Id: categories.php 1739 2007-12-20 00:52:16Z hpdl $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

define('HEADING_TITLE', 'Categorias / Productos');
if (!defined('HEADING_TITLE_SEARCH')) define('HEADING_TITLE_SEARCH', 'Buscar:');
define('HEADING_TITLE_GOTO', 'Ir A:');

define('TABLE_HEADING_ID', 'ID');
define('TABLE_HEADING_CATEGORIES_PRODUCTS', 'Categorias / Productos');
define('TABLE_HEADING_ACTION', 'Acci&oacute;n');
define('TABLE_HEADING_STATUS', 'Estado');

define('TEXT_NEW_PRODUCT', 'Nuevo Producto en &quot;%s&quot;');
if (!defined('TEXT_CATEGORIES')) define('TEXT_CATEGORIES', 'Categorias:');
define('TEXT_SUBCATEGORIES', 'Subcategorias:');
define('TEXT_PRODUCTS', 'Productos:');
define('TEXT_PRODUCTS_PRICE_INFO', 'Precio:');
define('TEXT_PRODUCTS_TAX_CLASS', 'Tipo Impuesto:');
define('TEXT_PRODUCTS_AVERAGE_RATING', 'Evaluaci&oacute;n Media:');
define('TEXT_PRODUCTS_QUANTITY_INFO', 'Cantidad:');
define('TEXT_DATE_ADDED', 'A&ntilde;adido el:');
define('TEXT_DATE_AVAILABLE', 'Fecha Disponibilidad:');
define('TEXT_LAST_MODIFIED', 'Modificado el:');
if (!defined('TEXT_IMAGE_NONEXISTENT')) define('TEXT_IMAGE_NONEXISTENT', 'NO EXISTE IMAGEN');
define('TEXT_NO_CHILD_CATEGORIES_OR_PRODUCTS', 'Inserte una nueva categoria o producto.');
define('TEXT_PRODUCT_MORE_INFORMATION', 'Si quiere mas informaci&oacute;n, visite la <a href="http://%s" target="blank"><u>p&aacute;gina</u></a> de este producto.');
define('TEXT_PRODUCT_DATE_ADDED', 'Este producto fue a&ntilde;adido el %s.');
define('TEXT_PRODUCT_DATE_AVAILABLE', 'Este producto estar&aacute; disponible el %s.');

define('TEXT_EDIT_INTRO', 'Haga los cambios necesarios');
define('TEXT_EDIT_CATEGORIES_ID', 'ID Categoria:');
define('TEXT_EDIT_CATEGORIES_NAME', 'Nombre Categoria:');
define('TEXT_EDIT_CATEGORIES_IMAGE', 'Imagen Categoria:');
define('TEXT_EDIT_CATEGORIES_IMAGE_SMALL', 'Imagen Categoria pequeña:');
define('TEXT_EDIT_SORT_ORDER', 'Orden:');

define('TEXT_INFO_COPY_TO_INTRO', 'Elija la categoria hacia donde quiera copiar este producto');
define('TEXT_INFO_CURRENT_CATEGORIES', 'Categorias:');

define('TEXT_INFO_HEADING_NEW_CATEGORY', 'Nueva Categoria');
define('TEXT_INFO_HEADING_EDIT_CATEGORY', 'Editar Categoria');
define('TEXT_INFO_HEADING_DELETE_CATEGORY', 'Eliminar Categoria');
define('TEXT_INFO_HEADING_MOVE_CATEGORY', 'Mover Categoria');
define('TEXT_INFO_HEADING_DELETE_PRODUCT', 'Eliminar Producto');
define('TEXT_INFO_HEADING_MOVE_PRODUCT', 'Mover Producto');
define('TEXT_INFO_HEADING_COPY_TO', 'Copiar A');

define('TEXT_DELETE_CATEGORY_INTRO', 'Seguro que desea eliminar esta categoria?');
define('TEXT_DELETE_PRODUCT_INTRO', 'Es usted seguro usted desea suprimir permanentemente este producto?');

define('TEXT_DELETE_WARNING_CHILDS', '<b>ADVERTENCIA:</b> Hay %s categorias que pertenecen a esta categoria!');
define('TEXT_DELETE_WARNING_PRODUCTS', '<b>ADVERTENCIA:</b> Hay %s productos en esta categoria!');

define('TEXT_MOVE_PRODUCTS_INTRO', 'Elija la categoria hacia donde quiera mover <b>%s</b>');
define('TEXT_MOVE_CATEGORIES_INTRO', 'Elija la categoria hacia donde quiera mover <b>%s</b>');
define('TEXT_MOVE', 'Mover <b>%s</b> a:');

define('TEXT_NEW_CATEGORY_INTRO', 'Rellene los siguientes datos de la nueva categoria');
define('TEXT_CATEGORIES_NAME', 'Nombre Categoria:');
define('TEXT_CATEGORIES_IMAGE', 'Imagen Categoria:');
define('TEXT_CATEGORIES_IMAGE_SMALL', 'Imagen Categoria pequeña:');
define('TEXT_SORT_ORDER', 'Orden:');

define('TEXT_PRODUCTS_STATUS', 'Estado de los Productos:');
define('TEXT_PRODUCTS_DATE_AVAILABLE', 'Fecha Disponibilidad:');
define('TEXT_PRODUCT_AVAILABLE', 'Activado');
define('TEXT_PRODUCT_NOT_AVAILABLE', 'Desactivado');
define('TEXT_PRODUCTS_MANUFACTURER', 'Marca/Fabricante:');
define('TEXT_PRODUCTS_NAME', 'Nombre del Producto:');
define('TEXT_PRODUCTS_DESCRIPTION', 'Descripci&oacute;n del producto:');
define('TEXT_PRODUCTS_QUANTITY', 'Cantidad:');
define('TEXT_PRODUCTS_MODEL', 'Modelo:');
define('TEXT_PRODUCTS_IMAGE', 'Imagen:');
define('TEXT_PRODUCTS_URL', 'URL del Producto:');
define('TEXT_PRODUCTS_URL_WITHOUT_HTTP', '<small>(sin http://)</small>');
define('TEXT_PRODUCTS_PRICE_NET', 'Precio (Neto):');
define('TEXT_PRODUCTS_PRICE_GROSS', 'Precio (Bruto):');
define('TEXT_PRODUCTS_WEIGHT', 'Peso:');
define('TEXT_CUSTOMERS_GROUPS_NOTE', 'Si el campo de precio de los grupos de distribución se dejara vacío, no se añadirá ningún precio para el grupo de cliente.<br>En este caso se le reflejará el precio de los clientes finales.<br />
Si se rellena el campo, pero la casilla está sin marcar, el precio no se insertará.<br />
Si el precio está ya insertado, pero se desmarca la casilla, el precio será eliminado.<br>
Los precios para los grupos de cliente son SIN IVA<br>
Es importante seleccionar el tipo de impuesto de cada producto, para que su desglose sea el correcto.');

define('EMPTY_CATEGORY', 'Categoria Vacia');

define('TEXT_HOW_TO_COPY', 'Metodo de Copia:');
define('TEXT_COPY_AS_LINK', 'Enlazar el producto');
define('TEXT_COPY_AS_DUPLICATE', 'Duplicar el producto');

define( 'ERROR_NOMBRE_PRODUCTO', '<br/ >*Debe introducir un nombre en español para el producto.' );
define( 'ERROR_NOMBRE_PRODUCTO_MULTI', '<br/ >*Debe introducir un nombre en todos sus idiomas para el producto.' );
define('ERROR_CANNOT_LINK_TO_SAME_CATEGORY', 'Error: No se pueden enlazar productos en la misma categoria.');
define('ERROR_CATALOG_IMAGE_DIRECTORY_NOT_WRITEABLE', 'Error: No se puede escribir en el directorio de imagenes del cat&aacute;logo: ' . DIR_FS_CATALOG_IMAGES);
define('ERROR_CATALOG_IMAGE_DIRECTORY_DOES_NOT_EXIST', 'Error: No existe el directorio de imagenes del cat&aacute;logo: ' . DIR_FS_CATALOG_IMAGES);
define('ERROR_CANNOT_MOVE_CATEGORY_TO_PARENT', 'Error: Category cannot be moved into child category.');
define('TEXT_PRODUCTS_PRICE', 'Precio por unidad para nivel');
define('TEXT_PRODUCTS_QTY_BLOCKS', 'Unidades en el paquete:');
define('TEXT_PRODUCTS_QTY_BLOCKS_INFO', '(solo puede pedir en paquetes de X unidades)');
define('TEXT_HIDE_PRODUCTS_FROM_GROUP', 'Seleccione el grupo al que deseas ocultar este producto:');
define('TEXT_HIDDEN_FROM_GROUPS', 'Ocultar para los grupos: ');
define('TEXT_GROUPS_NONE', 'none');
define('TEXT_HIDE_CATEGORIES_FROM_GROUPS', 'Ocultar categoría para los grupos:');
define('TABLE_HEADING_HIDE_CATEGORIES', 'Ocultar');
// 0: Icons for all groups for which the category or product is hidden, mouse-over the icons to see what group
// 1: Only one icon and only if the category or product is hidden for a group, mouse-over the icon to what groups
define('LAYOUT_HIDE_FROM', '0');
// EOF Hide product from groups
define('TEXT_SHIPPING_METHODS','Métodos de Envío');
define('TEXT_PAYMENT_METHODS','Métodos de Pago');
define('TEXT_PRODUCTS_REORDER', 'Reorder Level:');
define('TEXT_PRODUCTS_REORDER_TO', 'Reorder Qty.:');
// BOF QPBPP for SPPC
if (!defined('TEXT_PRODUCTS_QTY_BLOCKS')) define('TEXT_PRODUCTS_QTY_BLOCKS', 'Quantity Blocks:');
define('TEXT_PRODUCTS_QTY_BLOCKS_HELP', 'Solo se podrá comprar en grupos de X cantidad, por defecto 1');
if (!defined('TEXT_PRODUCTS_PRICE')) define('TEXT_PRODUCTS_PRICE', 'Price level');
define('TEXT_PRODUCTS_QTY', 'Cantidad para descuento:');
define('TEXT_PRODUCTS_DELETE', 'Eliminar');
define('TEXT_ENTER_QUANTITY', 'Cantidad');
define('TEXT_PRICE_PER_PIECE', 'Price&nbsp;for&nbsp;each');
define('TEXT_SAVINGS', 'Your savings');
define('TEXT_DISCOUNT_CATEGORY', 'Categoría de Descuento:');
define('ERROR_UPDATE_INSERT_DISCOUNT_CATEGORY', 'Something went wrong when updating or inserting into the table discount_categories');
define('ERROR_ALL_CUSTOMER_GROUPS_DELETED', 'All customer groups have been deleted, please re-enter at least retail in table customers_groups (see sppc_v421_install.sql)');
define('TEXT_PRODUCTS_MIN_ORDER_QTY', 'Cantidad mínima del pedido:');
define('TEXT_PRODUCTS_MIN_ORDER_QTY_HELP', 'Por defecto 1');
define('TEXT_PRICE_BREAK_INFO', '<acronym title="as Price(Qty)">Price breaks</acronym>: ');
define('PB_DROPDOWN_BEFORE', '');
define('PB_DROPDOWN_BETWEEN', ' at ');
define('PB_DROPDOWN_AFTER', ' each');
define('PB_FROM', 'from');
if (!defined('TEXT_DELETE_IMAGE')) define('TEXT_DELETE_IMAGE', 'Eliminar esta imagen');
define('TEXT_DELETE_ARCHIVO', 'Eliminar esta archivo');



define('TEXT_PRODUCTS_SHIPPING', 'Envío Gratuito:');
if (!defined('TEXT_YES')) define('TEXT_YES', 'Si');
if (!defined('TEXT_NO')) define('TEXT_NO', 'No');
define('TEXT_PRODUCTS_IMAGE_REMOVE', 'Eliminar esta imagen de este producto?');
define('TEXT_PRODUCTS_IMAGE_DELETE', 'Suprimir esta imagen desde el servidor (Permanente!)?');

define('TEXT_PRODUCTS_SEO_URL', 'Producto SEO URL:');
define('TEXT_EDIT_CATEGORIES_SEO_URL', 'Categoría SEO URL:');
define('TEXT_CATEGORIES_SEO_URL', 'Categoría SEO URL:');


define('TEXT_PRODUCTS_BUNDLE', 'Configurar Pack de Producto:');
define('TEXT_ADD_LINE', 'Añadir nueva linea');
define('TEXT_ADD_PRODUCT', 'Seleccionar Producto a Añadir: ');
define('TEXT_REMOVE_PRODUCT', 'Eliminar');
define('TEXT_BUNDLE_HEADING_PRODUCT', 'Producto:');
define('TEXT_BUNDLE_HEADING_QUANTITY', 'Cantidad:');
define('TEXT_PRODUCTS_BY_BUNDLE', 'Este Pack de Productos contiene los siguientes productos:');
define('TEXT_RATE_COSTS', 'Coste de las partes separadas:');
define('TEXT_IT_SAVE', 'Ahorras');
define('ENTRY_AVAILABLE_SEPARATELY', 'Este producto está disponible para su venta individual.');
define('ENTRY_IN_BUNDLE_ONLY', 'Este producto está disponible solo para vender como parte del Pack.');
define('TEXT_SOLD_IN_BUNDLE', 'Este producto puede ser comprado solo como parte de los siguientes Packs:');
define('TEXT_EDIT_PRODUCT', 'Editar producto en %s');

define('TEXT_CATEGORIES_SAVE_CHANGES_EDIT', 'Guardar cambios del producto');
define('TEXT_CATEGORIES_SAVE_CHANGES_NEW', 'Crear producto nuevo');
define('TEXT_CATEGORIES_BACK_NO_SAVE', 'Volver sin guardar');
define('TEXT_CATEGORIES_VIEW_PRODUCT_INFO', 'Ver producto');
