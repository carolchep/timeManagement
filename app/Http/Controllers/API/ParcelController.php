<?php

namespace App\Http\Controllers\API;

use App\Parcel;
use App\Traits\GetsDistanceInfo;
use App\Transformers\DeliveryDetailsTransformer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ParcelController extends Controller
{
    use GetsDistanceInfo;

    public function store(Request $request)
    {
        $this->validate($request, [
            'description' => 'required',
//            'note' => 'required',
            'origin_lat' => 'required',
            'origin_lng' => 'required',
            'destination_lat' => 'required',
            'destination_lng' => 'required',
            'category_id' => 'required',
            'destination_name' => 'required',
            'origin_name' => 'required'
        ], [
            'category_id.required' => 'category is required'
        ]);

        $parcel = Parcel::create($request->all());

        $origin = "$request->origin_lat, $request->origin_lng";
        $destination = "$request->destination_lat, $request->destination_lng";

        $this->setDistanceInfo($parcel, $origin, $destination);
        $this->setPrice($parcel);

        return response()->json([
            'success' => true,
            'deliveryDetails' => fractal($parcel ,new DeliveryDetailsTransformer())
        ]);
    }

    private function setPrice(Parcel $parcel)
    {
        $category = $parcel->category;

        $rate = $category->rate;

        if ((bool)$rate->is_flat_rate) {
            if ((bool) $parcel->return_to_sender) {
                $price = ($rate->rate * 2);
                $parcel->price = $price;
                $parcel->save();
            } else {
                $parcel->price = $rate->rate;
                $parcel->save();
            }
        } else {
            if ((bool) $parcel->return_to_sender) {
                $price = ($rate->rate * ($parcel->distance/1000)) * 2;
                $parcel->price = $price;
                $parcel->save();
            } else {
                $parcel->rate = $rate->rate;
                $parcel->price = $rate->rate * ($parcel->distance/1000);
                $parcel->save();
            }
        }
    }

    private function setDistanceInfo(Parcel $parcel, $origin, $destination)
    {
        $distance_info = $parcel->getDistanceInfo($origin, $destination);

        $parcel->distance = $distance_info['distance']['value'];
        $parcel->eta = $distance_info['duration']['value'];
        $parcel->save();
    }
}
