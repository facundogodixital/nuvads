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


class CheckWebsiteScrapingJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $backoff = 15;
    public bool $failOnTimeout = true;

    protected string $logUuid;


    public function __construct(public readonly int $researchRunId)
    {
        $this->logUuid = (string) Str::uuid();
    }


    public function handle(): void
    {
        $this->logInfo('Starting CheckWebsiteScrapingJob.', ['attempt' => $this->attempts()]);
        // La reserva vence al quedar disponible otra entrega; el worker termina antes por su timeout.
        $lock = Cache::lock(self::class.':'.$this->researchRunId, config('queue.connections.database.retry_after'));
        if (!$lock->get()) {
            resolve(ResearchDispatcherService::class)->redispatchAfterOverlap(self::class, $this->researchRunId);
            $this->logInfo('Another instance holds the execution lock. A new job was queued with delay.');
            return;
        }

        try {
            $researchRun = resolve(ResearchRunService::class)->checkWebsiteScraping($this->researchRunId);
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
            ['scraping'],
            'No se pudo consultar o guardar el resultado del scraping.',
        );
    }


    private function logResult(?ResearchRun $researchRun): void
    {
        if ($researchRun === null) {
            $currentRun = resolve(ResearchRunService::class)->find($this->researchRunId);
            $this->logInfo('Skipped: research missing, no longer scraping, or required data unavailable.', [
                'status' => $currentRun?->status,
                'externalRunId' => $currentRun?->external_run_id,
            ]);
            return;
        }

        $message = match ($researchRun->status) {
            'analyzing' => 'Website sources saved. Content analysis queued.',
            'scraping' => 'Apify is still running. Next check queued.',
            'failed' => 'Scraping failed. See the recorded error.',
        };
        $this->logInfo($message, [
            'status' => $researchRun->status,
            'externalRunId' => $researchRun->external_run_id,
            'sourceCount' => count($researchRun->knowledge_source_ids),
            'error' => $researchRun->error_message,
        ]);
        if ($researchRun?->status === 'failed') {
            Log::channel('CheckWebsiteScrapingJobErrors')->error(
                "[{$this->logUuid}] | Research failed.",
                ['researchRunId' => $this->researchRunId, 'reason' => $researchRun->error_message],
            );
        }
    }


    private function logInfo(string $message, array $context = []): void
    {
        $context['researchRunId'] = $this->researchRunId;
        Log::channel('CheckWebsiteScrapingJobInfo')->info("[{$this->logUuid}] | {$message}", $context);
    }


    private function logError(Throwable $exception, string $message): void
    {
        // Los mensajes de excepciones inesperadas pueden contener SQL, credenciales o contenido privado.
        Log::channel('CheckWebsiteScrapingJobErrors')->error("[{$this->logUuid}] | {$message}", [
            'researchRunId' => $this->researchRunId,
            'attempt' => $this->attempts(),
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'errorCode' => $exception instanceof ApiException ? $exception->errorCode : null,
        ]);
    }

}
