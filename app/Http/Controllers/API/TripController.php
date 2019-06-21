<?php

namespace App\Http\Controllers\API;

use AL\Controllers\InvoiceController;
use App\Transformers\InvoiceTransformer;
use App\Transformers\PaymentTransformer;
use App\Transformers\TripTransformer;
use App\Transformers\TripUpdatesTransformer;
use App\TripRequest;
use App\Parcel;
use App\Traits\SendsFirebaseNotifications;
use App\Transformers\PartnerTransformer;
use App\Transformers\UserTransformer;
use App\User;
use http\Env\Response;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TripController extends Controller
{
    use SendsFirebaseNotifications;

    public function index(Request $request)
    {
        $user = $request->user();
        $model = new TripRequest();
        $query = $model->newQuery();

        if ($user->type == config('constants.user_types.customer')) {
            $query->where('customer_id', $user->id);
        } elseif($user->type == config('constants.user_types.partner')) {
            $query->where('partner_id', $user->id);
        }

        $trips = $query->where('status', $request->status)
            ->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => true,
            'tripRequests' => fractal($trips, new TripTransformer())
        ]);
    }

    private function getAvailableDriver(Request $request)
    {
        $model = new User();

        $query = $model->ofType(config('constants.user_types.partner'))->newQuery();
        $available_driver = $query->where('lat', '!=', null)
            ->where('lng', '!=', null)
            ->where('last_seen_on', null)//if it is null then driver is online.
            ->distance($request->origin_lat, $request->origin_lng)
            ->orderBy('distance', 'asc')
            ->get()
            ->filter(function($driver, $key) {
                return $driver->is_available;
            })->first();

        if (!(bool) $available_driver) {
            return $available_driver;
        }

        $user = $request->user();

        $this->sendNotification([$available_driver->firebase_token], [
            'title' => 'Trip',
            'body' => "{$user->name} has requested a courier"
        ], 'request', null);

        return $available_driver;

    }

    public function storeRequest(Request $request, Parcel $parcel)
    {
        $destination_coordinates = "$request->origin_lat,$request->origin_lng";

        if ((bool) $parcel->tripRequest) {
            return response()->json([
                'success' => false,
                'tripRequest' => fractal($parcel->tripRequest, new TripTransformer()),
                'availableDriver' => fractal($parcel->tripRequest->driver, new PartnerTransformer($destination_coordinates)),
                'message' => 'a trip request has been made for this parcel'
            ]);
        }

        $available_driver = $this->getAvailableDriver($request);


        if (!(bool) $available_driver) {
            return  response()->json([
                'success' => false,
                'message' => 'no available driver right now'
            ]);
        }

        $this->validate($request, [
            'scheduled_for' => 'required|in:later,now',
            'payment_method' => 'required|in:'.implode(',', config('constants.payment_methods'))
        ]);

        $user = $request->user();

        $trip_request = $user->customerTrips()->create([
            'parcel_id' => $parcel->id,
            'payment_method' => $request->payment_method,
            'partner_id' => $available_driver->id,
            'status' => config('constants.request_statuses.acceptance_pending')
        ]);

        if ($request->scheduled_for == 'later') {
            $trip_request->scheduled_for = $request->scheduled_for;
            $trip_request->schedule_date_time = $request->schedule_date_time;
            $trip_request->save();

            return response()->json([
                'success' => true,
                'tripRequest' => fractal($trip_request, new TripTransformer()),
            ]);
        }

        $this->sendNotification(
            [$available_driver->firebase_token],
            ['title' => 'BikeIt Trip', 'body' => 'New trip requested'],
            'trip',
            ['customer' => $user]
        );

        return response()->json([
            'success' => true,
            'tripRequest' => fractal($trip_request, new TripTransformer()),
            'availableDriver' => fractal($available_driver, new PartnerTransformer($destination_coordinates))
        ]);
    }

    public function acceptRequest(Request $request, TripRequest $trip_request)
    {
        if(!(bool) $trip_request->id) {
            return response()->json([
                'success' => false,
                'message' => 'no trip request for you'
            ]);
        }

        $user = $request->user();
        if ($trip_request->partner_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => "unauthorized request"
            ]);
        }

        if ($trip_request->accepted_on != null) {
            return response()->json([
                'success' => false,
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'message' => "trip has already been accepted"
            ]);
        }
        elseif ($trip_request->cancelled_on != null) {
            return response()->json([
                'success' => false,
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'message' => "trip has already been cancelled"
            ]);
        } elseif ($trip_request->started_on != null) {
            return response()->json([
                'success' => false,
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'message' => "trip has already been started"
            ]);
        } elseif ($trip_request->completed_on != null) {
            return response()->json([
                'success' => false,
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'message' => "trip has already been completed",
            ]);
        }

        $trip_request->accepted_on = now(config('app.timezone'));
        $trip_request->status = config('constants.request_statuses.accepted');

        $trip_request->save();

        InvoiceController::generateInvoice($trip_request);

        if ($trip_request->payment_method == config('constants.payment_methods.cash_on_delivery') || $trip_request->payment_method == config('constants.payment_methods.cash')) {
            $parcel = $trip_request->parcel;
            $trip_request->payment()->create([
                'client_id' => $trip_request->customer_id,
                'partner_id' => $trip_request->partner_id,
                'invoice_id' => $trip_request->invoice->id,
                'amount' => $parcel->price,
                'payment_method' => $trip_request->payment_method,
                'reference' => 'CSH-'.time(),
                'status' => config('constants.payment_status.pending')
            ]);
        }

        return response()->json([
            'success' => true,
            'tripRequest' => fractal($trip_request, new TripTransformer()),
            'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
        ]);
    }

    public function cancelRequest(Request $request, TripRequest $trip_request)
    {
        if(!(bool) $trip_request->id) {
            return response()->json([
                'success' => false,
                'message' => 'no trip request for you'
            ]);
        }

        $invoice = $trip_request->invoice;
        if ((bool) $invoice) {
            $invoice->status = config('constants.invoice_status.canceled');
            $invoice->save();
        }
        if ($trip_request->completed_on != null) {
            return response()->json([
                'success' =>  false,
                'message' => "trip has already been completed",
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
            ]);
        }

        if ($trip_request->started_on != null) {
            return response()->json([
                'success' =>  false,
                'message' => "trip has already been started",
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
            ]);
        }

        $user = $request->user();

        $trip_request->cancelled_on = now(config('app.timezone'));

        $trip_request->cancelled_by = $user->type;
        $status = config('constants.request_statuses.cancelled_by_customer');
        if ($user->type == config('constants.user_types.partner')) {
            $status = config('constants.request_statuses.cancelled_by_partner');
        }
        $trip_request->status = $status;

        $trip_request->save();

        $this->sendNotification([$trip_request->customer->firebase_token],
            ['title' => 'BikeIt Trip', 'body' => 'Trip cancelled'], 'trip', '');

        return response()->json([
            'success' => true,
            'tripRequest' => fractal($trip_request, new TripTransformer()),
            'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
        ]);
    }

    public function showTrip(TripRequest $trip_request)
    {
        if(!(bool) $trip_request->id) {
            return response()->json([
                'success' => false,
                'message' => 'no trip request for you'
            ]);
        }

        return response()->json([
            'success' => true,
            'tripRequest' => fractal($trip_request, new TripTransformer()),
            'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
        ]);
    }

    public function endTrip(TripRequest $trip_request)
    {
        if(!(bool) $trip_request->id) {
            return response()->json([
                'success' => false,
                'message' => 'no trip request for you'
            ]);
        }
        if ($trip_request->cancelled_on != null) {
            return response()->json([
                'success' => false,
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'message' => "trip has already been cancelled",
            ]);
        }  elseif ($trip_request->completed_on != null) {
            return response()->json([
                'success' => false,
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'message' => "trip has already been completed",
            ]);
        }

        $trip_request->completed_on = now(config('app.timezone'));

        $trip_request->status = config('constants.request_statuses.completed');

        $trip_request->save();

        $this->sendNotification(
            [$trip_request->customer->firebase_token],
            ['title' => 'BikeIt Trip', 'body' => 'Delivery trip has been started'],
            'trip',
            ''
        );

        return response()->json([
            'success' => true,
            'tripRequest' => fractal($trip_request, new TripTransformer()),
            'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
        ]);
    }

    public function startTrip(TripRequest $trip_request)
    {
        if(!(bool) $trip_request->id) {
            return response()->json([
                'success' => false,
                'message' => 'no trip request for you'
            ]);
        }

        if ($trip_request->cancelled_on != null) {
            return response()->json([
                'success' => false,
                'message' => "trip has already been cancelled",
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
            ]);
        } elseif ($trip_request->completed_on != null) {
            return response()->json([
                'success' => false,
                'message' => "trip has already been completed",
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
            ]);
        }

        $trip_request->started_on = now(config('app.timezone'));
        $trip_request->status = config('constants.request_statuses.active');
        $trip_request->save();

        $this->sendNotification(
            [$trip_request->customer->firebase_token],
            ['title' => 'BikeIt Trip', 'body' => 'Delivery trip has been started'],
            'trip',
            ''
        );

        return response()->json([
            'success' => true,
            'tripRequest' => fractal($trip_request, new TripTransformer()),
            'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
        ]);
    }

    public function setArrivedStatus(TripRequest $trip_request)
    {
        if(!(bool) $trip_request->id) {
            return response()->json([
                'success' => false,
                'message' => 'no trip request for you'
            ]);
        }

        if ($trip_request->cancelled_on != null) {
            return response()->json([
                'success' => false,
                'message' => "trip has already been cancelled",
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
            ]);
        }  elseif ($trip_request->completed_on != null) {
            return response()->json([
                'success' => false,
                'message' => "trip has already been completed",
                'tripRequest' => fractal($trip_request, new TripTransformer()),
                'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
            ]);
        }

        $trip_request->status = config('constants.request_statuses.arrived');
        $trip_request->save();

        $this->sendNotification(
            [$trip_request->customer->firebase_token],
            ['title' => 'BikeIt Trip', 'body' => 'The rider has arrived.'],
            'trip',
            ''
        );

        return response()->json([
            'success' => true,
            'tripRequest' => fractal($trip_request, new TripTransformer()),
            'invoice' => fractal($trip_request->invoice, new InvoiceTransformer())
        ]);
    }

    public function getTrip(Request $request)
    {
        $user = $request->user();

        $trip_request = TripRequest::orderBy('created_at', 'desc')
            ->where('partner_id', $user->id)
            ->where('cancelled_on', null)
            ->where('completed_on', null)
            ->where('accepted_on', null)
            ->where('started_on', null)
            ->first();

        if(!(bool) $trip_request) {
            return response()->json([
                'success' => false,
                'message' => 'no trip request for you'
            ]);
        }


        return response()->json([
            'success' => true,
            'tripRequest' => fractal($trip_request, new TripTransformer())
        ]);
    }

    public function getParcelLocation(TripRequest $trip_request)
    {
        $update = $trip_request->tripUpdates()->orderBy('created_at', 'desc')->first();
       return response()->json([
            'success' => true,
           'locationData' => [
               'originLat' => $trip_request->parcel->origin_lat,
               'originLng' => $trip_request->parcel->origin_lng,
               'currentLat' => $update == null ? null : $update->lat,
               'currentLng' => $update == null ? null : $update->lng,
           ]
       ]);
    }

    public function updateTrip(Request $request, TripRequest $trip_request)
    {
        $trip_request->tripUpdates()->create([
            'lat' => $request->lat,
            'lng' => $request->lng,
        ]);

        return response()->json([
            'success' => true,
            'tripRequest' => $trip_request
        ]);
    }

    public function getTripUpdates(TripRequest $trip_request)
    {
        $data = [
            'success' => true,
            'tripUpdates' => fractal($trip_request->tripUpdates(), new TripUpdatesTransformer()),
        ];

        if ((bool) $driver  = $trip_request->driver) {
            $lat = $trip_request->parcel->origin_lat;
            $lng = $trip_request->parcel->origin_lng;

            $data['driver'] = fractal($driver, new PartnerTransformer("$lat, $lng"));
        }

        return response()->json($data);
    }
}
