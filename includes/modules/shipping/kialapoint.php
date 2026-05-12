<?php
class kialapoint {
  var $code, $title, $description, $icon, $enabled, $relais, $sort_order, $tax_class, $_check, $quotes;
  // constructor
  function __construct() {
    global $order, $customer, $customer_group_id;
    $this->code = 'kialapoint';
    $this->title = 'Kiala';
    $this->description = MODULE_SHIPPING_KIALAPOINT_TITLE;
    $this->sort_order = MODULE_SHIPPING_KIALAPOINT_SORT_ORDER;
    $this->icon = DIR_WS_ICONS . 'kiala.png';
    $this->enabled = ((MODULE_SHIPPING_KIALAPOINT_STATUS == 'True') ? true : false);
     if ( ($this->enabled == true) && ((int)MODULE_SHIPPING_KIALA_ZONE > 0) && is_object($order) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id, zone_country_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_KIALA_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        $order_shipping_country = $order->delivery['country']['id'];
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
//          } elseif ($check['zone_country_id'] == $order->delivery['country']['id']) {
            $check_flag = true;
            break;
          }
        }
        
        if ($check_flag == false)
        	$this->enabled = false;
        
        // Si el grupo de cliente es distinto de Cliente Final, desactivar
        if ($customer_group_id != 0)
        	$this->enabled = false;
			
			
		if( $_SERVER['REMOTE_ADDR'] == '84.123.172.164' || $_SERVER['REMOTE_ADDR'] == '93.9.216.30')
			$this->enabled = false;
        	
     }
}
  // methods
  function quote($method = '') {
    global $order, $cart, $shipping_weight, $languages_id, $sendto, $customer_id, $language;
    if (defined('MOBILE_SESSION')) { // begin mobile module
	
	//define the quote object to return
    $this->quotes = array('id' => $this->code,
                          'module' => $this->title,
                          'methods' => array());
    
	//Add the Kiala module icon
	if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);
	
	//Select the appropriate kiala point for the connected user
	$selectkp = tep_db_query("select kp from kiala_client_suggest where id='".$customer_id."'");
	$fetchkp = tep_db_fetch_array($selectkp);
	if (tep_not_null($fetchkp['kp']))
		$kp_suggestion = $fetchkp['kp'];
	else
		$kp_suggestion = 'null';
		
	//define the parameters for the Kiala map
	$params = "";
	
	//Get and Add the site language to both parameters
	$languages_query = tep_db_query("select languages_id, code from " . TABLE_LANGUAGES . " order by sort_order");
	while ($languages = tep_db_fetch_array($languages_query)) {
		if ($languages['languages_id'] == $languages_id) {
			$lang = $languages['code'];
		}
	}
	$params .= "language=".urlencode($lang);
		
	//Add the country 
	$dest_country = $order->delivery['country']['iso_code_2'];
	$cust_country = $order->customer['country']['iso_code_2'];
	$params .= "&country=".urlencode($dest_country);
	
	//Add country for DSP ID
	if (preg_match('/^[3]{1}[0-9]{7}$/i', MODULE_SHIPPING_KIALAPOINT_DSPID)) {
		$params .= "&dspid=".urlencode(MODULE_SHIPPING_KIALAPOINT_DSPID);
	} else {
		$params .= "&dspid=".urlencode($dest_country);
	}
	
	//Add the city
	$dest_city = $order->delivery['city'];
	$cust_city = $order->customer['city'];
	$params .= "&city=".urlencode($dest_city);
	
	//Add the zip code
	$dest_zip = $order->delivery['postcode'];
	$cust_zip = $order->customer['postcode'];
	$params .= "&zip=".urlencode($dest_zip);
	
	//Add the street address
	$dest_street = $order->delivery['street_address'];
	$cust_street = $order->customer['street_address'];
	$params .= "&street=".urlencode($dest_street);
	//Add the map option to off for the first parameter
	$params .= "&map-controls=".urlencode("off");
	
	//Add the sort methode
	$params .= "&sort-method=".urlencode("ACTIVE_FIRST");
	
	//remove the thumbnails for the first parameter
	$params .= "&thumbnails=".urlencode("off");
	
	//Add the CSS path to the first parameter
	$params .= "&css=".urlencode("https://locateandselect.kiala.com/static/style/search/search_public_theme_sleek.css");
	
	//Add the back URL to the firts parameter
	$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,5))=='https'?'https':'http';
	
	$protocol = 'https';
	$host = $protocol.'://'.$_SERVER["SERVER_NAME"].':'.$_SERVER["SERVER_PORT"].DIR_WS_CATALOG;
	$params .= "&bckUrl=" . $host . "checkout_shipping.php?";
	
	//Add the target
	$params .= "&target=_parent";
	
	//Kiala locate and select map link
	$ls_map_link = "https://locateandselect.kiala.com/locateandselect/search?".$params;
	//print($ls_map_link);
	
	if (isset($_GET["shortkpid"])) {
		// get cleanedup query string
		$str = (isset($_SERVER['QUERY_STRING']) ) ? $_SERVER['QUERY_STRING'] : '';
		// parse into array
		parse_str($str, $tvars);
		$vars = array();
		foreach ( $tvars as $key => $value ) {
		  $vars[$key] = $value;
		}
	}
	
	//split the kpopenninghours to a table
	if (!function_exists('kpOpenHoursSplit'))
	{
	function kpOpenHoursSplit($hours){
		$hoursTab=explode('-',$hours);
		$cnt='<table>';
		for ($i=0;$i<sizeof($hoursTab);$i=$i+1)
		{
			$subHoursTab=explode('.',$hoursTab[$i]);
			$cnt.='<tr><td>'.$subHoursTab[0].'</td><td>'.$subHoursTab[1].' - '.$subHoursTab[2].'</td></tr>';
		}
		$cnt.='</table>';
		return $cnt;
	}
	}
	
	//list of allowed countries
	// $allowed_countries = array('BE','FR','NL','LU','ES');
