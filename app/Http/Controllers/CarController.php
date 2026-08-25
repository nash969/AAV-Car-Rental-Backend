<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'transmission' => 'required|string',
            'seats' => 'required|integer',
            'fuel_type' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'available' => 'boolean',

            'within_12hrs' => 'required|numeric|min:0',
            'within_24hrs' => 'required|numeric|min:0',
            'outside_12hrs' => 'required|numeric|min:0',
            'outside_24hrs' => 'required|numeric|min:0',
            'unli_24hrs' => 'required|numeric|min:0',
        ]);

        $car = DB::transaction(function () use ($request, $validated) {

            $imagePath = null;

            if ($request->hasFile('image')) {
                $path = $request->file('image')->store(
                    'vehicle-images',
                    'public'
                );

                $imagePath = '/storage/' . $path;
            }

            $car = Car::create([
                'brand' => $validated['brand'],
                'model' => $validated['model'],
                'year' => $validated['year'],

                // Keep this column populated for now
                'price_per_day' => $validated['within_24hrs'],

                'transmission' => $validated['transmission'],
                'seats' => $validated['seats'],
                'fuel_type' => $validated['fuel_type'],
                'image' => $imagePath,
                'available' => $validated['available'] ?? true,
            ]);

            $car->rates()->createMany([
                [
                    'location' => 'within',
                    'duration' => '12hrs',
                    'price' => $validated['within_12hrs'],
                ],
                [
                    'location' => 'within',
                    'duration' => '24hrs',
                    'price' => $validated['within_24hrs'],
                ],
                [
                    'location' => 'outside',
                    'duration' => '12hrs',
                    'price' => $validated['outside_12hrs'],
                ],
                [
                    'location' => 'outside',
                    'duration' => '24hrs',
                    'price' => $validated['outside_24hrs'],
                ],
                [
                    'location' => 'unli',
                    'duration' => '24hrs',
                    'price' => $validated['unli_24hrs'],
                ],
            ]);

            return $car;
        });

        return response()->json([
            'message' => 'Car and rental rates added successfully!',
            'data' => $car->load('rates')
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
        $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer',
            'transmission' => 'required|string',
            'seats' => 'required|integer',
            'fuel_type' => 'required|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'available' => 'boolean',

            'within_12hrs' => 'required|numeric|min:0',
            'within_24hrs' => 'required|numeric|min:0',
            'outside_12hrs' => 'required|numeric|min:0',
            'outside_24hrs' => 'required|numeric|min:0',
            'unli_24hrs' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $validated, $car) {

            $carData = [
                'brand' => $validated['brand'],
                'model' => $validated['model'],
                'year' => $validated['year'],
                'price_per_day' => $validated['within_24hrs'],
                'transmission' => $validated['transmission'],
                'seats' => $validated['seats'],
                'fuel_type' => $validated['fuel_type'],
                'available' => $validated['available'] ?? true,
            ];

            // Replace image only if admin selected a new file
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store(
                    'vehicle-images',
                    'public'
                );

                $carData['image'] = '/storage/' . $path;
            }

            $car->update($carData);

            $car->rates()->updateOrCreate(
                [
                    'location' => 'within',
                    'duration' => '12hrs',
                ],
                [
                    'price' => $validated['within_12hrs'],
                ]
            );

            $car->rates()->updateOrCreate(
                [
                    'location' => 'within',
                    'duration' => '24hrs',
                ],
                [
                    'price' => $validated['within_24hrs'],
                ]
            );

            $car->rates()->updateOrCreate(
                [
                    'location' => 'outside',
                    'duration' => '12hrs',
                ],
                [
                    'price' => $validated['outside_12hrs'],
                ]
            );

            $car->rates()->updateOrCreate(
                [
                    'location' => 'outside',
                    'duration' => '24hrs',
                ],
                [
                    'price' => $validated['outside_24hrs'],
                ]
            );
            
            $car->rates()->updateOrCreate(
                [
                    'location' => 'unli',
                    'duration' => '24hrs',
                ],
                [
                    'price' => $validated['unli_24hrs'],
                ]
            );
        });

        return response()->json([
            'message' => 'Car and rental rates updated successfully!',
            'data' => $car->fresh()->load('rates')
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
