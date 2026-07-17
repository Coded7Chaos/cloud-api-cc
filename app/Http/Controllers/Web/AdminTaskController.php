<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminTaskController extends Controller
{
    public function create(): View
    {
        return view('admin.tasks.create', ['users' => User::where('is_admin', false)->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['titulo' => ['required', 'string', 'max:255'], 'descripcion' => ['nullable', 'string'], 'usuarios' => ['required', 'array', 'min:1'], 'usuarios.*' => ['integer', 'distinct', 'exists:users,id']]);
        DB::transaction(function () use ($data): void {
            $task = Tarea::create(['titulo' => $data['titulo'], 'descripcion' => $data['descripcion'] ?? null]);
            $task->usuarios()->attach($data['usuarios']);
        });

        return back()->with('success', 'Tarea creada y asignada.');
    }
}
