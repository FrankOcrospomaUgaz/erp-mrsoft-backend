<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductoResource;
use App\Models\Cliente;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductoController extends Controller
{
    private function normalizeTipo(?string $tipo): string
    {
        return in_array($tipo, ['servicio', 'producto'], true) ? $tipo : 'servicio';
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $all = filter_var($request->get('all', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = $request->get('per_page', 5);

        $query = Producto::with([
            'modulos.contratos',
            'contratos',
            'avisos_saas',
        ])->when($search, function ($query, $search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('tipo', 'ILIKE', "%{$search}%")
                    ->orWhere('descripcion', 'ILIKE', "%{$search}%");
            });

            $query->orWhereHas('modulos', function ($q) use ($search) {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('precio_unitario', 'ILIKE', "%{$search}%")
                    ->orWhere('precio_mensual', 'ILIKE', "%{$search}%")
                    ->orWhere('precio_anual', 'ILIKE', "%{$search}%");
            });
        })->latest();

        if ($all) {
            return response()->json(
                ProductoResource::collection($query->get())
            );
        }

        $productos = $query->paginate($perPage);

        return response()->json([
            'data' => ProductoResource::collection($productos->items()),
            'links' => [
                'first' => $productos->url(1),
                'last' => $productos->url($productos->lastPage()),
                'prev' => $productos->previousPageUrl(),
                'next' => $productos->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $productos->currentPage(),
                'from' => $productos->firstItem(),
                'last_page' => $productos->lastPage(),
                'path' => $productos->path(),
                'per_page' => $productos->perPage(),
                'to' => $productos->lastItem(),
                'total' => $productos->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $producto = Producto::with(['modulos', 'avisos_saas'])->find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        return response()->json([
            'status' => 200,
            'data' => new ProductoResource($producto),
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->productRules(), $this->productMessages());

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $producto = Producto::create([
                'nombre' => $request->input('nombre'),
                'tipo' => $this->normalizeTipo($request->input('tipo')),
                'descripcion' => $request->input('descripcion'),
            ]);

            foreach ($request->input('modulos', []) as $modulo) {
                $producto->modulos()->create($this->mapModuloPayload($modulo));
            }

            DB::commit();

            return response()->json([
                'status' => 201,
                'message' => 'Producto creado exitosamente',
                'data' => new ProductoResource($producto->load('modulos')),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->productRules(), $this->productMessages());

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $producto->update([
                'nombre' => $request->input('nombre'),
                'tipo' => $this->normalizeTipo($request->input('tipo')),
                'descripcion' => $request->input('descripcion'),
            ]);

            $producto->modulos()->delete();
            foreach ($request->input('modulos', []) as $modulo) {
                $producto->modulos()->create($this->mapModuloPayload($modulo));
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Producto actualizado correctamente',
                'data' => new ProductoResource($producto->load('modulos')),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $producto->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Producto eliminado correctamente',
        ], 200);
    }

    private function productRules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'tipo' => 'required|in:servicio,producto',
            'descripcion' => 'nullable|string',
            'modulos' => 'nullable|array',
            'modulos.*.nombre' => 'required|string|max:255',
            'modulos.*.descripcion_contrato' => 'nullable|string',
            'modulos.*.precio_mensual' => 'required|numeric|min:0',
            'modulos.*.precio_anual' => 'required|numeric|min:0',
        ];
    }

    private function productMessages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'tipo.required' => 'El tipo es obligatorio.',
            'modulos.*.nombre.required' => 'El nombre del concepto es obligatorio.',
            'modulos.*.precio_mensual.required' => 'El precio mensual del concepto es obligatorio.',
            'modulos.*.precio_mensual.numeric' => 'El precio mensual debe ser numerico.',
            'modulos.*.precio_anual.required' => 'El precio anual del concepto es obligatorio.',
            'modulos.*.precio_anual.numeric' => 'El precio anual debe ser numerico.',
        ];
    }

    public function getFormatoAlta($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $formato = $producto->formato_alta ?: $this->getDefaultFormatoAlta($producto);

        return response()->json([
            'status' => 200,
            'data' => [
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'formato_alta' => $formato,
            ],
        ], 200);
    }

    public function updateFormatoAlta(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $formato = $request->input('formato_alta', []);

        $producto->update([
            'formato_alta' => $formato,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Formato de alta guardado exitosamente.',
            'data' => [
                'producto_id' => $producto->id,
                'producto_nombre' => $producto->nombre,
                'formato_alta' => $producto->formato_alta,
            ],
        ], 200);
    }

    public function pdfFormatoAlta(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'status' => 404,
                'message' => 'Producto no encontrado',
            ], 404);
        }

        if (empty($producto->formato_alta)) {
            $producto->formato_alta = $this->getDefaultFormatoAlta($producto);
        }

        $cliente = null;
        if ($request->filled('cliente_id')) {
            $cliente = Cliente::with('contactos_clientes')->find($request->input('cliente_id'));
        }

        $datosCliente = [
            'razon_social' => $request->input('cliente_razon_social', $cliente?->razon_social ?? $cliente?->nombre_comercial ?? 'MARAKOS GRILL CONCESIONES E.I.R.L'),
            'ruc' => $request->input('cliente_ruc', $cliente?->ruc ?? '20601799317'),
        ];

        $data = [
            'producto' => $producto,
            'cliente' => $cliente,
            'datosCliente' => $datosCliente,
        ];

        $pdf = Pdf::loadView('pdf.formato_alta', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('dpi', 72)
            ->setOption('defaultFont', 'Helvetica');

        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', strtolower($producto->nombre));

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="formato-alta-' . $safeName . '.pdf"',
        ]);
    }

    private function getDefaultFormatoAlta(Producto $producto): array
    {
        $nombre = $producto->nombre;

        return [
            'portada' => [
                'slogan' => 'Tu restaurante digital',
                'telefono_soporte' => '+51 979 293 176',
                'email_soporte' => 'martin.ampuero@garzasoft.com',
                'web_url' => 'www.gesrest.net',
                'empresa_desarrollo' => 'Mr. Soft',
            ],
            'presentacion' => [
                'titulo' => 'PRESENTACIÓN',
                'descripcion' => "{$nombre} es el software en nube para gestión de restaurantes y negocios. Incluye los módulos siguientes (*) atención de clientes, (*) control de productos en almacén, (*) registro de recetas y sub recetas, (*) seguimiento de ingresos y egresos de caja chica (*) compras y cuentas por pagar (*) sincronización con nuestra plataforma de facturación electrónica.",
                'caracteristicas' => [
                    'Detalle de los productos que vendes.',
                    'Detalle de los productos compras.',
                    'Detalle de los productos en tu almacén.',
                    'El personal responsable de cada operación en tu negocio.',
                    'El importe total y detalle de dinero en caja diaria.',
                    'Detalle de gastos.',
                    'El tiempo de atención / preparación de cocina y bar.',
                    'La estadística de venta de platos en el restaurante.',
                    'La productividad por mesero, plato, turno, salón, otros.',
                ],
                'mensaje_agradecimiento' => "Mr. SOFT agradece depositar su confianza en nuestra empresa, le garantizamos el soporte y apoyo necesario para aprovechar al máximo {$nombre}, nuestra herramienta para su productividad.",
                'firmante_nombre' => 'Gilberto Martín Ampuero Pasco',
                'firmante_cargo' => 'CEO Mr. SOFT',
            ],
            'acceso' => [
                'titulo' => 'CREDENCIALES DE ACCESO',
                'url_acceso' => 'https://gesrest.net/',
                'url_mesero' => 'https://sistema.gesrest.net/waiter-login/ejemplo',
                'perfiles' => [
                    [
                        'perfil' => 'PERFIL ADMINISTRADOR',
                        'usuarios' => [
                            ['usuario' => '20601799317', 'clave' => '20601799317'],
                        ],
                    ],
                    [
                        'perfil' => 'PERFIL CAJERO',
                        'usuarios' => [
                            ['usuario' => 'DIEGOMARAKOSE', 'clave' => 'DIEGOMARAKOSE'],
                            ['usuario' => 'LUCARMARAKOSE', 'clave' => 'LUCARMARAKOSE'],
                            ['usuario' => 'MELISAMARAKOSE', 'clave' => 'MELISAMARAKOSE'],
                        ],
                    ],
                    [
                        'perfil' => 'PERFIL MESERO',
                        'enlace' => 'https://sistema.gesrest.net/waiter-login/STpasiENipZv',
                        'usuarios' => [
                            ['usuario' => 'JAMIRMARAKOSE', 'clave' => '1234'],
                            ['usuario' => 'JEFFERSONMARAKOSE', 'clave' => '1234'],
                            ['usuario' => 'JUANJMARAKOSE', 'clave' => '1234'],
                            ['usuario' => 'LIONELMARAKOSE', 'clave' => '1234'],
                            ['usuario' => 'MILUSKAMARAKOSE', 'clave' => '1234'],
                        ],
                    ],
                ],
            ],
            'facturacion' => [
                'titulo' => 'CREDENCIALES PARA ACCESO A PORTAL DE CONTADOR',
                'url_portal' => 'https://comprobante-e.com',
                'series' => [
                    ['tipo' => 'Serie factura', 'serie' => 'F040'],
                    ['tipo' => 'Serie boleta', 'serie' => 'B040'],
                    ['tipo' => 'Serie Nota de Crédito', 'serie' => 'NC40'],
                ],
                'credenciales_contador' => [
                    ['usuario' => '20601799317', 'clave' => 'marakos19'],
                ],
            ],
            'tutoriales' => [
                'titulo' => "TUTORIALES PARA USO DE {$nombre}",
                'plataforma' => 'YouTube',
                'canal' => 'Mr Soft',
                'nombre_playlist' => "{$nombre} - Software para restaurantes 🍴",
                'enlace_playlist' => 'https://www.youtube.com/playlist?list=PLTwle3OwQTDthaIAsGGGFc8iimt69fSOj',
                'videos' => [
                    ['titulo' => 'Presentación 🍳', 'url' => 'https://youtu.be/us7pS1mjCZE?si=U_e281AnAOFgZ6q3'],
                    ['titulo' => 'Recorrido por la plataforma 🍳', 'url' => 'https://youtu.be/q5zDJpZK85g?si=C4P5cWvuECFXdo5p'],
                    ['titulo' => '¿Cómo ingresar a la plataforma? 🔐', 'url' => 'https://youtu.be/lL32cakXcus?si=PHxN68vLuE3x7Orp'],
                    ['titulo' => '¿Cómo registrar un pedido en salón? 🍽️', 'url' => 'https://youtu.be/Wj5bpyReOD8?si=ngv2NGhu7LBMmb27'],
                    ['titulo' => '¿Cómo registrar una venta rápida? ☕', 'url' => 'https://youtu.be/VpWpitK87oo?si=4T-BrRv5b7RYb_lB'],
                    ['titulo' => '¿Cómo cobrar una mesa? 💰', 'url' => 'https://youtu.be/t5yrv0Q4f1E?si=DLqBcmI2RSmsTE5T'],
                    ['titulo' => '¿Cómo emitir un comprobante de venta electrónico para SUNAT?', 'url' => 'https://youtu.be/oxpqPuOw8Sc?si=qR5oqEwde6P0_qMo'],
                    ['titulo' => '¿Cómo disminuir productos comandados? ⬇️', 'url' => 'https://youtu.be/CIxr6MPPqoQ?si=vf8ZOxTUKY1NVyWH'],
                    ['titulo' => '¿Cómo anular un producto registrado? 🚫', 'url' => 'https://youtu.be/PmF7jkleJdk?si=iZzipLbjgoi3feyT'],
                    ['titulo' => '¿Cómo anular un pedido completo? 🚫', 'url' => 'https://youtu.be/Vb8j6sVXH5Q?si=FlM78NRtEwbhAuiH'],
                    ['titulo' => '¿Cómo anular una venta? 🚫', 'url' => 'https://youtu.be/FD2gI9z7qXk?si=PeN7r-4tdHW_flnV'],
                    ['titulo' => '¿Cómo cambiar mi contraseña? 🔑', 'url' => 'https://youtu.be/tpvKMZCnBJU?si=ExWy3dp12PR3RrfP'],
                    ['titulo' => '¿Cómo crear una nueva categoría de productos? 🍕', 'url' => 'https://youtu.be/SSn6IofCquI?si=wV5WsuLmauEDpGEm'],
                    ['titulo' => '¿Cómo crear un nuevo producto? 🍔', 'url' => 'https://youtu.be/WguSM1eJ62o?si=KBgl_GVv2o_RDE02'],
                    ['titulo' => '¿Cómo registrar mis gastos? 💸', 'url' => 'https://youtu.be/vV_rctLu4gs?si=09wlGN8Hy-7mKbVH'],
                    ['titulo' => '¿Cómo configurar mis productos favoritos? ⭐', 'url' => 'https://youtu.be/cjzyNOTF11M?si=QRPyi5iL7xJi4Ndb'],
                    ['titulo' => '¿Cómo controlar mi inventario? 📦', 'url' => 'https://youtu.be/PODRHCv0iis?si=Nd3cwxW1cDf0sExB'],
                    ['titulo' => '¿Cómo crear ingredientes? 🥦', 'url' => 'https://youtu.be/63yQtPY1g8U?si=tZaYkX9E_Zef9L5p'],
                    ['titulo' => '¿Cómo crear productos compuestos? 🍲', 'url' => 'https://youtu.be/w0y2YNaiL8Y?si=PeZMC-hNZ23JJ_kD'],
                    ['titulo' => '¿Cómo configurar tus recetas? 🍳', 'url' => 'https://youtu.be/3Uvo7p23LYw?si=WBdvuuxqv1nhC1yy'],
                    ['titulo' => '¿Cómo hacer entradas/salidas de stock de productos? 🚚', 'url' => 'https://youtu.be/Z3bksX0WrEQ?si=i_SoeGvpqxMmQsWl'],
                    ['titulo' => '¿Cómo ver mi stock de productos? 📄', 'url' => 'https://youtu.be/2J_U0EFy_as?si=XyT-NXrQy_bDNdjL'],
                    ['titulo' => '¿Cómo ver el kárdex de inventario? 📄', 'url' => 'https://youtu.be/XWo2kdtXhTY?si=wXFc-tOy2mWENXa8'],
                    ['titulo' => '¿Cómo aperturar caja? 💰', 'url' => 'https://youtu.be/SD-8vguX89M?si=S2-PMcHO-WSonuFp'],
                    ['titulo' => '¿Cómo cerrar caja? 💰', 'url' => 'https://youtu.be/U3CI98ky6J0?si=_t8lqqHNvXhUqTdA'],
                    ['titulo' => '¿Cómo registrar un pedido de PedidosYa o Rappi? 📲', 'url' => 'https://youtu.be/9MydaU3mDTU?si=o6R3KNEdMEACw78h'],
                    ['titulo' => '¿Cómo mover una mesa? 🔄', 'url' => 'https://youtu.be/FRe96ByPZxM?si=MXSUOl1VE0yVWOdM'],
                    ['titulo' => '¿Cómo cambiar el nombre de un producto para mi comprobante de venta electrónico? ✏️', 'url' => 'https://youtu.be/zDpZ4-uWMJc?si=jOkHB--8OGUn7qjr'],
                    ['titulo' => '¿Cómo aplicar descuento a un producto? 🏷️', 'url' => 'https://youtu.be/U5eX_8jTDgY?si=H7U9yRSBJCypStVo'],
                    ['titulo' => '¿Cómo aplicar un descuento a todo mi pedido? 🏷️', 'url' => 'https://youtu.be/llZV8dp1syA?si=73bM1QqpQjWpm9UV'],
                    ['titulo' => '¿Cómo dar una cortesía completa? 🎁', 'url' => 'https://youtu.be/AXgsL2WLEIs?si=6AgM33O5DLKlWu6Q'],
                    ['titulo' => '¿Cómo dividir cuenta por productos? ✂️', 'url' => 'https://youtu.be/lCa6ip__usc?si=HE5KfVXP9r6mocIz'],
                    ['titulo' => '¿Cómo dividir cuenta por montos? ✂️', 'url' => 'https://youtu.be/H8Yp0EQCuro?si=eOuDEQTPNOMQaMGX'],
                    ['titulo' => '¿Cómo cambiar el medio de pago de una venta? 💵', 'url' => 'https://youtu.be/wIVYEN2lG3E?si=rwe-AqCN0Yu2WRGX'],
                    ['titulo' => '¿Cómo hacer un comprobante de venta electrónico por consumo?', 'url' => 'https://youtu.be/U-kLc65qoKg?si=NuUGff4cn1_Rpcvu'],
                    ['titulo' => '¿Cómo hacer un comprobante de venta electrónico por glosa?', 'url' => 'https://youtu.be/2Np51QFi7pE?si=wcbMZCpGvygSMplQ'],
                    ['titulo' => '¿Cómo enviar un comprobante por correo o WhatsApp? 📩', 'url' => 'https://youtu.be/LIwf62k48XU?si=vHt2RBnI10JAVJNK'],
                    ['titulo' => '¿Cómo hacer una venta al crédito? 💳', 'url' => 'https://youtu.be/jxRReJbF7f8?si=0Z8EF1g9GfgYrj9Q'],
                    ['titulo' => '¿Cómo pagar una venta al crédito? 💵', 'url' => 'https://youtu.be/fwKCn4O_Jjg?si=-5x-opgNTzdLk1Jo'],
                ],
            ],
        ];
    }
}
