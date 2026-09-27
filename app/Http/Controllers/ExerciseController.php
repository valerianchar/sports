<?php

namespace App\Http\Controllers;

use App\Support\ExerciseCatalog;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Exercises/Index', [
            'exercises' => ExerciseCatalog::forClient(),
            'groups' => ExerciseCatalog::groups(),
        ]);
    }
}
