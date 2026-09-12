<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isAdministrator(), 403);

        return view('team.index', ['users' => User::orderBy('is_active')->orderBy('name')->paginate(20)]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)], 'role' => ['required', 'in:administrator,team_member']]);
        $user = new User;
        $user->name = $data['name'];
        $user->email = strtolower($data['email']);
        $user->password = $data['password'];
        $user->role = $data['role'];
        $user->is_active = true;
        $user->save();

        return back()->with('success', 'Active account created. Share its credentials privately with the team member.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $data = $request->validate(['role' => ['required', 'in:administrator,team_member'], 'is_active' => ['required', 'boolean']]);
        if ($user->id === $request->user()->id && (! $data['is_active'] || $data['role'] !== 'administrator')) {
            return back()->withErrors(['team' => 'You cannot deactivate or demote your own administrator account.']);
        }
        DB::transaction(function () use ($user, $data, $request) {
            $admins = User::where('role', 'administrator')->where('is_active', true)->lockForUpdate()->get();
            $user = User::lockForUpdate()->findOrFail($user->id);
            abort_if($user->isAdministrator() && $user->is_active && $admins->count() <= 1 && (! $data['is_active'] || $data['role'] !== 'administrator'), 422, 'Keep at least one active administrator.');
            $wasActive = $user->is_active;
            $user->role = $data['role'];
            $user->is_active = (bool) $data['is_active'];
            $user->save();
            foreach (FollowUp::where('responsible_id', $user->id)->where('status', 'pending')->with('lead')->get() as $followUp) {
                if (! $followUp->lead || ! $user->can('view', $followUp->lead)) {
                    $followUp->status = 'cancelled';
                    $followUp->closed_at = now();
                    $followUp->save();
                    $followUp->lead?->recordChange('follow_up', 'Pending #'.$followUp->id, 'Cancelled: responsible member no longer has access', $request->user()->id);
                }
            }
            if (! $wasActive && $user->is_active) {
                $user->notify(new CrmNotification('Account approved', 'Your ShadiEvents CRM account is now active. You can sign in.'));
            }
        });

        return back()->with('success', 'Team member updated.');
    }
}
