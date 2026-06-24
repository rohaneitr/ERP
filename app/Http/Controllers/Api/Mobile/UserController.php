<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function index(Request $request)
    {
        if (! auth()->user()->can('user.view') && ! auth()->user()->can('user.create')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $business_id = auth()->user()->business_id;

        $users = User::where('business_id', $business_id)
            ->user()
            ->where('is_cmmsn_agnt', 0)
            ->select('id', 'username', 'first_name', 'last_name', 'email', 'allow_login')
            ->with('roles')
            ->get();

        $users = $users->map(function ($u) use ($business_id) {
            $role = $u->roles->first();
            $roleName = $role ? str_replace('#' . $business_id, '', $role->name) : 'No Role';
            return [
                'id' => $u->id,
                'name' => trim($u->first_name . ' ' . $u->last_name),
                'username' => $u->username,
                'email' => $u->email,
                'allow_login' => $u->allow_login,
                'role' => $roleName,
                'role_id' => $role ? $role->id : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function roles(Request $request)
    {
        if (! auth()->user()->can('user.view') && ! auth()->user()->can('user.create')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $business_id = auth()->user()->business_id;
        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        $roles_query = Role::where('business_id', $business_id)->get();
        $roles = [];

        foreach ($roles_query as $role) {
            if (! $is_admin && $role->name == 'Admin#' . $business_id) {
                continue;
            }
            $roles[] = [
                'id' => $role->id,
                'name' => str_replace('#' . $business_id, '', $role->name),
            ];
        }

        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('user.create')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $business_id = auth()->user()->business_id;

        if (! $this->moduleUtil->isQuotaAvailable('users', $business_id)) {
            return response()->json(['success' => false, 'message' => 'User limit reached. Please upgrade subscription.'], 400);
        }

        try {
            $user_data = $request->only(['first_name', 'last_name', 'email', 'username']);
            $user_data['business_id'] = $business_id;
            $user_data['status'] = 'active';
            $user_data['allow_login'] = 1;
            $user_data['password'] = Hash::make($request->input('password'));

            $user = User::create($user_data);

            if ($request->has('role_id')) {
                $role = Role::findOrFail($request->input('role_id'));
                $user->assignRole($role->name);
            }

            return response()->json(['success' => true, 'message' => 'User created successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('user.update')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $business_id = auth()->user()->business_id;

        try {
            $user = User::where('business_id', $business_id)->findOrFail($id);

            $user_data = $request->only(['first_name', 'last_name', 'email']);
            
            if ($request->has('password') && !empty($request->input('password'))) {
                $user_data['password'] = Hash::make($request->input('password'));
            }

            $user->update($user_data);

            if ($request->has('role_id')) {
                $role_id = $request->input('role_id');
                $user_role = $user->roles->first();
                $previous_role = ! empty($user_role->id) ? $user_role->id : 0;
                
                if ($previous_role != $role_id) {
                    $is_admin = $this->moduleUtil->is_admin($user);
                    if ($is_admin) {
                        $admins_count = User::role('Admin#' . $business_id)->count();
                        if ($admins_count <= 1) {
                            return response()->json(['success' => false, 'message' => 'Cannot change role. You are the only admin.'], 400);
                        }
                    }
                    if (! empty($previous_role)) {
                        $user->removeRole($user_role->name);
                    }
                    $role = Role::findOrFail($role_id);
                    $user->assignRole($role->name);
                }
            }

            return response()->json(['success' => true, 'message' => 'User updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        if (! auth()->user()->can('user.delete')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $business_id = auth()->user()->business_id;

        try {
            $user = User::where('business_id', $business_id)->findOrFail($id);
            $user->delete();

            return response()->json(['success' => true, 'message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
