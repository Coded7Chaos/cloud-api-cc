<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserDeletionService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Elimina definitivamente la cuenta y sus datos personales. El único dato
     * identificativo que permanece es el nombre mostrado en la auditoría.
     */
    public function delete(User $user, User $actor): void
    {
        $name = $this->audit->name($user);
        $email = $user->email;
        $avatarPath = $user->avatar_path;

        DB::transaction(function () use ($user, $actor, $name, $email): void {
            $this->audit->record(
                'usuarios',
                'usuario_eliminado',
                "Eliminó el usuario {$name}.",
                $actor,
                $user,
            );

            $this->anonymizeAuditHistory($user->id, $name, $email);

            // Estas tablas no tienen una llave foránea directa que las elimine
            // junto con el usuario.
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            if ($email !== null) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }

            // El borrado físico activa los cascadeOnDelete/nullOnDelete de
            // horarios, tareas, dispositivos, chats y mensajes.
            $user->forceDelete();
        });

        if ($avatarPath) {
            Storage::disk('local')->delete($avatarPath);
        }
    }

    private function anonymizeAuditHistory(int $userId, string $name, ?string $email): void
    {
        AuditLog::query()
            ->where(function ($query) use ($userId): void {
                $query->where('actor_user_id', $userId)
                    ->orWhere('target_user_id', $userId);
            })
            ->each(function (AuditLog $log) use ($userId, $name, $email): void {
                $changes = [];

                if ($log->actor_user_id === $userId) {
                    $changes['actor_name'] = $name;
                    $changes['actor_email'] = null;
                }

                if ($log->target_user_id === $userId) {
                    $changes['target_name'] = $name;
                    $changes['target_email'] = null;
                    $changes['metadata'] = null;
                }

                if ($email !== null && str_contains($log->description, $email)) {
                    $changes['description'] = str_replace($email, $name, $log->description);
                }

                $log->forceFill($changes)->save();
            });
    }
}
