<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserCollectionRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(StoreUserCollectionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'first_name' => $validated['first_name'] ?? null,
            'last_name'  => $validated['last_name'] ?? null,
        ]);

        $tokenResult = $user->createToken('auth_token');
        
        return response()->json([
            'user'  => new UserResource($user),
            'token' => $tokenResult->plainTextToken
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        // 1. Validate email format and password input
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 2. Attempt authentication using email and password
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials provided.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 3. Issue token for authenticated user
        $user = Auth::user();
        $tokenResult = $user->createToken('auth_token');

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $tokenResult->plainTextToken
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Guard against null user
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $user->currentAccessToken();

        // Guard against missing or null token instance
        if (!$token) {
            return response()->json([
                'message' => 'Invalid or already revoked access token.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Revoke current active token
        $token->delete();

        return response()->json([
            'message' => 'Successfully logged out from active session.'
        ], Response::HTTP_OK);
    }
}