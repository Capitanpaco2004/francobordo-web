<?php
/*
Google Product feed configuration for The Feedmachine Solution
*/

$feed_config = [
	'name'                        => 'Google Product Search',
	'authors'                     => 'francobordo',
	'with',
	'filename'                    => 'francobordo.txt', //change the name and the filename to a unique name for each language and country
	'schema_version'              => '2.0',
	'fields'                      => [
		'id'                      => [
			'output'  => 'products_id',
			'type'    => 'DB',
			'options' => [
				'INTVAL',
			],
		],
		'title'                   => [
			'output'  => 'products_name',
			'type'    => 'DB',
			'options' => [
				'STRIP_HTML',
				'STRIP_CRLF',
			],
		],
		'price'                   => [
			'output' => 'FM_RS_final_price_with_TAX_es_sp_wa',
			'type'   => 'FUNCTION',
		],
		'brand'                   => [
			'output'  => 'manufacturers_name',
			'type'    => 'DB',
			'options' => [
				'STRIP_HTML',
				'HTML_ENTITIES',
				'STRIP_CRLF',
			],
		],
		'mpn'                     => [
			'output' => 'products_model',
			'type'   => 'DB',
		],
		'gtin'                    => [
			'output' => 'product_ean',
			'type'   => 'DB',
		],
		'google_product_category' => [
			'output' => 'FM_RS_google_categories_es_sp_wa', //change the name to the name used for the function
			'type'   => 'FUNCTION',
		],
		'product_type'            => [
			'output'  => 'CATEGORY_TREE',
			'type'    => 'KEYWORD',
			'options' => [
				'STRIP_HTML',
				'STRIP_CRLF',
			],
			'filters' => [
				'patterns'     => [
					'#Submarinismo-Fusiles y Cuchillos-#',
				],
				'replacements' => [
					'Submarinismo-Cuchillos-',
				],
			],
		],
		'link'                    => [
			'output' => 'PRODUCTS_URL',
			'type'   => 'KEYWORD',
		],
		'image_link'              => [
			'output' => 'IMAGE_URL',
			'type'   => 'KEYWORD',
		],
		'condition'               => [
			'output' => 'new', //change to 'used' or 'refurbished' if needed
			'type'   => 'VALUE',
		],
		'description'             => [
			'output'  => 'products_description',
			'type'    => 'DB',
			'options' => [
				'STRIP_HTML',
				'STRIP_CRLF',
			],
		],
		'shipping_weight'         => [
			'output' => 'FM_RS_shipping_weight_and_unit',
			'type'   => 'FUNCTION',
		],
		'shipping_label'          => [
			'output' => 'FM_RS_shipping_label',
			'type'   => 'FUNCTION',
		],
		'availability'            => [
			'output' => 'IS_IN_STOCK',
			'type'   => 'KEYWORD',
		],
	],
	'currency_decimal_override'   => false,
	
	'currency_thousands_override' => '',
	'add_field_names'             => true,
	'category_tree_seperator'     => '-',
	'seperator'                   => "\t",
	'text_qualifier'              => '',
	'newline'                     => "\n",
	'encoding'                    => 'false',
	'precio'                      => 0,
	'include_attributes'          => 1,
	'include_record_function'     => '',
];

//FEED FUNCTIONS BEGIN

function FM_RS_product_id_es_sp_wa($product) {
	return 'Francobordo' . $product['products_id'] . '_es_sp_wa';
}

