<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SellerProductController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $seller = SellerProfile::where('user_id', $user->id)->first();

        if (!$seller) {
            return response()->json([]);
        }

        $products = Product::where('seller_id', $seller->id)->latest()->get();
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $seller = SellerProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'store_name'      => $user->role === 'ADMIN' ? 'Nexus Oficial Store' : "Loja de {$user->name}",
                'legal_name'      => $user->name,
                'document_type'   => 'CPF',
                'document_number' => $user->cpf ?? '00000000000',
            ]
        );

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0.01',
            'stock_quantity' => 'required|integer|min:0',
            'category_id'    => 'nullable',
            'image_url'      => 'nullable|url',
        ]);

        $rawCat = $request->input('category_id');
        $categoryId = null;

        if ($rawCat && is_string($rawCat) && Str::isUuid($rawCat)) {
            $categoryId = $rawCat;
        } else {
            $cat = Category::first();
            if (!$cat) {
                $cat = Category::create([
                    'name' => 'Geral',
                    'slug' => 'geral',
                ]);
            }
            $categoryId = $cat->id;
        }

        $name = $validated['title'];
        $baseSlug = Str::slug($name);
        $slug = $baseSlug . '-' . Str::lower(Str::random(6));

        // Converte valor float em centavos inteiros (R$ 1949.99 => 194999)
        $priceCents = (int) round(((float) $validated['price']) * 100);

        $product = Product::create([
            'seller_id'      => $seller->id,
            'category_id'    => $categoryId,
            'name'           => $name,
            'slug'           => $slug,
            'description'    => $validated['description'] ?? '',
            'price_cents'    => $priceCents,
            'stock_quantity' => $validated['stock_quantity'],
            'image_url'      => $validated['image_url'] ?? null,
            'weight_grams'   => 500,
            'height_cm'      => 10,
            'width_cm'       => 15,
            'length_cm'      => 20,
        ]);

        return response()->json([
            'message' => 'Produto anunciado com sucesso!',
            'product' => $product
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $seller = SellerProfile::where('user_id', $user->id)->first();

        $query = Product::where('id', $id);
        if ($user->role !== 'ADMIN') {
            if (!$seller) return response()->json(['message' => 'Perfil de vendedor não localizado'], 404);
            $query->where('seller_id', $seller->id);
        }

        $product = $query->firstOrFail();

        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'price'          => 'sometimes|numeric|min:0.01',
            'stock_quantity' => 'sometimes|integer|min:0',
            'category_id'    => 'nullable',
            'image_url'      => 'nullable|url',
        ]);

        $updateData = [];

        if (isset($validated['title'])) {
            $updateData['name'] = $validated['title'];
        }
        if (isset($validated['description'])) {
            $updateData['description'] = $validated['description'];
        }
        if (isset($validated['price'])) {
            $updateData['price_cents'] = (int) round(((float) $validated['price']) * 100);
        }
        if (isset($validated['stock_quantity'])) {
            $updateData['stock_quantity'] = $validated['stock_quantity'];
        }
        if (isset($validated['image_url'])) {
            $updateData['image_url'] = $validated['image_url'];
        }
        if (isset($validated['category_id']) && is_string($validated['category_id']) && Str::isUuid($validated['category_id'])) {
            $updateData['category_id'] = $validated['category_id'];
        }

        $product->update($updateData);

        return response()->json([
            'message' => 'Produto atualizado com sucesso!',
            'product' => $product
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $seller = SellerProfile::where('user_id', $user->id)->first();

        $query = Product::where('id', $id);
        if ($user->role !== 'ADMIN') {
            if (!$seller) return response()->json(['message' => 'Acesso negado'], 403);
            $query->where('seller_id', $seller->id);
        }

        $product = $query->firstOrFail();
        $product->delete();

        return response()->json(['message' => 'Produto excluído com sucesso!']);
    }
}
