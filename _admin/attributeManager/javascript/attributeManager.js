var placeHolderDiv;
var url = 'attributeManager/attributeManager.php';
var debug = false;

var amRequester = new Requester();

function attributeManagerInit() {
	if(amRequester.isAvailable()) 
		amRefresh(true);
}

function getElement(id) {
	return document.getElementById(id);
}

function getDropDownValue(id) {
	var el = getElement(id);
	return el != null ? el.value : null;
}

function setDropDownValue(id,value,type) {
	var el = getElement(id);
	if(el == null){
		return;
	}
	switch(type){
		case 'i':
			el.value=value;
		break;
		case 's':
			for (var i=0; i < el.length; i++) {
				if (el[i].value == value) {
					el[i].selected = true;
				}
			}
			el.value=value;
		break;
	}
}

//------------------------------------------------------------------<< Common Stuff
function amSendRequest(requestString,functionName, refresh, target) {
	var arRequestString = new Array;

	if('' != requestString)
		arRequestString.push(requestString);
	
	if('' != productsId) 
		arRequestString.push('products_id='+productsId);
		
	if('' != pageAction)
		arRequestString.push('pageAction='+pageAction);
		
	if('' != sessionId)
		arRequestString.push(sessionId);

	if(refresh == false) 
		amRequester.setAction(amEmpty);	
	else 
		amRequester.setAction((((null == functionName) || ('' == functionName)) ? amUpdateContent : functionName));
	
	if(null == target) {
		amRequester.setTarget('attributeManager');
	}
	else {
		amRequester.setTarget(target);
		arRequestString.push('target='+target);
	}

	requestString = arRequestString.join('&');
	
	amRequester.loadURL(url, requestString);
	
	return false;
}


function amEmpty(){}

function amReportError(request) {
	alert('Sorry. There was an error.');
}

function amRefresh(bolFirstCall) {
	var rString = (!bolFirstCall) ? 'amAction=refresh' : '';
	amSendRequest(rString);
	return false;
}

function amUpdateContent(id) {
	getElement(amRequester.getTarget()).innerHTML = amRequester.getText();
	amRestoreDisplayState();
}

//------------------------------------------------------------------<< page Actions


function amSetInterfaceLanguage(languageId) {
	amSendRequest('amAction=setInterfaceLanguage&language_id='+languageId);
	return false;
}

