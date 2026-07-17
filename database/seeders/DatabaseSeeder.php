<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $adminRoleId = Role::where('name', 'administrador')->value('id');
        $soporteRoleId = Role::where('name', 'soporte')->value('id');

        // ── Agente principal (con el que se inicia sesión) ──────────────────
        $agent = User::firstOrCreate(
            ['email' => 'agente@cc.test'],
            [
                'name' => 'Dipali',
                'last_name' => 'Patra',
                'password' => 'password', // se castea a hashed en el modelo
                'email_verified_at' => now(),
                'role_id' => $adminRoleId,
            ],
        );

        // 2do administrador, para tener dos cuentas admin de prueba.
        User::firstOrCreate(
            ['email' => 'administrador@cc.test'],
            [
                'name' => 'Admin',
                'last_name' => 'Dos',
                'password' => 'password',
                'email_verified_at' => now(),
                'role_id' => $adminRoleId,
            ],
        );

        // Cuenta de soporte con credenciales conocidas, fácil de usar a mano.
        $support = User::firstOrCreate(
            ['email' => 'soporte@cc.test'],
            [
                'name' => 'Soporte',
                'last_name' => 'Uno',
                'password' => 'password',
                'email_verified_at' => now(),
                'role_id' => $soporteRoleId,
            ],
        );

        // Resto de agentes de soporte (datos de Faker).
        User::factory()->count(3)->create(['role_id' => $soporteRoleId]);

        // ── Contactos + conversaciones + mensajes (bandeja de chats) ────────
        $samples = [
            ['Sayali Sontakke', '5215512345678', [
                ['inbound', 'Hola, ¿tienen stock del producto?'],
                ['outbound', '¡Hola! Sí, tenemos disponibilidad. ¿Cuántas unidades necesitas?'],
                ['inbound', 'Unas 10, ¿hacen envío?'],
            ]],
            ['Rohit Agarwal', '5215598765432', [
                ['inbound', 'Buenas, quería consultar el horario de atención.'],
                ['outbound', 'Atendemos de lunes a sábado de 9 a 18 hs.'],
            ]],
            ['John Alex', '5215511223344', [
                ['inbound', 'Necesito ayuda con mi pedido #4821.'],
            ]],
            ['Roxy Dane', '5215544332211', [
                ['inbound', 'Gracias por la atención!'],
                ['outbound', '¡A ti! Cualquier cosa quedamos a la orden.'],
            ]],
            ['Mira Joshi', '5215500990011', [
                ['inbound', '¿Aceptan transferencia bancaria?'],
                ['outbound', 'Sí, te paso los datos por aquí.'],
            ]],
        ];

        foreach ($samples as $i => [$name, $wa, $messages]) {
            $contact = Contact::firstOrCreate(
                ['wa_id' => $wa],
                ['profile_name' => $name, 'phone' => '+'.$wa],
            );

            $conversation = Conversation::create([
                'contact_id' => $contact->id,
                'assigned_user_id' => $agent->id,
                'status' => 'open',
                'last_message_at' => now()->subMinutes(($i + 1) * 7),
            ]);

            $clock = now()->subMinutes(($i + 1) * 7 + count($messages) * 2);
            foreach ($messages as [$direction, $body]) {
                $clock = $clock->copy()->addMinutes(2);
                Message::create([
                    'conversation_id' => $conversation->id,
                    'wa_message_id' => 'seed-'.Str::uuid(),
                    'direction' => $direction,
                    'type' => 'text',
                    'body' => $body,
                    'status' => $direction === 'inbound' ? 'read' : 'delivered',
                    'sender_user_id' => $direction === 'outbound' ? $agent->id : null,
                    'sent_at' => $clock,
                ]);
            }

            $conversation->update(['last_message_at' => $clock]);
        }

        // ── Horario vigente del agente de soporte (Lun–Sáb 09:00–18:00) ─────
        $version = $support->scheduleVersions()->create([
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        foreach (range(1, 6) as $weekday) {
            $version->shifts()->create([
                'weekday' => $weekday,
                'start_time' => '09:00',
                'end_time' => '18:00',
            ]);
        }
    }
}
