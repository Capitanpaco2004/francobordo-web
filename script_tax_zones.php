<?php

include('includes/application_top.php');

// Reportamos todos los errores PHP
error_reporting( E_ALL );
ini_set( 'display_errors', 'On' );

define('TAX_CLASS_ID_REGULAR', 1);
define('TAX_CLASS_ID_REDUCED', 3);
define('TAX_CLASS_ID_SUPER_REDUCED', 2);


$aux = tep_db_query('SELECT MAX(geo_zone_id) AS max_geo_zone_id FROM geo_zones;');
$aux = tep_db_fetch_array($aux);
$maxGeoZoneID = $aux['max_geo_zone_id'];

$zones = array();

$zones[] = array(
	'id' => NULL,
	'title' => 'España IVA',
	'description' => 'Zonas de España con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 21.00, 'recargo' => 5.20, 'description' => 'IVA 21%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 10.00, 'recargo' => 1.40, 'description' => 'IVA 10%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 4.00, 'recargo' => 0.50, 'description' => 'IVA 4%')
	),
	'zones' => array(
		array('country_id' => 195, 'geo_zone_id' => 130),
		array('country_id' => 195, 'geo_zone_id' => 131),
		array('country_id' => 195, 'geo_zone_id' => 132),
		array('country_id' => 195, 'geo_zone_id' => 133),
		array('country_id' => 195, 'geo_zone_id' => 134),
		array('country_id' => 195, 'geo_zone_id' => 135),
		array('country_id' => 195, 'geo_zone_id' => 136),
		array('country_id' => 195, 'geo_zone_id' => 137),
		array('country_id' => 195, 'geo_zone_id' => 138),
		array('country_id' => 195, 'geo_zone_id' => 139),
		array('country_id' => 195, 'geo_zone_id' => 140),
		array('country_id' => 195, 'geo_zone_id' => 141),
		array('country_id' => 195, 'geo_zone_id' => 142),
		array('country_id' => 195, 'geo_zone_id' => 143),
		array('country_id' => 195, 'geo_zone_id' => 144),
		array('country_id' => 195, 'geo_zone_id' => 146),
		array('country_id' => 195, 'geo_zone_id' => 147),
		array('country_id' => 195, 'geo_zone_id' => 148),
		array('country_id' => 195, 'geo_zone_id' => 149),
		array('country_id' => 195, 'geo_zone_id' => 150),
		array('country_id' => 195, 'geo_zone_id' => 151),
		array('country_id' => 195, 'geo_zone_id' => 152),
		array('country_id' => 195, 'geo_zone_id' => 153),
		array('country_id' => 195, 'geo_zone_id' => 154),
		array('country_id' => 195, 'geo_zone_id' => 155),
		array('country_id' => 195, 'geo_zone_id' => 156),
		array('country_id' => 195, 'geo_zone_id' => 158),
		array('country_id' => 195, 'geo_zone_id' => 159),
		array('country_id' => 195, 'geo_zone_id' => 160),
		array('country_id' => 195, 'geo_zone_id' => 161),
		array('country_id' => 195, 'geo_zone_id' => 162),
		array('country_id' => 195, 'geo_zone_id' => 164),
		array('country_id' => 195, 'geo_zone_id' => 165),
		array('country_id' => 195, 'geo_zone_id' => 166),
		array('country_id' => 195, 'geo_zone_id' => 167),
		array('country_id' => 195, 'geo_zone_id' => 168),
		array('country_id' => 195, 'geo_zone_id' => 169),
		array('country_id' => 195, 'geo_zone_id' => 171),
		array('country_id' => 195, 'geo_zone_id' => 172),
		array('country_id' => 195, 'geo_zone_id' => 173),
		array('country_id' => 195, 'geo_zone_id' => 174),
		array('country_id' => 195, 'geo_zone_id' => 175),
		array('country_id' => 195, 'geo_zone_id' => 176),
		array('country_id' => 195, 'geo_zone_id' => 177),
		array('country_id' => 195, 'geo_zone_id' => 178),
		array('country_id' => 195, 'geo_zone_id' => 179),
		array('country_id' => 195, 'geo_zone_id' => 180),
		array('country_id' => 195, 'geo_zone_id' => 181)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Austria IVA',
	'description' => 'Zonas de Austria con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 20.00, 'recargo' => 0, 'description' => 'IVA 20%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 13.00, 'recargo' => 0, 'description' => 'IVA 13%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 10.00, 'recargo' => 0, 'description' => 'IVA 10%')
	),
	'zones' => array(
		array('country_id' => 14, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Bélgica IVA',
	'description' => 'Zonas de Bélgica con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 21.00, 'recargo' => 0, 'description' => 'IVA 21%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 12.00, 'recargo' => 0, 'description' => 'IVA 12%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 6.00, 'recargo' => 0, 'description' => 'IVA 6%')
	),
	'zones' => array(
		array('country_id' => 21, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Bulgaria IVA',
	'description' => 'Zonas de Bulgaria con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 20.00, 'recargo' => 0, 'description' => 'IVA 20%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%')
	),
	'zones' => array(
		array('country_id' => 33, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Croacia IVA',
	'description' => 'Zonas de Croacia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 25.00, 'recargo' => 0, 'description' => 'IVA 25%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 13.00, 'recargo' => 0, 'description' => 'IVA 13%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 53, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Chipre IVA',
	'description' => 'Zonas de Chipre con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 19.00, 'recargo' => 0, 'description' => 'IVA 19%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 55, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'República Checa IVA',
	'description' => 'Zonas de República Checa con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 21.00, 'recargo' => 0, 'description' => 'IVA 21%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 15.00, 'recargo' => 0, 'description' => 'IVA 15%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 10.00, 'recargo' => 0, 'description' => 'IVA 10%')
	),
	'zones' => array(
		array('country_id' => 56, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Dinamarca IVA',
	'description' => 'Zonas de Dinamarca con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 25.00, 'recargo' => 0, 'description' => 'IVA 25%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 25.00, 'recargo' => 0, 'description' => 'IVA 25%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 25.00, 'recargo' => 0, 'description' => 'IVA 25%')
	),
	'zones' => array(
		array('country_id' => 57, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Estonia IVA',
	'description' => 'Zonas de Estonia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 20.00, 'recargo' => 0, 'description' => 'IVA 20%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%')
	),
	'zones' => array(
		array('country_id' => 67, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Finlandia IVA',
	'description' => 'Zonas de Finlandia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 24.00, 'recargo' => 0, 'description' => 'IVA 24%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 14.00, 'recargo' => 0, 'description' => 'IVA 14%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 10.00, 'recargo' => 0, 'description' => 'IVA 10%')
	),
	'zones' => array(
		array('country_id' => 72, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Francia IVA',
	'description' => 'Zonas de Francia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 20.00, 'recargo' => 0, 'description' => 'IVA 20%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 10.00, 'recargo' => 0, 'description' => 'IVA 10%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 73, 'geo_zone_id' => 0),
		array('country_id' => 74, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Alemania IVA',
	'description' => 'Zonas de Alemania con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 19.00, 'recargo' => 0, 'description' => 'IVA 19%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 7.00, 'recargo' => 0, 'description' => 'IVA 7%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 7.00, 'recargo' => 0, 'description' => 'IVA 7%')
	),
	'zones' => array(
		array('country_id' => 81, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Grecia IVA',
	'description' => 'Zonas de Grecia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 24.00, 'recargo' => 0, 'description' => 'IVA 24%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 13.00, 'recargo' => 0, 'description' => 'IVA 13%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 6.00, 'recargo' => 0, 'description' => 'IVA 6%')
	),
	'zones' => array(
		array('country_id' => 84, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Hungría IVA',
	'description' => 'Zonas de Hungría con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 27.00, 'recargo' => 0, 'description' => 'IVA 27%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 18.00, 'recargo' => 0, 'description' => 'IVA 18%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 97, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Irlanda IVA',
	'description' => 'Zonas de Irlanda con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 23.00, 'recargo' => 0, 'description' => 'IVA 23%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 13.00, 'recargo' => 0, 'description' => 'IVA 13%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%')
	),
	'zones' => array(
		array('country_id' => 103, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Italia IVA',
	'description' => 'Zonas de Italia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 22.00, 'recargo' => 0, 'description' => 'IVA 22%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 10.00, 'recargo' => 0, 'description' => 'IVA 10%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 4.00, 'recargo' => 0, 'description' => 'IVA 4%')
	),
	'zones' => array(
		array('country_id' => 105, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Letonia IVA',
	'description' => 'Zonas de Letonia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 21.00, 'recargo' => 0, 'description' => 'IVA 21%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 12.00, 'recargo' => 0, 'description' => 'IVA 12%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 117, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Lituania IVA',
	'description' => 'Zonas de Lituania con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 21.00, 'recargo' => 0, 'description' => 'IVA 21%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 123, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Luxemburgo IVA',
	'description' => 'Zonas de Luxemburgo con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 17.00, 'recargo' => 0, 'description' => 'IVA 17%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 14.00, 'recargo' => 0, 'description' => 'IVA 14%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 8.00, 'recargo' => 0, 'description' => 'IVA 8%')
	),
	'zones' => array(
		array('country_id' => 124, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Malta IVA',
	'description' => 'Zonas de Malta con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 18.00, 'recargo' => 0, 'description' => 'IVA 18%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 7.00, 'recargo' => 0, 'description' => 'IVA 7%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 132, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Holanda IVA',
	'description' => 'Zonas de Holanda con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 21.00, 'recargo' => 0, 'description' => 'IVA 21%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%')
	),
	'zones' => array(
		array('country_id' => 150, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Polonia IVA',
	'description' => 'Zonas de Polonia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 23.00, 'recargo' => 0, 'description' => 'IVA 23%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 8.00, 'recargo' => 0, 'description' => 'IVA 8%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 170, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Portugal IVA',
	'description' => 'Zonas de Portugal con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 23.00, 'recargo' => 0, 'description' => 'IVA 23%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 13.00, 'recargo' => 0, 'description' => 'IVA 13%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 6.00, 'recargo' => 0, 'description' => 'IVA 6%')
	),
	'zones' => array(
		array('country_id' => 171, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Rumania IVA',
	'description' => 'Zonas de Rumania con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 19.00, 'recargo' => 0, 'description' => 'IVA 19%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 9.00, 'recargo' => 0, 'description' => 'IVA 9%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 175, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Eslovaquia IVA',
	'description' => 'Zonas de Eslovaquia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 20.00, 'recargo' => 0, 'description' => 'IVA 20%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 10.00, 'recargo' => 0, 'description' => 'IVA 10%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 10.00, 'recargo' => 0, 'description' => 'IVA 10%')
	),
	'zones' => array(
		array('country_id' => 189, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Eslovenia IVA',
	'description' => 'Zonas de Eslovenia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 22.00, 'recargo' => 0, 'description' => 'IVA 22%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 9.50, 'recargo' => 0, 'description' => 'IVA 9,5%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 9.50, 'recargo' => 0, 'description' => 'IVA 9,5%')
	),
	'zones' => array(
		array('country_id' => 190, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Reino Unido IVA',
	'description' => 'Zonas de Reino Unido con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 25.00, 'recargo' => 0, 'description' => 'IVA 25%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 12.00, 'recargo' => 0, 'description' => 'IVA 12%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 6.00, 'recargo' => 0, 'description' => 'IVA 6%')
	),
	'zones' => array(
		array('country_id' => 222, 'geo_zone_id' => 0)
	)
);

$zones[] = array(
	'id' => NULL,
	'title' => 'Suecia IVA',
	'description' => 'Zonas de Suecia con Impuestos',
	'tax' => array(
		array('class_id' => TAX_CLASS_ID_REGULAR, 'priority' => '1', 'rate' => 20.00, 'recargo' => 0, 'description' => 'IVA 20%'),
		array('class_id' => TAX_CLASS_ID_REDUCED, 'priority' => '2', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%'),
		array('class_id' => TAX_CLASS_ID_SUPER_REDUCED, 'priority' => '3', 'rate' => 5.00, 'recargo' => 0, 'description' => 'IVA 5%')
	),
	'zones' => array(
		array('country_id' => 203, 'geo_zone_id' => 0)
	)
);

foreach ($zones as $zone) {
	// Tabla geo_zones
	$insert = array();
	$insert['geo_zone_name'] = $zone['title'];
	$insert['geo_zone_description'] = $zone['description'];
	$insert['geo_zones_type_id'] = 2;
	$insert['date_added'] = date('Y-m-d H:i:s');
	tep_db_perform('geo_zones', $insert);

	$geoZoneID = tep_db_insert_id();

	// Tabla tax_rates
	foreach ($zone['tax'] as $tax) {
		$insert = array();
		$insert['tax_zone_id'] = $geoZoneID;
		$insert['tax_class_id'] = $tax['class_id'];
		$insert['tax_priority'] = $tax['priority'];
		$insert['tax_rate'] = $tax['rate'];
		$insert['tax_recargo'] = $tax['recargo'];
		$insert['tax_description'] = $tax['description'];
		$insert['date_added'] = date('Y-m-d H:i:s');
		tep_db_perform('tax_rates', $insert);
	}

	// Tabla zones_to_geo_zones
	foreach ($zone['zones'] as $geo) {
		$insert = array();
		$insert['zone_country_id'] = $geo['country_id'];
		$insert['zone_id'] = $geo['geo_zone_id'];
		$insert['geo_zone_id'] = $geoZoneID;
		$insert['date_added'] = date('Y-m-d H:i:s');
		tep_db_perform('zones_to_geo_zones', $insert);
	}
}

?>