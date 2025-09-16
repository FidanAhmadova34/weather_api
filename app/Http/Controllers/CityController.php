<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\City;

class CityController extends Controller
{
    // List all cities
    public function index(){
        $cities = City::all();
        return response()->json($cities, 200);
    }

    // Create a new city
    public function store(Request $request){
        $request->validate([
            'city_name' => 'required|string|max:255|unique:cities',
            'country_code' => 'nullable|string|max:3'
        ]);

        $city = City::create([
            'city_name' => $request->city_name,
            'country_code' => $request->country_code
        ]);

        return response()->json($city, 201);
    }

    // Show a specific city by ID
    public function show($id){
        $city = City::find($id);
        if(!$city){
            return response()->json(['error' => 'City not found'], 404);
        }
        return response()->json($city, 200);
    }

    // Update an existing city
    public function update(Request $request, $id){
        $city = City::find($id);
        if(!$city){
            return response()->json(['error' => 'City not found'], 404);
        }

        $request->validate([
            'city_name' => 'sometimes|required|string|max:255|unique:cities,city_name,'.$id,
            'country_code' => 'nullable|string|max:3'
        ]);

        $city->update($request->all());
        return response()->json($city, 200);
    }

    // Delete a city
    public function destroy($id){
        $city = City::find($id);
        if(!$city){
            return response()->json(['error' => 'City not found'], 404);
        }

        $city->delete();
        return response()->json(null, 204);
    }
}
