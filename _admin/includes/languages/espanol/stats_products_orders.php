<?php
/*
  $Id: stats_products_orders.php,v 1 06 sept 2009 

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2005 osCommerce

  originally developed by paddybl
  Released under the GNU General Public License
*/	

define('HEADING_TITLE', 'Estadisticas de pedidos');
define('HEADING_DAY','Mostrar por dia:');
define('HEADING_MONTH', 'Mostrar por mes:');
define('HEADING_YEAR', 'Mostrar por año:');
define('HEADING_TITLE_NO_STATUS', 'Ocultar estado de los pedidos:');
define('HEADING_TITLE_STATUS', 'Mostrar solo los estado de pedidos:');
define('TEXT_NO_ORDERS', 'Ninguno');
define('TEXT_ALL_ORDERS', 'Todos');
define('HEADING_CUSTOMER', 'Seleccionar cliente: ');
define('TEXT_SELECT_CUSTOMER', 'Seleccionar cliente');
define('TEXT_ALL_CUSTOMERS', 'Todos los cliente');
define('HEADING_MANUFACTURER', 'Seleccionar fabricante: ');
define('TEXT_SELECT_MANUFACTURER', 'Seleccionar fabricante');
define('TEXT_ALL_MANUFACTURERS', 'Todos los fabricantes');
define('HEADING_CATEGORIES', 'Seleccionar categoria: ');
define('TEXT_SELECT_CATEGORY', 'Seleccionar categoria');
define('TEXT_ALL_CATEGORIES', 'Todas las categorias');
define('HEADING_ATTRIBUTES', 'Seleccionar opcion');
define('TEXT_ALL_OPTIONS', 'Todas las opciones');
define('HEADING_PRODUCTS', 'Seleccionar producto: ');
define('TEXT_SELECT_PRODUCT', 'Seleccionar productos');
define('TEXT_ALL_PRODUCTS', 'Todos los productos');

################### CONTENU MENUS ############################################################
define('JAN', 'Enero');
define('FEV', 'Febrero');
define('MAR', 'Marzo');
define('AVR', 'Abril');
define('MAI', 'Mayo');
define('JUN', 'Junio');
define('JUI', 'Julio');
define('AOU', 'Agosto');
define('SEP', 'Septiembre');
define('OCT', 'Octubre');
define('NOV', 'Noviembre');
define('DEC', 'Diciembre');
define('TEXT_ALL_DAYS', 'Todos los dias');
define('TEXT_ALL_MOIS', 'Todos los meses');
define('TEXT_ALL_ANNEE', 'Todos los años');
######################################################################################

################### TEXTES INFOS ###############################################################
define('DATE_ORDERS','Fecha de compra');
define('ORDER_ID','Numero de pedido');
define('ORDER_CUSTOMER','Nombre cliente<br>movil');
define('TEXT_PHONE','Movil: ');
define('ORDER_ADDRESS','Direccion');
define('ORDER_PRODUCT','ID, nombre producto y opcion(es)');
define('TEXT_CHOOSE_PRODUCT','Seleccionar este producto');
define('ORDER_QUANTITY','Cantidad');
define('ORDER_STATUS','Estado');
define('ORDER_TOTAL','Precio<br>sin IVA<br>Forma de pago');

#################### TEXTES RAPPORTS ############################################################
define('NEW_CUSTOMERS', 'Nuevos clientes:');
define('QUANTITY_PRODUCTS','Numero de productos vendidos: ');
define('NUMBER_ORDER', 'Numero de pedidos:');
define('CUSTOMERS_BOUGHT', 'Compras de clientes:');
define('NEW_CUSTOMERS_BOUGHT', 'Compras de nuevos clientes:');
define('TOTAL_TTC', 'Total de ventas impuestos incluidos:');
define('TOTAL_TAX', 'Total impuestos:');
define('TOTAL_HT', 'Total de ventas sin impuestos:');


define('BASKET_TTC', 'Promedio de ventas:');
define('BASKET_HT', 'Promedio del carrito:');
define('TEXT_NO_PRODUCTS', 'No hay productos para este filtro');
define('TEXT_RESULT_PAGES', 'Pagina: ');
define('PREVNEXT_TITLE_PAGE_NO', 'Pagina %d');
define('TEXT_SORT_PRODUCTS', 'Ordenar productos ');
define('TEXT_DESCENDINGLY', 'descendentemente ');
define('TEXT_ASCENDINGLY', 'ascendentemente');
define('TEXT_BY', ' por ');

################# TEXTES INFOS POPUP ##################################################

