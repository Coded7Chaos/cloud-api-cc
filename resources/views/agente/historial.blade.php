<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de atenci&oacute;n</title>
</head>
<body>
    <main>
        <h1>Historial de atenci&oacute;n</h1>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Contacto</th>
                    <th>Estado</th>
                    <th>Fecha de atenci&oacute;n</th>
                    <th>Env&iacute;o de mensajes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($conversations as $conversation)
                    <tr>
                        <td>{{ $conversation->id }}</td>
                        <td>
                            {{ $conversation->contact->profile_name
                                ?: $conversation->contact->phone
                                ?: $conversation->contact->wa_id }}
                        </td>
                        <td>{{ $conversation->status }}</td>
                        <td>{{ $conversation->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            @if ($conversation->status === 'closed')
                                Mensajes bloqueados
                            @else
                                Mensajes habilitados
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if ($conversations->isEmpty())
                    <tr>
                        <td colspan="5">No tienes conversaciones atendidas.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </main>
</body>
</html>
