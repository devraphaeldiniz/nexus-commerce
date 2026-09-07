<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::query()->with(['items.product']);

        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('customer_email', $user->email);
            });
        } elseif ($request->filled('email')) {
            $query->where('customer_email', $request->input('email'));
        } else {
            // Em ambiente de teste/convidado, traz os pedidos mais recentes
            $query->limit(10);
        }

        $orders = $query->orderByDesc('created_at')->get();

        return response()->json($orders);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::with(['items.product'])->findOrFail($id);
        return response()->json($order);
    }
}
