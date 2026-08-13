<?php

namespace App\Models\Contracts;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A CRM record that carries an activity timeline (contact, company, lead, deal).
 */
interface HasActivities
{
    /**
     * @return MorphMany<Activity, covariant \Illuminate\Database\Eloquent\Model&HasActivities>
     */
    public function activities(): MorphMany;
}
