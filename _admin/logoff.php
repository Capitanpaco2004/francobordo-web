<?php
  require('includes/application_top.php');

  $adminCore->logoff();

  include( 'theme/solenopsis/html/header.php' );
?>

		<a href="https://www.denox.es/" id="logn-denox"></a>
        <div id="logn">
			<div id="logn-msct"></div>
			<div id="logn-titu">Panel de administración</div>
			<p style="display: block; margin-bottom: 60px; padding: 0px 10px;">Has salido del área de Administración. Puedes hacer click sobre "acceder" para entrar de nuevo</p>
			<a id="logn-butn" href="<?php echo tep_href_link(FILENAME_LOGIN, '', 'SSL'); ?>" class="bton-dflt g-recaptcha" class="logn-acdr" type="submit" name="enviar">acceder</a>
        </div>
    </body>
</html>
