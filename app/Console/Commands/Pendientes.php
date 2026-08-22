<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Servicios\Pendientes as Faltantes;
use Illuminate\Console\Command;

/**
 * Qué le falta cargar al salón, en la terminal.
 *
 * **Lo que se muestra sale de `App\Servicios\Pendientes`, que es el mismo
 * lugar del que sale el bloque del panel.** Escrito dos veces, uno de los dos
 * se queda atrás y contesta distinto — es exactamente el error que este
 * proyecto se hace a sí mismo.
 *
 * La diferencia entre los dos es a quién le habla: el panel muestra **lo que
 * esa persona puede resolver** y esto muestra **todo**, porque quien corre un
 * comando es quien instala el sistema.
 */
class Pendientes extends Command
{
    protected $signature = 'spg:pendientes';

    protected $description = 'Qué datos le faltan al salón para que el sistema funcione completo';

    public function handle(): int
    {
        $puntos = Faltantes::todo();

        $this->line('');
        $this->info('  QUÉ FALTA CARGAR');
        $this->line('  ' . str_repeat('─', 66));

        if ($puntos === []) {
            $this->line('');
            $this->info('  Nada pendiente: el salón está configurado.');
            $this->line('');

            return self::SUCCESS;
        }

        foreach ([Faltantes::IMPIDE, Faltantes::CONFUNDE, Faltantes::CONVIENE] as $nivel) {
            $delNivel = array_filter($puntos, fn ($p) => $p['nivel'] === $nivel);
            if (! $delNivel) {
                continue;
            }

            $this->line('');
            $this->line('  <options=bold>' . match ($nivel) {
                Faltantes::IMPIDE => 'IMPIDE TRABAJAR',
                Faltantes::CONFUNDE => 'HACE QUE EL SISTEMA DECIDA DISTINTO DE LO QUE ESPERÁS',
                default => 'CONVIENE',
            } . '</>');

            foreach ($delNivel as $p) {
                $this->line('   · ' . $p['que']);
                $this->line('     <fg=gray>→ ' . $p['donde'] . '</>');
            }
        }

        $this->line('');

        return self::SUCCESS;
    }
}
