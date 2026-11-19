<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /** POST /api/auth/register  -> creates a CUSTOMER (role can never be chosen by the caller) */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => User::ROLE_CUSTOMER,
        ]);

        return $this->tokenResponse($user, 'Account created successfully.', 201);
    }

    /** POST /api/auth/login  -> customer login (email + password) */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', strtolower(trim($request->validated('identifier'))))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            $this->failedLogin();
        }

        if ($user->isAdmin()) {
            abort(403, 'Admins must sign in through the Admin Panel.');
        }

        return $this->tokenResponse($user, 'Logged in successfully.');
    }

    /** POST /api/auth/admin/login  -> admin login (email or admin name + password) */
    public function adminLogin(LoginRequest $request): JsonResponse
    {
        $identifier = trim($request->validated('identifier'));

        $admin = User::where('role', User::ROLE_ADMIN)
            ->where(fn ($q) => $q->where('email', strtolower($identifier))->orWhere('name', $identifier))
            ->get()
            ->first(fn (User $u) => Hash::check($request->validated('password'), $u->password));

        if (! $admin) {
            $this->failedLogin();
        }

        return $this->tokenResponse($admin, 'Admin logged in successfully.');
    }

    /** POST /api/auth/logout  -> deletes the token that was used for this request */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /** GET /api/auth/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => (new UserResource($request->user()))->resolve()]);
    }

    private function failedLogin(): never
    {
        // same message for "unknown user" and "wrong password" (does not reveal which emails exist)
        throw ValidationException::withMessages([
            'identifier' => ['The email or password is incorrect.'],
        ]);
    }

    private function tokenResponse(User $user, string $message, int $status = 200): JsonResponse
    {
        $token = $user->createToken($user->role.'-token', [$user->role]);

        return response()->json([
            'message' => $message,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('sanctum.expiration') * 60, // seconds
            'user' => (new UserResource($user))->resolve(),
        ], $status);
    }
}
