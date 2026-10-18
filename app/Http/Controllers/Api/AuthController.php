<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Hash of a random string, built once with the configured cost. Used only to equalise timing. */
    private static ?string $dummyHash = null;

    public function login(LoginRequest $request)
    {
        $admin = Admin::where('phone', $request->phone)->first();

        // Always run one Hash::check, even for an unknown phone number,
        // so an attacker can't tell "no such account" from "wrong password" by timing.
        $hash = $admin?->password ?? (self::$dummyHash ??= Hash::make(Str::random(32)));
        $valid = Hash::check($request->password, $hash);

        if (! $admin || ! $valid) {
            return response()->json(['message' => 'Téléphone ou mot de passe incorrect.'], 401);
        }

        if (! $admin->active) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        return response()->json([
            'accessToken' => $admin->createToken('admin')->plainTextToken,
            'user' => new UserResource($admin),
        ]);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}