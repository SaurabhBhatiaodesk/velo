<?php

namespace App\Http\Controllers;

use App\Models\Polygon;
use App\Models\ShippingCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelUpdateController extends Controller
{
    private $allowed;

    function __construct()
    {
        $this->allowed = [
            'polygons'=>[
                'active'=>                      ['value'=> [0,1], 'required'=>true],
                'shipping_code_id'=>            ['value'=> 'number', 'required'=>true, 'readOnly'=>true],
                'courier_id'=>                  ['value'=> 'number', 'required'=>true, 'readOnly'=>true],
                'max_range'=>                   ['value'=> 'number'],
                'min_range'=>                   ['value'=> 'number'],
                'pickup_polygon'=>              ['value'=> 'json'],
                'pickup_country'=>              ['value'=> 'string'],
                'pickup_state'=>                ['value'=> 'string'],
                'pickup_city'=>                 ['value'=> 'string'],
                'pickup_zipcode'=>              ['value'=> 'string'],
                'dropoff_polygon'=>             ['value'=> 'json'],
                'dropoff_country'=>             ['value'=> 'string'],
                'dropoff_state'=>               ['value'=> 'string'],
                'dropoff_city'=>                ['value'=> 'string'],
                'pickup_max_days'=>             ['value'=> [1,2,3,4,5,6,7, null]],
                'dropoff_max_days'=>            ['value'=> [1,2,3,4,5,6,7, null]],
                'min_weight'=>                  ['value'=> 'float'],
                'max_weight'=>                  ['value'=> 'float'],
                'initial_free_km'=>             ['value'=> 'float'],
                'min_dimensions'=>              ['value'=> 'json'],
                'max_dimensions'=>              ['value'=> 'json'],
                'min_monthly_deliveries'=>      ['value'=> 'float'],
                'scheduled_pickup'=>            ['value'=> [0,1]],
                'timezone'=>                    ['value'=> 'string'],
                'cutoff'=>                      ['value'=> 'string'],
                'title'=>                       ['value'=> 'string'],
                'description'=>                 ['value'=> 'string'],
                'fields'=>                      ['value'=> 'json'],
                'plan_id'=>                     ['value'=> 'number'],
                'tax_included'=>                ['value'=> [0,1]],
                'required_connections'=>        ['value'=> 'json'],
                'external_pricing'=>            ['value'=> [0,1]],
                'is_collection'=>               ['value'=> [0,1]],
                'min_pickups'=>                 ['value'=> 'number'],
                'has_push'=>                    ['value'=> [0,1]],
                'external_availability_check'=> ['value'=> [0,1]]

            ],
            'shipping_codes'=>[
                'code'=>                    ['value'=> 'string', 'required'=>true, 'readOnly'=>true],
                'min_weight'=>              ['value'=> 'float'],
                'max_weight'=>              ['value'=> 'float'],
                'initial_free_km'=>         ['value'=> 'float'],
                'min_dimensions'=>          ['value'=> 'json'],
                'max_dimensions'=>          ['value'=> 'json'],
                'min_monthly_deliveries'=>  ['value'=> 'float'],
                'created_at'=>              ['value'=> false],
                'updated_at'=>              ['value'=> false],
                'is_same_day'=>             ['value'=> [0,1]],
                'is_on_demand'=>            ['value'=> [0,1]],
                'is_return'=>               ['value'=> [0,1]],
                'is_international'=>        ['value'=> [0,1]],
                'is_replacement'=>          ['value'=> [0,1]],
                'pickup_max_days'=>         ['value'=> 'number'],
                'dropoff_max_days'=>        ['value'=> 'number']
            ]
        ];
    }

    public function createModelRow(Request $request){
        $model = $request->post('model');
        $row = $request->post('row');
        \Log::info('MODEL_CREATE_ROW', [$model, $row]);
        $create = [];
        $validResponse = $this->validateRequiredFieldsCreate($model, $row);
        if($validResponse !== true){
            return $validResponse;
        }

        foreach ($row as $field=>$value) {
            $validResponse = $this->valid($model, $field, $value, true);
            if($validResponse !== true){
                return $validResponse;
            }
            if( $this->allowed[$model][$field]['value'] === 'json' ){
                $create[$field] = json_decode($value);
            }else{
                $create[$field] = $value;
            }
        }

        switch ($model){
            case 'polygons':        Polygon::create($create); break;
            case 'shipping_codes':  ShippingCode::create($create); break;
            default: return response()->json(['success'=>false, 'error'=>'App model is missing']);
        }
        return response()->json(['success'=>true]);
    }

    public function updateModelRow(Request $request){
        $id = $request->post('id');
        $model = $request->post('model');
        $row = $request->post('row');
        \Log::info('MODEL_UPDATE_ROW', [$id, $model, $row]);
        $update = [];
        foreach ($row as $field=>$value) {
            $validResponse = $this->valid($model, $field, $value['new']);
            if($validResponse !== true){
                return $validResponse;
            }

            if( @$this->allowed[$model][$field]['value'] === 'json' ){
                $update[$field] = json_decode($value['new']);
            }else{
                $update[$field] = $value['new'];
            }
        }
        $updated = DB::table($model)->where('id', $id)->update($update);
        \Log::info('UPDATE', [$update]);
        if(!$updated){
            return response()->json(['success'=>false, 'error'=>'Failed to update']);
        }
        return response()->json(['success'=>true]);
    }

    public function updateModel(Request $request){
        $id = $request->post('id');
        $model = $request->post('model');
        $field = $request->post('field');
        $value = $request->post('value');
        \Log::info('MODEL_UPDATE_ROW', [$id, $model, $field, $value]);

        $validResponse = $this->valid($model, $field, $value);
        if($validResponse !== true){
            return $validResponse;
        }

        if(@$this->allowed[$model][$field]['value'] === 'json') {
            $value = $value ? $value : null;
        }
        \Log::info('UPDATE', [$field => $value]);
        $updated = DB::table($model)->where('id', $id)->update([$field => $value]);

        if(!$updated){
            return response()->json(['success'=>false, 'error'=>'Failed to update']);
        }
        return response()->json(['success'=>true]);
    }



    private function validateRequiredFieldsCreate($model, $row){
        // Validate required
        foreach ($this->allowed[$model] as $field => $allowed) {
            if( @$allowed['required'] && @!$row[$field] ){
                return response()->json(['success'=>false,'error'=>"Failed: $field is a required field "]);
            }
        }
        return true;
    }
    private function valid($model, $field, $value, $newRow = false){
        // Model is undefined
        if( !@$this->allowed[$model] ){
            return response()->json(['success'=>false,'error'=>"Failed: model $model is not configured" ]);
        }
        // Model Field in undefined
        if( !$this->allowed[$model][$field] || !@$this->allowed[$model][$field]['value'] ){
            return response()->json(['success'=>false,'error'=>"Failed:  $model -> $field  is not configured" ]);
        }

        // Validate readOnly
        if(@$this->allowed[$model][$field]['readOnly'] && !$newRow){
            return response()->json(['success'=>false,'error'=>"Failede:  $field is a read only field"]);
        }
        // Validate options
        if( is_array($this->allowed[$model][$field]['value']) ){
            if( !in_array($value, $this->allowed[$model][$field]['value']) ){
                return response()->json(['success'=>false,'error'=>"Invalid value ($value) for $model:  $field"]);
            }
        }
        // Validate number
        if( $this->allowed[$model][$field]['value'] === 'number' ){
            if( $value && !is_numeric($value) ){
                return response()->json(['success'=>false,'error'=>"Invalid number ($value) for $model:  $field"]);
            }
        }
        // Validate json
        if( $this->allowed[$model][$field]['value'] === 'json' ){
            if( $value && !json_validate($value) ){
                return response()->json(['success'=>false,'error'=>"Invalid json ($value) for $model:  $field"]);
            }
        }
        // Validate string
        if( $this->allowed[$model][$field]['value'] === 'string' ){
            // validate string ??
        }
        return true;
    }
}
