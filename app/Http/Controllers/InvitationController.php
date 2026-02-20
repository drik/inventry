<?php

namespace App\Http\Controllers;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = Invitation::withoutGlobalScopes()
            ->where('token', $token)
            ->where('status', InvitationStatus::Pending)
            ->firstOrFail();

        if ($invitation->isExpired()) {
            $invitation->update(['status' => InvitationStatus::Expired]);

            return view('invitations.expired', ['invitation' => $invitation]);
        }

        // Check if user already exists in this org
        $existingUser = User::where('organization_id', $invitation->organization_id)
            ->where('email', $invitation->email)
            ->first();

        if ($existingUser) {
            $invitation->markAsAccepted();

            return redirect('/app/' . $invitation->organization->slug)
                ->with('message', 'You are already a member of this organization.');
        }

        return view('invitations.accept', [
            'invitation' => $invitation,
            'organization' => $invitation->organization,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::withoutGlobalScopes()
            ->where('token', $token)
            ->where('status', InvitationStatus::Pending)
            ->firstOrFail();

        if ($invitation->isExpired()) {
            $invitation->update(['status' => InvitationStatus::Expired]);

            return view('invitations.expired', ['invitation' => $invitation]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Check if a user already exists with this email in this org
        $existingUser = User::where('organization_id', $invitation->organization_id)
            ->where('email', $invitation->email)
            ->first();

        if ($existingUser) {
            $invitation->markAsAccepted();
            Auth::login($existingUser);

            return redirect('/app/' . $invitation->organization->slug);
        }

        $user = User::create([
            'organization_id' => $invitation->organization_id,
            'name' => $validated['name'],
            'email' => $invitation->email,
            'password' => $validated['password'],
            'role' => $invitation->role,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $invitation->markAsAccepted();

        Auth::login($user);

        return redirect('/app/' . $invitation->organization->slug);
    }
}
