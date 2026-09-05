<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Cache::remember('catalog:categories', 3600, function () {
            return Category::all();
        });

        return response()->json($categories);
    }

    public function products(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        $categorySlug = $request->query('category');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $minReputation = $request->query('min_reputation');
        $sortBy = $request->query('sort', 'relevance');

        // Chave de cache dinâmica baseada na query string
        $cacheKey = 'catalog:products:' . md5(json_encode($request->query()));

        $products = Cache::remember($cacheKey, 60, function () use (
            $q, $categorySlug, $minPrice, $maxPrice, $minReputation, $sortBy
        ) {
            $query = Product::with(['category', 'seller:id,store_name,reputation_score,kyc_status'])
                ->where('stock_quantity', '>', 0)
                ->whereHas('seller', function ($sellerQuery) use ($minReputation) {
                    $sellerQuery->where('kyc_status', 'APPROVED');
                    if (! empty($minReputation)) {
                        $sellerQuery->where('reputation_score', '>=', (float) $minReputation);
                    }
                });

            if (! empty($q)) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'ILIKE', "%{$q}%")
                        ->orWhere('description', 'ILIKE', "%{$q}%");
                });
            }

            if (! empty($categorySlug)) {
                $query->whereHas('category', function ($catQuery) use ($categorySlug) {
                    $catQuery->where('slug', $categorySlug);
                });
            }

            if (! empty($minPrice)) {
                $query->where('price_cents', '>=', (int) $minPrice);
            }

            if (! empty($maxPrice)) {
                $query->where('price_cents', '<=', (int) $maxPrice);
            }

            match ($sortBy) {
                'price_asc'  => $query->orderBy('price_cents', 'asc'),
                'price_desc' => $query->orderBy('price_cents', 'desc'),
                'reputation' => $query->join('seller_profiles', 'products.seller_id', '=', 'seller_profiles.id')
                                      ->orderBy('seller_profiles.reputation_score', 'desc')
                                      ->select('products.*'),
                default      => $query->latest(),
            };

            return $query->get();
        });

        return response()->json($products);
    }

    public function showProduct(string $id): JsonResponse
    {
        $product = Cache::remember("catalog:product:{$id}", 3600, function () use ($id) {
            return Product::with(['category', 'seller'])->find($id);
        });

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado'], 404);
        }

        return response()->json($product);
    }
}
