<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function stats()
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Statistik admin.',
                'data' => [
                    'total_users' => User::where('role', 'user')->count(),
                    'total_destinations' => Destination::count(),
                    'total_trips' => Trip::count(),
                    'total_reviews' => Review::count(),
                    'active_destinations' => Destination::where('is_active', true)->count(),
                    'top_destinations' => Destination::orderBy('review_count', 'desc')->limit(5)->get(['id', 'name', 'rating', 'review_count']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function destinationIndex(Request $request)
    {
        try {
            $destinations = Destination::with('category')
                ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json(['success' => true, 'message' => 'Daftar destinasi.', 'data' => $destinations]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function destinationStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'address' => 'required|string',
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'price' => 'required|integer|min:0',
                'opening_hours' => 'nullable|string',
                'image_url' => 'nullable|url',
                'estimated_duration' => 'sometimes|integer|min:1',
                'tips' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
            ]);

            $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();

            $destination = Destination::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Destinasi berhasil ditambahkan.',
                'data' => $destination->load('category'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function destinationUpdate(Request $request, $id)
    {
        try {
            $destination = Destination::findOrFail($id);
            $validated = $request->validate([
                'category_id' => 'sometimes|exists:categories,id',
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'address' => 'sometimes|string',
                'lat' => 'sometimes|numeric',
                'lng' => 'sometimes|numeric',
                'price' => 'sometimes|integer|min:0',
                'opening_hours' => 'sometimes|nullable|string',
                'image_url' => 'sometimes|nullable|url',
                'estimated_duration' => 'sometimes|integer|min:1',
                'tips' => 'sometimes|nullable|string',
                'is_active' => 'sometimes|boolean',
            ]);

            $destination->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Destinasi berhasil diperbarui.',
                'data' => $destination->fresh()->load('category'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Destinasi tidak ditemukan.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function destinationDestroy($id)
    {
        try {
            Destination::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Destinasi berhasil dihapus.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Destinasi tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function users(Request $request)
    {
        try {
            $users = User::where('role', 'user')
                ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"))
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json(['success' => true, 'message' => 'Daftar pengguna.', 'data' => $users]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function toggleUserStatus($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['is_active' => !$user->is_active]);

            return response()->json([
                'success' => true,
                'message' => $user->is_active ? 'Pengguna diaktifkan.' : 'Pengguna dinonaktifkan.',
                'data' => $user,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Pengguna tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function reviews(Request $request)
    {
        try {
            $reviews = Review::with(['user:id,name,email', 'destination:id,name'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json(['success' => true, 'message' => 'Daftar ulasan.', 'data' => $reviews]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function deleteReview($id)
    {
        try {
            $review = Review::findOrFail($id);
            $destination = $review->destination;
            $review->delete();
            $destination->updateRating();

            return response()->json(['success' => true, 'message' => 'Ulasan berhasil dihapus.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Ulasan tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }
}