function FM_RS_google_categories_es_sp_wa($product) {
    $category_id = ($product['parent_id'] > 0) ? $product['parent_id'] : $product['categories_id'];

    $categories = [
        2 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > lanchas hinchables y botes remolcables',
        9 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        10 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > lanchas hinchables y botes remolcables',
        11 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones',
        12 => 'Equipamiento deportivo > Deportes acuáticos > Chalecos salvavidas',
        14 => 'Equipamiento deportivo > Deportes acuáticos > Chalecos salvavidas',
        18 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones',
        22 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        23 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        26 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje',
        27 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cadenas para anclas',
        28 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Anclas',
        29 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje',
        30 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje',
        31 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Bicheros',
        33 => 'Equipamiento deportivo > Deportes acuáticos > Chalecos salvavidas',
        34 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados',
        35 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > Esquí acuático',
        36 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        39 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        40 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        41 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        42 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        43 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        44 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Cuidado de vehículos motorizados > Cables de empalme',
        45 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        46 => 'Electrónica > Localizadores GPS',
        48 => 'Electrónica > Accesorios de electrónica náutica > Ecosondas',
        49 => 'Electrónica > Audio > Equipo de sonido > Altavoces',
        51 => 'Equipamiento deportivo > Deportes acuáticos > Embarcación',
        53 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        54 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        55 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > Esquí acuático > Bolsas y estuches para esquí acuático',
        56 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > lanchas hinchables y botes remolcables',
        57 => 'Equipamiento deportivo > Actividades al aire libre > Pesca',
        58 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de dirección para embarcaciones',
        59 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cornamusas',
        60 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        61 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        62 => 'Bricolaje > Accesorios de herraje > Bisagras',
        64 => 'Equipamiento deportivo > Deportes acuáticos > Chalecos salvavidas',
        68 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        69 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Iluminación para embarcaciones',
        70 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cuerdas y sogas para anclas',
        73 => 'Electrónica > Accesorios de electrónica náutica > GPS y trazadores gráficos náuticos',
        74 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Escalas para barcos',
        75 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros',
        80 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        81 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros',
        85 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Cuidado para embarcaciones',
        88 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cabrestantes para anclas',
        89 => 'Electrónica > Accesorios de electrónica náutica > Radar náutico',
        91 => 'Electrónica > Accesorios de electrónica náutica > Ecosondas',
        93 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        94 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Cuidado para embarcaciones',
        95 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Sistemas de combustible para embarcaciones > Piezas y depósitos de combustible para embarcaciones',
        96 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        99 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Cuidado de vehículos motorizados > Cargadores de baterías de vehículos',
        101 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Cuidado de vehículos motorizados > Cargadores de baterías de vehículos',
        103 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Iluminación para embarcaciones',
        106 => 'Electrónica > Accesorios de electrónica náutica > Radios náuticas',
        107 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        108 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Cuidado para embarcaciones',
        113 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        115 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        125 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        131 => 'Electrónica > Accesorios de electrónica náutica',
        135 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Mosquetones',
        138 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de dirección para embarcaciones > Ruedas de timones para embarcaciones',
        143 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        154 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        165 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        185 => 'Equipamiento deportivo > Actividades al aire libre > Pesca',
        186 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        187 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        189 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        194 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        202 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        204 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros',
        206 => 'Software > Software informático > Software de referencia > Software y datos de mapa para GPS',
        210 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        215 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Iluminación para embarcaciones',
        223 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Escalas para barcos',
        227 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros',
        232 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        246 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        249 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        264 => 'Casa y jardín > Cocina y comedor > Objetos para llevar comida y bebida > Neveras',
        274 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        284 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cebos de pesca',
        285 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cebos de pesca',
        308 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cebos de pesca',
        324 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cajas y bolsas de avíos',
        328 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cañas de pescar',
        330 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Carretes de pesca',
        346 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Iluminación para embarcaciones',
        351 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        360 => 'Bricolaje > Accesorios de herraje > Tornillos',
        366 => 'Medios audiovisuales > Manuales de productos > Manuales de actividades de ocio y deportes',
        373 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo',
        374 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Accesorios para tiendas de campaña',
        379 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Mobiliario de camping > Colchones de aire',
        380 => 'Equipamiento deportivo > Deportes de invierno > Descenso en trineo > Trineos > Platos para deslizarse por la nieve',
        388 => 'Equipamiento deportivo > Deportes acuáticos > Embarcación > Remo',
        389 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        394 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones',
        397 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        415 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Vajillas y utensilios de cocina para camping',
        416 => 'Casa y jardín > Cocina y comedor > Electrodomésticos de cocina > Cocinas de camping',
        418 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Faroles y lámparas de camping',
        419 => 'Casa y jardín > Decoración > Fundas',
        423 => 'Casa y jardín > Cocina y comedor > Electrodomésticos de cocina > Parrillas exteriores > Parrillas de gas',
        425 => 'Casa y jardín > Cocina y comedor > Accesorios para aparatos de cocina > Accesorios para parrillas exteriores',
        463 => 'Equipamiento deportivo > Actividades al aire libre > Pesca',
        485 => 'Bricolaje > Herramientas > Linternas',
        491 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel',
        492 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Ordenadores de buceo',
        493 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel',
        494 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Compensadores de flotabilidad',
        501 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Tijeras y cuchillos para bucear',
        502 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel',
        503 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Reguladores',
        519 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Trajes de buceo',
        520 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Aletas para bucear con esnórquel',
        528 => 'Casa y jardín > Decoración > Banderas y mangas de viento',
        534 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Anzuelos de pesca',
        540 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Sacos de dormir',
        548 => 'Equipamiento deportivo > Actividades al aire libre > Pesca',
        551 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Mobiliario de camping',
        552 => 'Casa y jardín > Piscina y balneario > Accesorios para piscinas y jacuzzis > Colchonetas y flotadores para piscina',
        556 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        569 => 'Equipamiento deportivo > Actividades al aire libre > Mochilas y bolsas de hidratación',
        580 => 'Casa y jardín > Cocina y comedor > Objetos para llevar comida y bebida > Neveras',
        582 => 'Casa y jardín > Cocina y comedor > Objetos para llevar comida y bebida > Neveras',
        597 => 'Electrónica > Accesorios de electrónica náutica',
        615 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > Esquí acuático > Bolsas y estuches para esquí acuático',
        620 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Tiendas de campaña',
        627 => 'Casa y jardín > Accesorios para el baño > Botiquines',
        637 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de motor de embarcaciones > Hélices de embarcaciones',
        644 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros',
        687 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros',
        688 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros',
        690 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas para veleros',
        699 => 'Electrónica > Accesorios electrónicos > Energía > Accesorios de baterías',
        706 => 'Casa y jardín > Cocina y comedor > Electrodomésticos de cocina > Cocinas de camping',
        717 => 'Equipamiento deportivo > Actividades al aire libre > Pesca > Cebos de pesca',
        779 => 'Equipamiento deportivo > Deportes acuáticos > Surf > Paddleboards',
        782 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > lanchas hinchables y botes remolcables',
        807 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Trajes de buceo',
        808 => 'Equipamiento deportivo > Actividades al aire libre > Camping, excursionismo y senderismo > Accesorios para tiendas de campaña > Protectores de suelo para tiendas de campaña',
        829 => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel > Trajes de buceo',
        839 => 'Equipamiento deportivo > Deportes acuáticos > Embarcación > Kayaking > Accesorios para kayak',
        854 => 'Equipamiento deportivo > Deportes acuáticos > Deportes acuáticos remolcados > Esquí acuático > Bolsas y estuches para esquí acuático',
        896 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones',
        907 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        1000 => 'Equipamiento deportivo > Deportes acuáticos > Embarcación > Kayaking > Accesorios para kayak',
        1017 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Cabrestantes para anclas',
        1061 => 'Óptica y fotografía > Cámaras > Cámaras digitales',
        1091 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de motor de embarcaciones > Ruedas de paletas para embarcaciones',
        1092 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Piezas de motor de embarcaciones > Ruedas de paletas para embarcaciones',
        1104 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        1111 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        1120 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        1123 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones',
        1138 => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Atracada y anclaje > Escaleras para muelle',
    ];

    // Fallback (anadido 2026-06-15): evita google_product_category vacio (era ~16.5%, 6027 prods).
    if (isset($categories[$category_id])) {
        return $categories[$category_id];
    }
    // 2do nivel: por categoria TOP del arbol del producto (idioma del feed).
    $lang = isset($GLOBALS['cur_feed']['language_id']) ? (int)$GLOBALS['cur_feed']['language_id'] : 3;
    $path = $GLOBALS['categories'][$product['categories_id']][$lang] ?? [];
    $top  = (is_array($path) && count($path)) ? trim((string)$path[0]) : '';
    $top_map = [
        'Electronica'              => 'Electrónica > Accesorios de electrónica náutica',
        'Motores'                  => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Motores y engranajes para embarcaciones',
        'Submarinismo'             => 'Equipamiento deportivo > Deportes acuáticos > Buceo y buceo con esnórquel',
        'Motonautica'              => 'Equipamiento deportivo > Deportes acuáticos',
        'Deportes Nauticos'        => 'Equipamiento deportivo > Deportes acuáticos',
        'Kayaks'                   => 'Equipamiento deportivo > Deportes acuáticos > Embarcación > Kayaking',
        'Mantenimiento y Limpieza' => 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones > Cuidado para embarcaciones',
    ];
    if (isset($top_map[$top])) {
        return $top_map[$top];
    }
    // Por defecto (Francobordo es 100% nautica): accesorios para embarcaciones.
    return 'Vehículos y recambios > Piezas y accesorios para vehículos > Accesorios y piezas para embarcaciones';
}