$allowed_countries = array();	
$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_BE"');
$value = tep_db_fetch_array($select_qry);
if ($value['configuration_value'] != '') $allowed_countries[] = 'BE';
$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_LU"');
$value = tep_db_fetch_array($select_qry);
if ($value['configuration_value'] != '') $allowed_countries[] = 'LU';
$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_FR"');
$value = tep_db_fetch_array($select_qry);
if ($value['configuration_value'] != '') $allowed_countries[] = 'FR';
$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_NL"');
$value = tep_db_fetch_array($select_qry);
if ($value['configuration_value'] != '') $allowed_countries[] = 'NL';
$select_qry = tep_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE `configuration_key` = "MODULE_SHIPPING_KIALAPOINT_DSPID_ES"');
$value = tep_db_fetch_array($select_qry);
if ($value['configuration_value'] != '') $allowed_countries[] = 'ES';
	$delay = "";
	if (in_array($dest_country,$allowed_countries)) {
        $cost = MODULE_SHIPPING_KIALAPOINT_TARIFS;
        $delay = MODULE_SHIPPING_KIALAPOINT_TITLE;
		$kp_selector = '<div id="KialaPoint">';
		$kp_selector .= '<div style="margin:5px;"> ';
		$kp_selector .= '<span onmouseover="ddrivetip()"; onmouseout="hideddrivetip()";>'.MODULE_SHIPPING_KIALAPOINT_INFO0.'</span>'; 
		$kp_selector .=	'<strong><a rel="shadowbox" onclick="show_info();" style="font-size: 11px; cursor:pointer; font-family: Verdana,Arial,sans-serif;">(More info...)</a></strong>';
		$kp_selector .= '<div id="kp-info-msg"></div></div>';
		$kp_selector .= '<div class="ui-grid-a my-breakpoint-50em">';
		$kp_selector .= '<div class="ui-block-a">';
		$kp_selector .= '<div id="content-kp-choice" style="margin:5px; font-size:12px;">';
		$kp_selector .= '<div id="kp-choice" style="border: 1px solid #4297D7;><div id="loading" align="center"><br><br><br><img src="'.$host.'images/suggest_kp_loading.gif"></div>';
		$kp_selector .= '<div id="kp-status" style="visibility:hidden;">Disactivated</div>';
		$kp_selector .= '<span id="kp-validity" style="color:red;"></span></div>';
		$kp_selector .= '</div>';
		$kp_selector .= '<div class="ui-block-b">';
		$kp_selector .= '<div id="content-kp-last-choice" style="margin:5px; font-size:12px;">';
		$kp_selector .= '<div id="kp-last-choice" style="border: 1px solid #4297D7;><div id="loading" align="center"><br><br><br><img src="'.$host.'images/suggest_kp_loading.gif"></div>';
		$kp_selector .= '<div id="kp-last-choice-status" style="visibility:hidden;">Disactivated</div>';
		$kp_selector .= '<span id="kp-last-choice-validity" style="color:red;"></span></div>';
		$kp_selector .= '</div>';
		$kp_selector .= '</div>';
		$kp_selector .= '<p class="main" align="center"><b>'.MODULE_SHIPPING_KIALAPOINT_KP_MAP.'</b></p>' ;
		$kp_selector .= '<div id="kp-field" align="center"><iframe id="kpiframe" width="97%" height="450" src="'.$ls_map_link.'&gui=sleek"></iframe></div>';
		$kp_selector .='</div>';
		//save all the javascript function into a variable and add it to $delay
		$js_output = '';
		ob_start();
		?>
		<SCRIPT language="javascript" src="<?php echo DIR_WS_CATALOG;?>ext/kialajs/jsonlib-src.js"></SCRIPT>
		<SCRIPT language="javascript" src="<?php echo DIR_WS_CATALOG;?>ext/kialajs/shadowbox/shadowbox.js"></SCRIPT>
		<link rel="stylesheet" type="text/css" href="<?php echo DIR_WS_CATALOG;?>ext/kialajs/shadowbox/shadowbox.css">
		<link rel="stylesheet" type="text/css" href="https://locateandselect.kiala.com/static/style/search/search_public_theme_sleek.css">
		<style type="text/css">	
			#dhtmltooltip {
			position: absolute;
			width: 300px;
			border: 2px solid #B6B7CB;
			padding: 2px;
			background-color: #F8F8F9;
			visibility: hidden;
			z-index: 100;
			}
		</style>
		<script type="text/javascript">
		<!--
			Shadowbox.init();
			function show_info() {
				Shadowbox.open({
					content:    "<br><div style='color: black; background: none repeat scroll 0% 0% white; font-family: Verdana,Arial,Helvetica,Sans-Serif; font-size: 13px; padding-left: 15px; padding-right: 15px;' id='advertising-info'>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO1; ?><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO2; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO3; ?>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO4; ?><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO5; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO6; ?><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO7; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO8; ?>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO9; ?><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO10; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO11; ?><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO12; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO13; ?></div><br>",
					player:     "html",
					title:      "Info",
					height:     220,
					width:      1220
				});
			};
		-->
		</script>
		<script type="text/javascript">
		<!--
		
		//reformat the KP ID
		function FormatKPID(kp,country) {
			if (country == "FR") {
				kp = kp.split('Relais Kiala: ')[1];
			} else if (country == "ES") {
				kp = kp.substr(1,4);
			} else /*BE or NL*/{
				kp = kp.substr(3,4);
			}
			return kp;
		}
		
		//find the KP to suggest to end customer
		function findSuggestKp() {
			var startUrl = encodeURI("<?php echo $ls_map_link; ?>");
			var kpObject = null;
			var kp = null;
			var message = null;
			var status = null;
			
			<?php if (isset($_GET['shortkpid'])) { ?>
				var kpSuggest = String('<?php echo $_GET['shortkpid']; ?>');
			<?php } else { ?>
				var kpSuggest = String('<?php echo $kp_suggestion; ?>');
			<?php } ?>
			jsonlib.fetch({
							url: startUrl,
							method: 'GET'
							},function (xml) {
								try {
									var kpsList = $(xml.content).find('.kp');
									//if at least one kp available
									if (kpsList.length > 0) {
										//if kp suggeggestion is not equal null and in the list
										if (kpSuggest != null) {
											kpsList.each(function(){
												kp = String(FormatKPID($(this).find('.kpshortid').html(),'<?php echo $dest_country; ?>'));
												if (kpSuggest == kp) {
													if ($(this).find('.status').children('span').attr('class') == 'ONHOLIDAY') {
														status = 'ONHOLIDAY';
													} else if ($(this).find('.status').children('span').attr('class') == 'NOTYETACTIVE'){
														status = 'NOTYETACTIVE';
													} else {
														status = 'ACTIVE';
														kpObject = this;
														message = '<?php if (isset($_GET['shortkpid'])) 
																			{ echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST1;}
																		 else 
																			{echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST2;}?>';
													}
												}
											});
											//if the suggested kp is on holiday
											if (status == 'ONHOLIDAY' || status == 'NOTYETACTIVE') {
												var i=0;
												var obj1='';
												while ((status == 'ONHOLIDAY' || status == 'NOTYETACTIVE') && kpsList.length >= i) {
													obj1 = kpsList[i];
													if ($(obj1).find('.status').children('span').attr('class') != 'ONHOLIDAY' || $(obj1).find('.status').children('span').attr('class') != 'NOTYETACTIVE') {
														kpObject = kpsList[i];
														status = 'ACTIVE';
														break;
													}
													i+=1;
												}
												message = '<?php echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST4;?>';
											}
											//if not in the list, take the first active one
											if (status == null) {
												var j=0;
												var obj2='';
												while (kpsList.length >= j) {
													obj2 = kpsList[j];
													if ($(obj2).find('.status').children('span').attr('class') != 'ONHOLIDAY' || $(obj2).find('.status').children('span').attr('class') != 'NOTYETACTIVE') {
														kpObject = kpsList[j];
														status = 'ACTIVE';
														break;
													}
													j+=1;
												}
												message = '<?php echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST3;?>';
											}
										//if equal null, take the first active one
										} else {
											var c=0;
											var obj3='';
											while (kpsList.length >= c) {
												obj3 = kpsList[c];
												if ($(obj3).find('.status').children('span').attr('class') != 'ONHOLIDAY' || $(obj3).find('.status').children('span').attr('class') != 'NOTYETACTIVE') {
													kpObject = kpsList[c];
													status = 'ACTIVE';
													break;
												}
												c+=1;
											}
											message = '<?php echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST3;?>';
										}
										//if no kp found for replacement
										if (status == 'ONHOLIDAY' || status == 'NOTYETACTIVE' || status == null) {
											$('#kp-choice').html('<b><?php echo MODULE_SHIPPING_KIALAPOINT_NO_KP_FOUND;?></b>');
										} else {
											showSuggestKp(kpObject,message);
										}
									}
								}catch(e){$('#kp-validity').html('<Font color="red">Error1: </font>'+e);}
							});
		}
		
		//show the found kp suggestion
		function showSuggestKp(kpObj,message) {
			var img = $(kpObj).find('img').attr('src');
			var kpName = $(kpObj).find('.kpname').html();
			var kpID = $(kpObj).find('.kpshortid').html();
<?php
if ($dest_country == 'FR') {
?>
			kpID = '('+kpID.split(': ')[1]+')';
			kpID = kpID.replace('(','');
			kpID = kpID.replace(')','');
<?php
}
?>
			var kpCompleteAddress = $(kpObj).find('.address').html();
			var addressArray = (kpCompleteAddress.indexOf('<BR>') > 0) ? kpCompleteAddress.split('<BR>') : kpCompleteAddress.split('<br>');
			var kpAddress = addressArray[0];
			var zipcity = addressArray[1];
			var kpZip = zipcity.split(',',1)[0];
			var kpCity= zipcity.split(kpZip)[1];
			var locationHint = $(kpObj).find('.locationhint').html();
			var openningHours = new Array();
			$(kpObj).find('.odd , .even').each(function(){
				openningHours.push( $(this).find('.day').html() + ' | ' + $(this).find('.hours').html() );
			});
/* MODIFS */			
			$('#kp-choice').html('<div id="kp-status" style="visibility:hidden;">Activated</div>'
									+'<span id="kp-validity" style="color:red;"></span>'
									+'<table id ="kp-tab" width="100%">'
									+'<th colspan="3" id="mykp-message">'+message+'</th>'
									+'<tr><td align="center" rowspan="3" style="padding-right:5px;"><img style="max-width:145px;max-height:145px;" src='+img+' /></td>'
									+'<td align="left"><div id="mykp-name">'+kpName+'</div><div id="mykp-id" class="main">'+kpID+'</div></td>'
									+'<tr><td align="left"><div id="mykp-address" class="main">'+kpAddress+'</div><div id="mykp-zip" class="main">'+kpZip+'</div><div id="mykp-city" class="main">'+kpCity+'</div></td></tr>'
									+'<tr><td align="left" class="main"><i>'+locationHint+'</i></td></tr>'
<?php
if (!isset($_GET['shortkpid'])) {
?>
									+'<tr><td align="center" colspan="2"><div id="selectNextKp-toSelect" style="display:block; font-size: 20px; cursor:pointer;"><img onclick="selectKP(\''+kpID+'\',\'selectNextKp\')" src="<?php echo tep_output_string(DIR_WS_LANGUAGES . $language . '/images/buttons/kpid_choose.png'); ?>"></div><div id="selectNextKp-selected" style="display:none;"><img src="<?php echo tep_output_string(DIR_WS_LANGUAGES . $language . '/images/buttons/kpid_selected.png'); ?>"></div></td></tr>'
<?php
} else {
?>
									+'<tr><td align="center" colspan="2"><div id="selectNextKp-toSelect" style="display:none; font-size: 20px; cursor:pointer;"><img onclick="selectKP(\''+kpID+'\',\'selectNextKp\')" src="<?php echo tep_output_string(DIR_WS_LANGUAGES . $language . '/images/buttons/kpid_choose.png'); ?>"></div><div id="selectNextKp-selected" style="display:block;"><img src="<?php echo tep_output_string(DIR_WS_LANGUAGES . $language . '/images/buttons/kpid_selected.png'); ?>"></div></td></tr>'
<?php
}
?>
									+'</table>');
<?php
if (isset($_GET['shortkpid'])) {
?>
	selectKP(kpID,'selectNextKp');
<?php
}
/* MODIFS  EoF */	
?>
		}
