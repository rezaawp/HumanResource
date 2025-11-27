<?php

namespace App\Extensions\HumanResource\System\Http\Controllers;

use App\Extensions\RBAC\System\Enum\Permissions;
use App\Extensions\RBAC\System\Models\Role;
use App\Extensions\RBAC\System\Models\User;
use App\Extensions\RBAC\System\Models\UserModuleAssignmentDetail;
use App\Extensions\RBAC\System\Repositories\RoleRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $user = $this->UserModelQuery()->with('roles')->where(function ($query) use ($id) {
            $query->where('id', $id);
        })->firstOrFail();
        $roles = $this->RoleModelQuery()->get();

        $roleAssign = $user->roles->pluck('name')->toArray();

        $leaders = UserModuleAssignmentDetail::query()
            ->where('user_id', $id)->where('assigned_type', User::class)
            ->with('assigned')
            ->get()->groupBy('purpose');
        
        $firstGroup = $leaders->first(); // Ini akan return koleksi dari group pertama

        $userAssigned = $firstGroup ? true : false;

        $selectedUsers = $firstGroup
            ? $firstGroup->map(function($item) {
                return [
                    'id' => $item->assigned->id,
                    'name' => $item->assigned->name,
                ];
            })->toArray()
            : [];
        
        $selectedAssgnAS_a = $firstGroup[0]['purpose'];
        
        return view('human-resource::user-management.edit', compact('user', 'roles', 'roleAssign', 'selectedUsers', 'userAssigned', 'selectedAssgnAS_a'));
    }

    function usersSave(Request $request) {
        /** @var RoleRepository */
        $roleRepository = app(RoleRepository::class);

        $request->validate([
            'user_id'                  => 'required|exists:users,id',
            'name'                     => 'required|string|max:255',
            'surname'                  => 'required|string|max:255',
            'phone'                    => 'nullable|string|max:15',
            'email'                    => 'required|email|max:255|unique:users,email,' . $request->user_id,
            'country'                  => 'nullable',
            'type'                     => 'required|array',
            'status'                   => 'nullable|in:0,1',
            'users'                    => 'nullable|array'
        ]);

        $user = $this->UserModelQuery()->where(function ($query) use ($request) {
            $query->where('id', $request->user_id);
        })->firstOrFail();

        $roles = $this->RoleModelQuery()->where(function ($query) use ($request) {
            $query->whereIn('name', $request->type);
        })->get()->pluck('name')->toArray();
        
        if ($roleRepository->userHasPermission(Permissions::HR_USR_MNGMNT_UPDATE_USER)) {
            $user->update([
                'name'    => $request->name,
                'surname' => $request->surname,
                'phone'   => $request->phone,
                'email'   => $request->email,
                'country' => $request->country,
                'status'  => $request->status,
            ]);
        }

        if ($roleRepository->userHasPermission(Permissions::HR_USR_MNGMNT_SYNC_ROLE)) {
            $user->syncRoles($roles);
        }
    
        if ($roleRepository->userHasPermission(Permissions::HR_USR_MNGMNT_ASSIGN_LEAD)) {
            $assignType = User::class;
            $userId = $request->user_id;
            $dataInsert = [];

            $queryDataBefore = UserModuleAssignmentDetail::query()
                ->where('user_id', $userId)
                ->where('assigned_type', $assignType);
                // ->where('purpose', $request->as_a)
                // ->whereIn('assigned_id', collect($request->users)->map(fn ($i) => (int) $i));
            
            if ($queryDataBefore->count() > 0) $queryDataBefore->delete();

            foreach ($request->users as $key => $_userId) {
                $_userId = (int) $_userId;
                array_push($dataInsert, [
                    'assigned_type' => $assignType,
                    'user_id' => $userId,
                    'assigned_id' => $_userId,
                    'purpose' => $request->as_a  
                ]);
            }

            DB::table((new UserModuleAssignmentDetail())->getTable())->insert($dataInsert);
        }

        return redirect()->route('dashboard.admin.hr.users.edit', ['user' => $user->id])
            ->with(['message' => __('Role created successfully.'), 'type' => 'success']);
    }

}