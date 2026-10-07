# Especificación Técnica y Plan de Implementación: Dashboard GIS de Contribuyentes MNM (SEDATEZ)

---

## 1. Resumen Ejecutivo y Diagnóstico de Datos

### 1.1 Contexto Institucional
El **Servicio Desconcentrado de Administración Tributaria del Estado Zulia (SEDATEZ)** tiene como competencia originaria la recaudación, fiscalización y control tributario sobre las actividades de exploración, extracción, procesamiento y transporte de **Minerales No Metálicos (MNM)** en el territorio del Estado Zulia, conforme a la legislación tributaria estadal.

El objetivo de este requerimiento es implementar el **Dashboard Cartográfico e Inteligente de Contribuyentes MNM**, permitiendo a los fiscales y directores de SEDATEZ:
1. Visualizar geoespacialmente las canteras, graveras, areneras y empresas procesadoras de minerales en los 21 municipios del Zulia.
2. Filtrar dinámicamente por tipo de mineral, municipio, parroquia, RIF o estatus de actividad.
3. Consultar la ficha técnica completa de cada empresa (domicilio fiscal, ubicación de la mina/terreno, representantes legales, código RITEZ y expediente SADL).
4. Actualizar o afinar las coordenadas geográficas (`nu_latitud` y `nu_longitud`) directamente desde el mapa cuando se realicen inspecciones en campo.

---

### 1.2 Diagnóstico de la Tabla `contribuyente.tab_mnm` y Relaciones en PostgreSQL (`etrib`)

Se realizó una inspección técnica directa a la base de datos `etrib` (`localhost:5432`), identificando las siguientes tablas clave:

#### Tabla Principal: `contribuyente.tab_mnm` (54 Registros Reales)
| Campo | Tipo | Nulo | Descripción y Utilidad en el Dashboard |
| :--- | :--- | :---: | :--- |
| `id` | `bigint` (PK) | No | Identificador único del registro MNM. |
| `id_tab_contribuyente` | `bigint` (FK) | Sí | Vínculo con `contribuyente.tab_contribuyente` (obtiene RITEZ, expediente, fecha registro). |
| `nu_documento_rif` | `varchar(30)` | No | RIF formal de la empresa (ej. `J-07013494-1`). |
| `de_razon_social` | `varchar` | Sí | Nombre comercial / Razón social (ej. `CEMENTOS CATATUMBO, C.A.`). |
| `dr_fiscal` | `varchar` | Sí | Dirección fiscal completa declarada por el contribuyente. |
| `dr_terreno` | `varchar` | Sí | Ubicación física de la cantera, mina o predio de explotación. |
| `tl_fijo` | `varchar` | Sí | Teléfono de contacto institucional. |
| `da_correo` | `varchar` | Sí | Correo electrónico de notificación fiscal. |
| `nb_responsable1` | `varchar` | Sí | Nombre del representante legal o apoderado. |
| `ap_responsable1` | `varchar` | Sí | Apellido del representante legal. |
| `tl_movil_resp` | `varchar` | Sí | Teléfono móvil de contacto directo. |
| `da_correo_resp` | `varchar` | Sí | Correo electrónico del representante legal. |
| `nb_propietario` | `varchar` | No | Nombre del propietario del terreno o mina. |
| `doc_propietario` | `varchar` | No | Documento de identidad del propietario. |
| `id_tab_municipio` | `bigint` (FK) | Sí | Vínculo con `mantenimiento.tab_municipio`. |
| `id_tab_parroquia` | `bigint` (FK) | Sí | Vínculo con `mantenimiento.tab_parroquia`. |
| `da_mineral` | `varchar` | Sí | Cadena de minerales autorizados/explotados (ej. *Piedra Caliza, Arena de Río, Arcilla*). |
| `da_superficie` | `varchar` | Sí | Extensión o área declarada de la mina o predio. |
| `nu_latitud` | `varchar` | Sí | Coordenada geográfica latitud (WGS84). |
| `nu_longitud` | `varchar` | Sí | Coordenada geográfica longitud (WGS84). |
| `in_activo` | `boolean` | Sí | Estatus tributario: `true` (Activo - 51 empresas), `false` (Inactivo - 3 empresas). |
| `in_llenado_guia` | `boolean` | Sí | Habilitación para emisión de guías de movilización de minerales. |

