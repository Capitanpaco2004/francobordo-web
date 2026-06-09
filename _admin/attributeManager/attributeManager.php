<?php
/*
  $Id: attributeManager.php,v 1.0 21/02/06 Sam West$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Released under the GNU General Public License
  
  Copyright © 2006 Kangaroo Partners
  http://kangaroopartners.com
  osc@kangaroopartners.com
*/

// change the directory upone for application top includes
chdir('../');
//ini_set('include_path', dirname(dirname(__FILE__)) . (((substr(strtoupper(PHP_OS),0,3)) == "WIN") ? ";" : ":") . ini_get('include_path'));

// OSC application top needed for sessions, defines and functions
require_once('includes/application_top.php');

// db wrapper
require_once('attributeManager/classes/amDB.class.php');

// session functions
require_once('attributeManager/includes/attributeManagerSessionFunctions.inc.php');

// config
require_once('attributeManager/classes/attributeManagerConfig.class.php');

// misc functions
require_once('attributeManager/includes/attributeManagerGeneralFunctions.inc.php');

// parent class
require_once('attributeManager/classes/attributeManager.class.php');

// instant class
require_once('attributeManager/classes/attributeManagerInstant.class.php');

// atomic class
require_once('attributeManager/classes/attributeManagerAtomic.class.php');

// security class
require_once('attributeManager/classes/stopDirectAccess.class.php');

// check that the file is allowed to be accessed
stopDirectAccess::checkAuthorisation(AM_SESSION_VALID_INCLUDE);


// get an instance of one of the attribute manager classes
$attributeManager =& amGetAttributeManagerInstance($_GET);

// do any actions that should be done
$globalVars = $attributeManager->executePageAction($_GET);


// set any global variables from the page action execution
if(is_array($globalVars) && 0 !== count($globalVars)) 
	foreach($globalVars as $varName => $varValue)
		$$varName = $varValue;


// get the current products options and values
$allProductOptionsAndValues = $attributeManager->getAllProductOptionsAndValues(true);
//$SortedProductAttributes = $attributeManager->sortArrSessionVar();


// count the options
$numOptions = count($allProductOptionsAndValues);
// output a response header
//header('Content-type: text/html; charset=ISO-8859-1');
header('Content-type: text/html; charset='.CHARSET);

//$attributeManager->debugOutput($allProductOptionsAndValues);
//$attributeManager->debugOutput($SortedProductAttributes);
//$attributeManager->debugOutput($attributeManager);

// include any prompts
require_once('attributeManager/includes/attributeManagerPrompts.inc.php');

if(!isset($_GET['target']) || 'topBar' == $_GET['target'] ) {
	if(!isset($_GET['target'])) 
		echo '<div id="topBar">';
?>

<table width="100%" cellpadding="0" cellspacing="0">
	<tr>
		<td>
		<?php
		$languages = tep_get_languages();
		if(count($languages) > 1) {
			foreach ($languages as $amLanguage) {
			?>
			&nbsp;<input type="image" <?php echo ($attributeManager->getSelectedLanaguage() == $amLanguage['id']) ? 'style="padding:1px;border:1px solid black" onClick="return false" ' :'onclick="return amSetInterfaceLanguage(\''.$amLanguage['id'].'\');" '?> src="<?php echo DIR_WS_CATALOG_LANGUAGES . $amLanguage['directory'] . '/images/bandera.png'?>"  border="0" title="<?=AM_AJAX_CHANGES?>" >
			<?php
			}
		}
		?>
		</td>
		<td align="right">

		<?php
		if(false !== AM_USE_TEMPLATES) {
			?>
			<div  style="padding:5px 3px 5px 0px">
				<input type="image" <?php if($attributeManager->getTemplateOrder()=='123'){echo 'style="border:1px solid #DDDDDD;"';} ?> src="attributeManager/images/icon_123.png" onclick="return amTemplateOrder('123');" border="0" title="AM_AJAX_SORT_NUMERIC" >
				<input type="image" <?php if($attributeManager->getTemplateOrder()=='abc'){echo 'style="border:1px solid #DDDDDD;"';} ?> src="attributeManager/images/icon_abc.png" onclick="return amTemplateOrder('abc');" border="0" title="AM_AJAX_SORT_ALPHABETIC" >
				&nbsp;
				<?php 
					echo tep_draw_pull_down_menu('template_drop',$attributeManager->buildAllTemplatesDropDown($attributeManager->getTemplateOrder()),(((!isset($selectedTemplate)) || (0 == $selectedTemplate)) ? '0' : $selectedTemplate),'id="template_drop" style="margin-bottom:3px"');	

				?>
				&nbsp;
				<input type="image" src="attributeManager/images/icon_load.png" onclick="return customTemplatePrompt('loadTemplate');" border="0" title="<?=AM_AJAX_LOADS_SELECTED_TEMPLATE?>" >
				&nbsp;
				<input type="image" src="attributeManager/images/icon_save.png" onclick="return customPrompt('saveTemplate');" border="0" title="<?=AM_AJAX_SAVES_ATTRIBUTES_AS_A_NEW_TEMPLATE?>" >
				&nbsp;
				<input type="image" src="attributeManager/images/icon_rename.png" onclick="return customTemplatePrompt('renameTemplate');" border="0" title="<?=AM_AJAX_RENAMES_THE_SELECTED_TEMPLATE?>" >
				&nbsp;
				<input type="image" src="attributeManager/images/icon_delete.png" onclick="return customTemplatePrompt('deleteTemplate');" border="0" title="<?=AM_AJAX_DELETES_THE_SELECTED_TEMPLATE?>" >
				&nbsp;
			</div>
			<?php
		}
		?>
		</td>
	</tr>
</table>
<?php
	if(!isset($_GET['target'])) 
		echo '</div>';
} // end target = topBar
	
