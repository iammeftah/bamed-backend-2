<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageStorage;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * POST /admin/uploads (multipart "file") -> { url }
     * JPG, PNG and WebP are converted to webp. SVG is sanitized and kept as svg.
     */
    public function store(Request $request, ImageStorage $images)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ], [
            'file.required' => 'Aucun fichier reçu.',
            'file.max' => 'Fichier trop volumineux (10 Mo max).',
        ]);

        $file = $request->file('file');
        $isSvg = strtolower($file->getClientOriginalExtension()) === 'svg';

        if ($isSvg) {
            // The content is fully parsed and rebuilt by storeSvg, the extension alone is never trusted
            $url = $images->storeSvg($file);
        } else {
            $request->validate([
                'file' => ['mimes:jpg,jpeg,png,webp'],
            ], [
                'file.mimes' => 'Image invalide (JPG, PNG, WebP ou SVG).',
            ]);

            $url = $images->storeWebp($file);
        }

        return response()->json(['url' => $url], 201);
    }
}