#### Tabla Vinculada: `contribuyente.tab_contribuyente`
| Campo | Tipo | Utilidad en el Dashboard |
| :--- | :--- | :--- |
| `co_ritez` | `varchar(12)` | Código único del Registro de Información Tributaria del Estado Zulia (ej. `2024-0003427`). |
| `nu_expediente` | `varchar` | Número de expediente administrativo oficial de SEDATEZ (ej. `SADL-2024-0000070`). |
| `fe_registro` | `date` | Fecha oficial de inscripción tributaria en el sistema. |

#### Tablas Geográficas: `mantenimiento.tab_municipio` y `mantenimiento.tab_parroquia`
* Contiene los **21 municipios del Estado Zulia** (`id_tab_estado = 23`) y sus respectivas parroquias.
* Distribución actual de empresas MNM en base de datos:
  * **Maracaibo:** 12 empresas
  * **Rosario de Perijá:** 9 empresas
  * **La Cañada de Urdaneta:** 6 empresas
  * **San Francisco:** 6 empresas
  * **Lagunillas:** 3 empresas
  * **Jesús Enrique Lossada:** 3 empresas
  * **Machiques de Perijá:** 1 empresa
  * **Mara:** 1 empresa
  * **Simón Bolívar:** 1 empresa
  * *Sin municipio asignado (para regularizar en GIS):* 12 empresas

#### Catálogo Oficial: `mantenimiento.tab_mnm`
Catálogo de los 15 Minerales No Metálicos oficiales del Estado Zulia:
1. Arcilla
2. Arena Blanca
3. Arena del Lago
4. Arena de Rio
5. Arena Lavada
6. Arena de Mina
7. Arena Roja
8. Barro para Relleno
9. Capa Vegetal
10. Menito Grueso y Fino
11. Piedra Caliza
12. Piedra de Rio
13. Polvillo de Caliza
14. Polvillo de Rio
15. Grava

---

### 1.3 Estrategia de Georreferenciación Híbrida
En los registros actuales de `contribuyente.tab_mnm`, los campos `nu_latitud` y `nu_longitud` se encuentran en blanco (pendientes de levantamiento georreferenciado).

**Solución Técnica:**
1. **Fallback Inteligente de Coordenadas:** Cuando una empresa no tenga `nu_latitud` o `nu_longitud` cargadas, el sistema proyecta temporalmente el marcador sobre el **centroide parroquial o municipal** correspondiente con una microdispersión matemática controlada (jitter determinista) para evitar que múltiples empresas queden encimadas en el mismo píxel.
2. **Identificador Visual de Estado GPS:**
   * 🟢 **Georreferenciación Exacta (GPS Verificado):** Coordenadas fijadas en campo.
   * 🟡 **Georreferenciación Parroquial (Aproximada):** Coordenadas calculadas según el municipio y parroquia declarada.
3. **Módulo de Fijación de Coordenadas (Tool en Vivo):** Los fiscales podrán abrir la ficha técnica, arrastrar el pin en el mapa o hacer clic en la ubicación satelital exacta de la cantera y pulsar **"Fijar Coordenadas GPS"**, guardando `nu_latitud` y `nu_longitud` directamente en PostgreSQL con registro de auditoría.

---

## 2. Especificación Arquitectónica y Funcional del Dashboard

```
┌─────────────────────────────────────────────────────────────────────────────────────────────┐
│                       ARQUITECTURA DEL DASHBOARD GIS SEDATEZ                                │
├─────────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                             │
│  [ BANNER SUPERIOR ]: Logo SEDATEZ • Funcionario Activo (Sesión) • KPIs Rápidos • Logout   │
│                                                                                             │
├───────────────────────────────┬─────────────────────────────────────────────────────────────┤
│   SIDEBAR DE CONTROL (12%)    │         VISOR CARTOGRÁFICO INTERACTIVO (88%)               │
│                               │                                                             │
│ 1. Buscador Predictivo        │ • Capas: Satélite / Híbrido / Callejero (Google Maps)       │
│    (RIF, Razón Social, RITEZ) │ • Marcadores diferenciados por Mineral y Estatus            │
│ 2. Filtro de Municipio (21)   │ • Clusters dinámicos por densidad geográfica                │
│ 3. Filtro de Mineral (15 MNM) │ • Popups Interactivos con resumen tributario                │
│ 4. Filtro de Estatus (Act/In) │                                                             │
│ 5. Tarjetas de Métricas:      ├─────────────────────────────────────────────────────────────┤
│    • Total: 54 empresas       │              OFFCANVAS / MODAL DETALLE EMPRESA              │
│    • Activas: 51 | Inactiv: 3 │                                                             │
│    • Con GPS: 0 | Parroquia:54│ • RIF, RITEZ, SADL-Expediente, Fechas, Propietario          │
│ 6. Lista Rápida de Resultados │ • Domicilio Fiscal vs Terreno de Mina                       │
│    (Clic centra el mapa)      │ • Minerales Explotados y Superficie                         │
│                               │ • Editor de Coordenadas GPS en Tiempo Real                  │
└───────────────────────────────┴─────────────────────────────────────────────────────────────┘
```

