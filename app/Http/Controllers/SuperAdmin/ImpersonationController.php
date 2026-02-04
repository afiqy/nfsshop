<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonationService)
    {
    }

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value();

        $ownersQuery = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->with('tenant');

        if ($search !== '') {
            $ownersQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('workshop_name', 'like', "%{$search}%")
                    ->orWhere('tenant_id', 'like', "%{$search}%");
            });
        }

        $owners = $ownersQuery
            ->orderBy('workshop_name')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('super-admin.impersonation.index', [
            'owners' => $owners,
            'search' => $search === '' ? null : $search,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $owner = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->findOrFail($validated['owner_id']);

        $impersonatorId = Auth::id();

        if (! $impersonatorId) {
            return redirect()->route('login');
        }

        try {
            $this->impersonationService->start($owner, (int) $impersonatorId, $request->session());
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->withErrors($exception->getMessage());
        }

        $workshop = $owner->workshop_name ?? optional($owner->tenant)->data['workshop_name'] ?? $owner->tenant_id;

        return redirect()->route('dashboard')->with('success', "Switched into {$workshop} as {$owner->name}.");
    }
}
