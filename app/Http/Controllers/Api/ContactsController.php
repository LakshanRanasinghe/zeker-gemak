<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RetouraanvraagRequest;
use App\Mail\CallbackRequestAdmin;
use App\Mail\ContactRequestAdmin;
use App\Mail\CustomMadeRequestAdmin;
use App\Mail\RecycleRequestAdmin;
use App\Mail\RetouraanvraagAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Log;
use Mail;

class ContactsController extends Controller
{
    public function drawerBooking(Request $request)
    {
        $data = $request->all();
        Mail::to(config('app.admin_emails'))->send(new CallbackRequestAdmin($data));

        return response()->json([
            'message' => 'Thank you! We will get back to you very soon!',
        ]);
    }

    public function drawerContact(Request $request)
    {
        $data = $request->all();
        Log::info($data);
        Mail::to(config('app.admin_emails'))->send(new ContactRequestAdmin($data));

        return response()->json([
            'message' => 'Thank you for contacting us! We will get back to you very soon!',
        ]);
    }

    public function customMadeRequest(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'shape' => 'nullable|string',
            'dimensions' => 'nullable|string',
            'printer' => 'nullable|string',
            'material' => 'nullable|string',
            'comments' => 'nullable|string',
        ]);

        Mail::to(config('app.admin_emails'))->send(new CustomMadeRequestAdmin($validated));

        return response()->json([
            'message' => 'Your request has been sent successfully.',
        ]);
    }

    public function recycleRequest(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:box,pickup',
            'company' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'country' => 'required|string|max:255',
            'street' => 'required|string|max:255',
            'postal' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'extra' => 'nullable|string',
            'newBox' => 'nullable|boolean',
        ]);

        Mail::to(config('app.admin_emails'))->send(new RecycleRequestAdmin($validated));

        return response()->json([
            'message' => 'Your recycle request has been sent successfully.',
        ]);
    }

    public function retouraanvraag(RetouraanvraagRequest $request)
    {
        $validated = $request->validated();
        $rmaNumber = 'RMA-'.now()->year.'-'.Str::upper(Str::random(5));

        Mail::to(config('app.admin_emails'))->send(
            new RetouraanvraagAdmin($validated, $rmaNumber, $request->file('file'))
        );

        return response()->json([
            'success' => true,
            'message' => 'Retouraanvraag succesvol ontvangen.',
            'rma_number' => $rmaNumber,
        ]);
    }
}
