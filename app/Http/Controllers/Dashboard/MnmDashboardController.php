<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContribuyenteMnm;
use App\Models\ContribuyenteOficio;
use App\Models\Municipio;
use App\Models\PoligonoEje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MnmDashboardController extends Controller
{
    /**
     * Obtener listado de empresas MNM y Registro de Oficio con filtros
     */
    public function getEmpresas(Request $request)
    {
        $grupo = $request->input('grupo', 'all');

        $queryMnm = ContribuyenteMnm::with(['contribuyente', 'municipio', 'parroquia']);
        $queryOficio = ContribuyenteOficio::with(['contribuyente', 'municipio', 'parroquia']);

        // Aplicar filtros comunes
        foreach ([$queryMnm, $queryOficio] as $query) {
            // Filtro por municipio
            if ($request->filled('municipio') && $request->municipio !== 'all') {
                $query->where('id_tab_municipio', $request->municipio);
            }

            // Filtro por estatus
            if ($request->filled('estatus') && $request->estatus !== 'all') {
                $query->where('in_activo', $request->boolean('estatus'));
            }

            // Filtro por mineral
            if ($request->filled('mineral') && $request->mineral !== 'all') {
                $query->where('da_mineral', 'ILIKE', '%' . $request->mineral . '%');
            }

            // Buscador de texto
            if ($request->filled('q')) {
                $term = '%' . $request->q . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('de_razon_social', 'ILIKE', $term)
                      ->orWhere('nu_documento_rif', 'ILIKE', $term)
                      ->orWhereHas('contribuyente', function ($sub) use ($term) {
                          $sub->where('co_ritez', 'ILIKE', $term)
                              ->orWhere('nu_expediente', 'ILIKE', $term);
                      });
                });
            }
        }

        $empresas = collect();

        if ($grupo === 'all' || $grupo === 'ritez') {
            $mnmList = $queryMnm->orderBy('de_razon_social', 'asc')->get()->map(function ($item) {
                $item->grupo_codigo = 'ritez';
                $item->grupo_nombre = 'Minerales No Metálicos';
                $item->origen_tabla = 'contribuyente.tab_mnm';
                $item->uid = 'ritez_' . $item->id;
                return $item;
            });
            $empresas = $empresas->concat($mnmList);
        }

        if ($grupo === 'all' || $grupo === 'oficio') {
            $oficioList = $queryOficio->orderBy('de_razon_social', 'asc')->get()->map(function ($item) {
                $item->grupo_codigo = 'oficio';
                $item->grupo_nombre = 'Registro de Oficio de Usuarios de Minerales No Metálicos';
                $item->origen_tabla = 'preregistro.tab_minas';
                $item->uid = 'oficio_' . $item->id;
                // No debe aparecer información de Código RITEZ, Expediente SADL, Estatus Tributario ni Guías de Transporte
                $item->co_ritez = null;
                $item->nu_expediente = null;
                $item->in_activo = null;
                $item->in_habilitado_guia = null;
                $item->contribuyente = null;
                return $item;
            });
            $empresas = $empresas->concat($oficioList);
        }

        $sorted = $empresas->sortBy('de_razon_social')->values();

        return response()->json([
            'success' => true,
            'total' => $sorted->count(),
            'data' => $sorted
        ]);
    }

    /**
     * Obtener KPIs y catálogos agregados (RITEZ + Registro de Oficio)
     */
    public function getKpis()
    {
        $totalRitez = ContribuyenteMnm::count();
        $activasRitez = ContribuyenteMnm::where('in_activo', true)->count();
        $inactivasRitez = ContribuyenteMnm::where('in_activo', false)->count();
        $conGpsRitez = ContribuyenteMnm::whereNotNull('nu_latitud')->where('nu_latitud', '!=', '')->count();

        $totalOficio = ContribuyenteOficio::count();
        $conGpsOficio = ContribuyenteOficio::whereNotNull('nu_latitud')->where('nu_latitud', '!=', '')->count();

        $totalGeneral = $totalRitez + $totalOficio;
        $activasGeneral = $activasRitez;
        $inactivasGeneral = $inactivasRitez;
        $conGpsGeneral = $conGpsRitez + $conGpsOficio;

        $municipios = Municipio::where('id_tab_estado', 23)->orderBy('de_municipio')->get();

        return response()->json([
            'success' => true,
            'kpis' => [
                'total_empresas' => $totalGeneral,
                'activas' => $activasGeneral,
                'inactivas' => $inactivasGeneral,
                'gps_verificado' => $conGpsGeneral,
                'porcentaje_gps' => $totalGeneral > 0 ? round(($conGpsGeneral / $totalGeneral) * 100, 1) : 0,
                'grupos' => [
                    'ritez' => [
                        'nombre' => 'Minerales No Metálicos',
                        'total' => $totalRitez,
                        'activas' => $activasRitez,
                        'inactivas' => $inactivasRitez,
                        'con_gps' => $conGpsRitez,
                    ],
                    'oficio' => [
                        'nombre' => 'Registro de Oficio de Usuarios de Minerales No Metálicos',
                        'total' => $totalOficio,
                        'con_gps' => $conGpsOficio,
                    ]
                ]
            ],
            'municipios' => $municipios
        ]);
    }

    /**
     * Actualizar coordenadas geográficas de un contribuyente (RITEZ o Registro de Oficio)
     */
    public function actualizarCoordenadas(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'latitud' => 'required|numeric|between:8.0,12.5',
            'longitud' => 'required|numeric|between:-74.0,-70.0',
        ]);

        $rawId = (string)$request->id;
        $grupo = $request->input('grupo', '');

        if (strpos($rawId, 'oficio_') === 0) {
            $id = (int)substr($rawId, 7);
            $grupo = 'oficio';
        } elseif (strpos($rawId, 'ritez_') === 0) {
            $id = (int)substr($rawId, 6);
            $grupo = 'ritez';
        } else {
            $id = (int)$rawId;
        }

        if ($grupo === 'oficio') {
            $empresa = ContribuyenteOficio::findOrFail($id);
        } elseif ($grupo === 'ritez') {
            $empresa = ContribuyenteMnm::findOrFail($id);
        } else {
            $empresa = ContribuyenteMnm::find($id) ?: ContribuyenteOficio::findOrFail($id);
        }

        $empresa->nu_latitud = (string)$request->latitud;
        $empresa->nu_longitud = (string)$request->longitud;
        $empresa->save();

        return response()->json([
            'success' => true,
            'message' => 'Coordenadas GPS actualizadas exitosamente en el sistema SEDATEZ.',
            'coordenadas' => [
                'lat' => (float)$request->latitud,
                'lng' => (float)$request->longitud
            ]
        ]);
    }

    /**
     * Obtener capas de polígonos de municipios por ejes según leyenda oficial SEDATEZ
     */
    public function getCapasEjes(Request $request)
    {
        $query = PoligonoEje::where('in_activo', 1)->orderBy('orden', 'asc');

        if ($request->filled('eje') && $request->eje !== 'all') {
            $ejeVal = $request->eje;
            if (is_numeric($ejeVal)) {
                $query->where('codigo_eje', 'EJE ' . $ejeVal);
            } else {
                $query->whereRaw('LOWER(codigo_eje) = ?', [strtolower($ejeVal)]);
            }
        }

        $poligonos = $query->get();

        $features = $poligonos->map(function ($p) {
            return [
                'type' => 'Feature',
                'id' => $p->id,
                'properties' => [
                    'id' => $p->id,
                    'codigo_eje' => $p->codigo_eje,
                    'nombre_eje' => $p->nombre_eje,
                    'id_municipio' => $p->id_tab_municipio,
                    'nombre_municipio' => $p->nombre_municipio,
                    'color_hex' => $p->color_hex,
                    'color_borde' => $p->color_borde ?: '#FFFFFF',
                    'opacidad' => (float)$p->opacidad,
                    'leyenda_municipios' => $p->leyenda_municipios,
                    'leyenda_minerales' => $p->leyenda_minerales,
                    'minerales' => $p->minerales_json ?: [],
                    'orden' => $p->orden
                ],
                'geometry' => $p->geometria_geojson
            ];
        });

        return response()->json([
            'success' => true,
            'type' => 'FeatureCollection',
            'metadata' => [
                'plano_codigo' => 'ME-01',
                'titulo' => 'MAPA DE MUNICIPIOS POR EJES DEL ESTADO ZULIA',
                'institucion' => 'SEDATEZ - GEOTECH.ca',
                'fecha' => 'AGOSTO 2026',
                'total_poligonos' => $features->count()
            ],
            'features' => $features
        ]);
    }
}

