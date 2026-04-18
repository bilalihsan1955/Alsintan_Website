<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(private readonly JwtService $jwtService) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
        ]);
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['editUser' => $user]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?: null,
            'password' => $data['password'],
            'role' => $data['role'],
            'preferences' => ['theme_mode' => 'system', 'language' => 'id'],
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Pengguna baru ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        $oldRole = $user->role;

        if ($this->isLastAdmin($user) && $data['role'] !== 'admin') {
            return redirect()->back()->withErrors(['role' => 'Tidak bisa mengubah role admin terakhir menjadi operator.']);
        }

        $user->name = $data['name'];
        $user->email = strtolower($data['email']);
        $user->phone = $data['phone'] ?: null;
        $user->role = $data['role'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $this->jwtService->revokeAllForUser($user->getKey());
        }

        $user->save();

        if ($oldRole !== $user->role) {
            $this->jwtService->revokeAllForUser($user->getKey());
        }

        return redirect()->route('admin.users.index')->with('status', 'Data pengguna diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        if ($actor && (int) $actor->id === (int) $user->id) {
            return redirect()->route('admin.users.index')->withErrors(['delete' => 'Anda tidak dapat menghapus akun sendiri.']);
        }

        if ($this->isLastAdmin($user)) {
            return redirect()->route('admin.users.index')->withErrors(['delete' => 'Tidak dapat menghapus admin terakhir di sistem.']);
        }

        $this->jwtService->revokeAllForUser($user->getKey());
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Pengguna dihapus.');
    }

    private function isLastAdmin(User $user): bool
    {
        if ($user->role !== 'admin') {
            return false;
        }

        return User::query()->where('role', 'admin')->count() === 1;
    }
}