if(!isset($_GET['target'])) 
	echo '<div id="attributeManagerAll">';
?>
<?php
if(!isset($_GET['target']) || 'currentAttributes' == $_GET['target']) {
	if(!isset($_GET['target'])) 
		echo '<div id="currentAttributes">';
?>
	<table width="100%" border="0" cellspacing="0" cellpadding="3">	
		<tr class="header">
			<td width="50" align="center">
				<input type="image" src="attributeManager/images/icon_plus.gif" onclick="return amShowHideAllOptionValues([<?php echo implode(',',array_keys($allProductOptionsAndValues));?>],true);" border="0" >
				&nbsp;
				<input type="image" src="attributeManager/images/icon_minus.gif" onclick="return amShowHideAllOptionValues([<?php echo implode(',',array_keys($allProductOptionsAndValues));?>],false);" border="0" >
			</td>
			<td>
				<?=AM_AJAX_NAME?>
			</td>
	
			<td align="right">
				<span style="margin-right:40px"><?=AM_AJAX_ACTION?></span>
			</td>
		</tr>
		
	<?php
	// Opcion 3: precargamos las imagenes "change_image" del producto (clave oid-vid => fichero)
	$amAttrImg = array();
	$amAttrPid = (int)($_GET['products_id'] ?? 0);
	if($amAttrPid > 0) {
		$amAttrRes = tep_db_query('SELECT products_attributes, value FROM products_attributes_actions WHERE products_id = "' . $amAttrPid . '" AND action = "change_image"');
		while($amAttrRow = tep_db_fetch_array($amAttrRes))
			$amAttrImg[$amAttrRow['products_attributes']] = $amAttrRow['value'];
	}

	// Precio final con IVA + signo "=": mostramos el PVP real de cada valor (base +/- delta, IVA incl.)
	// y forzamos el prefijo a "=". Internamente se sigue guardando como incremento (ver clase Instant,
	// que reconvierte "=" -> delta ex-IVA al persistir). Es un punto fijo estable: display y campo viven
	// en espacio "final + =", el server siempre convierte "final + = -> delta", sin doble conversion.
	$amHasBase   = false;
	$amBaseRetail = 0.0;   // products_price (ex-IVA)
	$amBaseProf   = 0.0;   // precio base grupo 1 (customers_group_price) o products_price si no hay
	$amTaxMul     = 1.0;   // 1 + IVA
	if($amAttrPid > 0) {
		$amBaseRes = tep_db_query('SELECT p.products_price, IF(pg.customers_group_price, pg.customers_group_price, p.products_price) AS prof_price, COALESCE(t.tax_rate,0) AS tax_rate FROM products p LEFT JOIN tax_rates t ON (p.products_tax_class_id = t.tax_class_id) LEFT JOIN products_groups pg ON (p.products_id = pg.products_id AND pg.customers_group_id = 1) WHERE p.products_id = "' . $amAttrPid . '" LIMIT 1');
		if($amBaseRow = tep_db_fetch_array($amBaseRes)) {
			$amHasBase    = true;
			$amBaseRetail = (float)$amBaseRow['products_price'];
			$amBaseProf   = (float)$amBaseRow['prof_price'];
			$amTaxMul     = (float)$amBaseRow['tax_rate'] / 100 + 1;
		}
	}
	// Convierte un delta guardado (precio + prefijo +/-) al precio final con IVA, formateado para el input
	if(!function_exists('amFinalPriceFromDelta')) {
		function amFinalPriceFromDelta($base, $price, $prefix, $taxMul) {
			$p = (float)$price;
			$signed = ($prefix === '-') ? -abs($p) : $p; // "=" guarda prefijo "+" con precio posible negativo
			return number_format(($base + $signed) * $taxMul, 2, '.', '');
		}
	}
	?>
	<?php
	if(0 < $numOptions) {
		foreach($allProductOptionsAndValues as $optionId => $optionInfo){
			$numValues = count($optionInfo['values']);
	?>
			<tr class="option">
				<td align="center">
				<input type="image" border="0" id="show_hide_<?php echo $optionId; ?>" src="attributeManager/images/icon_plus.gif" onclick="return amShowHideOptionsValues(<?php echo $optionId; ?>);" >
				
				</td>
				<td>
					<?php echo "{$optionInfo['name']} ($numValues)";?>
				</td>
		
				<td align="right">
					<?php 
					echo tep_draw_pull_down_menu("new_option_value_$optionId",$attributeManager->buildOptionValueDropDown($optionId),(((!isset($selectedOptionValue)) || (0 == $selectedOptionValue)) ? '0' : $selectedOptionValue),'style="margin:3px 0px 3px 0px;" id="new_option_value_'.$optionId.'"');	?>
<!-- JFJ artno per attribute -->
<?php echo tep_draw_input_field("reference_$optionId",'','style="margin:3px 0px 3px 0px;" id="reference_'.$optionId.'"')?>
<?php echo tep_draw_input_field("reference_prov_$optionId",'','style="margin:3px 0px 3px 0px;" id="reference_prov_'.$optionId.'"')?>
<?php echo tep_draw_input_field("products_attributes_ean_$optionId",'','style="margin:3px 0px 3px 0px;" id="products_attributes_ean_'.$optionId.'"')?>

					<input type="image" src="attributeManager/images/icon_add.png" value="Add" border="0" onclick="return amAddOptionValueToProduct('<?php echo $optionId?>');" title="<?php echo htmlspecialchars(sprintf(AM_AJAX_ADDS_ATTRIBUTE_TO_OPTION, $optionInfo['name'])); ?>" >
				
					<input type="image" title="<? echo htmlspecialchars(sprintf(AM_AJAX_ADDS_NEW_VALUE_TO_OPTION,$optionInfo['name'])) ?>" border="0" src="attributeManager/images/icon_add_new.png" onclick="return customPrompt('amAddNewOptionValueToProduct','<?php echo addslashes("option_id:$optionId|option_name:".str_replace('"','&quot;',$optionInfo['name']))?>');" >
<?php
if(false){
?>
<!--					<input type="image" src="attributeManager/images/icon_rename.png" onclick="return customTemplatePrompt('renameTemplate');" border="0" title="Renames the selected template" >-->
<?php
}
?>
					<input type="image" border="0" onClick="return customPrompt('amRemoveOptionFromProduct','<?php echo addslashes("option_id:$optionId|option_name:".str_replace('"','&quot;',$optionInfo['name']))?>');" src="attributeManager/images/icon_delete.png" title="<? echo htmlspecialchars(addslashes(sprintf(AM_AJAX_PRODUCT_REMOVES_OPTION_AND_ITS_VALUES,$optionInfo['name'],$numValues))) ?>" >

			
					<?php
					if(AM_USE_SORT_ORDER) {
					?>	
					<input type="image" onclick="return amMoveOption('<?php echo 'option_id:'.$optionId ; ?>', 'up');" src="attributeManager/images/icon_up.png" title="<?=AM_AJAX_MOVES_OPTION_UP?>" > 
					<input type="image" onclick="return amMoveOption('<?php echo 'option_id:'.$optionId ; ?>', 'down');" src="attributeManager/images/icon_down.png" title="<?=AM_AJAX_MOVES_OPTION_DOWN?>" > 
					<?php
					}
					?>
				</td>
			</tr>
			
<!-- ----- -->
<!-- Show Option Values -->
<!-- ----- -->
	<?php
			if(0 < $numValues){
				foreach($optionInfo['values'] as $optionValueId => $optionValueInfo) {
					// Mostrar precio final con IVA y signo "=" (solo si tenemos base/IVA del producto)
					if($amHasBase) {
						$optionValueInfo['price']     = amFinalPriceFromDelta($amBaseRetail, $optionValueInfo['price'], $optionValueInfo['prefix'], $amTaxMul);
						$optionValueInfo['prefix']    = '=';
						$optionValueInfo['price_pr']  = amFinalPriceFromDelta($amBaseProf, $optionValueInfo['price_pr'], $optionValueInfo['prefix_pr'], $amTaxMul);
						$optionValueInfo['prefix_pr'] = '=';
					}
	?>

			<tr class="optionValue" id="trOptionsValues_<?php echo $optionId; ?>" style="display:none" >
				<td align="center">
					<img src="attributeManager/images/icon_arrow.gif" >
				</td>
				<td>
					<?php $amValNameEsc = htmlspecialchars((string)$optionValueInfo['name'], ENT_QUOTES, CHARSET); $amValUid = $optionId.'_'.$optionValueId; ?>
					<span style="display:inline-flex;align-items:center;gap:5px;max-width:100%;">
						<input type="text" id="amValName_<?php echo $amValUid; ?>" class="amValName" value="<?php echo $amValNameEsc; ?>" data-orig="<?php echo $amValNameEsc; ?>" readonly style="width:420px;max-width:100%;box-sizing:border-box;font-size:12px;background:#f3f3f3;border:1px solid #ccc;" title="Pulsa el lapiz para editar el nombre" onkeydown="if(event.keyCode==13){this.blur();return false;}" onblur="return amAttrNameBlur('<?php echo $optionId; ?>','<?php echo $optionValueId; ?>',this);">
						<input type="image" id="amValEdit_<?php echo $amValUid; ?>" src="attributeManager/images/icon_rename.png" border="0" title="Editar nombre del valor" onclick="return amAttrNameEdit('<?php echo $optionId; ?>','<?php echo $optionValueId; ?>');" style="vertical-align:middle;cursor:pointer;">
					</span>
					<div id="amValAffected_<?php echo $amValUid; ?>" class="amValAffected" style="display:none;margin-top:4px;font-size:11px;line-height:1.35;color:#444;background:#fffbe6;border:1px solid #e8d98a;border-radius:3px;padding:5px 7px;max-width:480px;"></div>
					
				</td>
				<td align="right">

					<span style="margin-right:41px;padding: 20px 0 0;display: inline-block;">
					<?php echo drawDropDownPrefix('id="prefix_'.$optionValueId.'" style="margin:3px 0px 3px 0px;" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'prefix\');"',$optionValueInfo['prefix']);?>
					<label style="position: relative;">
						<span style="position: absolute;top: -24px;left: 0;">Precio Retail</span>
						<?php echo tep_draw_input_field("price_$optionValueId",$optionValueInfo['price'],' style="margin:3px 0px 3px 0px;" id="price_'.$optionValueId.'" size="5" onfocus="amF(this)" onblur="amB(this)" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'price\');"'); ?>
					</label>

					<?php echo drawDropDownPrefix('id="prefix_pr_'.$optionValueId.'" style="margin:3px 0px 3px 0px;" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'prefix_pr\');"',$optionValueInfo['prefix_pr']); ?>
					<label style="position: relative;">
						<span style="position: absolute;top: -24px;left: 0;">Precio Prof.</span>
						<?php echo tep_draw_input_field("price_pr_$optionValueId",$optionValueInfo['price_pr'],' style="margin:3px 0px 3px 0px;" id="price_pr_'.$optionValueId.'" size="5" onfocus="amF(this)" onblur="amB(this)" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'price_pr\');"'); ?>
					</label>

					<label style="position: relative;">
						<span style="position: absolute;top: -24px;left: 2px;">Modelo</span>
						<?php echo tep_draw_input_field("reference_$optionValueId",$optionValueInfo['reference'],' style="margin:3px 0px 3px 0px;" placeholder="Modelo" id="reference_'.$optionValueId.'" size="9" onfocus="amF(this)" onblur="amB(this)" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'reference\');"'); ?>
					</label>
					<label style="position: relative;">
						<span style="position: absolute;top: -24px;left: 2px;">Ref. proveedor</span>
						<?php echo tep_draw_input_field("reference_prov_$optionValueId",$optionValueInfo['reference_prov'],' style="margin:3px 0px 3px 0px;" placeholder="Ref. proveedor" id="reference_prov_'.$optionValueId.'" size="9" onfocus="amF(this)" onblur="amB(this)" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'reference_prov\');"'); ?>
					</label>
					<label style="position: relative;">
						<span style="position: absolute;top: -24px;left: 2px;">EAN</span>
						<?php echo tep_draw_input_field("products_attributes_ean_$optionValueId",$optionValueInfo['products_attributes_ean'],' style="margin:3px 0px 3px 1px;" placeholder="EAN" id="products_attributes_ean_'.$optionValueId.'" size="10" onfocus="amF(this)" onblur="amB(this)" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'products_attributes_ean\');"'); ?>
					</label>
<?php
					// More Product Weight added by RusNN 
					if (AM_USE_MPW) { 
						echo drawDropDownWeightPrefix('id="weight_prefix_'.$optionValueId.'" style="margin:3px 0px 3px 0px;" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'weight_prefix\');"',$optionValueInfo['weight_prefix']);
						echo '<label style="position: relative;">';
							echo '<span style="position: absolute;top: -24px;left: 0;">Incr. Peso</span>';
							echo tep_draw_input_field("weight_$optionValueId",$optionValueInfo['weight'],' style="margin:3px 0px 3px 0px;" id="weight_'.$optionValueId.'" size="5" onfocus="amF(this)" onblur="amB(this)" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\',\'weight\');"');
						echo '</label>';
					}
?>
<?php
					if(AM_USE_SORT_ORDER) {
/*					?>
					<?php echo tep_draw_input_field("sortOrder_$optionValueId",$optionValueInfo['sortOrder'],' style="margin:3px 0px 3px 0px;" id="sortOrder_'.$optionValueId.'" size="4" onChange="return amUpdate(\''.$optionId.'\',\''.$optionValueId.'\');"'); ?>
					<?php
*/					}
					?>

					</span>
<?php
if(false){
?>
<!--					<input type="image" src="attributeManager/images/icon_rename.png" onclick="return customTemplatePrompt('renameTemplate');" border="0" title="Renames the selected template" >-->
<?php
}
?>
<?php
					// Opcion 3: hasta 2 imagenes por valor (cambian la galeria en la ficha al seleccionarlo).
					// Parseamos el value en 2 slots; los ficheros nuevos llevan sufijo -1/-2, los legacy van al slot 1.
					$amImgKey  = $optionId . '-' . $optionValueId;
					$amImgUid  = $optionId . '_' . $optionValueId;
					$amImgVal  = isset($amAttrImg[$amImgKey]) ? (string)$amAttrImg[$amImgKey] : '';
					$amSlots   = array(1 => '', 2 => '');
					foreach (explode('[dxsepare]', $amImgVal) as $amF) {
						$amF = basename(trim($amF));
						if ($amF === '') continue;
						if (preg_match('/-([12])\.[^.]+$/', $amF, $amMM)) $amSlots[(int)$amMM[1]] = $amF;
						elseif ($amSlots[1] === '')                      $amSlots[1] = $amF;
						else                                             $amSlots[2] = $amF;
					}
?>
					<span class="amAttrImg" style="display:inline-block;vertical-align:middle;margin:0 4px;white-space:nowrap;">
<?php foreach (array(1, 2) as $amSlot): $amImgFile = $amSlots[$amSlot]; ?>
						<span style="display:inline-block;vertical-align:middle;<?php echo $amSlot === 2 ? 'margin-left:6px;' : ''; ?>">
							<img id="amAttrImgThumb_<?php echo $amImgUid; ?>_<?php echo $amSlot; ?>" src="<?php echo $amImgFile === '' ? '' : '../images/atributos/' . rawurlencode($amImgFile) . '?v=' . time(); ?>" style="width:30px;height:30px;object-fit:cover;border:1px solid #ccc;vertical-align:middle;<?php echo $amImgFile === '' ? 'display:none;' : ''; ?>" >
							<label title="Imagen <?php echo $amSlot; ?> del valor" style="cursor:pointer;border:1px solid #bbb;border-radius:3px;padding:1px 5px;background:#f5f5f5;font-size:11px;vertical-align:middle;">Img<?php echo $amSlot; ?>
								<input type="file" id="amAttrImgFile_<?php echo $amImgUid; ?>_<?php echo $amSlot; ?>" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;" onchange="amAttrImageUpload('<?php echo $optionId; ?>','<?php echo $optionValueId; ?>','<?php echo $amSlot; ?>',this);">
							</label>
							<input type="image" id="amAttrImgClear_<?php echo $amImgUid; ?>_<?php echo $amSlot; ?>" src="attributeManager/images/icon_delete.png" title="Quitar imagen <?php echo $amSlot; ?>" onclick="return amAttrImageClear('<?php echo $optionId; ?>','<?php echo $optionValueId; ?>','<?php echo $amSlot; ?>');" style="vertical-align:middle;<?php echo $amImgFile === '' ? 'display:none;' : ''; ?>" >
						</span>
<?php endforeach; ?>
					</span>
					<input type="image" border="0" onClick="return customPrompt('amRemoveOptionValueFromProduct','<?php echo addslashes("option_id:$optionId|option_value_id:$optionValueId|option_value_name:".str_replace('"','&quot;',$optionValueInfo['name']))?>');" src="attributeManager/images/icon_delete.png" title="<? echo htmlspecialchars(sprintf(AM_AJAX_PRODUCT_REMOVES_VALUE_FROM_OPTION,$optionValueInfo['name'],$optionInfo['name'])) ?>" >
					<?php
					if(AM_USE_SORT_ORDER) {
					?>	
						<input type="image" onclick="return amMoveOptionValue('<?php echo 'option_id:'.$optionId.'|option_value_id:'.$optionValueId.'|products_attributes_id:'.$optionValueInfo['products_attributes_id']; ?>', 'up');" src="attributeManager/images/icon_up.png" title="<?=AM_AJAX_MOVES_VALUE_UP?>" > 
						<input type="image" onclick="return amMoveOptionValue('<?php echo 'option_id:'.$optionId.'|option_value_id:'.$optionValueId.'|products_attributes_id:'.$optionValueInfo['products_attributes_id']; ?>', 'down');" src="attributeManager/images/icon_down.png" title="<?=AM_AJAX_MOVES_VALUE_DOWN?>" >  
					<?php
					}
					?>
				</td>
			</tr>
	<?php
				}
			}
		}	
	}
	?>
