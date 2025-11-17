<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CargaController extends Controller
{
    public function index()
    {
        return view('carga.index');
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls,json|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . $validator->errors()->first()
            ]);
        }

        try {
            $file = $request->file('archivo');
            $fileName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            switch ($extension) {
                case 'csv':
                    $result = $this->processCSV($file);
                    break;
                case 'xlsx':
                case 'xls':
                    $result = $this->processExcel($file);
                    break;
                case 'json':
                    $result = $this->processJSON($file);
                    break;
                default:
                    throw new \Exception('Formato de archivo no soportado');
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Error en carga de archivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar archivo: ' . $e->getMessage()
            ]);
        }
    }

    private function processCSV($file)
    {
        $fileName = $file->getClientOriginalName();
       
        try {
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }
           
            $uniqueFileName = uniqid() . '_' . $fileName;
            $fullPath = $tempPath . '/' . $uniqueFileName;
           
            $file->move($tempPath, $uniqueFileName);
           
            if (!file_exists($fullPath)) {
                throw new \Exception('No se pudo guardar el archivo temporal');
            }
           
            $data = [];
            if (($handle = fopen($fullPath, 'r')) !== FALSE) {
                $headers = fgetcsv($handle, 1000, ',');
               
                if ($headers === FALSE) {
                    throw new \Exception('No se pudieron leer los encabezados del CSV');
                }
               
                $headers = array_map(function($header) {
                    return trim(mb_convert_encoding($header, 'UTF-8', 'UTF-8'));
                }, $headers);
               
                $lineNumber = 1;
                while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $lineNumber++;
                    if (count($row) === count($headers)) {
                        $cleanedRow = array_map(function($value) {
                            return trim(mb_convert_encoding($value, 'UTF-8', 'UTF-8'));
                        }, $row);
                        $data[] = array_combine($headers, $cleanedRow);
                    }
                }
                fclose($handle);
            } else {
                throw new \Exception('No se pudo abrir el archivo CSV');
            }

            // DEBUG: Ver qué datos se están leyendo
            Log::info("Datos leídos del CSV:", ['count' => count($data), 'first_row' => $data[0] ?? []]);

            $transformedData = $this->transformData($data);
            $inserted = $this->insertToOracle($transformedData);
           
            // CORREGIDO: Solo pasar los parámetros necesarios
            $this->logUpload($fileName, $inserted, 'completado');
           
            unlink($fullPath);

            return response()->json([
                'success' => true,
                'message' => "Archivo CSV procesado correctamente. {$inserted} registros insertados.",
                'records' => $inserted
            ]);

        } catch (\Exception $e) {
            if (isset($fullPath) && file_exists($fullPath)) {
                unlink($fullPath);
            }
            throw $e;
        }
    }

    private function processExcel($file)
    {
        $fileName = $file->getClientOriginalName();
       
        try {
            // Usar storeAs en lugar de store para tener más control
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }
           
            $uniqueFileName = uniqid() . '_' . $fileName;
            $fullPath = $tempPath . '/' . $uniqueFileName;
           
            // Mover el archivo manualmente
            $file->move($tempPath, $uniqueFileName);
           
            if (!file_exists($fullPath)) {
                throw new \Exception('No se pudo guardar el archivo temporal Excel');
            }

            Log::info("Archivo Excel guardado en: " . $fullPath);

            $spreadsheet = IOFactory::load($fullPath);
            $worksheet = $spreadsheet->getActiveSheet();
           
            $data = [];
            $headers = [];
            $firstRow = true;
           
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
               
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getCalculatedValue();
                }
               
                if (empty(array_filter($rowData))) {
                    continue;
                }
               
                if ($firstRow) {
                    $headers = array_map(function($header) {
                        return trim(mb_convert_encoding($header, 'UTF-8', 'UTF-8'));
                    }, $rowData);
                    $firstRow = false;
                } else {
                    if (count($rowData) === count($headers)) {
                        $cleanedRow = array_map(function($value) {
                            return is_null($value) ? '' : trim(mb_convert_encoding($value, 'UTF-8', 'UTF-8'));
                        }, $rowData);
                        $data[] = array_combine($headers, $cleanedRow);
                    }
                }
            }

            // DEBUG
            Log::info("Datos leídos del Excel:", ['count' => count($data), 'first_row' => $data[0] ?? []]);

            $transformedData = $this->transformData($data);
            $inserted = $this->insertToOracle($transformedData);
           
            $this->logUpload($fileName, $inserted, 'completado');
           
            // Limpiar archivo temporal
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return response()->json([
                'success' => true,
                'message' => "Archivo Excel procesado correctamente. {$inserted} registros insertados.",
                'records' => $inserted
            ]);

        } catch (\Exception $e) {
            // Limpiar archivo temporal en caso de error
            if (isset($fullPath) && file_exists($fullPath)) {
                unlink($fullPath);
            }
            throw new \Exception("Error procesando Excel: " . $e->getMessage());
        }
    }

    private function processJSON($file)
    {
        $fileName = $file->getClientOriginalName();
       
        try {
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
            }
           
            $uniqueFileName = uniqid() . '_' . $fileName;
            $fullPath = $tempPath . '/' . $uniqueFileName;
           
            // Mover el archivo manualmente
            $file->move($tempPath, $uniqueFileName);
           
            if (!file_exists($fullPath)) {
                throw new \Exception('No se pudo guardar el archivo temporal JSON');
            }

            Log::info("Archivo JSON guardado en: " . $fullPath);

            $jsonContent = file_get_contents($fullPath);
            $data = json_decode($jsonContent, true);
           
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Error decodificando JSON: ' . json_last_error_msg());
            }
           
            // Si es un objeto único, convertirlo a array
            if (!is_array($data) || !isset($data[0])) {
                $data = [$data];
            }

            // DEBUG
            Log::info("Datos leídos del JSON:", ['count' => count($data), 'first_row' => $data[0] ?? []]);

            $transformedData = $this->transformData($data);
            $inserted = $this->insertToOracle($transformedData);
           
            $this->logUpload($fileName, $inserted, 'completado');
           
            // Limpiar archivo temporal
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return response()->json([
                'success' => true,
                'message' => "Archivo JSON procesado correctamente. {$inserted} registros insertados.",
                'records' => $inserted
            ]);

        } catch (\Exception $e) {
            // Limpiar archivo temporal en caso de error
            if (isset($fullPath) && file_exists($fullPath)) {
                unlink($fullPath);
            }
            throw $e;
        }
    }

    private function transformData($data)
    {
        return array_map(function($row) {
            $transformed = [];
           
            foreach ($row as $key => $value) {
                $cleanKey = strtoupper(trim($key));
                $cleanValue = is_string($value) ? trim($value) : $value;
               
                if ($cleanValue === '' || $cleanValue === 'NULL') {
                    $cleanValue = null;
                }
               
                $transformed[$cleanKey] = $cleanValue;
            }
           
            return $transformed;
        }, $data);
    }

    // CORREGIDO: Eliminado el parámetro $tipoCarga
    private function insertToOracle($data)
    {
        $batchSize = 100;
        $inserted = 0;
        $tableName = 'DATOS';

        Log::info('Iniciando inserción en Oracle', ['total_records' => count($data)]);

        foreach (array_chunk($data, $batchSize) as $chunkIndex => $batch) {
            $preparedBatch = [];
           
            try {
                Log::info("Procesando lote {$chunkIndex}", ['batch_size' => count($batch)]);

                // Obtener el máximo ID actual
                $maxId = 0;
                try {
                    $maxIdResult = DB::connection('oracle')
                        ->table($tableName)
                        ->select(DB::raw('COALESCE(MAX(TO_NUMBER(ID)), 0) as max_id'))
                        ->first();
                    $maxId = $maxIdResult ? (int)$maxIdResult->max_id : 0;
                } catch (\Exception $e) {
                    Log::warning("No se pudo obtener max ID, usando 0: " . $e->getMessage());
                }

                Log::info("Max ID encontrado: {$maxId}");

                foreach ($batch as $rowIndex => $row) {
                    $maxId++;
                   
                    // Mapeo simplificado y robusto
                    $preparedRow = [
                        'ID' => (string) $maxId,
                        'SOURCE' => 'FileUpload',
                        'SEVERITY' => $row['SEVERITY'] ?? null,
                        'START_TIME' => $row['START_TIME'] ?? null,
                        'END_TIME' => $row['END_TIME'] ?? null,
                        'START_LAT' => $row['START_LAT'] ?? null,
                        'START_LNG' => $row['START_LNG'] ?? null,
                        'STREET' => $row['STREET'] ?? null,
                        'CITY' => $row['CITY'] ?? null,
                        'STATE' => $row['STATE'] ?? null,
                        'WEATHER_CONDITION' => $row['WEATHER_CONDITION'] ?? null,
                        'TEMPERATURE_F' => $row['TEMPERATURE_F'] ?? null,
                        'HUMIDITY' => $row['HUMIDITY'] ?? null,
                        'PRESSURE_IN' => $row['PRESSURE_IN'] ?? null,
                        'VISIBILITY_MI' => $row['VISIBILITY_MI'] ?? null,
                        'WIND_SPEED_MPH' => $row['WIND_SPEED_MPH'] ?? null,
                        'PRECIPITATION_IN' => $row['PRECIPITATION_IN'] ?? null,
                        'DESCRIPTION' => $row['DESCRIPTION'] ?? 'Importado desde archivo',
                        'COUNTRY' => $row['COUNTRY'] ?? 'US'
                    ];

                    // DEBUG: Log de la primera fila del primer lote
                    if ($chunkIndex === 0 && $rowIndex === 0) {
                        Log::info("Primera fila a insertar:", $preparedRow);
                    }

                    $preparedBatch[] = $preparedRow;
                }

                Log::info("Insertando lote {$chunkIndex}", ['records' => count($preparedBatch)]);

                // Insertar en lote
                DB::connection('oracle')->table($tableName)->insert($preparedBatch);
                $inserted += count($preparedBatch);

                Log::info("Lote {$chunkIndex} insertado correctamente");

            } catch (\Exception $e) {
                Log::error("Error insertando lote {$chunkIndex}: " . $e->getMessage());
               
                if (!empty($preparedBatch)) {
                    Log::info("Intentando inserción individual para lote {$chunkIndex}");
                    foreach ($preparedBatch as $singleRow) {
                        try {
                            DB::connection('oracle')->table($tableName)->insert($singleRow);
                            $inserted++;
                        } catch (\Exception $singleError) {
                            Log::error("Error fila individual: " . $singleError->getMessage());
                        }
                    }
                }
            }
        }

        Log::info("Inserción completada", ['total_inserted' => $inserted]);
        return $inserted;
    }

    // CORREGIDO: Eliminado el parámetro $tipoCarga
    private function logUpload($fileName, $recordsProcessed, $status)
    {
        try {
            $userName = Auth::check() ? Auth::user()->name : 'Sistema';
           
            DB::connection('oracle')->table('UPLOAD_LOGS')->insert([
                'ID' => DB::connection('oracle')->select("SELECT seq_upload_logs.NEXTVAL as id FROM dual")[0]->id,
                'NOMBRE_ARCHIVO' => $fileName,
                'TIPO_CARGA' => 'DATOS', // Valor fijo
                'REGISTROS_PROCESADOS' => $recordsProcessed,
                'ESTADO' => $status,
                'FECHA_UPLOAD' => now(),
                'USUARIO' => $userName
            ]);
        } catch (\Exception $e) {
            Log::error('Error guardando log: ' . $e->getMessage());
        }
    }

    public function getUploadHistory()
    {
        try {
            Log::info('Obteniendo historial de uploads...');
           
            $history = DB::connection('oracle')
                ->table('UPLOAD_LOGS')
                ->select([
                    'ID',
                    'NOMBRE_ARCHIVO', // CORREGIDO: Este es el nombre correcto
                    'TIPO_CARGA',
                    'REGISTROS_PROCESADOS',
                    'ESTADO',
                    DB::raw("TO_CHAR(FECHA_UPLOAD, 'YYYY-MM-DD HH24:MI:SS') as FECHA_UPLOAD"),
                    'USUARIO'
                ])
                ->orderBy('FECHA_UPLOAD', 'desc')
                ->limit(20)
                ->get();

            // DEBUG: Verificar estructura de datos
            if ($history->isNotEmpty()) {
                $firstRecord = (array)$history->first();
                Log::info('Estructura del primer registro:', [
                    'keys' => array_keys($firstRecord),
                    'values' => $firstRecord
                ]);
            }

            Log::info('Total de registros en historial:', ['count' => count($history)]);

            return response()->json([
                'success' => true,
                'history' => $history,
                'message' => 'Historial cargado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo historial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo historial: ' . $e->getMessage(),
                'history' => []
            ]);
        }
    }
   

}