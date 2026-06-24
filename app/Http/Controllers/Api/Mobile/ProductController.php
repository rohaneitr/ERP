<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Product;
use App\Models\Variation;

class ProductController extends Controller
{
    /**
     * GET /api/mobile/products/search?q=&location_id=
     *
     * Server-side product search (used when online for faster results).
     * The mobile app also has a local SQLite fallback via ProductsRepo.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'           => ['required', 'string', 'min:2'],
            'location_id' => ['nullable', 'integer'],
        ]);

        $q          = $request->query('q');
        $locationId = $request->query('location_id', $request->user()->location_id ?? 1);
        $businessId = $request->user()->business_id;

        $products = Product::with([
            'variations',
            'variations.variation_location_details' => function ($query) use ($locationId) {
                $query->where('location_id', $locationId);
            },
            'category:id,name',
            'unit:id,actual_name,allow_decimal',
        ])
        ->where('business_id', $businessId)
        ->where('is_inactive', 0)
        ->where(function ($query) use ($q) {
            $query->where('name', 'like', "%{$q}%")
                  ->orWhere('sku', 'like', "%{$q}%")
                  ->orWhereHas('variations', function ($vq) use ($q) {
                      $vq->where('sub_sku', 'like', "%{$q}%");
                  });
        })
        ->limit(30)
        ->get();

        $formatted = $products->map(function (Product $p) {
            return [
                'id'             => $p->id,
                'name'           => $p->name,
                'sku'            => $p->sku,
                'barcode'        => $p->barcode,
                'type'           => $p->type,
                'category_id'    => $p->category_id,
                'category_name'  => $p->category?->name,
                'unit_id'        => $p->unit_id,
                'unit_name'      => $p->unit?->actual_name,
                'allow_decimal'  => (bool) $p->unit?->allow_decimal,
                'image_url'      => $p->image ? asset('uploads/img/' . $p->image) : null,
                'alert_quantity' => $p->alert_quantity,
                'is_inactive'    => (bool) $p->is_inactive,
                'variations'     => $p->variations->map(function ($v) {
                    $stock = $v->variation_location_details->first();
                    return [
                        'id'                 => $v->id,
                        'name'               => $v->name,
                        'sub_sku'            => $v->sub_sku,
                        'default_sell_price' => (float) $v->default_sell_price,
                        'dpp_inc_tax'        => (float) ($v->dpp_inc_tax ?? 0),
                        'qty_available'      => (float) ($stock?->qty_available ?? 0),
                    ];
                }),
            ];
        });

        return response()->json(['products' => $formatted]);
    }

    /**
     * GET /api/mobile/products/{id}
     *
     * Get a single product with full details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $locationId = $request->query('location_id', $request->user()->location_id ?? 1);

        $product = Product::with([
            'variations',
            'variations.variation_location_details' => fn($q) => $q->where('location_id', $locationId),
            'category:id,name',
            'unit:id,actual_name,allow_decimal',
            'brand:id,name',
        ])
        ->where('business_id', $request->user()->business_id)
        ->findOrFail($id);

        return response()->json(['product' => $product]);
    }
}
