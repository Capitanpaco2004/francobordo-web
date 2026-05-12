<?php
/**
 * Pantalla de verificacion TOTP (segundo factor)
 * Punto de entrada — toda la logica y vista reside en el modulo 2fa-admin
 */

include 'includes/application_top.php';


require_once __DIR__ . '/includes/modules/2fa-admin/includes/config.php';
require __DIR__ . '/includes/modules/2fa-admin/actions/verify.php';
require __DIR__ . '/includes/modules/2fa-admin/template/verify.php';
