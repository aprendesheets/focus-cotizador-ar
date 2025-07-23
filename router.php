<?php
session_start();

$view = $_GET['view'] ?? null;

if (!$view) {
    // Si no hay parámetro view, decidimos según si hay sesión o no
    if (isset($_SESSION['fl_user'])) {
        $view = 'dashboard';
    } else {
        $view = 'login';
    }
}


if (isset($_GET['fl_ajax']) && $_GET['fl_ajax'] === 'eliminar') {
    $id = intval($_GET['id']);
    $archivo = plugin_dir_path(__FILE__) . 'data/cotizaciones.json';

    if (file_exists($archivo)) {
        $datos = json_decode(file_get_contents($archivo), true);
        $nuevos = array_values(array_filter($datos, function ($c) use ($id) {
            return $c['id'] != $id;
        }));
        file_put_contents($archivo, json_encode($nuevos, JSON_PRETTY_PRINT));

        // 🔁 Redirigimos desde PHP directamente
        header('Location: ?view=dashboard');
        exit;
    } else {
        echo json_encode(['success' => false]);
        exit;
    }
}


// 🔁 Si es una llamada AJAX, que lo maneje el handler
if (isset($_GET['fl_ajax'])) {
    require 'ajax-handler.php';
    exit;
}



switch ($view) {
    case 'dashboard':
        require 'dashboard.php';
        break;
    case 'logout':
        require 'logout.php';
        break;
    case 'login':
    default:
        require 'login.php';
        break;
        case 'nueva':
    require 'nueva.php';
    break;
    case 'editar':
    require 'editar.php';
    break;

}
