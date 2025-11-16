<?php

namespace App\Http\Controllers;

use App\Models\Accidente;
use App\Models\Localizacion;
use App\Models\Clima;
use App\Models\Tiempo;
use App\Models\Fecha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
       
        switch ($user->rol) {
            case 'Administrador':
                return redirect('/admin/dashboard');
            case 'Analista':
                return redirect('/analista/dashboard');
            case 'Invitado':
                return redirect('/invitado/dashboard');
            default:
                return redirect('/dashboard');
        }
    }

    public function getKPIs()
    {
        try {
            $metrics = DB::connection('oracle')
                ->table('HECHOS_T')
                ->selectRaw('
                    COUNT(*) as total_accidentes,
                    AVG(SEVERITY) as severidad_promedio,
                    SUM(CASE WHEN dc.WEATHER_CONDITION IN (\'Rain\', \'Snow\', \'Fog\', \'Heavy Rain\', \'Light Rain\', \'Heavy Snow\', \'Light Snow\', \'Cloudy\', \'Overcast\', \'Haze\') THEN 1 ELSE 0 END) as accidentes_clima,
                    SUM(CASE WHEN dt.HORA_INICIO_KEY >= 18 OR dt.HORA_INICIO_KEY < 6 THEN 1 ELSE 0 END) as accidentes_nocturnos
                ')
                ->leftJoin('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
                ->leftJoin('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
                ->first();

            Log::info('KPIs calculados:', [
                'total_accidentes' => $metrics->total_accidentes ?? 0,
                'severidad_promedio' => $metrics->severidad_promedio ?? 0,
                'accidentes_clima' => $metrics->accidentes_clima ?? 0,
                'accidentes_nocturnos' => $metrics->accidentes_nocturnos ?? 0
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'totalAccidentes' => number_format($metrics->total_accidentes ?? 0),
                    'severidadPromedio' => round($metrics->severidad_promedio ?? 0, 1),
                    'accidentesClima' => number_format($metrics->accidentes_clima ?? 0),
                    'accidentesNocturnos' => number_format($metrics->accidentes_nocturnos ?? 0)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getKPIs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar KPIs',
                'data' => [
                    'totalAccidentes' => '0',
                    'severidadPromedio' => '0.0',
                    'accidentesClima' => '0',
                    'accidentesNocturnos' => '0'
                ]
            ]);
        }
    }

    public function getChartData()
    {
        try {
            $chartData = $this->executeChartQueries();

            return response()->json([
                'success' => true,
                'charts' => $chartData
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getChartData: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar datos para gráficos',
                'charts' => []
            ]);
        }
    }

    private function executeChartQueries()
    {
        // Consulta para accidentes por estado
        $porEstados = DB::connection('oracle')
            ->table('HECHOS_T')
            ->join('DIM_LOCALIZACION as dl', 'HECHOS_T.LOC_KEY', '=', 'dl.LOC_KEY')
            ->select('dl.STATE as estado', DB::raw('COUNT(*) as total'))
            ->whereNotNull('dl.STATE')
            ->groupBy('dl.STATE')
            ->orderBy('total', 'DESC')
            ->limit(10)
            ->get();

        // Consulta para tendencia mensual
        $porMeses = DB::connection('oracle')
            ->table('HECHOS_T')
            ->join('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
            ->join('DIM_FECHA as df', 'dt.FECHA_KEY', '=', 'df.FECHA_KEY')
            ->select(
                DB::raw('TO_CHAR(df.ANIO) || \'-\' || LPAD(TO_CHAR(df.MES), 2, \'0\') as mes'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('df.ANIO', 'df.MES')
            ->orderBy('df.ANIO')
            ->orderBy('df.MES')
            ->get();

        // Consulta para distribución horaria
        $porHoras = DB::connection('oracle')
            ->table('HECHOS_T')
            ->join('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
            ->select('dt.HORA_INICIO_KEY as hora', DB::raw('COUNT(*) as total'))
            ->groupBy('dt.HORA_INICIO_KEY')
            ->orderBy('dt.HORA_INICIO_KEY')
            ->get();

        return [
            'porEstados' => $porEstados,
            'porMeses' => $porMeses,
            'porHoras' => $porHoras,
        ];
    }

    public function getDatosFiltrados(Request $request)
    {
        try {
            Log::info('Solicitud de datos filtrados recibida:', $request->all());

            $query = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_LOCALIZACION as dl', 'HECHOS_T.LOC_KEY', '=', 'dl.LOC_KEY')
                ->join('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
                ->join('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
                ->join('DIM_LUZ as dluz', 'HECHOS_T.LUZ_KEY', '=', 'dluz.LUZ_KEY');

            $this->applyFilters($query, $request);

            $total = $query->count();
           
            // OBTENER MÉTRICAS FILTRADAS
            $metrics = $this->getFilteredMetrics($query);
           
            $datosGraficos = $this->getFilteredChartData($request);

            Log::info('Datos filtrados procesados:', [
                'total' => $total,
                'metrics' => $metrics
            ]);

            return response()->json([
                'success' => true,
                'total' => $total,
                'metrics' => $metrics,
                'graficos' => $datosGraficos
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getDatosFiltrados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al aplicar filtros: ' . $e->getMessage()
            ]);
        }
    }

    private function getFilteredMetrics($query)
    {
        $metrics = (clone $query)
            ->selectRaw('
                COUNT(*) as total_accidentes,
                AVG(HECHOS_T.SEVERITY) as severidad_promedio,
                SUM(CASE WHEN dc.WEATHER_CONDITION IN (\'Rain\', \'Snow\', \'Fog\', \'Heavy Rain\', \'Light Rain\', \'Heavy Snow\', \'Light Snow\', \'Cloudy\', \'Overcast\', \'Haze\') THEN 1 ELSE 0 END) as accidentes_clima,
                SUM(CASE WHEN dt.HORA_INICIO_KEY >= 18 OR dt.HORA_INICIO_KEY < 6 THEN 1 ELSE 0 END) as accidentes_nocturnos
            ')
            ->first();

        Log::info('Métricas filtradas calculadas:', [
            'total_accidentes' => $metrics->total_accidentes ?? 0,
            'severidad_promedio' => $metrics->severidad_promedio ?? 0,
            'accidentes_clima' => $metrics->accidentes_clima ?? 0,
            'accidentes_nocturnos' => $metrics->accidentes_nocturnos ?? 0
        ]);

        return [
            'totalAccidentes' => number_format($metrics->total_accidentes ?? 0),
            'severidadPromedio' => round($metrics->severidad_promedio ?? 0, 1),
            'accidentesClima' => number_format($metrics->accidentes_clima ?? 0),
            'accidentesNocturnos' => number_format($metrics->accidentes_nocturnos ?? 0)
        ];
    }

    private function applyFilters($query, $request)
    {
        Log::info('Aplicando filtros:', $request->all());

        if ($request->filled('estado')) {
            $query->where('dl.STATE', $request->estado);
            Log::info('Filtro estado aplicado:', ['estado' => $request->estado]);
        }

        if ($request->filled('severidad')) {
            $query->where('HECHOS_T.SEVERITY', $request->severidad);
            Log::info('Filtro severidad aplicado:', ['severidad' => $request->severidad]);
        }

        if ($request->filled('clima')) {
            // Mapear valores en español a valores en inglés de la BD
            $climaMapping = [
                'Despejado' => 'Clear',
                'Lluvia' => 'Rain',
                'Nieve' => 'Snow',
                'Niebla' => 'Fog',
                'Nublado' => 'Cloudy',
                'Cubierto' => 'Overcast',
                'Bruma' => 'Haze',
                'Lluvia Intensa' => 'Heavy Rain',
                'Lluvia Ligera' => 'Light Rain',
                'Nieve Intensa' => 'Heavy Snow',
                'Nieve Ligera' => 'Light Snow'
            ];
            
            $climaValue = $climaMapping[$request->clima] ?? $request->clima;
            
            $query->where('dc.WEATHER_CONDITION', 'LIKE', '%' . $climaValue . '%');
            
            Log::info('Filtro clima aplicado:', [
                'clima_original' => $request->clima, 
                'clima_bd' => $climaValue,
                'query' => 'LIKE %' . $climaValue . '%'
            ]);
        }

        if ($request->filled('luz')) {
            // Mapear valores en español a valores en inglés de la BD
            $luzMapping = [
                'Día' => 'Day',
                'Noche' => 'Night',
                'Amanecer' => 'Dawn',
                'Atardecer' => 'Dusk'
            ];
            
            $luzValue = $luzMapping[$request->luz] ?? $request->luz;
            
            // Búsqueda exacta para luz
            $query->where('dluz.SUNRISE_SUNSET', $luzValue);
            
            Log::info('Filtro luz aplicado:', [
                'luz_original' => $request->luz, 
                'luz_bd' => $luzValue
            ]);
        }
    }

    public function getMapData()
    {
        try {
            Log::info('Solicitando datos del mapa...');

            // Consulta para obtener datos del mapa
            $estadosData = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_LOCALIZACION as dl', 'HECHOS_T.LOC_KEY', '=', 'dl.LOC_KEY')
                ->select(
                    'dl.STATE as estado',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('AVG(HECHOS_T.SEVERITY) as severidad_promedio')
                )
                ->whereNotNull('dl.STATE')
                ->groupBy('dl.STATE')
                ->orderBy('total', 'DESC')
                ->limit(15)
                ->get();

            Log::info('Datos de estados obtenidos:', ['count' => $estadosData->count()]);

            // Combinar con coordenadas
            $estadosConCoordenadas = $this->combinarConCoordenadas($estadosData);

            Log::info('Datos del mapa procesados:', [
                'total_estados' => $estadosConCoordenadas->count(),
                'estados' => $estadosConCoordenadas->pluck('estado')
            ]);

            return response()->json([
                'success' => true,
                'data' => $estadosConCoordenadas
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getMapData: ' . $e->getMessage());
            Log::error('Trace completo: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar datos del mapa: ' . $e->getMessage(),
                'data' => []
            ]);
        }
    }

    private function combinarConCoordenadas($estadosData)
    {
        // Coordenadas aproximadas del centro de cada estado
        $coordenadasEstados = [
            'CA' => ['lat' => 36.7783, 'lng' => -119.4179, 'name' => 'California'],
            'TX' => ['lat' => 31.9686, 'lng' => -99.9018, 'name' => 'Texas'],
            'FL' => ['lat' => 27.6648, 'lng' => -81.5158, 'name' => 'Florida'],
            'NY' => ['lat' => 42.1657, 'lng' => -74.9481, 'name' => 'Nueva York'],
            'IL' => ['lat' => 40.6331, 'lng' => -89.3985, 'name' => 'Illinois'],
            'PA' => ['lat' => 41.2033, 'lng' => -77.1945, 'name' => 'Pennsylvania'],
            'OH' => ['lat' => 40.4173, 'lng' => -82.9071, 'name' => 'Ohio'],
            'GA' => ['lat' => 32.1656, 'lng' => -82.9001, 'name' => 'Georgia'],
            'NC' => ['lat' => 35.7596, 'lng' => -79.0193, 'name' => 'Carolina del Norte'],
            'MI' => ['lat' => 44.3148, 'lng' => -85.6024, 'name' => 'Michigan'],
            'AZ' => ['lat' => 34.0489, 'lng' => -111.0937, 'name' => 'Arizona'],
            'WA' => ['lat' => 47.7511, 'lng' => -120.7401, 'name' => 'Washington'],
            'CO' => ['lat' => 39.5501, 'lng' => -105.7821, 'name' => 'Colorado'],
            'TN' => ['lat' => 35.5175, 'lng' => -86.5804, 'name' => 'Tennessee'],
            'MA' => ['lat' => 42.4072, 'lng' => -71.3824, 'name' => 'Massachusetts'],
        ];

        return $estadosData->map(function($estado) use ($coordenadasEstados) {
            $coords = $coordenadasEstados[$estado->estado] ?? ['lat' => 39.8283, 'lng' => -98.5795];
            
            $severidadPromedio = $estado->severidad_promedio ?? 0;
            if (!is_numeric($severidadPromedio)) {
                $severidadPromedio = floatval($severidadPromedio);
            }
            
            return (object)[
                'estado' => $estado->estado,
                'total' => $estado->total,
                'latitud' => $coords['lat'],
                'longitud' => $coords['lng'],
                'severidad_promedio' => $severidadPromedio
            ];
        });
    }

    public function getMapDataFiltrado(Request $request)
    {
        try {
            Log::info('Solicitud de mapa filtrado recibida:', $request->all());

            $query = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_LOCALIZACION as dl', 'HECHOS_T.LOC_KEY', '=', 'dl.LOC_KEY')
                ->select(
                    'dl.STATE as estado',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('AVG(HECHOS_T.SEVERITY) as severidad_promedio')
                )
                ->whereNotNull('dl.STATE');

            // Aplicar filtros
            if ($request->filled('estado')) {
                $query->where('dl.STATE', $request->estado);
            }

            if ($request->filled('severidad')) {
                $query->where('HECHOS_T.SEVERITY', $request->severidad);
            }

            if ($request->filled('clima')) {
                // Mapear valores en español a valores en inglés de la BD
                $climaMapping = [
                    'Despejado' => 'Clear',
                    'Lluvia' => 'Rain',
                    'Nieve' => 'Snow',
                    'Niebla' => 'Fog',
                    'Nublado' => 'Cloudy',
                    'Cubierto' => 'Overcast',
                    'Bruma' => 'Haze',
                    'Lluvia Intensa' => 'Heavy Rain',
                    'Lluvia Ligera' => 'Light Rain',
                    'Nieve Intensa' => 'Heavy Snow',
                    'Nieve Ligera' => 'Light Snow'
                ];
                
                $climaValue = $climaMapping[$request->clima] ?? $request->clima;
                $query->join('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
                      ->where('dc.WEATHER_CONDITION', 'LIKE', '%' . $climaValue . '%');
            }

            if ($request->filled('luz')) {
                // Mapear valores en español a valores en inglés de la BD
                $luzMapping = [
                    'Día' => 'Day',
                    'Noche' => 'Night',
                    'Amanecer' => 'Dawn',
                    'Atardecer' => 'Dusk'
                ];
                
                $luzValue = $luzMapping[$request->luz] ?? $request->luz;
                $query->join('DIM_LUZ as dluz', 'HECHOS_T.LUZ_KEY', '=', 'dluz.LUZ_KEY')
                      ->where('dluz.SUNRISE_SUNSET', $luzValue);
            }

            $estadosData = $query->groupBy('dl.STATE')->get();

            Log::info('Resultados de consulta filtrada:', [
                'total_estados' => $estadosData->count(),
                'estados' => $estadosData->pluck('estado')
            ]);

            // Combinar con coordenadas
            $estadosConCoordenadas = $this->combinarConCoordenadas($estadosData);

            return response()->json([
                'success' => true,
                'data' => $estadosConCoordenadas,
                'filters_applied' => $request->all()
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getMapDataFiltrado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar datos del mapa filtrado: ' . $e->getMessage()
            ]);
        }
    }

    private function getFilteredChartData($request)
    {
        $query = DB::connection('oracle')
            ->table('HECHOS_T')
            ->join('DIM_LOCALIZACION as dl', 'HECHOS_T.LOC_KEY', '=', 'dl.LOC_KEY')
            ->join('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
            ->join('DIM_LUZ as dluz', 'HECHOS_T.LUZ_KEY', '=', 'dluz.LUZ_KEY');

        $this->applyFilters($query, $request);

        $porEstados = (clone $query)
            ->select('dl.STATE as estado', DB::raw('COUNT(*) as total'))
            ->whereNotNull('dl.STATE')
            ->groupBy('dl.STATE')
            ->orderBy('total', 'DESC')
            ->limit(10)
            ->get();

        $porMeses = (clone $query)
            ->join('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
            ->join('DIM_FECHA as df', 'dt.FECHA_KEY', '=', 'df.FECHA_KEY')
            ->select(
                DB::raw('TO_CHAR(df.ANIO) || \'-\' || LPAD(TO_CHAR(df.MES), 2, \'0\') as mes'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('df.ANIO', 'df.MES')
            ->orderBy('df.ANIO')
            ->orderBy('df.MES')
            ->get();

        $porHoras = (clone $query)
            ->join('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
            ->select('dt.HORA_INICIO_KEY as hora', DB::raw('COUNT(*) as total'))
            ->groupBy('dt.HORA_INICIO_KEY')
            ->orderBy('dt.HORA_INICIO_KEY')
            ->get();

        Log::info('Datos de gráficos filtrados:', [
            'porEstados_count' => $porEstados->count(),
            'porMeses_count' => $porMeses->count(),
            'porHoras_count' => $porHoras->count()
        ]);

        return [
            'porEstados' => $porEstados,
            'porMeses' => $porMeses,
            'porHoras' => $porHoras
        ];
    }

    public function getEstadisticasAvanzadas()
    {
        try {
            $topEstados = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_LOCALIZACION as dl', 'HECHOS_T.LOC_KEY', '=', 'dl.LOC_KEY')
                ->select('dl.STATE', DB::raw('COUNT(*) as total'))
                ->whereNotNull('dl.STATE')
                ->groupBy('dl.STATE')
                ->orderBy('total', 'DESC')
                ->limit(5)
                ->get();

            $porSeveridad = DB::connection('oracle')
                ->table('HECHOS_T')
                ->select('SEVERITY', DB::raw('COUNT(*) as total'))
                ->groupBy('SEVERITY')
                ->orderBy('SEVERITY')
                ->get();

            $condicionesClima = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
                ->select('dc.WEATHER_CONDITION', DB::raw('COUNT(*) as total'))
                ->whereNotNull('dc.WEATHER_CONDITION')
                ->groupBy('dc.WEATHER_CONDITION')
                ->orderBy('total', 'DESC')
                ->limit(8)
                ->get();

            return response()->json([
                'success' => true,
                'topEstados' => $topEstados,
                'porSeveridad' => $porSeveridad,
                'condicionesClima' => $condicionesClima
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getEstadisticasAvanzadas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar estadísticas: ' . $e->getMessage()
            ]);
        }
    }
}