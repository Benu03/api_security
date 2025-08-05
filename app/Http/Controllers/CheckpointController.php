<?php


namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;


class CheckpointController extends Controller
{

    public function PostCheckpointPatroli(Request $request)
    {
        Log::info('Begin PostCheckpointPatroli');

        


        Log::info('End PostCheckpointPatroli');
        return response()->json([
            'status'  => 200,
            'success' => true,
            'message' => 'Presensi berhasil',
            'data'    => [
            ],
        ], 200);
    }
    


}