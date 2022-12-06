<?php

namespace App\Enums;

class SolicitudStatus
{
    const RESERVADA = 'reserved';
    const BORRADOR = 'draft';
    const ARRIBADA = 'arrived';
    const ABIERTA = 'open';
    const PAGADA = 'paid';
    const VALIDARPAGO = 'paidlocked';
    const PAIDVALID = 'paidvalid';
    const ENCIERRO = 'locked';
    const CANCELADA = 'cancel';
    const CERRADA = 'closed';
    const FACTURADA = 'invoiced';
    const CUENTAXCOBRAR = 'receivable';
    const LIBERADA = 'relased';
    const SOLLAMADA = 'call_request';
    const SOLCOTIZACION = 'quot_request';
    const ONTRANSIT = 'on_transit';
}
