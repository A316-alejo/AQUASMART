<?php

namespace App\Http\Controllers;

use App\Http\Requests\Farms\StoreFarmAssignmentRequest;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FarmAssignmentController extends Controller
{
    public function store(StoreFarmAssignmentRequest $request, Farm $farm): RedirectResponse
    {
        $user = User::findOrFail($request->validated('user_id'));

        $farm->users()->attach($user);

        return redirect()->route('farms.show', $farm)
            ->with('status', "{$user->name} quedó asignado a {$farm->name}.");
    }

    public function destroy(Farm $farm, User $user): RedirectResponse
    {
        Gate::authorize('manageAssignments', $farm);

        abort_unless($farm->users()->detach($user) > 0, 404);

        return redirect()->route('farms.show', $farm)
            ->with('status', "{$user->name} ya no está asignado a {$farm->name}.");
    }
}
