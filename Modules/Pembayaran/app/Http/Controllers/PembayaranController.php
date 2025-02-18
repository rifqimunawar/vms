<?php

namespace Modules\Pembayaran\Http\Controllers;

use App\Helpers\Fungsi;
use Illuminate\Http\Request;
use Modules\Tagihan\Models\Pam;
use Modules\Master\Models\Warga;
use Modules\Master\Models\Periode;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Tagihan\Models\TagihanRonda;
use RealRashid\SweetAlert\Facades\Alert;
use Modules\Pembayaran\Models\Pembayaran;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PembayaranController extends Controller
{
  public function index()
  {
    Fungsi::hakAkses('/pembayaran');
    $alert = 'Delete Data!';
    $text = "Are you sure you want to delete?";
    confirmDelete($alert, $text);

    $title = 'Data Pembayaran';

    $data = Warga::with(['absens.ronda', 'rondas', 'tagihans'])->latest()->get();

    $response = [];

    foreach ($data as $index => $item) {
      $totalTagihanUmum = 0;
      foreach ($item->tagihans as $umum) {
        $totalTagihanUmum += $umum->nominal;
      }
      $tagihanUmum = Fungsi::rupiah($totalTagihanUmum);

      $totalTagihanPam = 0;
      foreach ($item->tagihanPam as $tagihan) {
        $totalTagihanPam += $tagihan->nominal;
      }
      $tagihanPamTotalFormatted = Fungsi::rupiah($totalTagihanPam);

      // ronda di ambil harus dari model TagihanRonda
      $jml_tdk_ronda = $item->absens->where('absen', 1)->count();
      $nominal_denda_ronda = $jml_tdk_ronda * 20000;

      $totalTagihan = $totalTagihanUmum + $totalTagihanPam + $nominal_denda_ronda;

      $response[] = [
        'warga_id' => $item->id,
        'nama_warga' => $item->nama,
        'total_tagihan' => Fungsi::rupiah($totalTagihan),
        'tagihan_umum' => $tagihanUmum,
        'tagihan_pam' => $tagihanPamTotalFormatted,
        'jml_tdk_ronda' => $jml_tdk_ronda,
        'nominal_denda_ronda' => Fungsi::rupiah($nominal_denda_ronda)
      ];
    }

    // dd($response);
    // return $data;
    return view(
      'pembayaran::/pembayaran/index',
      [
        'title' => $title,
        'data' => $response,
      ]
    );
  }

  public function periode_pembayaran($warga_id)
  {
    Fungsi::hakAkses('/pembayaran');
    $title = 'Pilih Periode Pembayaran Rutin';
    $data = Warga::with([
      'periodes' => function ($query) {
        $query->orderBy('created_at', 'desc');
      }
    ])->findOrFail($warga_id);



    // return $data;


    return view(
      'pembayaran::/pembayaran/periode_pembayaran_rutin',
      [
        'title' => $title,
        'data' => $data,
        'warga_id' => $warga_id,
      ]
    );
  }
  public function indexByPeriode($warga_id, $periode_id)
  {
    Fungsi::hakAkses('/pembayaran');
    $periode = Periode::findOrFail($periode_id);
    $title = 'Tagihan Rutin Pada Bulan ' . $periode->nama_periode;

    $data = Warga::with([
      'tagihans' => function ($query) use ($periode_id) {
        $query->wherePivot('periode_id', $periode_id)
          ->with(['pembayaran', 'periodes']);
      }
    ])->find($warga_id);
    return view(
      'pembayaran::/pembayaran/indexByPeriode',
      [
        'title' => $title,
        'data' => $data,
      ]
    );
  }

  public function store(Request $request)
  {
    $data = $request->all();
    $warga_id = $request->warga_id;
    $periode_id = $request->periode_id;
    $nama_periode = Periode::where('id', $periode_id)->value('nama_periode');
    $data['periode_nama'] = $nama_periode;

    if ($request->hasFile('img')) {
      $extension = $request->img->getClientOriginalExtension();
      $newFileName = 'pembayaran' . '_' . now()->timestamp . '.' . $extension;
      $request->file('img')->move(public_path('/img'), $newFileName);
      $data['img'] = $newFileName;
    }

    if (!empty($request->id)) {
      $updateData = Pembayaran::findOrFail($request->id);
      $data['modified_by'] = Auth::user()->username;
      $updateData->update($data);
      Alert::success('Success', 'Data berhasil diupdate');
      return redirect()->route('indexByPeriode', ['warga_id' => $warga_id, 'periode_id' => $periode_id]);
    }

    $data['created_by'] = Auth::user()->username;
    Pembayaran::create($data);
    Alert::success('Success', 'Data berhasil disimpan');
    return redirect()->route('indexByPeriode', ['warga_id' => $warga_id, 'periode_id' => $periode_id]);
  }

  public function pembayaran_pam($warga_id)
  {
    Fungsi::hakAkses('/pembayaran');
    $data = Pam::where('warga_id', $warga_id)
      ->with([
        'warga',
        'pembayaran' => function ($query) {
          $query->whereNotNull('pam_id');
        }
      ])
      ->latest()
      ->get();
    $title = 'Tagihan Pam ' . ($data->isNotEmpty() ? $data->first()->warga->nama : '-');
    return view(
      'pembayaran::/pembayaran/pembayaran-pam',
      [
        'title' => $title,
        'data' => $data,
      ]
    );
  }
  public function storePam(Request $request)
  {

    $data = $request->all();
    $warga_id = $request->warga_id;
    if ($request->hasFile('img')) {
      $extension = $request->img->getClientOriginalExtension();
      $newFileName = 'pembayaran' . '_' . now()->timestamp . '.' . $extension;
      $request->file('img')->move(public_path('/img'), $newFileName);
      $data['img'] = $newFileName;
    }

    if (!empty($request->id)) {
      $updateData = Pembayaran::findOrFail($request->id);
      $data['modified_by'] = Auth::user()->username;
      $updateData->update($data);
      Alert::success('Success', 'Data berhasil diupdate');
      return redirect()->route('pembayaran_pam', ['warga_id' => $warga_id]);
    }

    $data['created_by'] = Auth::user()->username;
    Pembayaran::create($data);
    Alert::success('Success', 'Data berhasil disimpan');
    return redirect()->route('pembayaran_pam', ['warga_id' => $warga_id]);
  }

  public function pembayaran_denda($warga_id)
  {
    $title = 'Tagihan Denda Ronda';
    Fungsi::hakAkses('/pembayaran');
    $data = TagihanRonda::with(['warga', 'pembayaran'])
      ->latest()
      ->get();

    // return $data;
    return view(
      'pembayaran::/pembayaran/pembayaran-denda',
      [
        'title' => $title,
        'data' => $data,
      ]
    );
  }

  public function storeDenda(Request $request)
  {
    $data = $request->all();
    // return $data;
    $warga_id = $request->warga_id;
    if ($request->hasFile('img')) {
      $extension = $request->img->getClientOriginalExtension();
      $newFileName = 'pembayaran' . '_' . now()->timestamp . '.' . $extension;
      $request->file('img')->move(public_path('/img'), $newFileName);
      $data['img'] = $newFileName;
    }

    if (!empty($request->id)) {
      $updateData = Pembayaran::findOrFail($request->id);
      $data['modified_by'] = Auth::user()->username;
      $updateData->update($data);
      Alert::success('Success', 'Data berhasil diupdate');
      return redirect()->route('pembayaran_denda', ['warga_id' => $warga_id]);
    }

    $data['created_by'] = Auth::user()->username;
    Pembayaran::create($data);
    Alert::success('Success', 'Data berhasil disimpan');
    return redirect()->route('pembayaran_denda', ['warga_id' => $warga_id]);
  }

  public function invoice($id)
  {
    $title = '';
    $data = Pembayaran::find($id);

    // return $data;
    $qrCode = QrCode::size(80)->generate('Hello, Laravel 11!');
    return view(
      'pembayaran::/pembayaran/invoice',
      [
        'title' => $title,
        'data' => $data,
        'qrCode' => $qrCode,
      ]
    );
  }

}
