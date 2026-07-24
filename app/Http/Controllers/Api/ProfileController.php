<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Perfil del usuario autenticado: cada quien edita SU cuenta. Sin permisos de
 * por medio (a diferencia de UserController, que es el ABM de administración):
 * lo único que hace falta es estar logueado, porque siempre opera sobre
 * $request->user().
 */
class ProfileController extends Controller
{
    /** Foto de perfil se guarda en el disco privado, no en el público. */
    private const AVATAR_DISK = 'local';

    private const AVATAR_DIR = 'avatars';

    public function __construct(private readonly AuditLogService $audit) {}

    /** Actualiza nombre, apellido y correo. El rol no se toca desde acá. */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // La web permite editar el correo. El móvil no muestra ese campo y
            // lo omite por completo, por eso aquí es opcional.
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $emailChanged = array_key_exists('email', $data) && $data['email'] !== $user->email;
        $user->update($data);

        $this->audit->record(
            'usuarios',
            'perfil_actualizado',
            'Actualizó los datos de su perfil.',
            $user,
            $user,
            ['fields' => array_keys($user->getChanges()), 'email_changed' => $emailChanged],
        );

        return response()->json(['user' => $user->fresh()->load('role.permissions')]);
    }

    /**
     * Cambia la contraseña. Exige la actual (para que un descuido con la sesión
     * abierta no alcance para robar la cuenta) y la nueva repetida dos veces.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Verificación manual en vez de la regla current_password: esa depende
        // del guard por defecto, y acá la petición puede venir por sesión (web)
        // o por token (móvil).
        if (! Hash::check($data['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->update(['password' => $data['password']]);

        $this->audit->record(
            'usuarios',
            'contrasena_cambiada',
            'Cambió su contraseña.',
            $user,
            $user,
        );

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    /** Sube (o reemplaza) la foto de perfil. */
    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ]);

        // Borra la anterior antes de guardar la nueva: si no, quedan archivos
        // huérfanos acumulándose en el disco.
        $this->deleteAvatarFile($user->avatar_path);

        $path = $request->file('avatar')->store(self::AVATAR_DIR, self::AVATAR_DISK);
        $user->update(['avatar_path' => $path]);

        return response()->json(['user' => $user->fresh()->load('role.permissions')]);
    }

    /** Quita la foto de perfil y vuelve a las iniciales. */
    public function destroyAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->deleteAvatarFile($user->avatar_path);
        $user->update(['avatar_path' => null]);

        return response()->json(['user' => $user->fresh()->load('role.permissions')]);
    }

    /**
     * Sirve la foto del usuario autenticado. La ruta del archivo nunca sale al
     * cliente; el <img> del SPA pega acá con la cookie de sesión.
     */
    public function avatar(Request $request): StreamedResponse
    {
        $user = $request->user();
        $path = $user->avatar_path;

        abort_unless($path && Storage::disk(self::AVATAR_DISK)->exists($path), 404);

        return Storage::disk(self::AVATAR_DISK)->response($path);
    }

    private function deleteAvatarFile(?string $path): void
    {
        if ($path && Storage::disk(self::AVATAR_DISK)->exists($path)) {
            Storage::disk(self::AVATAR_DISK)->delete($path);
        }
    }
}
