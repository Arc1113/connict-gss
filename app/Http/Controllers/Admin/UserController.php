<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UserAdministration;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-users');

        $filters = [
            'search' => trim($request->string('search')->toString()),
            'role' => $request->string('role')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $users = User::query()
            ->when(
                $filters['search'] !== '',
                fn ($query) => $query->where(function ($query) use ($filters): void {
                    $query
                        ->where('name', 'ilike', '%'.$filters['search'].'%')
                        ->orWhere('email', 'ilike', '%'.$filters['search'].'%');
                }),
            )
            ->when(
                $filters['role'] !== '',
                fn ($query) => $query->where('role', $filters['role']),
            )
            ->when(
                $filters['status'] === 'active',
                fn ($query) => $query->where('is_active', true),
            )
            ->when(
                $filters['status'] === 'inactive',
                fn ($query) => $query->where('is_active', false),
            )
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => $this->serialize(
                $user,
                $request->user()->id,
            ));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage-users');

        return Inertia::render('Admin/Users/Create', [
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function store(
        SaveUserRequest $request,
        UserAdministration $administration,
    ): RedirectResponse {
        $user = $administration->create(
            $request->safe()->except('password_confirmation'),
            $request->user(),
            $request->ip(),
        );

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'Operator account created.');
    }

    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('manage-users');

        return Inertia::render('Admin/Users/Edit', [
            'managedUser' => $this->serialize($user, $request->user()->id),
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function update(
        SaveUserRequest $request,
        User $user,
        UserAdministration $administration,
    ): RedirectResponse {
        $attributes = $request->safe()->except('password_confirmation');

        if (($attributes['password'] ?? null) === null) {
            unset($attributes['password']);
        }

        $administration->update(
            $user,
            $attributes,
            $request->user(),
            $request->ip(),
        );

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'Account changes saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $user, int $actorId): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => [
                'value' => $user->role->value,
                'label' => $user->role->label(),
            ],
            'is_active' => $user->is_active,
            'is_current' => $user->id === $actorId,
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_map(
            fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            UserRole::cases(),
        );
    }
}
