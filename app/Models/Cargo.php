<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    protected $table = 'mantenimiento.tab_cargo';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'de_cargo',
        'in_activo',
    ];

    protected $casts = [
        'in_activo' => 'boolean',
    ];

    public function funcionarios()
    {
        return $this->hasMany(Funcionario::class, 'id_tab_cargo', 'id');
    }
}
