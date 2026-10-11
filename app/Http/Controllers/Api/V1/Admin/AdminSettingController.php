<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class AdminSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->success(Setting::all()->pluck('value', 'key'));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated()['settings'] as $key => $value) {
            Setting::set($key, $value);
        }

        ActivityLog::record('settings.updated', null, ['keys' => array_keys($request->validated()['settings'])]);

        return $this->success(null, 'Settings saved');
    }
}
