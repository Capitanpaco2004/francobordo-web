<?php

require('includes/application_top.php');
define('EBAY_ROUTE', getcwd().'/');

global $aSectionsEbay, $versionModuleEbay;
$phpVersion = (version_compare(PHP_VERSION, '5.5.0') >= 0);

if ($phpVersion) {
	require('includes/modules/ebay/functions.php');
	require(DIR_WS_CLASSES . 'currencies.php');
	$currencies = new currencies();
	checkActionEbay();
	checkInstallation();

	$tab = tep_db_prepare_input($_GET['tab']);
	$sJavascript = getJavascriptEbay();
}
require(THEME . 'html/header.php'); ?>
<?php
if (function_exists('getStylesEbay')) {
	getStylesEbay();
}

global $hooks, $ebayMaintenance, $ebayMaintenanceIps;

?>

<?php if ($phpVersion) : ?>
	<div class="toolbarHead">
		<div class="hdr-tlbr">
			<h1 class="pageHeading" style="top: 12px;">Sincronización EBAY</h1>
		</div>
	</div>
	<ul class="menuEbay">
        <?php foreach ($aSectionsEbay as $aSectionEbay): ?>
            <li><a href="<?php echo $aSectionEbay[1]; ?>" style="padding: 6px 9px; margin: 2px 0;" class="buttonS <?php echo $aSectionEbay[2]; ?>"><?php echo $aSectionEbay[0]; ?></a></li>
        <?php endforeach; ?>
        <?php if ($tab == 'sync'): 	?>
            <li><a href="javascript:void(0);" id="stopSync" class="buttonS bRed">Parar sincronización</a></li>
        <?php endif; ?>
		<li>Modo Sandbox <?php if (EBAY_SANDBOX == 'true'): ?><strong style="color: red;">ON</strong><?php else: ?><strong style="color: green;">OFF</strong><?php endif; ?></li>
		<li>Versión de PHP: <strong><?php echo phpversion(); ?></strong></li>
		<li>Versión módulo: <strong><?php echo $versionModuleEbay; ?></strong></li>
		<li><strong style="color: green;">Llamadas a la api hoy: <?php echo ebayGetCountLimits(); ?></strong></li>
	</ul>
	<?php if ($service == false): ?>
		<div class="ebayInfo">
			<p>Necesitas configurar el módulo de EBAY.</p>
			<p>Puedes hacerlo en Sistema -> Configuración -> EBAY</p>

		</div>
	<?php endif; ?>
	<?php
	if ($ebayMaintenance == true) {
		echo '<p style="margin-top: 30px; background-color: #8e1212; color: white; padding: 20px; font-size: 17px;"><strong>Está activado el modo mantenimiento. Pruebe dentro de unos minutos. Muchas gracias.</strong></p>';
	}
	?>
	<?php getEbayView(); ?>

<?php else: ?>
	<div class="ebayInfo">
		<p>La versión actual de PHP no es compatible (<strong><?php echo PHP_VERSION; ?></strong>)</p>
	</div>
<?php endif; ?>
<?php
require(THEME . 'html/footer.php');
require(DIR_WS_INCLUDES . 'application_bottom.php');
