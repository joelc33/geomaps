# Plan y Especificación Técnica: Implementación de Login Real con PostgreSQL (`etrib`)

---

## 1. Resumen Ejecutivo y Diagnóstico de la Base de Datos

Se realizó una inspección técnica directa al motor **PostgreSQL 16** alojado en `localhost:5432`, conectando a la base de datos institucional **`etrib`** con las credenciales validadas (`postgres` / `1234`).

### 1.1 Diagnóstico de la Tabla `autenticacion.tab_usuarios`

| Campo | Tipo de Dato | Nulo | Descripción y Función en el Login |
| :--- | :--- | :---: | :--- |
| `id` | `bigint` (PK) | No | Identificador único del usuario (secuencia `tab_usuarios_id_seq`). |
| `da_usuario` | `varchar(100)` | Sí | Nombre de usuario único (ej. `Miguelr`, `ksosa` o correo en contribuyentes). |
| `da_email` | `varchar(100)` | Sí | Dirección de correo electrónico oficial. |
| `da_password` | `varchar(60)` | Sí | Contraseña hasheada con **Bcrypt** estándar (prefijo `$2y$10$...`, costo 10). |
| `remember_token` | `varchar(100)` | Sí | Token para persistencia de sesión ("Recordarme"). |
| `in_estatus` | `boolean` | Sí | Estado de la cuenta: `true` (activo / habilitado), `false` (bloqueado / inactivo). |
| `id_tab_tipo_usuario`| `bigint` (FK) | Sí | Relación con `mantenimiento.tab_tipo_usuario` (ej. Administrativo, Contribuyente). |
| `created_at` / `updated_at` | `timestamp` | Sí | Auditoría de marcas de tiempo. |

### 1.2 Tablas Relacionadas para Datos de Sesión
1. **`mantenimiento.tab_funcionario`** (Relacionada por `id_tab_usuarios = tab_usuarios.id`):
   * Contiene los datos personales del funcionario público: `nb_funcionario` (Nombre), `ap_funcionario` (Apellido), `nu_cedula` (Cédula de Identidad), `id_tab_cargo` (Cargo institucional).
   * **Beneficio directo:** Al iniciar sesión, el banner superior mostrará dinámicamente el nombre y apellido real del funcionario (ej. *"Miguel Rivas Burgos"* o *"Katina Ayarit Sosa"*), sustituyendo cualquier dato estático.
2. **`mantenimiento.tab_tipo_usuario`**: Define el rol institucional (ej. *"Administrativo"*).
3. **`autenticacion.tab_usuario_login`**: Tabla histórica para auditoría de inicios de sesión (`id_tab_usuarios`, `created_at`).

### 1.3 Validación Criptográfica del Hash
El análisis de los hashes en `autenticacion.tab_usuarios` confirmó que el algoritmo utilizado es **Bcrypt nativo de PHP / Laravel** (`$2y$10$`, `PASSWORD_BCRYPT`). Por lo tanto, la verificación se realiza de manera 100% nativa con `password_verify($password, $hash)` o `Hash::check($password, $hash)` sin requerir conversiones adicionales ni algoritmos heredados.

---

## 2. Especificación Técnica (Spec)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       ARQUITECTURA DE AUTENTICACIÓN                         │
└─────────────────────────────────────────────────────────────────────────────┘

    [ Frontend: Login Form ]
         │
         │ POST /login { identificador, password }
         ▼
    [ Rate Limiting Middleware (throttle:5,1) ]
         │
         ▼
    [ LoginController ]
         │
         ├─── 1. Buscar usuario por da_usuario O da_email
         │       SELECT * FROM autenticacion.tab_usuarios 
         │       WHERE (da_usuario = :id OR da_email = :id) LIMIT 1;
         │
         ├─── 2. Validar estatus: in_estatus === true
         │       (Si false ──► Retornar 403: "Cuenta inactiva")
         │
         ├─── 3. Verificar contraseña Bcrypt: Hash::check(password, da_password)
         │       (Si falla ──► Retornar 401: "Credenciales inválidas")
         │
         ├─── 4. Cargar datos del Funcionario asociado
         │       LEFT JOIN mantenimiento.tab_funcionario ON ...
         │
         ├─── 5. Iniciar sesión y regenerar Session ID
         │       Auth::login($user) + session()->regenerate()
         │
         ├─── 6. Registrar en auditoria.tab_usuario_login
         │
         ▼
    [ Respuesta JSON / Redirección ] ──► Cargar Dashboard con datos reales
