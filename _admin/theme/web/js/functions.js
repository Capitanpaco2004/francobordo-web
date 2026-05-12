var bFixed = false;
var dmNav = $(".toolbarHead");
var nHeightNav = dmNav.length > 0 ? dmNav.offset().top : 0;

$(document).ready(function()
{
	// Buscar en el menu lateral
	let inputMenuSearch = $("#admin-left-search input");
	let menuItems = $("#admin-left .menu > li");

	inputMenuSearch.keyup(function () {
		var val = $(this).val();

		if (val === "") {
			// Sin filtro: restaurar estado normal
			menuItems.each(function(){
				$(this).css("display", "");
				$(this).find(".sbmn a").css("display", "");
				if (!$(this).hasClass("actv")) {
					$(this).find(".sbmn").css("display", "");
				}
			});
			return;
		}

		var rxp = new RegExp(val, 'i');

		menuItems.each(function(){
			var li = $(this);
			var subLinks = li.find(".sbmn a:not(.prnt)");
			var hasMatch = false;

			subLinks.each(function(){
				if (rxp.test($(this).text())) {
					$(this).css("display", "");
					hasMatch = true;
				} else {
					$(this).css("display", "none");
				}
			});

			if (hasMatch) {
				li.css("display", "");
				li.find(".sbmn").css("display", "block");
			} else {
				li.css("display", "none");
			}
		});
	});

	// Dejar fijo el menu
	let fileUrl = document.location.href;
	let baseFileUrl = fileUrl.split("?")[0];

	let anchor = $("#admin-left").find("a[href='" + baseFileUrl + "']");

	if (anchor.length === 0) {
		anchor = $("#admin-left").find("a[href='" + fileUrl + "']");
	}

	if (anchor.length > 0) {
		while (true) {
			if (anchor.hasClass("menu")) {
				break;
			}

			if (anchor.prop("tagName") !== "LI" && anchor.prop("tagName") !== "A" && anchor.prop("tagName") !== "DIV") {
				anchor = anchor.parent();
				continue;
			}

			anchor.addClass("actv");
			anchor = anchor.parent();
		}
	}

	// Select de categorias
	if ($(".select-categories-search-result").length !== 0) {
		$('.select-categories-search').each(function(){
			$('.select-categories-search').select2({
				data: $.parseJSON($(this).next().text()),
				query: function (q) {
					var pageSize,
						results,
						that = this;
					pageSize = 100;
					results = [];

					if (q.term && q.term !== '') {
						results = _.filter(that.data, function (e) {
							return e.text.toUpperCase().indexOf(q.term.toUpperCase()) >= 0;
						});
					} else if (q.term === '') {
						results = that.data;
					}

					q.callback({
						results: results.slice((q.page - 1) * pageSize, q.page * pageSize),
						more: results.length >= q.page * pageSize,
					});
				},
			});
		});
	}

	// Estructura nueva //
	$("#admin-left .menu a.prnt").click(function()
	{
		$(this).parent().toggleClass("actv");
	});

	$("#icon-main").click(function()
	{
		$("body").toggleClass("menu-minz");

		$.ajax({ url: "index.php?action_global=admin_menu_left&value=" + ($("body").hasClass("menu-minz") ? "Plegado" : "") });
	});


	$("#admin-left").hover(function()
	{
		if( $("body").hasClass("menu-minz" ) )
		{
			$("body").removeClass("menu-minz");
			$("body").addClass("menu-minz-2");
		}
	},function()
	{
		if( $("body").hasClass("menu-minz-2" ) )
		{
			$("body").removeClass("menu-minz-2");
			$("body").addClass("menu-minz");
		}
	});

	// Fullscreen
	$("#cbcr .full").click( function()
	{
		$(this).toggleClass("open");

		if( !$(this).hasClass("open") )
		{
			if( document.exitFullscreen )
				document.exitFullscreen();
				else if( document.mozCancelFullScreen )
				document.mozCancelFullScreen();
				else if( document.webkitExitFullscreen )
				document.webkitExitFullscreen();
		}
		else
		{
			var el = document.documentElement;
			var rfs = el.requestFullScreen || el.webkitRequestFullScreen || el.mozRequestFullScreen;
			rfs.call(el);
		}
	});

	// Alert Denox
	$.ajax({
		url: "theme/web/html/alert.php"
	}).done(function(aJson)
	{
		var aJson = $.parseJSON( aJson );

		if( aJson.change )
			$("#cbcr .alrt").addClass("actv");

		$("#alrt-target").html(aJson.html);
		if (typeof tabs === "function") tabs();
	});

	$("#cbcr .alrt").click(function()
	{
		if( $(this).hasClass("actv") )
		{
			$.ajax({ url: "index.php?action_global=message_alert_denox"});
			$(this).removeClass("actv");
		}
	});

	// Scroll
	if ($.fn.scrollbar) $(".scll").scrollbar();

	// Tabs
	if (typeof tabs === "function") tabs();

	// Magnific-popup inline
	$(".mgp-inln").magnificPopup({type: 'inline', fixedContentPos: true, fixedBgPos: false, preloader: false, midClick: true, removalDelay: 300, mainClass: 'my-mfp-zoom-in'});
	// Estructura nueva //

	//Carga de ciudades a partir de Provincia
	jQuery( ".campo.getCitiesFromZone" ).on( "change", "#states select", function() {
		zone = jQuery(this).val();
		country = jQuery('select[name=country]').val()
		jQuery.post('create_account.php', {action: 'getCities', zone: zone, country: country}, function(data) {
			data = jQuery.parseJSON(data)
			jQuery('.campo.city').html(data.cities)
		})
	});

	//Carga de ciudades a partir de codigo postal
	jQuery( ".campo.getCitiesFromCP" ).on( "change", "#postcode", function() {
		postcode = jQuery(this).val();
		country = jQuery('select[name=country]').val()
		jQuery.post('create_account.php', {action: 'getCities', cp: postcode, country: country}, function(data) {
			data = jQuery.parseJSON(data)
			jQuery('.campo.city').html(data.cities)

			if (jQuery('select[name=country]').val() == data.id_country) {
				//jQuery('select[name=zone_id]').val(data.id_zone)
				jQuery('select[name=state] option[value="' + data.id_zone + '"]').prop('selected', true)
				//jQuery('select[name=zone_id] option[value="' + data.id_zone + '"]').attr('selected', 'selected')
				//jQuery('select[name=zone_id]').change()
			}
		})
	});

	//Obtenemos el cp seleccionado para autorrelenarlo
	jQuery( ".campo.city" ).on( "change", "select", function() {
		city = jQuery(this).find('option:selected').text();
		postcode = city.match(/\[(.*?)\]/)
		jQuery('input[name=postcode]').val(postcode[1])
	});


	if(typeof goOnLoad == 'function')
		goOnLoad();

	var getCatAjax = function( dmCat )
	{
		if( $( dmCat ).find( 'ul' ).length <= 0 )
		{
			// Peticion ajax para añadir siguiente bloque categorias
			$.ajax({
				url: "categories.php?action=add_catlist",
				method: 'POST',
				data:
				{
					id: dmCat.attr("data-id"),
					pid: dmCat.attr("data-prd"),
					current: dmCat.attr("data-crnt")
				}
			}).done(function(html)
			{
				dmCat.append(html);

					$(".li-event-new label").click( function()
					{
						dsplUl( $(this).parent().parent().find( 'ul' ) );
					});

				$(".li-event-new .check, .li-event-new .check :checkbox").uniform();

				$( ".li-event-new .check :checkbox" ).change( function()
				{
					if( $( ".hdn-chck-cat[data-id='" + $(this).val() + "']" ).length > 0 )
						$( ".hdn-chck-cat[data-id='" + $(this).val() + "']" ).prop( "checked", $(this).prop( "checked" ) );
				});

				$(".li-event-new").click( function() { getCatAjax( $(this) ); } ).removeClass("li-event-new");


			});
		}
	};

	$(".li-event-new").click( function() { getCatAjax( $(this) ); } ).removeClass("li-event-new");

	var dsplUl = function( dmUl )
	{
		if( dmUl.css( "display" ) != 'none' )
			dmUl.css( 'display', 'none' );
		else
			dmUl.first().css( 'display', 'block' );
	}

	$(".cat-dspl li label").click( function()
	{
		dsplUl( $(this).parent().parent().find( 'ul' ) );
	});
  
	// Inicio, repuestos
	var fnModificarPuntoRepuestos = function(nId)
	{
		// Fila para obtener los datos
		var dmTr = $("#table-repuestos tbody tr[data-id=" + nId + "]");

		// Punto en pantalla
		var dmPunto = $("#repuesto-imagen div.pnto[data-id=" + nId + "]");

		// Clase arriba, abajo, etc
		var sClass = dmTr.find("select[class=rp_posicion]").val()

		// Valor de tamaño
		var nSize = dmTr.find(".rp_size").val()

		// Añadimos la clase arriba, abajo, etc
		dmPunto.attr( "class", "pnto " + sClass );

		if( sClass == "top" || sClass == "bottom" )
		{
			dmPunto.height( nSize ).find("div").height( nSize ).width(1);
			dmPunto.height( nSize ).width(27);
		}
		else if( sClass == "dia_sup_drch" || sClass == "dia_inf_drch" || sClass == "dia_inf_izqd" || sClass == "dia_sup_izqd" )
		{
			dmPunto.height( nSize ).find("div").height( Math.sqrt( Math.pow(nSize, 2) + Math.pow(nSize, 2) ) ).width(1);
			dmPunto.height( nSize ).width(nSize);
		}
		else if( sClass == 'derecha' || sClass == 'izquierda' )
		{
			dmPunto.height( nSize ).find("div").height( 1 ).width(nSize);
			dmPunto.height( 27 ).width(nSize);
		}
	}

	// Eventos para la tabla de repuestos
	var fnEventTableRepuestos = function(nTotalTr)
	{
		// Creamos el spinner
		$("#table-repuestos tbody tr[data-id=" + nTotalTr + "]").find("input[class=rp_size]").spinner(
		{
			readonly: true,
			spin: function( event, ui )
			{
				fnModificarPuntoRepuestos( $(this).closest("tr").data("id") );
			}
		});

		// Boton eliminar
		$("#table-repuestos tbody tr[data-id=" + nTotalTr + "]").find("a[class*=buttonS]").click(function()
		{
			$("#repuesto-imagen div.pnto[data-id=" + $(this).closest("tr").data("id") + "]").remove();
			$(this).closest("tr").remove();
		});

		// Evento para cuando cambiamos la posicion
		$("#table-repuestos tbody tr[data-id=" + nTotalTr + "]").find("select[class=rp_posicion]").change(function()
		{
			fnModificarPuntoRepuestos( $(this).closest("tr").data("id") );
			// $("#repuesto-imagen div.pnto[data-id=" + $(this).closest("tr").data("id") + "]").attr( "class", "pnto " + $(this).val() );
		});

		// Evento para cuando cambiamos el alias
		$("#table-repuestos tbody tr[data-id=" + nTotalTr + "]").find("select[class=rp_alias]").change(function()
		{
			$("#repuesto-imagen div.pnto[data-id=" + $(this).closest("tr").data("id") + "]").find("span").html( $(this).val() );
		});

		// Evento drag
		$( "#repuesto-imagen .pnto" ).not(".ui-draggable").draggable({
			containment: "#repuesto-imagen",
			scroll: false,
			stop: function()
			{
				var offset = $(this).offset();
				var offsetParent = $("#repuesto-imagen").offset()

				var nLeft = offset.left - offsetParent.left;
				var nTop = offset.top - offsetParent.top;

				$("#table-repuestos tbody tr[data-id=" + $(this).data("id") + "]").find("input.rp_x").val(nLeft);
				$("#table-repuestos tbody tr[data-id=" + $(this).data("id") + "]").find("input.rp_y").val(nTop);
			}
		});
	};

	// Autocomplete products
	$("#repuesto-autocomplete").autocomplete(
	{
		source: "categories.php?action=autocomplete",
		minLength: 4,
		select: function( event, ui )
		{
			var sID = ui.item.id;
			var sName = ui.item.label;
			var sImage = ui.item.image;
			var sHtml = "";
			var nTotalTr = $("#table-repuestos tbody tr").length + 1;

			sHtml = "<tr data-id='" + nTotalTr + "'>";
				sHtml += "<td width='110' style='text-align: center;'>";
					sHtml += "<select class='rp_alias' name='rp_alias[]'>" + $("#select_alias_molde select").html() + "</select>";
					sHtml += "<input name='rp_products_id_repuesto[]' value='" + sID + "' type='hidden'>";
					sHtml += "<input class='rp_x' name='rp_x[]' value='0' type='hidden'>";
					sHtml += "<input class='rp_y' name='rp_y[]' value='0' type='hidden'>";
				sHtml += "</td>";
				sHtml += "<td width='110' style='text-align: center;'><select class='rp_posicion' name='rp_posicion[]'><option value='top'>&#8593; Arriba</option><option value='dia_sup_drch'>&#8599; Diagonal superior derecha</option><option value='derecha'>&#8594; Derecha</option><option value='dia_inf_drch'>&#8600; Diagonal inferior derecha</option><option value='bottom'>&#8595; Abajo</option><option value='dia_inf_izqd'>&#8601; Diagonal inferior izquierda</option><option value='izquierda'>&#8592; Izquierda</option><option value='dia_sup_izqd'>&#8598; Diagonal superior izquierda</option></select></td>";
				sHtml += "<td width='110' style='text-align: center;'><input readonly class='rp_size' name='rp_size[]' value='100'/></td>";
				sHtml += "<td width='110' style='text-align: center;'>" + sImage + "</td>";
				sHtml += "<td style='text-align: left;'>" + sName + "</td>";
				sHtml += "<td width='110' style='text-align: center;'><a href='javascript: void(0)' class='buttonS bRed'>Eliminar</a></td>";
			sHtml += "</tr>";

			// Creamos al fila en la tabla
			$("#table-repuestos tbody").append($(sHtml));

			// Creamos el indicador
			$("#repuesto-imagen").append( $('<div data-id="' + nTotalTr + '" class="pnto top" style="left: 0px; top: 0px;"><span></span><div></div></div>') );

			// LLamamos para recrear eventos
			fnEventTableRepuestos(nTotalTr);

			$(this).val("");
			return false;
		}
	});

	// Input file event, cuando se sube la imagen mostramos en base 64
	$("#repuesto-input-upload-imagen").change(function(event)
	{
		$.each(event.target.files, function(index, file)
		{
			var reader = new FileReader();
			reader.onload = function(event)
			{
				var sHtml = '<img width="750" height="450" src="' + event.target.result + '"/>';
				sHtml += '<input type="hidden" name="rp_image" value="' + event.target.result + '"/>';

				$("#repuesto-imagen .imge").html(sHtml);
				$("#repuestos_autocomplete").css("display", "block");

				if( $("#repuestos_autocomplete").prev().hasClass("msje") )
					$("#repuestos_autocomplete").prev().remove();
			};

			reader.readAsDataURL(file);
		});
	});

	// Boton que cuando es pulsado muestra la ventana file para subir archivo
	$("#repuesto-boton-upload-imagen").click(function()
	{
		$("#repuesto-input-upload-imagen").trigger("click");
	});

	// Boton para eliminar los repuestos e imagen
	$("#repuesto-boton-eliminar-imagen").click(function()
	{
		if( confirm( "Si eliminas la imagen se eliminara todos los repuestos, es necesario una imagen. ¿Realmente deseas eliminar todo?" ) )
		{
			$("#repuestos_autocomplete").css( "display", "none" );
			$("#table-repuestos tbody tr").remove();

			$("#repuesto-imagen .imge").html("<input name='rp_image' type='hidden' value='eliminar' />");
		}
	});

	// LLamamos para recrear eventos en repuestos
	if( $("#table-repuestos tbody").length > 0 && $("#table-repuestos tbody tr").length > 0 )
	{
		$("#table-repuestos tbody tr").each(function(nIndex, dmElement)
		{
			fnEventTableRepuestos(nIndex + 1);
		});
	}
	// Fin, repuestos

	// Inicio, mostrar desplegable para filtrar
	$(".filter-togle").click(function(e)
	{
		if( $(this).hasClass("act") )
			$(this).removeClass("act");
		else
			$(this).addClass("act");

		$(".tablePars[data-id='" + $(this).data("id") + "']").stop().slideToggle( "slow" );

	});
	// Fin, mostrar desplegable para filtrar

	// Combobox en categories.php de seo idioma, para cambiar entre idiomas
	$("#products_seo_idioma").change(function()
	{
		$(".tab-seo-idma").css("display", "none");
		$("#seo-" + $(this).val()).css("display", "block");
	});

	if( $("#products_seo_idioma").length > 0 )
		$("#products_seo_idioma").trigger( "change" );

	// Inicio, combobox idioma, para cambiar entre idiomas
	$(".change_idioma").change(function()
	{
		$(".tab-change-idma-" + $(this).data("id")).css("display", "none");
		$("#change-idma-" + $(this).data("id") + "-" + $(this).val()).css("display", "block");
	});

	if( $(".change_idioma").length > 0 )
		$(".change_idioma").trigger( "change" );
	// Fin, combobox idioma, para cambiar entre idiomas

	// Inicio, subir imagenes en el categories.php
	// Eliminar archivo
	function fileImageDelete()
	{
		if( confirm( "¿Deseas eliminar la imagen?" ) )
		{
			// Si contiene la clase aux es una imagen multiple de producto para eliminar directamente
			if( $(this).hasClass("plupload_accion_delete_aux") )
			{
				$(this).parent().parent().remove();
				return
			}

			jQuery("#dxbg").css( "display", "block" );
			jQuery("#dxload").css( "display", "block" );

			// Si estamos eliminando una imagen de produco guardamos el elemento
			if( jQuery(this).attr("data-type") == "products" )
				var dmElement = jQuery(this).parent().parent();

			// Elemento que hemos realizado click
			var dmThis = jQuery(this);

			// Peticion ajax para eliminar la imagen
			jQuery.ajax({
				url: "categories.php?action=delete_image",
				method: 'POST',
				data:
				{
					type:  jQuery(this).attr("data-type"),
					image: jQuery(this).attr("data-image"),
					id: jQuery(this).attr("data-id")
				}
			}).done(function()
			{
				// Si es categoria recargamos
				if( $(dmThis).attr("data-type") == "categories" )
					window.location.reload();
				else // Si es un producto
				{
					jQuery(dmElement).remove();
					jQuery("#dxbg").css( "display", "none" );
					jQuery("#dxload").css( "display", "none" );
				}
			});
		}
	}

	// Evento eliminar
	jQuery(".dlte-image-catg").click(fileImageDelete);

	// Funcion que se llama cuando seleccionamos un archivo a subir multiple para productos
	function handleFileSelect(evt)
	{
		// Seleccionamos todos los archivos que hemos subido
		var files = evt.target.files;

		// Recorremos
		for( var i = 0, f; f = files[i]; i++ )
		{
			// Solo imagenes
			if( !f.type.match('image.*') )
				continue;

			var reader = new FileReader();

			// Cargamos la informacion
			reader.onload = (function(theFile)
			{
				return function(e)
				{
					var sHtml = "";

					// Pintamos los elementos
					sHtml += '<td class="plupload_image"><div><div class="image-show-prod-ribbon"></div>' + ['<img width="80" height="80" src="', e.target.result, '" title="', escape(theFile.name), '"/>'].join('') + '</div></td>';
					sHtml += '<td><input type="hidden" name="products_subimages[]" value="' + e.target.result + '"/>' + escape(theFile.name) + '</td>';
					sHtml += '<td class="plupload_accion"><a data-click="false" class="plupload_accion_delete plupload_accion_delete_aux"><span class="icos-trash"></span></a></td>';
					$("#uploader_filelist").append( '<tr class="aux-disabl">' + sHtml + '</tr>' );

					// Evento para cuando borramos
					$("#uploader_filelist").find('a.plupload_accion_delete_aux[data-click*="false"]').attr("data-click", "true").click(fileImageDelete);
				};
			})(f);

			// Read in the image file as a data URL.
			reader.readAsDataURL(f);
		}

		// Eliminamos el input multiple file auxiliar
		$("#dx-file-images input").remove();
	}

	// Añadimos el evento click al boton falso para subir imagenes
	$("#dx-file-images-buttom").click(function()
	{
		// Creamos el input si este no existe
		if( $("#dx-file-images input").length == 0 )
		{
			$("<input />",
			{
				type: "file",
				style: "visibility: hidden; width: 1px; height: 1px; display: none; opacity: 0;",
				multiple: "",
				change: handleFileSelect
			}).appendTo("#dx-file-images");
		}

		// Lanzamos evento para la ventana de file upload
		$("#dx-file-images input").trigger("click");
	});

	// Ordenar imagenes de productos
	jQuery( "#drop-rows-image-prod" ).sortable({
		items: "tr:not(.aux-disabl)",
		placeholder: "ui-state-highlight",
		cursor: "move",
		over: function(e, ui)
		{
			sortableIn = 1;
		},
		out: function(e, ui)
		{
			sortableIn = 0;
		},
		receive: function(e, ui)
		{
			sortableIn = 1;
		},
		beforeStop: function(e, ui)
		{
			if (sortableIn == 1)
			{
				// Variables
				var sImagenes = "";
				var sId = "";

				// Obtenemos todas las imagenes
				$("#drop-rows-image-prod .dlte-image-catg").each(function(index,elmt)
				{
					sImagenes += $(elmt).attr("data-image") + ",";
					sId = $(elmt).attr("data-id");
				});

				// Loadding
				jQuery("#dxbg").css( "display", "block" );
				jQuery("#dxload").css( "display", "block" );

				// Peticion ajax para ordenar
				jQuery.ajax({
					url: "categories.php?action=image-order-product",
					method: 'POST',
					data:
					{
						id: sId,
						images: sImagenes
					}
				}).done(function()
				{
					jQuery("#dxbg").css( "display", "none" );
					jQuery("#dxload").css( "display", "none" );
				});
			}
		},
		helper: function(e)
		{
			return "<div style='background: #cecece; opacity: 0.3;' class='drag'></div>";
		}
	});
	// Fin, subir imagenes en el categories.php

	// Menu izquierdo de iconos
	$(".nav a").click(function(e)
	{
		if(e)e.stopPropagation();

		$(".nav a").removeClass("active");
		$(this).addClass("active");

		$(".tab-new").css("display", "none");
		$(".tab-new[data-id=" + $(this).attr("data-id") + "]").css("display", "block");
		$("input[name=menu-tab]").val($(this).attr("data-id"));

		$( window ).trigger("resize");
	});

	if($("input[name=menu-tab]").length > 0 && $("input[name=menu-tab]").val() !== 1){
		$("#box-left a[data-id=" + $("input[name=menu-tab]").val() + "]").trigger("click");
	}

	// Cambiar imagen
	$(".dx-hovr").hover(
		function()
		{
			$(this).attr( "src", $(this).attr( "src" ).replace( ".png", "_hover.png" ) );
		},
		function()
		{
			$(this).attr( "src", $(this).attr( "src" ).replace( "_hover.png", ".png" ) );
		}
	);
});



