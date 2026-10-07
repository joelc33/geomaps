<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContribuyenteMnm extends Model
{
    protected $table = 'contribuyente.tab_mnm';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_tab_contribuyente',
        'id_tab_documento',
        'nu_documento_rif',
        'de_razon_social',
        'dr_fiscal',
        'dr_terreno',
        'tl_fijo',
        'da_correo',
        'nb_responsable1',
        'ap_responsable1',
        'tl_movil_resp',
        'da_correo_resp',
        'nb_propietario',
        'doc_propietario',
        'id_tab_municipio',
        'id_tab_parroquia',
        'da_superficie',
        'da_mineral',
        'nu_latitud',
        'nu_longitud',
        'in_activo',
        'in_llenado_guia',
        'id_tab_ambito_territorial',
    ];

    protected $casts = [
        'in_activo' => 'boolean',
        'in_llenado_guia' => 'boolean',
    ];

    /**
     * Relación con el registro de contribuyente general (RITEZ, Expediente)
     */
    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class, 'id_tab_contribuyente', 'id');
    }

    /**
     * Relación con el Municipio
     */
    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'id_tab_municipio', 'id');
    }

    /**
     * Relación con la Parroquia
     */
    public function parroquia()
    {
        return $this->belongsTo(Parroquia::class, 'id_tab_parroquia', 'id');
    }

    /**
     * Obtener el array de minerales declarados
     */
    public function getArrayMineralesAttribute()
    {
        if (empty($this->da_mineral)) return [];
        return array_map('trim', explode(',', $this->da_mineral));
    }
}
