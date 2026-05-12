var eventClass;
var event;

!function ($){
	eventClass = function()
	{
		// Variables
		this.eventsLoad = new Object();
		
		/**
		* Añade un evento a la cola de ventos
		* @param string sEvent Evento a ejecutar
		* @param string fnFunction Funcion a realizar
		* @param array aArguments Argumentos que queremos pasarle a la funcion del evento
		*/
		this.add = function(sEvent, fnFunction, aArguments)
		{
			// Variables
			sEvent = sEvent.toLowerCase();

			// Si no existe lo creamos
			if( !(sEvent in this.eventsLoad) )
				this.eventsLoad[sEvent] = [];
			
			// Vamos añadiendo funciones
			this.eventsLoad[sEvent][this.eventsLoad[sEvent].length] = {"execute": fnFunction, "arguments": $.type( aArguments ) != "undefined" ? aArguments : []};
		}
		
		/**
		 * Ejecuta el evento pasado como argumento
		 * @param string sEvent Evento a ejecutar
		 * @param array aArguments Array de parametros para pasarlo
		 * @return array Devuelve un array con los eventos procesados
		*/
		this.execute = function(sEvent, aArguments)
		{
			// Variables
			var value = [];
			aReturn = [];
			sEvent = sEvent.toLowerCase();
			aArguments = $.type( aArguments ) != "undefined" ? aArguments : [];
		
			// Si no existe el evento o la funcion
			if( !(sEvent in this.eventsLoad) )
				return aReturn;
		
			// Recorremos y lanzamos funciones
			$.each( this.eventsLoad[sEvent], function(key, value)
			{		
				// Añadimos
				$.merge( value.arguments, aArguments );
				
				// Realizamos la peticion
				aReturn[aReturn.length] = call_user_func_array( value.execute, value.arguments );
				
				value.arguments = [];
			});
			
			// Retornamos
			return aReturn;
		}
	}
}(jQuery);