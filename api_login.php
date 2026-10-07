<?php
/**
 * SEDATEZ - API de Autenticación
 * Implementación de Login Real contra PostgreSQL (etrib)
 * Conforme a PLAN_LOGIN_ETRIB_SPEC.md
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/config_db.php';

// Obtener IP del cliente
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $clientIp = trim($forwarded[0]);
}

// -------------------------------------------------------------
// Funciones de Rate Limiting (throttle: 5 intentos / 1 minuto)
// -------------------------------------------------------------
function checkRateLimit($ip, $maxAttempts = 5, $decaySeconds = 60) {
    $file = sys_get_temp_dir() . '/sedatez_rl_' . md5($ip) . '.json';
    $now = time();
    if (file_exists($file)) {
        $data = json_decode(@file_get_contents($file), true);
        if (is_array($data) && isset($data['first_attempt'])) {
            if ($now - $data['first_attempt'] < $decaySeconds) {
                if ($data['attempts'] >= $maxAttempts) {
                    return false; // Excedió el límite
                }
            } else {
                @unlink($file); // Ventana expirada
            }
        }
    }
    return true;
}

function recordFailedAttempt($ip, $decaySeconds = 60) {
    $file = sys_get_temp_dir() . '/sedatez_rl_' . md5($ip) . '.json';
    $now = time();
    $data = array('attempts' => 1, 'first_attempt' => $now);
    if (file_exists($file)) {
        $saved = json_decode(@file_get_contents($file), true);
        if (is_array($saved) && isset($saved['first_attempt']) && ($now - $saved['first_attempt'] < $decaySeconds)) {
            $data['attempts'] = (int)$saved['attempts'] + 1;
            $data['first_attempt'] = $saved['first_attempt'];
        }
    }
    @file_put_contents($file, json_encode($data));
}

function resetRateLimit($ip) {
    $file = sys_get_temp_dir() . '/sedatez_rl_' . md5($ip) . '.json';
    if (file_exists($file)) {
        @unlink($file);
    }
}

// -------------------------------------------------------------
// Validación del Método HTTP
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Método no permitido. Utilice POST.'
    ));
    exit;
}

// -------------------------------------------------------------
// 1. Verificación de Rate Limiting
// -------------------------------------------------------------
if (!checkRateLimit($clientIp, 5, 60)) {
    http_response_code(429);
    echo json_encode(array(
        'success' => false,
        'message' => 'Demasiados intentos de acceso fallidos. Por favor espere 1 minuto antes de reintentar.'
    ));
    exit;
}

// -------------------------------------------------------------
// 2. Procesamiento de Parámetros de Entrada
// -------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$identificador = '';
if (isset($input['identificador']) && trim($input['identificador']) !== '') {
    $identificador = trim($input['identificador']);
} elseif (isset($input['usuario']) && trim($input['usuario']) !== '') {
    $identificador = trim($input['usuario']);
}

$password = '';
if (isset($input['password'])) {
    $password = (string)$input['password'];
} elseif (isset($input['contraseña'])) {
    $password = (string)$input['contraseña'];
}

if ($identificador === '' || $password === '') {
    http_response_code(422);
    echo json_encode(array(
        'success' => false,
        'message' => 'Debe ingresar su usuario y contraseña.'
    ));
    exit;
}

// -------------------------------------------------------------
// 3. Autenticación contra PostgreSQL etrib
// -------------------------------------------------------------
try {
    $pdo = getDbConnection();

    // Buscar en autenticacion.tab_usuarios únicamente por nombre de usuario (da_usuario)
    $sql = "SELECT u.id, u.da_usuario, u.da_email, u.da_password, u.in_estatus, u.id_tab_tipo_usuario,
                   f.nb_funcionario, f.ap_funcionario, f.nu_cedula,
                   c.de_cargo,
                   tu.de_tipo_usuario,
                   r.de_rol
            FROM autenticacion.tab_usuarios u
            LEFT JOIN mantenimiento.tab_funcionario f ON f.id_tab_usuarios = u.id
            LEFT JOIN mantenimiento.tab_cargo c ON c.id = f.id_tab_cargo
            LEFT JOIN mantenimiento.tab_tipo_usuario tu ON tu.id = u.id_tab_tipo_usuario
            LEFT JOIN autenticacion.tab_usuario_rol ur ON ur.id_tab_usuarios = u.id
            LEFT JOIN autenticacion.tab_rol r ON r.id = ur.id_tab_rol
            WHERE LOWER(u.da_usuario) = LOWER(:usuario)
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array(':usuario' => $identificador));
    $user = $stmt->fetch();

    // 3.1 Usuario inexistente
    if (!$user) {
        recordFailedAttempt($clientIp);
        http_response_code(401);
        echo json_encode(array(
            'success' => false,
            'message' => 'Las credenciales ingresadas no coinciden con nuestros registros.'
        ));
        exit;
    }

    // 3.2 Restricción por tipo de usuario: Solo perfil Administrativo (id_tab_tipo_usuario = 1)
    if (!isset($user['id_tab_tipo_usuario']) || (int)$user['id_tab_tipo_usuario'] !== 1) {
        recordFailedAttempt($clientIp);
        http_response_code(403);
        echo json_encode(array(
            'success' => false,
            'message' => 'Acceso denegado: solo los usuarios con perfil administrativo (tipo 1) pueden ingresar a este sistema.'
        ));
        exit;
    }

    // 3.3 Cuenta inactiva
    if (empty($user['in_estatus'])) {
        http_response_code(403);
        echo json_encode(array(
            'success' => false,
            'message' => 'Su cuenta se encuentra inactiva. Comuníquese con la administración de SEDATEZ.'
        ));
        exit;
    }

    // 3.3 Verificación de contraseña Bcrypt
    if (!password_verify($password, $user['da_password'])) {
        recordFailedAttempt($clientIp);
        http_response_code(401);
        echo json_encode(array(
            'success' => false,
            'message' => 'Las credenciales ingresadas no coinciden con nuestros registros.'
        ));
        exit;
    }

    // ---------------------------------------------------------
    // 4. Éxito: Limpiar Rate Limit y Regenerar Sesión
    // ---------------------------------------------------------
    resetRateLimit($clientIp);
    session_regenerate_id(true);

    // Formateo de nombre formal
    $nb = isset($user['nb_funcionario']) ? trim($user['nb_funcionario']) : '';
    $ap = isset($user['ap_funcionario']) ? trim($user['ap_funcionario']) : '';
    $nombreCompleto = trim($nb . ' ' . $ap);
    if ($nombreCompleto === '') {
        $nombreCompleto = $user['da_usuario'];
    }

    // Iniciales para el avatar
    $iniciales = '';
    if (!empty($nb) && !empty($ap)) {
        $iniciales = strtoupper(substr($nb, 0, 1) . substr($ap, 0, 1));
    } else {
        $iniciales = strtoupper(substr($user['da_usuario'], 0, 2));
    }

    // Cargo institucional o rol
    $cargo = !empty($user['de_cargo']) 
        ? $user['de_cargo'] 
        : (!empty($user['de_rol']) ? $user['de_rol'] : (!empty($user['de_tipo_usuario']) ? $user['de_tipo_usuario'] : 'Funcionario SEDATEZ'));

    $sessionUser = array(
        'id' => (int)$user['id'],
        'usuario' => $user['da_usuario'],
        'email' => $user['da_email'],
        'nombre' => $nombreCompleto,
        'iniciales' => $iniciales,
        'cargo' => $cargo,
        'rol' => isset($user['de_rol']) ? $user['de_rol'] : null,
        'cedula' => isset($user['nu_cedula']) ? $user['nu_cedula'] : null,
        'tipo' => isset($user['de_tipo_usuario']) ? $user['de_tipo_usuario'] : 'Funcionario',
        'id_tab_tipo_usuario' => (int)$user['id_tab_tipo_usuario']
    );

    $_SESSION['auth_user'] = $sessionUser;

    // ---------------------------------------------------------
    // 5. Auditoría en auditoria.tab_usuario_login
    // ---------------------------------------------------------
    try {
        $auditSql = "INSERT INTO auditoria.tab_usuario_login (id_tab_usuarios, ip_cliente, created_at, updated_at) 
                     VALUES (:user_id, :ip, NOW(), NOW())";
        $auditStmt = $pdo->prepare($auditSql);
        $auditStmt->execute(array(
            ':user_id' => (int)$user['id'],
            ':ip' => $clientIp
        ));
    } catch (Exception $auditEx) {
        // La auditoría no debe interrumpir el login si la IP o trigger tiene particularidad
        error_log('Error registrando auditoria de login: ' . $auditEx->getMessage());
    }

    // ---------------------------------------------------------
    // 6. Respuesta JSON Exitosa (Conforme al Spec)
    // ---------------------------------------------------------
    echo json_encode(array(
        'success' => true,
        'message' => 'Autenticación exitosa',
        'user' => $sessionUser,
        'redirect' => 'index.html#dashboard'
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Error de conexión con la base de datos: ' . $e->getMessage()
    ));
}
