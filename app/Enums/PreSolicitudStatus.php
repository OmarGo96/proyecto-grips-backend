<?php

namespace App\Enums;

class PreSolicitudStatus
{
    const EXPIRADA = -1;
    const CANCELADA = 0;
    const NUEVA = 1;
    const OPERADORASIGNADO = 2;
    /** @deprecated **/ const CLIENTEACEPTA = 3;
    const PAGADO = 4;
    const PAGOFALLIDO = 5;
    const PADSTATUS = 6;
}
