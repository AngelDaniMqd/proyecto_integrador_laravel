<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Requests\validadorDonativo;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class donativoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $consultaDonativos = DB::table('donativos')->get();
        return view('donativos', compact('consultaDonativos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('donativos');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(validadorDonativo $request)
    {
        DB::table('donativos')->insert([
            "nombre"=>$request->input('name'),
            "correo"=>$request->input('email'),
            "cantidad"=>$request->input('amount'),
            "metodo_pago"=>$request->input('payment_method'),
            "created_at"=>Carbon::now(),
            "updated_at"=>Carbon::now()
        ]);
        return to_route('rutaDonativos')->with('message', 'Gracias por tu donación de $' . number_format($request->amount, 2) . '!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function __construct()
    {
        // Configurar Stripe
        Stripe::setApiKey(env('STRIPE_SECRET'));
        
        // Fix para SSL en desarrollo (temporal)
        if (env('APP_ENV') === 'local') {
            Stripe::setVerifySslCerts(false);
        }
    }

    public function createCheckoutSession(Request $request)
    {
        try {
            // Validar cantidad
            $amount = $request->input('amount', 5); // Default $5
            $amountInCents = $amount * 100; // Stripe usa centavos

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Donativo Sustainity',
                            'description' => 'Apoya nuestro proyecto de videojuegos educativos',
                        ],
                        'unit_amount' => $amountInCents,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('rutaDonativos') . '?success=true',
                'cancel_url' => route('rutaDonativos') . '?canceled=true',
                'metadata' => [
                    'donation_amount' => $amount,
                    'project' => 'Sustainity'
                ]
            ]);

            return response()->json(['url' => $session->url]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
