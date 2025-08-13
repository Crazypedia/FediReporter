<?php

namespace FediversePlugin;

use Plugin;
use Signal;
use ThreadEntry;
use Ticket;

/**
 * FediversePlugin hooks into osTicket events to manage moderation sync.
 */
class FediversePlugin extends Plugin
{
    /**
     * Plugin version number
     */
    public $version = '0.04';
{
    public function bootstrap()
    {
        // Hook into ticket closure event
        Signal::connect('ticket.closed', [$this, 'onTicketClosed']);

        // Hook into new thread entries (e.g., agent notes)
        Signal::connect('threadentry.created', [$this, 'onThreadEntryCreated']);
    }

    /**
     * Called when a new thread entry is created.
     * Pushes internal notes to remote server as moderation comments.
     *
     * @param ThreadEntry $entry
     */
    public function onThreadEntryCreated(ThreadEntry $entry)
    {
        if (!$entry->isInternal() || $entry->getType() !== 'note') {
            return; // Only sync internal notes
        }

        $ticket = $entry->getTicket();
        if (!$ticket instanceof Ticket) {
            return;
        }

        $agent = $entry->getPoster();
        $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';

        ModerationSync::pushTicketNote($ticket, $entry->getBody(), $agent, $domain);
    }

    /**
     * Handles ticket closure event and triggers moderation sync.
     *
     * @param \Ticket $ticket
     */
    public function onTicketClosed(\Ticket $ticket)
    {
        \FediversePlugin\ModerationSync::applyModerationOnClose($ticket);
    }
}
