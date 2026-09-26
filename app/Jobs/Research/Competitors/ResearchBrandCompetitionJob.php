<?php

namespace App\Jobs\Research\Competitors;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Services\BrandService;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\BrandCompetitionResearchService;


class ResearchBrandCompetitionJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 600;
    private string $logUuid;


    public function __construct(public readonly int $brandId)
    {
        $this->logUuid = (string) Str::uuid();
    }


    public function handle(): void
    {
        $this->logInfo('Starting ResearchBrandCompetitionJob.', ['attempt' => $this->attempts()]);

        $brand = resolve(BrandService::class)->find($this->brandId);
        resolve(BrandCompetitionResearchService::class)->research($brand, $this->logInfo(...));

        $this->logInfo('Finished execution.');
    }


    // No hay una investigación para marcar como fallida: lo que la marca sabía de su competencia sigue como estaba.
    public function failed(Throwable $exception): void
    {
        $this->logError('Job failed.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }


    private function logInfo(string $message, array $context = []): void
    {
        $context = ['brandId' => $this->brandId, ...$context];
        Log::channel('ResearchBrandCompetitionJobInfo')->info("[{$this->logUuid}] | {$message}", $context);
    }


    // El error va también al log Info, así el recorrido completo se lee en un solo archivo.
    private function logError(string $message, array $context = []): void
    {
        $context = ['brandId' => $this->brandId, ...$context];
        foreach (['ResearchBrandCompetitionJobInfo', 'ResearchBrandCompetitionJobErrors'] as $channel) {
            Log::channel($channel)->error("[{$this->logUuid}] | {$message}", $context);
        }
    }

}
