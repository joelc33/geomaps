<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContribuyenteMnm;
use App\Models\Municipio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MnmDashboardController extends Controller
{
    /**
     * Obtener listado de empresas MNM con filtros
     */
    public function getEmpresas(Request $request)
    {
        $query = ContribuyenteMnm::with(['contribuyente', 'municipio', 'parroquia']);

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

        $empresas = $query->orderBy('de_razon_social', 'asc')->get();

        return response()->json([
            'success' => true,
            'total' => $empresas->count(),
            'data' => $empresas
        ]);
    }

    /**
     * Obtener KPIs y catálogos agregados
     */
    public function getKpis()
    {
        $total = ContribuyenteMnm::count();
        $activas = ContribuyenteMnm::where('in_activo', true)->count();
        $inactivas = ContribuyenteMnm::where('in_activo', false)->count();
        $conGps = ContribuyenteMnm::whereNotNull('nu_latitud')->where('nu_latitud', '!=', '')->count();

        $municipios = Municipio::where('id_tab_estado', 23)->orderBy('de_municipio')->get();

        return response()->json([
            'success' => true,
            'kpis' => [
                'total_empresas' => $total,
                'activas' => $activas,
                'inactivas' => $inactivas,
                'gps_verificado' => $conGps,
                'porcentaje_gps' => $total > 0 ? round(($conGps / $total) * 100, 1) : 0,
            ],
            'municipios' => $municipios
        ]);
    }

    /**
     * Actualizar coordenadas geográficas de un contribuyente
     */
    public function actualizarCoordenadas(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'latitud' => 'required|numeric|between:8.0,12.5',
            'longitud' => 'required|numeric|between:-74.0,-70.0',
        ]);

        $mnm = ContribuyenteMnm::findOrFail($request->id);
        $mnm->nu_latitud = (string)$request->latitud;
        $mnm->nu_longitud = (string)$request->longitud;
        $mnm->save();

        return response()->json([
            'success' => true,
            'message' => 'Coordenadas GPS actualizadas exitosamente en el sistema SEDATEZ.',
            'coordenadas' => [
                'lat' => (float)$request->latitud,
                'lng' => (float)$request->longitud
            ]
        ]);
    }
}
