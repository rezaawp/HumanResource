<?php

namespace App\Extensions\HumanResource\System\Http\Controllers;

use App\Actions\EmailConfirmation;
use App\Extensions\RBAC\System\Enum\Permissions;
use App\Extensions\RBAC\System\Models\Role;
use App\Extensions\RBAC\System\Models\User;
use App\Extensions\RBAC\System\Models\UserModuleAssignmentDetail;
use App\Extensions\RBAC\System\Repositories\RoleRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\Classes\Helper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Str;
use App\Enums\Roles;
use App\Models\Plan;

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

    function create() {
        $plans = Plan::all();
        return view('human-resource::user-management.create', compact('plans'));
    }

    function index(Request $request) {
        $routeId = 210;
        $search = $request->input('search');
        $users = $this->UserModelQuery()->where(function ($query) use ($search) {
            $query->where('name', 'like', "%$search%")
                ->orWhere('surname', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%");
        })->with('roles')->paginate(20);

        $users->getCollection()->transform(function ($role) {
            // create/override attribute 'permissions' as CSV of permission names
            $role->roles = $role->roles->pluck('label')->implode(', ');
            return $role;
        });

        return view('human-resource::user-management.index', compact('users'));
    }
    
    function edit($id) {
        $user = $this->UserModelQuery()->with('roles')->where(function ($query) use ($id) {
            $query->where('id', $id);
        })->firstOrFail();
        $roles = $this->RoleModelQuery()->get();

        $roleAssign = $user->roles->pluck('name')->toArray();

        $plans = Plan::all();

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
        
        $selectedAssgnAS_a = isset($firstGroup[0]['purpose']) ? $firstGroup[0]['purpose'] : '';
        
        return view('human-resource::user-management.edit', compact('user', 'roles', 'roleAssign', 'selectedUsers', 'userAssigned', 'selectedAssgnAS_a', 'plans'));
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
            'users'                    => 'nullable|array',
            'plan_id'                  => 'required|exists:plans,id'
        ]);

        $plan = Plan::query()->findOrFail((int) $request->input('plan_id'));

        $entities = "";

        if ($plan) {
            $entities = $plan->ai_models;
        }

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
                'plan_id' => $request->plan_id,
                'entity_credits' => $entities
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

            if ($request->users) {
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
        }

        return redirect()->route('dashboard.admin.hr.users.edit', ['user' => $user->id])
            ->with(['message' => __('Role created successfully.'), 'type' => 'success']);
    }

    public function usersStore(Request $request): RedirectResponse
    {
        if (Helper::appIsDemo()) {
            return back()->with(['message' => __('This feature is disabled in Demo version.'), 'type' => 'error']);
        }

        $validator = Validator::make($request->all(), [
            'name'                     => 'required|string|max:255',
            'surname'                  => 'required|string|max:255',
            'email'                    => 'required|email|max:255|unique:users',
            'password'                 => 'required|min:8',
            'repassword'               => 'required|same:password',
            'phone'                    => 'nullable|string|max:15',
            'avatar'                   => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'type'                     => ['required', new Enum(Roles::class)],
            'country'                  => 'nullable',
            'status'                   => 'nullable|in:0,1',
            'plan_id'                  => ['required', 'exists:plans,id']
        ], [
            'repassword.same' => __('The password and re-password must match.'),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        // $entities = $request->input('entities');

        $masterPlan = Plan::query()->find($request->input('plan_id'))->firstOrFail();

        if ($masterPlan)
        {
            $entities = $masterPlan->ai_models;
        }

        $user = User::query()->create([
            'name'                    => $request->name,
            'surname'                 => $request->surname,
            'email'                   => $request->email,
            'phone'                   => $request->phone,
            'country'                 => $request->country,
            'type'                    => $request->type,
            'status'                  => $request->status,
            'email_confirmation_code' => Str::random(67),
            'password'                => Hash::make($request->password),
            'email_verification_code' => Str::random(67),
            'affiliate_code'          => Str::upper(Str::random(12)),
        ]);

        $user->updateCredits($entities);

        if ($request->hasFile('avatar')) {
            $path = 'upload/images/avatar/';
            $image = $request->file('avatar');
            if ($image->guessExtension() === 'svg') {
                $image = self::sanitizeSVG($request->file('avatar'));
            }
            $image_name = Str::random(4) . '-' . Str::slug($user?->fullName()) . '-avatar.' . $image->guessExtension();
            // Image extension check
            $imageTypes = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
            if (! in_array(Str::lower($image->guessExtension()), $imageTypes)) {
                return back()->with(['message' => __('The file extension must be jpg, jpeg, png, webp or svg.'), 'type' => 'error']);
            }
            $image->move($path, $image_name);
            $user->avatar = $path . $image_name;
            $user->save();
        }

        EmailConfirmation::forUser($user)->send();

        return back()->with(['message' => __('Created Successfully'), 'type' => 'success']);
    }
}