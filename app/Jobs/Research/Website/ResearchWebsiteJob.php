<?php

namespace App\Jobs\Research\Website;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Services\ResearchRunService;
use App\Services\WebsiteResearchService;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class ResearchWebsiteJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 600;
    private string $logUuid;


    public function __construct(public readonly int $researchRunId)
    {
        $this->logUuid = (string) Str::uuid();
    }


    public function handle(): void
    {
        $this->logInfo('Starting ResearchWebsiteJob.', ['attempt' => $this->attempts()]);

        $researchRun = resolve(ResearchRunService::class)->find($this->researchRunId);
        if ($researchRun === null) {
            $this->logInfo('Research run not found. Nothing to do.');
            return;
        }
        $researchRun = resolve(WebsiteResearchService::class)->research($researchRun, $this->logInfo(...));

        $this->logInfo('Finished execution.', [
            'status' => $researchRun->status,
            'knowledgeSourceIds' => $researchRun->knowledge_source_ids,
        ]);
    }


    public function failed(Throwable $exception): void
    {
        $context = [
            'researchRunId' => $this->researchRunId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
        // El fallo va también al log Info, así el recorrido completo se lee en un solo archivo.
        foreach (['ResearchWebsiteJobInfo', 'ResearchWebsiteJobErrors'] as $channel) {
            Log::channel($channel)->error("[{$this->logUuid}] | Job failed.", $context);
        }
        resolve(ResearchRunService::class)->fail(
            $this->researchRunId, 'No se pudo completar el análisis del sitio web.',
        );
    }


    private function logInfo(string $message, array $context = []): void
    {
        $context = ['researchRunId' => $this->researchRunId, ...$context];
        Log::channel('ResearchWebsiteJobInfo')->info("[{$this->logUuid}] | {$message}", $context);
    }

}
