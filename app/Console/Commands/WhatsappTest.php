<?php

namespace App\Console\Commands;

use App\Services\WhatsappService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

/**
 * Prueba de envío por la WhatsApp Cloud API.
 *
 *   php artisan whatsapp:test 59173059904
 *
 * Envía la plantilla de prueba (con sus 3 parámetros de body) y muestra la
 * respuesta de Meta o el error exacto si falla.
 */
class WhatsappTest extends Command
{
    protected $signature = 'whatsapp:test
        {to : Número destino en formato internacional sin + (ej. 59173059904)}
        {--template=jaspers_market_order_confirmation_v1 : Nombre de la plantilla}
        {--lang=en_US : Código de idioma de la plantilla}';

    protected $description = 'Envía una plantilla de prueba por la WhatsApp Cloud API';

    public function handle(WhatsappService $whatsapp): int
    {
        $to = (string) $this->argument('to');
        $template = (string) $this->option('template');

        // Parámetros del body de jaspers_market_order_confirmation_v1: {{1}} {{2}} {{3}}.
        $components = [[
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'John Doe'],
                ['type' => 'text', 'text' => '123456'],
                ['type' => 'text', 'text' => 'Jun 30, 2026'],
            ],
        ]];

        $this->info("Enviando plantilla '{$template}' a {$to}…");

        try {
            $response = $whatsapp->sendTemplate($to, $template, (string) $this->option('lang'), $components);

            $this->newLine();
            $this->info('✅ Enviado. Respuesta de Meta:');
            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $wamid = data_get($response, 'messages.0.id');
            if ($wamid) {
                $this->newLine();
                $this->line("wamid: <fg=green>{$wamid}</>");
            }

            return self::SUCCESS;
        } catch (RequestException $e) {
            $this->newLine();
            $this->error('❌ Meta rechazó la petición (HTTP '.$e->response->status().'):');
            $this->line($e->response->body());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('❌ Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
