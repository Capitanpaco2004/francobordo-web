<?php

// Libreria oscommerce
include 'includes/application_top.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', -1);

// Incluimos la configuracion de phplist
include getcwd() . '/lists/config/config.php';

// Funciones
function phplist_suscribir($sEmail)
{
    global $dbConnect;

    // Comprobamos si existe
    $dbResult = $dbConnect->query('SELECT id FROM phplist_user_user WHERE email = "' . $sEmail . '"');

    // Si existe suscribo
    if ($dbResult->num_rows > 0) {
        $dbConnect->query('UPDATE phplist_user_user set blacklisted = 0 where email = "' . $sEmail . '"');
        $dbConnect->query('DELETE FROM phplist_user_blacklist where email = "' . $sEmail . '"');
        $dbConnect->query('DELETE FROM phplist_user_blacklist_data where email = "' . $sEmail . '"');
    } else // Si no existe inserto
    {
        // Obtenemos el uniqid
        $sUniQid = md5(uniqid(mt_rand()));
        $dbResult = $dbConnect->query('SELECT id FROM phplist_user_user WHERE uniqid = "' . $sUniQid . '"');
        while ($dbResult->num_rows > 0) {
            $sUniQid = md5(uniqid(mt_rand()));
            $dbResult = $dbConnect->query('SELECT id FROM phplist_user_user WHERE uniqid = "' . $sUniQid . '"');
        }

        // Insertamos
        $dbConnect->query('INSERT INTO phplist_user_user (email, confirmed, blacklisted, bouncecount, entered, modified, uniqid, htmlemail, subscribepage, rssfrequency, password, passwordchanged, disabled, extradata, foreignkey, os_customers_id, os_customers_info_date_of_last_logon, optedin) VALUES ("' . $sEmail . '", 1, 0, 0, "' . date('Y-m-d H:i:s') . '", "' . date('Y-m-d H:i:s') . '", "' . $sUniQid . '", 1, 1, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 1)');
    }
}

function phplist_desuscribir($sEmail)
{
    global $dbConnect;

    // Comprobamos que exista y que esta suscrito
    $dbResult = $dbConnect->query('SELECT id FROM phplist_user_user WHERE blacklisted = 0 AND email = "' . $sEmail . '"');

    // Si existe, desuscribo
    if ($dbResult->num_rows > 0) {
        $dbConnect->query('UPDATE phplist_user_user set blacklisted = 1 where email = "' . $sEmail . '"');
        $dbConnect->query('INSERT INTO phplist_user_blacklist (email, added) VALUES ("' . $sEmail . '", "' . date('Y-m-d H:i:s') . '")');
        $dbConnect->query('INSERT INTO phplist_user_blacklist_data (email, name) VALUES ("' . $sEmail . '", "")');
    }
}

// Variables
$aCustomersOscommerce = array();
$aCustomersPhpList = array();
$sLog = '<a href="#add">Añadidos:</a> %s<br/><a href="#delete">Eliminados</a>: %s<br/><br/>--------------------------------------<br/><span id="add">Añadidos</span><br/>--------------------------------------<br/>%s<br/><br/><br/><br/>--------------------------------------<br/><span id="delete">Eliminados</span><br/>--------------------------------------<br/>%s';
$nContAdd = 0;
$nContDelete = 0;
$sEmailAdd = '';
$sEmailDelete = '';

// Conectamos con la base de datos
$dbConnect = new mysqli($database_host, $database_user, $database_password, $database_name);
$dbConnect->query("SET CHARACTER SET utf8");
$dbConnect->query("SET NAMES utf8");

// Obtenemos todos los emails de oscommerce y si esta o no suscrito
$aDatos = tep_db_query('SELECT customers_email_address, customers_newsletter FROM customers');

while ($aDato = tep_db_fetch_array($aDatos)) {
    $aCustomersOscommerce[strtolower($aDato['customers_email_address'])] = ($aDato['customers_newsletter'] == "1" ? true : false);
}

// Obtenemos todos los emails de phplist y si esta o no suscrito
$dbResult = $dbConnect->query('SELECT email, blacklisted FROM phplist_user_user');

while ($aDato = $dbResult->fetch_array(MYSQLI_ASSOC)) {
    $aCustomersPhpList[strtolower($aDato['email'])] = ($aDato['blacklisted'] == "1" ? false : true);
}

// Recorremos los clientes de oscommerce
foreach ($aCustomersOscommerce as $sEmail => $bSuscrito) {
    // EL CLIENTE NO EXISTE EN PHPLIST Y EN LA TIENDA ONLINE ESTA SUSCRITO --> SUSCRIBIRLO
    if (!array_key_exists($sEmail, $aCustomersPhpList) && $bSuscrito) {
        // Suscribir
        phplist_suscribir($sEmail);

        // Log
        $sEmailAdd .= $sEmail . '<br/>';
        $nContAdd++;

        // Continuamos
        continue;
    }

    // EL CLIENTE EXISTE EN PHPLIST (ESTA SUSCRITO) Y EN LA TIENDA ONLINE ESTA DESUSCRITO --> LO DESUSCRIBO DEL PHPLIS
    if (array_key_exists($sEmail, $aCustomersPhpList) && $aCustomersPhpList[$sEmail] && !$bSuscrito) {
        // Desuscribir
        phplist_desuscribir($sEmail);

        // Log
        $sEmailDelete .= $sEmail . '<br/>';
        $nContDelete++;

        // Continuamos
        continue;
    }

    // EL CLIENTE EXISTE EN PHP LIST (ESTA SUSCRITO) Y EN LA TIENDA ONLINE ESTA SUSCRITO --> PASO DE EL

    // EL CLIENTE EXISTE EN PHPLIST (ESTA DESUSCRITO) Y EN LA TIENDA ONLINE ESTA SUSCRITO/DESUSCRITO --> PASO DE EL TAMBIEN, SI ESTA DESUSCRITO EN PHPLIST NO HAY MAS NA QUE HACER
}

echo sprintf($sLog, $nContAdd, $nContDelete, $sEmailAdd, $sEmailDelete);
