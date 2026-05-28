<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedDestination;
use Illuminate\Http\Request;

class SavedController extends Controller
{
    public function index(Request $request)
    {
        try {
            $saved = SavedDestination::with('destination.category')
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'message' => 'Destinasi tersimpan.',
                'data' => $saved,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    public function toggle(Request $request, $destinationId)
    {
        try {
            $existing = SavedDestination::where('user_id', $request->user()->id)
                ->where('destination_id', $destinationId)
                ->first();

            if ($existing) {
                $existing->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Destinasi dihapus dari simpanan.',
                    'data' => ['saved' => false],
                ]);
            }

            SavedDestination::create([
                'user_id' => $request->user()->id,
                'destination_id' => $destinationId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Destinasi disimpan.',
                'data' => ['saved' => true],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }
}
