<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Factory;
use Illuminate\Http\Request;

class FactoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Factory::query();

        if ($request->boolean('is_verified') === false) {
            $query->where('is_verified', false);
        }

        return response()->json($query->get());
    }


    public function approve(Request $request, $id)
    {
        $factory = Factory::findOrFail($id);

        $factory->is_verified = true;
        $factory->verified_until = $request->input('verified_until');
        $factory->save();

        return response()->json(['message' => 'Фабрику верифіковано']);
    }

    public function reject($id)
    {
        $factory = Factory::findOrFail($id);
        $factory->delete();

        return response()->json(['message' => 'Фабрика видалена']);
    }

    public function coordinates()
    {
        $factories = Factory::where('certificate_status', 'valid')
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get([
                'id',
                'email',
                'website',
                'warehouse_address',
                'lat',
                'lng'
            ]);

        return response()->json($factories);
    }
}
