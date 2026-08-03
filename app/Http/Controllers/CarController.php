<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;

class CarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Car::with('rates')->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer',
            'price_per_day' => 'required|numeric',
            'transmission' => 'required|string',
            'seats' => 'required|integer',
            'fuel_type' => 'required|string',
            'image' => 'nullable|string',
            'available' => 'boolean'
        ]);

        $car = Car::create($validated);

        return response()->json([
            'message' => 'Car added successfully!',
            'data' => $car
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Car $car)
    {
        return response()->json($car->load('rates'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Car $car)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Car $car)
    {
         $car->update($request->all());

        return response()->json([
            'message' => 'Car updated successfully!',
            'data' => $car
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Car $car)
    {
         $car->delete();

        return response()->json([
            'message' => 'Car deleted successfully!'
        ]);
    }
}
