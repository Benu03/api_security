<?php


namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;


class PresensiController extends Controller
{

    public function PostPresensi(Request $request)
{
    Log::info('Begin PostPresensi');

    // 1. Validasi input file
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

    // 2. Ambil waktu presensi & type presensi
    $time = Carbon::parse($request->input('time_presensi'), 'Asia/Jakarta');    
    $year = $time->format('Y');
    $month = $time->format('m');
    $day = $time->format('d');
    $type = strtoupper($request->input('type_presensi', 'UNKNOWN'));
    $timestamp = $time->format('Ymd_His');

    // 3. Siapkan folder
       $folderPath = storage_path("app/data/presensi/{$year}/{$month}/{$day}");

    if (!File::exists($folderPath)) {
        File::makeDirectory($folderPath, 0755, true);
        Log::info("Folder created: {$folderPath}");
    }

    $filename = strtolower($type) . '_' . $request->input('nik') . '_' . $timestamp . '.' . $file->getClientOriginalExtension();

    // Pindahkan file
    $file->move($folderPath, $filename);

    // Simpan path relatif
    $relativePath = "data/presensi/{$year}/{$month}/{$day}/{$filename}";
    Log::info('Foto disimpan di:', ['path' => $relativePath]);
    Log::info('Foto disimpan di:', ['path' => $relativePath]);

    // 5. Log semua data input
    Log::info('Data Presensi:', [
        'username'       => $request->input('username'),
        'nik'            => $request->input('nik'),
        'time_presensi'  => $request->input('time_presensi'),
        'latitude'       => $request->input('latitude'),
        'longitude'      => $request->input('longitude'),
        'type_presensi'  => $type,
    ]);

    Log::info('End PostPresensi');

    return response()->json([
        'status'  => 200,
        'success' => true,
        'message' => 'Presensi berhasil',
        'data'    => [
        ],
    ], 200);
}
  


}