<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contribuyente extends Model
{
    protected $table = 'contribuyente.tab_contribuyente';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nu_documento_rif',
        'co_ritez',
        'nu_expediente',
        'fe_registro',
        'nu_codigo',
        'in_activo',
        'in_habilitado_guia',
        'direccion_fiscal',
        'id_municipio',
        'id_parroquia',
    ];

    protected $casts = [
        'in_activo' => 'boolean',
        'fe_registro' => 'date',
    ];

    public function mnm()
    {
        return $this->hasMany(ContribuyenteMnm::class, 'id_tab_contribuyente', 'id');
    }
}
