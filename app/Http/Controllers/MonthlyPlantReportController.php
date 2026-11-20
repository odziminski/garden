<?php

namespace App\Http\Controllers;

use App\Services\MonthlyPlantReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MonthlyPlantReportController extends Controller
{
    public function __invoke(Request $request, MonthlyPlantReport $report): Response
    {
        $validator = Validator::make($request->query(), [
            'month' => ['nullable', 'regex:/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D'],
            'plant' => ['nullable', 'integer', 'min:1'],
        ], ['month.regex' => 'Enter a month in YYYY-MM format.']);

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('monthly-report.index'));
        }

        $filters = $validator->validated();
        $user = $request->user();
        $month = CarbonImmutable::createFromFormat('!Y-m', $filters['month'] ?? now($user->reminder_timezone ?? 'UTC')->format('Y-m'), 'UTC');
        $plants = $user->plants()->orderBy('name')->orderBy('id')->get(['id', 'name']);
        $plant = isset($filters['plant'])
            ? $plants->firstWhere('id', (int) $filters['plant']) ?? abort(404)
            : $plants->first();

        return response()->view('monthly-report', [
            'month' => $month,
            'plants' => $plants,
            'plant' => $plant,
            'report' => $plant ? $report->forPlant($plant, $month) : null,
        ], 200, ['Cache-Control' => 'private, no-store']);
    }
}
