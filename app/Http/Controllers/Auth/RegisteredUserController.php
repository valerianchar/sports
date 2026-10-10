<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Un compte neuf part de zéro : l'assistant compose la première séance.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->only('name', 'email', 'password'));

        Auth::login($user);
        $request->session()->regenerate();

        // Venu d'un lien de séance partagée : on y retourne.
        return redirect()->intended(route('workouts.index'))
            ->with('success', "Bienvenue {$user->first_name} ! L'assistant compose ta première séance.");
    }
}