//////////////////////////////////////////////////////////////////////
// LAST KP
//////////////////////////////////////////////////////////////////////
<?php
$ls_last_link = "https://locateandselect.kiala.com/details?countryid=". $dest_country ."&language=". $dest_country ."&map=off&align=left&shortID=";
?>
	
		function findLastKp(last_kp_id) {
			var startUrl = encodeURI("<?php echo $ls_last_link; ?>"+last_kp_id);
			var LastkpObject = null;
			var kp = null;
			var message = null;
			var status = null;			
			var kpSuggest = String(last_kp_id);
			
			jsonlib.fetch({
							url: startUrl,
							method: 'GET'
							},function (xml) {
								try {
									LastkpObject = $(xml.content);
									message = '<?php echo MODULE_SHIPPING_KIALAPOINT_LAST_KP;?>';
									showSuggestLastKp(LastkpObject,message);
								}catch(e){$('#last-kp-validity').html('<Font color="red">Error1: </font>'+e);}
							});
		}
		//show the found kp suggestion
		function showSuggestLastKp(kpObj,message) {
			var img = $(kpObj).find('img').attr('src');
			var kpTitle = $(kpObj).find('.name').html();
			var kpName = kpTitle.split('&nbsp;')[0];
			var kpID = $(kpObj).find('.kpshortid').html();
<?php
if ($dest_country == 'FR') {
?>
			kpID = '('+kpID.split(': ')[1]+')';
			kpID = kpID.replace('(','');
			kpID = kpID.replace(')','');
<?php
}
?>
			var kpCompleteAddress = $(kpObj).find('.address').html();
			var addressArray = (kpCompleteAddress.indexOf('<BR>') > 0) ? kpCompleteAddress.split('<BR>') : kpCompleteAddress.split('<br>');
			var kpAddress = addressArray[0];
			var zipcity = addressArray[1];
			var kpZip = zipcity.split(',',1)[0];
			var kpCity= zipcity.split(kpZip)[1];
			var locationHint = $(kpObj).find('.locationhint').html();
			var openningHours = new Array();
			$(kpObj).find('.odd , .even').each(function(){
				openningHours.push( $(this).find('.day').html() + ' | ' + $(this).find('.hours').html() );
			});
			
			if(typeof(img) != "undefined" && img !== null) {
				$('#kp-last-choice').html('<div id="mylastkpkp-status" style="visibility:hidden;">Activated</div>'
									+'<span id="last-kp-validity" style="color:red;"></span>'
									+'<table id ="mylastkpkp-tab" width="100%">'
									+'<th colspan="3" id="mylastkpkp-message">'+message+'</th>'
									+'<tr><td align="center" rowspan="3" style="padding-right:5px;"><img style="max-width:145px;max-height:145px;" src='+img+' /></td>'
									+'<td align="left"><div id="mylastkpkp-name">'+kpName+'</div><div id="mylastkpkp-id">'+kpID+'</div></td>'
									+'<tr><td align="left"><div id="mylastkpkp-address">'+kpAddress+'</div><div id="mylastkpkp-zip">'+kpZip+'</div><div id="mylastkpkp-city">'+kpCity+'</div></td></tr>'
									+'<tr><td align="left" class="main"><i>'+locationHint+'</i></td></tr>'
									+'<tr><td align="center" colspan="2"><div id="selectLastKp-toSelect" style="display:block; font-size: 20px; cursor:pointer;"><img src="<?php echo tep_output_string(DIR_WS_LANGUAGES . $language . '/images/buttons/kpid_choose.png'); ?>" onclick="selectKP(\''+kpID+'\',\'selectLastKp\')"></div><div id="selectLastKp-selected" style="display:none;"><img src="<?php echo tep_output_string(DIR_WS_LANGUAGES . $language . '/images/buttons/kpid_selected.png'); ?>"></div></td></tr>'
									+'</table>');
			}
			else {
				suppLastKp();
			}
		}
		
		function suppLastKp(){
			$('#content-kp-last-choice').hide('slow');
			$('#content-kp-choice').width('98%');
		}
