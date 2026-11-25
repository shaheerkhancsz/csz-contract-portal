<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\ContractWorkflow;
use Illuminate\Console\Command;

class RefreshContractStatuses extends Command
{
    protected $signature = 'contracts:refresh-statuses';
    protected $description = 'Move signed contracts to Active / Expiring / Expired based on their dates';

    public function handle(ContractWorkflow $workflow): int
    {
        $n = 0;
        Contract::whereIn('status', [ContractStatus::Signed, ContractStatus::Active, ContractStatus::Expiring])
            ->each(function (Contract $c) use ($workflow, &$n) {
                $before = $c->status;
                $workflow->refreshStatus($c);
                $n += $c->fresh()->status !== $before ? 1 : 0;
            });
        $this->info("$n contract(s) changed status.");

        return self::SUCCESS;
    }
}
