<?php

namespace Meta\AdminCore\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image upload + WebP conversion.
 *
 * Uses `intervention/image` when available (peer dependency — consumer
 * must composer-require it). Falls back to storing the original file
 * without conversion if the library or PHP extensions are missing, so
 * fresh installs don't error out on first upload.
 */
class ImageService
{
    protected int $quality = 85;

    /**
     * Upload a file. If it's an image AND Intervention is available,
     * convert to WebP and optionally resize. Returns the final storage
     * path (relative to the `public` disk).
     */
    public function upload(
        UploadedFile $file,
        string $folder,
        ?int $width = null,
        ?int $height = null,
        ?int $quality = null,
    ): string {
        $quality = $quality ?? $this->quality;

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '', $originalName);

        if (class_exists(\Intervention\Image\Laravel\Facades\Image::class)) {
            try {
                $filename = time() . '_' . ($safeName ?: Str::random(10)) . '.webp';
                $image = \Intervention\Image\Laravel\Facades\Image::read($file->getRealPath());

                if ($width || $height) {
                    $image->resize($width, $height, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                }

                Storage::disk('public')->put($folder . '/' . $filename, $image->toWebp($quality));
                return $folder . '/' . $filename;
            } catch (\Throwable) {
                // Fall through to raw-store path.
            }
        }

        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $filename = time() . '_' . ($safeName ?: Str::random(10)) . '.' . $ext;
        $file->storeAs($folder, $filename, 'public');
        return $folder . '/' . $filename;
    }

    public function uploadOriginal(UploadedFile $file, string $folder, ?int $quality = null): string
    {
        return $this->upload($file, $folder, null, null, $quality);
    }

    public function uploadWithSize(UploadedFile $file, string $folder, string $size = 'medium'): string
    {
        $sizes = [
            'thumbnail' => ['width' => 150,  'height' => 150],
            'small'     => ['width' => 300,  'height' => 300],
            'medium'    => ['width' => 800,  'height' => 600],
            'large'     => ['width' => 1200, 'height' => 900],
            'hero'      => ['width' => 1920, 'height' => 1080],
        ];
        $d = $sizes[$size] ?? $sizes['medium'];
        return $this->upload($file, $folder, $d['width'], $d['height']);
    }

    public function delete(?string $path): bool
    {
        if (empty($path)) return false;
        $path = preg_replace('#^/?storage/#', '', $path);
        return Storage::disk('public')->delete($path);
    }

    public function replace(UploadedFile $file, ?string $oldPath, string $folder, ?int $width = null, ?int $height = null): string
    {
        $this->delete($oldPath);
        return $this->upload($file, $folder, $width, $height);
    }

    /**
     * Generate a smart-cropped variant using a focal point (relative
     * coordinates in [0..1], 0,0 = top-left, 1,1 = bottom-right).
     *
     *   $path   — path inside the `public` disk (same format `upload()` returns).
     *   $w, $h  — target dimensions in pixels.
     *   $fx, $fy — focal point. 0.5/0.5 = dead centre (same as a plain
     *             "cover" crop). 0.2/0.8 = 20% from left, 80% from top.
     *
     * Returned path points at a cached variant at
     * "{folder}/focal/{w}x{h}_{fx}_{fy}-{original-filename}" so the
     * disk becomes the cache. Regenerate by deleting that variant.
     * Falls back to the original on errors.
     */
    public function focalCrop(string $path, int $w, int $h, float $fx = 0.5, float $fy = 0.5): string
    {
        if (!class_exists(\Intervention\Image\Laravel\Facades\Image::class)) return $path;

        $fx = max(0.0, min(1.0, $fx));
        $fy = max(0.0, min(1.0, $fy));

        $cleanPath = preg_replace('#^/?storage/#', '', $path);
        $dir  = dirname($cleanPath);
        $file = basename($cleanPath);
        $tag  = sprintf('%dx%d_%02d_%02d', $w, $h, (int) round($fx * 100), (int) round($fy * 100));
        $cached = "{$dir}/focal/{$tag}-{$file}";

        $disk = Storage::disk('public');
        if ($disk->exists($cached)) return $cached;
        if (!$disk->exists($cleanPath)) return $path;

        try {
            $image = \Intervention\Image\Laravel\Facades\Image::read($disk->path($cleanPath));
            $srcW  = $image->width();
            $srcH  = $image->height();

            // Cover-scale so the smaller axis fills the target, then crop
            // from the focal anchor.
            $scale = max($w / $srcW, $h / $srcH);
            $newW  = (int) ceil($srcW * $scale);
            $newH  = (int) ceil($srcH * $scale);
            $image->resize($newW, $newH);

            $cropX = (int) round(max(0, min($newW - $w, $fx * $newW - $w / 2)));
            $cropY = (int) round(max(0, min($newH - $h, $fy * $newH - $h / 2)));
            $image->crop($w, $h, $cropX, $cropY);

            $disk->put($cached, $image->toWebp($this->quality));
            return $cached;
        } catch (\Throwable) {
            return $path;
        }
    }
}
