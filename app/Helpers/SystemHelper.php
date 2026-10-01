<?php

namespace App\Helpers;

class SystemHelper
{


    // Sube el límite de tiempo de PHP para el pedido en curso, en segundos.
    public function setTimeLimit(int $timeLimitInSeconds): void
    {
        // Las dos llamadas, como en el SystemHelper de Clienty, que es igual: set_time_limit() reinicia el contador
        // con el límite nuevo e ini_set() deja max_execution_time con el mismo valor.
        set_time_limit($timeLimitInSeconds);
        ini_set('max_execution_time', (string) $timeLimitInSeconds);
    }

}
