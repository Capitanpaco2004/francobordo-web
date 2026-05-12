<?php

/*
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

/*
Google Product feed configuration for The Feedmachine Solution
based on google-simple.php by: Lech Madrzyk
----------------------------
This configuration is complient with the Google-Feed specifications of march 2012.
It has to be used together with the modified feedmachine.php file which includes the 'IS_IN_STOCK' Keyword definition.
*/

$feed_config = array(
    'name' => 'Google Product Search',
                     'authors' => 'francobordo',
    'with',
                     'filename' => 'francobordo_options.txt', //change the name and the filename to a unique name for each language and country
                     'schema_version' => '2.0',
    'fields' => array(
        'id' => array(
            'output' => 'products_id',
            'type' => 'DB',
            'options' => array(
                'INTVAL'
            )
        ),
        'title' => array(
            'output' => 'products_name',
            'type' => 'DB',
            'options' => array(
                'STRIP_HTML',
                'STRIP_CRLF'
            )
        ),
        'price' => array(
            'output' => 'FM_RS_final_price_with_TAX_es_sp_wa',
            'type' => 'FUNCTION'
        ),
	'cost_price' => array('output' => 'products_cost',
                   'type' => 'DB'
        ),
        'brand' => array(
            'output' => 'manufacturers_name',
            'type' => 'DB',
            'options' => array(
                'STRIP_HTML',
                'HTML_ENTITIES',
                'STRIP_CRLF'
            )
        ),
        'mpn' => array(
            'output' => 'products_model',
            'type' => 'DB'
        ),
        'gtin' => array(
            'output' => 'product_ean',
            'type' => 'DB'
        ),
        'google_product_category' => array(
            'output' => 'FM_RS_google_categories_es_sp_wa', //change the name to the name used for the function
            'type' => 'FUNCTION'
        ),
        'product_type' => array(
            'output' => 'CATEGORY_TREE',
            'type' => 'KEYWORD',
            'options' => array(
                'STRIP_HTML',
                'STRIP_CRLF'
            )
            ),
        'link' => array(
            'output' => 'PRODUCTS_URL',
            'type' => 'KEYWORD'
        ),
        'image_link' => array(
            'output' => 'IMAGE_URL',
            'type' => 'KEYWORD'
        ),
        'condition' => array(
            'output' => 'new', //change to 'used' or 'refurbished' if needed
            'type' => 'VALUE'
        ),
        'description' => array(
            'output' => 'products_description',
            'type' => 'DB',
            'options' => array(
                'STRIP_HTML',
                'STRIP_CRLF'
            )
        ),
        'shipping_weight' => array(
            'output' => 'FM_RS_shipping_weight_and_unit',
            'type' => 'FUNCTION'
        ),
        'shipping_label' => array(
            'output' => 'FM_RS_shipping_label',
            'type' => 'FUNCTION'
        ),
        'availability' => array(
            'output' => 'IS_IN_STOCK',
            'type' => 'KEYWORD'
        )
    ),
                     'currency_decimal_override' => false,
                     'currency_thousands_override' => '',
                     'add_field_names' => true,
                     'category_tree_seperator' => '-',
                     'seperator' => "\t",
                     'text_qualifier' => '',
                     'newline' => "\n",
                     'encoding' => 'false',
    		     'precio' => 0,
    		     'include_attributes' => 1,
                     'include_record_function' => ''
                    );

//FEED FUNCTIONS BEGIN

function FM_RS_product_id_es_sp_options($product) {
  return 'Francobordo' . $product['products_id'] . '_es_sp_options';
}

