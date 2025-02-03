<?php

namespace Modules\Tagihan\Http\Controllers;

use Carbon\Carbon;
use App\Helpers\Fungsi;
use Illuminate\Http\Request;
use Modules\Master\Models\Warga;
use Modules\Tagihan\Models\Umum;
use Illuminate\Support\Facades\DB;
use Modules\Master\Models\Periode;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Master\Exports\UmumExport;
use RealRashid\SweetAlert\Facades\Alert;

class UmumController extends Controller
{
  public function periode()
  {
    Fungsi::hakAkses('/tagihan/umum');
    $alert = 'Delete Data!';
    $text = "Are you sure you want to delete?";
    confirmDelete($alert, $text);

    $title = 'Pilih Periode';
    $data = Periode::latest()
      ->latest()
      ->get();

    return view(
      'tagihan::/umum/periode',
      [
        'title' => $title,
        'data' => $data,
      ]
    );
  }
  public function index($id)
  {
    Fungsi::hakAkses('/tagihan/umum');
    $alert = 'Delete Data!';
    $text = "Are you sure you want to delete?";
    confirmDelete($alert, $text);

    $title = 'Data Tagihan Umum';
    // $data = Umum::with('periodes')->withCount('wargas')
    //   ->latest()
    //   ->get();
    $data = Periode::with('umums')->find($id);




    return $data;
    // return view(
    //   'tagihan::/umum/index',
    //   [
    //     'title' => $title,
    //     'data' => $data,
    //   ]
    // );
  }
  public function create()
  {
    Fungsi::hakAkses('/tagihan/umum');

    $title = "Tagihan Umum Baru";
    return view(
      'tagihan::umum/create',
      [
        'title' => $title,
      ]
    );
  }
  public function store(Request $request)
  {
    $data = $request->all();
    $formatNominal = str_replace(['Rp', '.', ' ', "\u{A0}"], '', $request->nominal);
    $data['nominal'] = intval($formatNominal);


    if ($request->hasFile('img')) {
      $extension = $request->img->getClientOriginalExtension();
      $newFileName = 'umum' . '_' . now()->timestamp . '.' . $extension;
      $request->file('img')->move(public_path('/img'), $newFileName);
      $data['img'] = $newFileName;
    }

    if (!empty($request->id)) {
      $updateData = Umum::findOrFail($request->id);
      $data['updated_by'] = Auth::user()->username;
      $updateData->update($data);

      $wargaIds = Warga::pluck('id')->toArray();
      if (!empty($wargaIds)) {
        $updateData->wargas()->sync($wargaIds);
      }

      Alert::success('Success', 'Data berhasil diupdate');
      return redirect()->route('umum.index');
    }

    $data['created_by'] = Auth::user()->username;
    $umum = Umum::create($data);
    $wargaIds = Warga::pluck('id')->toArray();
    if (!empty($wargaIds)) {
      $umum->wargas()->sync($wargaIds);
    }
    Alert::success('Success', 'Data berhasil disimpan');
    return redirect()->route('umum.index');
  }
  public function edit($id)
  {
    $title = "Update Data Umum";
    Fungsi::hakAkses('/tagihan/umum');
    $umum = Umum::findOrFail($id);

    return view(
      'tagihan::umum.edit',
      [
        'data' => $umum,
        'title' => $title,
      ]
    );
  }
  public function view($id)
  {
    $tagihan_id = $id;
    $umum = Umum::findOrFail($id)->with('wargas')->get();
    $judul = Umum::findOrFail($id);
    $title = "Tagihan " . $judul->nama_tagihan;
    Fungsi::hakAkses('/tagihan/umum');
    // $response = Umum::findOrFail($tagihan_id)->with('wargas')->get();

    $data = DB::table('umum_warga')
      ->select('umum_warga.*', 'umums.nama_tagihan', 'umums.nominal', 'wargas.nama as nama_warga', 'wargas.telp')
      ->join('umums', 'umum_warga.umum_id', '=', 'umums.id')
      ->join('wargas', 'umum_warga.warga_id', '=', 'wargas.id')
      ->where('umum_warga.umum_id', $tagihan_id)
      ->orderBy('umum_warga.id', 'desc')
      ->get();
    // dd($response);
    return view(
      'tagihan::umum.view',
      [
        // 'data' => $judul,
        'data' => $data,
        'title' => $title,
      ]
    );
  }
  public function destroy($id)
  {
    Fungsi::hakAkses('/tagihan/umum');

    $data = Umum::findOrFail($id);
    $data->deleted_by = Auth::user()->username;
    // if ($data->karyawans()->count() > 0) {
    //   Alert::error('Oops....', 'Data tidak dapat dihapus karena memiliki data umum');
    //   return redirect()->route('umum.index');
    // }
    // if ($data->karyawans()->count() > 0) {
    //   Alert::error('Oops....', 'Data tidak dapat dihapus karena memiliki data umum');
    //   return redirect()->route('umum.index');
    // }
    $data->delete();
    Alert::success('Success', 'Data berhasil dihapus');
    return redirect()->route('umum.index');
  }
  public function print()
  {
    $title = "List Data Umum ";
    $data = Umum::latest();
    return view(
      'tagihan::umum/print',
      [
        'title' => $title,
        'data' => $data,
      ]
    );
  }
  public function export()
  {
    return Excel::download(new UmumExport, 'umum.xlsx');
  }
  public function pdf()
  {
    $title = "List Data Tagihan Umum " . Carbon::now()->format('d-M-Y');
    $today = Carbon::now()->format('d M Y');
    $data = Umum::latest();

    // ========================untuk development
    return view(
      'tagihan::umum/pdf',
      [
        'title' => $title,
        'data' => $data,
        'today' => $today,
      ]
    );

    // ========================untuk production
    // $html = view('tagihan::umum.pdf', [
    //   'title' => $title,
    //   'data' => $data,
    //   'today' => $today,
    // ])->render();

    // $mpdf = new \Mpdf\Mpdf();
    // $mpdf->WriteHTML($html);
    // $fileName = Carbon::now()->format('Y_m_d') . '_data_karyawan.pdf';
    // $mpdf->Output($fileName, 'D');
    // $mpdf->Output();
  }
}
