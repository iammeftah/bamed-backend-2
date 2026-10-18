<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImageStorage;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /** POST /admin/uploads (multipart "file") -> { url }  (always a webp) */
    public function store(Request $request, ImageStorage $images)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'file.required' => 'Aucun fichier reçu.',
            'file.mimes' => 'Image invalide (JPG, PNG ou WebP).',
            'file.max' => 'Fichier trop volumineux (10 Mo max).',
        ]);

        return response()->json([
            'url' => $images->storeWebp($request->file('file')),
        ], 201);
    }
}