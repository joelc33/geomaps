<?php
/**
 * SEDATEZ - API de Actualización de Coordenadas GPS para Contribuyentes MNM
 * Conforme a PLAN_DASHBOARD_MNM_SPEC.md
 */

header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/config_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Método no permitido. Utilice POST.'
    ));
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
$lat = isset($input['latitud']) ? (float)$input['latitud'] : null;
$lng = isset($input['longitud']) ? (float)$input['longitud'] : null;
$observacion = isset($input['observacion']) ? trim($input['observacion']) : '';

if ($id <= 0 || $lat === null || $lng === null) {
    http_response_code(422);
    echo json_encode(array(
        'success' => false,
        'message' => 'Parámetros incompletos. Debe indicar el ID del contribuyente, latitud y longitud.'
    ));
    exit;
}

// Validar límites geográficos del Estado Zulia
if ($lat < 8.0 || $lat > 12.5 || $lng < -74.0 || $lng > -70.0) {
    http_response_code(422);
    echo json_encode(array(
        'success' => false,
        'message' => 'Las coordenadas ingresadas están fuera de los límites territoriales del Estado Zulia.'
    ));
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Verificar existencia del contribuyente en tab_mnm
    $checkStmt = $pdo->prepare("SELECT id, de_razon_social, nu_documento_rif FROM contribuyente.tab_mnm WHERE id = :id");
    $checkStmt->execute(array(':id' => $id));
    $empresa = $checkStmt->fetch();

    if (!$empresa) {
        http_response_code(404);
        echo json_encode(array(
            'success' => false,
            'message' => 'El registro del contribuyente no fue encontrado en la base de datos.'
        ));
        exit;
    }

    // 2. Actualizar nu_latitud y nu_longitud
    $updateStmt = $pdo->prepare("UPDATE contribuyente.tab_mnm 
                                 SET nu_latitud = :lat, 
                                     nu_longitud = :lng, 
                                     updated_at = NOW() 
                                 WHERE id = :id");
    $updateStmt->execute(array(
        ':lat' => (string)$lat,
        ':lng' => (string)$lng,
        ':id' => $id
    ));

    $userId = isset($_SESSION['auth_user']['id']) ? $_SESSION['auth_user']['id'] : null;
    $userNombre = isset($_SESSION['auth_user']['nombre']) ? $_SESSION['auth_user']['nombre'] : 'Fiscal SEDATEZ';

    // 3. Auditoría en log
    error_log(sprintf(
        "[SEDATEZ GIS] Coordenadas actualizadas para contribuyente ID %d (%s, RIF %s) por usuario %s: Lat %f, Lng %f",
        $id,
        $empresa['de_razon_social'],
        $empresa['nu_documento_rif'],
        $userNombre,
        $lat,
        $lng
    ));

    echo json_encode(array(
        'success' => true,
        'message' => 'Coordenadas GPS actualizadas exitosamente en el sistema SEDATEZ.',
        'data' => array(
            'id' => $id,
            'razon_social' => $empresa['de_razon_social'],
            'rif' => $empresa['nu_documento_rif'],
            'latitud' => $lat,
            'longitud' => $lng,
            'es_gps_exacto' => true,
            'tipo_coordenada' => 'GPS_EXACTO'
        )
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Error al guardar las coordenadas en la base de datos: ' . $e->getMessage()
    ));
}
