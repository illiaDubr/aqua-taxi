<?php

namespace App\Http\Controllers;

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
    public function moderateCertificate(Request $request, $factoryId)
    {
        $request->validate([
            'status' => 'required|in:valid,invalid',
            'expiration_date' => 'nullable|date',
        ]);

        $factory = Factory::findOrFail($factoryId);

        $factory->certificate_status = $request->status;
        $factory->certificate_expiration = $request->expiration_date;
        $factory->save();

        return response()->json(['message' => 'Сертификат обновлен администратором.'], 200);
    }
    public function factoriesWithCertificates()
    {
        $factories = Factory::whereNotNull('certificate_path')->get();

        return response()->json($factories, 200);
    }
}
