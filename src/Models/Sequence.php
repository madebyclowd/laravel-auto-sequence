<?php

namespace MadeByClowd\AutoSequence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sequence extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'module',
        'type_code',
        'period',
        'scope',
        'current_number',
        'format_template',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'current_number' => 'integer',
    ];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Define composite primary key fields.
     *
     * @var array<string>
     */
    protected array $compositeKeys = ['module', 'type_code', 'period', 'scope'];

    /**
     * Get the table associated with the model dynamically.
     */
    public function getTable(): string
    {
        return config('auto-sequence.table', 'sequences');
    }

    /**
     * Get the database connection name dynamically.
     */
    public function getConnectionName(): ?string
    {
        return config('auto-sequence.connection') ?? parent::getConnectionName();
    }

    /**
     * Override getKey for composite primary key.
     *
     * @return array<string, mixed>
     */
    public function getKey(): array
    {
        $keys = [];
        foreach ($this->compositeKeys as $key) {
            $keys[$key] = $this->getAttribute($key);
        }

        return $keys;
    }

    /**
     * Override setKeysForSaveQuery to handle composite keys.
     *
     * @param  Builder  $query
     * @return Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        foreach ($this->compositeKeys as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        return $query;
    }

    /**
     * Override getKeyName to return the first key (Laravel expects a string).
     */
    public function getKeyName(): string
    {
        return $this->compositeKeys[0];
    }

    /**
     * Relationship to the user who created the sequence (optional, based on config).
     */
    public function creator(): ?BelongsTo
    {
        if (! config('auto-sequence.audit.enabled', false)) {
            return null;
        }

        $userModel = config('auto-sequence.audit.user_model', 'App\Models\User');
        $createdByColumn = config('auto-sequence.audit.created_by_column', 'created_by');

        return $this->belongsTo($userModel, $createdByColumn);
    }

    /**
     * Relationship to the user who last updated the sequence (optional, based on config).
     */
    public function updater(): ?BelongsTo
    {
        if (! config('auto-sequence.audit.enabled', false)) {
            return null;
        }

        $userModel = config('auto-sequence.audit.user_model', 'App\Models\User');
        $updatedByColumn = config('auto-sequence.audit.updated_by_column', 'updated_by');

        return $this->belongsTo($userModel, $updatedByColumn);
    }
}