function amUpdate(optionId, optionValueId, optionSender) {
	if (typeof optionSender=="undefined") {
		optionSender='na';
	}
	prefix=getDropDownValue('prefix_'+optionValueId);
	price=getDropDownValue('price_'+optionValueId);
	reference=getDropDownValue('reference_'+optionValueId);
	reference_prov=getDropDownValue('reference_prov_'+optionValueId);
	products_attributes_ean=getDropDownValue('products_attributes_ean_'+optionValueId);

	prefix_pr=getDropDownValue('prefix_pr_'+optionValueId);
	price_pr=getDropDownValue('price_pr_'+optionValueId);
	
	if((optionSender=='prefix')&&((prefix=='')||(prefix==' '))){
		price='0';
	}
	if(price.indexOf('-')==0){
		prefix='-';
		setDropDownValue('prefix_'+optionValueId,'-','s');
		price=price.substr(1);
	}
	price=parseFloat(price);
	if(isNaN(price)){
		price=0;
	}else{
		price*=10000;
		price=Math.round(price);
		price=price/10000;
	}
	price=price+'';
	if(price.indexOf(".")<0){
		price+='.';
	}
	while(price.length-price.indexOf(".")<5){
		price+='0';
	}
	
	
	if((optionSender=='prefix_pr')&&((prefix_pr=='')||(prefix_pr==' '))){
		price_pr='0';
	}
	if(price_pr.indexOf('-')==0){
		prefix_pr='-';
		setDropDownValue('prefix_pr_'+optionValueId,'-','s');
		price_pr=price_pr.substr(1);
	}
	price_pr=parseFloat(price_pr);
	if(isNaN(price_pr)){
		price_pr=0;
	}else{
		price_pr*=10000;
		price_pr=Math.round(price_pr);
		price_pr=price_pr/10000;
	}
	price_pr=price_pr+'';
	if(price_pr.indexOf(".")<0){
		price_pr+='.';
	}
	while(price_pr.length-price_pr.indexOf(".")<5){
		price_pr+='0';
	}
	
	setDropDownValue('price_'+optionValueId,price,'i');
	setDropDownValue('price_pr_'+optionValueId,price_pr,'i');
	setDropDownValue('reference_'+optionValueId,reference,'i');
	setDropDownValue('reference_prov_'+optionValueId,reference_prov,'i');
	setDropDownValue('products_attributes_ean_'+optionValueId,products_attributes_ean,'i');

	if((price!='0.0000')&&((prefix=='')||(prefix==' '))){
		setDropDownValue('prefix_'+optionValueId,'%2B','s');//+
	}

	if((price_pr!='0.0000')&&((prefix_pr=='')||(prefix_pr==' '))){
		setDropDownValue('prefix_pr_'+optionValueId,'%2B','s');//+
	}

    weight_prefix=getDropDownValue('weight_prefix_'+optionValueId);
    weight=getDropDownValue('weight_'+optionValueId);
    if ((weight != null) && (weight_prefix != null)) {
      if((optionSender=='weight_prefix')&&((weight_prefix=='')||(weight_prefix==' '))){
        weight='0';
      }
      if(weight.indexOf('-')==0){
        weight_prefix='-';
        setDropDownValue('weight_prefix_'+optionValueId,'-','s');
        weight=weight.substr(1);
      }
      weight=parseFloat(weight);
      if(isNaN(weight)){
        weight=0;
      }else{
        weight*=1000;
        weight=Math.round(weight);
        weight=weight/1000;
      }
      weight=weight+'';
      if(weight.indexOf(".")<0){
        weight+='.';
      }
      while(weight.length-weight.indexOf(".")<5){
        weight+='0';
      }
      setDropDownValue('weight_'+optionValueId,weight,'i');

      if((weight!='0.000')&&((weight_prefix=='')||(weight_prefix==' '))){
        setDropDownValue('weight_prefix_'+optionValueId,'%2B','s');//+
      }
    }

	// FIX 2026-05-05: encodeURIComponent en cada valor para evitar truncamiento de URL cuando hay caracteres
	// como '#', '&', '+', '%' en el valor (ej. modelo "01.138.15#PZ" — el '#' truncaba la querystring).
	amSendRequest('amAction=update&option_id='+encodeURIComponent(optionId)+'&option_value_id='+encodeURIComponent(optionValueId)+'&price='+encodeURIComponent(getDropDownValue('price_'+optionValueId))+'&price_pr='+encodeURIComponent(getDropDownValue('price_pr_'+optionValueId))+'&reference='+encodeURIComponent(getDropDownValue('reference_'+optionValueId))+'&reference_prov='+encodeURIComponent(getDropDownValue('reference_prov_'+optionValueId))+'&products_attributes_ean='+encodeURIComponent(getDropDownValue('products_attributes_ean_'+optionValueId))+'&prefix='+encodeURIComponent(getDropDownValue('prefix_'+optionValueId))+'&prefix_pr='+encodeURIComponent(getDropDownValue('prefix_pr_'+optionValueId))+'&sortOrder='+encodeURIComponent(getDropDownValue('sortOrder_'+optionValueId))+'&weight='+encodeURIComponent(getDropDownValue('weight_'+optionValueId))+'&weight_prefix='+encodeURIComponent(getDropDownValue('weight_prefix_'+optionValueId)),'',false);

	getElement('price_'+optionValueId).blur();
    if ((weight != null) && (weight_prefix != null)) getElement('weight_'+optionValueId).blur();
	var el = getElement('sortOrder_'+optionValueId);
	var el = getElement('reference_'+optionValueId);
	var el = getElement('reference_prov_'+optionValueId);
	var el = getElement('products_attributes_ean_'+optionValueId);
	if(el != null) el.blur();
	return false;
}

// QT Pro Plugin, modified by RusNN
function amUpdateProductStockQuantity(products_stock_id) {
	amSendRequest('amAction=updateProductStockQuantity&products_stock_id='+products_stock_id+'&productStockQuantity='+getDropDownValue('productStockQuantity_'+products_stock_id));
	return false;
}


// Control de stock POR VARIANTE: guarda products_attributes.check_stock al marcar
// el checkbox de la fila de stock del QT PRO (no permitir comprar sin stock,
// SOLO esa variante; OR con el check_stock global de la ficha).
function amUpdateVariantCheckStock(products_stock_id) {
	var el = document.getElementById('productStockCheck_'+products_stock_id);
	amSendRequest('amAction=updateVariantCheckStock&products_stock_id='+products_stock_id+'&variantCheckStock='+((el && el.checked) ? 1 : 0));
	return false;
}

var check = [];
function checkBox(id) {

    if(check[id] != true) //if a value is not true, use this rather than == false, 'cos the first time no value will be set and it will be undefined, not true or false
        {
        document.getElementById('imgCheck_' + id).src = "attributeManager/images/icon_unchecked.gif"; // change the image
        document.getElementById('stockTracking_' + id).value = "0"; //change the field value
        check[id] = false; //change the value for this checkbox in the array
        }
    else
        {
        document.getElementById('imgCheck_' + id).src = "attributeManager/images/icon_checked.gif";
        document.getElementById('stockTracking_' + id).value = "1";
        check[id] = true;
        }
}
    
