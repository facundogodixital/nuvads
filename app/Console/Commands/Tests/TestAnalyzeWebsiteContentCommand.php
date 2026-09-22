<?php

namespace App\Console\Commands\Tests;

use Illuminate\Console\Command;
use App\Helpers\FirecrawlHelper;
use Illuminate\Support\Facades\File;


class TestAnalyzeWebsiteContentCommand extends Command
{

    /** @var string */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint -- heredada sin tipo.
    protected $signature = 'tests:analyze-website-content';
    /** @var string */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint -- heredada sin tipo.
    protected $description = 'Prueba manual de análisis del contenido de un sitio web';


    public function handle(): int
    {
        $url = 'https://upgrowshop.com';
        $this->info("Recopilando la portada con Firecrawl: {$url}");
        $rawJson = resolve(FirecrawlHelper::class)->scrapeWebsite($url);

        $timestamp = now()->format('Ymd-His-u');
        $directory = storage_path('app/private/tests');
        $filePath = "{$directory}/upgrowshop-firecrawl-{$timestamp}.json";
        File::ensureDirectoryExists($directory);
        File::put($filePath, $rawJson);
        $this->info("Respuesta original guardada en: {$filePath}");

        return self::SUCCESS;
    }

}
