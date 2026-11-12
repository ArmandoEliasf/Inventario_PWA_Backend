<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login de la PWA. Devuelve un token Sanctum y el rol del usuario
     * para que el frontend ajuste la interfaz.
     */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string'],
            'contrasena' => ['required', 'string'],
        ]);

        $usuario = Usuario::query()
            ->with('role')
            ->where('usuario', $datos['usuario'])
            ->first();

        if (! $usuario || ! $usuario->activo || ! Hash::check($datos['contrasena'], $usuario->getAuthPassword())) {
            throw ValidationException::withMessages([
                'usuario' => 'Credenciales inválidas.',
            ]);
        }

        $token = $usuario->createToken('pwa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'usuario' => [
                'cve_usuario' => $usuario->cve_usuario,
                'nombre' => $usuario->nombre,
                'rol' => $usuario->role?->nombre,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user()->load('role', 'persona');

        return response()->json([
            'usuario' => [
                'cve_usuario' => $usuario->cve_usuario,
                'usuario' => $usuario->usuario,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'rol' => $usuario->role?->nombre,
                'activo' => (bool) $usuario->activo,
            ],
        ]);
    }
}
