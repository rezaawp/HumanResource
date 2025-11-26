<?php

namespace App\Extensions\HumanResource\System\Http\Controllers;

use App\Extensions\RBAC\System\Models\Role;
use App\Extensions\RBAC\System\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserManagementController extends Controller {
    private $menuId = 210; // ubah sesuai menu id yang benar di database

    // validasi dati access
    protected function RoleModelQuery() : \Illuminate\Database\Eloquent\Builder {
        return Role::queryPermission(204);
    }

    // validasi dati access
    protected function UserModelQuery() : \Illuminate\Database\Eloquent\Builder {
        return User::queryPermission($this->menuId);
    }

    function index(Request $request) {
        $routeId = 210;
        $search = $request->input('search');
            $users = $this->UserModelQuery()->where(function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('surname', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            })->paginate(25);

            return view('human-resource::user-management.index', compact('users'));
    }
    
    function edit($id) {
        $user = $this->UserModelQuery()->where(function ($query) use ($id) {
            $query->where('id', $id);
        })->firstOrFail();
        $roles = $this->RoleModelQuery()->get();
        return view('human-resource::user-management.edit', compact('user', 'roles'));
    }

    function usersSave(Request $request) {
        $request->validate([
            'user_id'                  => 'required|exists:users,id',
            'name'                     => 'required|string|max:255',
            'surname'                  => 'required|string|max:255',
            'phone'                    => 'nullable|string|max:15',
            'email'                    => 'required|email|max:255|unique:users,email,' . $request->user_id,
            'country'                  => 'nullable',
            'type'                     => 'required|array',
            'status'                   => 'nullable|in:0,1',
        ]);

        $user = $this->UserModelQuery()->where(function ($query) use ($request) {
            $query->where('id', $request->user_id);
        })->firstOrFail();

        $roles = $this->RoleModelQuery()->where(function ($query) use ($request) {
            $query->whereIn('name', $request->type);
        })->get()->pluck('name')->toArray();
        
        $user->update([
            'name'    => $request->name,
            'surname' => $request->surname,
            'phone'   => $request->phone,
            'email'   => $request->email,
            'country' => $request->country,
            'status'  => $request->status,
        ]);

        $user->syncRoles($roles);


        return redirect()->route('dashboard.admin.hr.users.edit', ['user' => $user->id])
            ->with(['message' => __('Role created successfully.'), 'type' => 'success']);
    }

}