// QT Pro Plugin

function amAddOption() {
	amSendRequest('amAction=addOption&options='+getAllPromptTextValues()+'&optionSort='+getDropDownValue('optionSortDropDown')+'&optionTrack='+getPromptHiddenValue('stockTracking_1'),'',true,'newAttribute');
	removeCustomPrompt();
	return false;
}

function amAddOptionValue(){
	var optionId = getDropDownValue('optionDropDown')
	amSendRequest('amAction=addOptionValue&option_values='+getAllPromptTextValues()+'&option_id='+optionId,'',true,'newAttribute');
	removeCustomPrompt();
	return false;
}

function amAddAttributeToProduct() {
	var option = getDropDownValue('optionDropDown');
	var optionValue = getDropDownValue('optionValueDropDown');
	var pricePrefix = getDropDownValue('prefix_0');
	var price = getDropDownValue('newPrice');
    var weightPrefix = getDropDownValue('weight_prefix_0');
	var weight = getDropDownValue('newWeight');
	var reference = getDropDownValue('reference_new');
	var reference_prov = getDropDownValue('reference_prov_new');
	var products_attributes_ean = getDropDownValue('products_attributes_ean_new');
	var sortOrder = -1;
	var pricePrPrefix = getDropDownValue('prefix_pr_0');
	var price_pr = getDropDownValue('newPricePr');

	
	if(0 == option || 0 == optionValue)
		return false;

	amSendRequest('amAction=addAttributeToProduct&option_id='+option+'&option_value_id='+optionValue+'&prefix='+pricePrefix+'&prefix_pr='+pricePrPrefix+'&reference='+reference+'&reference_prov='+reference_prov+'&products_attributes_ean='+products_attributes_ean+'&sortOrder='+sortOrder+'&price='+price+'&price_pr='+price_pr+'&weight_prefix='+weightPrefix+'&weight='+weight);
	return false;
}

function amRemoveOptionFromProduct() {
	amSendRequest('amAction=removeOptionFromProduct&option_id='+getPromptHiddenValue('option_id'));
	return false;
}

function amRemoveOptionValueFromProduct() {
	amSendRequest('amAction=removeOptionValueFromProduct&option_id='+getPromptHiddenValue('option_id')+'&option_value_id='+getPromptHiddenValue('option_value_id'));
	return false;
}

// Begin QT Pro Plugin - added by Phocea, modified by RusNN
function amAddStockToProduct(dropDownOptionsList) {
	// we rebuild the array
  	var dropDownOptions = dropDownOptionsList.split(/,/);
	if(0 == dropDownOptions.length)
		return false;
		
	var optionValue = new Array(dropDownOptions.length);
	
 	for(var i = 0; i < dropDownOptions.length; i++) {
 		optionValue[i] = getDropDownValue(dropDownOptions[i]);
 	}
	var stockQuantity = getDropDownValue('stockQuantity');
	
	var stockOptions = '';
	for(var i = 0; i < dropDownOptions.length; i++)
	{
 		stockOptions = stockOptions + dropDownOptions[i]+'='+optionValue[i]+'&';
 	}
	
	//customPrompt('debug',stockOptions+'stockQuantity:'+stockQuantity);
	amSendRequest('amAction=addStockToProduct&stockOptions='+stockOptions+'stockQuantity='+stockQuantity);
	//amSendRequest('amAction=RemoveStockOptionValueFromProduct&option_id='+stockQuantity);
	return false;
}

function amRemoveStockOptionValueFromProduct() {
	amSendRequest('amAction=removeStockOptionValueFromProduct&option_id='+getPromptHiddenValue('option_id'));
    removeCustomPrompt();
	return false;
}
// End QT Pro Plugin - added by Phocea

function amAddOptionValueToProduct(optionId) {
	var optionValueId = getDropDownValue('new_option_value_'+optionId);
	var reference = getDropDownValue('reference_'+optionId);
	var reference_prov = getDropDownValue('reference_prov_'+optionId);
	var products_attributes_ean = getDropDownValue('products_attributes_ean_'+optionId);
	if(0 == optionValueId)
		return false;
//	amSendRequest('amAction=addOptionValueToProduct&option_id='+optionId+'&option_value_id='+optionValueId,'',true,'currentAttributes');
	amSendRequest('amAction=addOptionValueToProduct&option_id='+optionId+'&reference='+reference+'&reference_prov='+reference_prov+'&products_attributes_ean='+products_attributes_ean+'&option_value_id='+optionValueId,'',true,'currentAttributes');
	return false;
}

