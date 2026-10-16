<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConfiguracionController extends Controller
{
    public function login(): JsonResponse
    {
        return response()->json([
            'nombre_empresa' => Configuracion::valor('nombre_empresa', config('app.name', 'Sistema POS e Inventario')),
        ]);
    }

    public function publicas(): JsonResponse
    {
        $serverNow = now();

        return response()->json([
            'nombre_empresa' => Configuracion::valor('nombre_empresa', config('app.name', 'Sistema POS e Inventario')),
            'tiempo_sesion' => (int) Configuracion::valor('tiempo_sesion', 1),
            'server_epoch_ms' => $serverNow->getTimestampMs(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->ensureDefaults();

        $items = Configuracion::query()
            ->orderBy('codigo')
            ->get([
                'id',
                'codigo',
                'descripcion',
                'value',
                'activo',
                'created_at',
                'updated_at',
                'last_modified_by_user_id',
                'last_modified_by_user_name',
            ]);

        return response()->json([
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:120', 'alpha_dash', 'unique:configuraciones,codigo'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            // La obligatoriedad depende del código y se valida en el
            // normalizador. Solo nit_empresa admite vacío; los demás códigos
            // conservan sus reglas obligatorias.
            'value' => ['present', 'nullable'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        $codigo = strtolower((string) $validated['codigo']);
        $valueNormalizado = $this->normalizarValorSegunCodigo($codigo, $validated['value']);

        $user = $request->user();

        $item = Configuracion::query()->create([
            'codigo' => $codigo,
            'descripcion' => $validated['descripcion'] ?? null,
            'value' => $valueNormalizado,
            'activo' => (bool) ($validated['activo'] ?? true),
            'last_modified_by_user_id' => $user?->id,
            'last_modified_by_user_name' => $user?->name ?? $user?->username,
        ]);

        return response()->json([
            'message' => 'Configuracion creada correctamente.',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, Configuracion $configuracion): JsonResponse
    {
        $validated = $request->validate([
            'value' => $configuracion->codigo === 'nit_empresa'
                ? ['present', 'nullable']
                : ['required'],
        ]);

        $valueNormalizado = $this->normalizarValorSegunCodigo($configuracion->codigo, $validated['value']);

        $user = $request->user();

        $configuracion->update([
            'value' => $valueNormalizado,
            'last_modified_by_user_id' => $user?->id,
            'last_modified_by_user_name' => $user?->name ?? $user?->username,
        ]);

        return response()->json([
            'message' => 'Configuracion actualizada correctamente.',
            'data' => $configuracion,
        ]);
    }

    public function toggle(Configuracion $configuracion): JsonResponse
    {
        $configuracion->update([
            'activo' => ! $configuracion->activo,
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $configuracion,
        ]);
    }

    public function destroy(Configuracion $configuracion): JsonResponse
    {
        $configuracion->delete();

        return response()->json([
            'message' => 'Configuracion eliminada correctamente.',
        ]);
    }

    private function normalizarValorSegunCodigo(string $codigo, mixed $value): string
    {
        $codigo = strtolower($codigo);
        $valorTexto = trim((string) $value);

        if ($codigo === 'nombre_empresa') {
            if ($valorTexto === '') {
                throw ValidationException::withMessages([
                    'value' => ['El nombre de empresa es obligatorio.'],
                ]);
            }

            return $valorTexto;
        }

        if ($codigo === 'nit_empresa') {
            $nit = preg_replace('/\D+/', '', $valorTexto) ?? '';

            if ($nit === '') {
                return '';
            }

            if (strlen($nit) > 30) {
                throw ValidationException::withMessages([
                    'value' => ['El NIT de empresa no puede superar 30 números.'],
                ]);
            }

            return $nit;
        }

        if ($codigo === 'habilitar_consignacion') {
            if (! in_array($valorTexto, ['0', '1'], true)) {
                throw ValidationException::withMessages(['value' => ['Use 1 para habilitar o 0 para deshabilitar consignación.']]);
            }
            return $valorTexto;
        }

        if (! preg_match('/^\d+$/', $valorTexto)) {
            throw ValidationException::withMessages([
                'value' => ['Este campo debe ser un numero entero.'],
            ]);
        }

        $valorEntero = (int) $valorTexto;

        if ($codigo === 'devolucion_limite_dias_cajero' && $valorEntero < 2) {
            throw ValidationException::withMessages([
                'value' => ['El limite de devolucion para cajero debe ser mayor o igual a 2 dias.'],
            ]);
        }

        if ($codigo === 'caja_aperturas_maximas_por_dia' && $valorEntero < 1) {
            throw ValidationException::withMessages([
                'value' => ['La cantidad maxima de aperturas por dia debe ser mayor o igual a 1.'],
            ]);
        }

        if (! in_array($codigo, ['devolucion_limite_dias_cajero', 'caja_aperturas_maximas_por_dia'], true) && $valorEntero < 0) {
            throw ValidationException::withMessages([
                'value' => ['Este valor debe ser mayor o igual a 0.'],
            ]);
        }

        return (string) $valorEntero;
    }

    private function ensureDefaults(): void
    {
        Configuracion::query()->updateOrCreate(
            ['codigo' => 'porcentaje_utilidad_compra'],
            [
                'descripcion' => 'Porcentaje de venta en compras para sugerir precio sobre el costo.',
                'value' => Configuracion::query()->where('codigo', 'porcentaje_utilidad_compra')->value('value') ?? '25',
                'activo' => true,
            ]
        );

        Configuracion::query()->updateOrCreate(
            ['codigo' => 'habilitar_consignacion'],
            ['descripcion' => 'Habilita compras en consignación y cuentas por pagar a proveedores.', 'value' => Configuracion::query()->where('codigo', 'habilitar_consignacion')->value('value') ?? '0', 'activo' => true]
        );

        Configuracion::query()->updateOrCreate(
            ['codigo' => 'nit_empresa'],
            ['descripcion' => 'NIT al que deben emitirse las facturas de compra con crédito fiscal.', 'value' => Configuracion::query()->where('codigo', 'nit_empresa')->value('value') ?? '', 'activo' => true]
        );

        Configuracion::query()->updateOrCreate(
            ['codigo' => 'caja_aperturas_maximas_por_dia'],
            [
                'descripcion' => 'Cantidad maxima de aperturas de caja permitidas por usuario en un dia.',
                'value' => Configuracion::query()->where('codigo', 'caja_aperturas_maximas_por_dia')->value('value') ?? '1',
                'activo' => true,
            ]
        );
    }
}
