<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function company()
    {
        return view('settings.company', ['s' => Setting::values()]);
    }

    public function saveCompany(Request $r)
    {
        $d = $r->validate([
            'company_name'    => 'required|max:100',
            'company_tagline' => 'nullable|max:100',
            'company_address' => 'nullable|max:255',
            'company_phone'   => 'nullable|max:30',
            'company_email'   => 'nullable|email|max:100',
            'logo'            => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'favicon'         => 'nullable|image|mimes:png,jpg,jpeg|max:512',
        ]);

        $pairs = Arr::only($d, ['company_name', 'company_tagline', 'company_address', 'company_phone', 'company_email']);

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $field => $key) {
            if ($r->boolean('remove_' . $field)) {
                $this->drop($key);
                $pairs[$key] = null;
            }
            if ($r->hasFile($field)) {
                $this->drop($key);
                $pairs[$key] = $r->file($field)->store('branding', 'public');
            }
        }

        Setting::put($pairs);

        return redirect()->route('settings.company')->with('success', 'Profil perusahaan disimpan.');
    }

    private function drop(string $key): void
    {
        $old = Setting::val($key);
        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }

    public function appearance()
    {
        return view('settings.appearance', ['s' => Setting::values()]);
    }

    public function saveAppearance(Request $r)
    {
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $d = $r->validate(['sidebar_color' => $hex, 'accent_color' => $hex]);

        Setting::put([
            'sidebar_color'     => strtoupper($d['sidebar_color']),
            'accent_color'      => strtoupper($d['accent_color']),
            'sidebar_collapsed' => $r->boolean('sidebar_collapsed') ? '1' : '0',
        ]);

        return redirect()->route('settings.appearance')->with('success', 'Tampilan disimpan.');
    }

    public function resetAppearance()
    {
        $def = Setting::defaults();
        Setting::put([
            'sidebar_color'     => $def['sidebar_color'],
            'accent_color'      => $def['accent_color'],
            'sidebar_collapsed' => $def['sidebar_collapsed'],
        ]);

        return redirect()->route('settings.appearance')->with('success', 'Tampilan dikembalikan ke bawaan.');
    }
}