<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config_db.php';

if (isset($_SESSION['auth_user']) && (!empty($_SESSION['auth_user']['in_ver_mapa']) || !empty($_SESSION['auth_user']['es_admin']))) {
    $userId = (int)$_SESSION['auth_user']['id'];
    try {
        $pdo = getDbConnection();
        // Obtener rol actual del usuario en BD
        $stmt = $pdo->prepare("
            SELECT r.id AS id_rol, r.de_rol
            FROM autenticacion.tab_usuario_rol ur
            INNER JOIN autenticacion.tab_rol r ON r.id = ur.id_tab_rol
            WHERE ur.id_tab_usuarios = :uid AND (ur.in_estatus IS TRUE OR ur.in_estatus IS NULL)
            ORDER BY r.id ASC
            LIMIT 1
        ");
        $stmt->execute(array(':uid' => $userId));
        $rolInfo = $stmt->fetch();
        if ($rolInfo) {
            $_SESSION['auth_user']['id_rol'] = (int)$rolInfo['id_rol'];
            $_SESSION['auth_user']['rol'] = $rolInfo['de_rol'];
        }
        
        $idRol = isset($_SESSION['auth_user']['id_rol']) ? (int)$_SESSION['auth_user']['id_rol'] : 0;
        $deRol = isset($_SESSION['auth_user']['rol']) ? trim($_SESSION['auth_user']['rol']) : '';
        $esAdmin = ($idRol === 1 || $idRol === 2 || strcasecmp($deRol, 'Super Administrador') === 0 || strcasecmp($deRol, 'Administrador') === 0);
        
        $permisos = array(
            'puede_ver_tipo_empresa' => true,
            'puede_ver_tipo_minerales' => true,
            'puede_ver_municipios_eje' => true,
            'puede_modificar_coordenadas' => $esAdmin,
            'es_admin' => $esAdmin
        );
        
        if ($idRol > 0) {
            $stmtP = $pdo->prepare("
                SELECT puede_ver_tipo_empresa, puede_ver_tipo_minerales, puede_ver_municipios_eje, puede_modificar_coordenadas
                FROM autenticacion.tab_rol_permisos_mapa
                WHERE id_tab_rol = :id_rol
            ");
            $stmtP->execute(array(':id_rol' => $idRol));
            $pRow = $stmtP->fetch();
            if ($pRow) {
                $permisos['puede_ver_tipo_empresa'] = (bool)$pRow['puede_ver_tipo_empresa'];
                $permisos['puede_ver_tipo_minerales'] = (bool)$pRow['puede_ver_tipo_minerales'];
                $permisos['puede_ver_municipios_eje'] = (bool)$pRow['puede_ver_municipios_eje'];
                $permisos['puede_modificar_coordenadas'] = (bool)$pRow['puede_modificar_coordenadas'];
            }
        }
        
        $_SESSION['auth_user']['permisos'] = $permisos;
        $_SESSION['auth_user']['es_admin'] = $esAdmin;
    } catch (Exception $e) {
        // En caso de incidencia de BD temporal, se mantiene la sesión activa
    }

    echo json_encode(array(
        'authenticated' => true,
        'user' => $_SESSION['auth_user']
    ));
} else {
    echo json_encode(array(
        'authenticated' => false
    ));
}

