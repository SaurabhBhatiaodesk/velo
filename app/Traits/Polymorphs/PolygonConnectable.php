<?php

namespace App\Traits\Polymorphs;

use App\Models\PolygonConnection;

trait PolygonConnectable
{
    private function getPolygonConnectableKey()
    {
        return (isset($this->model_key) && strlen($this->model_key)) ? $this->model_key : 'id';
    }

    public function polygon_connection()
    {
        $modelKey = $this->getPolygonConnectionKey();
        return $this->morphOne(PolygonConnection::class, 'polygon_connectable', 'polygon_connectable_type', 'polygon_connectable_' . $modelKey, $modelKey);
    }

    public function polygon_connections()
    {
        $modelKey = $this->getPolygonConnectableKey();
        return $this->morphMany(PolygonConnection::class, 'polygon_connectable', 'polygon_connectable_type', 'polygon_connectable_' . $modelKey, $modelKey);
    }
}
