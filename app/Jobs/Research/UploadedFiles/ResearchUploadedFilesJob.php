<?php

namespace App\Jobs\Research\UploadedFiles;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Services\ResearchRunService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\UploadedFilesResearchService;


class ResearchUploadedFilesJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout;
    public int $tries = 1;
    private string $logUuid;


    public function __construct(public readonly int $researchRunId)
    {
        $this->logUuid = (string) Str::uuid();
        // El worker lo corta 60 segundos antes de que la queue lo dé por perdido y lo vuelva a entregar.
        $this->timeout = config('queue.connections.database.retry_after') - 60;
    }


    public function handle(): void
    {
        $this->logInfo('Starting ResearchUploadedFilesJob.', ['attempt' => $this->attempts()]);

        $researchRun = resolve(ResearchRunService::class)->find($this->researchRunId);
        if ($researchRun === null) {
            $this->logInfo('Research run not found. Nothing to do.');
            return;
        }
        $researchRun = resolve(UploadedFilesResearchService::class)->research(
            $researchRun, $this->logInfo(...), $this->logError(...),
        );

        $this->logInfo('Finished execution.', [
            'status' => $researchRun->status,
            'files' => count($researchRun->knowledge_source_ids),
        ]);
    }


    public function failed(Throwable $exception): void
    {
        $this->logError('Job failed.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);
        resolve(ResearchRunService::class)->fail(
            $this->researchRunId, 'No se pudo completar el análisis de tus archivos.',
        );
    }


    private function logInfo(string $message, array $context = []): void
    {
        $context = ['researchRunId' => $this->researchRunId, ...$context];
        Log::channel('ResearchUploadedFilesJobInfo')->info("[{$this->logUuid}] | {$message}", $context);
    }


    // El error va también al log Info, así el recorrido completo se lee en un solo archivo.
    private function logError(string $message, array $context = []): void
    {
        $context = ['researchRunId' => $this->researchRunId, ...$context];
        foreach (['ResearchUploadedFilesJobInfo', 'ResearchUploadedFilesJobErrors'] as $channel) {
            Log::channel($channel)->error("[{$this->logUuid}] | {$message}", $context);
        }
    }

}
