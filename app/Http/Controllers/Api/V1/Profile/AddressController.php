<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->success($request->user()->addresses()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => 'required|string|max:50',
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'is_default' => 'boolean',
        ]);

        if ($data['is_default'] ?? false) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($data);

        return $this->success($address, 'Address created', 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        $data = $request->validate([
            'label' => 'sometimes|string|max:50',
            'line1' => 'sometimes|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:100',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'is_default' => 'boolean',
        ]);

        if ($data['is_default'] ?? false) {
            $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($data);

        return $this->success($address, 'Address updated');
    }

    public function destroy(Address $address): JsonResponse
    {
        $address->delete();

        return $this->success(null, 'Address deleted');
    }
}
