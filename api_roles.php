<?php
/**
 * SEDATEZ - API de Gestión de Roles y Permisos de Perfiles
 * Permite listar, configurar y restringir las opciones del sistema cartográfico:
 * 1.- tipo de empresas
 * 2.- Tipo de minerales
 * 3.- Municipios por Eje
 * 4.- Modificar coordenadas de gps en la ficha de la empresa
 */

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config_db.php';

// Función para responder JSON uniforme
function jsonResponse($success, $message, $data = array(), $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode(array_merge(array(
        'success' => $success,
        'message' => $message
    ), $data));
    exit;
}

// Verificar autenticación
if (!isset($_SESSION['auth_user']) || empty($_SESSION['auth_user']['id'])) {
    jsonResponse(false, 'Acceso denegado. No ha iniciado sesión.', array(), 401);
}

$currentUser = $_SESSION['auth_user'];
$currentUserId = (int)$currentUser['id'];
$currentRoleId = isset($currentUser['id_rol']) ? (int)$currentUser['id_rol'] : 0;
$currentRoleName = isset($currentUser['rol']) ? trim($currentUser['rol']) : '';
$isSuperOrAdmin = ($currentRoleId === 1 || $currentRoleId === 2 || strcasecmp($currentRoleName, 'Super Administrador') === 0 || strcasecmp($currentRoleName, 'Administrador') === 0);

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