---

## 3. Especificación Backend (APIs y Modelos)

### 3.1 Endpoints RESTful para Apache / PHP

#### 3.1.1 `GET /api_empresas_mnm.php`
Retorna el listado completo o filtrado de empresas georreferenciadas con formato GeoJSON y JSON extendido.

* **Parámetros de Consulta (Query Params Opcionales):**
  * `municipio`: ID del municipio (`id_tab_municipio`) o texto.
  * `mineral`: Cadena o ID del mineral a filtrar.
  * `estatus`: `1` (activo), `0` (inactivo), `all` (todos).
  * `q`: Búsqueda textual sobre `de_razon_social`, `nu_documento_rif`, `co_ritez` o `nu_expediente`.

* **Estructura de la Respuesta JSON:**
```json
{
  "success": true,
  "total": 54,
  "filtrados": 54,
  "data": [
    {
      "id": 6,
      "rif": "J-07013494-1",
      "razon_social": "CEMENTOS CATATUMBO, C.A.",
      "ritez": "2024-0001052",
      "expediente": "SADL-2024-0000018",
      "fecha_registro": "2024-01-15",
      "activo": true,
      "estatus_texto": "Activo",
      "minerales": ["Arcilla", "Arena de Mina", "Piedra Caliza"],
      "minerales_raw": "Arcilla, Arena de Mina, Piedra Caliza",
      "superficie": "45.5 Ha",
      "direccion_fiscal": "Sector San Francisco, Av 5...",
      "direccion_terreno": "Km 18 Vía Perijá, Cantera La Paz",
      "municipio_id": 322,
      "municipio": "MARACAIBO",
      "parroquia_id": 1073,
      "parroquia": "CRISTO DE ARANZA",
      "contacto": {
        "responsable": "Carlos Mendoza",
        "telefono_movil": "0414-6123456",
        "telefono_fijo": "0261-7981122",
        "correo": "contacto@cementoscatatumbo.com"
      },
      "propietario": {
        "nombre": "CEMENTOS CATATUMBO C.A.",
        "documento": "J-07013494-1"
      },
      "geometria": {
        "lat": 10.6589,
        "lng": -71.6296,
        "es_gps_exacto": false,
        "tipo_coordenada": "CENTROIDE_PARROQUIA"
      }
    }
  ]
}
```

#### 3.1.2 `GET /api_kpis_mnm.php`
Retorna los agregados estadísticos para actualizar los contadores y gráficos del panel lateral:
* Total de empresas.
* Total de activas vs inactivas.
* Empresas por municipio (conteo agrupado).
* Top de minerales más explotados.
* Porcentaje de cobertura de georreferenciación GPS.

#### 3.1.3 `POST /api_actualizar_coordenadas.php`
Actualiza de forma persistente las coordenadas geográficas de un contribuyente en `contribuyente.tab_mnm`.

* **Payload de Entrada (JSON):**
```json
{
  "id": 6,
  "latitud": "10.642135",
  "longitud": "-71.684210",
  "observacion": "Coordenadas verificadas en inspección fiscal de campo"
}
```

* **Validaciones:**
  * Sesión de usuario autenticada (`$_SESSION['auth_user']`).
  * Validación de rango geográfico del Estado Zulia:
    * Latitud entre `8.3000` y `12.0000`.
    * Longitud entre `-73.5000` y `-70.5000`.
* **Acciones:**
  * Actualiza `nu_latitud` y `nu_longitud` en `contribuyente.tab_mnm`.
  * Registra evento de auditoría en `auditoria.tab_transaccion` o log de cambios.
* **Respuesta:**
```json
{
  "success": true,
  "message": "Coordenadas GPS actualizadas exitosamente en el sistema SEDATEZ",
  "coordenadas": {
    "lat": 10.642135,
    "lng": -71.684210
  }
}
```

---

### 3.2 Especificación en Laravel 11 (Modelos y Controladores)

