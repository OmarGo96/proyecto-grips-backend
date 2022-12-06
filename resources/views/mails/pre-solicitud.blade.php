<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="initial-scale=1">
    <title>Solicitud de Servicio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@0,100;0,300;0,400;0,500;0,700;0,900;1,400;1,500;1,700;1,900&display=swap"
        rel="stylesheet">

    <style>
        th {
            text-align: left;
        }

        td {
            padding-bottom: 12px;
        }

        legend {
            font-size: 1.5em;
            font-weight: 500;
            text-align: center;
        }

        table {
            font-size: 12px;
        }

        @media (min-width: 768px) {
            table {
                font-size: 12px;
            }
        }
    </style>
</head>

<body style="font-family: 'Roboto', sans-serif;">
    <header>
        <div style="text-align: center; margin-bottom: 14px;">
            <img class="logo" src="{{ config('app.base_domain') }}/web/binary/company_logo" alt="{{ config('app.name') }} Logo"/>
        </div>
    </header>
    <div style="width: 100%; word-break: break-word;">
        <fieldset>
            <legend>Solicitud de Servicio</legend>
            <br>
            <div class="row">
                <table border="0" style="width:100%;">
                    <tbody>
                        <tr>
                            <th>
                                <div>Folio</div>
                            </th>
                            <th>
                                <div>Solicitó</div>
                            </th>
                            <th>
                                <div>Tipo de Servicio</div>
                            </th>
                            <th>
                                <div>Fecha de Solicitud</div>
                            </th>
                        </tr>
                        <tr>
                            <td>
                                <div>{{ $solicitud->name }}</div>
                            </td>
                            <td>
                                <div>{{ $solicitud->solicito }}</div>
                            </td>
                            <td>
                                <div>{{ $solicitud->tiposervicio_id[1] }}</div>
                            </td>
                            <td>
                                <div>{{ $solicitud->fecha }}</div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <div>Cliente</div>
                            </th>
                            <th>
                                <div>Teléfono</div>
                            </th>
                            <th>
                                <div>Tipo de Pago</div>
                            </th>
                            <th>
                                <div>Fecha y Hora de Inicio</div>
                            </th>
                        </tr>
                        <tr>
                            <td>
                                <div>{{ $solicitud->partner_id[1] }}</div>
                            </td>
                            <td>
                                <div>{{ $solicitud->telefono }}</div>
                            </td>
                            <td>
                                <div>{{ $solicitud->tipopago_id[1] }}</div>
                            </td>
                            <td>
                                <div>{{ $solicitud->horainicio_tz }}</div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <div>Asegurado</div>
                            </th>
                            <th>
                                <div>No. de Expediente</div>
                            </th>
                            <th>
                                <div>Tiempo de Arribo</div>
                            </th>
                        </tr>
                        <tr>
                            <td>
                                @if(isset($solicitud->asegurado) && $solicitud->asegurado !== false)
                                <div>{{ $solicitud->asegurado }}</div>
                                @else
                                <div>--</div>
                                @endif
                            </td>
                            <td>
                                @if(isset($solicitud->noexpediente) && $solicitud->noexpediente !== false)
                                <div>{{ $solicitud->noexpediente }}</div>
                                @else
                                <div>--</div>
                                @endif
                            </td>
                            <td>
                                @if(isset($solicitud->tmestimadoarribo) && $solicitud->tmestimadoarribo !== false)
                                <div>{{ $solicitud->tmestimadoarribo }}</div>
                                @else
                                <div>--</div>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div><br>

            <div class="row">
                <div style="font-size:14px">
                    <strong><span>Costo</span></strong>
                    <span data-oe-type="monetary" data-oe-expression="o.amount_total"
                        style="font-size:14px">$&nbsp;<span
                            class="oe_currency_value">{{ number_format($solicitud->amount_total, 2) }}</span></span><br>
                    <span style="font-size:14px">{{ $solicitud->cantLetra }}</span><br>
                </div>
            </div>
        </fieldset> <!-- solicitud de servicio -->

        <br>
        <br>

        <fieldset>
            <legend>Datos del Vehículo</legend>
            <br>
            <table width="100%">
                <tbody>
                    <tr>
                        <th><strong>Marca</strong></th>
                        <th><strong>Tipo</strong></th>
                    </tr>
                    <tr style="border-bottom: 0.5px solid #DCDCDC;">
                        <td class="text-left"><span>{{ $solicitud->marca_id[1] }}</span></td>
                        <td class="text-left"><span>{{ $solicitud->tipovehiculo_id[1] }} </span></td>
                    </tr>
                    <tr>
                        <th><strong>Clase</strong></th>
                        <th><strong>Color</strong></th>
                    </tr>
                    <tr>
                        <td class="text-left"><span>{{ $solicitud->clase_id[1] }}</span></td>
                        <td class="text-left"><span>{{ $solicitud->colorvehiculo_id[1] }}</span></td>
                    </tr>
                    <tr>
                        <th><strong>Año</strong></th>
                        <th><strong>Placas</strong></th>
                    </tr>
                    <tr>
                        @if(isset($solicitud->anio) && $solicitud->anio !== false)
                        <td class="text-left"><span>{{ $solicitud->anio }}</span></td>
                        @else
                        <td class="text-left">--</td>
                        @endif

                        @if(isset($solicitud->placas) && $solicitud->placas !== false)
                        <td class="text-left"><span>{{ $solicitud->placas }}</span></td>
                        @else
                        <td class="text-left">--</td>
                        @endif
                    </tr>
                    <tr>
                        <th><strong>No. Serie</strong></th>
                    </tr>
                    <tr>
                        @if(isset($solicitud->noserie) && $solicitud->noserie != false)
                        <td class="text-left"><span>{{$solicitud->noserie}}</span></td>
                        @else
                        <td class="text-left"><span>--</span></td>
                        @endif
                    </tr>
                </tbody>
            </table>
        </fieldset> <!-- datos del vehículo -->

        <br>
        <br>

        <fieldset>
            <legend>Servicios</legend>
            <br>
            <div class="row">
                <table style="word-break: normal; width: 100%;">
                    <tbody>
                        <tr>
                            <th><strong>Cant.</strong></th>
                            <th><p>Concepto</p></th>
                        </tr>
                        <tr>
                            <td style="font-size:12px"><span>1.000</span></td>
                            <td style="font-size:12px"><span>SERVICIO DE GRUA</span></td>
                        </tr>

                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="text-align: right;">
                            </td>
                            <td style="text-align: right;">
                            </td>
                            <td colspan="8">
                                <table style="width:100%">
                                    <tbody>
                                        <tr>
                                            <td style="padding: 0;"> <strong style="font-size:14px">P.U.</strong></td>
                                            <td style="text-align: right; padding: 0;"> <span style="font-size:14px;">$&nbsp;<span class="oe_currency_value">{{ number_format($solicitud->amount_untaxed, 2) }}</span></span></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0;"> <strong style="font-size:14px">IVA</strong></td>
                                            <td style="text-align: right; padding: 0;"> <span style="font-size:14px;">$&nbsp;<span class="oe_currency_value">{{ number_format($solicitud->amount_tax, 2) }}</span></span></td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0;"> <strong style="font-size:14px">Sub-total:</strong></td>
                                            <td style="text-align: right; padding: 0;"> <span style="font-size:14px;">$&nbsp;<span class="oe_currency_value">{{ number_format($solicitud->amount_total, 2) }}</span></span></td>
                                        </tr>

                                        <tr>
                                            <td style="padding: 0;"><strong
                                                    style="font-size:14px">Total:
                                                </strong></td>
                                            <td style="text-align: right; padding: 0;"><span
                                                    style="font-size:14px; ">$&nbsp;<span
                                                        class="oe_currency_value">{{ number_format($solicitud->amount_total, 2) }}</span></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </fieldset> <!-- servicios relalizados -->
    </div>
</body>

</html>
