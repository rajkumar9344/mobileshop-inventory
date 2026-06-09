<?php

namespace Modules\User\Http\Controllers;

use Modules\User\DataTables\RolesDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Exceptions\RoleAlreadyExists;

class RolesController extends Controller
{
    public function index(RolesDataTable $dataTable) {
        abort_if(Gate::denies('access_user_management'), 403);

        return $dataTable->render('user::roles.index');
    }


    public function create() {
        abort_if(Gate::denies('access_user_management'), 403);

        return view('user::roles.create');
    }


    public function store(Request $request) {
        abort_if(Gate::denies('access_user_management'), 403);

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'required|array'
        ]);

        try {
            $role = Role::create([
                'name' => $request->name
            ]);
        } catch (RoleAlreadyExists $e) {
            toast('A role with this name already exists.', 'error');
            return back()->withInput();
        }

        // Ensure dependent permissions (e.g. view_* implies show_*) are included
        $requested = $request->permissions;
        $dependent = [
            'view_sales' => 'show_sales',
            'view_sales_receipts' => 'show_sales_receipts',
            'view_purchases' => 'show_purchases',
            'view_purchases_receipts' => 'show_purchases_receipts',
            // Edit implies Update for customers so role editors don't get 403 on submit
            'edit_customers' => 'update_customers',
        ];

        foreach ($dependent as $view => $show) {
            if (in_array($view, $requested) && !in_array($show, $requested)) {
                $requested[] = $show;
            }
        }

        // Only assign permissions that actually exist to avoid PermissionDoesNotExist
        $validPermissions = Permission::whereIn('name', $requested)->pluck('name')->toArray();
        if (!empty($validPermissions)) {
            $role->givePermissionTo($validPermissions);
        }

        toast('Role Created With Selected Permissions!', 'success');

        return redirect()->route('roles.index');
    }


    public function edit(Role $role) {
        abort_if(Gate::denies('access_user_management'), 403);

        return view('user::roles.edit', compact('role'));
    }


    public function update(Request $request, Role $role) {
        abort_if(Gate::denies('access_user_management'), 403);

        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'required|array'
        ]);

        $role->update([
            'name' => $request->name
        ]);

        // Ensure dependent permissions (e.g. view_* implies show_*) are included
        $requested = $request->permissions;
        $dependent = [
            'view_sales' => 'show_sales',
            'view_sales_receipts' => 'show_sales_receipts',
            'view_purchases' => 'show_purchases',
            'view_purchases_receipts' => 'show_purchases_receipts',
            // Keep edit->update parity for customers
            'edit_customers' => 'update_customers',
        ];

        foreach ($dependent as $view => $show) {
            if (in_array($view, $requested) && !in_array($show, $requested)) {
                $requested[] = $show;
            }
        }

        // Sync only existing permissions to avoid errors
        $validPermissions = Permission::whereIn('name', $requested)->pluck('name')->toArray();
        $role->syncPermissions($validPermissions);

        toast('Role Updated With Selected Permissions!', 'success');

        return redirect()->route('roles.index');
    }


    public function destroy(Role $role) {
        abort_if(Gate::denies('access_user_management'), 403);

        $role->delete();

        toast('Role Deleted!', 'success');

        return redirect()->route('roles.index');
    }
}
