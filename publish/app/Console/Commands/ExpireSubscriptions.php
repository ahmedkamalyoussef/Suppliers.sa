<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SubscriptionService;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and expire subscriptions that have ended, reverting users to Basic plan';

    private $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired subscriptions...');

        $count = $this->subscriptionService->updateExpiredSubscriptions();

        if ($count > 0) {
            $this->info("{$count} subscription(s) expired and reverted to Basic plan.");
        } else {
            $this->info('No expired subscriptions found.');
        }

        return Command::SUCCESS;
    }
}