function amAddNewOptionValueToProduct() {
	var optionId = getPromptHiddenValue('option_id');
	var optionValues = getAllPromptTextValues();
	//amSendRequest('amAction=addNewOptionValueToProduct&option_values='+optionValues+'&option_id='+optionId,'',true,'currentAttributes');
	amSendRequest('amAction=addNewOptionValueToProduct&option_values='+optionValues+'&option_id='+optionId,'',true,'currentAttributes');
	removeCustomPrompt();
	return false;
}

function amUpdateNewOptionValue(optionId) {
	amSendRequest('amAction=updateNewOptionValue&option_id='+optionId,'',true,'newAttribute');
	return false;
}


function loadTemplate() {
	var templateId = getDropDownValue('template_drop');
	amSendRequest('amAction=loadTemplate&template_id='+templateId);
	removeCustomPrompt();
	resetOpenClosedState();
}

function saveTemplate(){
	var newName = getAllPromptTextValues();
	var templateId = getElement("existing_template").value;
		
	amSendRequest('amAction=saveTemplate&new_template_id='+templateId+'&template_name='+newName,'',true,'topBar');
	removeCustomPrompt();
	return false;	
}

function renameTemplate() {
	var newName = getAllPromptTextValues();
	var templateId = getPromptHiddenValue('template_id');
	amSendRequest('amAction=renameTemplate&template_name='+newName+"&template_id="+templateId,'',true,'topBar');
	removeCustomPrompt();
	return false;	
}

function deleteTemplate() {
	var templateId = getDropDownValue('template_drop');
	amSendRequest('amAction=deleteTemplate&template_id='+templateId,'',true,'topBar');
	removeCustomPrompt();
}

function amTemplateOrder(order) {
	amSendRequest('amAction=setTemplateOrder&templateOrder='+order);
	return false;
}


//------------------------------------------------------------------<< custom prompts

function getAllPromptTextValues() {
	var allValues = getElement("popupContents").getElementsByTagName("input");
	var returnArray = new Array;
	for (var i = 0; i < allValues.length; i++) 
		if('text' == allValues[i].type) 
			returnArray.push(allValues[i].id+':'+escape((getElement(allValues[i].id).value)));
	return returnArray.join('|');
}

function getPromptHiddenValue(id) {
	if(getElement(id))
		return getElement(id).value;
	else 
		return false;
}

function customPrompt(section,getVars) {
	var requestString = 'amAction=prompt&section='+section
	if(null != getVars)
		requestString += '&gets='+getVars;
	amSendRequest(requestString, createCustomPrompt, true, 'prompt');
	return false;
}

function customTemplatePrompt(section) {
	var templateDrop = getElement('template_drop');
	var templateId = templateDrop.value;
	var templateName = templateDrop.options[templateDrop.selectedIndex].text;
	var requestString = 'amAction=prompt&section='+section+'&gets=template_name:'+templateName+'|'+'template_id:'+templateId;
	
	if(0 != templateId)
		amSendRequest(requestString, createCustomPrompt, true, 'prompt');
	else
		templateDrop.focus();
	
	return false;
}

function createCustomPrompt() {
 	var attributeManager = getElement("attributeManager");
 	var attributeManagerX = findPosX(attributeManager);
 	var attributeManagerY = findPosY(attributeManager)
 	var attributeManagerW = attributeManager.scrollWidth;
 	var attributeManagerH = attributeManager.scrollHeight;
 	
 	// cover the attribute manager with a semi tranparent div
 	newBit = attributeManager.appendChild(document.createElement("div"));
 	newBit.id = "blackout";
 	newBit.style.height = attributeManagerH;
 	newBit.style.width = attributeManagerW;
 	newBit.style.left = attributeManagerX;
 	newBit.style.top = attributeManagerY;
 	
 	// hide select boxes (for IE)
	showHideSelectBoxes('hidden'); 
	
	// create a popup shaddow
	popupShaddow = attributeManager.appendChild(document.createElement("div"));
	popupShaddow.id = "popupShaddow";
	
	// create the contents div
	popupContents = attributeManager.appendChild(document.createElement("div"));
	popupContents.id = "popupContents";
	
	// put the ajax reqest text in the box
	popupContents.innerHTML = amRequester.getText();
	
	// work out the center postion for the box
	leftPos = (((attributeManagerW - popupContents.scrollWidth) / 2) + attributeManagerX);
	topPos = (((attributeManagerH - popupContents.scrollHeight) / 2) + attributeManagerY);
	
	// position the box
	popupContents.style.left = leftPos;
	popupContents.style.top = topPos;
	
	// size the shadow
	popupShaddow.style.width = popupContents.scrollWidth;
	popupShaddow.style.height =popupContents.scrollHeight;
	
	// position the shadow
	popupShaddow.style.left = leftPos+6;
	popupShaddow.style.top = topPos+6;

	// if the form has any inputs focus on the first one
	if(inputs == popupContents.getElementsByTagName("input"))
		inputs[0].focus();
	
	return false;
}



