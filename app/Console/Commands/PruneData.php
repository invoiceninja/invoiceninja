<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class PruneData.
 */
class PruneData extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:prune-data';

    /**
     * @var string
     */
    protected $description = 'Delete inactive accounts';

    public function fire()
    {
        $this->info(date('Y-m-d').' Running PruneData...');

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        // delete accounts who never registered, didn't create any invoices,
        // hansn't logged in within the past 6 months and isn't linked to another account
        $sql = 'select c.id
                from companies c
                left join accounts a on a.company_id = c.id
                left join clients cl on cl.account_id = a.id
                left join tasks t on t.account_id = a.id
                left join expenses e on e.account_id = a.id
                left join users u on u.account_id = a.id and u.registered = 1
                where c.created_at < DATE_SUB(now(), INTERVAL 6 MONTH)
                and c.trial_started is null
                and c.plan is null
                group by c.id
                having count(cl.id) = 0
                and count(t.id) = 0
                and count(e.id) = 0
                and count(u.id) = 0';

        $results = DB::select($sql);

        foreach ($results as $result) {
            $this->info("Deleting company: {$result->id}");
            try {
                DB::table('companies')
                    ->where('id', '=', $result->id)
                    ->delete();
            } catch (\Illuminate\Database\QueryException $e) {
                // most likely because a user_account record exists which doesn't cascade delete
                $this->info("Unable to delete companyId: {$result->id}");
            }
        }

        $this->info('Done');
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
