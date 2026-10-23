<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TokenController extends Controller
{
    public function store(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate(['email' => 'required|string|email|max:254', 'password' => 'required|string|max:72', 'nombre_dispositivo' => 'required|string|max:60']);
        $user = User::where('email', $data['email'])->first();
        // Hasher también se ejecuta para correos desconocidos: respuesta genérica.
        $hash = $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (! Hash::check($data['password'], $hash) || ! $user || strlen($data['password']) > 72) {
            abort(401);
        }
        $expires = now()->addHours(8);
        $token = $user->createToken($data['nombre_dispositivo'], ['finanzas'], $expires);

        return response()->json(['data' => ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $expires->toISOString()]], 201)
            ->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => null, 'message' => 'Token revocado.']);
    }
}
