<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Destination;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function byDestination($destinationId)
    {
        try {
            $reviews = Review::with('user:id,name,avatar')
                ->where('destination_id', $destinationId)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Ulasan destinasi.',
                'data' => $reviews,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'destination_id' => 'required|exists:destinations,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'required|string|min:10|max:1000',
                'images' => 'sometimes|array',
                'images.*' => 'url',
            ]);

            $existing = Review::where('user_id', $request->user()->id)
                ->where('destination_id', $validated['destination_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah memberikan ulasan untuk destinasi ini.',
                ], 422);
            }

            $review = Review::create([
                'user_id' => $request->user()->id,
                'destination_id' => $validated['destination_id'],
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
                'images' => $validated['images'] ?? null,
            ]);

            Destination::find($validated['destination_id'])->updateRating();

            return response()->json([
                'success' => true,
                'message' => 'Ulasan berhasil ditambahkan.',
                'data' => $review->load('user:id,name,avatar'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $review = Review::where('user_id', $request->user()->id)->findOrFail($id);

            $validated = $request->validate([
                'rating' => 'sometimes|integer|min:1|max:5',
                'comment' => 'sometimes|string|min:10|max:1000',
                'images' => 'sometimes|nullable|array',
            ]);

            $review->update($validated);
            $review->destination->updateRating();

            return response()->json([
                'success' => true,
                'message' => 'Ulasan berhasil diperbarui.',
                'data' => $review->fresh()->load('user:id,name,avatar'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Ulasan tidak ditemukan.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $review = Review::where('user_id', $request->user()->id)->findOrFail($id);
            $destination = $review->destination;
            $review->delete();
            $destination->updateRating();

            return response()->json([
                'success' => true,
                'message' => 'Ulasan berhasil dihapus.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Ulasan tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function myReviews(Request $request)
    {
        try {
            $reviews = Review::with('destination:id,name,image_url')
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Ulasan saya.',
                'data' => $reviews,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }
}
