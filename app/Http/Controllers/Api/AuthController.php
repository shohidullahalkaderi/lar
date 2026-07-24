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
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function register(StoreUserCollectionRequest $request): JsonResponse
{
    $validated = $request->validated();

    $email = strtolower(trim($validated['email']));

    // Safely handle optional name
    $nameInput = $validated['name'] ?? null;
    $name = !empty($nameInput) 
        ? trim($nameInput) 
        : Str::slug(explode('@', $email)[0], '_') . '_' . Str::random(8);

    $user = User::create([
        'name'       => $name,
        'email'      => $email,
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
        $credentials = $request->only(['email', 'password']);
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return response()->json([
                'detail' => 'Invalid credentials provided.'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!Auth::attempt(['email' => strtolower(trim($credentials['email'])), 'password' => $credentials['password']])) {
            return response()->json([
                'detail' => 'Invalid login credentials provided.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (isset($user->is_active) && !$user->is_active) {
            return response()->json([
                'detail' => 'Account disabled.'
            ], Response::HTTP_FORBIDDEN);
        }

        $tokenResult = $user->createToken('auth_token');

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $tokenResult->plainTextToken
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'detail' => 'Successfully logged out.'
        ], Response::HTTP_OK);
    }
}