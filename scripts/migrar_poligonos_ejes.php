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
    echo "   -> Tabla y vista verificadas exitosamente.\n";

    // 2. Ruta del GeoJSON institucional
    $localGeoJson = __DIR__ . '/../assets/geo/zulia_municipios_ejes.geojson';
    if (!file_exists($localGeoJson)) {
        throw new Exception("No se encontró el archivo $localGeoJson");
    }

    echo "2. Leyendo geometrías oficiales desde $localGeoJson...\n";
    $data = json_decode(file_get_contents($localGeoJson), true);
    if (!$data || !isset($data['features']) || empty($data['features'])) {
        throw new Exception("El archivo GeoJSON está vacío o es inválido.");
    }

    // 3. Limpiar tabla previa si existían datos para asegurar consistencia
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

    $totalInsertados = 0;
    foreach ($data['features'] as $feature) {
        $p = $feature['properties'];
        $geom = $feature['geometry'];

        $insertStmt->execute([
            ':codigo_eje' => $p['codigo_eje'],
            ':nombre_eje' => $p['nombre_eje'],
            ':id_tab_municipio' => $p['id_municipio'],
            ':nombre_municipio' => $p['nombre_municipio'],
            ':color_hex' => $p['color_hex'],
            ':color_borde' => isset($p['color_borde']) ? $p['color_borde'] : '#FFFFFF',
            ':opacidad' => isset($p['opacidad']) ? $p['opacidad'] : 0.38,
            ':leyenda_municipios' => $p['leyenda_municipios'],
            ':leyenda_minerales' => $p['leyenda_minerales'],
            ':minerales_json' => json_encode(isset($p['minerales']) ? $p['minerales'] : [], JSON_UNESCAPED_UNICODE),
            ':geometria_geojson' => json_encode($geom),
            ':in_activo' => 1,
            ':orden' => isset($p['orden']) ? $p['orden'] : ($totalInsertados + 1)
        ]);
        $totalInsertados++;
    }

    echo "3. Insertados exitosamente $totalInsertados polígonos municipales en 'mantenimiento.tab_poligonos_ejes'.\n";
    echo "=== MIGRACIÓN COMPLETADA CON ÉXITO ===\n";

} catch (Exception $e) {
    echo "ERROR EN LA MIGRACIÓN: " . $e->getMessage() . "\n";
    exit(1);
}
