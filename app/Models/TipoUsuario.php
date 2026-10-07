<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoUsuario extends Model
{
    protected $table = 'mantenimiento.tab_tipo_usuario';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'de_tipo_usuario',
        'in_activo',
    ];

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'id_tab_tipo_usuario', 'id');
    }
}
