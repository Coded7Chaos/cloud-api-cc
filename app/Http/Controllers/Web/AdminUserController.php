<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')], 'password' => ['required', 'confirmed', Password::defaults()], 'is_admin' => ['nullable', 'boolean'],
        ]);
        $data['is_admin'] = $request->boolean('is_admin');
        $data['email_verified_at'] = now();
        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)], 'password' => ['nullable', 'confirmed', Password::defaults()], 'is_admin' => ['nullable', 'boolean'],
        ]);
        $data['is_admin'] = $request->boolean('is_admin');
        if (blank($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'No puedes eliminar tu cuenta.');
        $user->delete();

        return back()->with('success', 'Usuario eliminado.');
    }
}
