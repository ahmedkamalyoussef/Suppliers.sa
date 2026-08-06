<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SubscriptionService;

class SendRenewalReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send renewal reminders to users with subscriptions expiring in 2 days';

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
        $this->info('Checking for subscriptions expiring in 2 days...');

        $count = $this->subscriptionService->sendRenewalReminders();

        if ($count > 0) {
            $this->info("{$count} renewal reminder(s) sent successfully.");
        } else {
            $this->info('No subscriptions expiring in 2 days found.');
        }

        return Command::SUCCESS;
    }
}
