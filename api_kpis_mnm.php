<?php
/**
 * SEDATEZ - API de KPIs y Catálogos para el Dashboard MNM
 * Conforme a PLAN_DASHBOARD_MNM_SPEC.md
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config_db.php';

try {
    $pdo = getDbConnection();

    // 1. Resumen General de Contribuyentes MNM
    $summarySql = "SELECT 
        COUNT(*) AS total_empresas,
        COUNT(CASE WHEN in_activo = true THEN 1 END) AS activas,
        COUNT(CASE WHEN in_activo = false THEN 1 END) AS inactivas,
        COUNT(CASE WHEN in_llenado_guia = true THEN 1 END) AS habilitadas_guia,
        COUNT(CASE WHEN nu_latitud IS NOT NULL AND nu_latitud != '' THEN 1 END) AS gps_verificado
    FROM contribuyente.tab_mnm";
    $summary = $pdo->query($summarySql)->fetch();

    // 2. Conteo por Municipio
    $munSql = "SELECT 
        COALESCE(mun.id, 0) AS id_municipio,
        COALESCE(mun.de_municipio, 'POR ASIGNAR') AS municipio,
        COUNT(m.id) AS total,
        COUNT(CASE WHEN m.in_activo = true THEN 1 END) AS activas,
        COUNT(CASE WHEN m.in_activo = false THEN 1 END) AS inactivas
    FROM contribuyente.tab_mnm m
    LEFT JOIN mantenimiento.tab_municipio mun ON mun.id = m.id_tab_municipio
    GROUP BY mun.id, mun.de_municipio
    ORDER BY total DESC, mun.de_municipio ASC";
    $porMunicipio = $pdo->query($munSql)->fetchAll();

    // 3. Catálogo completo de los 21 municipios del Estado Zulia
    $catalogoMunSql = "SELECT id, de_municipio AS nombre
                       FROM mantenimiento.tab_municipio 
                       WHERE id_tab_estado = 23 
                       ORDER BY de_municipio ASC";
    $municipiosLista = $pdo->query($catalogoMunSql)->fetchAll();

    // 4. Catálogo oficial de minerales no metálicos
    $catalogoMinSql = "SELECT id, de_mnm AS nombre 
                       FROM mantenimiento.tab_mnm 
                       WHERE in_activo = true 
                       ORDER BY de_mnm ASC";
    $mineralesLista = $pdo->query($catalogoMinSql)->fetchAll();

    // 5. Conteo aproximado de presencia de minerales
    $mineralesPresencia = array();
    foreach ($mineralesLista as $min) {
        $stmtMin = $pdo->prepare("SELECT COUNT(*) AS total FROM contribuyente.tab_mnm WHERE da_mineral ILIKE :mineral");
        $stmtMin->execute(array(':mineral' => '%' . $min['nombre'] . '%'));
        $c = (int)$stmtMin->fetchColumn();
        if ($c > 0) {
            $mineralesPresencia[] = array(
                'id' => (int)$min['id'],
                'nombre' => $min['nombre'],
                'total' => $c
            );
        }
    }

    // Ordenar minerales por frecuencia
    usort($mineralesPresencia, function($a, $b) {
        return $b['total'] - $a['total'];
    });

    echo json_encode(array(
        'success' => true,
        'resumen' => array(
            'total_empresas' => (int)$summary['total_empresas'],
            'activas' => (int)$summary['activas'],
            'inactivas' => (int)$summary['inactivas'],
            'habilitadas_guia' => (int)$summary['habilitadas_guia'],
            'gps_verificado' => (int)$summary['gps_verificado'],
            'pendientes_gps' => (int)($summary['total_empresas'] - $summary['gps_verificado']),
            'porcentaje_gps' => $summary['total_empresas'] > 0 
                ? round(($summary['gps_verificado'] / $summary['total_empresas']) * 100, 1) 
                : 0
        ),
        'por_municipio' => $porMunicipio,
        'por_mineral' => $mineralesPresencia,
        'municipios_catalogo' => $municipiosLista,
        'minerales_catalogo' => $mineralesLista
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Error al calcular los KPIs de Minerales No Metálicos: ' . $e->getMessage()
    ));
}
