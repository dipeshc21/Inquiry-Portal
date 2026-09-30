<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {
    }

    public function index(PaginationRequest $request): ApiResponse
    {
        $this->authorize('viewAny', User::class);

        $data = $request->validated();
        $query = User::query()->with('team');

        if (! empty($data['q'])) {
            $search = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $data['q']
            );

            $query->where(function ($query) use ($search): void {
                $query->whereRaw(
                    "name LIKE ? ESCAPE '!'",
                    ['%'.$search.'%']
                )->orWhereRaw(
                    "email LIKE ? ESCAPE '!'",
                    ['%'.$search.'%']
                );
            });
        }

        $users = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($data['per_page'] ?? 15);

        return ApiResponse::success(
            UserResource::collection($users->getCollection()),
            'Users retrieved successfully.',
            [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ]
        );
    }

    public function assignable(): ApiResponse
    {
        $this->authorize('assignable', User::class);

        return ApiResponse::success(
            UserResource::collection(
                User::query()
                    ->assignable()
                    ->with('team')
                    ->orderBy('name')
                    ->orderBy('id')
                    ->get()
            ),
            'Assignable agents retrieved successfully.'
        );
    }

    public function show(User $user): ApiResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success(
            new UserResource($user->load('team')),
            'User retrieved successfully.'
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = DB::transaction(function () use ($request): User {
            $user = User::query()->create(
                Arr::except($request->validated(), ['password_confirmation'])
            );

            $this->activityLogger->log(
                action: 'user_created',
                description: 'Staff account created for '.$user->name.'.',
                actor: $request->user(),
                newValues: $user->only([
                    'id',
                    'name',
                    'email',
                    'role',
                    'team_id',
                    'is_active',
                ])
            );

            return $user;
        });

        return ApiResponse::success(
            new UserResource($user->load('team')),
            'User created successfully.'
        )->response()->setStatusCode(201);
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): ApiResponse {
        $this->authorize('update', $user);

        $user = DB::transaction(function () use ($request, $user): User {
            $this->lockAdministration();

            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $data = Arr::except(
                $request->validated(),
                ['password_confirmation']
            );

            $nextRole = $data['role'] ?? $user->role;
            $nextActive = $data['is_active'] ?? $user->is_active;

            // Prevent an administrator from locking themselves out.
            abort_if(
                $user->id === $request->user()->id
                    && ($nextRole !== 'admin' || ! $nextActive),
                409
            );

            if (
                $user->isAdmin()
                && $user->is_active
                && ($nextRole !== 'admin' || ! $nextActive)
            ) {
                abort_unless(
                    User::query()
                        ->active()
                        ->where('role', 'admin')
                        ->whereKeyNot($user->id)
                        ->exists(),
                    409
                );
            }

            $before = $user->only([
                'name',
                'email',
                'role',
                'team_id',
                'is_active',
            ]);

            $user->fill($data);

            $revokeTokens = $user->isDirty([
                'password',
                'email',
                'role',
                'is_active',
            ]);

            if ($user->isDirty()) {
                $passwordChanged = $user->isDirty('password');
                $user->save();

                if ($revokeTokens) {
                    $user->tokens()->delete();
                }

                $this->activityLogger->log(
                    action: 'user_updated',
                    description: 'Staff account updated for '.$user->name.'.',
                    actor: $request->user(),
                    oldValues: $before,
                    newValues: [
                        ...$user->only(array_keys($before)),
                        'password_changed' => $passwordChanged,
                    ]
                );
            }

            return $user;
        });

        return ApiResponse::success(
            new UserResource($user->load('team')),
            'User updated successfully.'
        );
    }

    public function destroy(
        \Illuminate\Http\Request $request,
        User $user
    ): ApiResponse {
        $this->authorize('delete', $user);

        DB::transaction(function () use ($request, $user): void {
            $this->lockAdministration();

            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->isAdmin() && $user->is_active) {
                abort_unless(
                    User::query()
                        ->active()
                        ->where('role', 'admin')
                        ->whereKeyNot($user->id)
                        ->exists(),
                    409
                );
            }

            // Preserve required authorship foreign keys. Such accounts can
            // be deactivated through the update endpoint instead.
            abort_if(
                $user->notes()->exists() || $user->reminders()->exists(),
                409
            );

            foreach ($user->assignedInquiries()->get() as $inquiry) {
                $inquiry->assigned_to = null;
                $inquiry->save();

                $this->activityLogger->log(
                    action: 'assigned',
                    description: 'Assignment removed because the staff '
                        .'account was deleted.',
                    inquiry: $inquiry,
                    actor: $request->user(),
                    oldValues: ['assigned_to' => $user->id],
                    newValues: ['assigned_to' => null]
                );
            }

            $this->activityLogger->log(
                action: 'user_deleted',
                description: 'Staff account deleted: '.$user->name.'.',
                actor: $request->user(),
                oldValues: $user->only(['id', 'name', 'email', 'role'])
            );

            $user->tokens()->delete();
            $user->delete();
        });

        return ApiResponse::success(null, 'User deleted successfully.');
    }

    private function lockAdministration(): void
    {
        DB::table('assignment_locks')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();
    }
}
