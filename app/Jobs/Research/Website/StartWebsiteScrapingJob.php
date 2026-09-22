<?php

namespace App\Jobs\Research\Website;

use Throwable;
use App\Models\ResearchRun;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;
use App\Services\ResearchRunService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Dispatchers\ResearchDispatcherService;


class StartWebsiteScrapingJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public bool $failOnTimeout = true;

    protected string $logUuid;


    public function __construct(public readonly int $researchRunId)
    {
        $this->logUuid = (string) Str::uuid();
    }


    public function handle(): void
    {
        $this->logInfo('Starting StartWebsiteScrapingJob.', ['attempt' => $this->attempts()]);
        // La reserva vence al quedar disponible otra entrega; el worker termina antes por su timeout.
        $lock = Cache::lock(self::class.':'.$this->researchRunId, config('queue.connections.database.retry_after'));
        if (!$lock->get()) {
            resolve(ResearchDispatcherService::class)->redispatchAfterOverlap(self::class, $this->researchRunId);
            $this->logInfo('Another instance holds the execution lock. A new job was queued with delay.');
            return;
        }

        try {
            $researchRun = resolve(ResearchRunService::class)->startWebsiteScraping($this->researchRunId);
            $this->logResult($researchRun);
        } catch (Throwable $exception) {
            $this->logError($exception, 'Attempt failed.');
            throw $exception;
        } finally {
            $lock->release();
        }
    }


    public function failed(Throwable $exception): void
    {
        $this->logError($exception, 'Job failed permanently.');
        resolve(ResearchRunService::class)->fail(
            $this->researchRunId,
            ['pending', 'scraping'],
            'No se pudo iniciar o registrar el scraping. Revisa la ejecución en Apify antes de repetirlo.',
        );
    }


    private function logResult(?ResearchRun $researchRun): void
    {
        if ($researchRun === null) {
            $currentRun = resolve(ResearchRunService::class)->find($this->researchRunId);
            $this->logInfo('Skipped: research missing, no longer pending, or required data unavailable.', [
                'status' => $currentRun?->status,
                'externalRunId' => $currentRun?->external_run_id,
            ]);
            return;
        }

        $this->logInfo('Apify run started. Next scraping check queued.', [
            'status' => $researchRun->status,
            'externalRunId' => $researchRun->external_run_id,
            'sourceCount' => count($researchRun->knowledge_source_ids),
            'error' => $researchRun->error_message,
        ]);
        if ($researchRun?->status === 'failed') {
            Log::channel('StartWebsiteScrapingJobErrors')->error(
                "[{$this->logUuid}] | Research failed.",
                ['researchRunId' => $this->researchRunId, 'reason' => $researchRun->error_message],
            );
        }
    }


    private function logInfo(string $message, array $context = []): void
    {
        $context['researchRunId'] = $this->researchRunId;
        Log::channel('StartWebsiteScrapingJobInfo')->info("[{$this->logUuid}] | {$message}", $context);
    }


    private function logError(Throwable $exception, string $message): void
    {
        // Los mensajes de excepciones inesperadas pueden contener SQL, credenciales o contenido privado.
        Log::channel('StartWebsiteScrapingJobErrors')->error("[{$this->logUuid}] | {$message}", [
            'researchRunId' => $this->researchRunId,
            'attempt' => $this->attempts(),
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'errorCode' => $exception instanceof ApiException ? $exception->errorCode : null,
        ]);
    }

}
