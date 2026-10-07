<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funcionario extends Model
{
    protected $table = 'mantenimiento.tab_funcionario';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_tab_usuarios',
        'id_tab_documento',
        'nu_cedula',
        'nb_funcionario',
        'ap_funcionario',
        'id_tab_cargo',
        'tx_direccion',
        'tx_telefono',
        'tx_email',
        'in_activo',
        'id_tab_departamento',
    ];

    protected $casts = [
        'in_activo' => 'boolean',
        'nu_cedula' => 'integer',
    ];

    /**
     * Relación con el usuario de autenticación
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_tab_usuarios', 'id');
    }

    /**
     * Relación con el cargo
     */
    public function cargo()
    {
        return $this->belongsTo(Cargo::class, 'id_tab_cargo', 'id');
    }
}
