<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $sellerProfile = DB::table('seller_profiles')->where('user_id', $user->id)->first();

        if (! $sellerProfile) {
            return response()->json([], 200);
        }

        $products = Product::where('seller_id', $sellerProfile->id)
            ->with('category')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $sellerProfile = DB::table('seller_profiles')->where('user_id', $user->id)->first();

        if (! $sellerProfile) {
            return response()->json(['message' => 'Perfil de vendedor não localizado.'], 404);
        }

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string',
            'category_id'          => 'required|exists:categories,id',
            'price_cents'          => 'required|integer|min:100',
            'original_price_cents' => 'nullable|integer|min:100',
            'stock_quantity'       => 'required|integer|min:0',
            'images'               => 'required|array|min:3',
            'images.*'             => 'required|string|url|max:500',
            'weight_grams'         => 'nullable|integer|min:1',
            'length_cm'            => 'nullable|integer|min:1',
            'width_cm'             => 'nullable|integer|min:1',
            'height_cm'            => 'nullable|integer|min:1',
        ]);

        $images = array_slice($validated['images'], 0, 8);
        $coverImage = substr($images[0], 0, 255);

        $product = Product::create([
            'id'                   => (string) Str::uuid(),
            'seller_id'            => $sellerProfile->id,
            'category_id'          => $validated['category_id'],
            'name'                 => $validated['name'],
            'slug'                 => Str::slug($validated['name']) . '-' . Str::lower(Str::random(6)),
            'description'          => $validated['description'] ?? '',
            'price_cents'          => $validated['price_cents'],
            'original_price_cents' => $validated['original_price_cents'] ?? null,
            'stock_quantity'       => $validated['stock_quantity'],
            'image_url'            => $coverImage,
            'images'               => $images,
            'weight_grams'         => $validated['weight_grams'] ?? 350,
            'length_cm'            => $validated['length_cm'] ?? 30,
            'width_cm'             => $validated['width_cm'] ?? 20,
            'height_cm'            => $validated['height_cm'] ?? 15,
        ]);

        return response()->json([
            'message' => 'Produto publicado com sucesso!',
            'product' => $product->load('category'),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $sellerProfile = DB::table('seller_profiles')->where('user_id', $user->id)->first();

        $product = Product::where('id', $id)
            ->where('seller_id', $sellerProfile ? $sellerProfile->id : null)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string',
            'category_id'          => 'required|exists:categories,id',
            'price_cents'          => 'required|integer|min:100',
            'original_price_cents' => 'nullable|integer|min:100',
            'stock_quantity'       => 'required|integer|min:0',
            'images'               => 'required|array|min:3',
            'images.*'             => 'required|string|url|max:500',
            'weight_grams'         => 'nullable|integer|min:1',
            'length_cm'            => 'nullable|integer|min:1',
            'width_cm'             => 'nullable|integer|min:1',
            'height_cm'            => 'nullable|integer|min:1',
        ]);

        $images = array_slice($validated['images'], 0, 8);

        $product->name = $validated['name'];
        $product->description = $validated['description'] ?? '';
        $product->category_id = $validated['category_id'];
        $product->price_cents = $validated['price_cents'];
        $product->original_price_cents = $validated['original_price_cents'] ?? null;
        $product->stock_quantity = $validated['stock_quantity'];
        $product->images = $images;
        $product->image_url = substr($images[0], 0, 255);
        $product->weight_grams = $validated['weight_grams'] ?? $product->weight_grams;
        $product->length_cm = $validated['length_cm'] ?? $product->length_cm;
        $product->width_cm = $validated['width_cm'] ?? $product->width_cm;
        $product->height_cm = $validated['height_cm'] ?? $product->height_cm;
        $product->save();

        return response()->json([
            'message' => 'Produto atualizado com sucesso!',
            'product' => $product->load('category'),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $sellerProfile = DB::table('seller_profiles')->where('user_id', $user->id)->first();

        $product = Product::where('id', $id)
            ->where('seller_id', $sellerProfile ? $sellerProfile->id : null)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Produto não localizado.'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'Produto removido com sucesso.']);
    }
}
