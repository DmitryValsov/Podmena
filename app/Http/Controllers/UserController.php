<?php
namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;

class UserController extends Controller
{
    public function show(User $user): \Inertia\Response
    {
        return Inertia::render('user/Show', [
            //'user' => $user
        ]);
    }

    public function raspisanie(User $user): \Inertia\Response
    {
        return Inertia::render('user/Raspisanie', [
            //'user' => $user
        ]);
    }


    public function raspisanie_admin(User $user): \Inertia\Response
    {
        return Inertia::render('admin/Raspisanie', [
            //'user' => $user
        ]);
    }

}
