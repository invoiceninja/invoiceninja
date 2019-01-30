<?php

namespace App\Console\Commands;

use App\Jobs\SendOverdueTicketNotification;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class SendOverdueTickets.
 */
class SendOverdueTickets extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:send-overdue-tickets';

    /**
     * @var string
     */
    protected $description = 'Send overdue tickets';

    public function __construct()
    {

        parent::__construct();

    }

    public function fire()
    {
        $this->info(date('r') . ' Running SendOverdueTickets...');

        if ($database = $this->option('database'))
            config(['database.default' => $database]);

        $this->sendReminders();

        $this->info(date('r') . ' Done');
    }

    private function sendReminders()
    {

        $tickets = Ticket::with('account', 'account.account_ticket_settings')
            ->where('due_date', '<', Carbon::now())
            ->whereIn('status_id', [1,2])
            ->where('overdue_notification_sent', '=', 0)
            ->whereHas('account.account_ticket_settings', function ($query) {
                $query->where('alert_ticket_overdue_agent_id', '>', '0');
            })->get();


        foreach($tickets as $ticket)
            dispatch(new SendOverdueTicketNotification($ticket));

    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database', null],
        ];
    }
}
