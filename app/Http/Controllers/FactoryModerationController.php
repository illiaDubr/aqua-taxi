<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Factory;
use Illuminate\Http\Request;

class FactoryModerationController extends Controller
{
    public function show($id)
    {
        $factory = Factory::findOrFail($id);
        return response()->json($factory);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'verified_until' => 'required|date',
        ]);

        $factory = Factory::findOrFail($id);
        $factory->is_verified = true;
        $factory->verified_until = $request->input('verified_until');
        $factory->save();

        return response()->json(['message' => 'Factory approved']);
    }

    public function reject($id)
    {
        $factory = Factory::findOrFail($id);
        $factory->delete();

        return response()->json(['message' => 'Factory rejected and deleted']);
    }
}
