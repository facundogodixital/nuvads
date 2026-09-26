<?php

namespace Tests\Feature\Competitors;

use Tests\TestCase;
use App\Models\Brand;
use App\Services\BrandService;
use App\Services\CompetitorService;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\Competitors\ResearchBrandCompetitionJob;


class BrandCompetitionResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;


    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchBrandCompetitionJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchBrandCompetitionJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Mi marca',
            'brand_offer_description' => 'Plantas de interior con asesoramiento.',
            'brand_content_opportunities_description' => 'Mostrar el vivero por dentro.',
        ]);
    }


    // El cruce manda la marca y solo los competidores que ya tienen algo analizado. Los campos de la competencia se
    // guardan enteros, también los que vuelven vacíos, y las ideas de contenido se reemplazan por el texto mezclado.
    #[Test]
    public function recalculates_the_competition_fields_from_the_analyzed_competitors(): void
    {
        resolve(BrandService::class)->update($this->brand, ['competitors_weaknesses_description' => 'Cálculo viejo.']);
        $competitorService = resolve(CompetitorService::class);
        $competitorService->create($this->brand, [
            'name' => 'Vivero Sur',
            'competitor_weaknesses_description' => 'Tarda en responder consultas.',
        ]);
        $competitorService->create($this->brand, ['name' => 'Recién sumado']);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($this->openAiResponse([
            'competitors_strengths_description' => null,
            'competitors_weaknesses_description' => 'Vivero Sur tarda en responder.',
            'competitors_opportunities_description' => 'Responder rápido es una ventaja.',
            'brand_content_opportunities_description' => 'Mostrar el vivero por dentro y los tiempos de respuesta.',
        ]))]);

        (new ResearchBrandCompetitionJob($this->brand->id))->handle();

        $brand = $this->brand->fresh();
        $competitionInput = $this->recordedOpenAiInputs()[0];
        $this->assertSame(['Vivero Sur'], array_column($competitionInput['competitors'], 'name'));
        $sentBrandOffer = $competitionInput['brand']['brand_offer_description'];
        $this->assertSame('Plantas de interior con asesoramiento.', $sentBrandOffer);
        $this->assertNull($brand->competitors_strengths_description);
        $this->assertSame('Vivero Sur tarda en responder.', $brand->competitors_weaknesses_description);
        $this->assertSame('Responder rápido es una ventaja.', $brand->competitors_opportunities_description);
        $mergedContentOpportunities = 'Mostrar el vivero por dentro y los tiempos de respuesta.';
        $this->assertSame($mergedContentOpportunities, $brand->brand_content_opportunities_description);
    }


    // Sin competidores analizados, por ejemplo después de borrar el último, los campos de la competencia quedan
    // vacíos sin consultar al modelo, y las ideas de contenido no se tocan.
    #[Test]
    public function clears_the_competition_fields_when_no_competitor_is_analyzed(): void
    {
        resolve(BrandService::class)->update($this->brand, [
            'competitors_strengths_description' => 'Cálculo viejo.',
            'competitors_opportunities_description' => 'Cálculo viejo.',
        ]);
        Http::fake();

        (new ResearchBrandCompetitionJob($this->brand->id))->handle();

        $brand = $this->brand->fresh();
        $this->assertNull($brand->competitors_strengths_description);
        $this->assertNull($brand->competitors_opportunities_description);
        $this->assertSame('Mostrar el vivero por dentro.', $brand->brand_content_opportunities_description);
        Http::assertNothingSent();
    }


    private function recordedOpenAiInputs(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)
            ->map(fn (array $pair): array => $this->decodeOpenAiText($pair[0]['input']))
            ->values()
            ->all();
    }


    // El helper de OpenAI agrega un recordatorio de JSON al final del texto; se decodifica solo el objeto.
    private function decodeOpenAiText(string $text): array
    {
        $jsonObject = substr($text, 0, strrpos($text, '}') + 1);

        return json_decode($jsonObject, true);
    }


    private function openAiResponse(array $content): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($content)]],
        ]]];
    }

}
