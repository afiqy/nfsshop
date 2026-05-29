<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {days=90}';
    protected $description = 'Delete audit logs older than the configured retention period.';

    public function handle()
    {
        $days = $this->argument('days');
        $count = AuditLog::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Pruned {$count} audit log entries older than {$days} days.");
    }
}