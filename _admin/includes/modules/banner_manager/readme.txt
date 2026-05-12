# Banner Manager Module

## Descripcion
Modulo para la gestion de banners publicitarios en la tienda.

## Funcionalidades
- Crear, editar y eliminar banners
- Soporte para imagenes responsive (web, tablet, movil)
- Soporte multi-idioma para imagenes
- Agrupacion de banners por grupos
- Programacion de fecha de activacion
- Fecha de expiracion
- Activar/desactivar banners

## Requisitos
- PHP 8.0 o superior
- Tablas: banners, banners_history

## Instalacion
El modulo utiliza las tablas existentes de banners de osCommerce.
No requiere instalacion adicional.

## Uso
1. Acceder a Herramientas > Gestion de Banners
2. Crear nuevo banner con el boton "Crear Banner"
3. Rellenar los campos requeridos (titulo, grupo)
4. Subir imagenes para cada idioma y dispositivo
5. Guardar

## Estructura de archivos
- index.php - Archivo principal del modulo
- css/style.css - Estilos del modulo
- js/index.js - JavaScript del modulo
- readme.txt - Este archivo

## Changelog
v1.0 - Conversion del banner_manager.php original al nuevo formato de modulo
