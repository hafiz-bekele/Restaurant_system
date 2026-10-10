<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key');

        return $this->success($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate(['settings' => 'required|array']);

        foreach ($request->settings as $key => $value) {
            Setting::set($key, $value);
        }

        ActivityLog::record('settings.updated', null, ['keys' => array_keys($request->settings)]);

        return $this->success(null, 'Settings saved');
    }
}
