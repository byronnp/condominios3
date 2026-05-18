<?php

namespace App\Console\Commands;

use App\Models\Condominium\Condominium;
use App\Services\Billing\MonthlyFeeChargeGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyFeeCharges extends Command
{
    protected $signature = 'fee-charges:generate-monthly {--period= : Periodo en formato YYYY-MM}';

    protected $description = 'Genera automaticamente las alicuotas mensuales para todos los condominios activos.';

    public function handle(MonthlyFeeChargeGenerator $generator): int
    {
        $period = $this->option('period') ?: Carbon::now()->format('Y-m');

        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            $this->error('El periodo debe tener formato YYYY-MM.');

            return self::FAILURE;
        }

        Condominium::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Condominium $condominium) use ($generator, $period): void {
                try {
                    $result = $generator->generateForCondominium($condominium, $period);
                    $this->info($result['condominium_name'].': creadas '.$result['created'].', omitidas '.$result['skipped'].'.');
                } catch (\Throwable $exception) {
                    $this->warn($condominium->name.': '.$exception->getMessage());
                }
            });

        return self::SUCCESS;
    }
}
