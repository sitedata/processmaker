<?php

namespace ProcessMaker\Traits;
use Illuminate\Support\Str;
use ProcessMaker\Models\ProcessRequest;
use ProcessMaker\Models\Process;
use ProcessMaker\Models\ProcessRequestToken;
use ProcessMaker\Models\User;

trait HideSystemResources
{
    public function scopeSystem($query)
    {
        if (substr(static::class, -8) === 'Category') {
            return $query->where('is_system', true);
        } else {
            return $query->whereHas('categories', function ($query) {
                $query->where('is_system', true);
            });
        }
    }

    public function scopeNonSystem($query)
    {
        if (substr(static::class, -8) === 'Category') {
            return $query->where('is_system', false);
        } else if (static::class == ProcessRequest::class) {
            // ProcessRequests must be filtered this way since
            // they could be in a separate database
            $systemProcessIds = Process::system()->pluck('id');
            $query->whereNotIn('process_id', $systemProcessIds);
        } else if (static::class == User::class) {
            return $query->where('is_system', false);
        } else if (static::class === ProcessRequestToken::class) {
            return $query->whereHas('process.categories', function ($query) {
                $query->where('is_system', false);
            });
        } else {            
            return $query->whereDoesntHave('categories', function ($query) {
                $query->where('is_system', true);
            });
        }
    }
}
