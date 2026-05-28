<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Destination;
use Illuminate\Http\Request;

class DestinationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Destination::with('category')->where('is_active', true);

            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('description', 'like', "%{$request->search}%")
                      ->orWhere('address', 'like', "%{$request->search}%");
                });
            }

            if ($request->category) {
                $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
            }

            if ($request->max_price !== null) {
                $query->where('price', '<=', $request->max_price);
            }

            $sortField = match ($request->sort) {
                'price_asc' => ['price', 'asc'],
                'price_desc' => ['price', 'desc'],
                'rating' => ['rating', 'desc'],
                'name' => ['name', 'asc'],
                default => ['rating', 'desc'],
            };
            $query->orderBy($sortField[0], $sortField[1]);

            $destinations = $query->paginate($request->per_page ?? 10);

            return response()->json([
                'success' => true,
                'message' => 'Daftar destinasi.',
                'data' => $destinations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $destination = Destination::with(['category', 'reviews.user'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Detail destinasi.',
                'data' => $destination,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Destinasi tidak ditemukan.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        }
    }

    public function featured()
    {
        try {
            $destinations = Destination::with('category')
                ->where('is_active', true)
                ->orderBy('rating', 'desc')
                ->limit(6)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Destinasi unggulan.',
                'data' => $destinations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        }
    }

    public function categories()
    {
        try {
            $categories = Category::withCount(['destinations' => function ($q) {
                $q->where('is_active', true);
            }])->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar kategori.',
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        }
    }
}
