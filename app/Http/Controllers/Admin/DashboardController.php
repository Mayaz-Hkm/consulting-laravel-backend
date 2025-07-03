<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $users = User::latest()->take(5)->get();
        $usersCount = User::count();

        return view('dashboard', compact('users', 'usersCount'));
    }

}
