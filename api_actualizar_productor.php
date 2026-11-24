<?php
/**
 * SEDATEZ - API para Actualizar la Condición de Productor en Contribuyentes MNM (tab_mnm)
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

// 1. Obtener datos de entrada (JSON o POST tradicional)
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);
if (!is_array($inputData)) {
    $inputData = $_POST;
}

$id = isset($inputData['id']) ? (int)$inputData['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'El identificador (ID) del contribuyente es obligatorio y debe ser válido.'
    ));
    exit;
}

if (!isset($inputData['in_productor']) && !isset($inputData['es_productor'])) {
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'El campo in_productor (true/false) es requerido.'
    ));
    exit;
}

$rawVal = isset($inputData['in_productor']) ? $inputData['in_productor'] : $inputData['es_productor'];
$inProductor = ($rawVal === true || $rawVal === 1 || $rawVal === '1' || $rawVal === 'true' || strtolower((string)$rawVal) === 'si');

try {
    $pdo = getDbConnection();

    // 2. Verificar existencia en contribuyente.tab_mnm
    $check = $pdo->prepare("SELECT id, de_razon_social, nu_documento_rif, in_productor FROM contribuyente.tab_mnm WHERE id = :id");
    $check->execute(array(':id' => $id));
    $empresa = $check->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        http_response_code(404);
        echo json_encode(array(
            'success' => false,
            'message' => "El contribuyente con ID {$id} no fue encontrado en contribuyente.tab_mnm."
        ));
        exit;
    }

    // 3. Actualizar in_productor
    $updateStmt = $pdo->prepare("UPDATE contribuyente.tab_mnm 
                                 SET in_productor = :val, 
                                     updated_at = NOW() 
                                 WHERE id = :id");
    $updateStmt->execute(array(
        ':val' => $inProductor ? 'true' : 'false',
        ':id' => $id
    ));

    echo json_encode(array(
        'success' => true,
        'id' => $id,
        'rif' => $empresa['nu_documento_rif'],
        'razon_social' => $empresa['de_razon_social'],
        'in_productor' => $inProductor,
        'tipo_contribuyente' => $inProductor ? 'Regular' : 'No Regular',
        'message' => 'Condición de contribuyente regular actualizada exitosamente.'
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Error en el servidor al actualizar la condición de productor: ' . $e->getMessage()
    ));
}
