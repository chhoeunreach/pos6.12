<?php

namespace Tests\Unit;

use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotServiceTest extends TestCase
{
    public function test_send_document_omits_empty_caption(): void
    {
        config(['telegram.bot_token' => 'test-token']);

        $tmpFile = tempnam(sys_get_temp_dir(), 'tg_');
        file_put_contents($tmpFile, '%PDF-1.4 fake');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $service = new class extends TelegramBotService {
            public function payloadForTest(string $chatId, ?string $caption): array
            {
                return $this->sendDocumentPayload($chatId, $caption);
            }
        };

        $this->assertSame(
            ['chat_id' => '-100123', 'parse_mode' => 'HTML'],
            $service->payloadForTest('-100123', null)
        );

        $service->sendDocumentToChat('-100123', $tmpFile, null, 'test.pdf');

        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);

        /** @var \Illuminate\Http\Client\Request $request */
        $request = $recorded[0][0];
        $this->assertTrue(str_contains($request->url(), '/sendDocument'));
    }
}
