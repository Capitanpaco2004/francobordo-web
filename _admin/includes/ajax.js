var xmlHttp;

function  createRequest() {
	if(window.ActiveXObject) {
		xmlHttp = new ActiveXObject("Microsoft.XMLHTTP");
	} else if(window.XMLHttpRequest) {
		xmlHttp = new XMLHttpRequest();
	}
}

function updateOrderField(orderID, table, field, pred) {
	var newValue = prompt("New Value:", pred);
	if ((newValue != null) && (newValue != '')) {
		createRequest();
		var url = "orders_ajax.php?action=update_order_field&oID=" + orderID + "&db_table=" + table + "&field=" + field + "&new_value=" + newValue;
		xmlHttp.open("GET", url, true);
		xmlHttp.onreadystatechange  =  StateChange;
		xmlHttp.send(null);
	}
}

function actualizarFactura(orderID, table, field, pred) {
	var newValue = prompt("New Value:", pred);
	if ((newValue != null) && (newValue != '')) {
		createRequest();
		var url = "edit_orders.php?action=actualizar_factura&oID=" + orderID + "&db_table=" + table + "&field=" + field + "&new_value=" + newValue;
		xmlHttp.open("GET", url, true);
		xmlHttp.onreadystatechange  =  StateChange;
		xmlHttp.send(null);
	}
}

function actualizarFacturaSerie(orderID, table, field, pred) {
	var newValue = prompt("New Value:", pred);
	if ((newValue != null) && (newValue != '')) {
		createRequest();
		var url = "edit_orders.php?action=actualizar_facturaserie&oID=" + orderID + "&db_table=" + table + "&field=" + field + "&new_value=" + newValue;
		xmlHttp.open("GET", url, true);
		xmlHttp.onreadystatechange  =  StateChange;
		xmlHttp.send(null);
	}
}

function updateProduct(orderID, productID, field, accion, extra, pred) {
	//molaria tener el valor actual en predeterminado...
	if (accion == 'eliminate') {
		if (confirm('Are you sure you want to eliminate this product from the order?')) {
			var newValue = 0;
			var url = "orders_ajax.php?action=eliminate&pID=" + productID + "&order=" + orderID + "&new_value=" + newValue;
		}
	} else if (accion == 'update') {
		if (field == 'options') {
			var newValue = prompt("Option:", pred);
			if ((newValue != null) && (newValue != '')) {
				newValue +=  "&option_price=" + prompt("Price:");
			}
		} else {
			var newValue = prompt("New Value:", pred);
		}
		var url = "orders_ajax.php?action=update_product&pID=" + productID + "&order=" + orderID + "&field=" + field + "&new_value=" + newValue;
		if (extra != '') {
			url += "&extra=" + extra;
		}
	}
	if ((accion == 'eliminate') || ((newValue != null) && (newValue != ''))) {
		createRequest();
		xmlHttp.open("GET", url, true);
		xmlHttp.onreadystatechange  =  StateChange;
		xmlHttp.send(null);
	}
}

function addProduct(orderID) {
	if (navigator.appName == "Microsoft Internet Explorer"){
		var largeur = screen.width;
		var hauteur = screen.height;
	} else {
		var largeur = window.innerWidth;
		var hauteur = window.innerHeight;
	}
	document.getElementById('add-Product').style.top = (hauteur/2)-100;
	document.getElementById('add-Product').style.left = (largeur/2)-100;
	document.getElementById('add-Product').style.display = 'block';
	document.getElementById('keywords').focus();
}

//this code is highly inspirated of AJAX Quick Search ( contrib: http://www.oscommerce.com/community/contributions,3413/category/search,ajax )
function loadXMLDoc(key) {
	var url="orders_ajax.php?action=search&keyword="+key;
	createRequest();
	xmlHttp.open("GET", url, true);
	xmlHttp.onreadystatechange  =  searchProduct;
	xmlHttp.send(null);
}

function selectProduct(prID, prName) {
	document.getElementById("addProductFind").innerHTML = prName;
	document.getElementById('addProductSearch').style.display= 'none';
	document.getElementById('addProductFind').style.display= 'block';
	var url="orders_ajax.php?action=attributes&prID=" + prID;
	createRequest();
	xmlHttp.open("GET", url, true);
	xmlHttp.onreadystatechange  = checkAttributes;
	xmlHttp.send(null);
}

function updateOrdersTotal (orderID, classe, pred, column) {
	var newValue = prompt("New Value:", pred);
	if (newValue != null){
		if (classe == 'value') newValue = parseFloat(newValue);
		var url="orders_ajax.php?action=orders_total_update&oID=" + orderID + "&column=" + column + "&class=" + classe + "&new_value=" + newValue;
		createRequest();
		xmlHttp.open("GET", url, true);
		xmlHttp.onreadystatechange = StateChange;
		xmlHttp.send(null);
	}
}

function createOrdersTotal(orderID){
	var newTitle = prompt("Concepto:");
	if ((newTitle != null) && (newTitle != '')){
		var newValue = prompt("Valor en euros:", "0.0000");
		if ((newValue != null) && (newValue != '')){
			var url = "orders_ajax.php?action=new_order_total&oID=" + orderID + "&title=" + newTitle + "&value=" + newValue;
			createRequest();
			xmlHttp.open("GET", url, true);
			xmlHttp.onreadystatechange = StateChange;
			xmlHttp.send(null);
		}
	}
}
function setAttr(){
	var oID = getURLGetElement('oID');
	var url="orders_ajax.php?action=set_attributes&oID=" + oID;
	//hay que recuperar los nombres de los selects y los valores eligidos para meterlos en una linea var postVar = "var1=valor1&var2=valor2"; que se enviara con xmlHttp.send(postVar);
	var postVar = "products_quantity=";
	postVar += prompt("unidades:", "1");
	for (var i = 0; i < document.attributes.elements.length; i++){
		postVar += '&' + document.attributes.elements[i].name + '=' + document.attributes.elements[i].value;
	}
	xmlHttp.open("POST", url, true);
hideAddProducts();
	xmlHttp.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
	xmlHttp.send(postVar);
	//ENGLISH
	//alert('Product added.' + "\n" + 'Refresh the browser to see the changes.');
	//FRANÇAIS
	//alert('Article ajouté.' + "\n" + 'Rafraichir le navigateur pour voir les changements.';
	//ESPAÑOL
	alert('Producto añadido' + "\n" + 'F5 para ver los cambios.');
}

function  StateChange() {
	if(xmlHttp.readyState == 4) {
		alert(xmlHttp.responseText);
	}
}

function  searchProduct() {
	if(xmlHttp.readyState == 4) {
		document.getElementById("quicksearch").innerHTML = xmlHttp.responseText;
	}
}

function checkAttributes() {
	if(xmlHttp.readyState == 4) {
		document.getElementById("ProdAttr").innerHTML = xmlHttp.responseText;
	}
}

function hideAddProducts() {
	document.getElementById('add-Product').style.display = 'none';
	document.getElementById('addProductFind').style.display = 'none';
	document.getElementById('ProdAttr').innerHTML = '&nbsp;';
	document.getElementById('keywords').value = '';
	document.getElementById('quicksearch').innerHTML = '';
	document.getElementById('addProductSearch').style.display = 'block';
}

function getURLGetElement(getEl) {
	var regexS = "[\\?&]"+getEl+"=([^&#]*)";
	var regex = new RegExp( regexS );
	var tmpURL = window.location.href;
	var results = regex.exec( tmpURL );
	if( results == null )
	    return "";
	  else
	    return results[1];
}