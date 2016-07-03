<?php namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;

/**
 * Class PruneData
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

        // delete accounts who never registered, didn't create any invoices,
        // hansn't logged in within the past 6 months and isn't linked to another account
        $sql = 'select a.id
                from (select id, last_login from accounts) a
                left join users u on u.account_id = a.id and u.public_id = 0
                left join invoices i on i.account_id = a.id
                left join user_accounts ua1 on ua1.user_id1 = u.id
                left join user_accounts ua2 on ua2.user_id2 = u.id
                left join user_accounts ua3 on ua3.user_id3 = u.id
                left join user_accounts ua4 on ua4.user_id4 = u.id
                left join user_accounts ua5 on ua5.user_id5 = u.id
                where u.registered = 0
                and a.last_login < DATE_SUB(now(), INTERVAL 6 MONTH)
                and (ua1.id is null and ua2.id is null and ua3.id is null and ua4.id is null and ua5.id is null)
                group by a.id
                having count(i.id) = 0';

        $results = DB::select($sql);
        
        foreach ($results as $result) {
            $this->info("Deleting {$result->id}");        
            DB::table('accounts')
                ->where('id', '=', $result->id)
                ->delete();
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
        return [];
    }
}