```

---

### 2.1 Backend Spec

#### 2.1.1 Configuración de Base de Datos (`config/database.php` y `.env`)
```php
// .env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=etrib
DB_USERNAME=postgres
DB_PASSWORD=1234
```

```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'etrib'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', '1234'),
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
    'sslmode' => 'prefer',
],
```

#### 2.1.2 Modelo Eloquent `Usuario` (`app/Models/Usuario.php`)
El modelo debe mapear los nombres específicos de campos de la tabla `autenticacion.tab_usuarios`:

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use Notifiable;

    protected $table = 'autenticacion.tab_usuarios';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'da_usuario',
        'da_email',
        'da_password',
        'in_estatus',
        'id_tab_tipo_usuario'
    ];

    protected $hidden = [
        'da_password',
        'remember_token',
        'da_pass_recuperar'
    ];

    /**
     * Informa a Laravel que el campo de contraseña es da_password
     */
    public function getAuthPassword()
    {
        return $this->da_password;
    }

    /**
     * Relación con los datos del funcionario público
     */
    public function funcionario()
    {
        return $this->hasOne(Funcionario::class, 'id_tab_usuarios', 'id');
    }

    /**
     * Relación con el tipo de usuario
     */
    public function tipoUsuario()
    {
        return $this->belongsTo(TipoUsuario::class, 'id_tab_tipo_usuario', 'id');
    }

    /**
     * Obtener el nombre formal para mostrar en la interfaz
     */
    public function getNombreCompletoAttribute()
    {
        if ($this->funcionario && !empty($this->funcionario->nb_funcionario)) {
            return trim($this->funcionario->nb_funcionario . ' ' . $this->funcionario->ap_funcionario);
        }
        return $this->da_usuario;
    }
}
```

#### 2.1.3 Modelo Eloquent `Funcionario` (`app/Models/Funcionario.php`)
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funcionario extends Model
{
    protected $table = 'mantenimiento.tab_funcionario';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_tab_usuarios',
        'nu_cedula',
        'nb_funcionario',
        'ap_funcionario',
        'id_tab_cargo',
        'tx_email',
        'in_activo'
    ];

    public function cargo()
    {
        return $this->belongsTo(Cargo::class, 'id_tab_cargo', 'id');
    }
}
```

#### 2.1.4 Configuración de Proveedor de Autenticación (`config/auth.php`)
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'usuarios',
    ],
],

'providers' => [
    'usuarios' => [
        'driver' => 'eloquent',
        'model' => App\Models\Usuario::class,
    ],
],
```

#### 2.1.5 Controlador de Autenticación (`app/Http/Controllers/Auth/LoginController.php`)
Permite login flexible por **Nombre de Usuario (`da_usuario`)** o **Correo Electrónico (`da_email`)**:

```php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'identificador' => 'required|string|max:100',
            'password' => 'required|string',
        ], [
            'identificador.required' => 'Debe ingresar su usuario o correo electrónico.',
            'password.required' => 'Debe ingresar su contraseña.',
        ]);

        $identificador = trim($request->input('identificador'));
        $password = $request->input('password');

        // 1. Localizar usuario por da_usuario o da_email
        $usuario = Usuario::with('funcionario', 'tipoUsuario')
            ->where('da_usuario', $identificador)
            ->orWhere('da_email', $identificador)
            ->first();

        // 2. Si no existe o contraseña incorrecta
        if (!$usuario || !Hash::check($password, $usuario->da_password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales ingresadas no coinciden con nuestros registros.'
            ], 401);
        }

        // 3. Validar estatus activo
        if (!$usuario->in_estatus) {
            return response()->json([
                'success' => false,
                'message' => 'Su cuenta se encuentra inactiva. Comuníquese con la Administración de SEDATEZ.'
            ], 403);
        }

        // 4. Iniciar sesión y proteger contra fijación de sesión
        Auth::login($usuario);
        $request->session()->regenerate();

        // 5. Auditoría opcional en tab_usuario_login
        try {
            DB::table('autenticacion.tab_usuario_login')->insert([
                'id_tab_usuarios' => $usuario->id,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // No bloquear el login si la tabla de auditoría tiene trigger independiente
        }

        // 6. Retornar datos de sesión formateados
        return response()->json([
            'success' => true,
            'message' => 'Autenticación exitosa.',
            'user' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre_completo,
                'usuario' => $usuario->da_usuario,
                'email' => $usuario->da_email,
                'tipo' => optional($usuario->tipoUsuario)->de_tipo_usuario ?? 'Funcionario',
            ],
            'redirect' => route('dashboard')
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => route('login')]);
        }

        return redirect()->route('login');
    }
}
```

---

### 2.2 Frontend Spec

#### 2.2.1 Contrato del Formulario de Login
* **Campo de Identificador:**
  * `id="login-email"` (o `id="login-identificador"`)
  * `type="text"`
  * `placeholder="Usuario o correo institucional"`
  * `label="Usuario o Email *"`
* **Campo de Contraseña:**
  * `id="login-password"`
  * `type="password"`
  * Botón alternador de visibilidad (icono de ojo).
* **Botón de Envío:**
  * `id="btn-submit-login"`
  * Estados visuales:
    * Normal: *"Log in now"*
    * Procesando: Spinner de carga + *"Verificando credenciales..."* + atributo `disabled`.
