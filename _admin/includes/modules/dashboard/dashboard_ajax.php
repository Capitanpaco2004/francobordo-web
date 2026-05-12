<?php
/**
 * Dashboard AJAX - Save/Load widget configuration per admin
 * Stores config as JSON file per admin user
 */
chdir(dirname(__FILE__) . '/../../../');
$PHP_SELF = 'index.php'; // Bypass admin_files check (uses dashboard permissions)
require(dirname(__FILE__) . '/../../application_top.php');

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$config_dir = dirname(__FILE__) . '/config/';

// Crear directorio si no existe
if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
}

$config_file = $config_dir . 'admin_' . (int)$login_id . '.json';

switch ($action) {
    case 'save_config':
        $config = $_POST['config'] ?? '';
        if (!empty($config)) {
            // Validar que es JSON valido
            $decoded = json_decode($config, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                file_put_contents($config_file, json_encode($decoded, JSON_PRETTY_PRINT));
                echo json_encode(['status' => 'ok']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'JSON invalido']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Config vacia']);
        }
        break;

    case 'load_config':
        if (file_exists($config_file)) {
            $config = file_get_contents($config_file);
            echo json_encode(['status' => 'ok', 'config' => json_decode($config, true)]);
        } else {
            echo json_encode(['status' => 'ok', 'config' => null]);
        }
        break;

    case 'reset_config':
        if (file_exists($config_file)) {
            unlink($config_file);
        }
        echo json_encode(['status' => 'ok']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Accion no valida']);
}
