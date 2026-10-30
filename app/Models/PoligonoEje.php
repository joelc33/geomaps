<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoligonoEje extends Model
{
    protected $table = 'mantenimiento.tab_poligonos_ejes';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'codigo_eje',
        'nombre_eje',
        'id_tab_municipio',
        'nombre_municipio',
        'color_hex',
        'color_borde',
        'opacidad',
        'leyenda_municipios',
        'leyenda_minerales',
        'minerales_json',
        'geometria_geojson',
        'in_activo',
        'orden'
    ];

    protected $casts = [
        'minerales_json' => 'array',
        'geometria_geojson' => 'array',
        'opacidad' => 'float',
        'in_activo' => 'integer',
        'orden' => 'integer'
    ];

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'id_tab_municipio', 'id');
    }
}