* **Caja de Alertas (`#login-alert`):**
  * Oculta por defecto (`d-none`).
  * Muestra mensajes de error devueltos por el backend con iconos de advertencia.

#### 2.2.2 Flujo de Envío JavaScript (Fetch API)
```javascript
loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const identificador = document.getElementById('login-identificador').value.trim();
    const password = document.getElementById('login-password').value;
    const alertBox = document.getElementById('login-alert');
    const btnSubmit = document.getElementById('btn-submit-login');
    const btnText = document.getElementById('btn-login-text');
    const btnSpinner = document.getElementById('btn-login-spinner');

    // Estado cargando
    btnSubmit.disabled = true;
    btnSpinner.classList.remove('d-none');
    btnText.textContent = "Verificando credenciales...";
    alertBox.classList.add('d-none');

    try {
        const response = await fetch('/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({ identificador, password })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            // Actualizar interfaz con el funcionario real autenticado
            actualizarSesionUsuario(data.user);
            mostrarDashboard();
        } else {
            alertBox.textContent = data.message || "Credenciales inválidas.";
            alertBox.classList.remove('d-none');
        }
    } catch (err) {
        alertBox.textContent = "Error de conexión con el servidor de autenticación.";
        alertBox.classList.remove('d-none');
    } finally {
        btnSubmit.disabled = false;
        btnSpinner.classList.add('d-none');
        btnText.textContent = "Log in now";
    }
});
```

#### 2.2.3 Actualización Dinámica del Banner Superior Post-Login
Cuando el login es exitoso, la información del funcionario en el banner superior se actualiza de inmediato con los datos reales:
```javascript
function actualizarSesionUsuario(user) {
    // Iniciales en el círculo avatar (ej. "MR" para Miguel Rivas)
    const initials = user.nombre.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
    document.getElementById('user-avatar-initials').textContent = initials;
    
    // Nombre completo y tipo de usuario real
    document.getElementById('user-fullname').textContent = user.nombre;
    document.getElementById('user-role-label').textContent = user.tipo;
}
```

---

## 3. Plan de Implementación Paso a Paso

### Fase 1: Configuración de Base de Datos y Dependencias
* **Paso 1.1:** Configurar los parámetros de conexión en `.env` apuntando a `etrib` (`DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=etrib`, `DB_USERNAME=postgres`, `DB_PASSWORD=1234`).
* **Paso 1.2:** Validar la disponibilidad de la extensión PDO PostgreSQL (`pdo_pgsql`) en el entorno de ejecución activo.

### Fase 2: Modelos de Datos y Mapeo de Esquemas
* **Paso 2.1:** Crear el modelo `App\Models\Usuario` con la tabla `autenticacion.tab_usuarios` y sobrescribir el método `getAuthPassword()` para apuntar al campo `da_password`.
* **Paso 2.2:** Crear el modelo `App\Models\Funcionario` apuntando a `mantenimiento.tab_funcionario` para relacionar nombres, apellidos y cargos.
* **Paso 2.3:** Configurar el proveedor de autenticación en `config/auth.php` asignando el modelo `App\Models\Usuario`.

### Fase 3: Controlador de Autenticación y Rutas Seguras
* **Paso 3.1:** Crear `App\Http\Controllers\Auth\LoginController` con métodos `login()` y `logout()`.
* **Paso 3.2:** Implementar validación de doble identificador (`da_usuario` o `da_email`) y chequeo de bandera `in_estatus == true`.
* **Paso 3.3:** Configurar middleware de limitación de tasa (`throttle:5,1`) en las rutas de login para mitigar ataques de fuerza bruta.
* **Paso 3.4:** Definir rutas en `routes/web.php` y `routes/api.php`:
  * `POST /login` -> `LoginController@login`
  * `POST /logout` -> `LoginController@logout`

### Fase 4: Integración Frontend y Conexión de Vistas
* **Paso 4.1:** Adecuar el formulario de Login en [index.html](file:///var/www/html/mapa_sedatez/index.html) para admitir tanto usuario como correo en el campo de entrada.
* **Paso 4.2:** Conectar el manejador de eventos `submit` para enviar la petición al endpoint `/api/login` (o backend Laravel).
* **Paso 4.3:** Vincular los elementos del banner superior (`user-avatar-initials`, `user-fullname`, `user-role-label`) para renderizar el funcionario real autenticado.
* **Paso 4.4:** Conectar el botón `Salir` con la invalidación de sesión en el backend.

### Fase 5: Pruebas y Aseguramiento de Calidad
* **Paso 5.1:** Prueba de inicio de sesión exitoso con un usuario activo existente (ej. `Miguelr`, `ksosa`).
* **Paso 5.2:** Prueba de rechazo de credenciales con contraseña incorrecta (código HTTP 401).
* **Paso 5.3:** Prueba de bloqueo a cuentas con `in_estatus = false` (código HTTP 403).
* **Paso 5.4:** Verificación del registro de auditoría en `autenticacion.tab_usuario_login`.