<!-- ----- -->
<!-- EOF Show Option Values -->
<!-- ----- -->
	</table>
	<?php
	if(!isset($_GET['target'])) 
		echo '</div>';
} // end target = currentAttributes

if(!isset($_GET['target']) || 'newAttribute' == $_GET['target'] ) {
	if(!isset($_GET['target'])) 
		echo '<div id="newAttribute">';
	
	// check to see if the selected option isset if it isn't pick the first otion in the dropdown
	$optionDrop = $attributeManager->buildOptionDropDown();
	
	if ((!isset($selectedOption)) ||(!is_numeric($selectedOption))) {
		foreach($optionDrop as $key => $value) {
			if(tep_not_null($value['id'])){
				$selectedOption = $value['id'];
				break;
			}
		}
	}

	$optionValueDrop = $attributeManager->buildOptionValueDropDown($selectedOption);
?>
<!-- ----- -->
<!-- SHOW NEW OPTION PANEL on Bottom -->
<!-- ----- -->
		<div class="newOptionPanel-header">
			<?=AM_AJAX_OPTION_NEW_PANEL?>
		</div>
	<table border="0"  cellpadding="0" cellspacing="0">
		<tr>
			<td align="right" valign="middle" class="newOptionPanel-label">
				<?=AM_AJAX_OPTION?> <?php echo tep_draw_pull_down_menu('optionDropDown',$optionDrop,$selectedOption,'id="optionDropDown" onChange="return amUpdateNewOptionValue(this.value);" class="optionDropDown"')?>
			</td>
			<td align="right" valign="middle" class="newOptionPanel-button">
				<input border="0"  type="image" src="attributeManager/images/icon_add_new.png" onclick="return customPrompt('amAddOption');" title="<?=AM_AJAX_ADDS_NEW_OPTION?>" >
			</td>
			<td align="right" valign="middle" class="newOptionPanel-label">
				<?=AM_AJAX_VALUE?> <?php echo tep_draw_pull_down_menu('optionValueDropDown',$optionValueDrop,(((isset($selectedOptionValue)) && (is_numeric($selectedOptionValue)))? $selectedOptionValue : ''),'id="optionValueDropDown" class="optionValueDropDown"')?>
			</td>
			<td align="right" valign="middle" class="newOptionPanel-button">
					<input border="0" type="image" src="attributeManager/images/icon_add_new.png" onclick="return customPrompt('amAddOptionValue');" title="<?=AM_AJAX_ADDS_NEW_OPTION_VALUE?>" >
			</td>
			<td valign="top" class="newOptionPanel-label">
				<?=AM_AJAX_PREFIX?> <?php echo drawDropDownPrefix('id="prefix_0"', '=')?>
			</td>
			<td valign="top" class="newOptionPanel-label">
				<?=AM_AJAX_PRICE?> <?php echo tep_draw_input_field('newPrice','','size="5" id="newPrice"'); ?>
			</td>

			<td valign="top" class="newOptionPanel-label">
				<?=AM_AJAX_PREFIX?> <?php echo drawDropDownPrefix('id="prefix_pr_0"', '=')?>
			</td>
			<td valign="top" class="newOptionPanel-label">
				Precio Prof. <?php echo tep_draw_input_field('newPricePr','','size="5" id="newPricePr"'); ?>
			</td>

<td valign="top" class="newOptionPanel-label">
				<?=AM_AJAX_REFERENCE?> <?php echo tep_draw_input_field('reference_new','','size="12" id="reference_new"'); ?>
			</td>
<td valign="top" class="newOptionPanel-label">
				Ref. Proveedor: <?php echo tep_draw_input_field('reference_prov_new','','size="20" id="reference_prov_new"'); ?>
			</td>
<td align="top" class="newOptionPanel-label">
				EAN: <?php echo tep_draw_input_field('products_attributes_ean_new','','size="14" id="products_attributes_ean_new"'); ?>
			</td>
<?php 
// More Product Weight added by RusNN
  if (AM_USE_MPW) {
?>
      <td valign="top" class="newOptionPanel-label">
        <?=AM_AJAX_WEIGHT_PREFIX?> <?php echo drawDropDownWeightPrefix('id="weight_prefix_0"')?>
      </td>
      <td valign="top" class="newOptionPanel-label">
        <?=AM_AJAX_WEIGHT?> <?php echo tep_draw_input_field('newWeight','','size="4" id="newWeight"'); ?>
      </td>
<?php
  }
?>
<?php
			if(AM_USE_SORT_ORDER) {
			?>
			<!--
			<td valign="top" class="newOptionPanel-label">
				<?=AM_AJAX_SORT?> <?php echo tep_draw_input_field('newSort','','size="4" id="newSort"'); ?>
			</td>
			-->
			<?php
			} else {
			?>
			<td valign="top">
				<?php echo tep_draw_hidden_field('newSort','','size="4" id="newSort"'); ?>
			</td>
			<?php
			}
			?>

			<td align="right" valign="middle" class="newOptionPanel-button">
				<input type="image" src="attributeManager/images/icon_add.png" value="Add" onclick="return amAddAttributeToProduct();" title="<?=AM_AJAX_ADDS_ATTRIBUTE_TO_PRODUCT?>" border="0"  >
			</td>
		</tr>
	</table>			
<?php
	if(!isset($_GET['target'])) 
		echo '</div>';
} // end target = newAttribute
if(!isset($_GET['target'])) 
	echo '</div>';
