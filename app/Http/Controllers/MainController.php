<?php


namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MainController extends Controller
{

public function UserAccess(Request $request)
{
    Log::info('Begin UserAccess');

    $username = $request->username;

    if (!$username) {
        Log::warning('Username tidak ditemukan dalam request.');
        return response()->json([
            'status'  => 400,
            'success' => false,
            'message' => 'Username is required',
        ], 400);
    }

    $userAccess = DB::connection('qms')
        ->table('mst.mst_users_access')
        ->where('username', $username)
        ->first();


    if (!$userAccess) {
        Log::warning("User access not found for username: {$username}");

        return response()->json([
            'status'  => 400,
            'success' => false,
            'message' => 'Data not found',
        ], 400);
    }

    Log::info('End UserAccess');

    return response()->json([
        'status'  => 200,
        'success' => true,
        'message' => 'Berhasil',
        'data'    => $userAccess,
    ], 200);
}


public function SyncUseraccess(Request $request)
{
    Log::info('Begin SyncUseraccess');

    $insertedCount = 0;
    $updatedCount = 0;
    $deletedCount = 0;

    $now = Carbon::now();
    $deletedBy = 'system_sync';

    // Ambil data dari SSO
    $userSSO = DB::connection('sso')
        ->table('auth.v_auth_user_module')
        ->select('username', 'nik', 'fullname', 'role')
        ->where('module', 'SECURITY SERVICE')
        ->get()
        ->keyBy('username');

    // Ambil data dari QMS
    $userAccess = DB::connection('qms')
        ->table('mst.mst_users_access')
        ->get()
        ->keyBy('username');


    // 1. Update atau Insert
    foreach ($userSSO as $username => $ssoUser) {
        if (!isset($userAccess[$username])) {
            // Insert
            DB::connection('qms')->table('mst.mst_users_access')->insert([
                'username' => $ssoUser->username,
                'nik' => $ssoUser->nik,
                'fullname' => $ssoUser->fullname,
                'role' => $ssoUser->role,
                'created_date' => $now,
                'created_by' => $deletedBy
            ]);
            $insertedCount++;
        } else {
            // Cek jika ada perubahan
            $dbUser = $userAccess[$username];
            if (
                $dbUser->nik !== $ssoUser->nik ||
                $dbUser->fullname !== $ssoUser->fullname ||
                $dbUser->role !== $ssoUser->role || $dbUser->deleted_date !== null 
            ) {
                DB::connection('qms')->table('mst.mst_users_access')
                    ->where('username', $username)
                    ->update([
                        'nik' => $ssoUser->nik,
                        'fullname' => $ssoUser->fullname,
                        'role' => $ssoUser->role,
                        'deleted_date' => null,
                        'deleted_by' => null,
                        'updated_date' => $now,
                        'updated_by' => $deletedBy
                    ]);
                $updatedCount++;
            }
        }
    }

    // 2. Tandai yang tidak ada di SSO sebagai deleted
    foreach ($userAccess as $username => $dbUser) {
        if (!isset($userSSO[$username]) && is_null($dbUser->deleted_date)) {
            DB::connection('qms')->table('mst.mst_users_access')
                ->where('username', $username)
                ->update([
                    'deleted_date' => $now,
                    'deleted_by' => $deletedBy
                ]);
            $deletedCount++;
        }
    }

    Log::info('End SyncUseraccess', [
        'inserted' => $insertedCount,
        'updated' => $updatedCount,
        'deleted' => $deletedCount
    ]);

    return response()->json([
        'status'  => 200,
        'success' => true,
        'message' => 'Berhasil',
        'inserted' => $insertedCount,
        'updated' => $updatedCount,
        'deleted' => $deletedCount
    ]);
}

}