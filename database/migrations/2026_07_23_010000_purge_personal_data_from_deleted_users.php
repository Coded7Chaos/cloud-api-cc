<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->each(function ($user): void {
                $name = trim($user->name.' '.$user->last_name) ?: 'Usuario eliminado';

                $this->ensureDeletionAuditExists($user->id, $name, $user->deleted_at);
                $this->anonymizeAuditHistory($user->id, $name, $user->email);

                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $user->id)
                    ->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                DB::table('users')->where('id', $user->id)->delete();

                if ($user->avatar_path ?? null) {
                    Storage::disk('local')->delete($user->avatar_path);
                }
            });
    }

    public function down(): void
    {
        // La eliminación de datos personales es intencionalmente irreversible.
    }

    private function ensureDeletionAuditExists(int $userId, string $name, string $deletedAt): void
    {
        if (! Schema::hasTable('audit_logs') || DB::table('audit_logs')
            ->where('target_user_id', $userId)
            ->where('action', 'usuario_eliminado')
            ->exists()) {
            return;
        }

        DB::table('audit_logs')->insert([
            'category' => 'usuarios',
            'action' => 'usuario_eliminado',
            'target_user_id' => $userId,
            'target_name' => $name,
            'description' => "Eliminó el usuario {$name}.",
            'occurred_at' => $deletedAt,
            'created_at' => $deletedAt,
            'updated_at' => $deletedAt,
        ]);
    }

    private function anonymizeAuditHistory(int $userId, string $name, string $email): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')
            ->where('actor_user_id', $userId)
            ->update([
                'actor_name' => $name,
                'actor_email' => null,
            ]);

        DB::table('audit_logs')
            ->where('target_user_id', $userId)
            ->update([
                'target_name' => $name,
                'target_email' => null,
                'metadata' => null,
            ]);

        DB::table('audit_logs')
            ->where(function ($query) use ($userId): void {
                $query->where('actor_user_id', $userId)
                    ->orWhere('target_user_id', $userId);
            })
            ->where('description', 'like', '%'.$email.'%')
            ->get(['id', 'description'])
            ->each(fn ($log) => DB::table('audit_logs')
                ->where('id', $log->id)
                ->update(['description' => str_replace($email, $name, $log->description)]));
    }
};
