<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    /**
     * Procesar solicitud de autenticación
     */
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

        // 1. Localizar usuario por da_usuario O por da_email (insensible a mayúsculas)
        $usuario = Usuario::with(['funcionario.cargo', 'tipoUsuario'])
            ->whereRaw('LOWER(da_usuario) = LOWER(?)', [$identificador])
            ->orWhereRaw('LOWER(da_email) = LOWER(?)', [$identificador])
            ->first();

        // 2. Si no existe o la contraseña no coincide
        if (!$usuario || !Hash::check($password, $usuario->da_password)) {
            return response()->json([
                'success' => false,
                'message' => 'Las credenciales ingresadas no coinciden con nuestros registros.'
            ], 401);
        }

        // 3. Validar estatus de la cuenta
        if (!$usuario->in_estatus) {
            return response()->json([
                'success' => false,
                'message' => 'Su cuenta se encuentra inactiva. Comuníquese con la administración de SEDATEZ.'
            ], 403);
        }

        // 4. Iniciar sesión y regenerar session ID contra fijación de sesión
        Auth::login($usuario);
        $request->session()->regenerate();

        // 5. Auditoría en auditoria.tab_usuario_login
        try {
            DB::table('auditoria.tab_usuario_login')->insert([
                'id_tab_usuarios' => $usuario->id,
                'ip_cliente'      => $request->ip(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Exception $e) {
            // Continuar sin interrumpir el login
        }

        $cargo = 'Funcionario SEDATEZ';
        if ($usuario->funcionario && $usuario->funcionario->cargo) {
            $cargo = $usuario->funcionario->cargo->de_cargo;
        } elseif ($usuario->tipoUsuario) {
            $cargo = $usuario->tipoUsuario->de_tipo_usuario;
        }

        // 6. Retornar payload formateado para el frontend
        return response()->json([
            'success' => true,
            'message' => 'Autenticación exitosa',
            'user' => [
                'id'        => $usuario->id,
                'usuario'   => $usuario->da_usuario,
                'email'     => $usuario->da_email,
                'nombre'    => $usuario->nombre_completo,
                'iniciales' => $usuario->iniciales,
                'cargo'     => $cargo,
                'tipo'      => optional($usuario->tipoUsuario)->de_tipo_usuario ?? 'Funcionario',
                'cedula'    => optional($usuario->funcionario)->nu_cedula,
            ],
            'redirect' => '/dashboard'
        ]);
    }

    /**
     * Cerrar sesión del usuario
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada con éxito',
                'redirect' => '/login'
            ]);
        }

        return redirect('/login');
    }

    /**
     * Verificar sesión activa
     */
    public function checkSession(Request $request)
    {
        if (Auth::check()) {
            $usuario = Auth::user();
            return response()->json([
                'authenticated' => true,
                'user' => [
                    'id'        => $usuario->id,
                    'usuario'   => $usuario->da_usuario,
                    'email'     => $usuario->da_email,
                    'nombre'    => $usuario->nombre_completo,
                    'iniciales' => $usuario->iniciales,
                    'cargo'     => optional(optional($usuario->funcionario)->cargo)->de_cargo ?? 'Funcionario',
                    'tipo'      => optional($usuario->tipoUsuario)->de_tipo_usuario ?? 'Funcionario',
                ]
            ]);
        }

        return response()->json([
            'authenticated' => false
        ]);
    }
}
