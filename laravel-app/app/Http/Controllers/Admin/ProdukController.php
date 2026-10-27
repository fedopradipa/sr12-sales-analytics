<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produk\StoreProdukRequest;
use App\Http\Requests\Produk\UpdateProdukRequest;
use App\Http\Requests\Produk\UpdateProdukStockRequest;
use App\Models\Produk;
use App\Services\ProdukService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProdukController extends Controller
{
    public function __construct(
        protected ProdukService $produkService
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:60',
            ],
        ]);

        $daftarProduk = $this->produkService
            ->paginate($filters);

        return view(
            'admin.produk.index',
            compact(
                'daftarProduk'
            )
        );
    }

    public function store(
        StoreProdukRequest $request
    ): RedirectResponse {
        $this->produkService->create(
            $request->validated()
        );

        return redirect()
            ->route('admin.produk.index')
            ->with(
                'success',
                'Produk berhasil ditambahkan.'
            );
    }

    public function update(
        UpdateProdukRequest $request,
        Produk $produk
    ): RedirectResponse {
        $this->produkService->update(
            $produk,
            $request->validated()
        );

        return redirect()
            ->route('admin.produk.index')
            ->with(
                'success',
                'Produk berhasil diperbarui.'
            );
    }

    public function updateStock(
        UpdateProdukStockRequest $request,
        Produk $produk
    ): RedirectResponse {
        $data = $request->validated();

        $this->produkService->updateStock(
            $produk,
            (int) $data['stok']
        );

        return redirect()
            ->route('admin.produk.index')
            ->with(
                'success',
                'Stok produk berhasil diperbarui.'
            );
    }

    public function destroy(
        Produk $produk
    ): RedirectResponse {
        $this->produkService->delete($produk);

        return redirect()
            ->route('admin.produk.index')
            ->with(
                'success',
                'Produk berhasil dihapus.'
            );
    }
}
