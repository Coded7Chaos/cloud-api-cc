<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear tarea</title>
</head>
<body>
    <main>
        <h1>Crear tarea</h1>

        @if (session('success'))
            <p>{{ session('success') }}</p>
        @endif

        @if ($errors->any())
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.tareas.store') }}">
            @csrf

            <div>
                <label for="titulo">T&iacute;tulo</label>
                <input id="titulo" name="titulo" type="text" value="{{ old('titulo') }}" required>
            </div>

            <div>
                <label for="descripcion">Descripci&oacute;n</label>
                <textarea id="descripcion" name="descripcion" rows="5">{{ old('descripcion') }}</textarea>
            </div>

            <div>
                <label for="usuarios">Asignar usuarios</label>
                <select id="usuarios" name="usuarios[]" multiple required>
                    @foreach ($usuarios as $usuario)
                        <option
                            value="{{ $usuario->id }}"
                            @selected(in_array($usuario->id, old('usuarios', [])))
                        >
                            {{ trim($usuario->name . ' ' . $usuario->last_name) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit">Crear y asignar tarea</button>
        </form>
    </main>
</body>
</html>
