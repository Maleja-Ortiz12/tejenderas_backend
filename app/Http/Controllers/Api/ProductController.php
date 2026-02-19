<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->orderBy('created_at', 'desc')->get();
        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|unique:products|max:255',
            'name' => 'required|max:255',
            'category' => 'required|in:telas,perfumeria',
            'brand' => 'nullable|string|max:255',
            'subcategory' => 'nullable|string|max:255',
            'is_promo' => 'nullable|boolean',
            'is_combo' => 'nullable|boolean',
            'description' => 'nullable',
            'base_price' => 'required|numeric|min:0',
            'markup' => 'nullable|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path;
        }

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'barcode' => 'sometimes|required|max:255|unique:products,barcode,' . $product->id,
            'name' => 'sometimes|required|max:255',
            'category' => 'sometimes|required|in:telas,perfumeria',
            'brand' => 'nullable|string|max:255',
            'subcategory' => 'nullable|string|max:255',
            'is_promo' => 'nullable|boolean',
            'is_combo' => 'nullable|boolean',
            'description' => 'nullable',
            'base_price' => 'sometimes|required|numeric|min:0',
            'markup' => 'nullable|numeric|min:0',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path;
        }

        $product->update($validated);

        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }

    /**
     * Check if a barcode exists.
     */
    public function check($barcode)
    {
        $product = Product::where('barcode', $barcode)->first();

        if ($product) {
            return response()->json(['exists' => true, 'product' => $product]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Generate a unique barcode.
     */
    public function generate()
    {
        do {
            $barcode = str_pad(mt_rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (Product::where('barcode', $barcode)->exists());

        return response()->json(['barcode' => $barcode]);
    }
}

