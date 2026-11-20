<?php

namespace App\Http\Controllers;

use App\Mail\GardenInviteMail;
use App\Models\GardenInvitation;
use App\Models\GardenMembership;
use App\Services\GardenSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GardenSharingController extends Controller
{
    public function __construct(private readonly GardenSharing $sharing) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('garden-sharing.index', [
            'invitations' => GardenInvitation::query()->where('owner_id', $user->id)->pending()->with('plant:id,name')->latest()->get(),
            'members' => GardenMembership::query()->where('owner_id', $user->id)->with(['member', 'plant:id,name'])->latest()->get(),
            'sharedGardens' => GardenMembership::query()->where('member_id', $user->id)->with(['owner', 'plant:id,name'])->latest()->get(),
            'ownedPlants' => $user->plants()->orderBy('name')->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function invite(Request $request): Response
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'plant_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $email = $data['email'];
        $plantId = isset($data['plant_id']) ? (int) $data['plant_id'] : null;
        $token = $this->sharing->invite($request->user(), $email, $plantId);
        $url = route('garden-sharing.invitations.show', $token);
        $sent = false;
        if (! in_array(config('mail.default'), ['log', 'array'], true)) {
            try {
                Mail::to($email)->send(new GardenInviteMail($request->user()->email, $url));
                $sent = true;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response()->view('garden-sharing.link', [
            'email' => $email,
            'inviteUrl' => $url,
            'invitationSent' => $sent,
        ], 201, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function showInvitation(Request $request, string $token): Response
    {
        $invitation = $this->sharing->findInvitation($token);
        $this->sharing->ensureCanAccept($invitation, $request->user());

        return response()->view('garden-sharing.join', [
            'invitation' => $invitation,
            'token' => $token,
        ], 200, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $this->sharing->accept($this->sharing->findInvitation($token), $request->user());

        return to_route('browse')->header('Referrer-Policy', 'no-referrer');
    }

    public function revoke(Request $request, GardenInvitation $invitation): RedirectResponse
    {
        $this->sharing->revoke($invitation, $request->user());

        return to_route('garden-sharing.index')->with('sharing_status', 'Invitation revoked.');
    }

    public function removeMembership(Request $request, GardenMembership $membership): RedirectResponse
    {
        $this->sharing->removeMembership($membership, $request->user());

        $message = $membership->owner_id === $request->user()->id
            ? 'Member access removed.'
            : 'You left the shared garden.';

        return to_route('garden-sharing.index')->with('sharing_status', $message);
    }
}
