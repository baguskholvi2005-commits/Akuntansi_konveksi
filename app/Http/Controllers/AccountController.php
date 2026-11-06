<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function password()
    {
        return view('account.password');
    }

    public function update(Request $r)
    {
        $d = $r->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|min:8|max:100|confirmed|different:current_password',
        ]);

        $user = $r->user();
        $user->password = Hash::make($d['password']);
        $user->save();

        return back()->with('success', 'Kata sandi berhasil diganti.');
    }
}