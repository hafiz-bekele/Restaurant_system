<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class AdminBannerController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(Banner::orderBy('sort_order')->get());
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        return $this->success(Banner::create($request->validated()), 'Banner created', 201);
    }

    public function update(UpdateBannerRequest $request, Banner $banner): JsonResponse
    {
        $banner->update($request->validated());

        return $this->success($banner->fresh(), 'Banner updated');
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return $this->success(null, 'Banner deleted');
    }
}