?>
<?php
// Modified by RusNN
if (AM_USE_QT_PRO) {
  $products_id = tep_db_prepare_input($_GET['products_id']);
  
   if(!isset($_GET['target']) || 'currentProductStockValues' == $_GET['target']) {
	if(!isset($_GET['target'])) 
		echo '<div id="currentProductStockValues">';

$q=tep_db_query($sql="select products_name, products_options_name as _option, products_attributes.options_id as _option_id, products_options_values_name as _value, products_attributes.options_values_id as _value_id from ".
                  "products_description, products_attributes, products_options, products_options_values where ".
                  "products_attributes.products_id = products_description.products_id and ".
                  "products_attributes.products_id = '" . $products_id . "' and ".
                  "products_attributes.options_id = products_options.products_options_id and ".
                  "products_attributes.options_values_id = products_options_values.products_options_values_id and ".
                  "products_description.language_id = " . (int)$languages_id . " and ".
                  "products_options_values.language_id = " . (int)$languages_id . " and products_options.products_options_track_stock = 1 and ".
                  "products_options.language_id = " . (int)$languages_id . " order by products_attributes.options_id, products_attributes.options_values_id");
  if (tep_db_num_rows($q)>0) {
    $flag = true;
    
    while($list=tep_db_fetch_array($q)) {
      $options[$list['_option_id']][]=array($list['_value'],$list['_value_id']);
      $option_names[$list['_option_id']]=$list['_option'];
      $product_name=$list['products_name'];
    }
  } else {
    $flag = false;
  }
?>
	<table width="100%" border="0" cellspacing="0" cellpadding="3">	
		<tr class="header">
			<td width="50" align="center">
				&nbsp;
			</td>
			<td>
				<?=AM_AJAX_QT_PRO?>
			</td>
	
			<td align="right" colspan="<?php echo (sizeof($options ?? [])+2); ?>">
				<span style="margin-right:40px"><?=AM_AJAX_ACTION?></span>
			</td>
		</tr>
<?php
  if ($flag) {
?>		<tr class="option">
			<td align="center">
			<input type="image" border="0" id="show_hide_9999" src="attributeManager/images/icon_plus.gif" onclick="return amShowHideOptionsValues(9999);" >
			</td>
<?php
	foreach( $options as $k => $v ) {
?>   	
			<td>
				<?php echo $option_names[$k]; ?>
			</td>
<?php
      $title[]=$k;
    }
?>
			<td align="right">
				<span style="margin-right:41px;">
				<?=AM_AJAX_QUANTITY?>
				</span>
			</td>
		</tr>
<?php
    $q=tep_db_query("select * from " . TABLE_PRODUCTS_STOCK . " where products_id='" . $products_id . "' order by products_stock_attributes");
    while($rec=tep_db_fetch_array($q)) {
      $val_array=explode(",",$rec['products_stock_attributes']);
?>      
		<tr class="optionValue" id="trOptionsValues_9999" style="display:none" >
			<td align="center">
				<?php echo $rec['products_stock_id']; ?>
				<img src="attributeManager/images/icon_arrow.gif" >
			</td>
<?php				
      foreach($val_array as $val) {
        if (preg_match("/^(\d+)-(\d+)$/",$val,$m1)) {
?>
			<td>
				&nbsp;&nbsp;&nbsp;<?php echo tep_values_name($m1[2]); ?>
			</td>
<?php				
        } else {
?>	
       			<td>
       				&nbsp;
       			</td>
<?php
        }
      }
      for($i=0;$i<sizeof($options)-sizeof($val_array);$i++) {
?>
       			<td>
       				&nbsp;
       			</td>
<?php		
      }
?>      
			<td align="right">
				<span style="margin-right:41px;">
				<?php echo tep_draw_input_field("productStockQuantity_" . $rec['products_stock_id'], $rec['products_stock_quantity'], ' style="margin:3px 0px 3px 0px;" id="productStockQuantity_'.$rec['products_stock_id'].'" size="4" onChange="return amUpdateProductStockQuantity(\''.$rec['products_stock_id'].'\');"'); ?>
				</span>
				<input type="image" border="0" onClick="return customPrompt('amRemoveStockOptionValueFromProduct','<?php echo addslashes("option_id:$rec[products_stock_id]")?>');" src="attributeManager/images/icon_delete.png" title="<?=AM_AJAX_DELETES_ATTRIBUTE_FROM_PRODUCT?>" >
			</td>
		</tr>
<?php
    }
?>
<?php
  }
?>
	</table>
<?php
	if(!isset($_GET['target'])) 
		echo '</div>';
	} // end target = currentStockValues
if(!isset($_GET['target']) || 'newProductStockValue' == $_GET['target'] ) {
	
	if(!isset($_GET['target'])) 
		echo '<div id="newProductStockValue">';
?>
	<table border="0" cellpadding="3">
		<tr>
			<td align="right" valign="top">
<?php	
  if ($flag) {
    // There are number of options, assigned to product. Allow to add this in combination with quantity (RusNN)
    $i=0;
	foreach( $options as $k => $v ) {
      echo "<td><select name=option$k id=option$k>";
      $dropDownOptions[] = 'option'.$k;
      foreach($v as $v1) {
        echo "<option value=".$v1[1].">".$v1[0];
      }
      echo "</select></td>";
      $i++;
    }
    $db_quantity = 1; // pre set value for 1 qty of options combination
  } else {
    // No options available for product. Should work with product quantity only. Get it from DB (RusNN)
    $q=tep_db_query("select products_quantity, products_name from " . TABLE_PRODUCTS . " p,products_description pd where pd.products_id= p.products_id and p.products_id='" . $products_id ."'");
    $list=tep_db_fetch_array($q);
    $db_quantity=$list['products_quantity'];
    $dropDownOptions = array();
  }
?>
            <td><?php echo AM_AJAX_QUANTITY; ?></td>
            <td>
                <?php echo tep_draw_input_field("stockQuantity", $db_quantity, ' style="margin:3px 0px 3px 0px;" id="stockQuantity" size="4"'); ?>
            </td>
            <td>
                <input type="image" src="attributeManager/images/icon_add.png" value="Add" onclick="return amAddStockToProduct('<?php echo implode(",", $dropDownOptions); ?>');" title="<?php echo ($flag) ? AM_AJAX_UPDATE_OR_INSERT_ATTRIBUTE_COMBINATIONBY_QUANTITY : AM_AJAX_UPDATE_PRODUCT_QUANTITY;?>" border="0"  >
            </td>
		</tr>
	</table>			
<?php
	if(!isset($_GET['target'])) 
		echo '</div>';
} // end target = newProductStockValue
if(!isset($_GET['target'])) 
	echo '</div>';
?>
<?php
} // End QT Pro Plugin
?>