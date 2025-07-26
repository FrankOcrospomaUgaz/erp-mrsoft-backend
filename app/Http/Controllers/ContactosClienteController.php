<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactosCliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class ContactosClienteController extends Controller
{
    public function index()
    {
        return response()->json(ContactosCliente::with('cliente')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'nombre'     => 'required|string|max:255',
            'celular'    => 'required|string|max:20',
            'email'      => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contacto = ContactosCliente::create($request->all());

        return response()->json(['message' => 'Contacto registrado correctamente', 'data' => $contacto]);
    }

    public function show($id)
    {
        $contacto = ContactosCliente::with('cliente')->findOrFail($id);
        return response()->json($contacto);
    }

    public function update(Request $request, $id)
    {
        $contacto = ContactosCliente::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'nombre'     => 'required|string|max:255',
            'celular'    => 'required|string|max:20',
            'email'      => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contacto->update($request->all());

        return response()->json(['message' => 'Contacto actualizado correctamente', 'data' => $contacto]);
    }

    public function destroy($id)
    {
        $contacto = ContactosCliente::findOrFail($id);
        $contacto->delete();

        return response()->json(['message' => 'Contacto eliminado correctamente']);
    }
}
