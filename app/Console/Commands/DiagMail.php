<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class DiagMail extends Command
{
    protected $signature = 'diag:mail {to : Correo de destino de la prueba}';

    protected $description = 'Envía un correo de prueba para revisar entregabilidad (SPF/DKIM/DMARC)';

    public function handle(): int
    {
        $to = $this->argument('to');
        $stamp = now()->format('Y-m-d H:i:s');

        try {
            Mail::raw(
                "Prueba de entregabilidad enviada el {$stamp}.\n\n"
                ."Revisa que llegue a la bandeja principal y, en Gmail, usa «Mostrar original» "
                ."para confirmar SPF, DKIM y DMARC en PASS.",
                function ($message) use ($to, $stamp) {
                    $message->to($to)->subject("Prueba reservas {$stamp}");
                }
            );
        } catch (\Throwable $e) {
            $this->error('Falló el envío: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Correo enviado a {$to}.");

        return self::SUCCESS;
    }
}
