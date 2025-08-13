<?php

namespace FediversePlugin;

use FediversePlugin\InstanceManager;
use FediversePlugin\API\APIException;

/**
 * PollingHandler is a placeholder for scheduled report syncing.
 * It polls all configured instances for new/unresolved abuse reports
 * and forwards them to the ticket importer logic.
 */
class PollingHandler
{
    /**
     * Entry point for cron-based polling task.
     * Typically hooked into osTicket's TaskScheduler.
     */
    public function pollAllInstances(): void
    {
        $instances = InstanceManager::listInstances();

        foreach ($instances as $instance) {
            try {
                $client = InstanceManager::getClient(
                    $instance['domain'],
                    $instance['token'],
                    $instance['platform']
                );

                // Fetch unresolved reports from the instance
                $reports = $client->fetchReports(['resolved' => 'false']);

                foreach ($reports as $report) {
                    \FediversePlugin\TicketMapper::importReport($report, \$client);
                    // TicketMapper::importReport($report, $client);
                }
            } catch (APIException $e) {
                // Optionally log or notify about failed instance sync
                error_log("Polling failed for {$instance['domain']}: " . $e->getMessage());
            }
        }
    }
}
