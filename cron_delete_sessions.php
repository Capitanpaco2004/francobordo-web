<?php
use AddonDependencyInjection\Application\DependencyInjection;
use Oscdenox\Core\Session\Application\DeleteUnregisteredCart\DeleteUnregisteredSessionsWithoutCartCommand;
use Oscdenox\Core\Session\Application\DeleteUnregisteredCart\DeleteUnregisteredSessionsWithoutCartCommandHandler;
use Oscdenox\Core\Session\Infrastructure\Session\SessionRepository;

include 'includes/application_top.php';

$action = isset($_GET['action']) ? (int)$_GET['action'] : '';

// Configuración de dependencias
if($action == 'install'){
	configureDependencies();
	die();
}

// Límite de registros por lote
$limit = isset($_GET['cronjobs_limit']) ? (int)$_GET['cronjobs_limit'] : 5000;

// Obtiene la configuración de lifetime de la cookie
$cookieLifeTimeFrontOffice = $configurationAdapter->get('COOKIE_LIFETIME_FRONT_OFFICE', 5);

// Obtiene el handler para el comando
$DeleteUnregisteredSessionsWithoutCartCommandHandler = DependencyInjection::getInstance()->get(DeleteUnregisteredSessionsWithoutCartCommandHandler::class);

$maxAttempts = 100;  // Límite de iteraciones
$attempts = 0;

// Bucle que se ejecuta hasta que no queden registros a eliminar en un lote o alcance el máximo de intentos
do {
	// Obtén el número de registros eliminados
	$remainingRecords = $DeleteUnregisteredSessionsWithoutCartCommandHandler->__invoke(
		new DeleteUnregisteredSessionsWithoutCartCommand($cookieLifeTimeFrontOffice, $limit)
	);
	echo "Sesiones eliminadas en este lote: " . $remainingRecords . "<br>";
	$attempts++;
} while ($remainingRecords > 0 && $attempts < $maxAttempts); // Continúa hasta que se hayan eliminado todas las sesiones sin carrito o alcance el límite

// Logueo opcional para verificar el resultado final del proceso
if ($remainingRecords === 0) {
	echo "Eliminación de sesiones completada.";
} else {
	echo "Se alcanzó el máximo de intentos sin completar la eliminación de todas las sesiones.";
}

/**
 * Configura las dependencias del módulo.
 */
function configureDependencies(): void {
	$dependencyInjection = DependencyInjection::getInstance();

	// Registrar la interfaz y la implementación
	$dependencyInjection->addInterface(\Oscdenox\Core\Session\Domain\SessionDeleterInterface::class, \Oscdenox\Core\Session\Infrastructure\Session\SessionRepository::class);

	// Registrar el comando y su handler
	$dependencyInjection->addInterface(\Oscdenox\Core\Session\Application\DeleteUnregisteredCart\DeleteUnregisteredSessionsWithoutCartCommandHandler::class, \Oscdenox\Core\Session\Application\DeleteUnregisteredCart\DeleteUnregisteredSessionsWithoutCartCommandHandler::class);
}
