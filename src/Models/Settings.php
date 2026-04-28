<?php

namespace LaravelEnso\MeiliSearch\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use LaravelEnso\MeiliSearch\Database\Factories\SettingsFactory;

class Settings extends Model
{
    use HasFactory;

    protected $table = 'meilisearch_settings';

    protected $guarded = ['id'];

    protected array $rememberableKeys = ['id'];

    private static ?self $instance = null;

    public static function current(): self
    {
        $id = Config::get('enso.meilisearch.settingsId');

        return self::$instance
            ??= self::find($id)
            ?? self::factory()->create(['id' => $id]);
    }

    public static function enabled()
    {
        return self::current()->enabled;
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    protected static function newFactory(): SettingsFactory
    {
        return SettingsFactory::new();
    }
}
