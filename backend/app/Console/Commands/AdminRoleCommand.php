<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdminRoleCommand extends Command
{
    protected $signature = 'identity:admin {action : grant or revoke} {email} {reason}';

    protected $description = 'Grant or revoke an admin role through a trusted CLI operation';

    public function handle(): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['grant', 'revoke'], true)) {
            $this->error('Action must be grant or revoke.');

            return self::FAILURE;
        }

        $user = DB::table('users')->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        if ($action === 'grant' && $user->status !== 'ACTIVE') {
            $this->error('Only an active user can receive the admin role.');

            return self::FAILURE;
        }

        $userHasAdmin = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('role', 'ADMIN')
            ->exists();

        if ($action === 'revoke' && $user->status === 'ACTIVE' && $userHasAdmin) {
            $activeAdmins = DB::table('user_roles')
                ->join('users', 'users.id', '=', 'user_roles.user_id')
                ->where('user_roles.role', 'ADMIN')
                ->where('users.status', 'ACTIVE')
                ->count();

            if ($activeAdmins <= 1) {
                $this->error('Cannot revoke the last active admin.');

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($action, $user): void {
            $role = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where('role', 'ADMIN');

            $changed = $action === 'grant'
                ? ! $role->exists()
                : $role->exists();

            if (! $changed) {
                return;
            }

            if ($action === 'grant') {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role' => 'ADMIN',
                    'assigned_at' => now(),
                ]);
            } else {
                $role->delete();
            }

            DB::table('audit_logs')->insert([
                'action' => $action === 'grant' ? 'ROLE_ASSIGNED' : 'ROLE_REVOKED',
                'resource_type' => 'user_role',
                'resource_id' => $user->id,
                'reason' => $this->argument('reason'),
                'metadata_json' => json_encode(['role' => 'ADMIN']),
                'created_at' => now(),
            ]);
        });

        $this->info($action === 'grant' ? 'Admin role granted.' : 'Admin role revoked.');

        return self::SUCCESS;
    }
}
