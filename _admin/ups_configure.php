<?php
/*
 $Id$

 osCommerce, Open Source E-Commerce Solutions
 http://www.oscommerce.com

 Copyright (c) 2010 osCommerce

 Released under the GNU General Public License
 */

require_once('includes/application_top.php');
require_once(DIR_WS_CLASSES . 'language.php');
require_once(DIR_WS_LANGUAGES.'/'.$language.'/ups_configure.php');
require_once(DIR_WS_MODULES . 'ups/classes/UpsTools.php');
require_once(DIR_WS_MODULES . 'ups/classes/UpsValidate.php');
require_once(DIR_WS_MODULES . 'ups/classes/UpsAccount.php');
require_once(DIR_WS_MODULES . 'ups/classes/UpsService.php');
require_once(DIR_WS_MODULES . 'ups/lib/UPSAccessLicenseApi.php');

global $languages_id, $upsMessageStack, $activeTab, $debug;

$action = Tools::getValue('action');
$debug = Tools::getConfigValue('MODULE_SHIPPING_UPSSHIPPING_DEBUG');

if(!$upsMessageStack)
	$upsMessageStack = new messageStack();
	
$is_license_agreed = Tools::getConfigValue('MODULE_SHIPPING_UPS_LICENSE_AGREED');

if(!$is_license_agreed){
	$licenseAgreementLanguageCode = strtoupper(Tools::getLanguageCode($languages_id) ?? '');
	$licenseAgreementCountryCode = strtoupper(Tools::getCountryCode(STORE_COUNTRY) ?? '');
	    
	//Generate license agreement
	$UPSAccessLicenseApi = new UPSAccessLicenseApi();	
	$licenseAgreementText = $UPSAccessLicenseApi->getLicenseAgreement($licenseAgreementCountryCode, $licenseAgreementLanguageCode);
}

$country_array[] = array( 'id' => 'BE', 'text' => 'Belgium');
$country_array[] = array( 'id' => 'FR', 'text' => 'France');
$country_array[] = array( 'id' => 'LU', 'text' => 'Luxembourg');
$country_array[] = array( 'id' => 'NL', 'text' => 'Netherlands');
$country_array[] = array( 'id' => 'ES', 'text' => 'Spain');

switch($action){
	case 'register':
	case 'printLicense':
		require_once('includes/modules/ups/ups_action_register.php');
		$is_license_agreed = Tools::getConfigValue('MODULE_SHIPPING_UPS_LICENSE_AGREED');
		break;
	
	case 'createAccount':
	case 'updateAccount':
	case 'updateAccountsSettings':
		require_once('includes/modules/ups/ups_action_account.php');
		break;
		
	case 'addService':
	case 'updateService':
	case 'editService':
	case 'deleteService':
	case 'getServiceDest':
	case 'getServices':
	case 'updateServicesSettings':		
		require_once('includes/modules/ups/ups_action_service.php');
		break;

	case 'export_worldship':
	case 'export_ups':
	case 'export_pdf':
		require_once('includes/modules/ups/ups_action_shipping.php');
		break;
		
	case 'updateTrackingSettings':		
		require_once('includes/modules/ups/ups_action_tracking.php');
		break;
		
	default:	
		break;
}

if(!$activeTab)	
	$activeTab = 'account';

include( THEME . 'html/header.php' );	
?>

<link rel="stylesheet" type="text/css" href="includes/modules/ups/assets/css/ups_configure.css">
<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
<script type="text/javascript" src="includes/modules/ups/assets/js/ups_configure.js"></script>

<script type="text/javascript">
if ( $.attrFn ) { $.attrFn.text = true; }
</script>

</head>
	<table border="0" width="100%" cellspacing="2" cellpadding="2">
		<tr>
			<td width="<?php echo BOX_WIDTH; ?>" valign="top">
				<table border="0" width="<?php echo BOX_WIDTH; ?>" cellspacing="1" cellpadding="1" class="columnLeft">
					<!-- left_navigation //-->
					<?php require(DIR_WS_INCLUDES . 'column_left.php'); ?>
					<!-- left_navigation_eof //-->
				</table>
			</td>
			<!-- body_text //-->
			<td width="100%" valign="top">
				<div id="ups_configuration">				
					<div id="ups_header">
						<div class="logo"><img src="includes/modules/ups/assets/img/ups-logo.png" alt="UPS" width="50" height="60" /></div>
						<div class="heading_title"><?php echo UPS_HEADING_TITLE; ?></div>
					</div>
					
					<?php 
					if ($upsMessageStack->size > 0) echo $upsMessageStack->output();

					if(!Tools::isUpsShippingModuleInstalled()){ 
					?>
						<div class="ups_content">
							<center><h3><?php echo UPS_MODULE_NOT_INSTALLED; ?></h3></center>
							<p><center><a href="<?php echo tep_href_link(FILENAME_MODULES, 'set=shipping&module=upsshipping', 'NONSSL'); ?>"><?php echo UPS_MODULE_INSTALL_LINK_INFO; ?></a></center></p>
						</div>						
					<?php 
					}
					else{
						if(!$is_license_agreed){
							require_once('includes/modules/ups/tabs/register.php');
						}
						else{
						?>			
						<div class="ups_content">				
							<ul class="nav-tabs">
								<li><a href="#account"<?php if($activeTab == 'account') { ?> class="active"<?php } ?>><?php echo UPS_ACCOUNT_SETTINGS; ?></a></li>
								<li><a href="#services"<?php if($activeTab == 'services') { ?> class="active"<?php } ?>><?php echo UPS_SERVICES; ?></a></li>
								<li><a href="#shipping"<?php if($activeTab == 'shipping') { ?> class="active"<?php } ?>><?php echo UPS_SHIPPING; ?></a></li>
								<!-- <li><a href="#tracking"<?php if($activeTab == 'tracking') { ?> class="active"<?php } ?>><?php echo UPS_TRACKING; ?></a></li>	 -->								
							</ul>
							<?php 					
							require_once('includes/modules/ups/tabs/accounts.php');
							require_once('includes/modules/ups/tabs/services.php');
							require_once('includes/modules/ups/tabs/shipping.php');
							//require_once('includes/modules/ups/tabs/tracking.php');	
						}		
					}							
					?>				
					</div>
				</div>
			</td>
			<!-- body_text_eof //-->
		</tr>
	</table>
<script type="text/javascript">
var ups_ajax_url = '<?php echo tep_href_link('ups_configure.php', '', 'NONSSL'); ?>';
</script>	
<?php require(THEME . 'html/footer.php'); ?>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>