<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Upload an image from device to public storage.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image'  => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp,gif,svg', 'max:10240'],
            'folder' => ['nullable', 'string', 'in:dishes,rooms,banners,avatars,uploads'],
        ]);

        $folder = $request->input('folder', 'uploads');
        $path   = $request->file('image')->store($folder, 'public');
        $url    = Storage::disk('public')->url($path);

        return $this->success([
            'url'  => $url,
            'path' => $path,
        ], 'Image uploaded successfully');
    }
}
