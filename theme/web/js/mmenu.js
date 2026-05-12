var mmenuClass;
var dmTargetOnly = false;

!function ($){
	mmenuClass = function(dmTarget, dmMenu, classOpen)
	{
		// Variables
		var self = this;
		var classOpen = classOpen != undefined ? classOpen : "mm-panel-open";
		var scrollTop = 0;
		var bodyHeight = 0;
		var getIdMenu = dmMenu.attr('id');

		// Creamos el contenedor de paneles
		var dmPanels = $("<div />", {"class": "panels"});

		// Damos evento para abrir menu
		dmTarget.click(function()
		{
			// Capa menu
			dmTargetOnly = $(this);

			// Si plegado obtenemos su posicion de scroll
			if (!$("html").hasClass(classOpen)) {
				scrollTop = $(window).scrollTop();
				$("body").css({"height": $("body").height(), "top": (scrollTop) * -1});

				// Si idioma esta abierto
				if ($("#head .icons .lnge").hasClass("actv") && !$("body").hasClass("fixed-head")) {
					$("#head .icons .lnge").trigger("click");
				}
				// Si login esta abierto
				if ($("#head .icons .lgin").hasClass("actv") && !$("body").hasClass("fixed-head")) {
					$("#head .icons .lgin").trigger("click");
				}
			}

			// Plegamos/desplegamos mediante su class
			$("html").toggleClass(classOpen);
			
			// Si esta desplegado ponemos el scroll donde se encontraba
			if (!$("html").hasClass(classOpen)) {
				$("body").attr("style", "");
				$([document.documentElement, document.body]).animate({scrollTop: scrollTop}, 0);
			}
		});

		// Capa cerrar
		if ($("#mm-close").length <= 0) {
			var dmClose = $("<div />", {"id": "mm-close"}).on("touchstart click", function(e)
			{
				e.stopPropagation(); e.preventDefault();
				dmTargetOnly.trigger("click");
			});
		}

		// Capa cerrar propia
		if(getIdMenu == "menu-panel") {
			var dmCloseSelf = $("<div />", {"class": "close"}).on("touchstart click", function(e)
			{
				e.stopPropagation(); e.preventDefault();
				dmTargetOnly.trigger("click");
			});
		}

		// Creamos capa para cerrar
		$("body").append(dmClose);

		// Obtenemos las listas
		var dmUls = $(dmMenu.find("ul").get().reverse());
		var total = dmUls.length - 1;

		// Recorremos para otorgarles index
		dmUls.each(function(i)
		{
			$(this).attr("data-id", i);
		});

		// Recorremos las listas
		dmUls.each(function()
		{
			// Indexs
			var nIndex = $(this).data("id");
			var nIndexParent = $(this).parent().parent().data("id");

			// Añadimos index
			$(this).parent().addClass("mm-child").attr("data-child", nIndex);

			// Creamos elemento
			var dmCreate = $("<div />", {"id": "mp" + nIndex,  "class": "mm mm-" + (nIndex ==  total ? "open" : "hidden") + ""}).html( '<div class="mp-titu" data-child="' + (nIndexParent == undefined ? '-1' : nIndexParent) + '"></div>' + $("<div />").append($(this).clone()).html() );

			// Creamos
			dmPanels.prepend(dmCreate);

			// Eliminamos
			$(this).remove();
		});

		// Creamos todo
		dmMenu.prepend(dmPanels);

		if(getIdMenu == "menu-panel") {
			dmMenu.append(dmCloseSelf);

			// Movemos
			app.get("responsive").moveElement($("#menu-panel-head"), dmMenu, "prepend");
		}
		
		// Cerrar aspa
		dmMenu.find(".fclose").on("touchstart click", function(e)
		{
			e.stopPropagation(); e.preventDefault();
			dmTargetOnly.trigger("click");
		});

		// Intentamos dar foco
		$(".panels .mm").on("touchstart",function()
		{
			$(this).blur();
			$(this).focus();
		});

		// Eventos para navegar entre los menus
		dmMenu.find(".mm-child").click(function()
		{
			var dmAux = $(this).closest(".mm");
			var dmOpen = $("#mp" + $(this).data("child"))

			dmAux.on("webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend", function(e){
    			$(this).addClass("mm-hidden");
    			$(this).removeClass("mm-open");
    			$(this).off("webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend");
 			});
 			dmAux.addClass("mm-panel_opened-parent");


			dmOpen.addClass("mm-panel_highest").removeClass("mm-hidden");

			setTimeout(function() {
				dmOpen.addClass("mm-open");
			}, 1);
		});

		// Evento para cerrar
		dmMenu.find(".mp-titu").click(function()
		{
			dmTargetOnly.trigger("click");
			return false;
		});
		

		// Evento para volver atras
		dmMenu.find(".mp-back").click(function()
		{
			var dmAux = $(this).closest(".mm");
			var dmOpen = $("#mp" + $(this).closest(".mm").find(".mp-titu").data("child"));

			dmAux.on("webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend", function(e){
    			$(this).addClass("mm-hidden")
    			$(this).off("webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend");
 			});
			dmAux.removeClass("mm-open").addClass("mm-panel_highest").removeClass("mm-panel_opened-parent");

			dmOpen.removeClass("mm-hidden").removeClass("mm-panel_highest").addClass("mm-open");
			setTimeout(function() {
				dmOpen.removeClass("mm-panel_opened-parent");
			 	//dmOpen.addClass("mm-open").removeClass("mm-panel_opened-parent");
			}, 1);
		});
	}
}(jQuery);
