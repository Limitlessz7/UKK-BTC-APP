<x-filament::page>
    <h2 class="text-xl font-bold mb-4">Laporan Penjualan Hari Ini ({{ \Carbon\Carbon::today()->toFormattedDateString() }})</h2>

    <div class="mb-6">
        <span class="font-semibold">Total Pendapatan:</span>
        <span class="text-green-600 text-lg">{{ number_format($this->getTotalHariIni(), 0, ',', '.') }} IDR</span>
    </div>

    {{ $this->table }}
</x-filament::page>
