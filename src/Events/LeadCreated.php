<?php

namespace Meta\AdminCore\Events;

use Meta\AdminCore\Models\Lead;

/**
 * Fired when a new Lead row is inserted. Consumers listen to send
 * admin notifications, push to CRM, ping Telegram, etc. without
 * binding the package to any specific notification class.
 */
class LeadCreated
{
    public function __construct(public readonly Lead $lead) {}
}
