<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBannerController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(Banner::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'subtitle'   => 'nullable|string|max:255',
            'image'      => 'required|string|max:500',
            'link'       => 'nullable|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after:starts_at',
        ]);

        return $this->success(Banner::create($data), 'Banner created', 201);
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $data = $request->validate([
            'title'      => 'sometimes|string|max:255',
            'subtitle'   => 'nullable|string|max:255',
            'image'      => 'sometimes|string|max:500',
            'link'       => 'nullable|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date',
        ]);

        $banner->update($data);

        return $this->success($banner->fresh(), 'Banner updated');
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return $this->success(null, 'Banner deleted');
    }
}
