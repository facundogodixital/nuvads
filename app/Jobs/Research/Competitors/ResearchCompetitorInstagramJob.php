<?php

namespace App\Jobs\Research\Competitors;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\CompetitorResearchRunService;
use App\Services\CompetitorInstagramResearchService;


class ResearchCompetitorInstagramJob implements ShouldQueue
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
        $this->logInfo('Starting ResearchCompetitorInstagramJob.', ['attempt' => $this->attempts()]);

        $researchRun = resolve(CompetitorResearchRunService::class)->find($this->researchRunId);
        if ($researchRun === null) {
            $this->logInfo('Research run not found. Nothing to do.');
            return;
        }
        // Un competidor borrado mientras la investigación esperaba en la queue ya no se investiga: no se llama a
        // ningún proveedor y la investigación queda como estaba, porque nadie la va a ver.
        $competitorWasDeleted = $researchRun->competitor === null;
        if ($competitorWasDeleted) {
            $this->logInfo('Competitor deleted. Nothing to do.', ['competitorId' => $researchRun->competitor_id]);
            return;
        }
        $researchRun = resolve(CompetitorInstagramResearchService::class)->research(
            $researchRun, $this->logInfo(...), $this->logError(...),
        );

        $this->logInfo('Finished execution.', [
            'status' => $researchRun->status,
            'competitorSourceIds' => $researchRun->competitor_source_ids,
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
        resolve(CompetitorResearchRunService::class)->fail(
            $this->researchRunId, 'No se pudo completar el análisis de Instagram.',
        );
    }


    private function logInfo(string $message, array $context = []): void
    {
        $context = ['researchRunId' => $this->researchRunId, ...$context];
        Log::channel('ResearchCompetitorInstagramJobInfo')->info("[{$this->logUuid}] | {$message}", $context);
    }


    // El error va también al log Info, así el recorrido completo se lee en un solo archivo.
    private function logError(string $message, array $context = []): void
    {
        $context = ['researchRunId' => $this->researchRunId, ...$context];
        foreach (['ResearchCompetitorInstagramJobInfo', 'ResearchCompetitorInstagramJobErrors'] as $channel) {
            Log::channel($channel)->error("[{$this->logUuid}] | {$message}", $context);
        }
    }

}
