<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const ROLES = ['admin', 'keuangan', 'gudang', 'pemilik'];

    public function index()
    {
        return view('settings.users', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'name'     => 'required|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'role'     => ['required', Rule::in(self::ROLES)],
            'password' => 'required|min:8|max:100',
        ]);

        User::create([
            'name'     => $d['name'],
            'email'    => $d['email'],
            'role'     => $d['role'],
            'password' => Hash::make($d['password']),
        ]);

        return back()->with('success', 'Pengguna ditambahkan.');
    }

    public function update(Request $r, User $user)
    {
        $d = $r->validate([
            'name'     => 'required|max:100',
            'role'     => ['required', Rule::in(self::ROLES)],
            'password' => 'nullable|min:8|max:100',
        ]);

        $user->name = $d['name'];

        // Admin tidak boleh mengubah peran dirinya sendiri, supaya sistem tidak kehilangan admin
        if ($user->id !== auth()->id()) {
            $user->role = $d['role'];
        }
        if (!empty($d['password'])) {
            $user->password = Hash::make($d['password']);
        }
        $user->save();

        return back()->with('success', 'Data pengguna diperbarui.');
    }

    public function toggle(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return back()->with('success', $user->is_active ? 'Pengguna diaktifkan.' : 'Pengguna dinonaktifkan.');
    }
}