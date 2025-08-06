<?php

use App\Http\Controllers\ControladorVistas;
use App\Http\Controllers\donativoController;
use App\Http\Controllers\crearcuentaController;
use App\Http\Controllers\nosotrosController;
use App\Http\Controllers\loginController;
use App\Http\Controllers\consultarController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\NoticiasController; // Agregar esta línea


Route::get('/', function () {
    return view('inicio');
})->name('rutaInicio');

Route::get('/donar', function () {
    return view('donativos');
})->name('rutaDonativos');

Route::get('/info', function () {
    return view('nosotros');
})->name('rutaNosotros');

Route::get('/Consulta', function () {
    return view('Consultar');
})->name('rutaConsultas');


Route::get('/Noti', [NewsController::class, 'index'])->name('rutaNoticias');

Route::get('/demo', function () {
    return view('demo');
})->name('rutaDemoDesarrollo');

/* Route::get('/Login', [ControladorVistas::class, 'login'])->name('rutaLogin');

Route::post('/Login', [ControladorVistas::class, 'iniciasesion'])->name('rutalogin'); */



/* Route::get('/CrearCuenta', [ControladorVistas::class, 'CrearCuenta'])->name('rutaCrear');

Route::post('/CrearCuenta', [ControladorVistas::class, 'creartuCuenta'])->name('rutaCrearCuenta'); */

/* Route::get('/enviarDonativo', [ControladorVistas::class, 'donativos'])->name('enviarDonativo'); */

/* Route::post('/enviarDonativo', [ControladorVistas::class, 'process'])->name('rutaDonar'); */

/* Route::get('/enviarInfo', function () {
    return view('nosotros');
})->name('rutaInfo'); */

/* Route::post('/enviarInfo', [ControladorVistas::class, 'procesoInfo'])->name('rutaInfo'); */

/* Rutas para donativoController */
Route::get('/donativos/create', [donativoController::class, 'create'])->name('rutaDonativos');
Route::post('/donativos', [donativoController::class, 'store'])->name('rutaDonar');
Route::get('/donativos', [donativoController::class, 'index'])->name('enviarDonativo');

/* Route::get('/crear_cuenta/create', [crearcuentaController::class, 'create'])->name('rutaCrear');
Route::post('/CrearCuenta', [crearcuentaController::class, 'store'])->name('rutaCrearCuenta');
Route::get('/CrearCuenta', [crearcuentaController::class, 'index'])->name('rutaCrear'); */

Route::get('/nosotros/create', [nosotrosController::class, 'create'])->name('rutaInfo');
Route::post('/nosotros/create', [nosotrosController::class, 'store'])->name('rutaInfo');
Route::get('/nosotros', [nosotrosController::class, 'index'])->name('enviarInfo');

/* Route::get('/login/create', [loginController::class, 'create'])->name('rutaLogin');
Route::post('/Login', [loginController::class, 'store'])->name('rutaLogin');
Route::get('/Login', [loginController::class, 'index'])->name('rutaLog');
Route::get('/logout', [loginController::class, 'logout'])->name('rutaLogout'); */

Route::get('/Login', [loginController::class, 'index'])->name('rutaLogin');
Route::post('/Login', [loginController::class, 'store'])->name('rutaLoginPost');
Route::get('/logout', [loginController::class, 'logout'])->name('rutaLogout');

Route::get('/CrearCuenta', [crearcuentaController::class, 'index'])->name('rutaCrear');
Route::post('/CrearCuenta', [crearcuentaController::class, 'store'])->name('rutaCrearCuenta');

Route::get('/Consultar', [consultarController::class, 'index'])->name('rutaConsultar');
Route::get('/Consultar/{id}/edit',[consultarController::class,'edit'])->name('rutaFormConsulta');
Route::put('/Consultar/{id}', [consultarController::class, 'update'])->name('rutaActualizar');
Route::delete('/Consultar/{id}', [consultarController::class, 'destroy'])->name('rutaEliminar');

Route::view('/component','componentes')->name('rutacomponent');



// Ruta para mostrar el formulario de donación
Route::get('/donar', function () {
    return view('donativos'); // Vista con el formulario de donación
})->name('rutaDonativos');

// Ruta para manejar el pago con Stripe
Route::post('/checkout', function (Request $request) {
    try {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        
        // Fix para SSL en desarrollo
        if (env('APP_ENV') === 'local') {
            Stripe::setVerifySslCerts(false);
        }

        // Validar amount
        $amount = $request->input('amount');
        if (!$amount || $amount <= 0) {
            return redirect()->back()->with('error', 'Cantidad inválida');
        }

        // Crear una sesión de pago con Stripe
        $session = Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => 'Donación Sustainity',
                        'description' => 'Apoya nuestro proyecto educativo'
                    ],
                    'unit_amount' => $amount * 100, // Convierte a centavos
                ],
                'quantity' => 1,
            ]],
            'success_url' => url('/gracias?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/cancelado'),
            'metadata' => [
                'donation_amount' => $amount,
                'currency' => 'mxn'
            ]
        ]);

        // Redirigir al usuario a la página de pago de Stripe
        return redirect($session->url);
        
    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Error al procesar el pago: ' . $e->getMessage());
    }
})->name('rutaCheckout');

// Ruta para la página de agradecimiento (éxito)
Route::get('/gracias', function () {
    return view('gracias'); // Vista de agradecimiento
})->name('rutaGracias');

// Ruta para la página de cancelación
Route::get('/cancelado', function () {
    return view('cancelado'); // Vista de cancelación
})->name('rutaCancelado');

Route::get('/test-db', function () {
    try {
        DB::connection()->getPdo();
        $results = DB::select('SHOW TABLES');
        dd($results);
    } catch (\Exception $e) {
        dd("Error de conexión: " . $e->getMessage());
    }
});

// Agregar estas rutas para noticias - usando base de datos local
Route::get('/noticias', [NoticiasController::class, 'index'])->name('rutaNoticias');

// API routes para likes/dislikes/comentarios
Route::post('/news/posts/{postId}/like', [NoticiasController::class, 'like']);
Route::delete('/news/posts/{postId}/like', [NoticiasController::class, 'like']);
Route::post('/news/posts/{postId}/dislike', [NoticiasController::class, 'dislike']);
Route::delete('/news/posts/{postId}/dislike', [NoticiasController::class, 'dislike']);
Route::get('/news/posts/{postId}/comments', [NoticiasController::class, 'getComments']);
Route::post('/news/posts/{postId}/comments', [NoticiasController::class, 'postComment']);
Route::put('/news/comments/{commentId}', [NoticiasController::class, 'updateComment']);
Route::delete('/news/comments/{commentId}', [NoticiasController::class, 'deleteComment']);

/* Route::post('/create-checkout-session', function (Request $request) {
    Stripe::setApiKey(env('STRIPE_SECRET'));
    
    // Fix para SSL en desarrollo
    if (env('APP_ENV') === 'local') {
        Stripe::setVerifySslCerts(false);
    }
    
    try {
        $amount = $request->input('amount', 5);
        $amountInCents = $amount * 100;

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Donativo Sustainity',
                    ],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('rutaDonativos') . '?success=true',
            'cancel_url' => route('rutaDonativos') . '?canceled=true',
        ]);

        return response()->json(['url' => $session->url]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}); */
