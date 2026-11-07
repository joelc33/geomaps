<?php
/**
 * SEDATEZ - API de Actualización de Coordenadas GPS para Contribuyentes MNM y Registro de Oficio
 * Conforme a PLAN_DASHBOARD_MNM_SPEC.md y requerimiento de Registro de Oficio (preregistro.tab_minas)
 */

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Método no permitido. Utilice POST.'
    ));
    exit;
}

// 1. Verificación de autenticación de usuario
if (!isset($_SESSION['auth_user']) || empty($_SESSION['auth_user']['id'])) {
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Acceso no autorizado. Debe iniciar sesión en el sistema SEDATEZ.'
    ));
    exit;
}

// 2. Verificación de permisos de rol para modificar coordenadas
$currentUser = $_SESSION['auth_user'];
$idRol = isset($currentUser['id_rol']) ? (int)$currentUser['id_rol'] : 0;
$puedeModificar = ($idRol === 1 || $idRol === 2); // SuperAdmin o Admin por defecto

try {
    $pdoAuth = getDbConnection();
    if ($idRol > 0) {
        $stmtP = $pdoAuth->prepare("SELECT puede_modificar_coordenadas FROM autenticacion.tab_rol_permisos_mapa WHERE id_tab_rol = :id_rol");
        $stmtP->execute(array(':id_rol' => $idRol));
        $pRow = $stmtP->fetch();
        if ($pRow) {
            $puedeModificar = (bool)$pRow['puede_modificar_coordenadas'];
        }
    }
} catch (Exception $e) {
    // Mantener la verificación base si ocurre excepción
}

if (!$puedeModificar) {
    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'message' => 'Acceso denegado: Su perfil o rol de usuario no cuenta con autorización para modificar coordenadas GPS de registrados.'
    ));
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$rawId = isset($input['id']) ? trim((string)$input['id']) : '';
$grupo = isset($input['grupo']) ? trim((string)$input['grupo']) : '';
$origenTabla = isset($input['origen_tabla']) ? trim((string)$input['origen_tabla']) : '';
$lat = isset($input['latitud']) ? (float)$input['latitud'] : null;
$lng = isset($input['longitud']) ? (float)$input['longitud'] : null;
$observacion = isset($input['observacion']) ? trim($input['observacion']) : '';

// Parsear si el ID viene con prefijo como 'oficio_3' o 'ritez_32'
$id = 0;
if (strpos($rawId, 'oficio_') === 0) {
    $id = (int)substr($rawId, 7);
    $grupo = 'oficio';
} elseif (strpos($rawId, 'ritez_') === 0) {
    $id = (int)substr($rawId, 6);
    $grupo = 'ritez';
} else {
    $id = (int)$rawId;
}

if ($id <= 0 || $lat === null || $lng === null) {
    http_response_code(422);
    echo json_encode(array(
        'success' => false,
        'message' => 'Parámetros incompletos. Debe indicar el ID del registrado, latitud y longitud.'
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

    // Determinar qué tabla actualizar
    $targetTable = 'contribuyente.tab_mnm';
    $grupoCodigo = 'ritez';
    $grupoNombre = 'Minerales No Metálicos';

    if ($grupo === 'oficio' || $origenTabla === 'preregistro.tab_minas') {
        $targetTable = 'preregistro.tab_minas';
        $grupoCodigo = 'oficio';
        $grupoNombre = 'Registro de Oficio de Usuarios de Minerales No Metálicos';
    } elseif ($grupo === 'ritez' || $origenTabla === 'contribuyente.tab_mnm') {
        $targetTable = 'contribuyente.tab_mnm';
        $grupoCodigo = 'ritez';
        $grupoNombre = 'Minerales No Metálicos';
    } else {
        // Si no se especificó grupo, verificar primero en contribuyente.tab_mnm
        $check1 = $pdo->prepare("SELECT id, de_razon_social, nu_documento_rif FROM contribuyente.tab_mnm WHERE id = :id");
        $check1->execute(array(':id' => $id));
        if ($check1->fetch()) {
            $targetTable = 'contribuyente.tab_mnm';
            $grupoCodigo = 'ritez';
            $grupoNombre = 'Minerales No Metálicos';
        } else {
            // Si no está en tab_mnm, verificar en preregistro.tab_minas
            $check2 = $pdo->prepare("SELECT id, de_razon_social, nu_documento_rif FROM preregistro.tab_minas WHERE id = :id");
            $check2->execute(array(':id' => $id));
            if ($check2->fetch()) {
                $targetTable = 'preregistro.tab_minas';
                $grupoCodigo = 'oficio';
                $grupoNombre = 'Registro de Oficio de Usuarios de Minerales No Metálicos';
            }
        }
    }

    // 1. Verificar existencia del contribuyente en la tabla seleccionada
    $checkStmt = $pdo->prepare("SELECT id, de_razon_social, nu_documento_rif FROM {$targetTable} WHERE id = :id");
    $checkStmt->execute(array(':id' => $id));
    $empresa = $checkStmt->fetch();

    if (!$empresa) {
        http_response_code(404);
        echo json_encode(array(
            'success' => false,
            'message' => "El registro ID {$id} no fue encontrado en {$targetTable}."
        ));
        exit;
    }

    // 2. Actualizar nu_latitud y nu_longitud en la tabla correspondiente
    $updateStmt = $pdo->prepare("UPDATE {$targetTable} 
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

    // 3. Auditoría en log del sistema
    error_log(sprintf(
        "[SEDATEZ GIS] Coordenadas actualizadas en %s para registrado ID %d (%s, RIF %s) [Grupo: %s] por usuario %s: Lat %f, Lng %f",
        $targetTable,
        $id,
        $empresa['de_razon_social'],
        $empresa['nu_documento_rif'],
        $grupoNombre,
        $userNombre,
        $lat,
        $lng
    ));

    echo json_encode(array(
        'success' => true,
        'message' => "Coordenadas GPS actualizadas exitosamente en el sistema SEDATEZ ({$grupoNombre}).",
        'data' => array(
            'id' => $id,
            'uid' => $grupoCodigo . '_' . $id,
            'grupo_codigo' => $grupoCodigo,
            'grupo_nombre' => $grupoNombre,
            'origen_tabla' => $targetTable,
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
