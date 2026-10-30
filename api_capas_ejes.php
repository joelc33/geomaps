<?php
/**
 * SEDATEZ - API de Capas y Polígonos de Municipios por Ejes del Estado Zulia
 * Plano ME-01: MAPA DE MUNICIPIOS POR EJES DEL ESTADO ZULIA (SEDATEZ / GEOTECH.ca)
 * 
 * Lee directamente desde la tabla 'mantenimiento.tab_poligonos_ejes'
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/config_db.php';

try {
    $pdo = getDbConnection();

    $filterEje = isset($_GET['eje']) ? trim($_GET['eje']) : '';

    // 1. Obtener conteo de contribuyentes por municipio desde las tablas de negocio
    $sqlEmpresas = "
        SELECT id_tab_municipio, COUNT(*) AS total_empresas
        FROM (
            SELECT id_tab_municipio FROM contribuyente.tab_mnm
            UNION ALL
            SELECT id_tab_municipio FROM preregistro.tab_minas
        ) uni
        WHERE id_tab_municipio IS NOT NULL
        GROUP BY id_tab_municipio
    ";
    $stmtEmp = $pdo->query($sqlEmpresas);
    $countsPorMunicipio = [];
    foreach ($stmtEmp->fetchAll() as $row) {
        $countsPorMunicipio[(int)$row['id_tab_municipio']] = (int)$row['total_empresas'];
    }

    // Verificar si la tabla de polígonos existe en PostgreSQL
    $hasPoligonosTable = false;
    try {
        $chk = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'mantenimiento' AND table_name = 'tab_poligonos_ejes' LIMIT 1");
        $hasPoligonosTable = (bool)$chk->fetchColumn();
    } catch (Exception $e) {
        $hasPoligonosTable = false;
    }

    if (!$hasPoligonosTable) {
        $fallbackGeoJson = __DIR__ . '/assets/geo/zulia_municipios_ejes.geojson';
        if (file_exists($fallbackGeoJson)) {
            $fallbackData = json_decode(file_get_contents($fallbackGeoJson), true);
            if (isset($fallbackData['features']) && is_array($fallbackData['features'])) {
                foreach ($fallbackData['features'] as &$feat) {
                    $mId = isset($feat['properties']['id_municipio']) ? (int)$feat['properties']['id_municipio'] : 0;
                    $feat['properties']['total_empresas'] = isset($countsPorMunicipio[$mId]) ? $countsPorMunicipio[$mId] : 0;
                }
            }
            echo json_encode($fallbackData, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // 2. Consultar la tabla de polígonos
    $whereClauses = ["in_activo = 1"];
    $params = [];

    if ($filterEje !== '' && strtolower($filterEje) !== 'all') {
        if (is_numeric($filterEje)) {
            $whereClauses[] = "codigo_eje = :codigo_eje";
            $params[':codigo_eje'] = 'EJE ' . (int)$filterEje;
        } else {
            $whereClauses[] = "LOWER(codigo_eje) = LOWER(:codigo_eje)";
            $params[':codigo_eje'] = $filterEje;
        }
    }

    $sql = "
        SELECT 
            id,
            codigo_eje,
            nombre_eje,
            id_tab_municipio,
            nombre_municipio,
            color_hex,
            color_borde,
            opacidad,
            leyenda_municipios,
            leyenda_minerales,
            minerales_json,
            geometria_geojson,
            orden
        FROM mantenimiento.tab_poligonos_ejes
        WHERE " . implode(' AND ', $whereClauses) . "
        ORDER BY orden ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 3. Estructurar Ejes para metadata de la Leyenda
    $ejesResumen = [
        'EJE 1' => [
            'codigo' => 'EJE 1',
            'nombre' => 'EJE 1: Metropolitano y Perijá',
            'color' => '#E05677',
            'color_borde' => '#FFFFFF',
            'opacidad' => 0.38,
            'leyenda_municipios' => 'JESÚS ENRIQUE LOSSADA, LA CAÑADA, MARACAIBO, MACHIQUES DE PERIJÁ, ROSARIO DE PERIJÁ, SAN FRANCISCO',
            'leyenda_minerales' => 'Arcilla, Arena, Arena de mina, Arena de río, Arena Roja, Arena y Manto, Caliza, Carbón, Grava, Grava de río y arena, Piedra ornamental',
            'minerales' => [
                'Arcilla', 'Arena', 'Arena de mina', 'Arena de río', 'Arena Roja',
                'Arena y Manto', 'Caliza', 'Carbón', 'Grava', 'Grava de río y arena', 'Piedra ornamental'
            ],
            'municipios' => []
        ],
        'EJE 2' => [
            'codigo' => 'EJE 2',
            'nombre' => 'EJE 2: Guajira y Costa Norte',
            'color' => '#1B8A43',
            'color_borde' => '#FFFFFF',
            'opacidad' => 0.38,
            'leyenda_municipios' => 'ALMIRANTE PADILLA, GUAJIRA, MARA, MIRANDA, SANTA RITA',
            'leyenda_minerales' => 'Arena, Carbón, Sal, Yeso',
            'minerales' => ['Arena', 'Carbón', 'Sal', 'Yeso'],
            'municipios' => []
        ],
        'EJE 3' => [
            'codigo' => 'EJE 3',
            'nombre' => 'EJE 3: Costa Oriental del Lago',
            'color' => '#0284C7',
            'color_borde' => '#FFFFFF',
            'opacidad' => 0.38,
            'leyenda_municipios' => 'BARALT, CABIMAS, LAGUNILLAS, SIMÓN BOLÍVAR, VALMORE RODRÍGUEZ',
            'leyenda_minerales' => 'Arena silícea, Arcilla, Capa vegetal y arena roja, Caliza',
            'minerales' => ['Arena silícea', 'Arcilla', 'Capa vegetal y arena roja', 'Caliza'],
            'municipios' => []
        ],
        'EJE 4' => [
            'codigo' => 'EJE 4',
            'nombre' => 'EJE 4: Sur del Lago',
            'color' => '#CA8A04',
            'color_borde' => '#FFFFFF',
            'opacidad' => 0.38,
            'leyenda_municipios' => 'COLÓN, CATATUMBO, JESÚS MARÍA SEMPRÚM, JAVIER PULGAR, SUCRE',
            'leyenda_minerales' => 'Arena, Carbón',
            'minerales' => ['Arena', 'Carbón'],
            'municipios' => []
        ]
    ];

    $features = [];

    foreach ($rows as $r) {
        $idMun = (int)$r['id_tab_municipio'];
        $codEje = $r['codigo_eje'];
        $minerales = !empty($r['minerales_json']) ? json_decode($r['minerales_json'], true) : [];
        $geometria = json_decode($r['geometria_geojson'], true);
        $totalEmpresas = isset($countsPorMunicipio[$idMun]) ? $countsPorMunicipio[$idMun] : 0;

        if (isset($ejesResumen[$codEje])) {
            $ejesResumen[$codEje]['municipios'][] = [
                'id_municipio' => $idMun,
                'nombre' => $r['nombre_municipio'],
                'total_empresas' => $totalEmpresas
            ];
        }

        $features[] = [
            'type' => 'Feature',
            'id' => (int)$r['id'],
            'properties' => [
                'id' => (int)$r['id'],
                'codigo_eje' => $codEje,
                'nombre_eje' => $r['nombre_eje'],
                'id_municipio' => $idMun,
                'nombre_municipio' => $r['nombre_municipio'],
                'color_hex' => $r['color_hex'],
                'color_borde' => $r['color_borde'] ?: '#FFFFFF',
                'opacidad' => (float)$r['opacidad'],
                'leyenda_municipios' => $r['leyenda_municipios'],
                'leyenda_minerales' => $r['leyenda_minerales'],
                'minerales' => $minerales,
                'total_empresas' => $totalEmpresas,
                'orden' => (int)$r['orden']
            ],
            'geometry' => $geometria
        ];
    }

    $response = [
        'success' => true,
        'type' => 'FeatureCollection',
        'metadata' => [
            'plano_codigo' => 'ME-01',
            'revision' => 'V-01',
            'titulo' => 'MAPA DE MUNICIPIOS POR EJES DEL ESTADO ZULIA',
            'institucion' => 'SEDATEZ - GEOTECH.ca',
            'fecha' => 'AGOSTO 2026',
            'total_poligonos' => count($features),
            'ejes' => array_values($ejesResumen)
        ],
        'features' => $features
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar capas de polígonos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