#### 3.2.1 Modelo `App\Models\ContribuyenteMnm` (`app/Models/ContribuyenteMnm.php`)
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContribuyenteMnm extends Model
{
    protected $table = 'contribuyente.tab_mnm';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_tab_contribuyente',
        'nu_documento_rif',
        'de_razon_social',
        'dr_fiscal',
        'dr_terreno',
        'tl_fijo',
        'da_correo',
        'nb_responsable1',
        'ap_responsable1',
        'tl_movil_resp',
        'da_correo_resp',
        'nb_propietario',
        'doc_propietario',
        'id_tab_municipio',
        'id_tab_parroquia',
        'dr_terreno',
        'da_superficie',
        'da_mineral',
        'nu_latitud',
        'nu_longitud',
        'in_activo',
        'in_llenado_guia',
    ];

    protected $casts = [
        'in_activo' => 'boolean',
        'in_llenado_guia' => 'boolean',
    ];

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class, 'id_tab_contribuyente', 'id');
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'id_tab_municipio', 'id');
    }

    public function parroquia()
    {
        return $this->belongsTo(Parroquia::class, 'id_tab_parroquia', 'id');
    }

    public function getArrayMineralesAttribute()
    {
        if (empty($this->da_mineral)) return [];
        return array_map('trim', explode(',', $this->da_mineral));
    }
}
```

#### 3.2.2 Modelo `App\Models\Contribuyente` (`app/Models/Contribuyente.php`)
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contribuyente extends Model
{
    protected $table = 'contribuyente.tab_contribuyente';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nu_documento_rif',
        'co_ritez',
        'nu_expediente',
        'fe_registro',
        'in_activo',
    ];
}
```

#### 3.2.3 Controlador `App\Http\Controllers\Dashboard\MnmDashboardController`
```php
namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContribuyenteMnm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MnmDashboardController extends Controller
{
    public function getEmpresas(Request $request)
    {
        $query = ContribuyenteMnm::with(['contribuyente', 'municipio', 'parroquia']);

        if ($request->filled('municipio')) {
            $query->where('id_tab_municipio', $request->municipio);
        }

        if ($request->filled('estatus') && $request->estatus !== 'all') {
            $query->where('in_activo', $request->boolean('estatus'));
        }

        if ($request->filled('mineral')) {
            $query->where('da_mineral', 'ILIKE', '%' . $request->mineral . '%');
        }

        if ($request->filled('q')) {
            $term = '%' . $request->q . '%';
            $query->where(function ($q) use ($term) {
                $q->where('de_razon_social', 'ILIKE', $term)
                  ->orWhere('nu_documento_rif', 'ILIKE', $term)
                  ->orWhereHas('contribuyente', function ($sub) use ($term) {
                      $sub->where('co_ritez', 'ILIKE', $term)
                          ->orWhere('nu_expediente', 'ILIKE', $term);
                  });
            });
        }

        $empresas = $query->get();

        return response()->json([
            'success' => true,
            'total' => $empresas->count(),
            'data' => $empresas
        ]);
    }

    public function actualizarCoordenadas(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:contribuyente.tab_mnm,id',
            'latitud' => 'required|numeric|between:8.3,12.0',
            'longitud' => 'required|numeric|between:-73.5,-70.5',
        ]);

        $mnm = ContribuyenteMnm::findOrFail($request->id);
        $mnm->update([
            'nu_latitud' => (string)$request->latitud,
            'nu_longitud' => (string)$request->longitud,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Coordenadas actualizadas exitosamente',
            'coordenadas' => [
                'lat' => (float)$request->latitud,
                'lng' => (float)$request->longitud
            ]
        ]);
    }
}
```

---

## 4. Especificación Frontend (Visor Cartográfico y UI)

### 4.1 Estructura Visual del Dashboard
1. **Sidebar Izquierdo (Filtros y Métricas):**
   * Ancho compacto (12% en pantallas de escritorio, colapsable a 48px).
   * **Buscador global:** Input con debounce (300ms) que filtra dinámicamente tanto la lista como los marcadores en el mapa.
   * **Selector de Municipio:** Menú desplegable con los 21 municipios del Zulia más la opción *"Todos los Municipios"*.
   * **Selector de Mineral:** Filtro con los minerales más explotados (Piedra Caliza, Arena de Río, Arcilla, Grava, Menito, etc.).
   * **Selector de Estatus:** Botones segmentados (*Todos*, *Activos*, *Inactivos*).
   * **Contadores en Tiempo Real:**
     * Badge numérico de resultados visibles.
     * Lista resumida con tarjeta por empresa que incluye RIF, nombre y botón *"Centrar en Mapa"*.

