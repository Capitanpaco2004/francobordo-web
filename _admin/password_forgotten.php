<?php
	// Librerias
	use util\tools;

	// Librerias
    require('includes/application_top.php');
    require(DIR_WS_LANGUAGES . $language . '/' . FILENAME_LOGIN);

    // Si nos manda email
    if( isset($_GET['action']) && ($_GET['action'] == 'process' ) )
    {
        $email_address = tep_db_prepare_input($_POST['email_address']);
        $log_times = $_POST['log_times']+1;

        if( $log_times >= 4 )
            tep_session_register('password_forgotten');

        // Check if email exists
        $check_admin_query = tep_db_query("select admin_id as check_id, admin_firstname as check_firstname, admin_lastname as check_lastname, admin_email_address as check_email_address from " . TABLE_ADMIN . " where admin_email_address = '" . tep_db_input($email_address) . "'");
        if( !tep_db_num_rows($check_admin_query) )
            $_GET['login'] = 'fail';
        else
        {
            $check_admin = tep_db_fetch_array($check_admin_query);
            $_GET['login'] = 'success';

            // Random value
            $randomValue = tools::createRandomValue(40);

			// Actualizamos
            tep_db_query("update " . TABLE_ADMIN . " set password_reset_key = '" . tep_db_input($randomValue) . "', password_reset_date = now() where admin_id = '" . (int)$check_admin['check_id'] . "'");

            // Url reset
            $url = tep_href_link('password_reset.php', 'account=' . urlencode($email_address) . '&key=' . $randomValue);

            // Mandamos email
			tep_mail( $check_admin['check_firstname'] . ' ' . $check_admin['check_lastname'], $check_admin['check_email_address'], sprintf(ADMIN_EMAIL_RESET_SUBJECT, STORE_NAME, $check_admin['check_firstname'], $check_admin['check_lastname']), sprintf(ADMIN_EMAIL_RESET_TEXT, STORE_NAME, $url, $url), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
    }

    include( 'theme/solenopsis/html/header.php' );

	# Messagestack estilo
	$messageStack->style = 'solenopsis';

    if( $_GET['login'] == 'success')
        $success_message = TEXT_FORGOTTEN_RESET_SUCCESS;
    elseif( $_GET['login'] == 'fail' )
        $info_message = TEXT_FORGOTTEN_ERROR;

    if( tep_session_is_registered('password_forgotten') )
		$info_message = TEXT_FORGOTTEN_FAIL;

	if( isset( $success_message ) )
		echo $messageStack->show( array('class' => 'success', 'text' => $success_message) );

	if( isset( $info_message ) )
		echo $messageStack->show( array('class' => 'error', 'text' => $info_message) );
?>

<a href="https://www.francobordo.com/" id="logn-denox"></a>
<form method="post" action="<?php echo tep_href_link( FILENAME_PASSWORD_FORGOTTEN, 'action=process' ); ?>" id="logn">
	<?php
		if( isset( $info_message ) )
			echo tep_draw_hidden_field( 'log_times', $log_times );
		else
			echo tep_draw_hidden_field( 'log_times', '0' );
	?>
	<div id="logn-msct"></div>
	<div id="logn-titu">Recuperar contrase&ntilde;a</div>
	<?php echo tep_draw_input_field( 'firstname', '', 'id="logn-email" placeholder="Nombre"' ); ?>
	<?php echo tep_draw_input_field( 'email_address', '', 'id="logn-pasw" placeholder="Email"' ); ?>
	<a title="Volver al login" href="<?php echo tep_href_link(FILENAME_LOGIN, '', 'SSL'); ?>" id="logn-olvd">Volver al login</a>
	<button id="logn-butn" class="bton-dflt logn-acdr" type="submit" name="enviar">Enviar recuperaci&oacute;n</button>
</form>
</body>
</html>
