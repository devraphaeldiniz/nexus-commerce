<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApi
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $token = null;

        // 1. Extração do token de qualquer origem (Header, Bearer, Query param)
        $header = $request->header('Authorization')
            ?: $request->server('HTTP_AUTHORIZATION')
            ?: $request->server('REDIRECT_HTTP_AUTHORIZATION');

        if ($header && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $token = trim($matches[1]);
        }

        if (! $token) {
            $token = $request->query('token') ?: $request->input('token');
        }

        if (! $token) {
            return response()->json(['message' => 'Token de autenticação não fornecido.'], 401);
        }

        // 2. Busca o usuário correspondente
        $user = User::where('remember_token', $token)->first();

        if (! $user && DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
            $tokenRecord = DB::table('personal_access_tokens')
                ->where('token', hash('sha256', $token))
                ->orWhere('token', $token)
                ->first();

            if ($tokenRecord) {
                $user = User::find($tokenRecord->tokenable_id);
            }
        }

        if (! $user) {
            return response()->json([
                'message' => 'Sessão inválida ou expirada. Refaça o login.',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        // 3. Validação de Papéis (Roles)
        if (! empty($roles)) {
            $allowed = [];
            foreach ($roles as $r) {
                foreach (explode(',', $r) as $subRole) {
                    $allowed[] = strtoupper(trim($subRole));
                }
            }

            $currentRole = strtoupper((string) $user->role);

            if (! in_array($currentRole, $allowed, true)) {
                return response()->json([
                    'message' => "Acesso negado. Perfil '{$currentRole}' não autorizado.",
                ], 403);
            }
        }

        return $next($request);
    }
}
