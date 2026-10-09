<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PreferencesController extends Controller
{
    /**
     * Les réglages : son, alertes, séance, compte.
     */
    public function edit(): Response
    {
        return Inertia::render('Settings/Index');
    }

    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Réglages enregistrés.');
    }
}
