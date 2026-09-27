<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CreateStarterWorkouts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request, CreateStarterWorkouts $createStarterWorkouts): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $createStarterWorkouts): User {
            $user = User::create($request->only('name', 'email', 'password'));
            $createStarterWorkouts->handle($user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('workouts.index')
            ->with('success', "Bienvenue {$user->first_name} ! Trois séances t'attendent pour commencer.");
    }
}
