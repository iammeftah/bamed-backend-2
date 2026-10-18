<?php

namespace App\Services;

use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\Brand;

/** Stores product photos as webp on the "public" disk and removes the ones nobody uses. */
class ImageStorage
{
    private const DISK = 'public';

    private const DIR = 'products';

    /** Longest side in pixels. Bigger photos are scaled down. */
    private const MAX_SIDE = 1600;

    /** Refuses huge images (decompression bombs). */
    private const MAX_PIXELS = 40_000_000;

    private const QUALITY = 82;

    /** Converts the upload to webp, stores it, and returns its public URL. */
    public function storeWebp(UploadedFile $file): string
    {
        if (! function_exists('imagewebp')) {
            throw new \RuntimeException('GD with WebP support is required.');
        }

        $real = $file->getRealPath();
        $info = $real ? @getimagesize($real) : false;

        if (
            ! $info
            || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < 1 || $info[1] < 1
            || $info[0] * $info[1] > self::MAX_PIXELS
        ) {
            $this->invalid();
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($real),
            IMAGETYPE_PNG => @imagecreatefrompng($real),
            default => @imagecreatefromwebp($real),
        };

        if (! $image) {
            $this->invalid();
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->fixOrientation($image, $real);
        }

        $image = $this->fit($image);

        ob_start();
        $ok = imagewebp($image, null, self::QUALITY);
        $data = ob_get_clean();
        unset($image);

        if (! $ok || ! is_string($data) || $data === '') {
            $this->invalid();
        }

        $path = self::DIR.'/'.Str::random(40).'.webp';
        Storage::disk(self::DISK)->put($path, $data);

        return '/'.'storage/'.$path;
        
    }

    /**
     * Deletes the files behind these URLs, unless a photo row still uses them.
     * URLs that are not our uploads (like /images/... in the frontend) are ignored.
     *
     * @param  iterable<string>  $urls
     */
    public function purgeUnused(iterable $urls): void
    {
        foreach (array_unique(iterator_to_array($urls, false)) as $url) {
            $path = $this->pathFromUrl((string) $url);

            if (
                $path === null
                || ProductImage::where('src', $url)->exists()
                || Brand::where('logo', $url)->exists()
            ) {
                continue;
            }

            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * Files in storage that no product photo points to (failed saves, abandoned forms).
     * Files younger than $minAgeMinutes are kept: they may belong to a form still open.
     *
     * @return list<array{path: string, size: int}>
     */
    public function orphans(int $minAgeMinutes = 60): array
    {
        $disk = Storage::disk(self::DISK);

        $used = array_flip(array_filter(
            ProductImage::query()->pluck('src')
                ->merge(Brand::query()->whereNotNull('logo')->pluck('logo'))
                ->map(fn ($u) => $this->pathFromUrl((string) $u))
                ->all()
        ));

        $limit = now()->subMinutes(max(0, $minAgeMinutes))->getTimestamp();
        $out = [];

        foreach ($this->imageFiles() as $path) {
            if (isset($used[$path]) || $disk->lastModified($path) > $limit) {
                continue;
            }

            $out[] = ['path' => $path, 'size' => (int) $disk->size($path)];
        }

        return $out;
    }

    /** Deletes the orphan files and returns how many were removed. */
    public function pruneOrphans(int $minAgeMinutes = 60): int
    {
        $disk = Storage::disk(self::DISK);
        $count = 0;

        foreach ($this->orphans($minAgeMinutes) as $file) {
            if ($disk->delete($file['path'])) {
                $count++;
            }
        }

        return $count;
    }

    /** @return array{files: int, bytes: int, orphans: int, orphanBytes: int} */
    public function stats(): array
    {
        $disk = Storage::disk(self::DISK);
        $files = $this->imageFiles();
        $orphans = $this->orphans();

        return [
            'files' => count($files),
            'bytes' => (int) array_sum(array_map(fn ($p) => $disk->size($p), $files)),
            'orphans' => count($orphans),
            'orphanBytes' => (int) array_sum(array_column($orphans, 'size')),
        ];
    }

    /** @return list<string> */
    private function imageFiles(): array
    {
        return array_values(array_filter(
            Storage::disk(self::DISK)->files(self::DIR),
            fn (string $p) => (bool) preg_match('/\.(webp|jpe?g|png)$/i', $p),
        ));
    }

    /** "http://host/storage/products/abc.webp" -> "products/abc.webp" (null if not ours). */
    private function pathFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('#/storage/('.self::DIR.'/[A-Za-z0-9_-]{8,64}\.(?:webp|jpe?g|png))$#', $path, $m)
            ? $m[1]
            : null;
    }

    private function fixOrientation(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return @imagerotate($image, $angle, 0) ?: $image;
    }

    /** Scales down to MAX_SIDE, keeping transparency. */
    private function fit(\GdImage $src): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $ratio = min(1, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages([
            'file' => 'Image invalide (JPG, PNG ou WebP).',
        ]);
    }
}