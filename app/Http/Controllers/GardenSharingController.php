<?php

namespace App\Http\Controllers;

use App\Models\GardenInvitation;
use App\Models\GardenMembership;
use App\Services\GardenSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GardenSharingController extends Controller
{
    public function __construct(private readonly GardenSharing $sharing) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('garden-sharing.index', [
            'invitations' => GardenInvitation::query()->where('owner_id', $user->id)->pending()->latest()->get(),
            'members' => GardenMembership::query()->where('owner_id', $user->id)->with('member')->latest()->get(),
            'sharedGardens' => GardenMembership::query()->where('member_id', $user->id)->with('owner')->latest()->get(),
        ]);
    }

    public function invite(Request $request): Response
    {
        $email = $request->validate(['email' => ['required', 'string', 'email', 'max:255']])['email'];
        $token = $this->sharing->invite($request->user(), $email);

        return response()->view('garden-sharing.link', [
            'email' => $email,
            'inviteUrl' => route('garden-sharing.invitations.show', $token),
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
