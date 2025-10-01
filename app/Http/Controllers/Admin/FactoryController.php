<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Factory;
use Illuminate\Http\Request;

class FactoryController extends Controller
{
    /**
     * Список фабрик (по желанию можно фильтровать).
     * /api/admin/factories/pending — у тебя уже висит на этом методе.
     */
    public function index(Request $request)
    {
        $q = Factory::query();

        // пример использования параметров фильтра
        if ($request->has('is_verified')) {
            $q->where('is_verified', (bool)$request->boolean('is_verified'));
        }

        return response()->json($q->orderByDesc('id')->get());
    }

    /**
     * Верифицировать фабрику до указанной даты.
     * POST /api/admin/factories/{id}/approve  body: { verified_until: 'YYYY-MM-DD' }
     */
    public function approve(Request $request, int $id)
    {
        $request->validate([
            'verified_until' => ['required','date'],
        ]);

        $factory = Factory::findOrFail($id);
        $factory->is_verified    = true;
        $factory->verified_until = $request->input('verified_until');
        $factory->save();

        return response()->json([
            'message' => 'Фабрику верифіковано',
            'factory' => $factory,
        ]);
    }

    /**
     * Полное удаление фабрики.
     */
    public function reject(int $id)
    {
        $factory = Factory::findOrFail($id);
        $factory->delete();

        return response()->json(['message' => 'Фабрика видалена']);
    }

    /**
     * Координаты фабрик для карты (водитель создаёт заказ у виробника).
     * Отдаём только верифицированных и обязательно — water_types.
     */
    public function coordinates()
    {
        $rows = Factory::query()
            ->select('id','email','website','warehouse_address','lat','lng','water_types')
            ->where('is_verified', true)
            ->where(function ($q) {
                $q->whereNull('verified_until')
                    ->orWhereDate('verified_until', '>=', now()->toDateString());
            })
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get([
                'id',
                'email',
                'website',
                'warehouse_address',
                'lat',
                'lng',
                'water_types', // <-- добавить
            ]);

        return response()->json($rows);
    }

    /**
     * Список верифицированных (для вкладки “Верифіковані виробники”).
     * GET /api/factories/verified
     */
    public function verified()
    {
        $rows = Factory::query()
            ->where('is_verified', true)
            ->where(function ($q) {
                $q->whereNull('verified_until')
                    ->orWhereDate('verified_until', '>=', now()->toDateString());
            })
            ->orderByDesc('id')
            ->get();

        return response()->json($rows);
    }
}
