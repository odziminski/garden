<?php

namespace App\Http\Controllers;

use App\Enums\CareType;
use App\Models\Plant;
use App\Services\PlantCareSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NeedsController extends Controller
{
    public function updateWatering(Plant $plant): RedirectResponse
    {
        $this->recordCare($plant, CareType::Watering, now($plant->careTimezone()));

        return back();
    }

    public function updateFertilizing(Plant $plant): RedirectResponse
    {
        $this->recordCare($plant, CareType::Fertilizing, now($plant->careTimezone()));

        return back();
    }

    public function storeCareLog(Request $request, Plant $plant): RedirectResponse
    {
        $timezone = $plant->careTimezone();
        $today = now($timezone)->toDateString();
        $care = $request->validateWithBag('care', [
            'type' => ['required', Rule::enum(CareType::class)],
            'care_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1970-01-02', 'before_or_equal:'.$today],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($care['care_date'], $timezone);
        $this->recordCare($plant, CareType::from($care['type']), $date, $care['note'] ?? null, $care['care_date']);

        return redirect()->route('plants', $plant)->with('care_status', 'Care entry added.');
    }

    public function today(Request $request): View
    {
        $asOf = now($request->user()->reminder_timezone ?? 'UTC');
        $plants = $request->user()->plants()->with('owner', 'history', 'needs')->orderBy('name')->get();
        $duePlants = $plants->map(function (Plant $plant) use ($asOf) {
            $actions = collect(CareType::cases())->map(function (CareType $type) use ($plant, $asOf) {
                $date = PlantCareSchedule::nextDate($plant, $type, $asOf);

                return $date !== null && $date->lte($asOf) ? ['type' => $type, 'date' => $date] : null;
            })->filter()->sortBy('date');

            return ['plant' => $plant, 'actions' => $actions];
        })->filter(fn (array $item) => $item['actions']->isNotEmpty())
            ->sortBy(fn (array $item) => $item['actions']->first()['date']);

        return view('today', ['duePlants' => $duePlants, 'today' => $asOf]);
    }

    private function recordCare(
        Plant $plant,
        CareType $type,
        CarbonInterface $date,
        ?string $note = null,
        ?string $performedOn = null,
    ): void {
        $performedOn ??= $date->toDateString();
        $performedAt = $date->copy()->utc();

        DB::transaction(function () use ($plant, $type, $performedOn, $performedAt, $note) {
            $lockedPlant = Plant::query()->lockForUpdate()->findOrFail($plant->id);
            $history = $lockedPlant->history()->firstOrNew();
            $column = $type->lastCareColumn();
            $dateColumn = $type->lastCareDateColumn();

            $lockedPlant->careLogs()->create([
                'type' => $type,
                'performed_at' => $performedAt,
                'performed_on' => $performedOn,
                'note' => $note,
            ]);

            $newerInstant = $history->$column === null || $performedAt->gt($history->$column);
            if ($newerInstant) {
                $history->$column = $performedAt;
            }
            if ($history->$dateColumn === null ? $newerInstant : $performedOn > $history->$dateColumn) {
                $history->$dateColumn = $performedOn;
            }
            if ($history->isDirty()) {
                $history->save();
            }

            $lockedPlant->setRelation('history', $history);
            PlantCareSchedule::syncFlags($lockedPlant);
        }, 3);
    }
}
