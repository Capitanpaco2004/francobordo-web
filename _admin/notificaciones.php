<?php
/*
Para generar el archivo secreto alojado en includes/modules/notificaciones/

Abrir consola de firebase
Elige proyecto
CLick en la rueda dentada > Configuración del proyecto
Cuentas de servicio
Generar nueva clave privada

 */
include 'includes/application_top.php';
include 'includes/modules/notificaciones/functions.php';

//Comprobamos instalación
include 'includes/modules/notificaciones/install.php';

//Comprobamos acciones
$accion = tep_db_prepare_input($_GET['action']);
switch ($accion) {
    case 'remove-notification':
        $id = (int) $_GET['id'];
        $sSql = 'DELETE FROM notificaciones WHERE id = ' . $id;
        tep_db_query($sSql);
        tep_redirect(tep_href_link('notificaciones.php'));
        break;

    case 'add-notification':
        enviarNotificaciones();
        break;

    case 'uninstall':
        uninstallNotificaciones();
        tep_redirect(tep_href_link('notificaciones.php'));
        break;
    case 'sandbox':
        sendSandboxNotification(
			[
				'dff1bRj8SZ0:APA91bFyAlOHRWt61e42WRL0cSANJXBQeGMHy1sUSYqfZnudo2cspyJ9gs-hNlh9xXB-Sg7QiQYHRy7BNLGhLPyQ--C9Q8gbr1vL1eFxWTg7fhIv8Gu2OTOthGC-CASg6Tnjp09K3aIF',
				'coUCjcu8Y0A:APA91bF_72dtAoR8rcKwEYQaqH67QK1lGP4oEBKC2-eWGHUghm-lUhq5Vj6BbSEUnydx8PceAKPoFCL7q3sRHhcwpmKLTzDp1uYJtKFX7cb7sbsaKyJ_65Unzh_QcEaagTpgy-_0O9gs',
			]
		);
        tep_redirect(tep_href_link('notificaciones.php'));
        break;

    default:
        $notificaciones = getNotificaciones();
        break;
}

include THEME . 'html/header.php';
?>

<div id="groupColor" class="groupColorContent allWidth">
<div id="Image" class="groupColorColumn">
	<div class="box-tbl" style="width: 100%">
		<div class="box-head">
			<h6>Histórico de notificaciones</h6>
			<div class="clear"></div>
		</div>
        <?php if (!empty($notificaciones)): ?>
    		<table class="tAlt wGeneral tDefault" width="100%" cellspacing="0" cellpadding="0">
    			<thead>
    				<tr>
                        <td style="text-align: left;" width="50">

    					</td>
    					<td style="text-align: left;" width="200">
    						Título
    					</td>
    					<td style="text-align: left;">
    						Texto
    					</td>
                        <td style="text-align: left;">
    						Enlace
    					</td>
                        <td style="text-align: center;">
    						Correctas
    					</td>
                        <td style="text-align: center;">
    						Fallidas
    					</td>
                        <td style="text-align: center;">
    						Clicks
    					</td>
                        <td style="text-align: center;">
    						Ratio
    					</td>
    					<td>
    					</td>
    				</tr>
    			</thead>
    			<tbody>
    				<?php foreach ($notificaciones as $notificacion): ?>
    					<tr>
                            <td style="text-align: left;">
    							<img src="<?php echo ($notificacion['image'] != '' ? $notificacion['image'] : tep_catalog_href_link('theme/web/images/general/notificaciones.jpg')); ?>" style="height: auto; width: 40px;" />
    						</td>
    						<td style="text-align: left;">
    							<?php echo $notificacion['title']; ?><br /><small style="font-size: 0.8em;"><?php echo $notificacion['id_notificacion']; ?></small>
    						</td>
    						<td style="text-align: left;">
    							<?php echo $notificacion['text']; ?>
    						</td>
                            <td style="text-align: left;">
    							<a href="<?php echo $notificacion['url']; ?>" target="_blank"><?php echo $notificacion['url']; ?></a>
    						</td>

                            <td style="text-align: center;">
    	                      <?php echo $notificacion['success']; ?>
    						</td>
                            <td style="text-align: center;">
    							<?php echo $notificacion['failure']; ?>
    						</td>
                            <td style="text-align: center;">
    							<?php echo $notificacion['total']; ?>
    						</td>
                            <td style="text-align: center;">
    							<?php echo number_format($notificacion['ratio'], 2); ?>%
    						</td>
                            <td style="text-align: right; width: 100px;">
    							<a href="<?php echo tep_href_link('notificaciones.php', 'action=remove-notification&id=' . $notificacion['id']); ?>" class="buttonS bRed borrarElemento">Borrar</a>
    						</td>
    					</tr>
    				<?php endforeach;?>
    			</tbody>
    		</table>
        <?php else: ?>
            <div class="msje msje-wrng"><div class="msje-icon"></div>Vaya... parece que aún no se ha enviado ninguna notificación</div>
        <?php endif;?>
	</div>

</div>
<div class="groupColorColumn">
	<div class="box-tbl" style="width: 100%">
		<div class="box-head">
			<h6>Crear nueva notificación</h6>
			<div class="clear"></div>
		</div>
		<form method="post" action="<?php echo tep_href_link('notificaciones.php', 'action=add-notification'); ?>" enctype="multipart/form-data">
            <p class="flexForm">
				<input type="file" name="image"  placeholder="Imagen"/>
			</p>
            <p>Imagen por defecto: <img src="<?php echo tep_catalog_href_link('theme/web/images/general/notificaciones.jpg'); ?>" style="height: auto; width: 40px;" /></p>
            <p class="flexForm">
				<input type="text" required name="title"  placeholder="Título"/>
			</p>
            <p class="flexForm">
				<input type="text" required name="url"  placeholder="Enlace"/>
			</p>
			<p class="flexForm">
				<textarea name="text" placeholder="Texto"></textarea>
			</p>
            <p class="flexForm">
                <?php $segmentos = getSegmentos();?>
				<label>Enviar a</label>
                <select name="segmento">
                    <?php foreach ($segmentos as $segmento): ?>
                        <option value="<?php echo $segmento['segmento']; ?>"><?php echo $segmento['segmento']; ?></option>
                    <?php endforeach;?>
                </select>
			</p>
            <?php $aIdiomas = tep_get_languages();?>
            <p class="flexForm">
				<label>Idioma</label>
                <select name="languages_id">
                    <?php foreach ($aIdiomas as $aIdioma): ?>
                        <option value="<?php echo $aIdioma['id']; ?>"><?php echo $aIdioma['name']; ?></option>
                    <?php endforeach;?>
                </select>
			</p>

            <p class="flexForm">
				<button type="submit" class="buttonS bGreen">Enviar</button>
			</p>
		</form>
	</div>
</div>

</div>
<link rel="stylesheet" type="text/css" href="theme/web/css/groupColor.css"/>
<?php $sJavascript .= '<script type="text/javascript">
$(function() {
$(".borrarElemento").click(function() {
	href = $(this).attr("href")
	if (confirm("¿Estás seguro que deseas borrar el elemento?")) {
		location.href = href
	}
	return false;
})

})
</script>
';

// Footer //
include THEME . 'html/footer.php';

// Librerias //
include DIR_WS_INCLUDES . 'application_bottom.php';
