<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('profile.index', [
            'user' => $request->user(),
            /** Default preferensi agar UI selalu punya nilai. */
            'preferences' => array_replace([
                'theme_mode' => 'system',
                'language' => 'id',
            ], (array) ($request->user()->preferences ?? [])),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $request->user()->fill($data)->save();

        return redirect()->route('profile')->with('ok', 'Profil diperbarui.');
    }

    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->avatar_path = $request->file('avatar')->store("avatars/{$user->getKey()}", 'public');
        $user->save();

        return redirect()->route('profile')->with('ok', 'Foto profil diperbarui.');
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->avatar_path = null;
        $user->save();

        return redirect()->route('profile')->with('ok', 'Foto profil dihapus.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:current_password'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return redirect()->route('profile')
                ->withErrors(['current_password' => 'Password lama salah.']);
        }

        $request->user()->password = $data['password'];
        $request->user()->save();

        return redirect()->route('profile')->with('ok', 'Password diperbarui.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme_mode' => ['required', 'string', Rule::in(['system', 'light', 'dark'])],
            'language' => ['required', 'string', Rule::in(['id', 'en'])],
        ]);

        $current = (array) ($request->user()->preferences ?? []);
        $request->user()->preferences = array_replace($current, $data);
        $request->user()->save();

        return redirect()->route('profile')->with('ok', 'Preferensi disimpan.');
    }
}
