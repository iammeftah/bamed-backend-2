<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Stores product photos and brand logos on the "public" disk and removes the ones nobody uses. */
class ImageStorage
{
    private const DISK = 'public';

    private const DIR = 'products';

    /** Longest side in pixels. Bigger photos are scaled down. */
    private const MAX_SIDE = 1600;

    /** Refuses huge images (decompression bombs). */
    private const MAX_PIXELS = 40_000_000;

    private const QUALITY = 82;

    /** Logos are small: a bigger SVG is refused. */
    private const MAX_SVG_BYTES = 512 * 1024;

    /** Elements that can run code or load things from elsewhere. */
    private const SVG_FORBIDDEN_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'embed', 'object',
        'set', 'animate', 'handler', 'listener', 'audio', 'video',
    ];

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

    /** Sanitizes the SVG, stores it as svg, and returns its public URL. */
    public function storeSvg(UploadedFile $file): string
    {
        $real = $file->getRealPath();
        $raw = $real ? @file_get_contents($real) : false;

        if (! is_string($raw) || $raw === '' || strlen($raw) > self::MAX_SVG_BYTES) {
            $this->invalid();
        }

        $clean = $this->sanitizeSvg($raw);

        if ($clean === null) {
            $this->invalid();
        }

        $path = self::DIR.'/'.Str::random(40).'.svg';
        Storage::disk(self::DISK)->put($path, $clean);

        return '/'.'storage/'.$path;
    }

    /**
     * Deletes the files behind these URLs, unless a photo row or a brand still uses them.
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
     * Files in storage that no product photo or brand logo points to (failed saves, abandoned forms).
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
            fn (string $p) => (bool) preg_match('/\.(webp|jpe?g|png|svg)$/i', $p),
        ));
    }

    /** "http://host/storage/products/abc.webp" -> "products/abc.webp" (null if not ours). */
    private function pathFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('#/storage/('.self::DIR.'/[A-Za-z0-9_-]{8,64}\.(?:webp|jpe?g|png|svg))$#', $path, $m)
            ? $m[1]
            : null;
    }

    /**
     * Parses the SVG and rebuilds it without anything that can run code.
     * Returns null when the file is not a plain, well-formed SVG.
     */
    private function sanitizeSvg(string $raw): ?string
    {
        // Entities are how XML bombs and file reads work: no logo needs them
        if (preg_match('/<!ENTITY/i', $raw)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $loaded = $doc->loadXML($raw, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->documentElement;

        if (! $loaded || ! $root || strtolower($root->localName) !== 'svg') {
            return null;
        }

        $xpath = new \DOMXPath($doc);

        // Copy the list first: removing nodes while looping a live list skips items
        $elements = iterator_to_array($xpath->query('//*') ?: []);

        foreach ($elements as $el) {
            if (! $el->parentNode) {
                continue;
            }

            $name = strtolower($el->localName);

            if (in_array($name, self::SVG_FORBIDDEN_ELEMENTS, true)) {
                $el->parentNode->removeChild($el);

                continue;
            }

            if ($name === 'style' && preg_match('/@import|javascript:|expression\s*\(/i', $el->textContent)) {
                $el->parentNode->removeChild($el);

                continue;
            }

            foreach (iterator_to_array($el->attributes) as $attr) {
                $attrName = strtolower($attr->localName);
                $value = trim($attr->value);

                $remove = str_starts_with($attrName, 'on')
                    || ($attrName === 'style' && preg_match('/@import|javascript:|expression\s*\(/i', $value))
                    || (
                        $attrName === 'href'
                        && ! str_starts_with($value, '#')
                        && ! preg_match('#^data:image/(?:png|jpe?g|gif|webp);base64,#i', $value)
                    );

                if ($remove) {
                    $el->removeAttributeNode($attr);
                }
            }
        }

        // Browsers only draw a standalone SVG that declares its namespace
        if ($root->namespaceURI === null) {
            $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }

        // Saving only the root element also drops any DOCTYPE
        $out = $doc->saveXML($root);

        return is_string($out) && $out !== '' ? $out : null;
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
            'file' => 'Image invalide (JPG, PNG, WebP ou SVG).',
        ]);
    }
}