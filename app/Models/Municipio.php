<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    protected $table = 'mantenimiento.tab_municipio';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'de_municipio',
        'id_tab_estado',
    ];

    public function parroquias()
    {
        return $this->hasMany(Parroquia::class, 'id_tab_municipio', 'id');
    }

    public function empresasMnm()
    {
        return $this->hasMany(ContribuyenteMnm::class, 'id_tab_municipio', 'id');
    }
}
