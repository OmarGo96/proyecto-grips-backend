<?php

namespace App\Models;

use App\Helpers\GeoDistance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    protected $table = 'res_company';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public static function getCompanyByGeoLocation(int $partner_id, $origin_lat, $origin_lon) {
        $distance = 30; //distancia km
        $limitBox = GeoDistance::getLimites($origin_lat, $origin_lon, $distance);
        $parent = Company::where('partner_id', $partner_id)->where('parent_id', '!=', 1)->first();

        $partnerCompany = Company::where('partner_id', $partner_id)->first();

        if (!$parent) {
            return Company::where('partner_id', $partner_id)->first();
        }

        $geoCompany = Company::whereRaw(GeoDistance::getGeoQueryRaw($origin_lat, $origin_lon, $distance))
                      ->whereRaw(GeoDistance::getLimitQueryRaw($limitBox))
                      ->where('parent_id', $parent->parent_id)
                      ->first();
        if (!$geoCompany) {
            return $partnerCompany;
        } else {
            return $geoCompany;
        }

    }
}
