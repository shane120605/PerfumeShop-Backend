<?php


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /** GET /api/admin/users?search=&role=customer|admin&per_page= */
    public function index(Request $request)
    {
        $request->validate([
            'role' => ['nullable', 'in:admin,customer,Admin,Customer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->orderBy('id');

        if ($role = strtolower((string) $request->query('role', ''))) {
            $query->where('role', $role);
        }
        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        return UserResource::collection($query->paginate((int) $request->query('per_page', 20))->withQueryString());
    }

    /** GET /api/admin/users/{user} */
    public function show(User $user)
    {
        return new UserResource($user);
    }

    /** POST /api/admin/users */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json([
            'message' => 'User created.',
            'data' => (new UserResource($user))->resolve(),
        ], 201);
    }

    /** PUT/PATCH /api/admin/users/{user}   (leave password empty to keep the old one) */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $me = $request->user();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $losesAdmin = isset($data['role']) && $data['role'] !== User::ROLE_ADMIN && $user->isAdmin();
        if ($losesAdmin && ($user->id === $me->id || $this->isLastAdmin($user))) {
            throw ValidationException::withMessages([
                'role' => ['You cannot remove the admin role from this account.'],
            ]);
        }

        $user->update($data);

        // a changed password logs that user out everywhere (but not the admin editing their own account)
        if (isset($data['password']) && $user->id !== $me->id) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'User updated.',
            'data' => (new UserResource($user->fresh()))->resolve(),
        ]);
    }

    /** DELETE /api/admin/users/{user} */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages(['user' => ['You cannot delete the account you are signed in with.']]);
        }
        if ($user->isAdmin() && $this->isLastAdmin($user)) {
            throw ValidationException::withMessages(['user' => ['The last admin account cannot be deleted.']]);
        }

        $user->tokens()->delete();
        $user->delete(); // their past orders stay (user_id becomes null)

        return response()->json(['message' => 'User deleted.']);
    }

    private function isLastAdmin(User $user): bool
    {
        return User::where('role', User::ROLE_ADMIN)->where('id', '!=', $user->id)->doesntExist();
    }
}
