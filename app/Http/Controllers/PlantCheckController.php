<?php

namespace App\Http\Controllers;

use App\Enums\PlantCheckOutcome;
use App\Models\Plant;
use App\Models\PlantCheckSchedule;
use App\Services\PlantCareAccess;
use App\Services\PlantChecks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlantCheckController extends Controller
{
    public function schedule(Request $request, Plant $plant, PlantChecks $checks): RedirectResponse
    {
        $details = $request->validateWithBag('plantCheckSchedule', [
            'enabled' => ['required', 'boolean'],
            'next_check_on' => $this->dateRules(),
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'use_legacy_interval' => ['sometimes', 'boolean'],
        ]);

        try {
            DB::transaction(function () use ($request, $plant, $checks, $details) {
                $actor = $request->user();
                $lockedPlant = PlantCareAccess::lock($plant, $actor);
                abort_unless($lockedPlant->isOwnedBy($actor), 404);
                $checks->configureLockedPlant(
                    $lockedPlant,
                    (bool) $details['enabled'],
                    $details['next_check_on'] ?? null,
                    isset($details['interval_days']) ? (int) $details['interval_days'] : null,
                    (bool) ($details['use_legacy_interval'] ?? false),
                );
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('plantCheckSchedule');
        }

        return $this->backToChecks($plant)->with('plant_check_status', 'Check schedule saved.');
    }

    public function store(Request $request, Plant $plant, PlantChecks $checks): RedirectResponse
    {
        $details = $request->validateWithBag('plantCheck', [
            'outcome' => ['required', Rule::enum(PlantCheckOutcome::class)],
            'schedule_version' => ['required', 'integer', 'min:0'],
            'next_check_on' => $this->dateRules(),
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $checks->record(
                $plant,
                $request->user(),
                PlantCheckOutcome::from($details['outcome']),
                (int) $details['schedule_version'],
                $details['next_check_on'] ?? null,
                $details['note'] ?? null,
            );
        } catch (ValidationException $exception) {
            throw $exception->errorBag('plantCheck');
        }

        $response = $this->backToChecks($plant);
        if ($result instanceof PlantCheckSchedule) {
            return $response->with('plant_check_status', 'Check deferred.')
                ->with('plant_check_skip_undo', [$plant->getKey(), $result->version]);
        }
        if ($result !== null) {
            return $response->with('plant_check_status', 'Check recorded.')
                ->with('plant_check_undo', [$plant->getKey(), $result->getKey()]);
        }

        return $response->with('plant_check_notice', 'This check has already been handled or the schedule changed.');
    }

    public function undo(Request $request, Plant $plant, string $check, PlantChecks $checks): RedirectResponse
    {
        $details = $request->validate(['history_only' => ['sometimes', 'boolean']]);
        $historyOnly = (bool) ($details['history_only'] ?? false);
        $undone = $checks->undo($plant, $request->user(), $check, $historyOnly);
        if ($historyOnly && $undone) {
            return $this->backToChecks($plant)->with('plant_check_status', 'Check removed from history.');
        }

        return $this->undoResponse($plant, $undone);
    }

    public function undoSkip(Request $request, Plant $plant, PlantChecks $checks): RedirectResponse
    {
        $validated = $request->validateWithBag('plantCheck', [
            'schedule_version' => ['required', 'integer', 'min:1'],
        ]);

        return $this->undoResponse($plant, $checks->undoSkip($plant, $request->user(), (int) $validated['schedule_version']));
    }

    private function undoResponse(Plant $plant, bool $undone): RedirectResponse
    {
        return $undone
            ? $this->backToChecks($plant)->with('plant_check_status', 'Check action undone.')
            : $this->backToChecks($plant)->with('plant_check_notice', 'The schedule has changed since this action.');
    }

    private function backToChecks(Plant $plant): RedirectResponse
    {
        return back(302, [], route('plants', $plant).'#plant-checks');
    }

    private function dateRules(): array
    {
        return ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];
    }
}
