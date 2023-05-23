<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kegunaan;
use App\Models\Peminjaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Jadwal;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;

class PeminjamanController extends Controller
{
    //
    public function index()
    {
        $peminjamen = Peminjaman::with('jadwals')->orderBy('id', 'desc')->paginate(6);
        return view('admin.peminjaman.index', compact('peminjamen'));
    }

    public function create()
    {
        $kegunaans = Kegunaan::all();
        return view('admin.peminjaman.create', compact('kegunaans'));
    }

    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'kegunaan' => 'required',
            'surat' => 'required|file|mimes:pdf',
            'moreFields.*.tanggal' => 'required|date',
            'moreFields.*.jammulai' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    foreach ($request->moreFields as $key => $data) {
                        $jadwal = Jadwal::where('tanggal', $data['tanggal'])
                            ->where(function ($query) use ($data) {
                                $query->whereBetween('jammulai', [$data['jammulai'], $data['jamselesai']])
                                    ->orWhereBetween('jamselesai', [$data['jammulai'], $data['jamselesai']])
                                    ->orWhere(function ($query) use ($data) {
                                        $query->where('jammulai', '<', $data['jammulai'])
                                            ->where('jamselesai', '>', $data['jammulai']);
                                    })
                                    ->orWhere(function ($query) use ($data) {
                                        $query->where('jammulai', '<', $data['jamselesai'])
                                            ->where('jamselesai', '>', $data['jamselesai']);
                                    });
                            })->first();

                        if ($jadwal && $key !== $attribute) {
                            $fail("Jammulai dan jamselesai harus berbeda dengan jadwal lain pada tanggal yang sama.");
                        }
                    }
                },
            ],
            'moreFields.*.jamselesai' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }


        // Simpan data peminjaman ke database
        $peminjaman = new Peminjaman;
        $peminjaman->user_id = auth()->user()->id;
        $peminjaman->kegunaan_id = $request->kegunaan;
        $peminjaman->status = 'diproses';
        $peminjaman->save();

        // Simpan data jadwal ke database
        foreach ($request->moreFields as $key => $value) {
            $jadwal = new Jadwal;
            $jadwal->tanggal = $value['tanggal'];
            $jadwal->jammulai = $value['jammulai'];
            $jadwal->jamselesai = $value['jamselesai'];
            $jadwal->peminjaman_id = $peminjaman->id;
            $jadwal->save();
        }

        // Redirect ke halaman sukses
        return redirect()->route('peminjaman.index')->with('success', 'Peminjaman Sukses Ditambahkan.');
    }

    public function show($id)
    {
        $peminjaman = Peminjaman::with('jadwals')->find($id);
        if (!$peminjaman) {
            abort(404);
        }

        $pdfPath = $peminjaman->surat;

        $path = url('storage/' . $pdfPath);
        // Mengembalikan view bersama dengan data $peminjaman
        return view('admin.peminjaman.detail', compact('peminjaman', 'pdfPath', 'path'));
    }

    public function update(Request $request, $id)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        // Cari data peminjaman yang ingin diupdate
        $peminjaman = Peminjaman::findOrFail($id);

        // Perbarui data peminjaman
        $peminjaman->status = $request->status;
        $peminjaman->save();


        // Redirect ke halaman sukses
        return redirect()->route('peminjaman.index')->with('success', 'Status Peminjaman Sukses Diperbarui.');
    }

    public function delete($id)
    {
        // Temukan peminjaman berdasarkan ID
        $peminjaman = Peminjaman::findOrFail($id);

        $peminjaman->jadwals()->delete();
        $peminjaman->delete();

        // Redirect ke halaman sukses
        return redirect()->route('peminjaman.index')->with('success', 'Peminjaman berhasil dihapus.');
    }
}