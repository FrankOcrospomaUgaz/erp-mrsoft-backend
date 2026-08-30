<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContratoResource;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Facturador;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\ListItem;

class ContratoController extends Controller
{
    public function generarSiguienteNumero(?int $year = null): string
    {
        $year = $year ?: now()->year;
        $pattern = '/^CT-' . $year . '-(\d+)$/';

        $lastSequence = Contrato::withTrashed()
            ->get(['numero'])
            ->reduce(function (int $carry, Contrato $contrato) use ($pattern) {
                if (!preg_match($pattern, (string) $contrato->numero, $matches)) {
                    return $carry;
                }

                return max($carry, (int) $matches[1]);
            }, 0);

        return sprintf('CT-%s-%03d', $year, $lastSequence + 1);
    }

    public function siguienteNumero(Request $request)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $year = $request->input('year') ? (int) $request->input('year') : null;
        if (!$year && $request->filled('fecha_inicio')) {
            try {
                $year = Carbon::parse($request->input('fecha_inicio'))->year;
            } catch (\Throwable) {
                $year = null;
            }
        }

        return response()->json([
            'status' => 200,
            'data' => [
                'numero' => $this->generarSiguienteNumero($year),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $clienteIds = $this->accessibleClienteIds($request);
        $query = Contrato::with([
            'cliente',
            'cuotas',
            'contratoProductoModulos.modulo',
            'contratoProductoModulos.producto',
        ])->when($clienteIds, fn ($query) => $query->whereIn('cliente_id', $clienteIds));

        if ($request->filled('search')) {
            $search = $request->get('search');

            $query->where(function ($q) use ($search) {
                $q->where('numero', 'ILIKE', "%{$search}%")
                    ->orWhere('tipo_contrato', 'ILIKE', "%{$search}%")
                    ->orWhere('estado', 'ILIKE', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('razon_social', 'ILIKE', "%{$search}%")
                            ->orWhere('nombre_comercial', 'ILIKE', "%{$search}%")
                            ->orWhere('ruc', 'ILIKE', "%{$search}%")
                            ->orWhere('dueno_nombre', 'ILIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('numero')) {
            $numero = $request->get('numero');
            $query->where('numero', 'ILIKE', "%{$numero}%");
        }

        if ($request->filled('cliente_id')) {
            $filterClienteId = (int) $request->get('cliente_id');
            $targetClienteIds = $this->getAllSubClientIds($filterClienteId);
            $query->whereIn('cliente_id', $targetClienteIds);
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->get('created_from'));
        }

        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->get('created_to'));
        }

        if ($request->filled('vigencia_from')) {
            $query->where('fecha_fin', '>=', $request->get('vigencia_from'));
        }

        if ($request->filled('vigencia_to')) {
            $query->where('fecha_inicio', '<=', $request->get('vigencia_to'));
        }

        if ($request->filled('producto_id')) {
            $productoId = (int) $request->get('producto_id');
            $query->whereHas('contratoProductoModulos', function ($q) use ($productoId) {
                $q->where('producto_id', $productoId);
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }

        $query->orderBy('id', 'desc');

        $contratos = $query->paginate($request->get('per_page', 10));

        return response()->json([
            'data' => ContratoResource::collection($contratos->items()),
            'links' => [
                'first' => $contratos->url(1),
                'last' => $contratos->url($contratos->lastPage()),
                'prev' => $contratos->previousPageUrl(),
                'next' => $contratos->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $contratos->currentPage(),
                'from' => $contratos->firstItem(),
                'last_page' => $contratos->lastPage(),
                'path' => $contratos->path(),
                'per_page' => $contratos->perPage(),
                'to' => $contratos->lastItem(),
                'total' => $contratos->total(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $contrato = Contrato::with([
            'cliente',
            'cuotas',
            'contratoProductoModulos.modulo',
            'contratoProductoModulos.producto',
        ])->find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if (!$this->canAccessContrato($request, $contrato)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        return response()->json([
            'status' => 200,
            'data' => new ContratoResource($contrato),
        ], 200);
    }

    public function pdf(Request $request, $id)
    {
        $contrato = Contrato::with([
            'cliente.parent_cliente.parent_cliente',
            'cuotas',
            'contratoProductoModulos.modulo',
            'contratoProductoModulos.producto',
        ])->find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if (!$this->canAccessContrato($request, $contrato)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $cliente = $contrato->cliente;
        $jerarquia = $this->buildClientHierarchy($cliente);
        $facturador = Facturador::where('activo', true)->latest()->first() ?? Facturador::latest()->first();
        $modulosAgrupados = $contrato->contratoProductoModulos
            ->groupBy(fn($item) => $item->producto?->nombre ?? 'Servicio')
            ->map(function ($items, $producto) {
                return [
                    'producto' => $producto,
                    'items' => $items,
                    'subtotal' => $items->sum('precio'),
                ];
            })
            ->values();

        $firmaArrendador = $contrato->firma_arrendador ?? $facturador?->firma_arrendador_default ?? null;
        $firmaCliente = $contrato->firma_cliente ?? null;

        $data = [
            'contrato' => $contrato,
            'cliente' => $cliente,
            'jerarquia' => $jerarquia,
            'facturador' => $facturador,
            'modulosAgrupados' => $modulosAgrupados,
            'fechaEmision' => now(),
            'vigenciaDescripcion' => $this->resolveVigenciaDescription($contrato),
            'periodicidadDescripcion' => $contrato->periodicidad_cuota === 'anual' ? 'anual' : 'mensual',
            'formaPagoDescripcion' => $contrato->forma_pago === 'parcial' ? 'pago fraccionado' : 'pago unico',
            'tipoContratoDescripcion' => $this->resolveContractTypeDescription($contrato->tipo_contrato),
            'montoTotalTexto' => $this->formatMoney((float) $contrato->total),
            'montoTotalLetras' => $this->amountToWords((float) $contrato->total),
            'fechaInicioTexto' => $this->formatDateLong($contrato->fecha_inicio),
            'fechaFinTexto' => $this->formatDateLong($contrato->fecha_fin),
            'firmaArrendador' => $firmaArrendador,
            'firmaCliente' => $firmaCliente,
        ];

        $pdf = Pdf::loadView('pdf.contrato', $data)
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato-' . $contrato->numero . '.pdf"',
        ]);
    }

    public function word(Request $request, $id)
    {
        $contrato = Contrato::with([
            'cliente.parent_cliente.parent_cliente',
            'cuotas',
            'contratoProductoModulos.modulo',
            'contratoProductoModulos.producto',
        ])->find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if (!$this->canAccessContrato($request, $contrato)) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $cliente = $contrato->cliente;
        $jerarquia = $this->buildClientHierarchy($cliente);
        $facturador = Facturador::where('activo', true)->latest()->first() ?? Facturador::latest()->first();
        $modulosAgrupados = $contrato->contratoProductoModulos
            ->groupBy(fn($item) => $item->producto?->nombre ?? 'Servicio')
            ->map(function ($items, $producto) {
                return [
                    'producto' => $producto,
                    'items' => $items,
                    'subtotal' => $items->sum('precio'),
                ];
            })
            ->values();

        $firmaArrendador = $contrato->firma_arrendador ?? $facturador?->firma_arrendador_default ?? null;
        $firmaCliente = $contrato->firma_cliente ?? null;

        $brandMap = [
            'hotelhub' => 'HotelHUB',
            'gesrest' => 'Gesrest',
            '360sys' => '360Sys',
        ];
        $empresaCliente = $jerarquia['empresa'] ?? $jerarquia['root'];
        $nombreEmisor = strtoupper($facturador->nombre_comercial ?? $facturador->razon_social ?? 'GARZASOFT EIRL');
        $rucEmisor = $facturador->ruc ?? '20602871119';
        $direccionEmisor = $facturador->direccion ?? 'Calle Nicolas la Torre 126 Urb. Magisterial, Chiclayo, Lambayeque';
        $representanteEmisor = 'AMPUERO PASCO GILBERTO MARTIN';
        $dniRepresentanteEmisor = '16734323';
        $nombreCliente = strtoupper($empresaCliente->razon_social ?? $empresaCliente->nombre_comercial ?? 'CLIENTE');
        $rucCliente = $empresaCliente->ruc ?? 'N/D';
        $representanteCliente = strtoupper($cliente->dueno_nombre ?? $empresaCliente->dueno_nombre ?? 'SIN REPRESENTANTE');
        $dniCliente = $cliente->contactos_clientes[0]->dni ?? 'N/D';

        $tipoContratoDesc = $this->resolveContractTypeDescription($contrato->tipo_contrato);
        $productoPrincipalRaw = $modulosAgrupados->first()['producto'] ?? strtoupper($tipoContratoDesc);
        $productoPrincipal = $brandMap[strtolower($productoPrincipalRaw)] ?? $productoPrincipalRaw;
        $periodicidadPago = $contrato->periodicidad_cuota === 'anual' ? 'anual' : 'mensual';
        $descripcionServicio = 'Pago ' . strtoupper($periodicidadPago === 'anual' ? 'ANUAL' : 'MENSUAL') . ' por servicio de plataforma de software para alojamiento ' . $productoPrincipal;
        $baseServicio = collect($contrato->contratoProductoModulos)->sum('precio');
        $cuotas = $contrato->cuotas->sortBy('fecha_vencimiento')->values();
        $fechaInicioContrato = Carbon::parse($contrato->fecha_inicio);
        $fechaFinContrato = Carbon::parse($contrato->fecha_fin);
        $fechaContrato = $fechaInicioContrato->copy()->subDay();
        $mesesCantidad = $contrato->vigencia_contrato === 'anual'
            ? ((int) ($contrato->duracion_anios ?: 1) * 12)
            : 6;
        $mesesTexto = match ($mesesCantidad) {
            6 => 'seis (6)',
            12 => 'doce (12)',
            24 => 'veinticuatro (24)',
            36 => 'treinta y seis (36)',
            48 => 'cuarenta y ocho (48)',
            60 => 'sesenta (60)',
            default => $mesesCantidad . ' (' . $mesesCantidad . ')',
        };
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        $fechaInicioTexto = $fechaInicioContrato->format('d') . ' de ' . ucfirst($meses[(int) $fechaInicioContrato->format('n')]) . ' del ' . $fechaInicioContrato->format('Y');
        $fechaFinTexto = $fechaFinContrato->format('d') . ' de ' . ucfirst($meses[(int) $fechaFinContrato->format('n')]) . ' del ' . $fechaFinContrato->format('Y');
        $fechaFirmaMes = $meses[(int) $fechaContrato->format('n')];
        $cuotasTexto = match ($cuotas->count()) {
            1 => 'una', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
            6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
            11 => 'once', 12 => 'doce', default => (string) $cuotas->count(),
        };
        $montoTotalLetras = $this->amountToWords((float) $contrato->total);

        // Crear documento PhpWord
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'marginTop' => 1100,
            'marginBottom' => 1100,
            'marginLeft' => 1400,
            'marginRight' => 1400,
        ]);

        // Estilos de párrafo y fuente
        $pCenter = ['alignment' => Jc::CENTER, 'spaceAfter' => 60, 'spaceBefore' => 0];
        $pJustify = ['alignment' => Jc::BOTH, 'spaceAfter' => 80, 'spaceBefore' => 0, 'lineHeight' => 1.15];
        $fTitle = ['bold' => true, 'size' => 11, 'color' => '000000'];
        $fTitleUnderline = ['bold' => true, 'underline' => 'single', 'size' => 10.5, 'color' => '000000'];
        $fClauseTitle = ['bold' => true, 'underline' => 'single', 'size' => 10, 'color' => '000000'];
        $fBold = ['bold' => true];
        $fNormal = [];

        // Encabezado
        $section->addText('CONTRATO N° ' . $contrato->numero, $fTitle, $pCenter);
        $section->addText('CONTRATO DEL SERVICIO DE ARRENDAMIENTO DE LA PLATAFORMA DE SOFTWARE PARA', $fTitleUnderline, $pCenter);
        $section->addText('ALOJAMIENTO ' . strtoupper($productoPrincipal), $fTitleUnderline, $pCenter);
        $section->addTextBreak(1);

        // Preámbulo
        $pIntro = $section->addTextRun($pJustify);
        $pIntro->addText('Conste por el presente documento el contrato del servicio de arrendamiento de la plataforma de software para alojamiento ' . $productoPrincipal . ', que celebran de una parte ');
        $pIntro->addText($nombreEmisor, $fBold);
        $pIntro->addText(' con RUC N° ');
        $pIntro->addText($rucEmisor, $fBold);
        $pIntro->addText(', domicilio en ' . $direccionEmisor . ', y debidamente representada por su representante legal quien firma el presente documento, ');
        $pIntro->addText($representanteEmisor, $fBold);
        $pIntro->addText(' con DNI ' . $dniRepresentanteEmisor . ', en adelante ');
        $pIntro->addText('EL ARRENDADOR', $fBold);
        $pIntro->addText(' y de otra parte ');
        $pIntro->addText($nombreCliente, $fBold);
        $pIntro->addText(' con RUC N° ');
        $pIntro->addText($rucCliente, $fBold);
        $pIntro->addText(' representada por el señor ');
        $pIntro->addText($representanteCliente, $fBold);
        $pIntro->addText(' con DNI N° ');
        $pIntro->addText($dniCliente, $fBold);
        $pIntro->addText(', en adelante ');
        $pIntro->addText('EL CLIENTE', $fBold);
        $pIntro->addText(', en los términos y condiciones siguientes:');

        // CLÁUSULA PRIMERA
        $section->addText('CLÁUSULA PRIMERA: ANTECEDENTES', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $pCl1 = $section->addTextRun($pJustify);
        $pCl1->addText('Con fecha ' . $fechaContrato->format('d-m-Y') . ', ');
        $pCl1->addText('EL ARRENDADOR', $fBold);
        $pCl1->addText(' envió la cotización para el Arrendamiento de la plataforma de software para alojamiento ' . $productoPrincipal . ' para ');
        $pCl1->addText('EL CLIENTE', $fBold);
        $pCl1->addText(', cuyos detalles y totales, se detallan a continuación:');

        // Tabla de antecedentes
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMarginTop' => 60,
            'cellMarginBottom' => 60,
            'cellMarginLeft' => 80,
            'cellMarginRight' => 80,
            'alignment' => JcTable::CENTER,
        ];
        $table = $section->addTable($tableStyle);
        $table->addRow(280);
        $table->addCell(800)->addText('Item', $fBold, ['alignment' => Jc::CENTER]);
        $table->addCell(4600)->addText('Descripción', $fBold, ['alignment' => Jc::CENTER]);
        $table->addCell(1400)->addText('P.Unitario', $fBold, ['alignment' => Jc::CENTER]);
        $table->addCell(1000)->addText('Cantidad', $fBold, ['alignment' => Jc::CENTER]);
        $table->addCell(1400)->addText('Total', $fBold, ['alignment' => Jc::CENTER]);

        $table->addRow();
        $table->addCell(800)->addText('01', $fNormal, ['alignment' => Jc::CENTER]);
        $table->addCell(4600)->addText('Pago instalación del servicio de plataforma de software para alojamiento ' . $productoPrincipal, $fNormal);
        $table->addCell(1400)->addText('S/ 0.00', $fNormal, ['alignment' => Jc::CENTER]);
        $table->addCell(1000)->addText('1', $fNormal, ['alignment' => Jc::CENTER]);
        $table->addCell(1400)->addText('S/ 0.00', $fNormal, ['alignment' => Jc::CENTER]);

        $cantPeriodo = $periodicidadPago === 'anual' ? max(1, (int) $contrato->duracion_anios) : max(1, $cuotas->count());
        $table->addRow();
        $table->addCell(800)->addText('02', $fNormal, ['alignment' => Jc::CENTER]);
        $table->addCell(4600)->addText($descripcionServicio, $fNormal);
        $table->addCell(1400)->addText('S/ ' . number_format($baseServicio, 2, '.', ''), $fNormal, ['alignment' => Jc::CENTER]);
        $table->addCell(1000)->addText((string)$cantPeriodo, $fNormal, ['alignment' => Jc::CENTER]);
        $table->addCell(1400)->addText('S/ ' . number_format((float) $contrato->total, 2, '.', ''), $fNormal, ['alignment' => Jc::CENTER]);

        $table->addRow();
        $cellTotal = $table->addCell(9200, ['gridSpan' => 5]);
        $cellTotal->addText('TOTAL DEL CONTRATO CON IGV S/ ' . number_format((float) $contrato->total, 2, '.', ''), $fBold, ['alignment' => Jc::RIGHT]);

        // CLÁUSULA SEGUNDA
        $section->addText('CLÁUSULA SEGUNDA: OBJETO', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $pCl2 = $section->addTextRun($pJustify);
        $pCl2->addText('El presente proceso contrato tiene por objeto el Arrendamiento de la plataforma de software para alojamiento ' . $productoPrincipal . ' para ');
        $pCl2->addText('EL CLIENTE', $fBold);
        $pCl2->addText('.');

        foreach ($contrato->contratoProductoModulos as $item) {
            $section->addListItem('Módulo ' . ($item->modulo?->nombre ?? 'Servicio'), 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        }

        // CLÁUSULA TERCERA
        $section->addText('CLÁUSULA TERCERA: MONTO CONTRACTUAL', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $pCl3 = $section->addTextRun($pJustify);
        $pCl3->addText('El monto total del arrendamiento materia del presente contrato asciende a ');
        $pCl3->addText('S/ ' . number_format((float) $contrato->total, 2, '.', '') . ' (' . $montoTotalLetras . ')', $fBold);
        $pCl3->addText(', incluido el IGV, ');
        if ($contrato->forma_pago === 'parcial') {
            $cuotaMonto = number_format((float) ($cuotas->first()->monto ?? 0), 2, '.', '');
            $pCl3->addText('el cual será cancelado en ' . $cuotasTexto . ' (' . $cuotas->count() . ') cuota' . ($cuotas->count() === 1 ? '' : 's') . ' ' . $periodicidadPago . ($cuotas->count() === 1 ? '' : 'es') . ' de S/ ' . $cuotaMonto . ' soles.');
        } else {
            $pCl3->addText('el cual será cancelado en un solo pago.');
        }

        // CLÁUSULA CUARTA
        $section->addText('CLÁUSULA CUARTA: FORMA DE PAGO', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $pCl4 = $section->addTextRun($pJustify);
        $pCl4->addText('EL CLIENTE', $fBold);
        $pCl4->addText(' se obliga a pagar la contraprestación del servicio en modalidad ' . $periodicidadPago . ' a ');
        $pCl4->addText('EL ARRENDADOR', $fBold);
        $pCl4->addText(' en moneda Soles luego de la firma del presente contrato y antes de iniciar el uso de la plataforma ' . $productoPrincipal . ', mediante depósito en cuenta bancaria de la empresa ');
        $pCl4->addText('EL ARRENDADOR', $fBold);
        $pCl4->addText('.');

        $section->addText('Cuentas a nombre de ' . $nombreEmisor, $fBold, ['spaceBefore' => 60, 'spaceAfter' => 20]);
        $section->addText('BANCO CONTINENTAL DEL PERÚ (BBVA)', $fBold, ['spaceAfter' => 10]);
        $section->addText('Cuenta en soles: 0011-0442-0200095395-16', $fNormal, ['spaceAfter' => 10]);
        $section->addText('Cuenta CCI en soles: 011-442-000200095395-16', $fNormal, ['spaceAfter' => 40]);
        $section->addText('BANCO DE CRÉDITO DEL PERÚ (BCP)', $fBold, ['spaceAfter' => 10]);
        $section->addText('Cuenta en soles: 415-2646186-0-69', $fNormal, ['spaceAfter' => 10]);
        $section->addText('Cuenta CCI en soles: 00241500264618606989', $fNormal, ['spaceAfter' => 60]);

        // CLÁUSULA QUINTA
        $section->addText('CLÁUSULA QUINTA: INICIO Y CULMINACIÓN DE LA PRESTACIÓN', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $section->addText('La vigencia del presente contrato se extenderá a partir del día siguiente a su suscripción hasta por un tiempo de ' . $mesesTexto . ' meses.', $fNormal, $pJustify);
        $pCl5_2 = $section->addTextRun($pJustify);
        $pCl5_2->addText('Quedando definido que el plazo de arrendamiento empezará a computarse desde el día ');
        $pCl5_2->addText($fechaInicioTexto, $fBold);
        $pCl5_2->addText(' y culminará el ');
        $pCl5_2->addText($fechaFinTexto, $fBold);
        $pCl5_2->addText('.');

        // CLÁUSULA SEXTA
        $section->addText('CLÁUSULA SEXTA: DOCUMENTOS MATERIA DEL CONTRATO', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $pCl6 = $section->addTextRun($pJustify);
        $pCl6->addText('El presente contrato está conformado por la cotización aceptada por ');
        $pCl6->addText('EL CLIENTE', $fBold);
        $pCl6->addText('.');

        // CLÁUSULA SÉPTIMA
        $section->addText('CLÁUSULA SÉPTIMA: CONFORMIDAD DE LOS BIENES', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $pCl7_1 = $section->addTextRun($pJustify);
        $pCl7_1->addText('La conformidad a la recepción de la prestación a cargo de ');
        $pCl7_1->addText('EL CLIENTE', $fBold);
        $pCl7_1->addText(' será dada por la Gerencia General o el Representante Legal.');
        $section->addText('De existir observaciones en la conformidad del servicio materia de este contrato, se consignarán en el acta respectiva, indicándose claramente el sentido de estas, dándose a EL ARRENDADOR plazo prudencial para su subsanación, en función a la complejidad.', $fNormal, $pJustify);
        $section->addText('Si pese al plazo otorgado, EL ARRENDADOR no cumpliese a cabalidad con la subsanación, EL CLIENTE podrá resolver el contrato, sin perjuicio de aplicar las penalidades que correspondan.', $fNormal, $pJustify);

        // CLÁUSULA OCTAVA
        $section->addText('CLÁUSULA OCTAVA: RESPONSABILIDADES DE EL ARRENDADOR Y EL CLIENTE', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $pCl8_1 = $section->addTextRun($pJustify);
        $pCl8_1->addText('EL ARRENDADOR', $fBold);
        $pCl8_1->addText(' tendrá las siguientes responsabilidades:');
        $section->addListItem('Instalar y configurar la plataforma de software para alojamiento ' . $productoPrincipal . ' en los equipos que indique EL CLIENTE para los módulos contratados.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        $section->addListItem('Ofrecer un nivel de atención de servicio ante fallas, no mayor a cuarenta y ocho (48) horas de reportado el incidente, en horario de lunes a sábado de 09:00 a 18:00 horas.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        $section->addListItem('Poner a disposición del cliente la URL https://hotelhub.com.pe para uso de la plataforma de software para alojamiento ' . $productoPrincipal . '.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        $section->addListItem('Poner a disposición del cliente la URL https://comprobante-e.com para consulta de sus clientes de los comprobantes electrónicos de venta emitidos y consulta del contador en rango de fechas.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        $section->addListItem('Almacenar en su servidor los datos resultado del uso de la plataforma de software para alojamiento ' . $productoPrincipal . ' por el plazo de duración de este contrato.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        $section->addListItem('Cuando EL CLIENTE tenga retraso en el pago, EL ARRENDADOR puede suspender el servicio, sin perjuicio de aplicar las penalidades que correspondan.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 40]);

        $pCl8_2 = $section->addTextRun($pJustify);
        $pCl8_2->addText('EL CLIENTE', $fBold);
        $pCl8_2->addText(' tendrá las siguientes responsabilidades:');
        $section->addListItem('Contar con una conexión a Internet adecuada que asegure la correcta operación de la plataforma de software para alojamiento ' . $productoPrincipal . '.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        $section->addListItem('Informar con un plazo no mayor a veinticuatro (24) horas sobre incidencias en el funcionamiento de la plataforma que impidan su correcto funcionamiento.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        $section->addListItem('Garantizar y custodiar el correcto funcionamiento de los equipos de cómputo como computadoras e impresoras que garanticen el funcionamiento de la plataforma.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 20]);
        $section->addListItem('Realizar el pago por el servicio dentro de los plazos establecidos en la Cláusula Tercera de este contrato.', 0, $fNormal, ['listType' => ListItem::TYPE_BULLET_FILLED], ['spaceAfter' => 40]);

        // CLÁUSULA NOVENA
        $section->addText('CLÁUSULA NOVENA: RESOLUCIÓN DEL CONTRATO', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $section->addText('Constituirán causales de resolución del presente contrato las siguientes:', $fNormal, $pJustify);
        $section->addText('1. El acuerdo mutuo de ambas partes.', $fNormal, $pJustify);
        $section->addText('2. Cuando EL CLIENTE tenga retraso en el pago en reiteradas oportunidades EL ARRENDADOR puede finalizar el contrato, estando obligado EL CLIENTE al pago íntegro de los saldos del presente contrato.', $fNormal, $pJustify);

        // CLÁUSULA DÉCIMA
        $section->addText('CLÁUSULA DÉCIMA: DE LA CONFIDENCIALIDAD', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $pCl10 = $section->addTextRun($pJustify);
        $pCl10->addText('EL ARRENDADOR', $fBold);
        $pCl10->addText(' guardará confidencialidad sobre la información que le facilite ');
        $pCl10->addText('EL CLIENTE', $fBold);
        $pCl10->addText(' en o para la ejecución del contrato o que por su propia naturaleza deba ser tratada como tal. Se excluye de la categoría de información confidencial toda aquella información que sea divulgada por EL CLIENTE, aquella que haya de ser revelada de acuerdo con las leyes o con una resolución judicial o acto de autoridad competente. Este deber se mantendrá aún con posterioridad a la finalización del servicio.');

        // CLÁUSULA DÉCIMO PRIMERA
        $section->addText('CLÁUSULA DÉCIMO PRIMERA: MARCO LEGAL DEL CONTRATO', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $section->addText('Solo en lo no previsto en este contrato y demás normativa especial que resulte aplicable, se utilizarán las disposiciones pertinentes del Código Civil vigente y demás normas concordantes.', $fNormal, $pJustify);

        // CLÁUSULA DÉCIMO SEGUNDA
        $section->addText('CLÁUSULA DÉCIMO SEGUNDA: SOLUCIÓN DE CONTROVERSIAS', $fClauseTitle, ['spaceBefore' => 120, 'spaceAfter' => 40]);
        $section->addText('Todos los conflictos que se deriven de la ejecución e interpretación del presente contrato, incluidos los que se refieran a su nulidad e invalidez, serán resueltos de manera definitiva e inapelable mediante arbitraje de derecho.', $fNormal, $pJustify);
        $section->addText('Facultativamente, cualquiera de las partes podrá someter a conciliación la referida controversia, sin perjuicio de recurrir al arbitraje en caso no se llegue a un acuerdo entre ambas.', $fNormal, $pJustify);
        $section->addText('De acuerdo con la cotización, las partes lo firman por duplicado en señal de conformidad en la ciudad de Chiclayo a los ' . $fechaContrato->format('d') . ' días del mes de ' . $fechaFirmaMes . ' del ' . $fechaContrato->format('Y') . '.', $fNormal, $pJustify);

        // Firmas
        $section->addTextBreak(2);
        $sigTable = $section->addTable(['alignment' => JcTable::CENTER, 'borderSize' => 0]);
        $sigTable->addRow(1600);

        $tempFilesToDelete = [];

        // Celda Arrendador
        $cellArrendador = $sigTable->addCell(4500, ['valign' => 'bottom']);
        if (!empty($firmaArrendador)) {
            $tmpImg = $this->saveBase64ToTempFile($firmaArrendador);
            if ($tmpImg) {
                $cellArrendador->addImage($tmpImg, ['width' => 140, 'height' => 60, 'alignment' => Jc::CENTER]);
                $tempFilesToDelete[] = $tmpImg;
            }
        }
        $cellArrendador->addText('______________________________', $fNormal, ['alignment' => Jc::CENTER]);
        $cellArrendador->addText('EL ARRENDADOR', $fBold, ['alignment' => Jc::CENTER]);

        // Celda Cliente
        $cellCliente = $sigTable->addCell(4500, ['valign' => 'bottom']);
        if (!empty($firmaCliente)) {
            $tmpImg = $this->saveBase64ToTempFile($firmaCliente);
            if ($tmpImg) {
                $cellCliente->addImage($tmpImg, ['width' => 140, 'height' => 60, 'alignment' => Jc::CENTER]);
                $tempFilesToDelete[] = $tmpImg;
            }
        }
        $cellCliente->addText('______________________________', $fNormal, ['alignment' => Jc::CENTER]);
        $cellCliente->addText('EL CLIENTE', $fBold, ['alignment' => Jc::CENTER]);

        // Guardar a archivo temporal y descargar
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $tempDocx = tempnam(sys_get_temp_dir(), 'contrato_') . '.docx';
        $objWriter->save($tempDocx);

        // Limpiar imágenes temporales
        foreach ($tempFilesToDelete as $file) {
            @unlink($file);
        }

        $fileContent = file_get_contents($tempDocx);
        @unlink($tempDocx);

        return response($fileContent, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="contrato-' . $contrato->numero . '.docx"',
        ]);
    }

    private function saveBase64ToTempFile(?string $dataUri): ?string
    {
        if (!$dataUri) return null;
        if (preg_match('/^data:image\/(\w+);base64,/', $dataUri, $type)) {
            $data = substr($dataUri, strpos($dataUri, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                $type = 'png';
            }
            $data = base64_decode($data);
            if ($data === false) return null;

            $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.' . $type;
            file_put_contents($tempFile, $data);
            return $tempFile;
        } elseif (filter_var($dataUri, FILTER_VALIDATE_URL)) {
            $content = @file_get_contents($dataUri);
            if ($content) {
                $tempFile = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
                file_put_contents($tempFile, $content);
                return $tempFile;
            }
        }
        return null;
    }

    public function guardarFirmas(Request $request, $id)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $contrato = Contrato::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'firma_arrendador' => 'nullable|string',
            'firma_cliente' => 'nullable|string',
            'guardar_como_default_arrendador' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $contrato->update([
            'firma_arrendador' => array_key_exists('firma_arrendador', $validated) ? $validated['firma_arrendador'] : $contrato->firma_arrendador,
            'firma_cliente' => array_key_exists('firma_cliente', $validated) ? $validated['firma_cliente'] : $contrato->firma_cliente,
        ]);

        if (!empty($validated['guardar_como_default_arrendador']) && !empty($validated['firma_arrendador'])) {
            $facturador = Facturador::where('activo', true)->latest()->first() ?? Facturador::latest()->first();
            if ($facturador) {
                $facturador->update([
                    'firma_arrendador_default' => $validated['firma_arrendador'],
                ]);
            }
        }

        return response()->json([
            'status' => 200,
            'message' => 'Firmas registradas correctamente.',
            'data' => new ContratoResource($contrato->fresh()),
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        if (!$request->filled('numero')) {
            $year = null;
            if ($request->filled('fecha_inicio')) {
                try {
                    $year = Carbon::parse($request->input('fecha_inicio'))->year;
                } catch (\Throwable) {
                    $year = null;
                }
            }
            $request->merge(['numero' => $this->generarSiguienteNumero($year)]);
        }

        $validator = Validator::make($request->all(), $this->contractRules(), $this->contractMessages());
        $this->appendContractValidation($validator, $request);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $contrato = Contrato::create($this->extractContratoAttributes($request));

            $productosModulos = $this->mapProductosModulos($request->input('productos_modulos', []));
            if (!empty($productosModulos)) {
                $contrato->contratoProductoModulos()->createMany($productosModulos);
            }

            if ($request->input('forma_pago') === 'parcial') {
                $cuotas = $this->mapCuotas($request->input('cuotas', []));
                if (!empty($cuotas)) {
                    $contrato->cuotas()->createMany($cuotas);
                }
            }

            DB::commit();

            $contrato->load([
                'cliente',
                'cuotas',
                'contratoProductoModulos.modulo',
                'contratoProductoModulos.producto',
            ]);

            return response()->json([
                'status' => 201,
                'message' => 'Contrato creado exitosamente',
                'data' => new ContratoResource($contrato),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al registrar el contrato',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $contrato = Contrato::find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->contractRules($contrato->id, true), $this->contractMessages());
        $this->appendContractValidation($validator, $request, true);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $contrato->update($this->extractContratoAttributes($request, true));

            if ($request->has('productos_modulos')) {
                $contrato->contratoProductoModulos()->delete();
                $productosModulos = $this->mapProductosModulos($request->input('productos_modulos', []));
                if (!empty($productosModulos)) {
                    $contrato->contratoProductoModulos()->createMany($productosModulos);
                }
            }

            $formaPago = $request->input('forma_pago', $contrato->forma_pago);
            if ($formaPago === 'unico') {
                $contrato->cuotas()->delete();
            } elseif ($request->has('cuotas')) {
                $contrato->cuotas()->delete();
                $cuotas = $this->mapCuotas($request->input('cuotas', []));
                if (!empty($cuotas)) {
                    $contrato->cuotas()->createMany($cuotas);
                }
            }

            DB::commit();

            $contrato->load([
                'cliente',
                'cuotas',
                'contratoProductoModulos.modulo',
                'contratoProductoModulos.producto',
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Contrato actualizado correctamente',
                'data' => new ContratoResource($contrato),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 500,
                'message' => 'Error al actualizar el contrato',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()?->cliente_id) {
            return response()->json(['status' => 403, 'message' => 'No autorizado'], 403);
        }

        $contrato = Contrato::find($id);

        if (!$contrato) {
            return response()->json([
                'status' => 404,
                'message' => 'Contrato no encontrado',
            ], 404);
        }

        if ($contrato->estado === 'anulado') {
            return response()->json([
                'status' => 422,
                'message' => 'El contrato ya se encuentra anulado.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'motivo_anulacion' => 'nullable|string',
            'fecha_anulacion' => 'required|date',
        ], [
            'fecha_anulacion.required' => 'La fecha de anulacion es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $contrato->update([
            'estado' => 'anulado',
            'motivo_anulacion' => $request->input('motivo_anulacion'),
            'fecha_anulacion' => $request->input('fecha_anulacion'),
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Contrato anulado correctamente',
        ], 200);
    }

    private function contractRules(?int $contractId = null, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : 'required|';

        return [
            'fecha_inicio' => $prefix . 'date',
            'fecha_fin' => $prefix . 'date|after:fecha_inicio',
            'numero' => [
                $partial ? 'sometimes' : 'required',
                'string',
                Rule::unique('contratos', 'numero')->ignore($contractId),
            ],
            'cliente_id' => $prefix . 'exists:clientes,id',
            'tipo_contrato' => $prefix . 'string|in:desarrollo,saas,soporte',
            'vigencia_contrato' => $prefix . 'string|in:semestral,anual',
            'duracion_anios' => 'nullable|integer|min:1',
            'total' => $prefix . 'numeric|min:0',
            'forma_pago' => $prefix . 'string|in:unico,parcial',
            'periodicidad_cuota' => $prefix . 'string|in:mensual,anual',
            'productos_modulos' => 'nullable|array',
            'productos_modulos.*.producto_id' => 'required_with:productos_modulos|exists:productos,id',
            'productos_modulos.*.modulo_id' => 'required_with:productos_modulos|exists:modulos,id',
            'productos_modulos.*.precio' => 'required_with:productos_modulos|numeric|min:0',
            'cuotas' => 'nullable|array',
            'cuotas.*.monto' => 'required_with:cuotas|numeric|min:0.01',
            'cuotas.*.fecha_vencimiento' => 'required_with:cuotas|date',
            'cuotas.*.situacion' => 'nullable|in:pendiente,pagado,vencido',
        ];
    }

    private function contractMessages(): array
    {
        return [
            'forma_pago.in' => 'La forma de pago debe ser unico o parcial.',
            'vigencia_contrato.in' => 'La vigencia del contrato debe ser semestral o anual.',
            'periodicidad_cuota.in' => 'El tipo de pago solo puede ser mensual o anual.',
        ];
    }

    private function appendContractValidation($validator, Request $request, bool $partial = false): void
    {
        $validator->after(function ($validator) use ($request, $partial) {
            $tipoContrato = $request->input('tipo_contrato');
            $formaPago = $request->input('forma_pago');
            $productos = $request->input('productos_modulos', []);
            $cuotas = $request->input('cuotas', []);

            if ($tipoContrato === 'saas' && empty($productos)) {
                $validator->errors()->add('productos_modulos', 'Para contratos SaaS debe seleccionar al menos un producto.');
            }

            if (!$request->filled('periodicidad_cuota')) {
                $validator->errors()->add('periodicidad_cuota', 'Debe seleccionar el tipo de pago del contrato.');
            }

            if ($request->input('vigencia_contrato') === 'anual' && (int) $request->input('duracion_anios', 0) < 1) {
                $validator->errors()->add('duracion_anios', 'Debe indicar al menos 1 año de duración.');
            }

            if ($request->input('vigencia_contrato') === 'semestral' && $request->input('periodicidad_cuota') === 'anual') {
                $validator->errors()->add('periodicidad_cuota', 'Un contrato semestral no puede tener pago anual.');
            }

            if ($formaPago === 'parcial' && empty($cuotas) && !$partial) {
                $validator->errors()->add('cuotas', 'Debe registrar al menos una cuota para pago parcial.');
            }
        });
    }

    private function extractContratoAttributes(Request $request, bool $partial = false): array
    {
        $attributes = $request->only([
            'fecha_inicio',
            'fecha_fin',
            'numero',
            'cliente_id',
            'tipo_contrato',
            'vigencia_contrato',
            'duracion_anios',
            'total',
            'forma_pago',
            'periodicidad_cuota',
        ]);

        if (($request->input('forma_pago') ?? null) === 'unico') {
            $attributes['periodicidad_cuota'] = $request->input('periodicidad_cuota');
        }

        if (($request->input('vigencia_contrato') ?? null) === 'semestral') {
            $attributes['duracion_anios'] = 1;
        }

        if (!$partial) {
            $attributes['estado'] = 'activo';
            $attributes['motivo_anulacion'] = null;
            $attributes['fecha_anulacion'] = null;
        }

        return $attributes;
    }

    private function mapProductosModulos(array $productosModulos): array
    {
        return collect($productosModulos)
            ->filter(fn($pm) => isset($pm['producto_id'], $pm['modulo_id'], $pm['precio']))
            ->map(fn($pm) => [
                'producto_id' => (int) $pm['producto_id'],
                'modulo_id' => (int) $pm['modulo_id'],
                'precio' => (float) $pm['precio'],
            ])
            ->values()
            ->all();
    }

    private function mapCuotas(array $cuotas): array
    {
        return collect($cuotas)
            ->filter(fn($c) => isset($c['monto'], $c['fecha_vencimiento']))
            ->map(fn($c) => [
                'monto' => (float) $c['monto'],
                'fecha_vencimiento' => $c['fecha_vencimiento'],
                'situacion' => $c['situacion'] ?? 'pendiente',
                'fecha_pago' => $c['fecha_pago'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function buildClientHierarchy(?Cliente $cliente): array
    {
        $linea = [];
        $cursor = $cliente;

        while ($cursor) {
            array_unshift($linea, $cursor);
            $cursor = $cursor->parent_cliente;
        }

        $root = $linea[0] ?? $cliente;
        $empresa = collect($linea)->first(fn($item) => $item->tipo === 'empresa');
        $local = collect($linea)->reverse()->first(fn($item) => $item->tipo === 'local') ?? $cliente;

        return [
            'linea' => $linea,
            'root' => $root,
            'empresa' => $empresa,
            'local' => $local,
        ];
    }

    private function resolveVigenciaDescription(Contrato $contrato): string
    {
        if ($contrato->vigencia_contrato === 'semestral') {
            return 'seis (6) meses';
        }

        $anios = max(1, (int) ($contrato->duracion_anios ?: 1));

        return $anios === 1
            ? 'un (1) ano'
            : $anios . ' anos';
    }

    private function resolveContractTypeDescription(string $tipo): string
    {
        return match ($tipo) {
            'desarrollo' => 'desarrollo de software',
            'soporte' => 'soporte tecnico',
            default => 'licenciamiento y alquiler de software SaaS',
        };
    }

    private function formatDateLong($date): string
    {
        if (!$date) {
            return '-';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        return $carbon->day . ' de ' . $meses[(int) $carbon->month] . ' de ' . $carbon->year;
    }

    private function formatMoney(float $amount): string
    {
        return 'S/ ' . number_format($amount, 2, '.', ',');
    }

    private function amountToWords(float $amount): string
    {
        $enteros = (int) floor($amount);
        $centimos = (int) round(($amount - $enteros) * 100);

        return strtoupper(trim($this->numberToSpanish($enteros))) . ' CON ' . str_pad((string) $centimos, 2, '0', STR_PAD_LEFT) . '/100 SOLES';
    }

    private function numberToSpanish(int $number): string
    {
        $units = [
            0 => 'cero', 1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro',
            5 => 'cinco', 6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve',
            10 => 'diez', 11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce',
            15 => 'quince', 16 => 'dieciseis', 17 => 'diecisiete', 18 => 'dieciocho',
            19 => 'diecinueve', 20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidos',
            23 => 'veintitres', 24 => 'veinticuatro', 25 => 'veinticinco', 26 => 'veintiseis',
            27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve',
        ];

        $tens = [
            30 => 'treinta', 40 => 'cuarenta', 50 => 'cincuenta',
            60 => 'sesenta', 70 => 'setenta', 80 => 'ochenta', 90 => 'noventa',
        ];

        $hundreds = [
            100 => 'cien', 200 => 'doscientos', 300 => 'trescientos',
            400 => 'cuatrocientos', 500 => 'quinientos', 600 => 'seiscientos',
            700 => 'setecientos', 800 => 'ochocientos', 900 => 'novecientos',
        ];

        if ($number < 30) {
            return $units[$number];
        }

        if ($number < 100) {
            $base = intdiv($number, 10) * 10;
            $resto = $number % 10;
            return $resto === 0 ? $tens[$base] : $tens[$base] . ' y ' . $this->numberToSpanish($resto);
        }

        if ($number < 1000) {
            if ($number === 100) {
                return 'cien';
            }

            $base = intdiv($number, 100) * 100;
            $resto = $number % 100;
            $prefijo = $base === 100 ? 'ciento' : $hundreds[$base];
            return $resto === 0 ? $prefijo : $prefijo . ' ' . $this->numberToSpanish($resto);
        }

        if ($number < 1000000) {
            $miles = intdiv($number, 1000);
            $resto = $number % 1000;
            $prefijo = $miles === 1 ? 'mil' : $this->numberToSpanish($miles) . ' mil';
            return $resto === 0 ? $prefijo : $prefijo . ' ' . $this->numberToSpanish($resto);
        }

        $millones = intdiv($number, 1000000);
        $resto = $number % 1000000;
        $prefijo = $millones === 1 ? 'un millon' : $this->numberToSpanish($millones) . ' millones';

        return $resto === 0 ? $prefijo : $prefijo . ' ' . $this->numberToSpanish($resto);
    }

    private function canAccessContrato(Request $request, Contrato $contrato): bool
    {
        $clienteIds = $this->accessibleClienteIds($request);

        return !$clienteIds || in_array((int) $contrato->cliente_id, $clienteIds, true);
    }

    private function accessibleClienteIds(Request $request): array
    {
        $clienteId = $request->user()?->cliente_id;

        if (!$clienteId) {
            return [];
        }

        $ids = [(int) $clienteId];
        $pending = [(int) $clienteId];

        while (!empty($pending)) {
            $children = Cliente::query()
                ->whereIn('parent_cliente_id', $pending)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $children)));
            $pending = $children;
        }

        return $ids;
    }

    private function getAllSubClientIds(int $clienteId): array
    {
        $ids = [$clienteId];
        $pending = [$clienteId];

        while (!empty($pending)) {
            $children = Cliente::query()
                ->whereIn('parent_cliente_id', $pending)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique(array_merge($ids, $children)));
            $pending = $children;
        }

        return $ids;
    }
}