function rowOverEffect(elmt)
{
    elmt.style.backgroundColor = "#DADADA";
}

function rowOutEffect(elmt)
{
    if( elmt.className  != "dataTableRowSelected" )
        elmt.style.backgroundColor = "#F0F1F1";
}

var ddsmoothmenu={

//Specify full URL to down and right arrow images (23 is padding-right added to top level LIs with drop downs):
arrowimages: {down:['downarrowclass', 'theme/web/images/down.png', 23], right:['rightarrowclass', 'theme/web/images/right.png']},
transition: {overtime:300, outtime:300}, //duration of slide in/ out animation, in milliseconds
shadow: {enable:true, offsetx:5, offsety:5}, //enable shadow?
showhidedelay: {showdelay: 100, hidedelay: 200}, //set delay in milliseconds before sub menus appear and disappear, respectively

///////Stop configuring beyond here///////////////////////////

detectwebkit: navigator.userAgent.toLowerCase().indexOf("applewebkit")!=-1, //detect WebKit browsers (Safari, Chrome etc)
detectie6: document.all && !window.XMLHttpRequest,
css3support: window.msPerformance || (!document.all && document.querySelector), //detect browsers that support CSS3 box shadows (ie9+ or FF3.5+, Safari3+, Chrome etc)

getajaxmenu:function($, setting){ //function to fetch external page containing the panel DIVs
	var $menucontainer=$('#'+setting.contentsource[0]) //reference empty div on page that will hold menu
	$menucontainer.html("Loading Menu...")
	$.ajax({
		url: setting.contentsource[1], //path to external menu file
		async: true,
		error:function(ajaxrequest){
			$menucontainer.html('Error fetching content. Server Response: '+ajaxrequest.responseText)
		},
		success:function(content){
			$menucontainer.html(content)
			ddsmoothmenu.buildmenu($, setting)
		}
	})
},


buildmenu:function($, setting){
	var smoothmenu=ddsmoothmenu
	var $mainmenu=$("#"+setting.mainmenuid+">ul") //reference main menu UL
	$mainmenu.parent().get(0).className=setting.classname || "ddsmoothmenu"
	var $headers=$mainmenu.find("ul").parent()
	$headers.hover(
		function(e){
			$(this).children('a:eq(0)').addClass('selected')
		},
		function(e){
			$(this).children('a:eq(0)').removeClass('selected')
		}
	)
	$headers.each(function(i){ //loop through each LI header
		var $curobj=$(this).css({zIndex: 100-i}) //reference current LI header
		var $subul=$(this).find('ul:eq(0)').css({display:'block'})
		$subul.data('timers', {})
		this._dimensions={w:this.offsetWidth, h:this.offsetHeight, subulw:$subul.outerWidth(), subulh:$subul.outerHeight()}
		this.istopheader=$curobj.parents("ul").length==1? true : false //is top level header?
		$subul.css({top:this.istopheader && setting.orientation!='v'? this._dimensions.h+"px" : 0})
		$curobj.children("a:eq(0)").css(this.istopheader? {paddingRight: smoothmenu.arrowimages.down[2]} : {}).append( //add arrow images
			'<img src="'+ (this.istopheader && setting.orientation!='v'? smoothmenu.arrowimages.down[1] : smoothmenu.arrowimages.right[1])
			+'" class="' + (this.istopheader && setting.orientation!='v'? smoothmenu.arrowimages.down[0] : smoothmenu.arrowimages.right[0])
			+ '" style="border:0;" />'
		)
		if (smoothmenu.shadow.enable && !smoothmenu.css3support){ //if shadows enabled and browser doesn't support CSS3 box shadows
			this._shadowoffset={x:(this.istopheader?$subul.offset().left+smoothmenu.shadow.offsetx : this._dimensions.w), y:(this.istopheader? $subul.offset().top+smoothmenu.shadow.offsety : $curobj.position().top)} //store this shadow's offsets
			if (this.istopheader)
				$parentshadow=$(document.body)
			else{
				var $parentLi=$curobj.parents("li:eq(0)")
				$parentshadow=$parentLi.get(0).$shadow
			}
			this.$shadow=$('<div class="ddshadow'+(this.istopheader? ' toplevelshadow' : '')+'"></div>').prependTo($parentshadow).css({left:this._shadowoffset.x+'px', top:this._shadowoffset.y+'px'})  //insert shadow DIV and set it to parent node for the next shadow div
		}
		$curobj.hover(
			function(e){
				var $targetul=$subul //reference UL to reveal
				var header=$curobj.get(0) //reference header LI as DOM object
				clearTimeout($targetul.data('timers').hidetimer)
				$targetul.data('timers').showtimer=setTimeout(function(){
					header._offsets={left:$curobj.offset().left, top:$curobj.offset().top}
					var menuleft=header.istopheader && setting.orientation!='v'? 0 : header._dimensions.w
					menuleft=(header._offsets.left+menuleft+header._dimensions.subulw>$(window).width())? (header.istopheader && setting.orientation!='v'? -header._dimensions.subulw+header._dimensions.w : -header._dimensions.w) : menuleft //calculate this sub menu's offsets from its parent
					if ($targetul.queue().length<=1){ //if 1 or less queued animations
						$targetul.css({left:menuleft+"px", width:header._dimensions.subulw+'px'}).animate({height:'show',opacity:'show'}, ddsmoothmenu.transition.overtime)
						if (smoothmenu.shadow.enable && !smoothmenu.css3support){
							var shadowleft=header.istopheader? $targetul.offset().left+ddsmoothmenu.shadow.offsetx : menuleft
							var shadowtop=header.istopheader?$targetul.offset().top+smoothmenu.shadow.offsety : header._shadowoffset.y
							if (!header.istopheader && ddsmoothmenu.detectwebkit){ //in WebKit browsers, restore shadow's opacity to full
								header.$shadow.css({opacity:1})
							}
							header.$shadow.css({overflow:'', width:header._dimensions.subulw+'px', left:shadowleft+'px', top:shadowtop+'px'}).animate({height:header._dimensions.subulh+'px'}, ddsmoothmenu.transition.overtime)
						}
					}
				}, ddsmoothmenu.showhidedelay.showdelay)
			},
			function(e){
				var $targetul=$subul
				var header=$curobj.get(0)
				clearTimeout($targetul.data('timers').showtimer)
				$targetul.data('timers').hidetimer=setTimeout(function(){
					$targetul.animate({height:'hide', opacity:'hide'}, ddsmoothmenu.transition.outtime)
					if (smoothmenu.shadow.enable && !smoothmenu.css3support){
						if (ddsmoothmenu.detectwebkit){ //in WebKit browsers, set first child shadow's opacity to 0, as "overflow:hidden" doesn't work in them
							header.$shadow.children('div:eq(0)').css({opacity:0})
						}
						header.$shadow.css({overflow:'hidden'}).animate({height:0}, ddsmoothmenu.transition.outtime)
					}
				}, ddsmoothmenu.showhidedelay.hidedelay)
			}
		) //end hover
	}) //end $headers.each()
	if (smoothmenu.shadow.enable && smoothmenu.css3support){ //if shadows enabled and browser supports CSS3 shadows
		var $toplevelul=$('#'+setting.mainmenuid+' ul li ul')
		var css3shadow=parseInt(smoothmenu.shadow.offsetx)+"px "+parseInt(smoothmenu.shadow.offsety)+"px 5px #aaa" //construct CSS3 box-shadow value
		var shadowprop=["boxShadow", "MozBoxShadow", "WebkitBoxShadow", "MsBoxShadow"] //possible vendor specific CSS3 shadow properties
		for (var i=0; i<shadowprop.length; i++){
			$toplevelul.css(shadowprop[i], css3shadow)
		}
	}
	$mainmenu.find("ul").css({display:'none', visibility:'visible'})
},

init:function(setting){
	if (typeof setting.customtheme=="object" && setting.customtheme.length==2){ //override default menu colors (default/hover) with custom set?
		var mainmenuid='#'+setting.mainmenuid
		var mainselector=(setting.orientation=="v")? mainmenuid : mainmenuid+', '+mainmenuid
		document.write('<style type="text/css">\n'
			+mainselector+' ul li a {background:'+setting.customtheme[0]+';}\n'
			+mainmenuid+' ul li a:hover {background:'+setting.customtheme[1]+';}\n'
		+'</style>')
	}
	this.shadow.enable=(document.all && !window.XMLHttpRequest)? false : this.shadow.enable //in IE6, always disable shadow
	jQuery(document).ready(function($){ //ajax menu?
		if (typeof setting.contentsource=="object"){ //if external ajax menu
			ddsmoothmenu.getajaxmenu($, setting)
		}
		else{ //else if markup menu
			ddsmoothmenu.buildmenu($, setting)
		}
	})
}

} //end ddsmoothmenu variable



// Dropdown
$('.dropdown-toggle').dropdown();

// Toogle option para buscador
$.fn.toggleOption = function (show) {
	$(this).toggle(show);
	if (show) {
		if ($(this).parent('span.toggleOption').length)
			$(this).unwrap();
	} else {
		if ($(this).parent('span.toggleOption').length === 0)
			$(this).wrap('<span class="toggleOption" style="display: none;" />');
	}
};

// Detectar el evento "load" y limpiar localStorage si no estamos en orders.php
window.addEventListener('load', function() {
	const currentPath = window.location.pathname;

	// Limpiar localStorage si no estamos en orders.php
	if (!currentPath.includes('/orders.php')) {
		localStorage.removeItem('selectedOrderId');
	}
});
