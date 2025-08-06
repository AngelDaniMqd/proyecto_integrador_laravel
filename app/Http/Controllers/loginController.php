<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class loginController extends Controller
{
    public function index()
    {
        return view('Login');
    }

    public function create()
    {
        return view('Login');
    }

    public function store(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            // Buscar usuario en la tabla tbusers
            $user = DB::table('tbusers')
                ->where('username', $request->username)
                ->orWhere('email', $request->username) // Permitir login con email también
                ->first();

            if ($user && Hash::check($request->password, $user->password)) {
                // Login exitoso - crear sesión
                session([
                    'logged_in' => true,
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email ?? null
                ]);

                return redirect()->route('rutaInicio')->with('message', 'Inicio de sesión exitoso');
            } else {
                // Credenciales incorrectas
                return redirect()->route('rutaLogin')->with('error', 'Usuario o contraseña incorrectos');
            }
        } catch (\Exception $e) {
            return redirect()->route('rutaLogin')->with('error', 'Error en el servidor: ' . $e->getMessage());
        }
    }

    public function logout()
    {
        // Eliminar todas las variables de sesión relacionadas con el login
        session()->forget(['logged_in', 'user_id', 'username', 'email']);
        session()->flush();

        return redirect()->route('rutaInicio')->with('message', 'Sesión cerrada exitosamente');
    }
}