function removeCustomPrompt() {
	getElement("attributeManager").removeChild(getElement("popupContents"));
	getElement("attributeManager").removeChild(getElement("popupShaddow"));
	getElement("attributeManager").removeChild(getElement("blackout"));
	showHideSelectBoxes('visible');	
}

function findPosX(obj) {
	var curleft = 0;
	if (obj.offsetParent){
		while (obj.offsetParent) {
			curleft += obj.offsetLeft
			obj = obj.offsetParent;
		}
	}
	else if (obj.x)
		curleft += obj.x;
	return curleft;
}

function findPosY(obj) {
	var curtop = 0;
	if (obj.offsetParent) {
		while (obj.offsetParent) {
			curtop += obj.offsetTop
			obj = obj.offsetParent;
		}
	}
	else if (obj.y)
		curtop += obj.y;
	return curtop;
}

function showHideSelectBoxes(vis) {
	var selects = getElement('attributeManager').getElementsByTagName("select");
	for(var i = 0; i < selects.length; i++) 
		selects[i].style.visibility = vis;
	return false;
}
//------------------------------------------------------------------<< Display Controls

var openClosedState;
var attributeManagerClosedState = true;
var attributeTemplatesClosedState = true;

function resetOpenClosedState() {
	 openClosedState = new Object()
}
resetOpenClosedState();

function amRestoreDisplayState() {

	// Im sure this is a really bad way to do this but i couldn't figure out another 
	var allTrs = getElement('attributeManager').getElementsByTagName("tr");
	for (var i = 0; i < allTrs.length; i++) {
		
		for(var a in openClosedState) {
			var reg = new RegExp("trOptionsValues_"+a+"$");
			if (reg.test(allTrs[i].id)) {
				if(true == openClosedState[a]) {
					allTrs[i].style.display =  "";
					getElement("show_hide_"+a).src = "attributeManager/images/icon_minus.gif";
				}
				else {
					allTrs[i].style.display =  "none";
					getElement("show_hide_"+a).src = "attributeManager/images/icon_plus.gif";
				}
			}
		}
	}
}

function amShowHideAttributeManager() {
	getElement('attributeManagerAll').style.display = (true == attributeManagerClosedState) ? "none" : "";
	attributeManagerClosedState = (true == attributeManagerClosedState) ? false : true;
	getElement('showHideAll').src = "attributeManager/images/icon_"+ ((true == attributeManagerClosedState) ? "minus.gif" : "plus.gif");
	return false;
}



function amShowHideAllOptionValues(options, show) {
	for(var i =0; i < options.length; i++) {
		openClosedState[options[i]] = !show;
		amShowHideOptionsValues(options[i]);
	}
	return false;
}

function amShowHideOptionsValues(id) {
	var allTrs = getElement('attributeManager').getElementsByTagName("tr");
	for (var i = 0; i < allTrs.length; i++) {
		
		var reg = new RegExp("trOptionsValues_"+id+"$");
		if (reg.test(allTrs[i].id)) 
			allTrs[i].style.display = (true == openClosedState[id]) ? "none" : "";
	}
	if(true == openClosedState[id]){
		getElement("show_hide_"+id).src = "attributeManager/images/icon_plus.gif";
		openClosedState[id] = false;
	}
	else{
		getElement("show_hide_"+id).src = "attributeManager/images/icon_minus.gif";
		openClosedState[id] = true;
	}
	return false;
}


function amF(i){
	if(i.value=='0.0000'){
		i.value='0.';
		i.select();
	}
}

function amB(i){
	if(i.value=='0.'){
		i.value='0.0000';
	}
}

//----------------------------
// Change: Add download attributes function for AM
// @author Urs Nyffenegger ak mytool
// Function: Javascript Functions
//-----------------------------
	
function amEditDownloadForProduct(){
	var products_attributes_filename = getDropDownValue('products_attributes_filename');
	var products_attributes_maxdays = getDropDownValue('products_attributes_maxdays');
	var products_attributes_maxcount = getDropDownValue('products_attributes_maxcount');
	var products_attributes_id = getPromptHiddenValue('products_attributes_id');
	
	amSendRequest('amAction=updateDownloadAttributeToProduct&option_id='+getPromptHiddenValue('option_id') + '&products_attributes_id='+products_attributes_id + '&products_attributes_filename=' + escape(products_attributes_filename) + '&products_attributes_maxdays=' + products_attributes_maxdays + '&products_attributes_maxcount=' + products_attributes_maxcount);
	removeCustomPrompt();
	return false;
	}

