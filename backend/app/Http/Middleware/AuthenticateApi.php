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
        $token = $request->cookie('nexus_auth');

        // Compatibilidade temporária: tokens Bearer serão aceitos apenas durante a migração.

        $header = $request->header('Authorization')
            ?: $request->server('HTTP_AUTHORIZATION')
            ?: $request->server('REDIRECT_HTTP_AUTHORIZATION');

        if (! $token && $header && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            $token = trim($matches[1]);
        }

        if (! $token) {
            return response()->json(['message' => 'Token de autenticação não fornecido.'], 401);
        }

        // 2. Busca o token pelo hash e valida sua expiração
        $user = User::where('api_token', hash('sha256', $token))
            ->where(function ($query) {
                $query->whereNull('api_token_expires_at')
                    ->orWhere('api_token_expires_at', '>', now());
            })
            ->first();


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

            if (in_array($currentRole, ['SELLER'], true) && ! $user->is_approved) {
                return response()->json(['message' => 'Vendedor aguardando aprovação.'], 403);
            }

            if (! in_array($currentRole, $allowed, true)) {
                return response()->json([
                    'message' => "Acesso negado. Perfil '{$currentRole}' não autorizado.",
                ], 403);
            }
        }

        return $next($request);
    }
}