//////////////////////////////////////////////////////////////////////
// IF KIALA IS SELECTED, A KP MUST BE CHOOSEN & SAVED
//////////////////////////////////////////////////////////////////////
function validateKpSelected() {
	var kpselected = document.getElementById('kpselected').value;
	var radios = document.getElementsByName('shipping');
	for (var i = 0, length = radios.length; i < length; i++) {
		if (radios[i].checked) {
			var shippingValue = radios[i].value;
		}
	}
	
	if ( (kpselected != '') || (shippingValue!='kialapoint_kialapoint') ) return true;
	else {
		alert('<?php echo MODULE_SHIPPING_KIALAPOINT_MUST_SELECT_KP; ?>');
		return false;
	}
}
function selectKP(kpselected,item) {
	document.getElementById('kpselected').value = kpselected;
	if (item=='selectLastKp'){
		var mylastkpname = $('#mylastkpkp-name').html();
		var mylastkpshortID = $('#mylastkpkp-id').html();
		var mylastkpaddress = $('#mylastkpkp-address').html();
		var mylastkpzip = $('#mylastkpkp-zip').html();
		var mylastkpcity = $('#mylastkpkp-city').html();
		SendToAddressCall(mylastkpname,mylastkpshortID,mylastkpaddress,mylastkpzip,mylastkpcity);
		
		document.getElementById('selectLastKp-selected').style.display = "block";
		document.getElementById('selectLastKp-toSelect').style.display = "none";
		
		document.getElementById('selectNextKp-selected').style.display = "none";
		document.getElementById('selectNextKp-toSelect').style.display = "block";
	}
	
	if (item=='selectNextKp') {
		var mykpname = $('#mykp-name').html();
		var mykpshortID = $('#mykp-id').html();
		var mykpaddress = $('#mykp-address').html();
		var mykpzip = $('#mykp-zip').html();
		var mykpcity = $('#mykp-city').html();
		SendToAddressCall(mykpname,mykpshortID,mykpaddress,mykpzip,mykpcity);
		
		if (document.getElementById('content-kp-last-choice').style.display == "block") {
			document.getElementById('selectLastKp-selected').style.display = "none";
			document.getElementById('selectLastKp-toSelect').style.display = "block";
		}
		
		document.getElementById('selectNextKp-selected').style.display = "block";
		document.getElementById('selectNextKp-toSelect').style.display = "none";
	}
}
//////////////////////////////////////////////////////////////////////
// DHTML tooltip
//////////////////////////////////////////////////////////////////////
var offsetxpoint=-60 //Customize x offset of tooltip
var offsetypoint=20 //Customize y offset of tooltip
var ie=document.all
var ns6=document.getElementById && !document.all
var enabletip=false
if (ie||ns6)
var tipobj=document.all? document.all["dhtmltooltip"] : document.getElementById? document.getElementById("dhtmltooltip") : ""
function ietruebody(){
return (document.compatMode && document.compatMode!="BackCompat")? document.documentElement : document.body
}
function ddrivetip(thecolor, thewidth){
var thetext='<?php echo MODULE_SHIPPING_KIALAPOINT_TITLE_HOVER; ?>';
if (ns6||ie){
if (typeof thewidth!="undefined") tipobj.style.width=thewidth+"px"
if (typeof thecolor!="undefined" && thecolor!="") tipobj.style.backgroundColor=thecolor
tipobj.innerHTML=thetext
enabletip=true
return false
}
}
function positiontip(e){
if (enabletip){
var curX=(ns6)?e.pageX : event.clientX+ietruebody().scrollLeft;
var curY=(ns6)?e.pageY : event.clientY+ietruebody().scrollTop;
//Find out how close the mouse is to the corner of the window
var rightedge=ie&&!window.opera? ietruebody().clientWidth-event.clientX-offsetxpoint : window.innerWidth-e.clientX-offsetxpoint-20
var bottomedge=ie&&!window.opera? ietruebody().clientHeight-event.clientY-offsetypoint : window.innerHeight-e.clientY-offsetypoint-20
var leftedge=(offsetxpoint<0)? offsetxpoint*(-1) : -1000
//if the horizontal distance isn't enough to accomodate the width of the context menu
if (rightedge<tipobj.offsetWidth)
//move the horizontal position of the menu to the left by it's width
tipobj.style.left=ie? ietruebody().scrollLeft+event.clientX-tipobj.offsetWidth+"px" : window.pageXOffset+e.clientX-tipobj.offsetWidth+"px"
else if (curX<leftedge)
tipobj.style.left="5px"
else
//position the horizontal position of the menu where the mouse is positioned
tipobj.style.left=curX+offsetxpoint+"px"
//same concept with the vertical position
if (bottomedge<tipobj.offsetHeight)
tipobj.style.top=ie? ietruebody().scrollTop+event.clientY-tipobj.offsetHeight-offsetypoint+"px" : window.pageYOffset+e.clientY-tipobj.offsetHeight-offsetypoint+"px"
else
tipobj.style.top=curY+offsetypoint+"px"
tipobj.style.visibility="visible"
}
}
function hideddrivetip(){
if (ns6||ie){
enabletip=false
tipobj.style.visibility="hidden"
tipobj.style.left="-1000px"
tipobj.style.backgroundColor='white'
tipobj.style.width='200'
}
}
document.onmousemove=positiontip
//////////////////////////////////////////////////////////////////////
// EoF
//////////////////////////////////////////////////////////////////////
	
		//save the new kp suggestion
		function SaveSuggestKPCall(kp){
			var url="<?php echo $host; ?>kiala_save_suggested_kp.php?";
			var params= "kp_id=" + kp;
			$.ajax({
				type: "GET",
				url: url,
				data: params,
				async: false,
				cache: false,
		   error:function(msg){
			 $('#kp-validity').html('<Font color="red">Error: </font>'+msg);
		   },
		   success:function(response){
		   }});
		} 
		 
		//split the kpopenninghours into a table
		function kpOpenHoursSplitJS(hours){
			var keyVal;
			var cnt='<table>';
			for (var i=0;i<hours.length;i=i+1) {
				keyVal = hours[i].split('|');
				cnt+='<tr><td>'+keyVal[0]+'</td><td>'+keyVal[1]+'</td></tr>';
			}
			cnt+='</table>';
			return cnt;
		}
		
		//trim strings
		function trim(stringToTrim) {
			return stringToTrim.replace(/^\s+|\s+$/g,"");
		}
		
		//change the address,save the new kp suggestion and submit the form
		function checkKp(){
			var form = $('body').find('form[name=checkout_address]');
			var button = $('body').find('form[name=checkout_address] :button');
			button.click(function() {
				var chosen_shipping = $('input[name=shipping]:checked').val();
				if ( chosen_shipping =='kialapoint_kialapoint' || typeof(chosen_shipping) == 'undefined' ) {
					if ($('#kp-status').html()=="Disactivated") {
							return false;
					} else {
						name = $('#mykp-name').html();
						shortID = $('#mykp-id').html();
						address = $('#mykp-address').html();
						zip = $('#mykp-zip').html();
						city = $('#mykp-city').html();
						SendToAddressCall(name,shortID,address,zip,city);
						SaveSuggestKPCall(FormatKPID($('#mykp-id').html(),'<?php echo $dest_country; ?>'));
						form.submit();
					}
				}
			});
		}
		
		//change the delivery address
		// Mahenina Kiala mobile
		function SendToAddressCall(kp_name,kp_id,kp_address,kp_zip,kp_city){
			var url="<?php echo DIR_WS_HTTP_CATALOG; ?>kiala_sendto_address_call.php?";
			var params= "kp_name=" + kp_name
						+ "&kp_id=" + kp_id
						+ "&kp_address=" + kp_address
						+ "&kp_zip=" + kp_zip
						+ "&kp_city=" + kp_city
						+ "&kp_order= <?php echo str_replace('&','.',http_build_query($order->delivery));?>";
			$.ajax({
				type: "GET",
				url: url,
				data: params,
				async: false,
				cache: false,
		   error:function(msg){
			 $('#kp-validity').html('<Font color="red">Error: </font>'+msg);
		   },
		   success:function(response){
		   }});
		}
		
		//show kiala module when chosen
		function showKiala(numItems) {
			if (numItems > 1) {
				var chosen_shipping = $('input[name=shipping]:checked').val();
				if ( 'kialapoint_kialapoint' == chosen_shipping ) {
					$('#KialaPoint').slideDown('slow');
				} else {
					$('#KialaPoint').slideUp('fast');
				}
			} else {
				$('#KialaPoint').slideDown('slow');
			}
		}
		function TestShowKiala(){
			$('input[name=shipping]').each(function(i) {
				var $element = $(this);
				// var element_parent = $element.parents('div:first'); // Mahenina changed to div
				var div_parent = $element.parents('div.ui-radio:first');
				var c_label = $('label',div_parent);
				var ship_add_parent = $(div_parent).parents(".ship_add:first");
				if($(c_label).hasClass('ui-radio-on')){
					if($('input[value=kialapoint_kialapoint]',div_parent).length>0){
						$("#KialaPointRow").show();
					}else{
						$("#KialaPointRow").hide();
					}
				}else{
					$("#KialaPointRow").hide();	
				}
			});
		}
		setInterval("TestShowKiala();",1000);
		//inject Kiala module into the page
		function injectKiala(numItems) {
			//  find reference to kiala_shipping method radio button
			$('input[name=shipping]').each(function(i) {
				var $element = $(this);
				// var element_parent = $element.parents('div:first'); // Mahenina changed to div
				var div_parent = $element.parents('div.ui-radio:first');
				var c_label = $('label',div_parent);
				var ship_add_parent = $(div_parent).parents(".ship_add:first");
				if($(c_label).hasClass('ui-radio-on')){
					//$('<div class="moduleRowSelected moduleRow"></div>').insertAfter(c_label);
					//var element_parent = $('div.moduleRowSelected:last');
				}
				if($('input[value=kialapoint_kialapoint]',div_parent).length>0){
					// alert("test");
					$('<div class="moduleRowSelected moduleRow"></div>').insertAfter(ship_add_parent);
					var element_parent = $('div.moduleRowSelected:last');
				}
				
				
				// var element_parent = $element.parents('tr');
				if ( $(element_parent).hasClass('moduleRow') || $(element_parent).hasClass('moduleRowSelected') ) {
					$(element_parent).click(function() {
						showKiala(numItems);
					});
				}
				if (numItems > 1) {
					if ( 'kialapoint_kialapoint' == $element.val() ) {
						var iframe_output = '<div id="KialaPointRow"><?php print $kp_selector; ?></div>';
						$(iframe_output).insertAfter(element_parent);
					}
				} else {
					var iframe_output = '<div id="KialaPointRow"><?php print $kp_selector; ?></div>';
						$(iframe_output).insertAfter(element_parent);
				}
			});
		}
		
		//when the page ready, then...
		$('document').ready(function() {
			var numItems = $('.moduleRow').length + $('.moduleRowSelected').length;
			injectKiala(numItems);
			<?php if (isset($_GET['shortkpid'])) { ?>
				var oldSelected = $('input[name=shipping]:checked').parents('tr') ;
				oldSelected.addClass('moduleRow');
				oldSelected.removeClass('moduleRowSelected');
				
				var newSelected = $('input:radio[name="shipping"]').filter('[value="kialapoint_kialapoint"]') ;
				newSelected.attr('checked', true);
				newSelected.parents('tr').addClass('moduleRowSelected');
				newSelected.parents('tr').removeClass('moduleRow');
			<?php } ?>
			showKiala(numItems);
			//showAdvertising();
			// checkKp();
			findSuggestKp();
			//more_info_box();
<?php
    if (substr(basename($PHP_SELF), 0, 8) == 'checkout') { // begin shipping estimator mod
//////////////////////////////////////////////////////////////////////
// LAST KP
//////////////////////////////////////////////////////////////////////
global $customer_id;
$country_array = array( 'Belgium' => 'BE' , 'Luxembourg' => 'LU' , 'France' => 'FR' , 'Spain' => 'ES' , 'Netherlands' => 'NL');
$select_qry = tep_db_query('SELECT *FROM `orders` WHERE `customers_id` ='. $customer_id .' AND delivery_name like \'KIALAPOINT%\' ORDER BY `orders_id` DESC LIMIT 0,1');
$orders_value = tep_db_fetch_array($select_qry);
$customerLocality = $country_array[$orders_value['delivery_country']];
$last_kp_name = $orders_value['delivery_name'];
} // end shipping estimator mod
if ($customerLocality != 'FR') {
	preg_match ( '#\((.*)\)#', $last_kp_name, $extract );
	$last_kp_id = $extract[1];
} else {
	$tab_kp_name = explode(',', $last_kp_name);
	$last_kp_id = substr($tab_kp_name[0],11);
}
if ( (isset($last_kp_id)) AND ($last_kp_id != '') ) {
?>
			findLastKp('<?php echo $last_kp_id; ?>');
<?php
} else {
?>
			suppLastKp();
<?php
}
//////////////////////////////////////////////////////////////////////
// LAST KP EoF
//////////////////////////////////////////////////////////////////////
?>
		});
		-->
		</script>
		<?php
		$js_output = ob_get_contents();
		ob_end_clean();
		
		$delay .= $js_output;
    }
		
    //cost and tax
	$table = preg_split("[:,]" , $cost);
    for ($i = 0; $i < sizeof($table); $i+=2) {
    	if ($shipping_weight > $table[$i]) continue;
    	$this->quotes['methods'][] = array(	'id'    => $this->code,
											'title' => $delay,
											'cost'  => $table[$i+1] + MODULE_SHIPPING_KIALAPOINT_HANDLING
										  );
		if ($this->tax_class > 0) {
        $this->quotes['tax'] = tep_get_tax_rate($this->tax_class,
                                                $order->delivery['country']['id'],
                                                $order->delivery['zone_id']);
		}
		// die(var_dump($this->quotes));
		return $this->quotes;
    }
