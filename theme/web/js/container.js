var containerClass;

!function ($){
	containerClass = function()
	{
		// Variables
		var self = this;

		// Array con los elementos
		this.aContainers = [];

		// Añade un elemento
		this.set = function(sName, sValue, bReplace)
		{
			// Valor por defecto
			bReplace = typeof bReplace !== 'undefined' ? bReplace : true;

			// Si queremos remplazar o si no existe añadimos
			if( bReplace || !this.has( sName ) )
				this.aContainers[sName] = sValue;
		}

		// Añade un array de elementos
		this.sets = function(aContainers, bReplace)
		{
			$.each(aContainers, function(sName, sValue)
			{
				self.set( sName, sValue, bReplace );
			});
		}

		// Comprueba si existe el elemento
		this.has = function(sName)
		{
			return sName in this.aContainers ? true : false;
		}

		// Devuelve el valor de un elemento almacenado
		this.get = function(sName)
		{
			if( this.has( sName ) )
				return this.aContainers[sName];

			return false;
		}

		// Devuelve todos los valores almacenados
		this.all = function()
		{
			return this.aContainers;
		}

		// Elimina el elemento
		this.delete = function(sName)
		{
			if( this.has( sName ) )
				delete this.aContainers[sName];
		}
	}
}(jQuery);