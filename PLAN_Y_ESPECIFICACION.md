# ESPECIFICACIÓN TÉCNICA Y PLAN DE IMPLEMENTACIÓN
## Sistema Web Georreferenciado SEDATEZ (Laravel + PostgreSQL + Google Maps + Bootstrap)

**Documento:** Especificación Técnica (Spec) y Plan de Ejecución (Plan)  
**Versión:** 1.0.0  
**Fecha:** Octubre 2026  
**Entidad / Proyecto:** SEDATEZ (Servicio Desconcentrado de Administración Tributaria del Estado Zulia)  
**Stack Tecnológico:** Laravel 11 / PHP 8.3, PostgreSQL 16, Bootstrap 5.3, Google Maps JavaScript API v3.

---

## TABLA DE CONTENIDO
1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [PARTE I: ESPECIFICACIÓN TÉCNICA Y FUNCIONAL (SPEC)](#parte-i-especificación-técnica-y-funcional-spec)
   - 2.1 [Objetivo y Alcance](#21-objetivo-y-alcance)
   - 2.2 [Requisitos Funcionales (RF)](#22-requisitos-funcionales-rf)
   - 2.3 [Requisitos No Funcionales (RNF)](#23-requisitos-no-funcionales-rnf)
   - 2.4 [Modelo de Datos en PostgreSQL](#24-modelo-de-datos-en-postgresql)
   - 2.5 [Arquitectura del Sistema](#25-arquitectura-del-sistema)
   - 2.6 [Especificación de Rutas y Endpoints API](#26-especificación-de-rutas-y-endpoints-api)
   - 2.7 [Especificación UI/UX y Reglas de Interfaz](#27-especificación-uiux-y-reglas-de-interfaz)
   - 2.8 [Seguridad y Control de Acceso](#28-seguridad-y-control-de-acceso)
3. [PARTE II: PLAN DETALLADO DE IMPLEMENTACIÓN PASO A PASO (PLAN)](#parte-ii-plan-detallado-de-implementación-paso-a-paso-plan)
   - 3.1 [Fase 1: Preparación del Entorno y Base de Datos PostgreSQL](#fase-1-preparación-del-entorno-y-base-de-datos-postgresql)
   - 3.2 [Fase 2: Inicialización del Proyecto Laravel y Configuración](#fase-2-inicialización-del-proyecto-laravel-y-configuración)
   - 3.3 [Fase 3: Capa de Persistencia (Migraciones, Modelos, Seeders)](#fase-3-capa-de-persistencia-migraciones-modelos-seeders)
   - 3.4 [Fase 4: Lógica de Backend y Controladores](#fase-4-lógica-de-backend-y-controladores)
   - 3.5 [Fase 5: Vistas Blade con Bootstrap 5](#fase-5-vistas-blade-con-bootstrap-5)
   - 3.6 [Fase 6: Integración del Mapa de Google Maps y Lógica JS](#fase-6-integración-del-mapa-de-google-maps-y-lógica-js)
   - 3.7 [Fase 7: Configuración de Servidor Web (Apache) y Validación](#fase-7-configuración-de-servidor-web-apache-y-validación)
4. [Matriz de Trazabilidad y Criterios de Aceptación](#4-matriz-de-trazabilidad-y-criterios-de-aceptación)

---

## 1. Resumen Ejecutivo

El presente documento define los requerimientos de ingeniería de software, arquitectura de datos y plan de desarrollo para el **Sistema Web Georreferenciado SEDATEZ**. 

La aplicación permite a los funcionarios tributarios autenticarse de manera segura y acceder a un panel de control interactivo donde se visualiza el universo de empresas y contribuyentes sobre la cartografía de **Google Maps**. El diseño contempla una barra lateral ultra-compacta ($\le 10\%$ del ancho de pantalla) para maximizar la superficie cartográfica central y un banner institucional con la identidad corporativa de SEDATEZ y Laravel sobre la base de diseño de **Bootstrap 5**.

---

# PARTE I: ESPECIFICACIÓN TÉCNICA Y FUNCIONAL (SPEC)

## 2.1 Objetivo y Alcance

### Objetivo General
Desarrollar una aplicación web escalable, segura y de alto rendimiento utilizando el framework **Laravel** y base de datos relacional **PostgreSQL**, que facilite la localización y consulta expedita de contribuyentes en el mapa oficial mediante interacción directa entre un listado lateral y Google Maps.

### Alcance
- **Módulo de Autenticación:** Pantalla de Login con validación de credenciales de usuario institucional.
- **Módulo Dashboard:**
  - **Banner Superior:** Cabecera institucional con isotipo/logotipo de SEDATEZ, logotipo de Laravel, indicador de usuario activo y botón de cierre de sesión.
  - **Barra Lateral Izquierda ($\le 10\%$ de ancho):** Listado optimizado de nombres de empresas con indicador visual, buscador rápido y tooltip/popover descriptivo con RIF y detalles.
  - **Zona Cartográfica Central:** Visor de Google Maps centrado automáticamente en la empresa seleccionada, con marcador dinámico y ventana de información (InfoWindow) que detalla Nombre, RIF, Dirección y Coordenadas geográficas.

---

## 2.2 Requisitos Funcionales (RF)

| Código | Requisito | Descripción |
| :--- | :--- | :--- |
| **RF-01** | **Autenticación de Usuarios** | El sistema debe proveer un formulario de inicio de sesión seguro con correo electrónico/usuario y contraseña. Las sesiones deben estar protegidas contra CSRF y ataques de fijación de sesión. |
| **RF-02** | **Cierre de Sesión Seguro** | El usuario podrá cerrar su sesión en cualquier momento desde el banner superior, invalidando el token y redirigiendo a la pantalla de login. |
| **RF-03** | **Banner Institucional** | La cabecera superior debe mostrar de forma fija los logotipos oficiales de SEDATEZ y Laravel, el título del sistema y el perfil del usuario autenticado. |
| **RF-04** | **Barra Lateral Amplia y Ergonómica** | Debe existir un panel lateral a la izquierda con ancho optimizado y ergonómico (predeterminado 400px, con selector adaptable entre 340px, 400px y 460px), conteniendo la lista de empresas activas con scroll vertical independiente y visualización completa y clara de nombres comerciales, RIFs y direcciones. |
| **RF-05** | **Búsqueda y Filtrado en Sidebar** | El usuario podrá escribir en un campo de búsqueda con botón de limpieza y utilizar chips de filtrado por sectores económicos (Petróleo, Alimentos, Banca, Salud, etc.) para acotar la lista de empresas visibles en tiempo real. |
| **RF-06** | **Centrado Interactivo en Google Maps** | Al hacer clic en una empresa de la barra lateral, el mapa debe centrarse suavemente (`panTo`) en la latitud y longitud de dicha empresa y ajustar el nivel de zoom a detalle de calle (`zoom: 16`). |
| **RF-07** | **InfoWindow Enriquecida** | Al centrarse en la empresa, debe desplegarse automáticamente una ventana de información (`google.maps.InfoWindow`) sobre el marcador correspondiente, mostrando: Nombre Comercial, RIF, Dirección completa, Latitud y Longitud, además de enlace a navegación. |
| **RF-08** | **API de Empresas para Geocodificación** | El backend en Laravel debe exponer un endpoint JSON (`/api/v1/empresas`) que devuelva la colección de empresas con sus atributos espaciales para ser consumido por el cliente JavaScript. |

---

## 2.3 Requisitos No Funcionales (RNF)

| Código | Requisito | Métrica / Estándar |
| :--- | :--- | :--- |
| **RNF-01** | **Rendimiento** | Tiempo de renderizado inicial del dashboard $< 1.5$ segundos. El centrado y transición de marcadores debe ejecutarse a 60 FPS sin bloquear el hilo principal. |
| **RNF-02** | **Ergonomía de Interfaz y Legibilidad** | Las tarjetas de empresas en la barra lateral presentan tipografía clara (`0.92rem`), badges contrastados de RIF con tipografía JetBrains Mono, visualización de dirección completa en bloque de dos líneas con iconos semánticos, indicador de coordenadas geográficas y selector interactivo de dimensiones de ancho (340px / 400px / 460px) para máxima legibilidad. |
| **RNF-03** | **Diseño Responsivo con Bootstrap 5** | Interfaz construida con Bootstrap 5.3, con rejilla fluida y soporte para resoluciones de escritorio (Full HD $1920\times 1080$, HD $1366\times 768$, Laptop $1440\times 900$). En pantallas móviles se habilita modo offcanvas colapsable. |
| **RNF-04** | **Base de Datos PostgreSQL** | Persistencia en PostgreSQL utilizando tipos de datos numéricos de alta precisión (`NUMERIC(10, 7)`) para latitud y longitud, garantizando exactitud milimétrica. |
| **RNF-05** | **Seguridad** | Hashing de contraseñas mediante algoritmo Bcrypt/Argon2id. Variables de entorno seguras para la clave de API de Google Maps (`GOOGLE_MAPS_API_KEY`). |
| **RNF-06** | **Disponibilidad y Modo Resiliente** | La capa de mapas debe contemplar modo de contingencia (fallback visual) en caso de caída de conectividad con los servicios de Google Maps. |

---

## 2.4 Modelo de Datos en PostgreSQL

### 2.4.1 Diagrama Entidad-Relación (Mermaid)

```mermaid
erDiagram
    USERS ||--o{ EMPRESAS : "registra / administra"
    
    USERS {
        bigint id PK
        varchar name "Nombre del funcionario"
        varchar email UK "Correo institucional"
        timestamp email_verified_at
        varchar password "Hash Bcrypt"
        varchar remember_token
        timestamp created_at
        timestamp updated_at
    }

    EMPRESAS {
        bigint id PK
        varchar nombre "Nombre o Razón Social"
        varchar rif UK "RIF ej: J-12345678-9"
        text direccion "Dirección física o fiscal"
        numeric latitud "Latitud decimal (10,7)"
        numeric longitud "Longitud decimal (10,7)"
        varchar telefono "Teléfono de contacto (opcional)"
        varchar categoria "Sector económico / tributario"
        boolean activo "Estado operativo"
        bigint user_id FK "Usuario creador"
        timestamp created_at
        timestamp updated_at
    }
```

### 2.4.2 Estructura DDL en PostgreSQL

```sql
-- Creación de la base de datos
-- CREATE DATABASE mapa_sedatez WITH ENCODING 'UTF8';

-- Tabla de Usuarios
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Empresas
CREATE TABLE empresas (
    id BIGSERIAL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    rif VARCHAR(20) NOT NULL UNIQUE,
    direccion TEXT NOT NULL,
    latitud NUMERIC(10, 7) NOT NULL,
    longitud NUMERIC(10, 7) NOT NULL,
    telefono VARCHAR(50) NULL,
    categoria VARCHAR(100) DEFAULT 'Comercial',
    activo BOOLEAN DEFAULT TRUE,
    user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    
    -- Restricciones de integridad para coordenadas geográficas válidas
    CONSTRAINT chk_latitud CHECK (latitud >= -90.0000000 AND latitud <= 90.0000000),
    CONSTRAINT chk_longitud CHECK (longitud >= -180.0000000 AND longitud <= 180.0000000),
    -- Restricción de formato RIF venezolano (J, G, V, E, C seguido de números)
    CONSTRAINT chk_rif_formato CHECK (rif ~* '^[JGVEC]-[0-9]{8,9}-[0-9]$')
);

-- Índices para búsqueda rápida y geolocalización
CREATE INDEX idx_empresas_nombre ON empresas(nombre);
CREATE INDEX idx_empresas_rif ON empresas(rif);
CREATE INDEX idx_empresas_coordenadas ON empresas(latitud, longitud);
```

---

## 2.5 Arquitectura del Sistema

```mermaid
flowchart TD
    subgraph Cliente["Navegador Web (Frontend)"]
        UI_Login["Vista Login (Bootstrap 5)"]
        UI_Dash["Dashboard Principal"]
        subgraph Dashboard_Components["Componentes UI"]
            Banner["Banner Superior\n(Logo SEDATEZ + Laravel)"]
            Sidebar["Barra Lateral ≤ 10%\n(Lista de Empresas)"]
            Map["Contenedor Google Maps\n(Centro + Marcadores + InfoWindow)"]
        end
        JS_App["Módulo JS (Google Maps API v3)"]
    end

    subgraph Backend["Servidor Web & Aplicación Laravel"]
        Apache["Servidor Apache 2.4 / PHP 8.3"]
        Router["Laravel Routing (Web & API)"]
        AuthMiddleware["Middleware 'auth'"]
        AuthController["AuthController"]
        DashboardController["DashboardController"]
        EmpresaApiController["EmpresaApiController"]
        EloquentModel["Modelo Eloquent 'Empresa'"]
    end

    subgraph BaseDeDatos["Persistencia"]
        PostgreSQL[("PostgreSQL 16\n(mapa_sedatez)")]
    end

    UI_Login -->|POST /login| Router
    Router --> AuthController
    AuthController -->|Autenticado| UI_Dash

    UI_Dash --> Banner
    UI_Dash --> Sidebar
    UI_Dash --> Map

    Sidebar -->|Evento Click: Seleccionar Empresa| JS_App
    JS_App -->|panTo(lat, lng) + setZoom(16) + openInfoWindow()| Map

    JS_App -->|GET /api/v1/empresas (JSON)| Router
    Router --> AuthMiddleware
    AuthMiddleware --> EmpresaApiController
    EmpresaApiController --> EloquentModel
    EloquentModel -->|Consultas SQL| PostgreSQL
```

---

## 2.6 Especificación de Rutas y Endpoints API

### 2.6.1 Rutas Web (Blade Views)
- `GET /login`: Formulario de autenticación institucional.
- `POST /login`: Procesamiento y validación de credenciales.
- `POST /logout`: Cierre de sesión y destrucción segura de tokens.
- `GET /dashboard`: Vista del panel cartográfico protegida con `auth`.

### 2.6.2 Endpoints API REST (JSON)
- `GET /api/v1/empresas`: Retorna la lista completa de empresas activas con coordenadas para el mapa.
- `GET /api/v1/empresas/{id}`: Retorna el detalle completo de una empresa.

#### Ejemplo de Respuesta `GET /api/v1/empresas`:
```json
{
  "status": "success",
  "total": 12,
  "data": [
    {
      "id": 1,
      "nombre": "Empresas Polar - Cervecería Modelo",
      "rif": "J-00006372-9",
      "direccion": "Av. 68 con Calle 148, Km 10 Vía Perijá, San Francisco, Zulia",
      "latitud": 10.5987120,
      "longitud": -71.6854130,
      "categoria": "Industrial",
      "activo": true
    },
    {
      "id": 2,
      "nombre": "Cervecería Regional C.A.",
      "rif": "J-07001402-5",
      "direccion": "Av. 17 Los Haticos, Sector Los Haticos, Maracaibo, Zulia",
      "latitud": 10.6128450,
      "longitud": -71.6219400,
      "categoria": "Industrial",
      "activo": true
    }
  ]
}
```

---

## 2.7 Especificación UI/UX y Reglas de Interfaz (Sistema de Diseño BootstrapBrain)

### 2.7.0 Paleta de Colores y Guía de Estilos Oficial (BootstrapBrain)
El sistema visual adopta la plantilla y estándares cromáticos de **BootstrapBrain**:
* **Color Primario Corporativo:** `#0066FF` (Azul Eléctrico / Royal Blue). Usado en el Hero del login, botones de acción principal, marcadores de Google Maps y estados activos.
* **Color Primario Hover:** `#0052cc`.
* **Fondo Primario Suave:** `#EBF3FF` / `#e7f0ff` (utilizado para resaltar elementos seleccionados en la barra lateral y badges de coordenadas).
* **Fondo General de la Aplicación:** `#f8f9fa` (gris claro neutro).
* **Fondo de Tarjetas y Componentes:** `#ffffff` (blanco puro) con bordes delgados `#dee2e6` / `#eaedf1` y bordes redondeados (`border-radius: 6px` a `12px`).
* **Tipografía:** `Inter`, `-apple-system`, `BlinkMacSystemFont`, `Segoe UI`, `Roboto`, sans-serif.
* **Diseño del Login (Split 2 Columnas):**
  * **Columna Izquierda (50%):** Hero en azul `#0066FF` con marca institucional de SEDATEZ en blanco y copy de impacto.
  * **Columna Derecha (50%):** Tarjeta en blanco con título `Log in`, campos con etiquetas `Email *` y `Password *` con asterisco rojo, y botón `Log in now` (exclusivo para credenciales fiscales institucionales).

### 2.7.1 Diseño y Ergonomía de la Barra Lateral Amplia (340px - 460px, Default 400px)
- **Evolución y Optimización por Solicitud de Usuario:** Atendiendo la instrucción de diseño *"has el listado más ancho de empresas, que se vea bien"*, se reemplazó la restricción del 10% por un ancho dedicado y espacioso de **400px** (`--sidebar-width: 400px`), con selector interactivo en tiempo real que permite alternar entre:
  * **340px:** Modo compacto.
  * **400px (Predeterminado):** Modo recomendado, equilibrio ideal entre visibilidad de datos y amplitud del mapa.
  * **460px:** Modo ultra-amplio para monitores grandes o alta densidad informativa.
- **Estrategia Visual de Alto Nivel:**
  1. **Tarjetas Individuales de Empresas:** Cada empresa se presenta en una tarjeta estilizada (`border-radius: 10px; border: 1px solid #eaedf1; padding: 14px 14px;`) con sombra sutil y elevación flotante al hacer hover.
  2. **Identificación Inmediata:** Avatar con icono representativo según actividad económica, insignia suave de sector (Petróleo, Alimentos, Banca, Salud, etc.) y etiqueta de estado "Activo".
  3. **Tipografía Confortable:** Nombre de empresa visible sin truncamiento forzado (`font-size: 0.92rem; font-weight: 700; color: #1e293b;`), RIF en badge monoespaciado (`font-family: JetBrains Mono; background: #f1f5f9;`) y dirección estructurada en 2 líneas completas.
  4. **Georreferenciación y Acción Rápida:** Visualización de coordenadas numéricas (`10.6542, -71.6083`) y botón de acción visual `"Ver en mapa"` que activa el centrado suave en Google Maps.
  5. **Filtro Avanzado y Chips por Sector:** Barra de búsqueda instantánea con botón de limpieza ("✕") combinada con botones chip horizontales para filtrar por sectores económicos con un solo clic.

### 2.7.2 Banner Superior Institucional (SEDATEZ)
- **Altura fija:** 70 píxeles (`height: 70px;`).
- **Fondo:** Blanco `#ffffff` con borde inferior `1px solid #dee2e6` y sombra sutil.
- **Composición Limpia y Oficial (Por solicitud de diseño):**
  - **Lado izquierdo:** Logotipo oficial de **SEDATEZ** suministrado por la institución (`logo_sedatez.png`), optimizado y recortado a sus bordes reales (`height: 56px`), exhibiendo nítidamente sus hojas geométricas superiores en azul, amarillo y rojo sobre el rótulo corporativo.
  - **Zona central:** Despejada y limpia (se retiró el texto secundario por indicación expresa para otorgar máxima sobriedad y protagonismo visual a la identidad corporativa de SEDATEZ).
  - **Lado derecho:** Perfil del fiscal activo con avatar circular en azul oscuro (`#0B2545`), nombre del usuario ("Lic. Juan Camarillo"), cargo ("Fiscal Tributario") y botón de cierre de sesión seguro ("Salir").

### 2.7.3 Zona Central de Google Maps
- **Ocupación:** Ocupa el 100% de la altura restante (`height: calc(100vh - 62px)`) y el 90% restante del ancho.
- **Configuración de Google Maps:**
  - Centro inicial predeterminado: Coordenadas de Maracaibo / San Francisco, Estado Zulia (`lat: 10.6427, lng: -71.6125`).
  - Nivel de zoom inicial: `zoom: 12`.
  - Estilo de mapa: `roadmap` personalizado con tonos suaves corporativos.
  - Al hacer clic en un elemento de la barra lateral:
    - Ejecuta `map.panTo(new google.maps.LatLng(lat, lng))`.
    - Ejecuta animación de zoom suave a nivel `16`.
    - Activa marcador con animación `BOUNCE` temporal.
    - Despliega `google.maps.InfoWindow` con diseño enriquecido mediante clases Bootstrap.

---

## 2.8 Seguridad y Control de Acceso

1. **Protección de Rutas:** Middleware `auth` de Laravel para impedir el acceso a `/dashboard` y `/api/v1/*` a usuarios no autenticados.
2. **Protección de API Key:** La clave `GOOGLE_MAPS_API_KEY` se resguarda en el archivo `.env` del servidor y se inyecta de forma controlada o mediante proxy seguro. Además, se aplican restricciones de HTTP Referrer en Google Cloud Console vinculadas al dominio autorizado.
3. **Validación de Datos:** Uso de `FormRequest` para validar tipos de datos, formato de RIF y rangos geográficos.
4. **Protección CSRF:** Inclusión obligatoria de `@csrf` en todos los formularios Blade.

---

# PARTE II: PLAN DETALLADO DE IMPLEMENTACIÓN PASO A PASO (PLAN)

```mermaid
gantt
    title Plan de Ejecución del Proyecto SEDATEZ
    dateFormat  YYYY-MM-DD
    section Fase 1: Entorno & BD
    Configurar PostgreSQL y extensiones      :f1_1, 2026-10-07, 1d
    Crear base de datos y usuario            :f1_2, after f1_1, 1d
    section Fase 2: Laravel Core
    Inicializar Laravel 11 y .env            :f2_1, after f1_2, 1d
    Configurar Auth y Bootstrap 5            :f2_2, after f2_1, 1d
    section Fase 3: Modelo de Datos
    Crear migración empresas                 :f3_1, after f2_2, 1d
    Modelo Eloquent, Factory y Seeders       :f3_2, after f3_1, 1d
    section Fase 4: Backend & API
    Controladores y API REST                 :f4_1, after f3_2, 1d
    section Fase 5: Vistas Blade
    Vistas Login y Dashboard con Bootstrap   :f5_1, after f4_1, 1d
    Banner Institucional (Logos SEDATEZ+Laravel):f5_2, after f5_1, 1d
    section Fase 6: Google Maps JS
    Integración Google Maps JS API           :f6_1, after f5_2, 1d
    Eventos Sidebar <-> Centrado de Mapa     :f6_2, after f6_1, 1d
    section Fase 7: Pruebas & Servidor
    Configuración Apache y Permisos          :f7_1, after f6_2, 1d
    Validación Integral y Pruebas            :f7_2, after f7_1, 1d
```

---

## 3.1 Fase 1: Preparación del Entorno y Base de Datos PostgreSQL

### Paso 1.1: Creación de la Base de Datos y Usuario
Conectarse al motor PostgreSQL y ejecutar los comandos de aprovisionamiento:
```bash
sudo -u postgres psql
```
```sql
CREATE DATABASE mapa_sedatez WITH OWNER postgres ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE mapa_sedatez TO postgres;
\q
```

### Paso 1.2: Verificación de Conectividad
Comprobar que la base de datos responde en el puerto 5432:
```bash
psql -h localhost -U postgres -d mapa_sedatez -c '\conninfo'
```

---

## 3.2 Fase 2: Inicialización del Proyecto Laravel y Configuración

### Paso 2.1: Creación de la Estructura del Proyecto Laravel
Ejecutar la creación del proyecto utilizando Composer y PHP 8.3:
```bash
php8.3 $(which composer) create-project laravel/laravel /var/www/html/mapa_sedatez_app
```

### Paso 2.2: Configuración del archivo `.env`
Editar `/var/www/html/mapa_sedatez/.env` con la configuración de PostgreSQL y Google Maps:
```env
APP_NAME="SEDATEZ - Mapa de Empresas"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost/mapa_sedatez

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mapa_sedatez
DB_USERNAME=postgres
DB_PASSWORD=tu_password_aqui

GOOGLE_MAPS_API_KEY=AIzaSyA6RBBze6bCJDvTY8gDHoYxGeG0-OgQY2Y
```

### Paso 2.3: Instalación de Paquete de Autenticación con Bootstrap
Instalar Laravel UI o Breeze configurado para Bootstrap:
```bash
php8.3 $(which composer) require laravel/ui
php8.3 artisan ui bootstrap --auth
```

---

## 3.3 Fase 3: Capa de Persistencia (Migraciones, Modelos, Seeders)

### Paso 3.1: Migración para la tabla `empresas`
Crear el archivo de migración `database/migrations/xxxx_xx_xx_create_empresas_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('rif', 20)->unique();
            $table->text('direccion');
            $table->decimal('latitud', 10, 7);
            $table->decimal('longitud', 10, 7);
            $table->string('telefono', 50)->nullable();
            $table->string('categoria', 100)->default('Comercial');
            $table->boolean('activo')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['latitud', 'longitud']);
            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
```

### Paso 3.2: Modelo Eloquent `Empresa.php`
Crear `app/Models/Empresa.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'rif',
        'direccion',
        'latitud',
        'longitud',
        'telefono',
        'categoria',
        'activo',
        'user_id'
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'activo' => 'boolean',
    ];
}
```

### Paso 3.3: Seeder con Contribuyentes Reales del Estado Zulia
Crear `database/seeders/EmpresaSeeder.php` con datos representativos:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Empresa;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $empresas = [
            [
                'nombre' => 'Empresas Polar - Cervecería Modelo',
                'rif' => 'J-00006372-9',
                'direccion' => 'Km 10 Vía Perijá, Municipio San Francisco, Zulia',
                'latitud' => 10.5987120,
                'longitud' => -71.6854130,
                'categoria' => 'Industrial'
            ],
            [
                'nombre' => 'Cervecería Regional C.A.',
                'rif' => 'J-07001402-5',
                'direccion' => 'Av. 17 Los Haticos, Parroquia Cristo de Aranza, Maracaibo',
                'latitud' => 10.6128450,
                'longitud' => -71.6219400,
                'categoria' => 'Industrial'
            ],
            [
                'nombre' => 'Tiendas Daka Maracaibo',
                'rif' => 'J-30495861-2',
                'direccion' => 'Av. 5 de Julio con Calle 72, Sector Tierra Negra, Maracaibo',
                'latitud' => 10.6698300,
                'longitud' => -71.6142100,
                'categoria' => 'Electrodomésticos / Comercio'
            ],
            [
                'nombre' => 'Farmatodo Bella Vista',
                'rif' => 'J-00020200-1',
                'direccion' => 'Av. 4 Bella Vista con Calle 67 Cecilio Acosta, Maracaibo',
                'latitud' => 10.6612100,
                'longitud' => -71.6094500,
                'categoria' => 'Farmacéutico / Retail'
            ],
            [
                'nombre' => 'Centro Comercial Sambil Maracaibo',
                'rif' => 'J-31056789-0',
                'direccion' => 'Av. Guajira, Zona Norte, Parroquia Juana de Ávila, Maracaibo',
                'latitud' => 10.6975200,
                'longitud' => -71.6364100,
                'categoria' => 'Centro Comercial'
            ],
            [
                'nombre' => 'Lácteos Los Andes - Planta Zulia',
                'rif' => 'G-20008541-3',
                'direccion' => 'Zona Industrial II Etapa, San Francisco, Zulia',
                'latitud' => 10.5843200,
                'longitud' => -71.6721000,
                'categoria' => 'Alimentos'
            ]
        ];

        foreach ($empresas as $item) {
            Empresa::updateOrCreate(['rif' => $item['rif']], $item);
        }
    }
}
```

---

## 3.4 Fase 4: Lógica de Backend y Controladores

### Paso 4.1: `DashboardController.php`
```php
<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $empresas = Empresa::where('activo', true)
            ->orderBy('nombre', 'asc')
            ->get();

        return view('dashboard', compact('empresas'));
    }
}
```

### Paso 4.2: `EmpresaApiController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;

class EmpresaApiController extends Controller
{
    public function index(): JsonResponse
    {
        $empresas = Empresa::where('activo', true)
            ->select(['id', 'nombre', 'rif', 'direccion', 'latitud', 'longitud', 'categoria'])
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $empresas->count(),
            'data' => $empresas
        ]);
    }
}
```

---

## 3.5 Fase 5: Vistas Blade con Bootstrap 5

### Paso 5.1: Layout Principal (`resources/views/layouts/app.blade.php`)
Configurado con Bootstrap 5.3 CDN o compilado por Vite, incorporando fuentes modernas (Inter/Roboto) y estilos maestros.

### Paso 5.2: Vista de Login (`resources/views/auth/login.blade.php`)
- Fondo corporativo con degradado elegante institucional (`#0B2545` a `#134074`).
- Tarjeta de autenticación centralizada con logotipo institucional SEDATEZ y logotipo Laravel.
- Campos de Correo y Contraseña con iconos y validación visual.
- Mensajes de error claros e indicador de carga al someter credenciales.

### Paso 5.3: Vista Dashboard (`resources/views/dashboard.blade.php`)
- **Banner Superior Fijo:**
  - Logo oficial SEDATEZ en formato vectorial SVG.
  - Separador y Logo oficial Laravel en SVG.
  - Título: "SEDATEZ • Georreferenciación de Empresas".
  - Menú de usuario con botón de cierre de sesión.
- **Barra Lateral Izquierda:**
  - Definida estrictamente con `width: 10vw; max-width: 10%; min-width: 90px;`.
  - Caja de búsqueda compacta para filtrado instantáneo.
  - Lista de botones interactivos con `data-lat`, `data-lng`, `data-id`.
  - Atributos `data-bs-toggle="tooltip"` y `data-bs-placement="right"` para mostrar el RIF y nombre completo en un globo emergente.
- **Contenedor del Mapa:**
  - `<div id="map" class="flex-grow-1" style="height: calc(100vh - 62px); width: 90vw;"></div>`.

---

## 3.6 Fase 6: Integración del Mapa de Google Maps y Lógica JS

### Paso 6.1: Script `public/js/sedatez-map.js`
```javascript
let map;
let markers = {};
let activeInfoWindow = null;

function initMap() {
    // Coordenadas centrales de Maracaibo / Zulia
    const defaultCenter = { lat: 10.6427, lng: -71.6125 };

    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 12,
        center: defaultCenter,
        mapTypeControl: true,
        streetViewControl: false,
        fullscreenControl: true,
        styles: [
            { featureType: "poi", elementType: "labels", stylers: [{ visibility: "off" }] }
        ]
    });

    // Cargar empresas desde la API de Laravel
    fetch('/api/v1/empresas')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                renderMarkers(res.data);
            }
        })
        .catch(err => console.error("Error al cargar empresas:", err));
}

function renderMarkers(empresas) {
    const bounds = new google.maps.LatLngBounds();

    empresas.forEach(empresa => {
        const pos = { lat: parseFloat(empresa.latitud), lng: parseFloat(empresa.longitud) };
        
        const marker = new google.maps.Marker({
            position: pos,
            map: map,
            title: empresa.nombre,
            animation: google.maps.Animation.DROP,
            icon: {
                path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW,
                fillColor: "#0B2545",
                fillOpacity: 0.9,
                strokeWeight: 2,
                strokeColor: "#ECA72C",
                scale: 6
            }
        });

        // Contenido HTML para el InfoWindow
        const contentString = `
            <div class="p-2" style="max-width: 280px; font-family: sans-serif;">
                <h6 class="fw-bold text-primary mb-1">${empresa.nombre}</h6>
                <div class="badge bg-dark mb-2">RIF: ${empresa.rif}</div>
                <p class="small text-muted mb-2"><i class="bi bi-geo-alt"></i> ${empresa.direccion}</p>
                <div class="small text-secondary mb-2">
                    <strong>Coordenadas:</strong> ${empresa.latitud}, ${empresa.longitud}
                </div>
                <a href="https://www.google.com/maps/search/?api=1&query=${empresa.latitud},${empresa.longitud}" 
                   target="_blank" class="btn btn-sm btn-outline-primary w-100">
                    Abrir en Google Maps
                </a>
            </div>
        `;

        const infoWindow = new google.maps.InfoWindow({ content: contentString });

        marker.addListener("click", () => {
            selectCompany(empresa.id, pos, marker, infoWindow);
        });

        markers[empresa.id] = { marker, infoWindow, pos, data: empresa };
        bounds.extend(pos);
    });
}

function focusCompanyOnMap(empresaId) {
    const item = markers[empresaId];
    if (!item) return;

    if (activeInfoWindow) activeInfoWindow.close();

    map.panTo(item.pos);
    map.setZoom(16);

    item.infoWindow.open(map, item.marker);
    activeInfoWindow = item.infoWindow;

    // Resaltar en sidebar
    document.querySelectorAll('.company-nav-item').forEach(el => el.classList.remove('active'));
    const btn = document.getElementById(`company-btn-${empresaId}`);
    if (btn) btn.classList.add('active');
}
```

---

## 3.7 Fase 7: Configuración de Servidor Web (Apache) y Validación

### Paso 7.1: VirtualHost en Apache (`/etc/apache2/sites-available/mapa_sedatez.conf`)
```apache
<VirtualHost *:80>
    ServerName mapa-sedatez.local
    DocumentRoot /var/www/html/mapa_sedatez/public

    <Directory /var/www/html/mapa_sedatez/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/mapa_sedatez_error.log
    CustomLog ${APACHE_LOG_DIR}/mapa_sedatez_access.log combined
</VirtualHost>
```

### Paso 7.2: Permisos de Directorios
```bash
sudo chown -R www-data:www-data /var/www/html/mapa_sedatez/storage /var/www/html/mapa_sedatez/bootstrap/cache
sudo chmod -R 775 /var/www/html/mapa_sedatez/storage /var/www/html/mapa_sedatez/bootstrap/cache
```

---

## 4. Matriz de Trazabilidad y Criterios de Aceptación

| Requisito | Criterio de Aceptación | Método de Verificación |
| :--- | :--- | :--- |
| **Login (RF-01)** | Usuario no autenticado es redirigido a `/login`. Al ingresar credenciales válidas pasa al dashboard. | Prueba funcional con formulario de login. |
| **Sidebar $\le 10\%$ (RF-04)** | La barra lateral mide $\le 10\%$ del ancho de pantalla medido en viewport. Muestra lista de nombres de empresas. | Inspección de CSS (`10vw`), medición en píxeles y validación responsiva. |
| **Centrado en Mapa (RF-06)** | Al presionar un nombre en la barra lateral, el mapa se centra instantáneamente en la latitud/longitud de la empresa y se abre la ventana emergente. | Clic en elementos y observación de traslación del mapa a las coordenadas de la empresa. |
| **Banner Superior (RF-03)** | Visible en todo momento en la parte superior con los logotipos de SEDATEZ y Laravel en alta definición. | Inspección visual en pantalla y validación de assets vectoriales SVG. |
| **Modelo de Datos (RF-07)** | Tabla `empresas` contiene: Nombre, Rif, Direccion, latitud, longitud en PostgreSQL. | Consulta SQL `\d empresas` en PostgreSQL. |
