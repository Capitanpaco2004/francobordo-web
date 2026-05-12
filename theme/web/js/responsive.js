var responsiveClass;

!function ($){
	responsiveClass = function()
	{
		// Variables
		var self = this;

		// Lista de funciones a llamar cuando se realiza responsive
		this.aFunctionsCallResponsive = [];

		// Estado del responsive antes de cambiar al actual
		this.sLastResponsive;

		// Si estamos en versión web
		this.web = false;

		// Si estamos en versión tablet
		this.tablet = false;

		// Si estamos en versión movil
		this.movil = false;

		// Añadir una llamada al responsive
		this.addEventResponsive = function(fnFunction)
		{
			this.aFunctionsCallResponsive.push( fnFunction )
		}

		// Cuando redimensiona es llamada está funcion para comprobar las variables responsives
		this.resizingResponsive = function()
		{
			if( $("#responsive").length == 0 )
				alert("Error no se encuentra la capa responsive, crea dicha capa en el pie de página antes del cierre del body con el nombre id responsive.")

			// Variables
			var nAux = parseInt( $("#responsive").css("min-width") );

			// Añadimos todo a false
			self.web = false;
			self.tablet = false;
			self.movil = false;

			// Web, tablet y movil, establecemos  cual se está usando
			if( nAux == 1 ) self.web = true;
			if( nAux == 2 ) self.tablet = true;
			if( nAux >= 3 ) self.movil = true;

			// Si existe algun cambio realizamos el aviso que ha cambiado
			if( nAux != self.sLastResponsive )
			{
				// Guardamos que cambio de responsive ha sido
				self.sLastResponsive = nAux;

				// Realizamos llamadas a todas los eventos añadidos
				for( var nCont = 0; nCont < self.aFunctionsCallResponsive.length; nCont++ )
					if( self.aFunctionsCallResponsive[nCont] != undefined )
						self.aFunctionsCallResponsive[nCont].call()
			}
		}

		// Mueve elementos de posición
		this.moveElement = function(dmElement, dmTarget, sWhere, bCondition)
		{
			if( $.type(bCondition) === "boolean" && !bCondition )
				return false;

			var dmDetach = dmElement.detach();

			switch(sWhere)
			{
				case "before":
					dmTarget.before(dmDetach);
				break;

				case "after":
					dmTarget.after(dmDetach);
				break;

				case "append":
					dmTarget.append(dmDetach);
				break;

				case "prepend":
					dmTarget.prepend(dmDetach);
				break;
			}
		}

		// Iniciamos responsive
		this.init = function()
		{
			// Añadimos evento al resize
			app.get("kernel").addEventResize( this.resizingResponsive )
		}
	}
}(jQuery);