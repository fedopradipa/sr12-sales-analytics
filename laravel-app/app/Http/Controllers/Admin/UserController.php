<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => $this->userService->paginate(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => null,
            'permissions' => $this->userService->permissions(),
        ]);
    }

    public function store(
        StoreUserRequest $request
    ): RedirectResponse {
        $this->userService->create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Operator berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        abort_unless($user->isOperator(), 404);
        $user->load('permissions');

        return view('admin.users.form', [
            'user' => $user,
            'permissions' => $this->userService->permissions(),
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): RedirectResponse {
        try {
            $this->userService->update(
                actor: $request->user(),
                target: $user,
                data: $request->validated()
            );
        } catch (DomainException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Operator berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        User $user
    ): RedirectResponse {
        try {
            $this->userService->delete(
                actor: $request->user(),
                target: $user
            );
        } catch (DomainException $exception) {
            return back()->with(
                'error',
                $exception->getMessage()
            );
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Operator berhasil dihapus.');
    }
}
