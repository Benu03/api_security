<?php


namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class CheckpointController extends Controller
{

    public function ScheduleCheckpointPatroli($username)
    {
        Log::info('Begin ScheduleCheckpointPatroli');

        $customer = DB::connection('qms')
            ->table('mst.mst_users_access')
            ->where('username', $username)
            ->first();

        if (!$customer) {
            Log::warning("ScheduleCheckpointPatroli: Username {$username} tidak ditemukan.");
            return;
        }

        $checkpoints = DB::connection('qms')
            ->table('scr.scr_mst_customer_location')
            ->where('customer_name', $customer->customer)
            ->where('category', 'CHECK POINT')
            ->orderBy('seq')
            ->get();

        if ($checkpoints->isEmpty()) {
            Log::warning("ScheduleCheckpointPatroli: Tidak ada checkpoint untuk customer {$customer->customer}");
            return;
        }

        $insertData = [];

        foreach ($checkpoints as $checkpoint) {
            $insertData[] = [
                'username'        => $username,
                'checkpoint_code' => $checkpoint->checkpoint_code,
                'seq'             => $checkpoint->seq,
                'created_by'      => $username
            ];
        }

        DB::connection('qms')
            ->table('scr.scr_task_patroli')
            ->insert($insertData);

        Log::info('End ScheduleCheckpointPatroli');
    }
        

    public function PostCheckpointPatroli(Request $request)
    {
        Log::info('Begin PostCheckpointPatroli', ['request' => $request->all()]);

        $this->validate($request, [
            'username'        => 'required|string',
            'checkpoint_code' => 'required|string',
            'time_checkpoint' => 'required|date_format:Y-m-d H:i:s',
            'latitude'        => 'required|numeric',
            'longitude'       => 'required|numeric',
        ]);

        $username       = $request->input('username');
        $checkpointCode = $request->input('checkpoint_code');
        $timeCheckpoint = $request->input('time_checkpoint');
        $latitude       = $request->input('latitude');
        $longitude      = $request->input('longitude');

        Log::info('Mencari checkpoint', ['checkpoint_code' => $checkpointCode]);

        $checkpoint = DB::connection('qms')
            ->table('scr.scr_mst_customer_location')
            ->where('checkpoint_code', $checkpointCode)
            ->first();

        if (!$checkpoint) {
            Log::warning('Checkpoint tidak ditemukan', ['checkpoint_code' => $checkpointCode]);
            return response()->json([
                'status'  => 400,
                'success' => false,
                'message' => 'Checkpoint code tidak valid untuk customer ini.',
            ], 400);
        }

        $currentSeq = $checkpoint->seq;
        Log::info('Checkpoint ditemukan', ['seq' => $currentSeq]);

        $sixHoursAgo = Carbon::now()->subHours(6);

        $lastPassed = DB::connection('qms')
            ->table('scr.scr_task_patroli')
            ->where('username', $username)
            ->whereNotNull('time_checkpoint')
            ->where('time_checkpoint', '>=', $sixHoursAgo)
            ->orderByDesc('seq')
            ->value('seq');

        Log::info('Validasi urutan checkpoint', [
            'currentSeq' => $currentSeq,
            'lastPassed' => $lastPassed,
            'condition'  => $currentSeq > $lastPassed + 1 ? 'loncat' : 'valid'
        ]);
       
    
        if ($lastPassed === null) {
            $lastPassed = 0;
        }

        if ($currentSeq > $lastPassed + 1) {
            return response()->json([
                'status'  => 400,
                'success' => false,
                'message' => 'Checkpoint tidak boleh melewati urutan. Harus mengikuti urutan checkpoint.',
            ], 400);
        }


        function haversineDistance($lat1, $lon1, $lat2, $lon2) {
            $earthRadius = 6371000; // radius bumi dalam meter

            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);

            $a = sin($dLat/2) * sin($dLat/2) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                sin($dLon/2) * sin($dLon/2);

            $c = 2 * atan2(sqrt($a), sqrt(1-$a));

            return $earthRadius * $c;
        }

        $distance = haversineDistance(
            floatval($latitude),
            floatval($longitude),
            floatval($checkpoint->latitude),
            floatval($checkpoint->longitude)
        );

        Log::info('Jarak user ke checkpoint', ['distance_meter' => $distance, 'allowed_radius' => $checkpoint->radius]);

        if ($distance > floatval($checkpoint->radius)) {
            Log::warning('Lokasi presensi melebihi radius checkpoint', [
                'distance' => $distance,
                'radius' => $checkpoint->radius,
            ]);
            return response()->json([
                'status'  => 400,
                'success' => false,
                'message' => 'Lokasi presensi melebihi radius checkpoint (' . $checkpoint->radius . ' meter).',
                'data'    => ['distance' => $distance],
            ], 400);
        }

        $updated = DB::connection('qms')
            ->table('scr.scr_task_patroli')
            ->where('username', $username)
            ->where('checkpoint_code', $checkpointCode)
            ->where('seq', $currentSeq)
            ->whereNull('time_checkpoint')
            ->update([
                'time_checkpoint' => $timeCheckpoint,
                'latitude'        => $latitude,
                'longitude'       => $longitude,
                'updated_by'      => $username,
                'updated_date'    => Carbon::now(),
            ]);

        Log::info('Update scr_task_patroli result', ['rows_updated' => $updated]);

        Log::info('End PostCheckpointPatroli');

        return response()->json([
            'status'  => 200,
            'success' => true,
            'message' => 'checkpoint patroli berhasil.',
            'data'    => [],
        ], 200);
    }


    public function ListCheckpointPatroli(Request $request)
    {
        Log::info('Begin ListCheckpointPatroli');
        $username = $request->username;
        $start_date = $request->start_date; 
        $end_date = $request->end_date;   


         if (!$username || !$start_date || !$end_date) {
            return response()->json([
                'status'  => 400,
                'success' => false,
                'message' => 'Username, start_date, dan end_date wajib diisi.'
            ], 400);
        }

        try {
            $data = DB::connection('qms')
                ->table('scr.scr_task_patroli')
                ->where('username', $username)
                ->whereBetween('created_date', [$start_date, $end_date])
                ->select(
                    'id',
                    'checkpoint_code',
                    'time_checkpoint',
                    'seq',
                    'latitude',
                    'longitude',
                    'created_date'
                )
                ->orderBy('created_date', 'desc')
                ->get();

            Log::info('End ListCheckpointPatroli');

            return response()->json([
                'status'  => 200,
                'success' => true,
                'message' => 'Data Checkpoint patroli berhasil diambil.',
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error ListCheckpointPatroli: " . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    
}