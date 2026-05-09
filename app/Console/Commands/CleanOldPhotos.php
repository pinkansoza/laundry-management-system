<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Pemesanan;
use Carbon\Carbon;

class CleanOldPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:clean-old
                            {--months=2 : Hapus foto yang lebih tua dari N bulan}
                            {--dry-run : Hanya tampilkan apa yang akan dihapus, tanpa menghapus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menghapus foto kondisi awal pemesanan yang sudah lebih dari 2 bulan untuk menghemat storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::now()->subMonths($months);

        $this->info("🧹 Membersihkan foto pemesanan yang dibuat sebelum: {$cutoffDate->format('d M Y')}");

        if ($dryRun) {
            $this->warn('⚠️  Mode DRY RUN — tidak ada file yang benar-benar dihapus.');
        }

        // Ambil semua pemesanan lama yang masih punya foto
        $pemesanans = Pemesanan::where('created_at', '<', $cutoffDate)
            ->whereNotNull('foto')
            ->get();

        if ($pemesanans->isEmpty()) {
            $this->info('✅ Tidak ada foto lama yang perlu dihapus.');
            return self::SUCCESS;
        }

        $totalFiles = 0;
        $totalDeleted = 0;
        $totalSizeFreed = 0;

        foreach ($pemesanans as $pemesanan) {
            $photos = $pemesanan->foto;

            // Skip jika foto kosong atau bukan array
            if (empty($photos) || !is_array($photos)) {
                continue;
            }

            $this->line('');
            $this->info("📦 Pesanan: {$pemesanan->kode_pesanan} ({$pemesanan->created_at->format('d M Y')})");

            foreach ($photos as $photoPath) {
                $totalFiles++;

                // Cek apakah file ada di storage
                if (Storage::disk('public')->exists($photoPath)) {
                    $fileSize = Storage::disk('public')->size($photoPath);

                    if ($dryRun) {
                        $this->line("   [DRY RUN] Akan dihapus: {$photoPath} (" . $this->formatBytes($fileSize) . ")");
                    } else {
                        Storage::disk('public')->delete($photoPath);
                        $this->line("   ✅ Dihapus: {$photoPath} (" . $this->formatBytes($fileSize) . ")");
                        $totalDeleted++;
                        $totalSizeFreed += $fileSize;
                    }
                } else {
                    $this->line("   ⚠️  File tidak ditemukan: {$photoPath}");
                }
            }

            // Kosongkan kolom foto di database (kecuali dry run)
            if (!$dryRun) {
                $pemesanan->update(['foto' => null]);
                $this->line("   🗃️  Kolom foto di database dikosongkan.");
            }
        }

        $this->line('');
        $this->info("═══════════════════════════════════════");
        $this->info("📊 Ringkasan:");
        $this->info("   Total pesanan diproses : {$pemesanans->count()}");
        $this->info("   Total file ditemukan   : {$totalFiles}");

        if ($dryRun) {
            $this->warn("   Mode DRY RUN — tidak ada yang dihapus.");
        } else {
            $this->info("   Total file dihapus     : {$totalDeleted}");
            $this->info("   Storage dibebaskan     : " . $this->formatBytes($totalSizeFreed));
        }
        $this->info("═══════════════════════════════════════");

        return self::SUCCESS;
    }

    /**
     * Format bytes ke ukuran yang mudah dibaca.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
