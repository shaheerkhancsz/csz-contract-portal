<?php

namespace App\Filament\Widgets;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $live = [ContractStatus::Active, ContractStatus::Expiring, ContractStatus::Signed];
        $url = fn (array $statuses) => ContractResource::getUrl('index', ['filters' => ['status' => ['values' => array_map(fn ($s) => $s->value, $statuses)]]]);
        $monthly = Contract::whereIn('status', $live)->get()->sum(fn (Contract $c) => match ($c->billing_cycle) {
            BillingCycle::Monthly => (float) $c->fee,
            BillingCycle::Quarterly => (float) $c->fee / 3,
            BillingCycle::Annual => (float) $c->fee / 12,
            default => 0,
        });

        return [
            Stat::make('Active contracts', Contract::whereIn('status', $live)->count())->url($url($live)),
            Stat::make('Expiring in 30 days', Contract::whereIn('status', $live)->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])->count())
                ->description('Plus '.Contract::whereIn('status', $live)->whereBetween('end_date', [now()->addDays(31)->toDateString(), now()->addDays(60)->toDateString()])->count().' in 31–60 days')
                ->color('warning')->url($url([ContractStatus::Expiring])),
            Stat::make('Awaiting approval', Contract::where('status', ContractStatus::AwaitingApproval)->count())->url($url([ContractStatus::AwaitingApproval])),
            Stat::make('Sent, not yet signed', Contract::where('status', ContractStatus::Sent)->count())->url($url([ContractStatus::Sent])),
            Stat::make('Monthly revenue under contract', 'PKR '.number_format($monthly))->description('Before tax; annual and quarterly fees spread per month'),
        ];
    }
}
