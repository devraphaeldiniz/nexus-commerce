<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Acesso não autenticado.'], 401);
        }

        if (! in_array($user->role, $roles)) {
            return response()->json([
                'message'       => 'Acesso negado: seu perfil não tem permissão para executar esta operação.',
                'required_roles' => $roles,
                'current_role'  => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