function FM_RS_shipping_label($product) {
	return ($product['products_ship_free'] == '1' ? 'freeshipping' : '');
}

function FM_RS_google_condition_us_en_wa($product) //change the name to a unique name for each language and country
{
	return (($product['products_condition'] == 'New') ? 'new' : //change to the terms used in your shop for the feed-language
        (($product['products_condition'] == 'Used') ? 'used' : (($product['products_condition'] == 'Overhauled') ? 'refurbished' : '')));
}

function FM_RS_google_gender_us_en_wa($product) //change the name to a unique name for each language and country
{
    return (($product['products_gender'] == 'Woman') ? 'female' : //change terms to the terms you use for each language in your shop
        (($product['products_gender'] == 'Men') ? 'male' : (($product['products_gender'] == 'Unisex') ? 'unisex' : '')));
}

function FM_RS_google_age_group_us_en_wa($product) //change the name to a unique name for each language and country
{
    return (($product['products_age_group'] == 'Adult') ? 'adult' : //change terms to the terms you use for each language in your shop
        (($product['products_age_group'] == 'Kids') ? 'kids' : ''));
}

function FM_RS_google_colors_us_en_wa($product) //change the name to a unique name for each language and country
{
    $color_query = tep_db_query("select pov.products_options_values_name as color
                from products_attributes as pa,
                products_options_values as pov,
                products_options as po
                where  pa.options_id = po. products_options_id
                and pa.options_values_id = pov.products_options_values_id
                and po.language_id = 1 and pov.language_id = 1
                and pa.options_id = 1
                AND pa.products_id = '" . $product['products_id'] . "'");
	while ($color = tep_db_fetch_array($color_query)) {
		$colors .= $color["color"] . ", ";
	}
	$replace_color = [
		'01-',
		'02-',
		'03-',
		'04-',
		'05-',
		'06-',
        '07-',
        '08-',
        '09-',
        '10-',
        '11-',
        '12-',
        '1-',
        '2-',
		'3-',
		'4-',
		'5-',
		'6-',
		'A-',
		'B-',
		'C-',
	];
	$colors        = str_replace($replace_color, '', $colors);
	$colors        = rtrim($colors, ", "); //this strips the last comma and white space
	(($colors == '') ? $colors = $product['products_color'] : '');
	return $colors;
}

function FM_RS_google_sizes_us_en_wa($product) //change the name to a unique name for each language and country
{
    $size_query = tep_db_query("select pov.products_options_values_name as size
                from products_attributes as pa,
                products_options_values as pov,
                products_options as po
                where  pa.options_id = po. products_options_id
                and pa.options_values_id = pov.products_options_values_id
                and po.language_id = 1 and pov.language_id = 1
                and pa.options_id = 2
                AND pa.products_id = ¡" . $product['products_id'] . "'");
	while ($size = tep_db_fetch_array($size_query)) {
		$sizes .= $size["size"] . ", ";
	}
	$replace_size = [
		'01-',
		'02-',
		'03-',
		'04-',
		'05-',
		'06-',
        '07-',
        '08-',
        '09-',
        '10-',
        '11-',
        '12-',
        '1-',
        '2-',
		'3-',
		'4-',
		'5-',
		'6-',
		'A-',
		'B-',
		'C-',
	];
	$sizes        = str_replace($replace_size, '', $sizes);
	$sizes        = rtrim($sizes, ", "); //this strips the last comma and white space
	(($sizes == '') ? $sizes = $product['products_size'] : '');
	return $sizes;
}

function FM_RS_final_price_with_tax_es_sp_wa($product) {

	$sql            = "SELECT specials_new_products_price from specials where products_id = '" . $product['products_id'] . "' AND status = '1' limit 1";
	$special_query  = tep_db_query($sql);
	$special_result = tep_db_fetch_array($special_query);
	if ($special_result['specials_new_products_price'] > 0) {
		$price = $special_result['specials_new_products_price'];
    } else {
		$price =$product['final_price'];
    }

	$price = round(($price) * (1 + ((tep_get_tax_rate($product['products_tax_class_id']) / 100))), 2);
    return $price . ' EUR';

}
//FEED FUNCTIONS END

