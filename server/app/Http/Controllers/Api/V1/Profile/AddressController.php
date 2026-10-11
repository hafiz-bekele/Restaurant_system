<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\StoreAddressRequest;
use App\Http\Requests\Profile\UpdateAddressRequest;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->success($request->user()->addresses()->get());
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data['is_default'] ?? false) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($data);

        return $this->success($address, 'Address created', 201);
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $data = $request->validated();

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