function FM_RS_google_categories_es_sp_options($product) {
	$output_field_category = ($product['parent_id'] > 0) ? $product['parent_id'] : $product['categories_id'];
	return  (($output_field_category == 2) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > lanchas hinchables y botes remolcables':
		(($output_field_category == 9) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 10) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > lanchas hinchables y botes remolcables':
		(($output_field_category == 11) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones':
		(($output_field_category == 12) ? 'Equipamiento deportivo > Deportes acuáticos > Chalecos salvavidas':
	        (($output_field_category == 14) ? 'Equipamiento deportivo > Deportes acuáticos > Chalecos salvavidas':
	        (($output_field_category == 18) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones':
		(($output_field_category == 22) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 23) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 26) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje':
		(($output_field_category == 27) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cadenas para anclas':
		(($output_field_category == 28) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Anclas':
	        (($output_field_category == 29) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje':
		(($output_field_category == 30) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje':
    	        (($output_field_category == 31) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Bicheros':
		(($output_field_category == 33) ? 'Equipamiento deportivo > Deportes acuáticos > Chalecos salvavidas':
		(($output_field_category == 34) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados':
		(($output_field_category == 35) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > Esquí acuático':
	        (($output_field_category == 36) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 39) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 40) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 41) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 42) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 43) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 44) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Cuidado de vehículos motorizados > Cables de empalme':
		(($output_field_category == 45) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 46) ? 'Electrónica > Localizadores GPS':
		(($output_field_category == 48) ? 'Electrónica > Accesorios de electrónica náutica > Ecosondas':
		(($output_field_category == 49) ? 'Electrónica > Audio > Equipo de sonido > Altavoces':
		(($output_field_category == 51) ? 'Equipamiento deportivo > Deportes acuáticos > Embarcación':
		(($output_field_category == 53) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 54) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 55) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > Esquí acuático > Bolsas y estuches para esquí acuático':
		(($output_field_category == 56) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > lanchas hinchables y botes remolcables':
		(($output_field_category == 57) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca':
		(($output_field_category == 58) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de dirección para embarcaciones':
		(($output_field_category == 59) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cornamusas':
		(($output_field_category == 60) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 61) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 62) ? 'Bricolaje > Accesorios de herraje > Bisagras':
	        (($output_field_category == 64) ? 'Equipamiento deportivo > Deportes acuáticos > Chalecos salvavidas':
		(($output_field_category == 68) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 69) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Iluminación para embarcaciones':
		(($output_field_category == 70) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cuerdas y sogas para anclas':
		(($output_field_category == 73) ? 'Electrónica > Accesorios de electrónica náutica > GPS y trazadores gráficos náuticos':
		(($output_field_category == 74) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Escalas para barcos':
		(($output_field_category == 75) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros':
		(($output_field_category == 80) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 81) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros':
		(($output_field_category == 85) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Cuidado para embarcaciones':
		(($output_field_category == 88) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cabrestantes para anclas':
		(($output_field_category == 89) ? 'Electrónica > Accesorios de electrónica náutica > Radar náutico':
		(($output_field_category == 91) ? 'Electrónica > Accesorios de electrónica náutica > Ecosondas':
		(($output_field_category == 93) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 94) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Cuidado para embarcaciones':
		(($output_field_category == 95) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Sistemas de combustible para embarcaciones > Piezas y depósitos de combustible para embarcaciones':
		(($output_field_category == 96) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 99) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Cuidado de vehículos motorizados > Cargadores de baterías de vehículos':
		(($output_field_category == 101) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Cuidado de vehículos motorizados > Cargadores de baterías de vehículos':
		(($output_field_category == 103) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Iluminación para embarcaciones':
		(($output_field_category == 106) ? 'Electrónica > Accesorios de electrónica náutica > Radios náuticas':
		(($output_field_category == 107) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 108) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Cuidado para embarcaciones':
		(($output_field_category == 113) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 115) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 125) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 131) ? 'Electrónica > Accesorios de electrónica náutica':
		(($output_field_category == 135) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Mosquetones':
		(($output_field_category == 138) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de dirección para embarcaciones > Ruedas de timones para embarcaciones':
		(($output_field_category == 143) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 154) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 165) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 185) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca':
		(($output_field_category == 186) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 187) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 189) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 194) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 202) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 204) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros':
		(($output_field_category == 206) ? 'Software > Software informático > Software de referencia > Software y datos de mapa para GPS':
		(($output_field_category == 210) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 215) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Iluminación para embarcaciones':
		(($output_field_category == 223) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Escalas para barcos':
		(($output_field_category == 227) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros':
		(($output_field_category == 232) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 246) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 249) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 264) ? 'Casa y jardín > Cocina y comedor > Objetos para llevar comida y bebida > Neveras':
		(($output_field_category == 274) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 284) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cebos de pesca':
		(($output_field_category == 285) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cebos de pesca':
		(($output_field_category == 308) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cebos de pesca':
		(($output_field_category == 324) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cajas y bolsas de avíos':
		(($output_field_category == 328) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cañas de pescar':
		(($output_field_category == 330) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Carretes de pesca':
		(($output_field_category == 346) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Iluminación para embarcaciones':
		(($output_field_category == 351) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 360) ? 'Bricolaje > Accesorios de herraje > Tornillos':
		(($output_field_category == 366) ? 'Medios audiovisuales > Manuales de productos > Manuales de actividades de ocio y deportes':
		(($output_field_category == 373) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo':
		(($output_field_category == 374) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Accesorios para tiendas de campaña':
		(($output_field_category == 379) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Mobiliario de camping > Colchones de aire':
		(($output_field_category == 380) ? 'Equipamiento deportivo > Deportes de invierno > Descenso en trineo > Trineos > Platos para deslizarse por la nieve':
		(($output_field_category == 388) ? 'Equipamiento deportivo > Deportes acuáticos > Embarcación > Remo':
		(($output_field_category == 389) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 394) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones':
		(($output_field_category == 397) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 415) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Vajillas y utensilios de cocina para camping':
		(($output_field_category == 416) ? 'Casa y jardín > Cocina y comedor > Electrodomésticos de cocina > Cocinas de camping':
		(($output_field_category == 418) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Faroles y lámparas de camping':
		(($output_field_category == 419) ? 'Casa y jardín > Decoración > Fundas':
		(($output_field_category == 423) ? 'Casa y jardín > Cocina y comedor > Electrodomésticos de cocina > Parrillas exteriores > Parrillas de gas':
		(($output_field_category == 425) ? 'Casa y jardín > Cocina y comedor > Accesorios para aparatos de cocina > Accesorios para parrillas exteriores':
		(($output_field_category == 463) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca':
		(($output_field_category == 485) ? 'Bricolaje > Herramientas > Linternas':
		(($output_field_category == 491) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel':
		(($output_field_category == 492) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Ordenadores de buceo':
		(($output_field_category == 493) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel':
		(($output_field_category == 494) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Compensadores de flotabilidad':
		(($output_field_category == 501) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Tijeras y cuchillos para bucear' :
		(($output_field_category == 502) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel' :
		(($output_field_category == 503) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Reguladores':
		(($output_field_category == 519) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Trajes de buceo':
		(($output_field_category == 520) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Aletas para bucear con esnórquel':
		(($output_field_category == 528) ? 'Casa y jardín > Decoración > Banderas y mangas de viento':
		(($output_field_category == 534) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Anzuelos de pesca':
		(($output_field_category == 540) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Sacos de dormir':
		(($output_field_category == 548) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca':
		(($output_field_category == 551) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Mobiliario de camping':
		(($output_field_category == 552) ? 'Casa y jardín > Piscina y balneario > Accesorios para piscinas y jacuzzis > Colchonetas y flotadores para piscina':
		(($output_field_category == 556) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 569) ? 'Equipamiento deportivo > Actividades al aire libre > Mochilas y bolsas de hidratación':
		(($output_field_category == 580) ? 'Casa y jardín > Cocina y comedor > Objetos para llevar comida y bebida > Neveras':
		(($output_field_category == 582) ? 'Casa y jardín > Cocina y comedor > Objetos para llevar comida y bebida > Neveras':
		(($output_field_category == 597) ? 'Electrónica > Accesorios de electrónica náutica':
		(($output_field_category == 615) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > Esquí acuático > Bolsas y estuches para esquí acuático':
		(($output_field_category == 620) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Tiendas de campaña':
		(($output_field_category == 627) ? 'Casa y jardín > Accesorios para el baño > Botiquines':
		(($output_field_category == 637) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de motor de embarcaciones > Hélices de embarcaciones':
		(($output_field_category == 644) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros':
		(($output_field_category == 687) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros':
		(($output_field_category == 688) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros':
		(($output_field_category == 690) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros':
		(($output_field_category == 699) ? 'Electrónica > Accesorios electrónicos > Energía > Accesorios de baterías':
		(($output_field_category == 706) ? 'Casa y jardín > Cocina y comedor > Electrodomésticos de cocina > Cocinas de camping':
		(($output_field_category == 717) ? 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cebos de pesca':
		(($output_field_category == 782) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > lanchas hinchables y botes remolcables':
		(($output_field_category == 779) ? 'Equipamiento deportivo > Deportes acuáticos > Surf > Paddleboards':
		(($output_field_category == 807) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Trajes de buceo':
		(($output_field_category == 808) ? 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Accesorios para tiendas de campaña > Protectores de suelo para tiendas de campaña':
		(($output_field_category == 829) ? 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Trajes de buceo':
		(($output_field_category == 839) ? 'Equipamiento deportivo > Deportes acuáticos > Embarcación > Kayaking > Accesorios para kayak':
		(($output_field_category == 854) ? 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > Esquí acuático > Bolsas y estuches para esquí acuático':
		(($output_field_category == 896) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones':
		(($output_field_category == 907) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 1000) ? 'Equipamiento deportivo > Deportes acuáticos > Embarcación > Kayaking > Accesorios para kayak':
		(($output_field_category == 1017) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cabrestantes para anclas':
		(($output_field_category == 1061) ? 'Óptica y fotografía > Cámaras > Cámaras digitales':
		(($output_field_category == 1091) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de motor de embarcaciones > Ruedas de paletas para embarcaciones':
		(($output_field_category == 1092) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de motor de embarcaciones > Ruedas de paletas para embarcaciones':
		(($output_field_category == 1104) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 1111) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 1120) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 1123) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones':
		(($output_field_category == 1138) ? 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Escaleras para muelle':
		'')))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))));
	}

function FM_RS_final_price_with_tax_options($product) 
{

	$sql = "SELECT specials_new_products_price from specials where products_id = '" . $product['products_id'] . "' AND status = '1' limit 1";
    $special_query  = tep_db_query($sql);
    $special_result = tep_db_fetch_array($special_query);
    if ($special_result['specials_new_products_price'] > 0) {
        $price = $special_result['specials_new_products_price'];
    } else {
		$price =$product['final_price'];
    }

	$price = round(($price) * (1 + ((tep_get_tax_rate($product['products_tax_class_id']) / 100))), 2);
    return $price;

}
//FEED FUNCTIONS END

