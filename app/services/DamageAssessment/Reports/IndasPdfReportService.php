<?php

namespace App\Services\DamageAssessment\Reports;

use App\Models\Building;
use App\Models\HousingUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndasPdfReportService
{
    public function build(Request $request): array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : null;

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : null;

        $buildingsQuery = Building::query();

        $housingQuery = HousingUnit::query();

        /*
        |--------------------------------------------------------------------------
        | Date Filter
        |--------------------------------------------------------------------------
        */

        if ($startDate && $endDate) {

            $buildingsQuery->whereBetween('creationdate', [
                $startDate,
                $endDate
            ]);

            $housingQuery->whereBetween('creationdate', [
                $startDate,
                $endDate
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Governorate Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('governorate')) {

            $buildingsQuery->where(function ($q) use ($request) {
                $q->where('governorate', $request->governorate)
                    ->orWhere('Governorate', $request->governorate);
            });

            $housingQuery->where(function ($q) use ($request) {
                $q->where('governorate', $request->governorate)
                    ->orWhere('Governorate', $request->governorate);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Municipality Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('municipalitie')) {

            $buildingsQuery->where(function ($q) use ($request) {
                $q->where('municipalitie', $request->municipalitie);
            });

            $housingQuery->where(function ($q) use ($request) {
                $q->where('municipalitie', $request->municipalitie);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Main Statistics
        |--------------------------------------------------------------------------
        */

        $totalBuildings = (clone $buildingsQuery)->count();

        $totalHousingUnits = (clone $housingQuery)->count();

        $assessedBuildings = (clone $buildingsQuery)
            ->whereNotNull('building_damage_status')
            ->where('building_damage_status', '!=', '')
            ->count();

        $assessedHousingUnits = (clone $housingQuery)
            ->whereNotNull('unit_damage_status')
            ->where('unit_damage_status', '!=', '')
            ->count();

        $affectedPopulation = round($assessedHousingUnits * 5.3);

        /*
        |--------------------------------------------------------------------------
        | Damage Statistics
        |--------------------------------------------------------------------------
        */

        $damageStats = [
            'minor' => (clone $housingQuery)
                ->where(function ($q) {
                    $q->where('unit_damage_status', 'Minor')
                        ->orWhere('unit_damage_status', 'Minor');
                })
                ->count(),

            'moderate' => (clone $housingQuery)
                ->where(function ($q) {
                    $q->where('unit_damage_status', 'Moderate')
                        ->orWhere('unit_damage_status', 'Moderate');
                })
                ->count(),

            'severe' => (clone $housingQuery)
                ->where(function ($q) {
                    $q->where('unit_damage_status', 'Severe')
                        ->orWhere('unit_damage_status', 'Severe');
                })
                ->count(),

            'destroyed' => (clone $housingQuery)
                ->where(function ($q) {
                    $q->where('unit_damage_status', 'Destroyed')
                        ->orWhere('unit_damage_status', 'Destroyed');
                })
                ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Governorates
        |--------------------------------------------------------------------------
        */

        $governorates = (clone $housingQuery)
            ->select([
                DB::raw("
                    COALESCE(governorate, Governorate) as governorate_name
                "),
                DB::raw("COUNT(*) as total_units"),
            ])
            ->groupBy('governorate_name')
            ->orderBy('total_units', 'DESC')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Municipalities
        |--------------------------------------------------------------------------
        */
        $municipalities = (clone $housingQuery)
            ->select([
                'municipalitie as municipality_name',
                DB::raw("COUNT(*) as total_units"),
            ])
            ->whereNotNull('municipalitie')
            ->groupBy('municipalitie')
            ->orderBy('total_units', 'DESC')
            ->limit(20)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Neighborhoods
        |--------------------------------------------------------------------------
        */

        $neighborhoods = (clone $housingQuery)
            ->select([
                'neighborhood',
                DB::raw("COUNT(*) as total_units"),
            ])
            ->whereNotNull('neighborhood')
            ->groupBy('neighborhood')
            ->orderBy('total_units', 'DESC')
            ->limit(20)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Governorate Detailed Statistics
        |--------------------------------------------------------------------------
        */

        $governorateDetails = [];

        foreach ($governorates as $gov) {

            $govQuery = HousingUnit::query();

            $govQuery->where(function ($q) use ($gov) {

                $q->where('governorate', $gov->governorate_name)
                    ->orWhere('Governorate', $gov->governorate_name);
            });

            $governorateDetails[] = [

                'name' => $gov->governorate_name,

                'total_units' => $gov->total_units,

                'minor' => (clone $govQuery)
                    ->where(function ($q) {
                        $q->where('unit_damage_status', 'Minor')
                            ->orWhere('unit_damage_status', 'Minor');
                    })
                    ->count(),

                'moderate' => (clone $govQuery)
                    ->where(function ($q) {
                        $q->where('unit_damage_status', 'Moderate')
                            ->orWhere('unit_damage_status', 'Moderate');
                    })
                    ->count(),

                'severe' => (clone $govQuery)
                    ->where(function ($q) {
                        $q->where('unit_damage_status', 'Severe')
                            ->orWhere('unit_damage_status', 'Severe');
                    })
                    ->count(),

                'destroyed' => (clone $govQuery)
                    ->where(function ($q) {
                        $q->where('unit_damage_status', 'Destroyed')
                            ->orWhere('unit_damage_status', 'Destroyed');
                    })
                    ->count(),
            ];
        }

        return [

            'reportDate' => now()->format('Y-m-d'),

            'totalBuildings' => number_format($totalBuildings),

            'totalHousingUnits' => number_format($totalHousingUnits),

            'assessedBuildings' => number_format($assessedBuildings),

            'assessedHousingUnits' => number_format($assessedHousingUnits),

            'affectedPopulation' => number_format($affectedPopulation),

            'damageStats' => $damageStats,

            'governorates' => $governorates,

            'municipalities' => $municipalities,

            'neighborhoods' => $neighborhoods,

            'governorateDetails' => $governorateDetails,

            'filters' => [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'governorate' => $request->governorate,
                'municipalitie' => $request->municipalitie,
            ],
        ];
    }
}