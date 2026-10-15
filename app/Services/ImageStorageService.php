<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageStorageService
{
    /**
     * Store an uploaded image, resize/re-encode it, and return its details.
     */
    public function store(UploadedFile $file, string $folder, array $sizes = ['medium' => 512, 'thumb' => 128]): array
    {
        if (!extension_loaded('gd')) {
            throw new \Exception('GD extension is missing on the server. Cannot process images.');
        }

        $manager = new ImageManager(new Driver());
        
        // Read and auto-orient based on EXIF
        $image = $manager->read($file);
        
        // Scale down to medium size for the main photo
        $mainImage = clone $image;
        $mainImage->scaleDown($sizes['medium']);
        
        // Encode to WebP (strips EXIF automatically)
        $encodedMain = $mainImage->toWebp(80);
        
        // Hash the output content to generate a unique filename
        $hash = hash('sha256', (string) $encodedMain);
        $filename = "{$hash}.webp";
        
        // Store main image if it doesn't exist
        $disk = Storage::disk('public');
        $mainPath = "{$folder}/{$filename}";
        
        if (!$disk->exists($mainPath)) {
            $disk->put($mainPath, (string) $encodedMain);
        }
        
        // Generate and store thumbnail if requested
        $thumbFilename = null;
        if (isset($sizes['thumb'])) {
            $thumbImage = clone $image;
            $thumbImage->cover($sizes['thumb'], $sizes['thumb']);
            $encodedThumb = $thumbImage->toWebp(80);
            
            $thumbFilename = "thumb_{$hash}.webp";
            $thumbPath = "{$folder}/{$thumbFilename}";
            
            if (!$disk->exists($thumbPath)) {
                $disk->put($thumbPath, (string) $encodedThumb);
            }
        }
        
        return [
            'photo' => $filename,
            'photo_hash' => $hash,
            'thumb' => $thumbFilename
        ];
    }

    /**
     * Delete an image from storage.
     */
    public function delete(string $folder, ?string $filename): void
    {
        if (!$filename) return;

        $disk = Storage::disk('public');
        
        $mainPath = "{$folder}/{$filename}";
        if ($disk->exists($mainPath)) {
            $disk->delete($mainPath);
        }
        
        // If it starts with thumb_, it's just a thumb, otherwise delete the thumb version too
        if (!str_starts_with($filename, 'thumb_')) {
            $thumbPath = "{$folder}/thumb_{$filename}";
            if ($disk->exists($thumbPath)) {
                $disk->delete($thumbPath);
            }
        }
    }
}
