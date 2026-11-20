<?php

namespace App\Http\Controllers;

use App\Models\PlantHandoff;
use App\Services\PlantHandoffs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlantHandoffController extends Controller
{
    public function __construct(private readonly PlantHandoffs $handoffs) {}

    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $instant = now('UTC');
        $incoming = PlantHandoff::query()->where('member_id', $viewer->id)->accepted()
            ->where('ends_at', '>', $instant)->with('owner:id,email')
            ->orderBy('starts_at')->orderBy('id')->simplePaginate(10, ['*'], 'incoming_page')->withQueryString();
        $incoming->getCollection()->filter(fn (PlantHandoff $handoff) => $handoff->isActiveAt($instant))
            ->load(['plants' => fn ($plants) => $plants->whereColumn('plants.user_id', 'plant_handoffs.owner_id')
                ->join('plant_handoffs', 'plant_handoffs.id', '=', 'plant_handoff_plants.plant_handoff_id')
                ->whereNull('plant_handoffs.revoked_at')->where('plant_handoffs.ends_at', '>', $instant)
                ->with('history', 'latestPlantCheck.checkedBy:id,email')
                ->orderBy('plants.name')->orderBy('plants.id')->select('plants.*')]);

        $outgoing = PlantHandoff::query()->where('owner_id', $viewer->id)
            ->with([
                'member:id,email,email_verified_at',
                'plants' => fn ($plants) => $plants->where('plants.user_id', $viewer->id)->orderBy('plants.name')->orderBy('plants.id'),
            ])
            ->latest('id')->simplePaginate(10, ['*'], 'outgoing_page')->withQueryString();

        return response()->view('handoff.index', [
            'viewer' => $viewer,
            'today' => $instant->copy()->setTimezone($viewer->reminder_timezone)->toDateString(),
            'instant' => $instant,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'ownedPlants' => $viewer->plants()->orderBy('name')->orderBy('id')->get(['id', 'name']),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function invite(Request $request): Response
    {
        $owner = $request->user();
        $today = now($owner->reminder_timezone)->toDateString();
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'starts_on' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'ends_on' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before:9999-12-31'],
            'plant_ids' => ['required', 'array', 'min:1'],
            'plant_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'instructions' => ['nullable', 'array'],
            'instructions.*' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'email' => 'helper email address',
            'starts_on' => 'start date',
            'ends_on' => 'end date',
            'plant_ids' => 'plants',
            'plant_ids.*' => 'plant',
            'instructions.*' => 'care instructions',
        ]);
        $token = $this->handoffs->invite($owner, $data);

        return response()->view('handoff.link', [
            'email' => $data['email'],
            'inviteUrl' => route('handoff.invitation.show', $token),
        ], 201, $this->invitationHeaders());
    }

    public function showInvitation(Request $request, string $token): Response
    {
        $handoff = $this->handoffs->findInvitation($token);
        $this->handoffs->ensureCanAccept($handoff, $request->user());

        return response()->view('handoff.join', ['handoff' => $handoff, 'token' => $token], 200, $this->invitationHeaders());
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $this->handoffs->accept($this->handoffs->findInvitation($token), $request->user());

        return to_route('handoff.index')->with('handoff_status', 'Handoff accepted.')->withHeaders($this->invitationHeaders());
    }

    public function revoke(Request $request, PlantHandoff $handoff): RedirectResponse
    {
        $this->handoffs->revoke($handoff, $request->user());

        return to_route('handoff.index')->with('handoff_status', 'Handoff revoked.');
    }

    private function invitationHeaders(): array
    {
        return ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer'];
    }
}
