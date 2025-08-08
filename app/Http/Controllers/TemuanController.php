<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TemuanController extends Controller
{
    public function PostTemuanPatroli(Request $request)
    {
        Log::info('Begin PostTemuanPatroli');

        try {
            // ===== 1. Validasi Request =====
            $this->validate($request, [
                'foto_temuan'          => 'required|file|mimes:png,jpg,jpeg|max:1024',
                'username'             => 'required|string',
                'time_temuan_patroli'  => 'required|date',
                'remark'               => 'nullable|string',
                'latitude'             => 'nullable|numeric',
                'longitude'            => 'nullable|numeric',
            ], [
                'foto_temuan.max'   => 'Ukuran file maksimal 1 MB.',
                'foto_temuan.mimes' => 'Hanya file PNG, JPG, atau JPEG yang diizinkan.',
            ]);
            // ===== 2. Ambil data request =====
            $file       = $request->file('foto_temuan');
            $username   = $request->input('username');
            $remark     = $request->input('remark');
            $lat        = $request->input('latitude');
            $lot        = $request->input('longitude');
            $timeTemuan = Carbon::parse($request->input('time_temuan_patroli'), 'Asia/Jakarta');

            $year       = $timeTemuan->format('Y');
            $month      = $timeTemuan->format('m');
            $day        = $timeTemuan->format('j');
            $timestamp  = $timeTemuan->timestamp;

            // ===== 3. Cek username di DB =====
            $customer = DB::connection('qms')
                ->table('mst.mst_users_access')
                ->where('username', $username)
                ->first();

            if (!$customer) {
                return response()->json([
                    'status'  => 404,
                    'success' => false,
                    'message' => 'Username tidak ditemukan.',
                ], 404);
            }

            // ===== 4. Buat folder kalau belum ada =====
            $folderPath = storage_path("app/data/temuan/{$year}/{$month}/{$day}");
            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0755, true);
                Log::info("Folder created: {$folderPath}");
            }

            // ===== 5. Simpan file =====
            $filename = "{$username}_{$timestamp}." . $file->getClientOriginalExtension();
            $file->move($folderPath, $filename);

            // Path yang disimpan di DB (relatif terhadap storage/app)
            $relativePath = "data/temuan/{$year}/{$month}/{$day}/{$filename}";

            // ===== 6. Insert ke DB =====
            DB::connection('qms')
                ->table('scr.scr_temuan_patroli')
                ->insert([
                    'username'             => $username,
                    'time_temuan_patroli'  => $timeTemuan->toDateTimeString(),
                    'customer'             => $customer->customer,
                    'foto_temuan'          => $relativePath,
                    'remark'               => $remark,
                    'latitude'             => $lat,
                    'longitude'            => $lot,
                    'created_by'           => $username,
                ]);

            Log::info('End PostTemuanPatroli');

            return response()->json([
                'status'  => 200,
                'success' => true,
                'message' => 'Post Temuan berhasil',
                'data'    => [
                    // 'path' => $relativePath
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error PostTemuanPatroli: " . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan temuan.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function ListTemuanPatroli(Request $request)
    {

        Log::info('Begin ListTemuanPatroli');
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
                ->table('scr.scr_temuan_patroli')
                ->where('username', $username)
                ->whereBetween('time_temuan_patroli', [$start_date, $end_date])
                ->select(
                    'id',
                    'time_temuan_patroli',
                    'customer',
                    'latitude',
                    'longitude',
                    'remark'
                )
                ->orderBy('time_temuan_patroli', 'desc')
                ->get();

            Log::info('End ListTemuanPatroli');

            return response()->json([
                'status'  => 200,
                'success' => true,
                'message' => 'Data temuan patroli berhasil diambil.',
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error ListTemuanPatroli: " . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error'   => $e->getMessage()
            ], 500);
        }

    }


    public function GetFotoTemuan($data)
    {
        Log::info('Begin GetFotoTemuan');

        $temuan = DB::connection('qms')
            ->table('scr.scr_temuan_patroli')
            ->select('foto_temuan')
            ->where('id', $data)
            ->first();

        if (!$temuan || !$temuan->foto_temuan) {
            return response()->json(['error' => 'Foto tidak ditemukan'], 404);
        }

        $relativePath = $temuan->foto_temuan; 

        $filePath = storage_path('app/' . $relativePath);

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File tidak ditemukan'], 404);
        }

        Log::info('End GetFotoTemuan');

        return response()->file($filePath, [
            'Content-Type' => 'image/jpeg',
        ]);
    }
    
    


}