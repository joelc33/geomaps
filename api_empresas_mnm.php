<?php
/**
 * SEDATEZ - API de Contribuyentes de Minerales No Metálicos (MNM)
 * Conforme a PLAN_DASHBOARD_MNM_SPEC.md
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config_db.php';

// Centroides de municipios de referencia (Latitud, Longitud)
$municipioCentroids = array(
    312 => array('lat' => 10.8833, 'lng' => -71.6333), // Almirante Padilla
    313 => array('lat' => 9.8000,  'lng' => -71.0500), // Baralt
    314 => array('lat' => 10.3958, 'lng' => -71.4417), // Cabimas
    315 => array('lat' => 9.0833,  'lng' => -72.5500), // Catatumbo
    316 => array('lat' => 8.9833,  'lng' => -71.9167), // Colón
    317 => array('lat' => 10.5833, 'lng' => -71.8667), // Jesús Enrique Lossada
    318 => array('lat' => 10.4194, 'lng' => -71.7456), // La Cañada de Urdaneta
    319 => array('lat' => 10.1333, 'lng' => -71.2500), // Lagunillas
    320 => array('lat' => 10.0644, 'lng' => -72.5450), // Machiques de Perijá
    321 => array('lat' => 10.9639, 'lng' => -71.7486), // Mara
    322 => array('lat' => 10.6589, 'lng' => -71.6296), // Maracaibo
    323 => array('lat' => 10.7444, 'lng' => -71.4889), // Miranda
    324 => array('lat' => 11.3833, 'lng' => -71.9000), // La Guajira
    325 => array('lat' => 10.3242, 'lng' => -72.3128), // Rosario de Perijá
    326 => array('lat' => 10.5333, 'lng' => -71.5167), // Santa Rita
    327 => array('lat' => 9.3000,  'lng' => -71.1000), // Sucre
    328 => array('lat' => 9.9333,  'lng' => -71.1833), // Valmore Rodríguez
    329 => array('lat' => 8.6500,  'lng' => -71.6000), // Francisco Javier Pulgar
    330 => array('lat' => 8.9167,  'lng' => -72.6000), // Jesús María Semprún
    331 => array('lat' => 10.5375, 'lng' => -71.6425), // San Francisco
    332 => array('lat' => 10.2667, 'lng' => -71.3667)  // Simón Bolívar
);

// Centroides de parroquias mineras conocidas
$parroquiaCentroids = array(
    1037 => array('lat' => 10.5500, 'lng' => -71.8800), // JOSÉ RAMÓN YÉPEZ
    1041 => array('lat' => 10.4200, 'lng' => -71.7500), // ANDRÉS BELLO
    1042 => array('lat' => 10.4000, 'lng' => -71.7300), // CHIQUINQUIRÁ
    1046 => array('lat' => 10.1500, 'lng' => -71.2400), // CAMPO MARA
    1047 => array('lat' => 10.1200, 'lng' => -71.2200), // ELEAZAR LÓPEZ CONTRERAS
    1052 => array('lat' => 10.0500, 'lng' => -72.5000), // SAN JOSÉ PERIJÁ
    1054 => array('lat' => 10.9800, 'lng' => -71.7300), // SAN RAFAEL
    1076 => array('lat' => 10.6800, 'lng' => -71.6900), // ANTONIO BORJAS ROMERO
    1065 => array('lat' => 10.6100, 'lng' => -71.6300), // CRISTO DE ARANZA
    1068 => array('lat' => 10.6300, 'lng' => -71.6700), // FRANCISCO EUGENIO BUSTAMANTE
    1073 => array('lat' => 10.6700, 'lng' => -71.6000), // OLEGARIO VILLALOBOS
    1078 => array('lat' => 10.6600, 'lng' => -71.7500), // SAN ISIDRO (canteras oeste)
    1077 => array('lat' => 10.6900, 'lng' => -71.6800), // VENANCIO PULGAR
    1088 => array('lat' => 10.3300, 'lng' => -72.3100), // EL ROSARIO
    1090 => array('lat' => 10.2800, 'lng' => -72.3600), // SIXTO ZAMBRANO
    1110 => array('lat' => 10.5100, 'lng' => -71.6300), // EL BAJO
    1117 => array('lat' => 10.2700, 'lng' => -71.3600)  // RAFAEL URDANETA
);

try {
    $pdo = getDbConnection();

    // Filtros recibidos por GET
    $municipioParam = isset($_GET['municipio']) ? trim($_GET['municipio']) : '';
    $mineralParam   = isset($_GET['mineral']) ? trim($_GET['mineral']) : '';
    $estatusParam   = isset($_GET['estatus']) ? trim($_GET['estatus']) : '';
    $searchParam    = isset($_GET['q']) ? trim($_GET['q']) : '';

    $whereClauses = array();
    $params = array();

    // Filtro por Municipio
    if ($municipioParam !== '' && $municipioParam !== 'all') {
        if (is_numeric($municipioParam)) {
            $whereClauses[] = "m.id_tab_municipio = :municipio";
            $params[':municipio'] = (int)$municipioParam;
        } else {
            $whereClauses[] = "LOWER(mun.de_municipio) = LOWER(:municipio)";
            $params[':municipio'] = $municipioParam;
        }
    }

    // Filtro por Mineral
    if ($mineralParam !== '' && $mineralParam !== 'all') {
        if (stripos($mineralParam, 'Menito') !== false) {
            $whereClauses[] = "m.da_mineral ILIKE :mineral";
            $params[':mineral'] = '%Menito%';
        } elseif (stripos($mineralParam, 'Lago') !== false) {
            $whereClauses[] = "m.da_mineral ILIKE :mineral";
            $params[':mineral'] = '%Lago%';
        } else {
            $whereClauses[] = "m.da_mineral ILIKE :mineral";
            $params[':mineral'] = '%' . $mineralParam . '%';
        }
    }

    // Filtro por Estatus (Activo/Inactivo)
    if ($estatusParam !== '' && $estatusParam !== 'all') {
        if ($estatusParam === '1' || $estatusParam === 'true' || $estatusParam === 'activo') {
            $whereClauses[] = "m.in_activo = true";
        } elseif ($estatusParam === '0' || $estatusParam === 'false' || $estatusParam === 'inactivo') {
            $whereClauses[] = "m.in_activo = false";
        }
    }

    // Filtro de Búsqueda de Texto
    if ($searchParam !== '') {
        $whereClauses[] = "(m.de_razon_social ILIKE :q OR m.nu_documento_rif ILIKE :q OR c.co_ritez ILIKE :q OR c.nu_expediente ILIKE :q OR mun.de_municipio ILIKE :q)";
        $params[':q'] = '%' . $searchParam . '%';
    }

    $whereSql = '';
    if (count($whereClauses) > 0) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    $sql = "SELECT 
                m.id,
                m.nu_documento_rif AS rif,
                m.de_razon_social AS razon_social,
                c.co_ritez AS ritez,
                c.nu_expediente AS expediente,
                c.fe_registro AS fecha_registro,
                m.in_activo AS activo,
                m.in_llenado_guia AS habilitado_guia,
                m.da_mineral AS minerales_raw,
                m.da_superficie AS superficie,
                m.dr_fiscal AS direccion_fiscal,
                m.dr_terreno AS direccion_terreno,
                m.id_tab_municipio AS id_municipio,
                mun.de_municipio AS municipio,
                m.id_tab_parroquia AS id_parroquia,
                par.de_parroquia AS parroquia,
                m.tl_fijo AS telefono_fijo,
                m.da_correo AS correo_empresa,
                m.nb_responsable1 AS responsable_nombre,
                m.ap_responsable1 AS responsable_apellido,
                m.tl_movil_resp AS responsable_telefono,
                m.da_correo_resp AS responsable_correo,
                m.nb_propietario AS propietario_nombre,
                m.doc_propietario AS propietario_documento,
                m.nu_latitud AS latitud_db,
                m.nu_longitud AS longitud_db,
                m.created_at,
                m.updated_at
            FROM contribuyente.tab_mnm m
            LEFT JOIN contribuyente.tab_contribuyente c ON c.id = m.id_tab_contribuyente
            LEFT JOIN mantenimiento.tab_municipio mun ON mun.id = m.id_tab_municipio
            LEFT JOIN mantenimiento.tab_parroquia par ON par.id = m.id_tab_parroquia
            $whereSql
            ORDER BY m.de_razon_social ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Consulta de Total General en BD
    $countSql = "SELECT COUNT(*) as total, 
                        COUNT(CASE WHEN in_activo = true THEN 1 END) as activos,
                        COUNT(CASE WHEN in_activo = false THEN 1 END) as inactivos,
                        COUNT(CASE WHEN nu_latitud IS NOT NULL AND nu_latitud != '' THEN 1 END) as con_gps
                 FROM contribuyente.tab_mnm";
    $statsRow = $pdo->query($countSql)->fetch();

    $empresas = array();

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $hasExactGps = false;
        $lat = null;
        $lng = null;
        $tipoCoordenada = 'CENTROIDE_PARROQUIA';

        // 1. Verificar si tiene coordenadas exactas en la BD
        $latDb = trim((string)$row['latitud_db']);
        $lngDb = trim((string)$row['longitud_db']);
        if ($latDb !== '' && $lngDb !== '' && is_numeric($latDb) && is_numeric($lngDb)) {
            $latVal = (float)$latDb;
            $lngVal = (float)$lngDb;
            if ($latVal >= 8.0 && $latVal <= 12.5 && $lngVal <= -70.0 && $lngVal >= -74.0) {
                $lat = $latVal;
                $lng = $lngVal;
                $hasExactGps = true;
                $tipoCoordenada = 'GPS_EXACTO';
            }
        }

        // Centroide de referencia (jurisdicción parroquial o municipal)
        $idParroquia = !empty($row['id_parroquia']) ? (int)$row['id_parroquia'] : null;
        $idMunicipio = !empty($row['id_municipio']) ? (int)$row['id_municipio'] : null;
        $baseLat = 10.5500;
        $baseLng = -71.7000;

        if ($idParroquia && isset($parroquiaCentroids[$idParroquia])) {
            $baseLat = $parroquiaCentroids[$idParroquia]['lat'];
            $baseLng = $parroquiaCentroids[$idParroquia]['lng'];
        } elseif ($idMunicipio && isset($municipioCentroids[$idMunicipio])) {
            $baseLat = $municipioCentroids[$idMunicipio]['lat'];
            $baseLng = $municipioCentroids[$idMunicipio]['lng'];
        }

        // 2. Si no tiene coordenadas exactas en BD, NO se genera punto en el mapa (queda nulo y pendiente)
        if (!$hasExactGps) {
            $lat = null;
            $lng = null;
            $tipoCoordenada = 'PENDIENTE';
        }

        // Lista de minerales formateada
        $mineralesArr = array();
        $mineralesRaw = isset($row['minerales_raw']) ? trim($row['minerales_raw']) : '';
        if ($mineralesRaw !== '') {
            $rawParts = explode(',', $mineralesRaw);
            foreach ($rawParts as $p) {
                $clean = trim($p);
                if ($clean !== '') $mineralesArr[] = $clean;
            }
        }

        // Determinar categoría y color visual predominante
        $categoriaColor = '#0b2545'; // Navy por defecto
        $mineralPrincipal = !empty($mineralesArr) ? $mineralesArr[0] : 'Mineral No Metálico';
        $minLower = strtolower($mineralPrincipal);

        if (strpos($minLower, 'caliza') !== false) {
            $categoriaColor = '#475569'; // Gris Caliza / Pizarra
        } elseif (strpos($minLower, 'arena') !== false) {
            $categoriaColor = '#d97706'; // Dorado Arena
        } elseif (strpos($minLower, 'arcilla') !== false || strpos($minLower, 'barro') !== false) {
            $categoriaColor = '#ea580c'; // Terracota Arcilla
        } elseif (strpos($minLower, 'grava') !== false || strpos($minLower, 'piedra') !== false) {
            $categoriaColor = '#0284c7'; // Azul Grava
        } elseif (strpos($minLower, 'menito') !== false) {
            $categoriaColor = '#059669'; // Verde Menito
        }

        $nombreResponsable = trim((isset($row['responsable_nombre']) ? $row['responsable_nombre'] : '') . ' ' . (isset($row['responsable_apellido']) ? $row['responsable_apellido'] : ''));

        $empresas[] = array(
            'id' => $id,
            'rif' => $row['rif'],
            'razon_social' => $row['razon_social'] ? $row['razon_social'] : 'EMPRESA SIN DENOMINACIÓN',
            'ritez' => $row['ritez'] ? $row['ritez'] : 'PENDIENTE',
            'expediente' => $row['expediente'] ? $row['expediente'] : 'S/E',
            'fecha_registro' => $row['fecha_registro'],
            'activo' => !empty($row['activo']),
            'estatus_texto' => !empty($row['activo']) ? 'Activo' : 'Inactivo',
            'habilitado_guia' => !empty($row['habilitado_guia']),
            'minerales' => $mineralesArr,
            'minerales_raw' => $mineralesRaw,
            'mineral_principal' => $mineralPrincipal,
            'color_mineral' => $categoriaColor,
            'superficie' => $row['superficie'] ? $row['superficie'] : 'No declarada',
            'direccion_fiscal' => !empty(trim((string)$row['direccion_fiscal'])) ? trim($row['direccion_fiscal']) : 'Sin dirección fiscal registrada',
            'direccion_terreno' => !empty(trim((string)$row['direccion_terreno'])) 
                ? trim($row['direccion_terreno']) 
                : (!empty(trim((string)$row['direccion_fiscal'])) ? trim($row['direccion_fiscal']) : 'Sin dirección fiscal registrada'),
            'id_municipio' => $row['id_municipio'] ? (int)$row['id_municipio'] : null,
            'municipio' => $row['municipio'] ? $row['municipio'] : 'POR ASIGNAR',
            'id_parroquia' => $row['id_parroquia'] ? (int)$row['id_parroquia'] : null,
            'parroquia' => $row['parroquia'] ? $row['parroquia'] : 'POR ASIGNAR',
            'telefono_fijo' => $row['telefono_fijo'],
            'correo' => $row['correo_empresa'],
            'responsable' => array(
                'nombre' => !empty($nombreResponsable) ? $nombreResponsable : 'No especificado',
                'telefono' => $row['responsable_telefono'],
                'correo' => $row['responsable_correo']
            ),
            'propietario' => array(
                'nombre' => $row['propietario_nombre'],
                'documento' => $row['propietario_documento']
            ),
            'latitud' => $lat,
            'longitud' => $lng,
            'tiene_coordenadas' => $hasExactGps,
            'centroide_referencial' => array(
                'lat' => $baseLat,
                'lng' => $baseLng
            ),
            'geometria' => array(
                'lat' => $lat,
                'lng' => $lng,
                'tiene_coordenadas' => $hasExactGps,
                'es_gps_exacto' => $hasExactGps,
                'tipo_coordenada' => $tipoCoordenada
            )
        );
    }

    echo json_encode(array(
        'success' => true,
        'total_general' => (int)$statsRow['total'],
        'total_activos' => (int)$statsRow['activos'],
        'total_inactivos' => (int)$statsRow['inactivos'],
        'total_con_gps' => (int)$statsRow['con_gps'],
        'total_pendientes_gps' => (int)($statsRow['total'] - $statsRow['con_gps']),
        'total_filtrados' => count($empresas),
        'data' => $empresas
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Error al consultar las empresas de Minerales No Metálicos: ' . $e->getMessage()
    ));
}
