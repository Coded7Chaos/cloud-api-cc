<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Command;

class CerrarChatsInactivos extends Command
{
    protected $signature = 'chats:cerrar-inactivos';

    protected $description = 'Cierra las conversaciones iniciadas hace más de 24 horas';

    public function handle(): int
    {
        $cantidad = Conversation::query()
            ->where('status', '!=', 'closed')
            ->where(function ($query): void {
                $query->where('last_message_at', '<=', now()->subHours(24))
                    ->orWhere(function ($query): void {
                        $query->whereNull('last_message_at')
                            ->where('created_at', '<=', now()->subHours(24));
                    });
            })
            ->update([
                'status' => 'closed',
                'updated_at' => now(),
            ]);

        $this->info("Se cerraron {$cantidad} conversaciones.");

        return self::SUCCESS;
    }
}