function amAddNewDownloadForProduct(){
	var products_attributes_filename = getDropDownValue('products_attributes_filename');
	var products_attributes_maxdays = getDropDownValue('products_attributes_maxdays');
	var products_attributes_maxcount = getDropDownValue('products_attributes_maxcount');
	var products_attributes_id = getPromptHiddenValue('products_attributes_id');
	
	amSendRequest('amAction=addDownloadAttributeToProduct&option_id='+getPromptHiddenValue('option_id') + '&products_attributes_id='+products_attributes_id + '&products_attributes_filename=' + escape(products_attributes_filename) + '&products_attributes_maxdays=' + products_attributes_maxdays + '&products_attributes_maxcount=' + products_attributes_maxcount);
	removeCustomPrompt();
	return false;
	}

function amDeleteDownloadForProduct(){
	var products_attributes_id = getPromptHiddenValue('products_attributes_id');
	
	amSendRequest('amAction=removeDownloadAttributeToProduct&products_attributes_id='+products_attributes_id );
	removeCustomPrompt();
	return false;
	}
	
function amMoveOptionValue(getVars, Direction){
	var requestString = 'amAction=moveOptionValue';

	if(null != getVars)
		requestString += '&gets='+getVars + '&dir=' + Direction;
		
	amSendRequest(requestString);
	return false;

}

function amMoveOption(getVars, Direction){
	var requestString = 'amAction=moveOption';

	if(null != getVars)
		requestString += '&gets='+getVars + '&dir=' + Direction;
		
	amSendRequest(requestString);
	return false;

}
//----------------------------
// EOF Change: download attributes for AM
//-----------------------------

//----------------------------
// Opcion 3: imagen principal por valor de atributo
// Sube/borra la imagen via attributeManager/amAttrImage.php y la guarda como
// accion change_image en products_attributes_actions (clave oid-vid).
//----------------------------
// URL del endpoint:
//  - anclada al ORIGEN ACTUAL (location.origin) para ignorar el <base href>, que es http
//    y romperia el fetch por mixed-content en una pagina https.
//  - con el SID (sessionId) en la query: el admin de osCommerce propaga la sesion por URL,
//    no solo por cookie; sin el, application_top redirige a login (302).
function amAttrImageEndpoint() {
	var base = location.origin + location.pathname.replace(/[^/]*$/, '') + 'attributeManager/amAttrImage.php';
	if(typeof sessionId !== 'undefined' && sessionId !== '')
		base += '?' + sessionId;
	return base;
}

