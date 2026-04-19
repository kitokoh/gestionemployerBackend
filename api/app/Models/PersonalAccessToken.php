<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The connection name for the model.
     *
     * Forces tokens to be stored in the central 'platform' database/schema.
     *
     * @var string|null
     */
    protected $connection = 'platform';
}