2. **Visor Cartográfico (Google Maps Oficial con respaldo Leaflet):**
   * Configuración de centro: `[10.4500, -71.8000]` con nivel de zoom `8.5` (cobertura completa de la Cuenca del Lago de Maracaibo y Estado Zulia).
   * Controles de visualización:
     * Alternador Satélite / Mapa de Carreteras.
     * Botón de geolocalización del fiscal en campo.
     * Control de zoom y pantalla completa.
   * **Marcadores y Clusters:**
     * Iconos con color distintivo según el mineral principal (ej. Gris piedra para Caliza, Dorado arena para Arenas, Naranja ladrillo para Arcilla).
     * Distinción visual para empresas inactivas (marcador tenue con borde rojo).
     * Infowindow enriquecido con CSS institucional de SEDATEZ:
       * Título con Razón Social y RIF.
       * Etiqueta de Municipio y Parroquia.
       * Lista de minerales con etiquetas ("pills").
       * Botón primario: *"Ver Ficha y Georreferenciar"*.

3. **Ficha Técnica Offcanvas (Panel Lateral Deslizante):**
   * Se abre al hacer clic en un marcador o en la lista del sidebar.
   * Pestañas organizadas:
     * **1. Datos Corporativos:** Razón Social, RIF, Código RITEZ, Nro. Expediente, Fecha de Inscripción.
     * **2. Actividad Minera:** Minerales autorizados, Superficie de explotación, Habilitación de Guía de Transporte.
     * **3. Ubicación y Predio:** Domicilio fiscal, Dirección del terreno/mina, Parroquia y Municipio.
     * **4. Contactos Legales:** Representante, Propietario del terreno, Teléfonos y Correos.
     * **5. Georreferenciación GPS:**
       * Coordenadas actuales (Lat / Lng).
       * Estado: *"GPS Verificado"* vs *"Aproximación Parroquial"*.
       * Botón interactivo: *"Fijar Nueva Ubicación en el Mapa"* (activa modo de clic en el mapa para capturar las coordenadas exactas de la cantera).
       * Botón *"Guardar Coordenadas en Base de Datos"*.

---

## 5. Diccionario de Datos y Mapeo SQL

### Consulta SQL Unificada para el Dashboard
```sql
SELECT 
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
    mun.id AS id_municipio,
    mun.de_municipio AS municipio,
    par.id AS id_parroquia,
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
ORDER BY m.de_razon_social ASC;
```

---

## 6. Plan de Implementación Paso a Paso

### Fase 1: Desarrollo del Servicio de Datos (`api_empresas_mnm.php` y `api_kpis_mnm.php`)
* **Paso 1.1:** Crear `api_empresas_mnm.php` con la consulta SQL unificada anterior, conectando a `etrib` mediante `config_db.php`.
* **Paso 1.2:** Implementar la lógica de cálculo de coordenadas centroidales deterministas por municipio/parroquia cuando `nu_latitud` o `nu_longitud` sean nulas.
* **Paso 1.3:** Crear `api_actualizar_coordenadas.php` para guardar `nu_latitud` y `nu_longitud` en la base de datos al ser ajustadas por el fiscal.
* **Paso 1.4:** Crear `api_kpis_mnm.php` para abastecer los indicadores numéricos del dashboard.

### Fase 2: Implementación de Modelos y Controladores en Laravel
* **Paso 2.1:** Crear `app/Models/ContribuyenteMnm.php` y `app/Models/Contribuyente.php`.
* **Paso 2.2:** Crear `app/Http/Controllers/Dashboard/MnmDashboardController.php`.
* **Paso 2.3:** Registrar las rutas en `routes/api.php` y `routes/web.php`.

### Fase 3: Integración del Frontend en `index.html`
* **Paso 3.1:** Actualizar el Sidebar con buscador, selectores de municipio y mineral, y contadores de empresas.
* **Paso 3.2:** Conectar el visor cartográfico (Google Maps / Leaflet) para consumir `/api_empresas_mnm.php` y dibujar los 54 contribuyentes reales en el mapa.
* **Paso 3.3:** Crear el Infowindow personalizado con diseño SEDATEZ para cada marcador.
* **Paso 3.4:** Crear el componente Offcanvas/Modal para la Ficha Técnica y edición de coordenadas.

### Fase 4: Pruebas y Validación con Datos Reales
* **Paso 4.1:** Validar la carga de los 54 registros de `contribuyente.tab_mnm`.
* **Paso 4.2:** Verificar el filtrado en tiempo real por municipio (ej. Maracaibo: 12, Rosario de Perijá: 9, La Cañada: 6).
* **Paso 4.3:** Probar la actualización de coordenadas GPS de una empresa y confirmar el guardado persistente en PostgreSQL.
* **Paso 4.4:** Verificar la respuesta fluida del mapa y compatibilidad multidispositivo.
