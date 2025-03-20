<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Response\ApiResponse;
use App\Models\CtlInventario;
use App\Models\CtlInventerio;
use App\Models\CtlProductos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CtlProductosController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = CtlProductos::with(['categoria:id,nombre', 'inventario'])
                ->where('activo', true); // Solo productos activos

            // Filtros
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('name')) {
                $query->where('nombre', 'LIKE', '%' . $request->name . '%');
            }

            $products = $query->paginate(10);

            return ApiResponse::success('Lista de productos', 200, $products);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $messages = [
                "nombre.required" => "Nombre es requerido",
                "nombre.max" => "El nombre no debe exceder los 255 caracteres",
                "nombre.unique" => "El nombre ya existe",
                "precio.required" => "El precio es requerido",
                "precio.numeric" => "El precio debe ser un número",
                "image.required" => "La imagen es requerida",
                "cantidad.required" => "La cantidad es requerida",
                "cantidad.numeric" => "La cantidad debe ser un número"
            ];

            $validator = Validator::make($request->all(), [
                "nombre" => "required|max:255|unique:ctl_productos,nombre",
                "precio" => "required|numeric",
                "image" => "required",
                "cantidad" => "required|numeric"
            ], $messages);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();

            $producto = new CtlProductos();
            $producto->fill($request->all());

            if ($producto->save()) {
                $inventario = new CtlInventerio();
                $inventario->cantidad = $request->cantidad;
                $inventario->product_id = $producto->id;

                if ($inventario->save()) {
                    DB::commit();
                    return ApiResponse::success('Producto creado con éxito', 201, $producto);
                }
            }

            DB::rollBack();
            return ApiResponse::error('No se pudo crear el producto');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error($e->getMessage());
        }
    }

    public function updateInventario(Request $request, $id)
    {
        try {
            $inventario = CtlInventerio::find($id);

            if (!$inventario) {
                return ApiResponse::error('Inventario no encontrado', 404);
            }

            $nuevaCantidad = $inventario->cantidad + $request->cantidad;

            if ($nuevaCantidad < 0) {
                return ApiResponse::error('No se puede reducir la cantidad por debajo de 0', 422);
            }

            $inventario->cantidad = $nuevaCantidad;

            if ($inventario->save()) {
                return ApiResponse::success('Inventario actualizado', 200, $inventario);
            }

            return ApiResponse::error('No se pudo actualizar el inventario', 500);

        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function deleteProducto($id)
    {
        try {
            $producto = CtlProductos::find($id);

            if (!$producto) {
                return ApiResponse::error('Producto no encontrado', 404);
            }

            $producto->activo = !$producto->activo;

            if ($producto->save()) {
                return ApiResponse::success('Estado del producto actualizado', 200, $producto);
            }

            return ApiResponse::error('No se pudo actualizar el estado del producto', 500);

        } catch (\Throwable $th) {
            return ApiResponse::error($th->getMessage(), 500);
        }
    }
}
