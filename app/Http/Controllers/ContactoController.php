<?php

namespace App\Http\Controllers;

use App\Models\ContactosCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactoController extends Controller
{
    public function index()
    {
        return response()->json(ContactosCliente::with('cliente')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'nombre' => 'required|string',
            'celular' => 'required|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $contacto = ContactosCliente::create($request->all());

        return response()->json($contacto, 201);
    }

    public function show(ContactosCliente $contacto)
    {
        return response()->json($contacto->load('cliente'));
    }

    public function update(Request $request, ContactosCliente $contacto)
    {
        $validator = Validator::make($request->all(), [
            'cliente_id' => 'exists:clientes,id',
            'nombre' => 'string',
            'celular' => 'string',
            'email' => 'email',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $contacto->update($request->all());

        return response()->json($contacto);
    }

    public function destroy(ContactosCliente $contacto)
    {
        $contacto->delete();
        return response()->json(null, 204);
    }
}
