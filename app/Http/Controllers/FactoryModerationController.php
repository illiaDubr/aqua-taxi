<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        return response()->json(['message' => 'Factory approved', 'factory' => $factory]);
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

        return response()->json(['message' => 'Сертифікат оновлено.', 'factory' => $factory], 200);
    }

    /**
     * FIX: учитываем и certificate_path, и legacy certificate_file.
     * На выходе всегда приводим к certificate_path.
     */
    public function factoriesWithCertificates()
    {
        $factories = Factory::query()
            ->where(function ($q) {
                $q->whereNotNull('certificate_path')
                    ->orWhereNotNull('certificate_file');
            })
            ->get()
            ->map(function ($f) {
                if (!$f->certificate_path && $f->certificate_file) {
                    $f->certificate_path = $f->certificate_file;
                }
                return $f;
            });

        return response()->json($factories, 200);
    }

    /** Скасувати верифікацію (перевести у не верифіковані) */
    public function unverify($id)
    {
        $factory = Factory::findOrFail($id);
        $factory->is_verified = false;
        $factory->verified_until = null;
        $factory->save();

        return response()->json(['message' => 'Верифікацію скасовано.', 'factory' => $factory], 200);
    }

    /** Видалити сертифікат (файл + метадані) */
    public function deleteCertificate($id)
    {
        $factory = Factory::findOrFail($id);

        if ($factory->certificate_path) {
            // принимаем "storage/certificates/...", "certificates/...", "/storage/..."
            $raw  = ltrim($factory->certificate_path, '/');
            $path = preg_replace('#^storage/#', '', $raw); // => "certificates/..."
            try {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            } catch (\Throwable $e) {
                // опционально залогировать
            }
        }

        $factory->certificate_path = null;
        // по твоей бизнес-логике можно поставить 'invalid', но тогда запись попадёт в список модерации
        $factory->certificate_status = null;
        $factory->certificate_expiration = null;
        $factory->save();

        return response()->json(['message' => 'Сертифікат видалено.', 'factory' => $factory], 200);
    }
}
