<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class crearcuentaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('CrearCuenta');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('CrearCuenta');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'username' => 'required|string|min:3|max:50',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:password',
        ]);

        try {
            // Verificar si el usuario ya existe en tbusers
            $existingUser = DB::table('tbusers')
                ->where('username', $request->username)
                ->orWhere('email', $request->email)
                ->first();

            if ($existingUser) {
                if ($existingUser->username === $request->username) {
                    return redirect()->route('rutaCrear')->with('error', 'El nombre de usuario ya está en uso');
                } else {
                    return redirect()->route('rutaCrear')->with('error', 'El email ya está registrado');
                }
            }

            // Crear nuevo usuario en tbusers
            $userId = DB::table('tbusers')->insertGetId([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            if ($userId) {
                // Crear sesión automáticamente después del registro
                session([
                    'logged_in' => true,
                    'user_id' => $userId,
                    'username' => $request->username,
                    'email' => $request->email
                ]);

                return redirect()->route('rutaInicio')->with('message', 'Cuenta creada exitosamente. ¡Bienvenido!');
            } else {
                return redirect()->route('rutaCrear')->with('error', 'Error al crear la cuenta');
            }
        } catch (\Exception $e) {
            return redirect()->route('rutaCrear')->with('error', 'Error en el servidor: ' . $e->getMessage());
        }
    }
}
