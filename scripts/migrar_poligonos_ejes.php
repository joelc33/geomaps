<?php
/**
 * SEDATEZ - Script de Migración y Carga de Polígonos de Municipios por Ejes del Estado Zulia
 * Plano ME-01: MAPA DE MUNICIPIOS POR EJES DEL ESTADO ZULIA (SEDATEZ / GEOTECH.ca)
 */

require_once __DIR__ . '/../config_db.php';

echo "=== INICIANDO MIGRACIÓN DE POLÍGONOS POR EJES - SEDATEZ ===\n";

try {
    $pdo = getDbConnection();

    // 1. Crear la tabla en PostgreSQL (esquema mantenimiento)
    echo "1. Verificando/Creando tabla 'mantenimiento.tab_poligonos_ejes'...\n";
    $sqlTable = "
    CREATE TABLE IF NOT EXISTS mantenimiento.tab_poligonos_ejes (
        id SERIAL PRIMARY KEY,
        codigo_eje VARCHAR(20) NOT NULL,
        nombre_eje VARCHAR(150) NOT NULL,
        id_tab_municipio INTEGER NULL REFERENCES mantenimiento.tab_municipio(id) ON DELETE SET NULL,
        nombre_municipio VARCHAR(150) NOT NULL,
        color_hex VARCHAR(30) NOT NULL,
        color_borde VARCHAR(30) DEFAULT '#FFFFFF',
        opacidad NUMERIC(3,2) DEFAULT 0.38,
        leyenda_municipios TEXT NOT NULL,
        leyenda_minerales TEXT NOT NULL,
        minerales_json JSONB NULL,
        geometria_geojson JSONB NOT NULL,
        in_activo SMALLINT DEFAULT 1,
        orden INTEGER DEFAULT 1,
        created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
    );

    CREATE INDEX IF NOT EXISTS idx_tab_poligonos_ejes_codigo ON mantenimiento.tab_poligonos_ejes(codigo_eje);
    CREATE INDEX IF NOT EXISTS idx_tab_poligonos_ejes_mun ON mantenimiento.tab_poligonos_ejes(id_tab_municipio);

    CREATE OR REPLACE VIEW public.tab_poligonos_ejes AS 
    SELECT * FROM mantenimiento.tab_poligonos_ejes;
    ";

    $pdo->exec($sqlTable);
    echo "   -> Tabla y vista creadas exitosamente.\n";

    // 2. Definición de Ejes y Leyendas según plano institucional ME-01
    $ejesConfig = [
        'EJE 1' => [
            'nombre' => 'EJE 1: Metropolitano y Perijá',
            'color' => '#E05677', // Magenta / Rosado
            'color_borde' => '#FFFFFF',
            'opacidad' => 0.38,
            'leyenda_municipios' => 'JESÚS ENRIQUE LOSSADA, LA CAÑADA, MARACAIBO, MACHIQUES DE PERIJÁ, ROSARIO DE PERIJÁ, SAN FRANCISCO',
            'leyenda_minerales' => 'Arcilla, Arena, Arena de mina, Arena de río, Arena Roja, Arena y Manto, Caliza, Carbón, Grava, Grava de río y arena, Piedra ornamental',
            'minerales' => [
                'Arcilla', 'Arena', 'Arena de mina', 'Arena de río', 'Arena Roja',
                'Arena y Manto', 'Caliza', 'Carbón', 'Grava', 'Grava de río y arena', 'Piedra ornamental'
            ],
            'municipios' => [
                ['id_mun' => 317, 'nombre' => 'JESÚS ENRIQUE LOSSADA', 'geojson_idx' => 95,  'orden' => 1],
                ['id_mun' => 318, 'nombre' => 'LA CAÑADA DE URDANETA', 'geojson_idx' => 116, 'orden' => 2],
                ['id_mun' => 322, 'nombre' => 'MARACAIBO',              'geojson_idx' => 133, 'orden' => 3],
                ['id_mun' => 320, 'nombre' => 'MACHIQUES DE PERIJÁ',   'geojson_idx' => 128, 'orden' => 4],
                ['id_mun' => 325, 'nombre' => 'ROSARIO DE PERIJÁ',     'geojson_idx' => 185, 'orden' => 5],
                ['id_mun' => 331, 'nombre' => 'SAN FRANCISCO',          'geojson_idx' => 302, 'orden' => 6]
            ]
        ],
        'EJE 2' => [
            'nombre' => 'EJE 2: Guajira y Costa Norte',
            'color' => '#1B8A43', // Verde
            'color_borde' => '#FFFFFF',
            'opacidad' => 0.38,
            'leyenda_municipios' => 'ALMIRANTE PADILLA, GUAJIRA, MARA, MIRANDA, SANTA RITA',
            'leyenda_minerales' => 'Arena, Carbón, Sal, Yeso',
            'minerales' => ['Arena', 'Carbón', 'Sal', 'Yeso'],
            'municipios' => [
                ['id_mun' => 312, 'nombre' => 'ALMIRANTE PADILLA',      'geojson_idx' => 331, 'orden' => 7],
                ['id_mun' => 324, 'nombre' => 'GUAJIRA',                'geojson_idx' => 292, 'orden' => 8],
                ['id_mun' => 321, 'nombre' => 'MARA',                   'geojson_idx' => 132, 'orden' => 9],
                ['id_mun' => 323, 'nombre' => 'MIRANDA',                'geojson_idx' => 332, 'orden' => 10],
                ['id_mun' => 326, 'nombre' => 'SANTA RITA',              'geojson_idx' => 206, 'orden' => 11]
            ]
        ],
        'EJE 3' => [
            'nombre' => 'EJE 3: Costa Oriental del Lago',
            'color' => '#0284C7', // Azul / Celeste
            'color_borde' => '#FFFFFF',
            'opacidad' => 0.38,
            'leyenda_municipios' => 'BARALT, CABIMAS, LAGUNILLAS, SIMÓN BOLÍVAR, VALMORE RODRÍGUEZ',
            'leyenda_minerales' => 'Arena silícea, Arcilla, Capa vegetal y arena roja, Caliza',
            'minerales' => ['Arena silícea', 'Arcilla', 'Capa vegetal y arena roja', 'Caliza'],
            'municipios' => [
                ['id_mun' => 313, 'nombre' => 'BARALT',                 'geojson_idx' => 27,  'orden' => 12],
                ['id_mun' => 314, 'nombre' => 'CABIMAS',                'geojson_idx' => 39,  'orden' => 13],
                ['id_mun' => 319, 'nombre' => 'LAGUNILLAS',              'geojson_idx' => 119, 'orden' => 14],
                ['id_mun' => 332, 'nombre' => 'SIMÓN BOLÍVAR',          'geojson_idx' => 304, 'orden' => 15],
                ['id_mun' => 328, 'nombre' => 'VALMORE RODRÍGUEZ',      'geojson_idx' => 234, 'orden' => 16]
            ]
        ],
        'EJE 4' => [
            'nombre' => 'EJE 4: Sur del Lago',
            'color' => '#CA8A04', // Amarillo Mostaza / Ocre
            'color_borde' => '#FFFFFF',
            'opacidad' => 0.38,
            'leyenda_municipios' => 'COLÓN, CATATUMBO, JESÚS MARÍA SEMPRÚM, JAVIER PULGAR, SUCRE',
            'leyenda_minerales' => 'Arena, Carbón',
            'minerales' => ['Arena', 'Carbón'],
            'municipios' => [
                ['id_mun' => 316, 'nombre' => 'COLÓN',                  'geojson_idx' => 60,  'orden' => 17],
                ['id_mun' => 315, 'nombre' => 'CATATUMBO',              'geojson_idx' => 55,  'orden' => 18],
                ['id_mun' => 330, 'nombre' => 'JESÚS MARÍA SEMPRÚM',    'geojson_idx' => 96,  'orden' => 19],
                ['id_mun' => 329, 'nombre' => 'FRANCISCO JAVIER PULGAR', 'geojson_idx' => 78,  'orden' => 20],
                ['id_mun' => 327, 'nombre' => 'SUCRE',                  'geojson_idx' => 333, 'orden' => 21]
            ]
        ]
    ];

    // 3. Cargar el GeoJSON descargado
    $sourceGeoJsonPath = '/tmp/ven_adm2.geojson';
    if (!file_exists($sourceGeoJsonPath)) {
        throw new Exception("No se encontró el archivo $sourceGeoJsonPath");
    }

    echo "2. Leyendo geometrías desde $sourceGeoJsonPath...\n";
    $sourceData = json_decode(file_get_contents($sourceGeoJsonPath), true);
    if (!$sourceData || !isset($sourceData['features'])) {
        throw new Exception("El archivo GeoJSON es inválido.");
    }

    // 4. Limpiar tabla previa si existían datos para asegurar consistencia
    $pdo->exec("TRUNCATE TABLE mantenimiento.tab_poligonos_ejes RESTART IDENTITY;");

    $insertStmt = $pdo->prepare("
        INSERT INTO mantenimiento.tab_poligonos_ejes (
            codigo_eje, nombre_eje, id_tab_municipio, nombre_municipio,
            color_hex, color_borde, opacidad, leyenda_municipios, leyenda_minerales,
            minerales_json, geometria_geojson, in_activo, orden
        ) VALUES (
            :codigo_eje, :nombre_eje, :id_tab_municipio, :nombre_municipio,
            :color_hex, :color_borde, :opacidad, :leyenda_municipios, :leyenda_minerales,
            :minerales_json, :geometria_geojson, :in_activo, :orden
        )
    ");

    $featureCollection = [
        'type' => 'FeatureCollection',
        'metadata' => [
            'plano' => 'ME-01',
            'titulo' => 'MAPA DE MUNICIPIOS POR EJES DEL ESTADO ZULIA',
            'ente' => 'SEDATEZ - GEOTECH.ca',
            'fecha' => 'AGOSTO 2026',
            'total_municipios' => 21,
            'total_ejes' => 4
        ],
        'features' => []
    ];

    $totalInsertados = 0;

    foreach ($ejesConfig as $codEje => $cfg) {
        echo "   Procesando $codEje ({$cfg['nombre']})...\n";
        foreach ($cfg['municipios'] as $mun) {
            $idx = $mun['geojson_idx'];
            if (!isset($sourceData['features'][$idx])) {
                throw new Exception("Índice de feature $idx no existe.");
            }

            $feature = $sourceData['features'][$idx];
            $geom = $feature['geometry'];

            $insertStmt->execute([
                ':codigo_eje' => $codEje,
                ':nombre_eje' => $cfg['nombre'],
                ':id_tab_municipio' => $mun['id_mun'],
                ':nombre_municipio' => $mun['nombre'],
                ':color_hex' => $cfg['color'],
                ':color_borde' => $cfg['color_borde'],
                ':opacidad' => $cfg['opacidad'],
                ':leyenda_municipios' => $cfg['leyenda_municipios'],
                ':leyenda_minerales' => $cfg['leyenda_minerales'],
                ':minerales_json' => json_encode($cfg['minerales'], JSON_UNESCAPED_UNICODE),
                ':geometria_geojson' => json_encode($geom),
                ':in_activo' => 1,
                ':orden' => $mun['orden']
            ]);

            $totalInsertados++;

            // Agregar al FeatureCollection para exportación local
            $featureCollection['features'][] = [
                'type' => 'Feature',
                'properties' => [
                    'id_poligono' => $mun['id_mun'],
                    'codigo_eje' => $codEje,
                    'nombre_eje' => $cfg['nombre'],
                    'id_municipio' => $mun['id_mun'],
                    'nombre_municipio' => $mun['nombre'],
                    'color_hex' => $cfg['color'],
                    'color_borde' => $cfg['color_borde'],
                    'opacidad' => $cfg['opacidad'],
                    'leyenda_municipios' => $cfg['leyenda_municipios'],
                    'leyenda_minerales' => $cfg['leyenda_minerales'],
                    'minerales' => $cfg['minerales'],
                    'orden' => $mun['orden']
                ],
                'geometry' => $geom
            ];
        }
    }

    echo "3. Insertados exitosamente $totalInsertados polígonos municipales en 'mantenimiento.tab_poligonos_ejes'.\n";

    // 5. Guardar archivo local GeoJSON de respaldo en assets/geo/
    $targetGeoJson = __DIR__ . '/../assets/geo/zulia_municipios_ejes.geojson';
    file_put_contents($targetGeoJson, json_encode($featureCollection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "4. Archivo GeoJSON institucional generado en: $targetGeoJson\n";

    echo "=== MIGRACIÓN COMPLETADA CON ÉXITO ===\n";

} catch (Exception $e) {
    echo "ERROR EN LA MIGRACIÓN: " . $e->getMessage() . "\n";
    exit(1);
}
