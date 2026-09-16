<?php

namespace Tests\Feature;

use App\Jobs\ScanTicketJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OpenApiImportTest extends TestCase
{
    use RefreshDatabase;

    private function openApiSpec(?array $paths = null): string
    {
        return json_encode([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Toko API', 'version' => '1.0'],
            'servers' => [['url' => 'https://api.example.com/v1']],
            'paths' => $paths ?? [
                '/products' => ['get' => ['summary' => 'Daftar produk']],
                '/products/{id}' => ['get' => ['summary' => 'Detail produk']],
                '/orders' => ['post' => ['summary' => 'Buat pesanan']],
            ],
        ]);
    }

    public function test_the_import_page_is_reachable(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tickets.import'))
            ->assertOk()
            ->assertSee('Import dari OpenAPI');
    }

    public function test_get_endpoints_become_tickets(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response($this->openApiSpec(), 200)]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/openapi.json'])
            ->assertRedirect(route('tickets.index'))
            ->assertSessionHas('success');

        $tickets = $user->tickets()->get();

        // POST-only paths are skipped: the scanner never sends data.
        $this->assertCount(2, $tickets);
        $this->assertEqualsCanonicalizing(
            ['https://api.example.com/v1/products', 'https://api.example.com/v1/products/1'],
            $tickets->pluck('api_url')->all()
        );
        $this->assertSame('Daftar produk — GET /products', $tickets->firstWhere('api_url', 'https://api.example.com/v1/products')->title);
    }

    public function test_endpoints_can_be_scanned_right_away(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response($this->openApiSpec(), 200)]);

        $this->actingAs(User::factory()->create())
            ->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/openapi.json', 'scan_now' => '1']);

        Queue::assertPushed(ScanTicketJob::class, 2);
    }

    public function test_existing_endpoints_are_skipped(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response($this->openApiSpec(), 200)]);

        $user = User::factory()->create();
        $user->tickets()->create(['title' => 'Sudah ada', 'api_url' => 'https://api.example.com/v1/products']);

        $this->actingAs($user)->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/openapi.json']);

        $this->assertSame(2, $user->tickets()->count());
        $this->assertSame('Sudah ada', $user->tickets()->firstWhere('api_url', 'https://api.example.com/v1/products')->title);
    }

    public function test_swagger_2_documents_are_supported(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(json_encode([
            'swagger' => '2.0',
            'host' => 'legacy.example.com',
            'basePath' => '/api',
            'schemes' => ['https'],
            'paths' => ['/users' => ['get' => ['operationId' => 'listUsers']]],
        ]), 200)]);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/swagger.json']);

        $this->assertSame('https://legacy.example.com/api/users', $user->tickets()->first()->api_url);
    }

    public function test_yaml_documents_are_supported(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response("openapi: 3.0.0\nservers:\n  - url: https://yaml.example.com\npaths:\n  /status:\n    get:\n      summary: Cek status\n", 200)]);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/openapi.yaml']);

        $this->assertSame('https://yaml.example.com/status', $user->tickets()->first()->api_url);
    }

    public function test_a_relative_server_url_resolves_against_the_document(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(json_encode([
            'openapi' => '3.0.0',
            'servers' => [['url' => '/v2']],
            'paths' => ['/ping' => ['get' => []]],
        ]), 200)]);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/docs/openapi.json']);

        $this->assertSame('https://8.8.8.8/v2/ping', $user->tickets()->first()->api_url);
    }

    public function test_the_number_of_endpoints_is_capped(): void
    {
        Queue::fake();
        config(['scanner.import_limit' => 3]);

        $paths = [];
        foreach (range(1, 10) as $i) {
            $paths["/endpoint{$i}"] = ['get' => []];
        }

        Http::fake(['*' => Http::response($this->openApiSpec($paths), 200)]);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/openapi.json']);

        $this->assertSame(3, $user->tickets()->count());
    }

    public function test_internal_spec_urls_are_refused(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post(route('tickets.import'), ['spec_url' => 'http://127.0.0.1/openapi.json'])
            ->assertSessionHasErrors('spec_url');

        Http::assertNothingSent();
    }

    public function test_a_document_without_get_endpoints_is_reported(): void
    {
        Http::fake(['*' => Http::response($this->openApiSpec(['/orders' => ['post' => []]]), 200)]);

        $this->actingAs(User::factory()->create())
            ->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/openapi.json'])
            ->assertSessionHasErrors('spec_url');
    }

    public function test_a_broken_document_is_reported(): void
    {
        Http::fake(['*' => Http::response('bukan json maupun yaml: { [', 200)]);

        $this->actingAs(User::factory()->create())
            ->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/openapi.json'])
            ->assertSessionHasErrors('spec_url');
    }

    public function test_imported_endpoints_belong_to_the_importing_user(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response($this->openApiSpec(), 200)]);

        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('tickets.import'), ['spec_url' => 'https://8.8.8.8/openapi.json']);

        $this->assertSame(2, $user->tickets()->count());
        $this->assertSame(0, $other->tickets()->count());
    }
}
