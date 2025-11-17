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
                    COALESCE(AVG(HECHOS_T.SEVERITY), 0) as severidad_promedio,
                    SUM(CASE WHEN dt.HORA_INICIO_KEY BETWEEN 18 AND 23 OR dt.HORA_INICIO_KEY BETWEEN 0 AND 5 THEN 1 ELSE 0 END) as accidentes_nocturnos,
                    SUM(CASE WHEN HECHOS_T.SEVERITY >= 3 THEN 1 ELSE 0 END) as accidentes_graves
                ')
                ->leftJoin('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'totalAccidentes' => number_format($metrics->total_accidentes),
                    'severidadPromedio' => round($metrics->severidad_promedio, 1),
                    'accidentesNocturnos' => number_format($metrics->accidentes_nocturnos),
                    'accidentesGraves' => number_format($metrics->accidentes_graves)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getKPIs: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al cargar KPIs']);
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
            // MAPEO COMPLETO Y CORREGIDO basado en los datos reales
            $climaMapping = [
                'Despejado' => ['FAIR', 'CLEAR'],
                'Lluvia' => ['RAIN', 'LIGHT RAIN', 'HEAVY RAIN'],
                'Nieve' => ['LIGHT SNOW', 'HEAVY SNOW', 'WINTRY MIX'],
                'Niebla' => ['FOG', 'PATCHES OF FOG'],
                'Nublado' => ['CLOUDY', 'MOSTLY CLOUDY', 'PARTLY CLOUDY', 'SCATTERED CLOUDS', 'OVERCAST'],
                'Tormenta' => ['T-STORM', 'THUNDERSTORM'],
                'Llovizna' => ['LIGHT DRIZZLE', 'DRIZZLE'],
                'Bruma' => ['HAZE'],
                'Ventoso' => ['FAIR / WINDY', 'MOSTLY CLOUDY / WINDY', 'CLOUDY / WINDY']
            ];
        
            $climaValue = $request->clima;
        
            if (isset($climaMapping[$climaValue])) {
                // Si es una categoría con múltiples valores en BD
                $query->whereIn('dc.WEATHER_CONDITION', $climaMapping[$climaValue]);
                Log::info('Filtro clima aplicado (múltiples valores):', [
                    'clima_original' => $climaValue,
                    'valores_bd' => $climaMapping[$climaValue]
                ]);
            } else {
                // Búsqueda directa
                $query->where('dc.WEATHER_CONDITION', 'LIKE', '%' . $climaValue . '%');
                Log::info('Filtro clima aplicado (búsqueda directa):', [
                    'clima_original' => $climaValue
                ]);
            }
        }

        if ($request->filled('luz')) {
            // Mapeo corregido para luz - usar valores EXACTOS de la BD
            $luzMapping = [
                'Día' => 'DAY',
                'Noche' => 'NIGHT'
                // No incluir Dawn/Dusk ya que no existen en tus datos
            ];
        
            $luzValue = $luzMapping[$request->luz] ?? $request->luz;
        
            if ($luzValue) {
                $query->where('dluz.SUNRISE_SUNSET', $luzValue);
                Log::info('Filtro luz aplicado:', [
                    'luz_original' => $request->luz,
                    'luz_bd' => $luzValue
                ]);
            }
        }
    }


    private function getFilteredMetrics($query)
    {
        $metrics = (clone $query)
            ->selectRaw('
                COUNT(*) as total_accidentes,
                COALESCE(AVG(HECHOS_T.SEVERITY), 0) as severidad_promedio,
                SUM(CASE WHEN dt.HORA_INICIO_KEY BETWEEN 18 AND 23 OR dt.HORA_INICIO_KEY BETWEEN 0 AND 5 THEN 1 ELSE 0 END) as accidentes_nocturnos
            ')
            ->first();

        return [
            'totalAccidentes' => number_format($metrics->total_accidentes ?? 0),
            'severidadPromedio' => round($metrics->severidad_promedio ?? 0, 1),
            'accidentesNocturnos' => number_format($metrics->accidentes_nocturnos ?? 0)
        ];
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
                $climaMapping = [
                    'Despejado' => ['FAIR', 'CLEAR'],
                    'Lluvia' => ['RAIN', 'LIGHT RAIN', 'HEAVY RAIN'],
                    'Nieve' => ['LIGHT SNOW', 'HEAVY SNOW', 'WINTRY MIX'],
                    'Niebla' => ['FOG', 'PATCHES OF FOG'],
                    'Nublado' => ['CLOUDY', 'MOSTLY CLOUDY', 'PARTLY CLOUDY', 'SCATTERED CLOUDS', 'OVERCAST'],
                    'Tormenta' => ['T-STORM', 'THUNDERSTORM'],
                    'Llovizna' => ['LIGHT DRIZZLE', 'DRIZZLE'],
                    'Bruma' => ['HAZE'],
                    'Ventoso' => ['FAIR / WINDY', 'MOSTLY CLOUDY / WINDY', 'CLOUDY / WINDY']
                ];
            
                $climaValue = $request->clima;
            
                if (isset($climaMapping[$climaValue])) {
                    $query->join('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
                        ->whereIn('dc.WEATHER_CONDITION', $climaMapping[$climaValue]);
                } else {
                    $query->join('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
                        ->where('dc.WEATHER_CONDITION', 'LIKE', '%' . $climaValue . '%');
                }
            }

            if ($request->filled('luz')) {
                $luzMapping = [
                    'Día' => 'DAY',
                    'Noche' => 'NIGHT'
                ];
            
                $luzValue = $luzMapping[$request->luz] ?? $request->luz;
            
                if ($luzValue) {
                    $query->join('DIM_LUZ as dluz', 'HECHOS_T.LUZ_KEY', '=', 'dluz.LUZ_KEY')
                        ->where('dluz.SUNRISE_SUNSET', $luzValue);
                }
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
                'filters_applied' => $request->all(),
                'query_debug' => $estadosData // Para debugging
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

    public function getOpcionesComparadores(Request $request)
    {
        try {
            $tipo = $request->input('tipo', 'periodos');
            
            Log::info("Solicitando opciones para tipo: {$tipo}");

            switch ($tipo) {
                case 'periodos':
                    $resultados = DB::connection('oracle')
                        ->table('DIM_FECHA')
                        ->select('ANIO')
                        ->whereNotNull('ANIO')
                        ->where('ANIO', '>', 2015)
                        ->where('ANIO', '<', 2024)
                        ->distinct()
                        ->orderBy('ANIO', 'desc')
                        ->limit(10)
                        ->get();

                    // DEBUG: Ver la estructura real
                    Log::info("Estructura periodo:", [
                        'primer_item' => $resultados->first(),
                        'campos' => $resultados->first() ? array_keys((array)$resultados->first()) : []
                    ]);

                    $opciones = collect();
                    foreach ($resultados as $item) {
                        // Probar diferentes nombres de campo
                        $anio = $item->anio ?? $item->ANIO ?? null;
                        if ($anio) {
                            $opciones->push((int)$anio);
                        }
                    }

                    break;
                
                case 'regiones':
                    $resultados = DB::connection('oracle')
                        ->table('DIM_LOCALIZACION')
                        ->select('STATE')
                        ->whereNotNull('STATE')
                        ->where('STATE', '!=', 'UNKNOWN')
                        ->whereRaw('LENGTH(STATE) = 2')
                        ->distinct()
                        ->orderBy('STATE')
                        ->limit(15)
                        ->get();

                    Log::info("Estructura regiones:", [
                        'primer_item' => $resultados->first(),
                        'campos' => $resultados->first() ? array_keys((array)$resultados->first()) : []
                    ]);

                    $opciones = collect();
                    foreach ($resultados as $item) {
                        $estado = $item->state ?? $item->STATE ?? null;
                        if ($estado) {
                            $opciones->push($estado);
                        }
                    }
                    break;
                
                case 'categorias':
                    $resultados = DB::connection('oracle')
                        ->table('DIM_CLIMA')
                        ->select('WEATHER_CONDITION')
                        ->whereNotNull('WEATHER_CONDITION')
                        ->where('WEATHER_CONDITION', '!=', 'UNKNOWN')
                        ->distinct()
                        ->orderBy('WEATHER_CONDITION')
                        ->limit(15)
                        ->get();

                    Log::info("Estructura climas:", [
                        'primer_item' => $resultados->first(),
                        'campos' => $resultados->first() ? array_keys((array)$resultados->first()) : []
                    ]);

                    $opciones = collect();
                    foreach ($resultados as $item) {
                        $clima = $item->weather_condition ?? $item->WEATHER_CONDITION ?? null;
                        if ($clima) {
                            $opciones->push($clima);
                        }
                    }
                    break;
                
                default:
                    $opciones = collect([]);
            }

            Log::info("Opciones finales para {$tipo}:", [
                'count' => $opciones->count(),
                'opciones' => $opciones->toArray()
            ]);

            return response()->json([
                'success' => true,
                'tipo' => $tipo,
                'opciones' => $opciones
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo opciones comparadores: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener opciones: ' . $e->getMessage(),
                'tipo' => $tipo,
                'opciones' => []
            ], 500);
        }
    }


    private function compararPeriodos($anio1, $anio2)
    {
        try {
            Log::info("Comparando períodos: {$anio1} vs {$anio2}");

            // Consulta mejorada para períodos
            $query1 = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
                ->join('DIM_FECHA as df', 'dt.FECHA_KEY', '=', 'df.FECHA_KEY')
                ->select(
                    DB::raw("'{$anio1}' as periodo"),
                    DB::raw('COUNT(*) as total_accidentes'),
                    DB::raw('COALESCE(AVG(HECHOS_T.SEVERITY), 0) as severidad_promedio'),
                    DB::raw('SUM(CASE WHEN HECHOS_T.SEVERITY >= 3 THEN 1 ELSE 0 END) as accidentes_graves'),
                    DB::raw('COALESCE(AVG(HECHOS_T.DISTANCE_MI), 0) as distancia_promedio')
                )
                ->where('df.ANIO', $anio1);

            $query2 = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_TIEMPO_ESTADO as dt', 'HECHOS_T.TIEMPO_KEY', '=', 'dt.TIEMPO_KEY')
                ->join('DIM_FECHA as df', 'dt.FECHA_KEY', '=', 'df.FECHA_KEY')
                ->select(
                    DB::raw("'{$anio2}' as periodo"),
                    DB::raw('COUNT(*) as total_accidentes'),
                    DB::raw('COALESCE(AVG(HECHOS_T.SEVERITY), 0) as severidad_promedio'),
                    DB::raw('SUM(CASE WHEN HECHOS_T.SEVERITY >= 3 THEN 1 ELSE 0 END) as accidentes_graves'),
                    DB::raw('COALESCE(AVG(HECHOS_T.DISTANCE_MI), 0) as distancia_promedio')
                )
                ->where('df.ANIO', $anio2);

            $result1 = $query1->first();
            $result2 = $query2->first();

            $comparison = collect([]);
            
            if ($result1) {
                $comparison->push((object)[
                    'periodo' => $result1->periodo,
                    'total_accidentes' => (int)$result1->total_accidentes,
                    'severidad_promedio' => round((float)$result1->severidad_promedio, 2),
                    'accidentes_graves' => (int)$result1->accidentes_graves,
                    'distancia_promedio' => round((float)$result1->distancia_promedio, 2)
                ]);
            } else {
                $comparison->push((object)[
                    'periodo' => $anio1,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0,
                    'distancia_promedio' => 0
                ]);
            }

            if ($result2) {
                $comparison->push((object)[
                    'periodo' => $result2->periodo,
                    'total_accidentes' => (int)$result2->total_accidentes,
                    'severidad_promedio' => round((float)$result2->severidad_promedio, 2),
                    'accidentes_graves' => (int)$result2->accidentes_graves,
                    'distancia_promedio' => round((float)$result2->distancia_promedio, 2)
                ]);
            } else {
                $comparison->push((object)[
                    'periodo' => $anio2,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0,
                    'distancia_promedio' => 0
                ]);
            }

            Log::info("Resultados comparación períodos:", $comparison->toArray());

            return response()->json([
                'success' => true,
                'tipo' => 'periodos',
                'data' => $comparison,
                'parametros' => ['año1' => $anio1, 'año2' => $anio2]
            ]);

        } catch (\Exception $e) {
            Log::error("Error comparando períodos {$anio1} vs {$anio2}: " . $e->getMessage());
            
            // Retornar datos por defecto en caso de error
            $datosPorDefecto = collect([
                (object)[
                    'periodo' => $anio1,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0,
                    'distancia_promedio' => 0
                ],
                (object)[
                    'periodo' => $anio2,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0,
                    'distancia_promedio' => 0
                ]
            ]);

            return response()->json([
                'success' => true,
                'tipo' => 'periodos',
                'data' => $datosPorDefecto,
                'parametros' => ['año1' => $anio1, 'año2' => $anio2],
                'warning' => 'Se muestran datos por defecto debido a un error: ' . $e->getMessage()
            ]);
        }
    }

    private function compararRegiones($estado1, $estado2)
    {
        try {
            Log::info("Comparando regiones: {$estado1} vs {$estado2}");

            $comparison = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_LOCALIZACION as dl', 'HECHOS_T.LOC_KEY', '=', 'dl.LOC_KEY')
                ->select(
                    'dl.STATE as region',
                    DB::raw('COUNT(*) as total_accidentes'),
                    DB::raw('COALESCE(AVG(HECHOS_T.SEVERITY), 0) as severidad_promedio'),
                    DB::raw('SUM(CASE WHEN HECHOS_T.SEVERITY >= 3 THEN 1 ELSE 0 END) as accidentes_graves'),
                    DB::raw('COALESCE(AVG(HECHOS_T.DISTANCE_MI), 0) as distancia_promedio')
                )
                ->whereIn('dl.STATE', [$estado1, $estado2])
                ->groupBy('dl.STATE')
                ->orderBy('dl.STATE')
                ->get()
                ->map(function($item) {
                    return (object)[
                        'region' => $item->region,
                        'total_accidentes' => (int)$item->total_accidentes,
                        'severidad_promedio' => round((float)$item->severidad_promedio, 2),
                        'accidentes_graves' => (int)$item->accidentes_graves,
                        'distancia_promedio' => round((float)$item->distancia_promedio, 2)
                    ];
                });

            Log::info("Resultados comparación regiones:", $comparison->toArray());

            // Verificar si tenemos ambos estados
            $estadosEncontrados = $comparison->pluck('region')->toArray();
            
            if (!in_array($estado1, $estadosEncontrados)) {
                $comparison->push((object)[
                    'region' => $estado1,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0,
                    'distancia_promedio' => 0
                ]);
            }
            
            if (!in_array($estado2, $estadosEncontrados)) {
                $comparison->push((object)[
                    'region' => $estado2,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0,
                    'distancia_promedio' => 0
                ]);
            }

            return response()->json([
                'success' => true,
                'tipo' => 'regiones',
                'data' => $comparison,
                'parametros' => ['estado1' => $estado1, 'estado2' => $estado2]
            ]);

        } catch (\Exception $e) {
            Log::error("Error comparando regiones {$estado1} vs {$estado2}: " . $e->getMessage());
            
            // Retornar datos por defecto
            $datosPorDefecto = collect([
                (object)[
                    'region' => $estado1,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0,
                    'distancia_promedio' => 0
                ],
                (object)[
                    'region' => $estado2,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0,
                    'distancia_promedio' => 0
                ]
            ]);

            return response()->json([
                'success' => true,
                'tipo' => 'regiones',
                'data' => $datosPorDefecto,
                'parametros' => ['estado1' => $estado1, 'estado2' => $estado2],
                'warning' => 'Se muestran datos por defecto debido a un error: ' . $e->getMessage()
            ]);
        }
    }


    private function compararCategorias($categoria1, $categoria2)
    {
        try {
            Log::info("Comparando categorías clima: {$categoria1} vs {$categoria2}");

            // Mapeo de categorías en español a inglés para la BD
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

            $categoria1Eng = $climaMapping[$categoria1] ?? $categoria1;
            $categoria2Eng = $climaMapping[$categoria2] ?? $categoria2;

            $comparison = DB::connection('oracle')
                ->table('HECHOS_T')
                ->join('DIM_CLIMA as dc', 'HECHOS_T.CLIMA_KEY', '=', 'dc.CLIMA_KEY')
                ->select(
                    'dc.WEATHER_CONDITION as categoria',
                    DB::raw('COUNT(*) as total_accidentes'),
                    DB::raw('COALESCE(AVG(HECHOS_T.SEVERITY), 0) as severidad_promedio'),
                    DB::raw('SUM(CASE WHEN HECHOS_T.SEVERITY >= 3 THEN 1 ELSE 0 END) as accidentes_graves')
                )
                ->whereIn('dc.WEATHER_CONDITION', [$categoria1Eng, $categoria2Eng])
                ->groupBy('dc.WEATHER_CONDITION')
                ->orderBy('dc.WEATHER_CONDITION')
                ->get()
                ->map(function($item) {
                    return (object)[
                        'categoria' => $item->categoria,
                        'total_accidentes' => (int)$item->total_accidentes,
                        'severidad_promedio' => round((float)$item->severidad_promedio, 2),
                        'accidentes_graves' => (int)$item->accidentes_graves
                    ];
                });

            Log::info("Resultados comparación categorías:", $comparison->toArray());

            // Verificar si tenemos ambas categorías
            $categoriasEncontradas = $comparison->pluck('categoria')->toArray();
            
            if (!in_array($categoria1Eng, $categoriasEncontradas)) {
                $comparison->push((object)[
                    'categoria' => $categoria1Eng,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0
                ]);
            }
            
            if (!in_array($categoria2Eng, $categoriasEncontradas)) {
                $comparison->push((object)[
                    'categoria' => $categoria2Eng,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0
                ]);
            }

            return response()->json([
                'success' => true,
                'tipo' => 'categorias',
                'data' => $comparison,
                'parametros' => ['categoria1' => $categoria1, 'categoria2' => $categoria2]
            ]);

        } catch (\Exception $e) {
            Log::error("Error comparando categorías {$categoria1} vs {$categoria2}: " . $e->getMessage());
            
            // Retornar datos por defecto
            $datosPorDefecto = collect([
                (object)[
                    'categoria' => $categoria1,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0
                ],
                (object)[
                    'categoria' => $categoria2,
                    'total_accidentes' => 0,
                    'severidad_promedio' => 0,
                    'accidentes_graves' => 0
                ]
            ]);

            return response()->json([
                'success' => true,
                'tipo' => 'categorias',
                'data' => $datosPorDefecto,
                'parametros' => ['categoria1' => $categoria1, 'categoria2' => $categoria2],
                'warning' => 'Se muestran datos por defecto debido a un error: ' . $e->getMessage()
            ]);
        }
    }

    public function getComparadores(Request $request)
    {
        try {
            Log::info('Solicitud de comparadores recibida:', $request->all());

            $tipo = $request->input('tipo', 'periodos');
            $param1 = $request->input('param1');
            $param2 = $request->input('param2');
           
            if (!$param1 || !$param2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se requieren ambos parámetros para la comparación'
                ], 400);
            }
           
            switch ($tipo) {
                case 'periodos':
                    return $this->compararPeriodos($param1, $param2);
                case 'regiones':
                    return $this->compararRegiones($param1, $param2);
                case 'categorias':
                    return $this->compararCategorias($param1, $param2);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipo de comparación no válido. Use: periodos, regiones o categorias'
                    ], 400);
            }
           
        } catch (\Exception $e) {
            Log::error('Error en comparadores: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    
}