define('TEXT_BUTTON_REPORT_PRINT','Imprimir');
define('TEXT_BUTTON_REPORT_PRINT_DESC','Imprimir este informe');
define('HEADING_NO_OPTIONS',' ERROR: no puede utilizar las opciones de filtro (ver install.txt)');
define('TEXT_EDIT_ORDER','Editar pedido');
define('TEXT_MAILTO','enviar email');
define('TEXT_VIEW_PRODUCT','Ver producto (nueva ventana)');
define('TEXT_INFO_DATE_ADDED', 'Fecha añadido:');
define('TEXT_INFO_LAST_MODIFIED', 'Ultima modificacion:');
define('TEXT_SPECIAL_INFO_DATE_ADDED', 'Fecha añadido (promo):');
define('TEXT_SPECIAL_INFO_LAST_MODIFIED', 'Ultima modificacion (promo):');
define('TEXT_INFO_NEW_PRICE', 'Nuevo precio:');
define('TEXT_INFO_ORIGINAL_PRICE', 'Precio actual:');
define('TEXT_INFO_PERCENTAGE', 'Porcentaje:');
define('TEXT_INFO_EXPIRES_DATE', 'Fecha caducidad:');
define('TEXT_INFO_STATUS_CHANGE', 'Cambio de estado:');
define('TEXT_INFO_COST','Costo:');
define('TEXT_GRATUIT','0');
define('TEXT_INFO_MARGE','Marge:');
define('HEADING_REFERENCE', 'Seleccionar modelo: ');
define('TEXT_SELECT_REFERENCE', 'Seleccionar modelo');
define('TEXT_ALL_REFERENCE', 'Todos los modelos');
define('TEXT_INFO_QUANTITY','Stock: ');

define('ORDERS_CSV_PRINT_LISTING','Descargar lista para EXCEL');
define('ORDERS_CSV_PRINT_RESULT','Descargar resultados para EXCEL');
define('ORDERS_CSV_DATE','Fecha de compra');
define('ORDER_CSV_ID','Numero de pedido');
define('ORDER_CSV_CUSTOMER','Nombre cliente');
define('ORDER_CSV_EMAIL', 'email');
define('ORDER_CSV_PHONE', 'Movil');
define('ORDER_CSV_STREET', 'Direccion');
define('ORDER_CSV_POSTCODE', 'Codigo postal');
define('ORDER_CSV_ADDRESS', 'Direccion');
define('ORDER_CSV_PRODUCTS_ID', 'ID producto');
define('ORDER_CSV_PRODUCT', 'Nombre producto');
define('ORDER_CSV_PRODUCT_OPTION', 'Opcion(es) producto(s)');
define('ORDER_CSV_QUANTITY', 'Cantidad');
define('ORDER_CSV_STATUS','Estado pedido');


define('ORDER_CSV_PAYMENT', 'Forma de pago');
define('ORDER_CSV_NEW_CUSTOMERS', 'Nuevo cliente');
define('ORDER_CSV_QUANTITY_PRODUCTS', 'Cantidad producto');
define('ORDER_CSV_CUSTOMERS_BOUGHT','compra cliente');
define('ORDER_CSV_NEW_CUSTOMERS_BOUGHT', 'compra nuevo cliente');
define('ORDER_CSV_NUMBER_ORDER', 'Numero pedido');
define('ORDER_CSV_TOTAL_HT', 'Total sin IVA');

define('ORDER_CSV_TOTAL_TTC', 'Total con IVA');
define('ORDER_CSV_TOTAL_TAX', 'Total IVA');

define('ORDER_CSV_BASKET_TTC','Cesta con IVA');
define('ORDER_CSV_BASKET_HT','Cesta sin IVA');

define('HEADING_CSV_CUSTOMER', 'Cliente: ');
define('HEADING_CSV_MANUFACTURER', 'Fabricante: ');
define('HEADING_CSV_CATEGORIES','Categoria: ');
define('HEADING_CSV_PRODUCTS','Producto: ');
define('HEADING_CSV_OPTIONS','Opcion');	
define('HEADING_CSV_REFERENCE','Modelo: ');
define('HEADING_DAY_START','Dia de inicio:');
define('HEADING_MONTH_START','Mes de inicio:');
define('HEADING_YEAR_START','Año de inicio:');
define('HEADING_DAY_END','Dia de finalizacion:');
define('HEADING_MONTH_END','Mes de finalizacion:');
define('HEADING_YEAR_END','Año de finalizacion:');
define('CSV_NO_STATUS','Ocultar estado de pedidos:');
define('CSV_STATUS','Mostrar estado de pedidos:');

?>