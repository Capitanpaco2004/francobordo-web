<?php
use util\configurations\ConfigCacheGenerator;
use util\configurations\ConfigurationsDataProvider;

// Uso de la clase
ConfigCacheGenerator::setConfigCacheFile((defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG : '') . 'cache/cachefile.inc.php');

$configurationsDataProvider = new ConfigurationsDataProvider();
$configCacheGenerator = new ConfigCacheGenerator();

$configurationData = $configurationsDataProvider->fetchConfigurationData();
$configCacheGenerator::generateConfigCache($configurationData, false, 1);
