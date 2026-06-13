<?php
// Cambiamos el modulo por login para que forbidden no salte y podamos mostrar la página
/*$_SERVER['PHP_SELF'] = 'login.php';
$_SERVER['SCRIPT_FILENAME'] = 'login.php';
$_SERVER['SCRIPT_NAME'] = str_replace('password_reset.php', 'login.php', $_SERVER['SCRIPT_NAME']);
*/
// Librerias
require 'includes/application_top.php';
require DIR_WS_LANGUAGES . $language . '/' . FILENAME_LOGIN;

# Messagestack estilo
$messageStack->style = 'solenopsis';

// Variables
$error = false;

// Si no nos envian nada
if (!isset($_GET['account']) || !isset($_GET['key'])) {
    $error = true;
    $messageStack->add_session('password_forgotten', TEXT_NO_RESET_LINK_FOUND);
}

// Si no tenemos ningun error
if ($error == false) {
    // Variables
    $email_address = tep_db_prepare_input($_GET['account']);
    $password_key = tep_db_prepare_input($_GET['key']);

    // Consultamos
    $check_admin_query = tep_db_query("select admin_id, admin_firstname, admin_lastname, admin_email_address, password_reset_key, password_reset_date from " . TABLE_ADMIN . " where admin_email_address = '" . tep_db_input($email_address) . "'");

    // Si existe
    if (tep_db_num_rows($check_admin_query)) {
        // Obtenemos
        $check_admin = tep_db_fetch_array($check_admin_query);

        // Si tiene algun fallo
        if (empty($check_admin['password_reset_key']) || ($check_admin['password_reset_key'] != $password_key) || (strtotime($check_admin['password_reset_date'] . ' +1 day') <= time())) {
            $error = true;
            $messageStack->add_session('password_forgotten', TEXT_NO_RESET_LINK_FOUND);
        }
    } else {
        $error = true;
        $messageStack->add_session('password_forgotten', TEXT_NO_EMAIL_ADDRESS_FOUND);
    }
}

// Si contiene errores
if ($error == true) {
    tep_redirect(tep_href_link(FILENAME_PASSWORD_FORGOTTEN));
}

// Si nos envian a cambiarla
if (isset($_POST['action']) && ($_POST['action'] == 'process')) {
    $password_new = tep_db_prepare_input($_POST['password']);
    $password_confirmation = tep_db_prepare_input($_POST['confirmation']);

    // Comprobamos que la contraseña tenga el tamaño mínimo, letras minúscula, mayúsculas y números
    if (strlen($password_new) < ENTRY_PASSWORD_MIN_LENGTH || !preg_match('/[A-Z]/', $password_new) || !preg_match('/[a-z]/', $password_new) || !preg_match('/[0-9]/', $password_new)) {
        // Error y mensaje
        $error = true;
        $messageStack->add(ENTRY_PASSWORD_NEW_ERROR);
    }
    // Si está OK pero no coincide con confirmación
    elseif( $password_new != $password_confirmation )
    {
	    // Error y mensaje
	    $error = true;
	    $messageStack->add(ENTRY_PASSWORD_NEW_ERROR_NOT_MATCHING);
    }

    // Actualizamos
    if ($error == false) {
        tep_db_query("update " . TABLE_ADMIN . " set admin_password = '" . tep_encrypt_password($password_new) . "', admin_modified = now(), password_reset_key = null, password_reset_date = null where admin_id = '" . (int) $check_admin['admin_id'] . "'");
        $messageStack->addSession('login', SUCCESS_PASSWORD_RESET, 'success');
        tep_redirect(tep_href_link(FILENAME_LOGIN_ADMIN, '', 'SSL'));
    }
}


include( 'theme/solenopsis/html/header.php' );
echo $messageStack->output();
echo $messageStack->show(array('class' => 'warning', 'text' => TEXT_MAIN_RESET));
?>
		<a href="https://www.denox.es/" id="logn-denox"></a>
        <form method="post" action="<?php echo tep_href_link('password_reset.php', 'account=' . $email_address . '&key=' . $password_key); ?>" id="logn">
            <div id="logn-msct" class="msct1"></div>
            <a href="<?php echo FILENAME_LOGIN; ?>" title="Volver" id="logn-vlvr"></a>
            <input type="hidden" name="action" value="process">
            <input type="password" name="password" value="" id="logn-pasw" placeholder="Introduce contraseña">
            <input type="password" name="confirmation" value="" id="logn-cnfr" placeholder="Repite contraseña">
			<a title="Iniciar sesión" href="<?php echo tep_href_link( FILENAME_DEFAULT, '', 'SSL' ); ?>" id="logn-olvd">Iniciar sesión</a>
            <button id="logn-butn" class="bton-dflt" type="submit" name="enviar">Acceder</button>
        </form>
    </body>
</html>
