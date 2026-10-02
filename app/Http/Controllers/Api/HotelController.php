<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HotelController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return HotelResource::collection(Hotel::query()->withCount('rooms')->orderBy('id')->get());
    }

    public function show(Hotel $hotel): HotelResource
    {
        return new HotelResource($hotel->loadCount('rooms'));
    }
}
