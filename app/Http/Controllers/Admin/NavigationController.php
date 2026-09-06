<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PanelNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    /**
     * Toggle whether a module shows in the current user's top navigation.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $key = $data['key'];

        $hidden = collect(PanelNavigation::hiddenKeys());

        $hidden = $hidden->contains($key)
            ? $hidden->reject(fn (string $k): bool => $k === $key)
            : $hidden->push($key);

        $user->navigation_preferences = [
            'hidden' => $hidden->unique()->values()->all(),
        ];
        $user->save();

        return back();
    }
}
