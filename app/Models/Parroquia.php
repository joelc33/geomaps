<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parroquia extends Model
{
    protected $table = 'mantenimiento.tab_parroquia';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'de_parroquia',
        'id_tab_municipio',
    ];

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'id_tab_municipio', 'id');
    }

    public function empresasMnm()
    {
        return $this->hasMany(ContribuyenteMnm::class, 'id_tab_parroquia', 'id');
    }
}
