<?php

namespace FediversePlugin;

use Plugin;
use Signal;
use ThreadEntry;
use Ticket;

class FediversePlugin extends Plugin
{
    /**
     * Plugin version number
     */
    public $version = '0.08';

    public function __construct()
    {
        parent::__construct();
    }

    public function bootstrap()
    {
        Signal::connect('ticket.closed', [$this, 'onTicketClosed']);
        Signal::connect('threadentry.created', [$this, 'onThreadEntryCreated']);
    }

    public function onTicketClosed(Ticket $ticket)
    {
        ModerationSync::applyModerationOnClose($ticket);
    }

    public function onThreadEntryCreated(ThreadEntry $entry)
    {
        if (!$entry->isInternal() || $entry->getType() !== 'note') {
            return;
        }

        $ticket = $entry->getTicket();
        $agent = $entry->getPoster();
        $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';

        ModerationSync::pushTicketNote($ticket, $entry->getBody(), $agent, $domain);
    }
}
