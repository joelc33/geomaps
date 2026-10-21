<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContribuyenteOficio extends Model
{
    /**
     * Tabla correspondiente al Registro de Oficio de Usuarios de Minerales No Metálicos
     */
    protected $table = 'preregistro.tab_minas';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_tab_contribuyente',
        'id_tab_documento',
        'nu_documento_rif',
        'de_razon_social',
        'dr_fiscal',
        'tl_fijo',
        'da_correo',
        'id_tab_nacionalidad',
        'nu_cedula',
        'nb_responsable1',
        'nb_responsable2',
        'ap_responsable1',
        'ap_responsable2',
        'tl_movil_resp',
        'tl_fijo_resp',
        'da_correo_resp',
        'nb_propietario',
        'doc_propietario',
        'id_tab_estado',
        'id_tab_municipio',
        'id_tab_parroquia',
        'dr_terreno',
        'da_superficie',
        'da_mineral',
        'nu_latitud',
        'nu_longitud',
        'in_activo',
    ];

    protected $casts = [
        'in_activo' => 'boolean',
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
