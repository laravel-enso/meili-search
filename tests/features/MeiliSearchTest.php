<?php

require_once __DIR__.'/../../src/AppServiceProvider.php';
require_once __DIR__.'/../../src/Models/Settings.php';
require_once __DIR__.'/../../database/factories/SettingsFactory.php';
require_once __DIR__.'/../../src/Forms/Builders/Settings.php';
require_once __DIR__.'/../../src/Http/Requests/ValidateSettings.php';
require_once __DIR__.'/../../src/Http/Controllers/Settings/Index.php';
require_once __DIR__.'/../../src/Http/Controllers/Settings/Update.php';
require_once __DIR__.'/../../src/Services/MeiliSearch.php';
require_once __DIR__.'/../../src/Console/Index.php';
require_once __DIR__.'/../../src/Console/Delete.php';
require_once __DIR__.'/../../src/Console/Flush.php';
require_once __DIR__.'/../../src/Console/Import.php';

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Searchable;
use LaravelEnso\MeiliSearch\Console\Delete;
use LaravelEnso\MeiliSearch\Console\Flush;
use LaravelEnso\MeiliSearch\Console\Import;
use LaravelEnso\MeiliSearch\Console\Index;
use LaravelEnso\MeiliSearch\Http\Controllers\Settings\Index as SettingsIndex;
use LaravelEnso\MeiliSearch\Http\Controllers\Settings\Update as SettingsUpdate;
use LaravelEnso\MeiliSearch\Models\Settings;
use LaravelEnso\MeiliSearch\Services\MeiliSearch;
use LaravelEnso\Users\Models\User;
use MeiliSearch\Client;
use MeiliSearch\Endpoints\Indexes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeiliSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed()
            ->actingAs(User::first());

        $this->createSettingsTable();
        $this->resetSettingsInstance();
        $this->loadRoutes();
    }

    protected function tearDown(): void
    {
        $this->resetSettingsInstance();

        \Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function index_creates_settings_when_missing(): void
    {
        $this->assertNull(Settings::query()->find(1));

        $this->get('/_test/integrations/meilisearch/settings')
            ->assertStatus(200)
            ->assertJsonStructure(['form']);

        $this->assertNotNull(Settings::query()->find(1));
    }

    #[Test]
    public function can_update_settings(): void
    {
        $settings = Settings::factory()->create(['enabled' => false]);

        $this->patch("/_test/integrations/meilisearch/settings/{$settings->getKey()}", [
            'enabled' => true,
        ])->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settings were stored sucessfully']);

        $this->assertTrue($settings->fresh()->enabled);
    }

    #[Test]
    public function rejects_invalid_settings_payload(): void
    {
        $settings = Settings::factory()->create();

        $this->patch("/_test/integrations/meilisearch/settings/{$settings->getKey()}", [
            'enabled' => 'invalid',
        ])->assertStatus(302)
            ->assertSessionHasErrors(['enabled']);
    }

    #[Test]
    public function current_creates_settings_when_missing(): void
    {
        $this->assertNull(Settings::query()->find(1));

        $settings = Settings::current();

        $this->assertSame(1, $settings->id);
        $this->assertDatabaseHas('meilisearch_settings', ['id' => 1]);
    }

    #[Test]
    public function enabled_uses_the_cached_current_settings_record(): void
    {
        Settings::factory()->create(['enabled' => true]);

        $this->assertTrue(Settings::enabled());
    }

    #[Test]
    public function meili_search_service_uses_the_model_searchable_name_for_all_operations(): void
    {
        $index = \Mockery::mock(Indexes::class);
        $client = \Mockery::mock(Client::class);

        $client->shouldReceive('index')
            ->once()
            ->with('test-products')
            ->andReturn($index);

        $client->shouldReceive('createIndex')
            ->once()
            ->with('test-products')
            ->andReturn($index);

        $client->shouldReceive('deleteIndex')
            ->once()
            ->with('test-products')
            ->andReturn(['taskUid' => 1]);

        $this->app->instance(Client::class, $client);

        $this->assertSame($index, MeiliSearch::index(MeiliSearchTestSearchableModel::class));
        $this->assertSame($index, MeiliSearch::createIndex(MeiliSearchTestSearchableModel::class));
        $this->assertSame(['taskUid' => 1], MeiliSearch::deleteIndex(MeiliSearchTestSearchableModel::class));
    }

    #[Test]
    public function index_command_creates_the_index_and_updates_model_attributes(): void
    {
        $index = \Mockery::mock(Indexes::class);
        $client = \Mockery::mock(Client::class);

        $client->shouldReceive('createIndex')
            ->once()
            ->with('test-products')
            ->andReturn($index);

        $index->shouldReceive('updateFilterableAttributes')
            ->once()
            ->with(['brand', 'category']);

        $index->shouldReceive('updateSortableAttributes')
            ->once()
            ->with(['price', 'created_at']);

        $index->shouldReceive('updateSearchableAttributes')
            ->once()
            ->with(['name', 'sku']);

        $this->app->instance(Client::class, $client);

        $command = \Mockery::mock(Index::class)->makePartial();
        $command->shouldReceive('argument')
            ->once()
            ->with('model')
            ->andReturn(MeiliSearchTestSearchableModel::class);
        $command->shouldReceive('info')
            ->once()
            ->with('Index for ['.MeiliSearchTestSearchableModel::class.'] created.');

        $command->handle();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function delete_command_deletes_the_index(): void
    {
        $client = \Mockery::mock(Client::class);

        $client->shouldReceive('deleteIndex')
            ->once()
            ->with('test-products')
            ->andReturn(['taskUid' => 1]);

        $this->app->instance(Client::class, $client);

        $command = \Mockery::mock(Delete::class)->makePartial();
        $command->shouldReceive('argument')
            ->once()
            ->with('model')
            ->andReturn(MeiliSearchTestSearchableModel::class);
        $command->shouldReceive('info')
            ->once()
            ->with('Index for ['.MeiliSearchTestSearchableModel::class.'] deleted.');

        $command->handle();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function flush_command_flushes_the_model_index(): void
    {
        MeiliSearchTestSearchableModel::$flushed = false;

        $command = \Mockery::mock(Flush::class)->makePartial();
        $command->shouldReceive('argument')
            ->once()
            ->with('model')
            ->andReturn(MeiliSearchTestSearchableModel::class);
        $command->shouldReceive('info')
            ->once()
            ->with('All ['.MeiliSearchTestSearchableModel::class.'] records have been flushed.');

        $command->handle();

        $this->assertTrue(MeiliSearchTestSearchableModel::$flushed);
    }

    #[Test]
    public function import_command_delegates_to_scout_import_with_the_selected_chunk(): void
    {
        $command = \Mockery::mock(Import::class)->makePartial();
        $command->shouldReceive('argument')
            ->once()
            ->with('model')
            ->andReturn(MeiliSearchTestSearchableModel::class);
        $command->shouldReceive('option')
            ->once()
            ->with('chunk')
            ->andReturn(250);
        $command->shouldReceive('call')
            ->once()
            ->with('scout:import', [
                'model' => MeiliSearchTestSearchableModel::class,
                '--chunk' => 250,
            ]);

        $command->handle();

        $this->addToAssertionCount(1);
    }

    private function createSettingsTable(): void
    {
        if (! Schema::hasTable('meilisearch_settings')) {
            Schema::create('meilisearch_settings', static function ($table): void {
                $table->increments('id');
                $table->boolean('enabled');
                $table->timestamps();
            });
        }
    }

    private function loadRoutes(): void
    {
        $loaded = collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($route) => $route->uri() === '_test/integrations/meilisearch/settings');

        if (! $loaded) {
            Route::middleware(SubstituteBindings::class)
                ->get('/_test/integrations/meilisearch/settings', SettingsIndex::class);

            Route::middleware(SubstituteBindings::class)
                ->patch('/_test/integrations/meilisearch/settings/{settings}', SettingsUpdate::class);
        }
    }

    private function resetSettingsInstance(): void
    {
        $property = new ReflectionProperty(Settings::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null);
    }
}

class MeiliSearchTestSearchableModel
{
    use Searchable;

    public static bool $flushed = false;

    public function searchableAs(): string
    {
        return 'test-products';
    }

    public static function removeAllFromSearch(): void
    {
        self::$flushed = true;
    }

    public static function filterableAttributes(): array
    {
        return ['brand', 'category'];
    }

    public static function sortableAttributes(): array
    {
        return ['price', 'created_at'];
    }

    public static function searchableAttributes(): array
    {
        return ['name', 'sku'];
    }
}
