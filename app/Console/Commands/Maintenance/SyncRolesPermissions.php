<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SyncRolesPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:syncRolesPermissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates DB roles and permissions from the roles config file';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function organizePermissions($permissions, $allRoles, $inRecursion = false)
    {
        $updatedPermissions = [];
        foreach ($permissions as $permission => $models) {
            foreach ($models as $model) {
                if ($permission === 'copy_roles_from') {
                    foreach ($permissions['copy_roles_from'] as $roleToCopy) {
                        $updatedPermissions = array_merge($updatedPermissions, $this->organizePermissions($allRoles[$roleToCopy], $allRoles, true));
                    }
                } else {
                    $updatedPermissions[$permission . ' ' . $model] = $permission . ' ' . $model;
                }
            }
        }
        foreach ($updatedPermissions as $permission) {
            if (
                !Permission::where('name', $permission)
                    ->where('guard_name', 'api')
                    ->exists()
            ) {
                Permission::create(['name' => $permission, 'guard_name' => 'api']);
            }
        }

        return $updatedPermissions;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $updatedPermissions = [];
        $allRoles = config('roles');
        foreach ($allRoles as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                $role = Role::create(['name' => $roleName]);
            }
            $updatedPermissions[$roleName] = array_keys($this->organizePermissions($permissions, $allRoles));
            $role->syncPermissions($updatedPermissions);
        }
        echo PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . json_encode($updatedPermissions, true) . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL;
    }
}
