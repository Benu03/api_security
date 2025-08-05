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


}