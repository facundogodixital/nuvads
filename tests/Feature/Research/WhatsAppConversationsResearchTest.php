<?php

namespace Tests\Feature\Research;

use ZipArchive;
use Tests\TestCase;
use App\Models\Brand;
use App\Models\ResearchRun;
use App\Services\UserService;
use App\Services\BrandService;
use Illuminate\Http\UploadedFile;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use App\Services\ResearchRunService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Storage;
use App\Services\KnowledgeSourceService;
use GuzzleHttp\Promise\PromiseInterface;
use App\Services\KnowledgeInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\WhatsAppConversations\ResearchWhatsAppConversationsJob;


class WhatsAppConversationsResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;
    // Lo que responde el análisis final simulado sobre si los chats son de esta marca.
    private bool $conversationsMatchBrand = true;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchWhatsAppConversationsJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchWhatsAppConversationsJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Maderera',
            'brand_customers_description' => 'Carpinteros.',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // El zip subido queda en el disco hasta que el job lo lee y lo borra. El modelo clasifica los contactos y solo se
    // guardan las conversaciones de clientes, reemplazando las de corridas anteriores; la que el contacto nunca
    // escribió ni llega al modelo. Con 31 conversaciones leídas hay dos tandas, cuyos temas se unifican, y el modelo no
    // puede sumar conversaciones ajenas ni de contactos personales. La mediana de respuesta ignora la respuesta lenta.
    // Cada conclusión apunta a las conversaciones de sus temas, y lo mezclado se guarda en la marca.
    #[Test]
    public function analyzes_the_customer_conversations_of_the_uploaded_zip(): void
    {
        $previousConversation = resolve(KnowledgeSourceService::class)->create($this->brand, [
            'type' => 'whatsapp_conversation', 'title' => 'Chat viejo', 'status' => 'ready',
        ]);
        $conversationFiles = ['5491100000000.txt' => $this->conversationFile('5491100000000', 'Mamá', [
            '[2026-01-01 09:00] Mamá: ¿Venís el domingo?',
            '[2026-01-01 09:05] Yo: Sí',
        ])];
        // 30 clientes que preguntan lo mismo; el negocio contesta en 3 minutos, salvo al primero, en 20.
        for ($customerNumber = 1; $customerNumber <= 30; $customerNumber++) {
            $phone = "54911000000{$customerNumber}";
            $day = str_pad((string) $customerNumber, 2, '0', STR_PAD_LEFT);
            $responseTime = $customerNumber === 1 ? '10:20' : '10:03';
            $conversationFiles["{$phone}.txt"] = $this->conversationFile($phone, "Cliente {$customerNumber}", [
                "[2026-08-{$day} 10:00] Cliente {$customerNumber}: Hola",
                '¿Cepillan la madera?',
                // El byte 0x85 de 😅 no puede cortar la línea.
                "[2026-08-{$day} {$responseTime}] Yo: Sí, cepillamos a pedido 😅",
            ]);
        }
        $conversationFiles['5491199999999.txt'] = $this->conversationFile('5491199999999', 'Promo', [
            '[2026-09-20 10:00] Yo: Promo de la semana',
        ]);
        Http::fake(['https://api.openai.com/v1/responses' => $this->fakeOpenAiResponse(...)]);

        $response = $this->post(
            '/api/research-runs',
            ['type' => 'whatsapp_conversations', 'zip_file' => $this->zipFile($conversationFiles)],
            ['Accept' => 'application/json'],
        );
        $researchRun = ResearchRun::query()->findOrFail($response->assertCreated()->json('data.id'));
        Queue::assertPushedOn('research_queue', ResearchWhatsAppConversationsJob::class);
        (new ResearchWhatsAppConversationsJob($researchRun->id))->handle();

        $researchRun->refresh();
        $brand = $this->brand->fresh();
        $this->assertSame('completed', $researchRun->status);
        Storage::disk('local')->assertMissing($researchRun->input['zip_path']);
        $this->assertCount(30, $researchRun->knowledge_source_ids);
        $this->assertNull(resolve(KnowledgeSourceService::class)->find($brand, $previousConversation->id));
        $openAiBodies = array_map(fn (Request $request): string => $request->body(), $this->recordedOpenAiRequests());
        $this->assertStringNotContainsString('Promo de la semana', implode("\n", $openAiBodies));
        $knowledgeSourceService = resolve(KnowledgeSourceService::class);
        $savedConversation = $knowledgeSourceService->find($brand, $researchRun->knowledge_source_ids[0]);
        $this->assertSame("Hola\n¿Cepillan la madera?", $savedConversation->payload['messages'][0]['text']);

        $this->assertSame('¿Cepillan la madera? Sí, a pedido.', $brand->brand_customers_faq_description);
        $this->assertSame('Carpinteros.', $brand->brand_customers_description);

        $this->getJson('/api/research-runs/whatsapp-conversations/status')->assertOk()
            ->assertJsonPath('data.last_completed.id', $researchRun->id);
        $whatsAppConversationsInsights = $this->getJson('/api/knowledge-insights/whatsapp-conversations')
            ->assertOk()
            ->json('data');
        $metrics = $whatsAppConversationsInsights['metrics']['payload'];
        $contactKinds = ['customer' => 30, 'supplier' => 0, 'personal' => 1, 'other' => 0];
        $this->assertEquals($contactKinds, $metrics['contact_kinds']);
        $this->assertEquals(3, $metrics['median_owner_response_minutes']);
        $this->assertSame(0.97, $metrics['answered_within_5_minutes_share']);
        $this->assertSame(30, $whatsAppConversationsInsights['analysis']['payload']['products'][0]['mentions_count']);

        $question = $whatsAppConversationsInsights['questions'][0];
        $this->assertSame('¿Cepillan la madera?', $question['body']);
        $this->assertEqualsCanonicalizing($researchRun->knowledge_source_ids, $question['knowledge_source_ids']);
        $this->assertSame('Sí, cepillamos a pedido.', $question['payload']['owner_answer']);
        $this->assertCount(3, $question['payload']['highlight_ids']);
        [$questionInsight, $metricsInsight] = $whatsAppConversationsInsights['insights'];
        $this->assertSame($question['knowledge_source_ids'], $questionInsight['knowledge_source_ids']);
        $this->assertSame([], $metricsInsight['payload']['highlight_ids']);
        $highlightedKnowledgeSourceIds = array_column($whatsAppConversationsInsights['conversations'], 'id');
        $this->assertEqualsCanonicalizing($question['payload']['highlight_ids'], $highlightedKnowledgeSourceIds);
    }


    // Si ninguna conversación es de un cliente, no hay nada para analizar: la investigación termina vacía con un
    // mensaje que lo dice, sin análisis final, y las conversaciones y el análisis anteriores siguen vigentes.
    #[Test]
    public function ends_empty_without_replacing_anything_when_no_conversation_is_from_a_customer(): void
    {
        $previousConversation = resolve(KnowledgeSourceService::class)->create($this->brand, [
            'type' => 'whatsapp_conversation', 'title' => 'Chat anterior', 'status' => 'ready',
        ]);
        $previousAnalysis = resolve(KnowledgeInsightService::class)->create($this->brand, [
            'type' => 'whatsapp_conversations_brand_analysis', 'body' => 'Análisis anterior.', 'status' => 'active',
            'level' => 1,
        ]);
        $conversationFiles = ['5491100000000.txt' => $this->conversationFile('5491100000000', 'Mamá', [
            '[2026-01-01 09:00] Mamá: ¿Venís el domingo?',
        ])];
        Http::fake(['https://api.openai.com/v1/responses' => $this->fakeOpenAiResponse(...)]);
        $researchRun = $this->createResearchRun($conversationFiles);

        (new ResearchWhatsAppConversationsJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('empty', $researchRun->status);
        $this->assertNotNull($researchRun->status_message);
        $this->assertCount(1, $this->recordedOpenAiRequests());
        $this->assertNotNull(resolve(KnowledgeSourceService::class)->find($this->brand, $previousConversation->id));
        $this->assertSame('active', $previousAnalysis->fresh()->status);
    }


    // Si el modelo ve que los chats son de otro negocio, la investigación termina igual, pero no toca el perfil de la
    // marca y el análisis lo deja registrado para que la pantalla lo avise.
    #[Test]
    public function does_not_touch_the_brand_when_the_conversations_are_from_another_business(): void
    {
        $this->conversationsMatchBrand = false;
        $conversationFiles = ['5491100000001.txt' => $this->conversationFile('5491100000001', 'Cliente 1', [
            '[2026-08-01 10:00] Cliente 1: ¿Cepillan la madera?',
            '[2026-08-01 10:03] Yo: Sí, cepillamos a pedido',
        ])];
        Http::fake(['https://api.openai.com/v1/responses' => $this->fakeOpenAiResponse(...)]);
        $researchRun = $this->createResearchRun($conversationFiles);

        (new ResearchWhatsAppConversationsJob($researchRun->id))->handle();

        $this->assertSame('completed', $researchRun->fresh()->status);
        $this->assertNull($this->brand->fresh()->brand_customers_faq_description);
        $this->getJson('/api/knowledge-insights/whatsapp-conversations')->assertOk()
            ->assertJsonPath('data.analysis.payload.matches_brand', false);
    }


    private function createResearchRun(array $conversationFiles): ResearchRun
    {
        return resolve(ResearchRunService::class)->create($this->brand, [
            'type' => 'whatsapp_conversations',
            'zip_file' => $this->zipFile($conversationFiles),
        ]);
    }


    private function recordedOpenAiRequests(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)->map(fn (array $pair): Request => $pair[0])->values()->all();
    }


    // Simula al modelo según la consulta. En cada tanda, Mamá es un contacto personal y el resto son clientes; la
    // pregunta sale de los textos, y el producto suma todas las claves, la de Mamá y una ajena, que el service tiene
    // que descartar. En la unificación agrupa los temas con el mismo nombre, y en el análisis final devuelve una
    // conclusión sobre la pregunta y otra sobre las métricas.
    private function fakeOpenAiResponse(Request $request): PromiseInterface
    {
        $input = $this->decodeOpenAiText($request['input']);
        $isBatchRequest = str_contains($request['instructions'], 'Clasificar cada conversación');
        $isUnifyRequest = str_contains($request['instructions'], 'Tu tarea es unificar');

        if ($isBatchRequest) {
            $contactKinds = [];
            $woodQuestionKeys = [];
            foreach ($input['conversations'] as $conversation) {
                $contactKinds[$conversation['key']] = $conversation['contact'] === 'Mamá' ? 'personal' : 'customer';
                if (str_contains($conversation['messages'], 'Cepillan')) {
                    $woodQuestionKeys[] = $conversation['key'];
                }
            }
            $woodQuestions = $woodQuestionKeys === [] ? [] : [[
                'topic' => '¿Cepillan la madera?',
                'conversation_keys' => $woodQuestionKeys,
                'owner_answer' => 'Sí, cepillamos a pedido.',
            ]];
            $allConversationKeys = [...array_keys($contactKinds), 'c999'];
            return Http::response($this->openAiResponse([
                'contact_kinds' => $contactKinds,
                'questions' => $woodQuestions,
                'objections' => [],
                'products' => [
                    ['topic' => 'Madera', 'conversation_keys' => $allConversationKeys, 'owner_answer' => null],
                ],
                'purposes' => [],
                'acquisition' => [],
                'customer_phrases' => [],
            ]));
        }

        if ($isUnifyRequest) {
            $groups = [];
            foreach ($input as $category => $topicNamesByKey) {
                $groups[$category] = [];
                foreach (array_unique($topicNamesByKey) as $topicName) {
                    $keys = array_keys($topicNamesByKey, $topicName, true);
                    $groups[$category][] = ['topic' => $topicName, 'keys' => $keys];
                }
            }
            return Http::response($this->openAiResponse($groups));
        }

        return Http::response($this->openAiResponse([
            'matches_brand' => $this->conversationsMatchBrand,
            'brand' => [
                'brand_customers_description' => null,
                'brand_customers_faq_description' => '¿Cepillan la madera? Sí, a pedido.',
                'brand_customers_needs_description' => null,
                'brand_content_opportunities_description' => null,
            ],
            'summary' => 'Preguntan si cepillan la madera.',
            'owner_voice' => 'Tutea y responde corto.',
            'insights' => [
                ['body' => 'Todos preguntan por el cepillado.', 'topic_keys' => ['questions_1']],
                ['body' => 'Responde en minutos.', 'topic_keys' => []],
            ],
        ]));
    }


    // El helper de OpenAI agrega un recordatorio de JSON al final del texto; se decodifica solo el objeto.
    private function decodeOpenAiText(string $text): array
    {
        $jsonObject = substr($text, 0, strrpos($text, '}') + 1);

        return json_decode($jsonObject, true);
    }


    // Un .txt con la forma en que lo arma la extensión de WhatsApp.
    private function conversationFile(string $phone, string $contactName, array $messageLines): string
    {
        $header = "# Teléfono: {$phone}\n# Contacto: {$contactName}\n# Mensajes: ".count($messageLines);

        return $header."\n\n".implode("\n", $messageLines)."\n";
    }


    private function zipFile(array $conversationFiles): UploadedFile
    {
        $zipFilePath = tempnam(sys_get_temp_dir(), 'whatsapp');
        $zip = new ZipArchive();
        $zip->open($zipFilePath, ZipArchive::OVERWRITE);
        foreach ($conversationFiles as $fileName => $fileText) {
            $zip->addFromString($fileName, $fileText);
        }
        $zip->close();

        return new UploadedFile($zipFilePath, 'whatsapp-conversaciones.zip', 'application/zip', null, true);
    }


    private function openAiResponse(array $content): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($content)]],
        ]]];
    }

}
