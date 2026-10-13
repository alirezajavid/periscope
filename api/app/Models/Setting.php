<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Key-value store for links, fixed pages, terms and the maintenance switch. Values are JSON. */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function read(string $key): mixed
    {
        $value = static::whereKey($key)->value('value');

        return $value === null ? null : json_decode($value, true);
    }

    public static function write(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Every known setting with its stored value (null when never set).
     *
     * @return array<string, mixed>
     */
    public static function allKnown(): array
    {
        $stored = static::pluck('value', 'key');

        return collect(array_keys(config('periscope.settings')))
            ->mapWithKeys(fn (string $key) => [$key => isset($stored[$key]) ? json_decode($stored[$key], true) : null])
            ->all();
    }
}