function amAttrImageUpload(oid, vid, slot, input) {
	if(!input || !input.files || !input.files[0]) return false;
	var file = input.files[0];
	if(file.size > 3 * 1024 * 1024) {
		alert('La imagen es demasiado grande (maximo 3 MB).');
		input.value = '';
		return false;
	}
	var sfx = oid + '_' + vid + '_' + slot;
	var reader = new FileReader();
	reader.onload = function(e) {
		var data = new FormData();
		data.append('products_id', productsId);
		data.append('oid', oid);
		data.append('vid', vid);
		data.append('slot', slot);
		data.append('op', 'save');
		data.append('image', e.target.result);
		fetch(amAttrImageEndpoint(), { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function(r){ return r.json(); })
			.then(function(j){
				if(j && j.ok) {
					var thumb = getElement('amAttrImgThumb_' + sfx);
					if(thumb) { thumb.src = j.thumb; thumb.style.display = 'inline'; }
					var clear = getElement('amAttrImgClear_' + sfx);
					if(clear) clear.style.display = 'inline';
				} else {
					alert((j && j.error) ? j.error : 'No se pudo subir la imagen.');
				}
			})
			.catch(function(){ alert('Error de red al subir la imagen.'); });
		input.value = '';
	};
	reader.readAsDataURL(file);
	return false;
}

function amAttrImageClear(oid, vid, slot) {
	if(!confirm('Quitar esta imagen del valor?')) return false;
	var sfx = oid + '_' + vid + '_' + slot;
	var data = new FormData();
	data.append('products_id', productsId);
	data.append('oid', oid);
	data.append('vid', vid);
	data.append('slot', slot);
	data.append('op', 'clear');
	fetch(amAttrImageEndpoint(), { method: 'POST', body: data, credentials: 'same-origin' })
		.then(function(r){ return r.json(); })
		.then(function(j){
			if(j && j.ok) {
				var thumb = getElement('amAttrImgThumb_' + sfx);
				if(thumb) { thumb.style.display = 'none'; thumb.src = ''; }
				var clear = getElement('amAttrImgClear_' + sfx);
				if(clear) clear.style.display = 'none';
				var fileInput = getElement('amAttrImgFile_' + sfx);
				if(fileInput) fileInput.value = '';
			} else {
				alert((j && j.error) ? j.error : 'No se pudo quitar la imagen.');
			}
		})
		.catch(function(){ alert('Error de red al quitar la imagen.'); });
	return false;
}

//----------------------------
// Editar el nombre de un valor de atributo (products_options_values_name) en el idioma activo.
// Flujo: el campo nace readonly; al pulsar el lapiz (amAttrNameEdit) se desbloquea Y se muestra
// el desglose de productos afectados (op=info). Al salir del campo (amAttrNameBlur) se guarda.
// Endpoint attributeManager/amAttrName.php, anclado a location.origin + SID (ver amAttrImage).
//----------------------------
function amAttrNameEndpoint() {
	var base = location.origin + location.pathname.replace(/[^/]*$/, '') + 'attributeManager/amAttrName.php';
	if(typeof sessionId !== 'undefined' && sessionId !== '')
		base += '?' + sessionId;
	return base;
}

function amHtmlEscape(s) {
	return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function amAttrNameAffectedHtml(j) {
	var n = j.products || 0;
	if(n <= 1)
		return 'Solo este producto usa este valor. El cambio no afecta a ningun otro producto.';
	var html = '<b>Atencion:</b> este valor lo usan <b>' + n + ' productos</b>. Al renombrarlo cambiara en TODOS:';
	html += '<ul style="margin:3px 0 0 16px;padding:0;">';
	var list = j.list || [];
	for(var i=0;i<list.length;i++)
		html += '<li>#' + list[i].id + ' &mdash; ' + amHtmlEscape(list[i].name) + '</li>';
	if(j.more && j.more > 0)
		html += '<li>&hellip; y ' + j.more + ' productos mas</li>';
	html += '</ul>';
	return html;
}

// Pulsar el lapiz: desbloquea el campo y muestra el desglose de productos afectados
function amAttrNameEdit(oid, vid, lang) {
	// 2 campos por valor (uno por idioma): el input se identifica con sufijo de idioma.
	var input = getElement('amValName_' + oid + '_' + vid + '_' + lang);
	var box   = getElement('amValAffected_' + oid + '_' + vid);
	if(!input) return false;
	if(!input.readOnly) { input.focus(); return false; }

	if(box) { box.style.display = 'block'; box.innerHTML = 'Cargando productos afectados...'; }
	var data = new FormData();
	data.append('vid', vid);
	data.append('op', 'info');
	fetch(amAttrNameEndpoint(), { method: 'POST', body: data, credentials: 'same-origin' })
		.then(function(r){ return r.json(); })
		.then(function(j){
			if(box) box.innerHTML = (j && j.ok) ? amAttrNameAffectedHtml(j) : ((j && j.error) ? j.error : 'No se pudo obtener la lista.');
		})
		.catch(function(){ if(box) box.innerHTML = 'Error de red al obtener la lista de productos.'; });

	input.readOnly = false;
	input.style.background = '#fff';
	input.focus();
	input.select();
	return false;
}

// Volver al estado bloqueado (texto fijo)
function amAttrNameLock(oid, vid, input) {
	input.readOnly = true;
	input.style.background = '#f3f3f3';
	var box = getElement('amValAffected_' + oid + '_' + vid);
	if(box) { box.style.display = 'none'; box.innerHTML = ''; }
}

function amAttrNameSend(oid, vid, input) {
	var data = new FormData();
	data.append('vid', vid);
	data.append('op', 'save');
	data.append('name', input.value);
	// Idioma EXACTO en que se renderizo el nombre (data-lang), para que el guardado coincida
	// con lo que se ve/edita. Evita el desajuste por la sesion de idioma de la AM ($GLOBALS).
	var amLang = input.getAttribute('data-lang');
	if(amLang) data.append('lang', amLang);
	data.append('confirmed', 1); // el desglose de afectados ya se mostro al entrar en edicion
	fetch(amAttrNameEndpoint(), { method: 'POST', body: data, credentials: 'same-origin' })
		.then(function(r){ return r.json(); })
		.then(function(j){
			if(j && j.ok) {
				input.setAttribute('data-orig', input.value);
				input.style.background = '#d8f5d8';
				setTimeout(function(){ amAttrNameLock(oid, vid, input); }, 700);
			} else {
				alert((j && j.error) ? j.error : 'No se pudo guardar el nombre.');
				input.value = input.getAttribute('data-orig') || '';
				amAttrNameLock(oid, vid, input);
			}
		})
		.catch(function(){
			alert('Error de red al guardar el nombre.');
			input.value = input.getAttribute('data-orig') || '';
			amAttrNameLock(oid, vid, input);
		});
	return false;
}

// Al salir del campo: guarda si cambio, re-bloquea siempre
function amAttrNameBlur(oid, vid, input) {
	if(input.readOnly) return false;
	var v    = (input.value || '').replace(/^\s+|\s+$/g, '');
	var orig = input.getAttribute('data-orig') || '';
	if(v === '') {
		alert('El nombre no puede estar vacio.');
		input.value = orig;
		amAttrNameLock(oid, vid, input);
		return false;
	}
	if(v === orig) {
		amAttrNameLock(oid, vid, input);
		return false;
	}
	return amAttrNameSend(oid, vid, input);
}

//----------------------------
// Editar el NOMBRE DE LA OPCION ("Modelo", "Talla"...) por idioma. Mismo flujo que los valores
// (lapiz -> desbloquea + desglose de afectados via op=info_option; blur -> save_option).
// Limite 32 chars: el nombre baja a QFacWin (EA15_ARTPROP.CNOMPROP VARCHAR(32)).
//----------------------------
function amOptNameEdit(oid, lang) {
	var input = getElement('amOptName_' + oid + '_' + lang);
	var box   = getElement('amOptAffected_' + oid);
	if(!input) return false;
	if(!input.readOnly) { input.focus(); return false; }

	if(box) { box.style.display = 'block'; box.innerHTML = 'Cargando productos afectados...'; }
	var data = new FormData();
	data.append('oid', oid);
	data.append('op', 'info_option');
	fetch(amAttrNameEndpoint(), { method: 'POST', body: data, credentials: 'same-origin' })
		.then(function(r){ return r.json(); })
		.then(function(j){
			if(box) box.innerHTML = (j && j.ok) ? amOptNameAffectedHtml(j) : ((j && j.error) ? j.error : 'No se pudo obtener la lista.');
		})
		.catch(function(){ if(box) box.innerHTML = 'Error de red al obtener la lista de productos.'; });

	input.readOnly = false;
	input.style.background = '#fff';
	input.focus();
	input.select();
	return false;
}

function amOptNameAffectedHtml(j) {
	var n = j.products || 0;
	if(n <= 1)
		return 'Solo este producto usa esta opcion. El cambio no afecta a ningun otro producto.';
	var html = '<b>Atencion:</b> esta opcion la usan <b>' + n + ' productos</b>. Al renombrarla cambiara en TODOS:';
	html += '<ul style="margin:3px 0 0 16px;padding:0;">';
	var list = j.list || [];
	for(var i=0;i<list.length;i++)
		html += '<li>#' + list[i].id + ' &mdash; ' + amHtmlEscape(list[i].name) + '</li>';
	if(j.more && j.more > 0)
		html += '<li>&hellip; y ' + j.more + ' productos mas</li>';
	html += '</ul>';
	return html;
}

function amOptNameLock(oid, input) {
	input.readOnly = true;
	input.style.background = '#f3f3f3';
	var box = getElement('amOptAffected_' + oid);
	if(box) { box.style.display = 'none'; box.innerHTML = ''; }
}

function amOptNameSend(oid, input) {
	var data = new FormData();
	data.append('oid', oid);
	data.append('op', 'save_option');
	data.append('name', input.value);
	var amLang = input.getAttribute('data-lang');
	if(amLang) data.append('lang', amLang);
	data.append('confirmed', 1); // el desglose de afectados ya se mostro al entrar en edicion
	fetch(amAttrNameEndpoint(), { method: 'POST', body: data, credentials: 'same-origin' })
		.then(function(r){ return r.json(); })
		.then(function(j){
			if(j && j.ok) {
				input.setAttribute('data-orig', input.value);
				input.style.background = '#d8f5d8';
				setTimeout(function(){ amOptNameLock(oid, input); }, 700);
			} else {
				alert((j && j.error) ? j.error : 'No se pudo guardar el nombre.');
				input.value = input.getAttribute('data-orig') || '';
				amOptNameLock(oid, input);
			}
		})
		.catch(function(){
			alert('Error de red al guardar el nombre.');
			input.value = input.getAttribute('data-orig') || '';
			amOptNameLock(oid, input);
		});
	return false;
}

function amOptNameBlur(oid, input) {
	if(input.readOnly) return false;
	var v    = (input.value || '').replace(/^\s+|\s+$/g, '');
	var orig = input.getAttribute('data-orig') || '';
	if(v === '') {
		alert('El nombre no puede estar vacio.');
		input.value = orig;
		amOptNameLock(oid, input);
		return false;
	}
	if(v === orig) {
		amOptNameLock(oid, input);
		return false;
	}
	return amOptNameSend(oid, input);
}
