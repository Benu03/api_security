<?php


namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image; 

class PresensiController extends Controller
{

public function PostPresensi(Request $request)
{
    Log::info('Begin PostPresensi');

    if (!$request->hasFile('foto')) {
        return response()->json([
            'status'  => 400,
            'success' => false,
            'message' => 'File tidak ditemukan.',
        ], 400);
    }

    $file = $request->file('foto');

    if (!$file->isValid()) {
        return response()->json([
            'status'  => 400,
            'success' => false,
            'message' => 'File tidak valid.',
        ], 400);
    }

    if ($file->getSize() > 1048576) { // 1 MB
        return response()->json([
            'status'  => 400,
            'success' => false,
            'message' => 'Ukuran file maksimal 1 MB.',
        ], 400);
    }

    $allowedExtensions = ['png', 'jpg', 'jpeg'];
    $extension = strtolower($file->getClientOriginalExtension());

    if (!in_array($extension, $allowedExtensions)) {
        return response()->json([
            'status'  => 400,
            'success' => false,
            'message' => 'Hanya file dengan format PNG, JPG, atau JPEG yang diizinkan.',
        ], 400);
    }

    $username = $request->input('username');
    $userAccess = DB::connection('qms')
        ->table('mst.mst_users_access')
        ->where('username', $username)
        ->first();

    $CustomerLocation = DB::connection('qms')
        ->table('scr.scr_mst_customer_location')
        ->where('customer_name', $userAccess->customer)
        ->where('category', 'PRESENSI')
        ->first();

    if (!$CustomerLocation) {
        return response()->json([
            'message' => 'User Anda belum di-assign ke customer Atau Lokasi presensi customer belum terdaftar'
        ], 400);
    }

    $lat = $request->input('latitude');
    $lot = $request->input('longitude');

    $distance = $this->calculateDistance(
        $lat, $lot,
        $CustomerLocation->latitude,
        $CustomerLocation->longitude
    );

    if ($distance > $CustomerLocation->radius) {
        return response()->json([
            'message' => 'Anda berada di luar radius lokasi presensi',
              'distance' => $distance,
        ], 400);
    }

    $nik = $request->input('nik');
    $time = Carbon::parse($request->input('time_presensi'), 'Asia/Jakarta');    
    $year = $time->format('Y');
    $month = $time->format('m');
    $day = $time->format('j');
    $timestamp = $time->timestamp;

    $type_presensi = $request->input('type_presensi');
    $shift_code = $request->input('shift_code');

    $folderPath = storage_path("app/data/presensi/{$year}/{$month}/{$day}");

    if (!File::exists($folderPath)) {
        File::makeDirectory($folderPath, 0755, true);
        Log::info("Folder created: {$folderPath}");
    }

  
    $filename = strtolower($type_presensi) . '_' . $nik . '_' . $timestamp . '.' . $file->getClientOriginalExtension();
    $file->move($folderPath, $filename);
    $relativePath = "data/presensi/{$year}/{$month}/{$day}/{$filename}";
    $time_presensi = $request->input('time_presensi'); 


    Log::info('Begin PostPresensi');


        $lastAbsen = DB::connection('qms')
            ->table('scr.scr_presensi_trx')
            ->where('nik', $nik)
            ->whereDate('time_presensi', Carbon::parse($time_presensi)->toDateString())
            ->orderByDesc('time_presensi')
            ->first();


        if ($type_presensi === 'IN' && $lastAbsen && $lastAbsen->type_presensi !== 'OUT') {
            return response()->json([
                'status'  => 400,
                'success' => false,
                'message' => 'Anda belum melakukan presensi Keluar sebelumnya Silakan Hubungi Admin',
            ], 400);
        }

        if ($type_presensi === 'OUT' && (!$lastAbsen || $lastAbsen->type_presensi !== 'IN')) {
            return response()->json([
                'status'  => 400,
                'success' => false,
                'message' => 'Anda belum melakukan presensi Masuk sebelumnya Silakan Hubungi Admin',
            ], 400);
        }


        $dataInsert = [
            'username'      => $username,
            'nik'           => $nik,
            'year_period'   => $year,
            'month_period'  => $month,
            'type_presensi' => $type_presensi,
            'latitude'      => $lat,
            'longitude'     => $lot,
            'foto'          => $relativePath,
            'time_presensi' => $time_presensi,
            'shift_code'    => $shift_code,
            'created_by'    => $username,
        ];

        DB::connection('qms')
            ->table('scr.scr_presensi_trx')
            ->insert($dataInsert);

        Log::info('End PostPresensi');

        // Respon sukses
        return response()->json([
            'status'  => 200,
            'success' => true,
            'message' => 'Presensi berhasil',
            'data'    => [],
        ], 200);

}
  
private function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371e3;

    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);
    $deltaLat = $lat2 - $lat1;
    $deltaLon = deg2rad($lon2 - $lon1);

    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1) * cos($lat2) *
         sin($deltaLon / 2) * sin($deltaLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    $distance = $earthRadius * $c;

    return $distance; 
}

public function GetShiftCode(Request $request)
{
    Log::info('Begin GetShiftCode');

    $nik = $request->nik;
    $time_presensi = $request->time_presensi;

    $time = Carbon::parse($time_presensi, 'Asia/Jakarta');    
    $year = $time->format('Y');
    $month = $time->format('m');
    $day = $time->format('j'); 


    $jadwal = DB::connection('qms')
        ->table('scr.scr_presensi_jadwal')
        ->where('nik', $nik)
        ->where('year_month_period', $year . '_' . $month)
        ->first();

    if (!$jadwal) {
        Log::warning("ShiftCode tidak ditemukan untuk NIK: $nik, Periode: $year . '_' . $month");
        return response()->json([
            'status'  => 404,
            'success' => false,
            'message' => 'Shift tidak ditemukan untuk user dan periode yang diberikan.',
        ], 404);
    }

    $dayKey = (string) intval($day);
    $shiftCode = $jadwal->$dayKey ?? null;

    Log::info('End GetShiftCode');

    return response()->json([
        'status'  => 200,
        'success' => true,
        'message' => 'Berhasil mengambil kode shift',
        'data'    => $shiftCode,
    ], 200);
}

public function GetFotoPresensi($data)
{
    Log::info('Begin GetFotoPresensi');

    $absen = DB::connection('qms')
        ->table('scr.scr_presensi_trx')
        ->select('foto')
        ->where('id', $data)
        ->first();

    if (!$absen || !$absen->foto) {
        return response()->json(['error' => 'Foto tidak ditemukan'], 404);
    }

    $relativePath = $absen->foto; 

    $filePath = storage_path('app/' . $relativePath);

    if (!file_exists($filePath)) {
        return response()->json(['error' => 'File tidak ditemukan'], 404);
    }

    Log::info('End GetFotoPresensi');

    return response()->file($filePath, [
        'Content-Type' => 'image/jpeg',
    ]);
}

public function GetListPresensi(Request $request)
{
        Log::info('Begin GetListPresensi');
          $username = $request->username;
          $nik = $request->nik;
          $start_date = $request->start_date;
          $end_date = $request->end_date;

$absen = DB::connection('qms')
        ->table('scr.scr_presensi_jadwal')
        ->where('username', $username)
          ->where('username', $username)
        ->get();




        Log::info('Begin GetListPresensi');
    
        return response()->json([
            'status'  => 200,
            'success' => true,
            'message' => 'Presensi berhasil',
            'data'    => [],
        ], 200);


}




}