//end mobile module
 } else { 
// begin classic module
	//define the quote object to return
    $this->quotes = array('id' => $this->code,
                          'module' => $this->title,
                          'methods' => array());
    
	//Add the Kiala module icon
	if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);
	
	//Select the appropriate kiala point for the connected user
	$selectkp = tep_db_query("select kp from kiala_client_suggest where id='".$customer_id."'");
	$fetchkp = tep_db_fetch_array($selectkp);
	if (tep_not_null($fetchkp['kp']))
		$kp_suggestion = $fetchkp['kp'];
	else
		$kp_suggestion = 'null';
		
	//define the parameters for the Kiala map
	$params = "";
	
	//Get and Add the site language to both parameters
	$languages_query = tep_db_query("select languages_id, code from " . TABLE_LANGUAGES . " order by sort_order");
	while ($languages = tep_db_fetch_array($languages_query)) {
		if ($languages['languages_id'] == $languages_id) {
			$lang = $languages['code'];
		}
	}
	$params .= "language=".urlencode($lang);
		
	//Add the country 
	$dest_country = $order->delivery['country']['iso_code_2'];
	$cust_country = $order->customer['country']['iso_code_2'];
	$params .= "&country=".urlencode($dest_country);
	
	//Add country for DSP ID
	if (preg_match('/^[3]{1}[0-9]{7}$/i', MODULE_SHIPPING_KIALAPOINT_DSPID)) {
		$params .= "&dspid=".urlencode(MODULE_SHIPPING_KIALAPOINT_DSPID);
	} else {
		$params .= "&dspid=".urlencode($dest_country);
	}
	
	//Add the city
	$dest_city = $order->delivery['city'];
	$cust_city = $order->customer['city'];
	$params .= "&city=".urlencode($dest_city);
	
	//Add the zip code
	$dest_zip = $order->delivery['postcode'];
	$cust_zip = $order->customer['postcode'];
	$params .= "&zip=".urlencode($dest_zip);
	
	//Add the street address
	$dest_street = $order->delivery['street_address'];
	$cust_street = $order->customer['street_address'];
	$params .= "&street=".urlencode($dest_street);
	//Add the map option to off for the first parameter
	$params .= "&map-controls=".urlencode("off");
	
	//Add the sort methode
	$params .= "&sort-method=".urlencode("ACTIVE_FIRST");
	
	//remove the thumbnails for the first parameter
	$params .= "&thumbnails=".urlencode("off");
	
	//Add the CSS path to the first parameter
	$params .= "&css=".urlencode("https://locateandselect/static/style/search/search_kiala_theme.cs");
	
	//Add the back URL to the firts parameter
	$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,5))=='https'?'https':'http';
	$host = 'https://'.$_SERVER["SERVER_NAME"].':'.$_SERVER["SERVER_PORT"].DIR_WS_CATALOG;
	$params .= "&bckUrl=".$host."checkout_shipping.php?";
	
	//Add the target
	$params .= "&target=_parent";
	
	//Kiala locate and select map link
	$ls_map_link = "https://locateandselect.kiala.com/locateandselect/search?".$params;
	//print($ls_map_link);
	
	if (isset($_GET["shortkpid"])) {
		// get cleanedup query string
		$str = (isset($_SERVER['QUERY_STRING']) ) ? $_SERVER['QUERY_STRING'] : '';
		// parse into array
		parse_str($str, $tvars);
		$vars = array();
		foreach ( $tvars as $key => $value ) {
		  $vars[$key] = $value;
		}
	}
	
	//split the kpopenninghours to a table
	if (!function_exists('kpOpenHoursSplit'))
	{
	function kpOpenHoursSplit($hours){
		$hoursTab=explode('-',$hours);
		$cnt='<table>';
		for ($i=0;$i<sizeof($hoursTab);$i=$i+1)
		{
			$subHoursTab=explode('.',$hoursTab[$i]);
			$cnt.='<tr><td>'.$subHoursTab[0].'</td><td>'.$subHoursTab[1].' - '.$subHoursTab[2].'</td></tr>';
		}
		$cnt.='</table>';
		return $cnt;
	}
	}
	
	//list of allowed countries
	$allowed_countries = array('BE','FR','NL','LU','ES');
	
	$delay = "";
	if (in_array($dest_country,$allowed_countries)) {
        $cost = MODULE_SHIPPING_KIALAPOINT_TARIFS;
        $delay = MODULE_SHIPPING_KIALAPOINT_TITLE . "<br>";
		$kp_selector = '<div id="KialaPoint">';
		$kp_selector .= '<div class="pghd">Punto Kiala</div><div style="font-size:11px;" width="630"> ';
		$kp_selector .= MODULE_SHIPPING_KIALAPOINT_INFO0; 
		$kp_selector .=	'<a rel="shadowbox" onclick="show_info();" style="color:red;">(Más info...)</a>';
		$kp_selector .= '<div id="kp-info-msg"></div></div>';
		$kp_selector .= '<div id="kp-choice" width="630" style="border: 2px solid #2781bb;"><div id="loading" align="center"><img src="images/suggest_kp_loading.gif"></div>';
		$kp_selector .= '<div id="kp-status" style="visibility:hidden; display: none;">Desactivado</div>';
		$kp_selector .= '<span id="kp-validity" style="color:red;"></span></div>';
		$kp_selector .= '<p align="center" style="margin: 10px;"><b>'.MODULE_SHIPPING_KIALAPOINT_KP_MAP.'</b></p>' ;
		$kp_selector .= '<div id="kp-field"><iframe id="kpiframe" width="732" height="450" src="'.$ls_map_link.'"></iframe></div>';
		$kp_selector .='</div>';
		
		
		//save all the javascript function into a variable and add it to $delay
		$js_output = '';
		ob_start();
		?>
		
		<SCRIPT language="javascript" src="<?php echo DIR_WS_CATALOG;?>ext/kialajs/jsonlib-src.js"></SCRIPT>
		<SCRIPT language="javascript" src="<?php echo DIR_WS_CATALOG;?>ext/kialajs/shadowbox/shadowbox.js"></SCRIPT>
		<link rel="stylesheet" type="text/css" href="<?php echo DIR_WS_CATALOG;?>ext/kialajs/shadowbox/shadowbox.css">
		<style type="text/css">	
			#dhtmltooltip {
			position: absolute;
			width: 300px;
			border: 2px solid #B6B7CB;
			padding: 2px;
			background-color: #F8F8F9;
			visibility: hidden;
			z-index: 100;
			}
		</style>	
		<script type="text/javascript">
		<!--
			Shadowbox.init();
			function show_info() {
				Shadowbox.open({
					content:    "<br><div style='color: black; background: none repeat scroll 0% 0% white; font-family: Verdana,Arial,Helvetica,Sans-Serif; font-size: 13px; padding-left: 15px; padding-right: 15px;' id='advertising-info'>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO1; ?><br><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO2; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO3; ?>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO4; ?><br><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO5; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO6; ?><br><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO7; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO8; ?>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO9; ?><br><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO10; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO11; ?><br><br>"
								+"<b><?php echo MODULE_SHIPPING_KIALAPOINT_INFO12; ?></b><br>"
								+"<?php echo MODULE_SHIPPING_KIALAPOINT_INFO13; ?></div><br>",
					player:     "html",
					title:      "Info",
					height:     220,
					width:      1220
				});
			};
		-->
		</script>
		<script type="text/javascript">
		<!--
		
		//reformat the KP ID
		function FormatKPID(kp,country) {
			if (country == "FR") {
				kp = kp.split('Relais Kiala: ')[1];
			} else if (country == "ES") {
				kp = kp.substr(1,4);
			} else /*BE or NL*/{
				kp = kp.substr(3,4);
			}
			return kp;
		}
		
		//find the KP to suggest to end customer
		function findSuggestKp() {
			var startUrl = encodeURI("<?php echo $ls_map_link; ?>");
			var kpObject = null;
			var kp = null;
			var message = null;
			var status = null;
			
			<?php if (isset($_GET['shortkpid'])) { ?>
				var kpSuggest = String('<?php echo $_GET['shortkpid']; ?>');
			<?php } else { ?>
				var kpSuggest = String('<?php echo $kp_suggestion; ?>');
			<?php } ?>
			jsonlib.fetch({
							url: startUrl,
							method: 'GET'
							},function (xml) {
									//var kpsList = jQuery(xml.content).find('.kp');
									var dmAux = new Element( 'div', {html:xml.content} );
									//var kpsList = jQuery(xml.content).find('div[class="kp"]');
									//if at least one kp available
									var kpsList = jQuery(dmAux).find('div[class="kp"]');
									
								
									if (kpsList.length > 0) {
										//if kp suggeggestion is not equal null and in the list
										if (kpSuggest != null) {
											kpsList.each(function(){
												kp = String(FormatKPID(jQuery(this).find('.kpshortid').html(),'<?php echo $dest_country; ?>'));
												if (kpSuggest == kp) {
													if (jQuery(this).find('.status').children('span').attr('class') == 'ONHOLIDAY') {
														status = 'ONHOLIDAY';
													} else if (jQuery(this).find('.status').children('span').attr('class') == 'NOTYETACTIVE'){
														status = 'NOTYETACTIVE';
													} else {
														status = 'ACTIVE';
														kpObject = this;
														message = '<?php if (isset($_GET['shortkpid'])) 
																			{ echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST1;}
																		 else 
																			{echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST2;}?>';
													}
												}
											});
											//if the suggested kp is on holiday
											if (status == 'ONHOLIDAY' || status == 'NOTYETACTIVE') {
												var i=0;
												var obj1='';
												while ((status == 'ONHOLIDAY' || status == 'NOTYETACTIVE') && kpsList.length >= i) {
													obj1 = kpsList[i];
													if (jQuery(obj1).find('.status').children('span').attr('class') != 'ONHOLIDAY' || jQuery(obj1).find('.status').children('span').attr('class') != 'NOTYETACTIVE') {
														kpObject = kpsList[i];
														status = 'ACTIVE';
														break;
													}
													i+=1;
												}
												message = '<?php echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST4;?>';
											}
											//if not in the list, take the first active one
											if (status == null) {
												var j=0;
												var obj2='';
												while (kpsList.length >= j) {
													obj2 = kpsList[j];
													if (jQuery(obj2).find('.status').children('span').attr('class') != 'ONHOLIDAY' || jQuery(obj2).find('.status').children('span').attr('class') != 'NOTYETACTIVE') {
														kpObject = kpsList[j];
														status = 'ACTIVE';
														break;
													}
													j+=1;
												}
												message = '<?php echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST3;?>';
											}
										//if equal null, take the first active one
										} else {
											var c=0;
											var obj3='';
											while (kpsList.length >= c) {
												obj3 = kpsList[c];
												if (jQuery(obj3).find('.status').children('span').attr('class') != 'ONHOLIDAY' || jQuery(obj3).find('.status').children('span').attr('class') != 'NOTYETACTIVE') {
													kpObject = kpsList[c];
													status = 'ACTIVE';
													break;
												}
												c+=1;
											}
											message = '<?php echo MODULE_SHIPPING_KIALAPOINT_KP_SUGGEST3;?>';
										}
										//if no kp found for replacement
										if (status == 'ONHOLIDAY' || status == 'NOTYETACTIVE' || status == null) {
											document.getElementById('kp-choice').innerHTML = '<b><?php echo MODULE_SHIPPING_KIALAPOINT_NO_KP_FOUND;?></b>';
											//jQuery('#kp-choice').html('<b><?php echo MODULE_SHIPPING_KIALAPOINT_NO_KP_FOUND;?></b>');
										} else {
											showSuggestKp(kpObject,message);
										}
									}
							});
		}
		
		//show the found kp suggestion
		function showSuggestKp(kpObj,message) {
			var img = jQuery(kpObj).find('img').attr('src');			
			var kpName = jQuery(kpObj).find('.kpname').html();		
			var kpID = jQuery(kpObj).find('.kpshortid').html();	
			var kpCompleteAddress = jQuery(kpObj).find('.address').html();		
			var kpAddress = kpCompleteAddress.split(new RegExp(/\<br\>/i))[0];		
			var zipcity = kpCompleteAddress.split(new RegExp(/\<br\>/i))[1];
			var kpZip = zipcity.split(' ',1)[0];
			var kpCity= zipcity.split(kpZip)[1];
			var locationHint = jQuery(kpObj).find('.locationhint').html();
			var openningHours = new Array();
			jQuery(kpObj).find('.odd , .even').each(function(){
				openningHours.push( jQuery(this).find('.day').html() + ' | ' + jQuery(this).find('.hours').html() );
			});
			
			
			document.getElementById('kp-choice').innerHTML = '<div id="kp-status" style="visibility:hidden; display: none;">Activated</div>'
									+'<span id="kp-validity" style="color:red;"></span>'
									+'<table id ="kp-tab" width="100%">'
									+'<th colspan="3" id="mykp-message">'+message+'</th>'
									+'<tr><td align="center" rowspan="3" style="padding-right:5px;"><img style="max-width:145px;max-height:145px;" src='+img+' /></td>'
									+'<td align="center"><div id="mykp-name">'+kpName+'</div><div id="mykp-id">'+kpID+'</div></td>'
									+'<tr><td align="center"><div id="mykp-address">'+kpAddress+'</div><div id="mykp-zip">'+kpZip+'</div><div id="mykp-city">'+kpCity+'</div></td></tr>'
									+'</table>'
									+'<tr><td align="center">'+locationHint+'</td></tr>'
			
			/*jQuery('#kp-choice').html('<div id="kp-status" style="visibility:hidden;">Activated</div>'
									+'<span id="kp-validity" style="color:red;"></span>'
									+'<table id ="kp-tab" width="100%">'
									+'<th colspan="3" id="mykp-message">'+message+'</th>'
									+'<tr><td align="center" rowspan="3" style="padding-right:5px;"><img style="max-width:145px;max-height:145px;" src='+img+' /></td>'
									+'<td align="center"><div id="mykp-name">'+kpName+'</div><div id="mykp-id">'+kpID+'</div></td>'
									+'<td align="center"rowspan="3"><div id="kpHours">'+kpOpenHoursSplitJS(openningHours)+'</div></td></tr>'
									+'<tr><td align="center"><div id="mykp-address">'+kpAddress+'</div><div id="mykp-zip">'+kpZip+'</div><div id="mykp-city">'+kpCity+'</div></td></tr>'
									+'<tr><td align="center">'+locationHint+'</td></tr>'
									+'</table>');*/
		}
		 
		//save the new kp suggestion
		function SaveSuggestKPCall(kp){
			var url="<?php echo $host; ?>kiala_save_suggested_kp.php?";
			var params= "kp_id=" + kp;
			jQuery.ajax({
				type: "GET",
				url: url,
				data: params,
				async: false,
				cache: false,
		   error:function(msg){
			 jQuery('#kp-validity').html('<Font color="red">Error: </font>'+msg);
		   },
		   success:function(response){
		   }});
		} 
		 
		//split the kpopenninghours into a table
		function kpOpenHoursSplitJS(hours){
			var keyVal;
			var cnt='<table>';
			for (var i=0;i<hours.length;i=i+1) {
				keyVal = hours[i].split('|');
				cnt+='<tr><td>'+keyVal[0]+'</td><td>'+keyVal[1]+'</td></tr>';
			}
			cnt+='</table>';
			return cnt;
		}
		
		//trim strings
		function trim(stringToTrim) {
			return stringToTrim.replace(/^\s+|\s+$/g,"");
		}
		
		//change the address,save the new kp suggestion and submit the form
		function checkKp(){
			var form = jQuery('body').find('form[name=checkout_payment]');
			var button = jQuery('#boton').find('input');
			
			button.click(function() {
				var chosen_shipping = jQuery('input[name=shipping]:checked').val();
				if ( chosen_shipping =='kialapoint_kialapoint' || typeof(chosen_shipping) == 'undefined' ) {
					if (jQuery('#kp-status').html()=="Disactivated") {
							return false;
					} else {
						name = jQuery('#mykp-name').html();
						shortID = jQuery('#mykp-id').html();
						address = jQuery('#mykp-address').html();
						zip = jQuery('#mykp-zip').html();
						city = jQuery('#mykp-city').html();
						SendToAddressCall(name,shortID,address,zip,city);
						SaveSuggestKPCall(FormatKPID(jQuery('#mykp-id').html(),'<?php echo $dest_country; ?>'));
						form.submit();
					}
				}
			});
		}
		
		//change the delivery address
		function SendToAddressCall(kp_name,kp_id,kp_address,kp_zip,kp_city){
			var url="<?php echo $host; ?>kiala_sendto_address_call.php?";
			var params= "kp_name=" + kp_name
						+ "&kp_id=" + kp_id
						+ "&kp_address=" + kp_address
						+ "&kp_zip=" + kp_zip
						+ "&kp_city=" + kp_city
						+ "&kp_order= <?php echo str_replace('&','.',http_build_query($order->delivery));?>";
			jQuery.ajax({
				type: "GET",
				url: url,
				data: params,
				async: false,
				cache: false,
		   error:function(msg){
			 jQuery('#kp-validity').html('<Font color="red">Error: </font>'+msg);
		   },
		   success:function(response){
		   }});
		}
		
		//show kiala module when chosen
		function showKiala(numItems) {
			if (numItems > 1) {
				var chosen_shipping = jQuery('input[name=shipping]:checked').val();
				if ( 'kialapoint_kialapoint' == chosen_shipping ) {
					jQuery('#KialaPoint').slideDown('slow');
				} else {
					jQuery('#KialaPoint').slideUp('fast');
				}
			} else {
				jQuery('#KialaPoint').slideDown('slow');
			}
		}
		
		//inject Kiala module into the page
		function injectKiala(numItems) {
			var dmInput = jQuery('input[value="kialapoint_kialapoint"]');
			var dmParent = dmInput.parent().parent().parent().parent();
			
			
			//jQuery(dmParent).after( '<div id="KialaPointRow"></div>' );	
			document.getElementById('dxkialap').innerHTML = '<?php echo $kp_selector; ?>'
			
			jQuery('input[name=shipping]').each(function(i)
			{
				jQuery(this).parent().parent().parent().click(function(e)
				{
					showKiala(numItems);
				});
			});
		
			//  find reference to kiala_shipping method radio button
			/*jQuery('input[name=shipping]').each(function(i) {
				var $element = jQuery(this);
				var element_parent = $element.parents('div');
				if ( jQuery(element_parent).hasClass('moduleRow') || jQuery(element_parent).hasClass('moduleRowSelected') ) {
					jQuery(element_parent).click(function() {
						showKiala(numItems);
					});
				}
				if (numItems > 1) {
					if ( 'kialapoint_kialapoint' == $element.val() ) {
						var iframe_output = '<div id="KialaPointRow"><?php print $kp_selector; ?></div>';
						jQuery(iframe_output).insertAfter(element_parent);
					}
				} else {
					var iframe_output = '<div id="KialaPointRow"><?php print $kp_selector; ?></div>';
						jQuery(iframe_output).insertAfter(element_parent);
				}
			});*/
		}
		
		//when the page ready, then...
		jQuery('document').ready(function() {
			var numItems = jQuery('.moduleRow').length + jQuery('.moduleRowSelected').length;
			injectKiala(numItems);
			<?php if (isset($_GET['shortkpid'])) { ?>
				var oldSelected = jQuery('input[name=shipping]:checked').parent().parent().parent();
				oldSelected.addClass('moduleRow');
				oldSelected.removeClass('moduleRowSelected');
				
				var newSelected = jQuery('input:radio[name="shipping"]').filter('[value="kialapoint_kialapoint"]') ;
				newSelected.attr('checked', true);
				newSelected.parent().parent().parent().addClass('moduleRowSelected');
				newSelected.parent().parent().parent().removeClass('moduleRow');
			
				/*var oldSelected = jQuery('input[name=shipping]:checked').parents('tr') ;
				oldSelected.addClass('moduleRow');
				oldSelected.removeClass('moduleRowSelected');
				
				var newSelected = jQuery('input:radio[name="shipping"]').filter('[value="kialapoint_kialapoint"]') ;
				newSelected.attr('checked', true);
				newSelected.parents('tr').addClass('moduleRowSelected');
				newSelected.parents('tr').removeClass('moduleRow');*/
			<?php } ?>
			showKiala(numItems);
			//showAdvertising();
			checkKp();
			findSuggestKp();
			//more_info_box();
			
		});
		-->
		</script>
		<?php
		$js_output = ob_get_contents();
		ob_end_clean();
		
		$delay .= $js_output;
    }
		
    //cost and tax
	$table = preg_split("[:,]" , $cost);
    for ($i = 0; $i < sizeof($table); $i+=2) {
    	if ($shipping_weight > $table[$i]) continue;
    	$this->quotes['methods'][] = array(	'id'    => $this->code,
											'title' => $delay,
											'cost'  => $table[$i+1] + MODULE_SHIPPING_KIALAPOINT_HANDLING
										  );
		if ($this->tax_class > 0) {
        $this->quotes['tax'] = tep_get_tax_rate($this->tax_class,
                                                $order->delivery['country']['id'],
                                                $order->delivery['zone_id']);
		}
		return $this->quotes;
    }
  } // end classic module
} // end methods
	
	//check function
    function check() {
		if (!isset($this->_check)) {
		$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_KIALAPOINT_STATUS'");
		$this->_check = tep_db_num_rows($check_query);
		}
		return $this->_check;
    }
	
	//Kiala module keys
	function keys() {
		return array('MODULE_SHIPPING_KIALAPOINT_STATUS',
					 'MODULE_SHIPPING_KIALAPOINT_TARIFS',
					 'MODULE_SHIPPING_KIALAPOINT_HANDLING',
					 'MODULE_SHIPPING_KIALAPOINT_TAX_CLASS',
					 'MODULE_SHIPPING_KIALAPOINT_SORT_ORDER'
					 );
	}
	
	//install Kiala module
	function install() {
		$active = MODULE_SHIPPING_KIALAPOINT_TITLE;
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Activation status of Kiala Point module', 'MODULE_SHIPPING_KIALAPOINT_STATUS', 'True', 'Activate/Deactivate the Kiala Point module', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Customer DSP ID', 'MODULE_SHIPPING_KIALAPOINT_DSPID', 'Please fill in this field with your DSP ID receieved from Kiala', 'Please fill in this field with your DSP ID receieved from Kiala', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sender country (Mandatory)', 'MODULE_SHIPPING_KIALAPOINT_COUNTRY', 'Please fill in this field with ONE of the folowing values :<br> BE or NL or FR or ES', 'Please advise from which country your parcels are going to be sent.', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Belgium - Kiala Sender ID', 'MODULE_SHIPPING_KIALAPOINT_BE_SENDER_ID', 'Mandatory field if your Sender country is BE!', 'For using this module in belgium, you need to register on kiala.be.', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Luxembourg - Kiala Sender ID', 'MODULE_SHIPPING_KIALAPOINT_LU_SENDER_ID', 'Mandatory field if your Sender country is LU!', 'For using this module in Luxembourg, you need to register on kiala.lu.', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('The Netherlands - Kiala Sender ID', 'MODULE_SHIPPING_KIALAPOINT_NL_SENDER_ID', 'Mandatory field if your Sender country is NL!', 'For using this module in The Netherlands, you need to register on kiala.nl', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('France - Kiala Sender ID', 'MODULE_SHIPPING_KIALAPOINT_FR_SENDER_ID', 'Mandatory field if your Sender country is FR!', 'For using this module in France, you need to register on kiala.fr', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Spain - Kiala Sender ID', 'MODULE_SHIPPING_KIALAPOINT_ES_SENDER_ID', 'Mandatory field if your Sender country is ES!', 'For using this module in Spain, you need to register on kiala.es', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Kiala Sender Password(Mandatory)', 'MODULE_SHIPPING_KIALAPOINT_SENDER_PASSWORD', 'Please fill in this field!', 'For using this module, you need to receive a password from Kiala.', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Tarifs', 'MODULE_SHIPPING_KIALAPOINT_TARIFS', '15:4.00', 'Max parcel weight = 15 kg and the default tarif is 4.00 euros.', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Fix tarif', 'MODULE_SHIPPING_KIALAPOINT_HANDLING', '0', 'Fix tarif : Packaging,etc', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Tax Class rate', 'MODULE_SHIPPING_KIALAPOINT_TAX_CLASS', '2', 'Apply the folowing tax rate on your shipping.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort Order', 'MODULE_SHIPPING_KIALAPOINT_SORT_ORDER', '0', 'Sort order of display.', '6', '0', now())");
		tep_db_query("create table kiala_orders_status(id int, status varchar(100))");
		tep_db_query("create table kiala_client_suggest(id int, kp varchar(20))");
	}
	
	//remove Kiala module
	function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
		tep_db_query("drop table kiala_orders_status");
		tep_db_query("drop table kiala_client_suggest");
	}
}
?>
