<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Diagnóstico temporal de la Etapa 0: comprueba que el WAF del hosting deja
 * pasar peticiones con la forma de un webhook o de un retorno de pasarela.
 * No guarda valores recibidos: solo nombres de campo y si contienen una URL.
 */
class DiagController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'time' => now()->toIso8601String(),
            'php' => PHP_VERSION,
            'env' => app()->environment(),
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        return $this->record($request, 'POST /webhook', $request->all());
    }

    public function back(Request $request): JsonResponse
    {
        return $this->record($request, 'GET /back', $request->query());
    }

    private function record(Request $request, string $label, array $fields): JsonResponse
    {
        $summary = [];
        foreach ($fields as $name => $value) {
            $text = is_scalar($value) ? (string) $value : json_encode($value);
            $summary[$name] = [
                'length' => strlen($text),
                'has_url' => (bool) preg_match('#https?://#i', $text),
            ];
        }

        Log::build(['driver' => 'single', 'path' => storage_path('logs/diag.log')])->info($label, [
            'content_type' => $request->header('Content-Type'),
            'user_agent' => $request->userAgent(),
            'fields' => $summary,
        ]);

        return response()->json(['ok' => true, 'received' => $summary]);
    }
}
