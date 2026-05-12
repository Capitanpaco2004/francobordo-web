<?php
/*
  $Id: attributeManager.php,v 1.0 21/02/06 Sam West$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Released under the GNU General Public License
  
  English translation to AJAX-AttributeManager-V2.7
  
  by Shimon Doodkin
  http://help.me.pro.googlepages.com
  helpmepro1@gmail.com
*/

//attributeManagerPrompts.inc.php

define('AM_AJAX_YES', 'Si');
define('AM_AJAX_NO', 'No');
define('AM_AJAX_UPDATE', 'Actualizar');
define('AM_AJAX_CANCEL', 'Cancelar');
define('AM_AJAX_OK', 'OK');

define('AM_AJAX_SORT', 'Ordenar:');
define('AM_AJAX_TRACK_STOCK', 'Track Stock?');
define('AM_AJAX_TRACK_STOCK_IMGALT', 'Track this attribute stock ?');

define('AM_AJAX_ENTER_NEW_OPTION_NAME', 'Nuevo atributo');
define('AM_AJAX_ENTER_NEW_OPTION_VALUE_NAME', 'Nuevo valor');
define('AM_AJAX_ENTER_NEW_OPTION_VALUE_NAME_TO_ADD_TO', 'Nuevo nombre de valor a a�adir a %s');

define('AM_AJAX_PROMPT_REMOVE_OPTION_AND_ALL_VALUES', 'Esta seguro de que quiere borrar los atributos de %s y todos sus valores para este producto?');
define('AM_AJAX_PROMPT_REMOVE_OPTION', 'Seguro que quiere borrar %s de este producto?');
define('AM_AJAX_PROMPT_STOCK_COMBINATION', 'Are you sure you want to remove this stock combination from this product?');

define('AM_AJAX_PROMPT_LOAD_TEMPLATE', 'Seguro que quiere recuperar %s de la plantilla? <br >Se sobreescribir�n los atributos actuales del producto. La operaci�n no se puede deshacer');
define('AM_AJAX_NEW_TEMPLATE_NAME_HEADER', 'Por favor incluya el nomber de la nueva plantilla. O...');
define('AM_AJAX_NEW_NAME', 'Nuevo nombre:');
define('AM_AJAX_CHOOSE_EXISTING_TEMPLATE_TO_OVERWRITE', ' ...<br > ... escoja una que exista para sobreescribirla');
define('AM_AJAX_CHOOSE_EXISTING_TEMPLATE_TITLE', 'Ya existe:'); 
define('AM_AJAX_RENAME_TEMPLATE_ENTER_NEW_NAME', 'Por favor incluya el nuevo nombre para la plantilla %s');
define('AM_AJAX_PROMPT_DELETE_TEMPLATE', 'Confirme borrado de la plantilla %s?<br>La operaci�n no se puede deshacer!');

//attributeManager.php

define('AM_AJAX_ADDS_ATTRIBUTE_TO_OPTION', 'A�ade el atributo seleccionado a la izquierda de la %s opci�n');
define('AM_AJAX_ADDS_NEW_VALUE_TO_OPTION', 'A�ade un nuevo valor a la opci�n %s');
define('AM_AJAX_PRODUCT_REMOVES_OPTION_AND_ITS_VALUES', 'Borra la opci�n %1$s y los %2$d valor(es) por debajo de la opci�n de este producto');
define('AM_AJAX_CHANGES', 'Cambios'); 
define('AM_AJAX_LOADS_SELECTED_TEMPLATE', 'Carga la plantilla seleccionada');
define('AM_AJAX_SAVES_ATTRIBUTES_AS_A_NEW_TEMPLATE', 'Guardar los atributos actuales como una nueva plantilla');
define('AM_AJAX_RENAMES_THE_SELECTED_TEMPLATE', 'Renombrar la plantilla seleccionada');
define('AM_AJAX_DELETES_THE_SELECTED_TEMPLATE', 'Borrar la plantilla seleccionada');
define('AM_AJAX_NAME', 'Nombre');
define('AM_AJAX_ACTION', 'Acci�n');
define('AM_AJAX_PRODUCT_REMOVES_VALUE_FROM_OPTION', 'Borra %1$s de %2$s, para este producto');
define('AM_AJAX_MOVES_VALUE_UP', 'Mover el valor haca arriba');
define('AM_AJAX_MOVES_VALUE_DOWN', 'Mover el valor hacia abajo');
define('AM_AJAX_MOVES_OPTION_UP', 'Mover la opción hacia arriba');
define('AM_AJAX_MOVES_OPTION_DOWN', 'Mover la opción hacia abajo');
define('AM_AJAX_ADDS_NEW_OPTION', 'A�adir una nueva opci�n a la lista');
define('AM_AJAX_OPTION', 'Opci�n:');
define('AM_AJAX_VALUE', 'Valor:');
define('AM_AJAX_PREFIX', 'Prefijo:');
define('AM_AJAX_PRICE', 'Precio:');
define('AM_AJAX_REFERENCE', 'Referencia:');
define('AM_AJAX_WEIGHT_PREFIX', 'Wgt.Prefix:');
define('AM_AJAX_WEIGHT', 'Peso:');
define('AM_AJAX_SORT', 'Orden:');
define('AM_AJAX_ADDS_NEW_OPTION_VALUE', 'Adds a new option value to the list');
define('AM_AJAX_ADDS_ATTRIBUTE_TO_PRODUCT', 'A�adir el atributo al producto actual');
define('AM_AJAX_DELETES_ATTRIBUTE_FROM_PRODUCT', 'Deletes attribute or attribute combination from the current product');
define('AM_AJAX_QUANTITY', 'Cantidad');
define('AM_AJAX_PRODUCT_REMOVE_ATTRIBUTE_COMBINATION_AND_STOCK', 'Elimina esta combinaci�n de atributos y las existencias del producto');
define('AM_AJAX_UPDATE_OR_INSERT_ATTRIBUTE_COMBINATIONBY_QUANTITY', 'Insertar o actualizar el atributo en combinaci�n con la cantidad determinada');
define('AM_AJAX_UPDATE_PRODUCT_QUANTITY', 'Set the given quantity to the current product');

//attributeManager.class.php
define('AM_AJAX_TEMPLATES', '-- Plantillas --');

//----------------------------
// Change: download attributes for AM
//
// author: mytool
//-----------------------------
define('AM_AJAX_FILENAME', 'Archivo');
define('AM_AJAX_FILE_DAYS', 'D�as');
define('AM_AJAX_FILE_COUNT', 'descargas m�ximas');
define('AM_AJAX_DOWLNOAD_EDIT', 'Editar opcion de descarga');
define('AM_AJAX_DOWLNOAD_ADD_NEW', 'A�adir opcion de descarga');
define('AM_AJAX_DOWLNOAD_DELETE', 'Borrar opcion de descarga');
define('AM_AJAX_HEADER_DOWLNOAD_ADD_NEW', 'A�adir opcion de descarga para \"%s\"');
define('AM_AJAX_HEADER_DOWLNOAD_EDIT', 'Editar opcion de descarga para\"%s\"');
define('AM_AJAX_HEADER_DOWLNOAD_DELETE', 'Borrar opcion de descarga para\"%s\"');
define('AM_AJAX_FIRST_SAVE', 'Hay que guardar el producto antes de a�adir opciones');

//----------------------------
// EOF Change: download attributes for AM
//-----------------------------

define('AM_AJAX_OPTION_NEW_PANEL','Nueva opcion:');
define('AM_AJAX_QT_PRO', 'QT PRO');
?>