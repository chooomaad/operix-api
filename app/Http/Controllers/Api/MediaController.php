<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'       => ['required', 'file', 'max:20480'],
            'model_type' => ['required', 'string'],
            'model_id'   => ['required', 'integer'],
            'collection' => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('file');
        $disk = 'public';
        $path = $file->storeAs(
            'operix/' . $request->model_type,
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            $disk
        );

        $media = Media::create([
            'model_type'  => $request->model_type,
            'model_id'    => $request->model_id,
            'collection'  => $request->input('collection', 'default'),
            'name'        => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name'   => $file->getClientOriginalName(),
            'mime_type'   => $file->getMimeType(),
            'disk'        => $disk,
            'path'        => $path,
            'size'        => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($media, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Media::findOrFail($id));
    }

    public function destroy(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return response()->json(['message' => 'Fichier supprimé.']);
    }
}
