<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\InventoryRequest;

class InventoryTransactionController extends Controller
{
    // --- LIST DATA ---
    public function index()
    {
        return InventoryTransaction::orderBy('date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    // --- CREATE DATA ---
    public function store(InventoryRequest $request): JsonResponse
    {

        try {
            DB::transaction(function () use ($request) {

                // PERBAIKAN DI SINI: Pakai $request->qty (sesuai input Postman)
                $qtyEffect = ($request->type === 'Pembelian') ? $request->qty : -$request->qty;

                InventoryTransaction::create([
                    'type' => $request->type,
                    'date' => $request->date,
                    'description' => $request->type,

                    // Mapping: Kiri (DB) = Kanan (Input Postman)
                    'qty_input' => $request->qty,
                    'price_input' => $request->price,

                    'qty' => $qtyEffect,

                    // Default value biar gak error
                    'cost' => 0,
                    'total_cost' => 0,
                    'qty_balance' => 0,
                    'value_balance' => 0,
                    'hpp' => 0
                ]);

                $this->recalculateBalances($request->date);
            });

            return response()->json(['message' => 'Transaksi berhasil disimpan.'], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal Simpan',
                'error_asli' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // --- UPDATE DATA ---
    public function update(InventoryRequest $request, $id)
    {

        $trx = InventoryTransaction::findOrFail($id);
        $oldDate = $trx->date;

        try {
            DB::transaction(function () use ($request, $trx, $oldDate) {

                // PERBAIKAN: Konsisten pakai $request->qty
                $qtyEffect = ($request->type === 'Pembelian') ? $request->qty : -$request->qty;

                $trx->update([
                    'date' => $request->date,
                    'type' => $request->type,
                    'description' => $request->type,

                    // Mapping Update juga disamakan
                    'qty_input' => $request->qty,
                    'price_input' => $request->price,
                    'qty' => $qtyEffect,
                ]);

                // Recalculate dari tanggal yang lebih lampau (tanggal lama vs baru)
                $startDate = ($request->date < $oldDate) ? $request->date : $oldDate;
                $this->recalculateBalances($startDate);
            });

            return response()->json(['message' => 'Transaksi berhasil diupdate.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    // --- DELETE DATA ---
    public function destroy($id)
    {
        $trx = InventoryTransaction::findOrFail($id);
        $trxDate = $trx->date;

        try {
            DB::transaction(function () use ($trx, $trxDate) {
                $trx->delete();
                $this->recalculateBalances($trxDate);
            });
            return response()->json(['message' => 'Transaksi berhasil dihapus.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    // --- HITUNG ULANG (PRIVATE) ---
    private function recalculateBalances($startDate)
    {
        $transactions = InventoryTransaction::where('date', '>=', $startDate)
            ->orderBy('date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $lastData = InventoryTransaction::where('date', '<', $startDate)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        $qtyBalance = $lastData ? $lastData->qty_balance : 0;
        $valueBalance = $lastData ? $lastData->value_balance : 0;
        $hpp = $lastData ? $lastData->hpp : 0;

        foreach ($transactions as $row) {
            if ($row->type == 'Pembelian') {
                $currentCost = $row->price_input;
            } else {
                $currentCost = $hpp;
            }

            $totalCost = $row->qty * $currentCost;
            $qtyBalance += $row->qty;
            $valueBalance += $totalCost;

            // VALIDASI MINUS
            if ($qtyBalance < 0) {
                throw new \Exception("Stok minus ({$qtyBalance}) pada tanggal " . $row->date->format('Y-m-d'));
            }

            // HPP BARU
            if ($qtyBalance > 0) {
                $hpp = $valueBalance / $qtyBalance;
            } else {
                $hpp = 0;
            }

            // Update baris ini tanpa mentrigger event model lain
            DB::table('inventory_transactions')
                ->where('id', $row->id)
                ->update([
                    'cost' => $currentCost,
                    'total_cost' => $totalCost,
                    'qty_balance' => $qtyBalance,
                    'value_balance' => $valueBalance,
                    'hpp' => $hpp,
                ]);
        }
    }
}
