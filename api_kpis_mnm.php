<?php
/**
 * SEDATEZ - API de KPIs y Catálogos para el Dashboard MNM y Registro de Oficio
 * Conforme a PLAN_DASHBOARD_MNM_SPEC.md y requerimiento de Registro de Oficio (preregistro.tab_minas)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config_db.php';

try {
    $pdo = getDbConnection();

    // 1. Resumen General y Desglose por Grupo (RITEZ + Registro de Oficio)
    $summarySql = "SELECT 
        COUNT(*) AS total_empresas,
        COUNT(CASE WHEN activo = true THEN 1 END) AS activas,
        COUNT(CASE WHEN activo = false THEN 1 END) AS inactivas,
        COUNT(CASE WHEN habilitado_guia = true THEN 1 END) AS habilitadas_guia,
        COUNT(CASE WHEN latitud_db IS NOT NULL AND latitud_db != '' THEN 1 END) AS gps_verificado,
        COUNT(*) AS total_empresas_db,
        -- Contadores del listado activo (Regulares + Registro de Oficio = 19)
        COUNT(CASE WHEN grupo_codigo = 'oficio' OR in_productor = true THEN 1 END) AS total_empresas,
        COUNT(CASE WHEN (grupo_codigo = 'oficio' OR in_productor = true) AND activo = true THEN 1 END) AS activas,
        COUNT(CASE WHEN (grupo_codigo = 'oficio' OR in_productor = true) AND activo = false THEN 1 END) AS inactivas,
        COUNT(CASE WHEN (grupo_codigo = 'oficio' OR in_productor = true) AND habilitado_guia = true THEN 1 END) AS habilitadas_guia,
        COUNT(CASE WHEN (grupo_codigo = 'oficio' OR in_productor = true) AND latitud_db IS NOT NULL AND latitud_db != '' THEN 1 END) AS gps_verificado,
        -- Desglose RITEZ
        COUNT(CASE WHEN grupo_codigo = 'ritez' THEN 1 END) AS total_ritez,
        COUNT(CASE WHEN grupo_codigo = 'ritez' AND activo = true THEN 1 END) AS activas_ritez,
        COUNT(CASE WHEN grupo_codigo = 'ritez' AND activo = false THEN 1 END) AS inactivas_ritez,
        COUNT(CASE WHEN grupo_codigo = 'ritez' AND in_productor = true THEN 1 END) AS productores_ritez,
        COUNT(CASE WHEN grupo_codigo = 'ritez' AND in_productor = false THEN 1 END) AS no_productores_ritez,
        COUNT(CASE WHEN grupo_codigo = 'ritez' AND latitud_db IS NOT NULL AND latitud_db != '' THEN 1 END) AS gps_ritez,
        -- Desglose Registro de Oficio
        COUNT(CASE WHEN grupo_codigo = 'oficio' THEN 1 END) AS total_oficio,
        COUNT(CASE WHEN grupo_codigo = 'oficio' AND latitud_db IS NOT NULL AND latitud_db != '' THEN 1 END) AS gps_oficio
    FROM (
        SELECT id, in_activo AS activo, in_llenado_guia AS habilitado_guia, in_productor, nu_latitud AS latitud_db, 'ritez' AS grupo_codigo FROM contribuyente.tab_mnm
        UNION ALL
        SELECT id, NULL::boolean AS activo, false AS habilitado_guia, NULL::boolean AS in_productor, nu_latitud AS latitud_db, 'oficio' AS grupo_codigo FROM preregistro.tab_minas
    ) uni_summary";
    $summary = $pdo->query($summarySql)->fetch();

    // 2. Conteo por Municipio (agregado de ambos grupos)
    // 2. Conteo por Municipio (agregado de ambos grupos en el listado)
    $munSql = "SELECT 
        COALESCE(mun.id, 0) AS id_municipio,
        COALESCE(mun.de_municipio, 'POR ASIGNAR') AS municipio,
        COUNT(uni.id) AS total,
        COUNT(CASE WHEN uni.activo = true THEN 1 END) AS activas,
        COUNT(CASE WHEN uni.activo = false THEN 1 END) AS inactivas,
        COUNT(CASE WHEN uni.grupo_codigo = 'ritez' THEN 1 END) AS total_ritez,
        COUNT(CASE WHEN uni.grupo_codigo = 'oficio' THEN 1 END) AS total_oficio
    FROM (
        SELECT id, id_tab_municipio, in_activo AS activo, in_productor, 'ritez' AS grupo_codigo FROM contribuyente.tab_mnm WHERE in_productor = true
        UNION ALL
        SELECT id, id_tab_municipio, true AS activo, NULL::boolean AS in_productor, 'oficio' AS grupo_codigo FROM preregistro.tab_minas
    ) uni
    LEFT JOIN mantenimiento.tab_municipio mun ON mun.id = uni.id_tab_municipio
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

    // 5. Conteo de presencia de minerales considerando ambos grupos
    // 5. Conteo de presencia de minerales considerando los contribuyentes en listado
    $mineralesPresencia = array();
    $minSql = "SELECT COUNT(*) AS total FROM (
                   SELECT da_mineral FROM contribuyente.tab_mnm WHERE in_productor = true
                   UNION ALL
                   SELECT da_mineral FROM preregistro.tab_minas
               ) uni_min WHERE uni_min.da_mineral ILIKE :mineral";
    $stmtMin = $pdo->prepare($minSql);

    foreach ($mineralesLista as $min) {
        $searchPattern = '%' . $min['nombre'] . '%';
        if (stripos($min['nombre'], 'Menito') !== false) {
            $searchPattern = '%Menito%';
        } elseif (stripos($min['nombre'], 'Lago') !== false) {
            $searchPattern = '%Lago%';
        }
        $stmtMin->execute(array(':mineral' => $searchPattern));
        $c = (int)$stmtMin->fetchColumn();
        if ($c > 0) {
            $mineralesPresencia[] = array(
                'id' => (int)$min['id'],
                'nombre' => $min['nombre'],
                'total' => $c
            );
        }
    }

    // Ordenar minerales por frecuencia descendente
    usort($mineralesPresencia, function($a, $b) {
        return $b['total'] - $a['total'];
    });

    echo json_encode(array(
        'success' => true,
        'resumen' => array(
            'total_empresas' => (int)$summary['total_empresas'],
            'total_registrados' => (int)$summary['total_empresas'],
            'total_general_db' => (int)$summary['total_empresas_db'],
            'activas' => (int)$summary['activas'],
            'inactivas' => (int)$summary['inactivas'],
            'habilitadas_guia' => (int)$summary['habilitadas_guia'],
            'gps_verificado' => (int)$summary['gps_verificado'],
            'pendientes_gps' => (int)($summary['total_empresas'] - $summary['gps_verificado']),
            'porcentaje_gps' => $summary['total_empresas'] > 0 
                ? round(($summary['gps_verificado'] / $summary['total_empresas']) * 100, 1) 
                : 0,
            'grupos' => array(
                'ritez' => array(
                    'nombre' => 'Minerales No Metálicos',
                    'total' => (int)$summary['total_ritez'],
                    'total' => (int)$summary['productores_ritez'],
                    'total_general_db' => (int)$summary['total_ritez'],
                    'productores' => (int)$summary['productores_ritez'],
                    'no_productores' => (int)$summary['no_productores_ritez'],
                    'regulares' => (int)$summary['productores_ritez'],
                    'no_regulares' => (int)$summary['no_productores_ritez'],
                    'activas' => (int)$summary['productores_ritez'],
                    'inactivas' => 0,
                    'gps_verificado' => (int)$summary['gps_ritez'],
                    'pendientes_gps' => (int)($summary['productores_ritez'] - $summary['gps_ritez'])
                ),
                'oficio' => array(
                    'nombre' => 'Registro de Oficio de Usuarios de Minerales No Metálicos',
                    'total' => (int)$summary['total_oficio'],
                    'gps_verificado' => (int)$summary['gps_oficio'],
                    'pendientes_gps' => (int)($summary['total_oficio'] - $summary['gps_oficio'])
                )
            )
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
