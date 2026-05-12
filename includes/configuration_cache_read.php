<?php
	// Tools
   use util\tools as tools;

   // Si existe el archivo cache de configuración
   if(  defined( 'FILENAME_CONFIGURATION_CACHE' ) && file_exists( FILENAME_CONFIGURATION_CACHE ) ) {
      include( FILENAME_CONFIGURATION_CACHE );
      return true;
   }

   // Si se puede crear el cache file
   tools::createCacheFile();

   // Incluimos el archivo
   include( FILENAME_CONFIGURATION_CACHE );
