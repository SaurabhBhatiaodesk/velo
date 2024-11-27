<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use App\Models\Polygon;

class CouriersController extends Controller
{
    public function show($courier)
    {
        $courier = Courier::find($courier);
        if (!$courier) {
            return $this->respond(['error' => 'Courier not found'], 404);
        }
        return $this->respond($courier);
    }

    public function forPolygon(Polygon $polygon)
    {
        if (!$polygon) {
            return $this->respond(['error' => 'Polygon not found'], 404);
        }
        if (!$polygon || !$polygon->courier) {
            return $this->respond(['error' => 'Invalid polygon'], 404);
        }
        return $this->respond($polygon);
    }
}
