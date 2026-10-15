<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdController extends Controller
{
    public function index()
    {
        $ads = Ad::all()->map(function ($ad) {
            $ad->average_watch_time = $ad->view_count > 0 ? round($ad->total_watch_time / $ad->view_count, 2) : 0;
            $ad->url = url('storage/' . $ad->path);
            return $ad;
        });

        return response()->json(['success' => true, 'data' => $ads]);
    }

    public function getActiveAd()
    {
        $ad = Ad::where('is_active', true)->inRandomOrder()->first();

        if ($ad) {
            $ad->url = url('storage/' . $ad->path);
            return response()->json(['success' => true, 'data' => $ad]);
        }

        return response()->json(['success' => false, 'message' => 'No active ads']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,mp4,mov,avi',
            'type' => 'required|in:image,video',
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('ads', 'public');
            
            $ad = Ad::create([
                'type' => $request->type,
                'path' => $path,
                'is_active' => true,
            ]);

            return response()->json(['success' => true, 'message' => 'Ad uploaded successfully', 'data' => $ad]);
        }

        return response()->json(['success' => false, 'message' => 'File not found'], 400);
    }

    public function updateStats(Request $request, $id)
    {
        $ad = Ad::findOrFail($id);
        
        $ad->increment('view_count');
        if ($request->has('watch_time')) {
            $ad->increment('total_watch_time', $request->watch_time);
        }

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $ad = Ad::findOrFail($id);
        Storage::disk('public')->delete($ad->path);
        $ad->delete();

        return response()->json(['success' => true, 'message' => 'Ad deleted successfully']);
    }

    public function toggleStatus($id)
    {
        $ad = Ad::findOrFail($id);
        $ad->is_active = !$ad->is_active;
        $ad->save();

        return response()->json(['success' => true, 'message' => 'Status updated', 'is_active' => $ad->is_active]);
    }
}
