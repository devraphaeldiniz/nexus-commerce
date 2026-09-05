<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token de autenticação não informado.'], 401);
        }

        $user = User::where('api_token', hash('sha256', $token))->first();

        if (! $user) {
            return response()->json(['message' => 'Sessão inválida.'], 401);
        }

        if ($user->api_token_expires_at && now()->isAfter($user->api_token_expires_at)) {
            return response()->json(['message' => 'Sessão expirada. Faça login novamente.'], 401);
        }

        if (! empty($roles) && ! in_array($user->role, $roles)) {
            return response()->json([
                'message'        => 'Acesso não autorizado para o papel atual.',
                'required_roles' => $roles,
                'current_role'   => $user->role,
            ], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
