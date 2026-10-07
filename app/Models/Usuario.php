<?php

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
        'id_tab_tipo_usuario',
    ];

    protected $hidden = [
        'da_password',
        'remember_token',
        'da_pass_recuperar',
    ];

    protected $casts = [
        'in_estatus' => 'boolean',
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
     * Relación con el tipo de usuario institucional
     */
    public function tipoUsuario()
    {
        return $this->belongsTo(TipoUsuario::class, 'id_tab_tipo_usuario', 'id');
    }

    /**
     * Relación con los roles del usuario
     */
    public function roles()
    {
        return $this->belongsToMany(
            Rol::class,
            'autenticacion.tab_usuario_rol',
            'id_tab_usuarios',
            'id_tab_rol'
        );
    }

    /**
     * Nombre formal y formateado para mostrar en la interfaz
     */
    public function getNombreCompletoAttribute()
    {
        if ($this->funcionario && !empty($this->funcionario->nb_funcionario)) {
            return trim($this->funcionario->nb_funcionario . ' ' . $this->funcionario->ap_funcionario);
        }
        return $this->da_usuario;
    }

    /**
     * Iniciales para el avatar en el banner superior
     */
    public function getInicialesAttribute()
    {
        if ($this->funcionario && !empty($this->funcionario->nb_funcionario)) {
            $f = $this->funcionario;
            return strtoupper(substr($f->nb_funcionario, 0, 1) . substr($f->ap_funcionario, 0, 1));
        }
        return strtoupper(substr($this->da_usuario, 0, 2));
    }
}
