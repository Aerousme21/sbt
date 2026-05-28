<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripDestination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    public function index(Request $request)
    {
        try {
            $trips = Trip::with(['tripDestinations.destination'])
                ->where('user_id', $request->user()->id)
                ->orderBy('trip_date', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Daftar trip.',
                'data' => $trips,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'trip_date' => 'required|date',
                'total_budget' => 'required|integer|min:0',
                'notes' => 'nullable|string',
                'status' => 'sometimes|in:planned,ongoing,completed',
                'destinations' => 'sometimes|array',
                'destinations.*.destination_id' => 'required_with:destinations|exists:destinations,id',
                'destinations.*.order' => 'sometimes|integer|min:0',
                'destinations.*.trip_day' => 'sometimes|integer|min:1',
                'destinations.*.visit_time' => 'sometimes|nullable|date_format:H:i',
                'destinations.*.budget_allocated' => 'sometimes|integer|min:0',
                'destinations.*.notes' => 'sometimes|nullable|string',
            ]);

            $trip = DB::transaction(function () use ($validated, $request) {
                $trip = Trip::create([
                    'user_id' => $request->user()->id,
                    'title' => $validated['title'],
                    'trip_date' => $validated['trip_date'],
                    'total_budget' => $validated['total_budget'],
                    'notes' => $validated['notes'] ?? null,
                    'status' => $validated['status'] ?? 'planned',
                ]);

                if (!empty($validated['destinations'])) {
                    foreach ($validated['destinations'] as $index => $dest) {
                        TripDestination::create([
                            'trip_id' => $trip->id,
                            'destination_id' => $dest['destination_id'],
                            'order' => $dest['order'] ?? $index,
                            'trip_day' => $dest['trip_day'] ?? 1,
                            'visit_time' => $dest['visit_time'] ?? null,
                            'budget_allocated' => $dest['budget_allocated'] ?? 0,
                            'notes' => $dest['notes'] ?? null,
                        ]);
                    }
                }

                return $trip;
            });

            return response()->json([
                'success' => true,
                'message' => 'Trip berhasil dibuat.',
                'data' => $trip->load('tripDestinations.destination'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $trip = Trip::with(['tripDestinations.destination.category'])
                ->where('user_id', $request->user()->id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Detail trip.',
                'data' => $trip,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trip tidak ditemukan.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server.',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $trip = Trip::where('user_id', $request->user()->id)->findOrFail($id);

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'trip_date' => 'sometimes|date',
                'total_budget' => 'sometimes|integer|min:0',
                'actual_spent' => 'sometimes|integer|min:0',
                'notes' => 'sometimes|nullable|string',
                'status' => 'sometimes|in:planned,ongoing,completed',
                'destinations' => 'sometimes|array',
                'destinations.*.destination_id' => 'required_with:destinations|exists:destinations,id',
                'destinations.*.order' => 'sometimes|integer|min:0',
                'destinations.*.trip_day' => 'sometimes|integer|min:1',
                'destinations.*.visit_time' => 'sometimes|nullable|date_format:H:i',
                'destinations.*.budget_allocated' => 'sometimes|integer|min:0',
                'destinations.*.notes' => 'sometimes|nullable|string',
            ]);

            DB::transaction(function () use ($trip, $validated) {
                $trip->update(array_filter($validated, fn($k) => $k !== 'destinations', ARRAY_FILTER_USE_KEY));

                if (isset($validated['destinations'])) {
                    $trip->tripDestinations()->delete();
                    foreach ($validated['destinations'] as $index => $dest) {
                        TripDestination::create([
                            'trip_id' => $trip->id,
                            'destination_id' => $dest['destination_id'],
                            'order' => $dest['order'] ?? $index,
                            'trip_day' => $dest['trip_day'] ?? 1,
                            'visit_time' => $dest['visit_time'] ?? null,
                            'budget_allocated' => $dest['budget_allocated'] ?? 0,
                            'notes' => $dest['notes'] ?? null,
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Trip berhasil diperbarui.',
                'data' => $trip->fresh()->load('tripDestinations.destination'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Trip tidak ditemukan.'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $trip = Trip::where('user_id', $request->user()->id)->findOrFail($id);
            $trip->delete();

            return response()->json([
                'success' => true,
                'message' => 'Trip berhasil dihapus.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Trip tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }
}
