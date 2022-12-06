<?php

namespace App\Helpers;

use App\Enums\JsonResponse;
use Illuminate\Support\Facades\Http;

class GeoDistance
{
    public static function getLimites($lat, $lon, $distance = 1, $earthRadius = 6371) {
        $return = array();

        // Los angulos para cada dirección
        $cardinalCoords = array('north' => '0',
                                'south' => '180',
                                'east' => '90',
                                'west' => '270');

        $rLat = deg2rad($lat);
        $rLng = deg2rad($lon);
        $rAngDist = $distance/$earthRadius;

        foreach ($cardinalCoords as $name => $angle)
        {
            $rAngle = deg2rad($angle);
            $rLatB = asin(sin($rLat) * cos($rAngDist) + cos($rLat) * sin($rAngDist) * cos($rAngle));
            $rLonB = $rLng + atan2(sin($rAngle) * sin($rAngDist) * cos($rLat), cos($rAngDist) - sin($rLat) * sin($rLatB));

             $return[$name] = array('lat' => (float) rad2deg($rLatB),
                                    'lng' => (float) rad2deg($rLonB));
        }

        return (object) array('min_lat'  => $return['south']['lat'],
                     'max_lat' => $return['north']['lat'],
                     'min_lng' => $return['west']['lng'],
                     'max_lng' => $return['east']['lng']);
    }

    public static function getGeoQueryRaw($lat, $lon, $distance) {
        return '(6371 * ACOS(SIN(RADIANS(lat)) * SIN(RADIANS(' . $lat . ')) + COS(RADIANS(lon - ' . $lon . ')) * COS(RADIANS(lat)) * COS(RADIANS(' . $lat . ')))) < '.$distance;
    }

    public static function getLimitQueryRaw($limitBox) {
        return '(lat BETWEEN ' . $limitBox->min_lat. ' AND ' . $limitBox->max_lat . ')
        AND (lon BETWEEN ' . $limitBox->min_lng. ' AND ' . $limitBox->max_lng.')';
    }

    public static function getDirectionsGoogleMaps(array $origin, array $destination, string $mode) {
        $url = config('app.directions-api');
        $googleApiKey = config('app.google-key');
        $response = Http::get($url, [
            'origin' => $origin['lat'].",".$origin['long'],
            'destination' => $destination['lat'].",".$destination['long'],
            'mode' => $mode,
            'key' => $googleApiKey
        ]);
        if ($response->status() == JsonResponse::OK) {
            return (object) ['ok' => true, 'data' => $response->object()];
        } else {
            return (object) ['ok' => false, 'errors' => $response->object()];
        }
    }
}
