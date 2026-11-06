<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySaasSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.hotelhub.integration_secret');
        $timestamp = (string) $request->header('X-SaaS-Timestamp', '');
        $signature = (string) $request->header('X-SaaS-Signature', '');

        if ($secret === '' || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            return response()->json(['message' => 'Firma de integración inválida o vencida.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp."\n".$request->getContent(), $secret);
        if (!hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Firma de integración inválida o vencida.'], 401);
        }

        return $next($request);
    }
}