try {
    $pdo = getDbConnection();

    // ==============================================================
    // 1. MIS PERMISOS (Cualquier usuario autenticado)
    // ==============================================================
    if ($action === 'mis_permisos') {
        $permisos = array(
            'puede_ver_tipo_empresa' => true,
            'puede_ver_tipo_minerales' => true,
            'puede_ver_municipios_eje' => true,
            'puede_modificar_coordenadas' => $isSuperOrAdmin,
            'es_admin' => $isSuperOrAdmin,
            'id_rol' => $currentRoleId,
            'de_rol' => isset($currentUser['rol']) ? $currentUser['rol'] : 'Usuario'
        );

        if ($currentRoleId > 0) {
            $stmt = $pdo->prepare("SELECT puede_ver_tipo_empresa, puede_ver_tipo_minerales, puede_ver_municipios_eje, puede_modificar_coordenadas 
                                   FROM autenticacion.tab_rol_permisos_mapa WHERE id_tab_rol = :id_rol");
            $stmt->execute(array(':id_rol' => $currentRoleId));
            $row = $stmt->fetch();
            if ($row) {
                $permisos['puede_ver_tipo_empresa'] = (bool)$row['puede_ver_tipo_empresa'];
                $permisos['puede_ver_tipo_minerales'] = (bool)$row['puede_ver_tipo_minerales'];
                $permisos['puede_ver_municipios_eje'] = (bool)$row['puede_ver_municipios_eje'];
                $permisos['puede_modificar_coordenadas'] = (bool)$row['puede_modificar_coordenadas'];
            }
        }

        // Actualizar sesión activa
        $_SESSION['auth_user']['permisos'] = $permisos;
        $_SESSION['auth_user']['es_admin'] = $isSuperOrAdmin;

        jsonResponse(true, 'Permisos recuperados', array('permisos' => $permisos, 'usuario' => $currentUser));
    }

    // ==============================================================
    // RESTRICCIÓN DE ACCIONES ADMINISTRATIVAS:
    // Solo Super Administrador (1) o Administrador (2)
    // ==============================================================
    if (!$isSuperOrAdmin) {
        jsonResponse(false, 'Acceso restringido: solo usuarios con perfil de Administrador pueden gestionar roles y permisos.', array(), 403);
    }

    // ==============================================================
    // 2. LISTAR ROLES CON SUS PERMISOS CONFIGURADOS
    // ==============================================================
    if ($action === 'listar_roles') {
        $sql = "SELECT r.id, r.de_rol, r.in_estatus,
                       COALESCE(p.puede_ver_tipo_empresa, TRUE) AS puede_ver_tipo_empresa,
                       COALESCE(p.puede_ver_tipo_minerales, TRUE) AS puede_ver_tipo_minerales,
                       COALESCE(p.puede_ver_municipios_eje, TRUE) AS puede_ver_municipios_eje,
                       COALESCE(p.puede_modificar_coordenadas, CASE WHEN r.id IN (1, 2) THEN TRUE ELSE FALSE END) AS puede_modificar_coordenadas,
                       p.updated_at AS permisos_updated_at,
                       COALESCE(u.total_usuarios, 0) AS total_usuarios
                FROM autenticacion.tab_rol r
                LEFT JOIN autenticacion.tab_rol_permisos_mapa p ON p.id_tab_rol = r.id
                LEFT JOIN (
                    SELECT id_tab_rol, COUNT(*) AS total_usuarios
                    FROM autenticacion.tab_usuario_rol
                    GROUP BY id_tab_rol
                ) u ON u.id_tab_rol = r.id
                ORDER BY r.id ASC";

        $stmt = $pdo->query($sql);
        $roles = array();
        while ($r = $stmt->fetch()) {
            $roles[] = array(
                'id' => (int)$r['id'],
                'de_rol' => $r['de_rol'],
                'in_estatus' => (bool)$r['in_estatus'],
                'total_usuarios' => (int)$r['total_usuarios'],
                'puede_ver_tipo_empresa' => (bool)$r['puede_ver_tipo_empresa'],
                'puede_ver_tipo_minerales' => (bool)$r['puede_ver_tipo_minerales'],
                'puede_ver_municipios_eje' => (bool)$r['puede_ver_municipios_eje'],
                'puede_modificar_coordenadas' => (bool)$r['puede_modificar_coordenadas'],
                'updated_at' => $r['permisos_updated_at']
            );
        }

        jsonResponse(true, 'Listado de roles recuperado con éxito.', array('roles' => $roles));
    }

    // ==============================================================
    // 3. GUARDAR / ACTUALIZAR PERMISOS DE UN ROL
    // ==============================================================
    if ($action === 'guardar_permisos') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Método HTTP no permitido. Use POST.', array(), 405);
        }

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $idRol = isset($input['id_tab_rol']) ? (int)$input['id_tab_rol'] : 0;
        if ($idRol <= 0) {
            jsonResponse(false, 'Debe especificar un ID de rol válido.', array(), 422);
        }

        // Obtener valores booleanos
        $verTipoEmpresa = !empty($input['puede_ver_tipo_empresa']) ? 'true' : 'false';
        $verTipoMinerales = !empty($input['puede_ver_tipo_minerales']) ? 'true' : 'false';
        $verMunicipiosEje = !empty($input['puede_ver_municipios_eje']) ? 'true' : 'false';
        $modCoordenadas = !empty($input['puede_modificar_coordenadas']) ? 'true' : 'false';

        // Upsert en autenticacion.tab_rol_permisos_mapa
        $upsertSql = "INSERT INTO autenticacion.tab_rol_permisos_mapa 
                        (id_tab_rol, puede_ver_tipo_empresa, puede_ver_tipo_minerales, puede_ver_municipios_eje, puede_modificar_coordenadas, created_at, updated_at)
                      VALUES 
                        (:id_rol, :ver_emp, :ver_min, :ver_eje, :mod_coords, NOW(), NOW())
                      ON CONFLICT (id_tab_rol) DO UPDATE SET
                        puede_ver_tipo_empresa = EXCLUDED.puede_ver_tipo_empresa,
                        puede_ver_tipo_minerales = EXCLUDED.puede_ver_tipo_minerales,
                        puede_ver_municipios_eje = EXCLUDED.puede_ver_municipios_eje,
                        puede_modificar_coordenadas = EXCLUDED.puede_modificar_coordenadas,
                        updated_at = NOW()";

        $upsertStmt = $pdo->prepare($upsertSql);
        $upsertStmt->execute(array(
            ':id_rol' => $idRol,
            ':ver_emp' => $verTipoEmpresa,
            ':ver_min' => $verTipoMinerales,
            ':ver_eje' => $verMunicipiosEje,
            ':mod_coords' => $modCoordenadas
        ));

        // Si se actualizó el rol del usuario conectado actualmente, refrescar su sesión
        if ($idRol === $currentRoleId) {
            $_SESSION['auth_user']['permisos'] = array(
                'puede_ver_tipo_empresa' => ($verTipoEmpresa === 'true'),
                'puede_ver_tipo_minerales' => ($verTipoMinerales === 'true'),
                'puede_ver_municipios_eje' => ($verMunicipiosEje === 'true'),
                'puede_modificar_coordenadas' => ($modCoordenadas === 'true'),
                'es_admin' => $isSuperOrAdmin
            );
        }

        jsonResponse(true, 'Configuración de permisos guardada exitosamente en la base de datos.', array(
            'id_tab_rol' => $idRol,
            'permisos' => array(
                'puede_ver_tipo_empresa' => ($verTipoEmpresa === 'true'),
                'puede_ver_tipo_minerales' => ($verTipoMinerales === 'true'),
                'puede_ver_municipios_eje' => ($verMunicipiosEje === 'true'),
                'puede_modificar_coordenadas' => ($modCoordenadas === 'true')
            )
        ));
    }

    // ==============================================================
    // 4. CREAR NUEVO ROL DE USUARIO
    // ==============================================================
    if ($action === 'crear_rol') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Método HTTP no permitido. Use POST.', array(), 405);
        }

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $nombreRol = isset($input['de_rol']) ? trim($input['de_rol']) : '';
        if ($nombreRol === '') {
            jsonResponse(false, 'Debe especificar el nombre del nuevo rol.', array(), 422);
        }

        // Verificar si ya existe
        $check = $pdo->prepare("SELECT id FROM autenticacion.tab_rol WHERE LOWER(de_rol) = LOWER(:nombre)");
        $check->execute(array(':nombre' => $nombreRol));
        if ($check->fetch()) {
            jsonResponse(false, 'Ya existe un rol registrado con ese nombre.', array(), 409);
        }

        $stmtRol = $pdo->prepare("INSERT INTO autenticacion.tab_rol (de_rol, in_estatus, created_at, updated_at) 
                                 VALUES (:nombre, true, NOW(), NOW()) RETURNING id");
        $stmtRol->execute(array(':nombre' => $nombreRol));
        $newRolId = (int)$stmtRol->fetchColumn();

        $verTipoEmpresa = !empty($input['puede_ver_tipo_empresa']) ? 'true' : 'false';
        $verTipoMinerales = !empty($input['puede_ver_tipo_minerales']) ? 'true' : 'false';
        $verMunicipiosEje = !empty($input['puede_ver_municipios_eje']) ? 'true' : 'false';
        $modCoordenadas = !empty($input['puede_modificar_coordenadas']) ? 'true' : 'false';

        $stmtP = $pdo->prepare("INSERT INTO autenticacion.tab_rol_permisos_mapa 
                                (id_tab_rol, puede_ver_tipo_empresa, puede_ver_tipo_minerales, puede_ver_municipios_eje, puede_modificar_coordenadas, created_at, updated_at)
                                VALUES (:id_rol, :ver_emp, :ver_min, :ver_eje, :mod_coords, NOW(), NOW())");
        $stmtP->execute(array(
            ':id_rol' => $newRolId,
            ':ver_emp' => $verTipoEmpresa,
            ':ver_min' => $verTipoMinerales,
            ':ver_eje' => $verMunicipiosEje,
            ':mod_coords' => $modCoordenadas
        ));

        jsonResponse(true, 'Rol creado exitosamente.', array(
            'rol' => array(
                'id' => $newRolId,
                'de_rol' => $nombreRol,
                'in_estatus' => true,
                'total_usuarios' => 0,
                'puede_ver_tipo_empresa' => ($verTipoEmpresa === 'true'),
                'puede_ver_tipo_minerales' => ($verTipoMinerales === 'true'),
                'puede_ver_municipios_eje' => ($verMunicipiosEje === 'true'),
                'puede_modificar_coordenadas' => ($modCoordenadas === 'true')
            )
        ));
    }

    // ==============================================================
    // 5. LISTAR USUARIOS Y SUS ROLES ASIGNADOS
    // ==============================================================
    if ($action === 'listar_usuarios') {
        $sql = "SELECT u.id, u.da_usuario, u.da_email, u.in_estatus, u.in_ver_mapa,
                       f.nb_funcionario, f.ap_funcionario, f.nu_cedula,
                       c.de_cargo,
                       r.id AS id_rol, r.de_rol
                FROM autenticacion.tab_usuarios u
                LEFT JOIN mantenimiento.tab_funcionario f ON f.id_tab_usuarios = u.id
                LEFT JOIN mantenimiento.tab_cargo c ON c.id = f.id_tab_cargo
                LEFT JOIN autenticacion.tab_usuario_rol ur ON ur.id_tab_usuarios = u.id
                LEFT JOIN autenticacion.tab_rol r ON r.id = ur.id_tab_rol
                WHERE u.id_tab_tipo_usuario = 1
                ORDER BY u.id ASC";

        $stmt = $pdo->query($sql);
        $usuarios = array();
        while ($u = $stmt->fetch()) {
            $nb = trim($u['nb_funcionario'] . ' ' . $u['ap_funcionario']);
            $usuarios[] = array(
                'id' => (int)$u['id'],
                'da_usuario' => $u['da_usuario'],
                'da_email' => $u['da_email'],
                'nombre_completo' => $nb !== '' ? $nb : $u['da_usuario'],
                'nu_cedula' => $u['nu_cedula'],
                'de_cargo' => $u['de_cargo'],
                'in_estatus' => (bool)$u['in_estatus'],
                'in_ver_mapa' => (bool)$u['in_ver_mapa'],
                'id_rol' => $u['id_rol'] ? (int)$u['id_rol'] : null,
                'de_rol' => $u['de_rol'] ? $u['de_rol'] : 'Sin Rol Asignado'
            );
        }

        jsonResponse(true, 'Usuarios recuperados con éxito.', array('usuarios' => $usuarios));
    }

    // ==============================================================
    // 6. ASIGNAR ROL A UN USUARIO
    // ==============================================================
    if ($action === 'asignar_rol') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Método HTTP no permitido. Use POST.', array(), 405);
        }

        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $targetUserId = isset($input['id_tab_usuarios']) ? (int)$input['id_tab_usuarios'] : 0;
        $targetRolId = isset($input['id_tab_rol']) ? (int)$input['id_tab_rol'] : 0;

        if ($targetUserId <= 0 || $targetRolId <= 0) {
            jsonResponse(false, 'Parámetros incompletos. Debe indicar el usuario y el rol a asignar.', array(), 422);
        }

        // Verificar que el rol exista
        $checkRol = $pdo->prepare("SELECT id, de_rol FROM autenticacion.tab_rol WHERE id = :id");
        $checkRol->execute(array(':id' => $targetRolId));
        $rolData = $checkRol->fetch();
        if (!$rolData) {
            jsonResponse(false, 'El rol seleccionado no existe en el sistema.', array(), 404);
        }

        // Eliminar asignación previa si existe
        $del = $pdo->prepare("DELETE FROM autenticacion.tab_usuario_rol WHERE id_tab_usuarios = :uid");
        $del->execute(array(':uid' => $targetUserId));

        // Insertar nueva asignación
        $ins = $pdo->prepare("INSERT INTO autenticacion.tab_usuario_rol (id_tab_usuarios, id_tab_rol, in_estatus, created_at, updated_at)
                              VALUES (:uid, :id_rol, true, NOW(), NOW())");
        $ins->execute(array(
            ':uid' => $targetUserId,
            ':id_rol' => $targetRolId
        ));

        // Si el usuario asignado es el que está en sesión, actualizar sesión
        if ($targetUserId === $currentUserId) {
            $_SESSION['auth_user']['id_rol'] = $targetRolId;
            $_SESSION['auth_user']['rol'] = $rolData['de_rol'];
        }

        jsonResponse(true, "Rol '{$rolData['de_rol']}' asignado exitosamente al usuario.", array(
            'id_tab_usuarios' => $targetUserId,
            'id_tab_rol' => $targetRolId,
            'de_rol' => $rolData['de_rol']
        ));
    }

    // Acción no reconocida
    jsonResponse(false, 'Acción no reconocida en la API de Roles.', array(), 400);

} catch (Exception $e) {
    jsonResponse(false, 'Error en el servidor: ' . $e->getMessage(), array(), 500);
}
