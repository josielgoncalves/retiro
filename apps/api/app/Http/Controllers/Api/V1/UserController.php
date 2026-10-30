<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(
            User::orderBy('name')->paginate($request->integer('per_page', 15))
        );
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        $this->auditLogger->log('user.created', $user, null, ['email' => $user->email, 'role' => $user->role], $request->user());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $before = $user->only(['name', 'email', 'role', 'active']);
        $user->update($data);

        $this->auditLogger->log('user.updated', $user, $before, $user->only(['name', 'email', 'role', 'active']), $request->user());

        return new UserResource($user);
    }
}
