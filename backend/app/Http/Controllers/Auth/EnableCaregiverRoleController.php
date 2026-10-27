<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnableCaregiverRoleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->status === 'ACTIVE', 403);

        DB::transaction(function () use ($request): void {
            $userId = $request->user()->id;
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            abort_unless($user?->status === 'ACTIVE', 403);

            $assigned = DB::table('user_roles')
                ->where('user_id', $userId)
                ->where('role', 'CAREGIVER')
                ->exists();

            if ($assigned) {
                return;
            }

            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role' => 'CAREGIVER',
                'assigned_at' => now(),
                'assigned_by_user_id' => $userId,
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $userId,
                'action' => 'ROLE_ASSIGNED',
                'resource_type' => 'user_role',
                'resource_id' => $userId,
                'reason' => 'SELF_ENABLED_CAREGIVER',
                'metadata_json' => json_encode(['role' => 'CAREGIVER']),
                'created_at' => now(),
            ]);
        });

        return response()->noContent();
    }
}
