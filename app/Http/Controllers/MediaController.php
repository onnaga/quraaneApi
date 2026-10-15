<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class MediaController extends Controller
{
    public function show($folder, $filename)
    {
        // Whitelist allowed folders
        $allowedFolders = ['uploadsUser', 'uploadsDaoras', 'uploadsAdmins', 'ads'];
        if (!in_array($folder, $allowedFolders)) {
            abort(404);
        }

        // Validate filename to prevent path traversal
        if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $filename)) {
            abort(404);
        }

        $path = storage_path("app/public/{$folder}/{$filename}");

        if (!File::exists($path)) {
            abort(404);
        }

        // Determine Cache-Control based on filename (if it looks like a hash, it's immutable)
        // For our new format, it will be {hash}.webp or thumb_{hash}.webp
        $isImmutable = preg_match('/^[a-f0-9]{64}\.webp$/', $filename) || preg_match('/^thumb_[a-f0-9]{64}\.webp$/', $filename);
        $cacheControl = $isImmutable ? 'public, max-age=31536000, immutable' : 'public, max-age=86400';

        // ETag can be the file size + modified time, or the hash itself if immutable
        $etag = $isImmutable ? md5($filename) : md5(File::lastModified($path) . File::size($path));

        // Check If-None-Match header
        $requestEtag = request()->header('If-None-Match');
        if ($requestEtag && trim($requestEtag, '"') === $etag) {
            return response()->json([], 304); // Not Modified
        }

        return response()->file($path, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
            'Cache-Control' => $cacheControl,
            'ETag' => '"' . $etag . '"',
        ]);
    }
}
