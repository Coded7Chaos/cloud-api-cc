<?php

namespace App\Http\Controllers;

use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TareaController extends Controller
{
    public function create(): View
    {
        $usuarios = User::query()->orderBy('name')->orderBy('last_name')->get();

        return view('admin.tareas.create', compact('usuarios'));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'usuarios' => ['required', 'array', 'min:1'],
            'usuarios.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($datos): void {
            $tarea = Tarea::create([
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'] ?? null,
            ]);

            $tarea->usuarios()->attach($datos['usuarios']);
        });

        return to_route('admin.tareas.create')
            ->with('success', 'Tarea creada y asignada correctamente.');
    }
}
