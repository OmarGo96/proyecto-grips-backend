<?php

namespace App\Helpers;

class CobroHelper
{
    public static function serviceCostQuoteOnMap($data, $totalKm, $oodo = true) {
        $param = 'amount_total';
        $cost = 0;
        if ($oodo == true) {
            for ($i = 0; $i < count($data); $i++) {

                $dataCollec = collect($data[$i]);
                if (!$dataCollec->get($param)) {
                    return false;
                    break;
                }
                $amount = $dataCollec->get($param);
                $unitOfMesure = $dataCollec->get('uom_id');

                if (isset($unitOfMesure) && isset($unitOfMesure[1]) && ($unitOfMesure[1] == 'km' || $unitOfMesure[1] == 'KM')) {
                    $total = $totalKm * $amount;
                    $cost = round($cost + $total, 2);
                } else {
                    $cost = round($cost + $amount, 2);
                }
            }
        }

        if ($cost == 0) {
            return false;
        }
        $cotizacion = self::prepareCotizacion($data, $totalKm, true);
        return (object) [
            'costo' => $cost,
            'cotizacion' => $cotizacion
        ];
    }

    public static function prepareCotizacion($calcObjs, $distanceKm, $oodo = true) {
        $items = [];
        $calculator = new \stdClass();
        $calculator->subtotal = 0;
        $calculator->tax = 0;
        $calculator->tax_amount = 0;
        $calculator->total = 0;

        if ($oodo == true) {
            for ($i = 0; $i < count($calcObjs); $i++) {
                $item = new \stdClass();
                $item->item_quantity = 1;
                $item->item_code = null;
                $item->item_description = isset($calcObjs[$i]['product_id']) && isset($calcObjs[$i]['product_id'][1]) ? $calcObjs[$i]['product_id'][1] : '';
                if (isset($calcObjs[$i]['uom_id']) && isset($calcObjs[$i]['uom_id'][1]) && ($calcObjs[$i]['uom_id'][1] == 'km' || $calcObjs[$i]['uom_id'][1] == 'KM') ) {
                    $item->item_quantity = $distanceKm;
                    $item->item_price = isset($calcObjs[$i]['price_unit']) ? round($calcObjs[$i]['price_unit'] * $distanceKm, 2) : '';
                    $calculator->subtotal = round($calculator->subtotal + ($calcObjs[$i]['price_unit'] * $distanceKm), 2);
                    $calculator->tax_amount = round($calculator->tax_amount + ($calcObjs[$i]['amount_tax'] * $distanceKm), 2);
                    $calculator->total = round($calculator->total + ($calcObjs[$i]['amount_total'] * $distanceKm), 2);
                } else {
                    $item->item_price = isset($calcObjs[$i]['price_unit']) ? $calcObjs[$i]['price_unit'] : '';
                    $calculator->subtotal = round($calculator->subtotal + ($calcObjs[$i]['price_unit']), 2);
                    $calculator->tax_amount = round($calculator->tax_amount + ($calcObjs[$i]['amount_tax']), 2);
                    $calculator->total = round($calculator->total + ($calcObjs[$i]['amount_total']), 2);
                }

                array_push($items, $item);
                $calculator->tax = 0.16;
            }
        }

        return (object) [
            'items' => $items,
            'calculator' => $calculator
        ];
    }

    public static function getCotizacionLine($data, $oodo = true) {
        $items = [];
        $calculator = new \stdClass();
        $calculator->subtotal = 0;
        $calculator->tax = 0;
        $calculator->tax_amount = 0;
        $calculator->total = 0;

        if ($oodo == true) {
            for ($i = 0; $i < count($data); $i++) {
                $item = new \stdClass();
                $item->item_quantity = $data[$i]['quantity'];
                $item->item_code = null;
                $item->item_description = isset($data[$i]['product_id']) && isset($data[$i]['product_id'][1]) ? $data[$i]['product_id'][1] : '';
                $item->item_price = isset($data[$i]['price_unit']) ? round($data[$i]['price_unit'] * $data[$i]['quantity'] , 2) : '';

                $calculator->subtotal = round($calculator->subtotal + ($data[$i]['price_subtotal']), 2);
                $calculator->tax_amount = round($calculator->tax_amount + ($data[$i]['price_tax']), 2);
                $calculator->total = round($calculator->total + ($data[$i]['price_total']) , 2);

                array_push($items, $item);
                $calculator->tax = 0.16;
            }
        }

        return (object) [
            'items' => $items,
            'calculator' => $calculator
        ];
    }
}
