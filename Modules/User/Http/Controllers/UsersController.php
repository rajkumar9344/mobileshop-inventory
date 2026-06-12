<?php

namespace Modules\User\Http\Controllers;

use Modules\User\DataTables\UsersDataTable;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Upload\Entities\Upload;

class UsersController extends Controller
{
    public function index(UsersDataTable $dataTable) {
        abort_if(Gate::denies('access_user_management'), 403);

        return $dataTable->render('user::users.index');
    }


    public function create() {
        abort_if(Gate::denies('access_user_management'), 403);

        return view('user::users.create');
    }


    public function store(Request $request) {
        abort_if(Gate::denies('access_user_management'), 403);

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email:rfc,strict|max:255|unique:users,email',
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&\#])[A-Za-z\d@$!%*?&\#]+$/'],
        ], [
            'password.regex' => 'Password must contain at least one lowercase letter, one uppercase letter, one number and one special character (@$!%*?&#).',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => $request->is_active
        ]);

        $user->assignRole($request->role);

        AuditLogger::log('user.created', ['user_id' => $user->id, 'email' => $user->email, 'role' => $request->role]);

        if ($request->has('image')) {
            $tempFile = Upload::where('folder', $request->image)->first();

            if ($tempFile) {
                $user->addMedia(Storage::path('public/temp/' . $request->image . '/' . $tempFile->filename))->toMediaCollection('avatars');

                Storage::deleteDirectory('public/temp/' . $request->image);
                $tempFile->delete();
            }
        }

        toast("User Created & Assigned '$request->role' Role!", 'success');

        return redirect()->route('users.index');
    }


    public function edit(User $user) {
        abort_if(Gate::denies('access_user_management'), 403);

        return view('user::users.edit', compact('user'));
    }


    public function update(Request $request, User $user) {
        abort_if(Gate::denies('access_user_management'), 403);

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email:rfc,strict|max:255|unique:users,email,'.$user->id,
        ]);

        $user->update([
            'name'     => $request->name,
            'email'    => $request->email,
            'is_active' => $request->is_active
        ]);

        $user->syncRoles($request->role);

        AuditLogger::log('user.updated', ['user_id' => $user->id, 'email' => $user->email, 'role' => $request->role]);

        if ($request->has('image')) {
            $tempFile = Upload::where('folder', $request->image)->first();

            if ($user->getFirstMedia('avatars')) {
                $user->getFirstMedia('avatars')->delete();
            }

            if ($tempFile) {
                $user->addMedia(Storage::path('public/temp/' . $request->image . '/' . $tempFile->filename))->toMediaCollection('avatars');

                Storage::deleteDirectory('public/temp/' . $request->image);
                $tempFile->delete();
            }
        }

        toast("User Updated & Assigned '$request->role' Role!", 'info');

        return redirect()->route('users.index');
    }


    public function destroy(User $user) {
        abort_if(Gate::denies('access_user_management'), 403);

        AuditLogger::log('user.deleted', ['user_id' => $user->id, 'email' => $user->email]);

        $user->delete();

        toast('User Deleted!', 'warning');

        return redirect()->route('users.index');
    }
}
