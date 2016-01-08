<?php namespace App\Ninja\Repositories;

use DB;
use Auth;
use Utils;
use Request;
use App\Models\EntityModel;
use App\Models\ExpenseActivity;
use App\Models\Expense;

class ExpenseActivityRepository
{
    // Expenses
    public function create(Expense $entity, $activityTypeId)
    {
        // init activity and copy over context
        $activity = self::getBlank($entity);
        
        // Fill with our information
        $activity->vendor_id        = $entity->vendor_id;
        $activity->contact_id       = $entity->contact_id;
        $activity->activity_type_id = $activityTypeId;
        $activity->message          = $activity->getMessage();
        $activity->expense_id       = $entity->id;
        $activity->save();

        return $activity;
    }

    private function getBlank($entity)
    {
        $activity = new ExpenseActivity();

        if (Auth::check() && Auth::user()->account_id == $entity->account_id) {
            $activity->user_id = Auth::user()->id;
            $activity->account_id = Auth::user()->account_id;
        } else {
            $activity->user_id = $entity->user_id;
            $activity->account_id = $entity->account_id;
        }
        
        $activity->token_id = session('token_id');
        $activity->ip       = Request::getClientIp();
        
        return $activity;
    }
    
    
    public function findByExpenseId($expenseId)
    {
        return DB::table('expense_activities')
                    ->join('accounts', 'accounts.id', '=', 'expense_activities.account_id')
                    ->join('users', 'users.id', '=', 'expense_activities.user_id')
                    ->join('expenses','expenses.public_id', '=', 'expense_activities.expense_id')
                    ->where('expense_activities.expense_id', '=', $expenseId)
                    ->select('*',
                        'users.first_name as user_first_name',
                        'users.last_name as user_last_name',
                        'users.email as user_email',
                        'expenses.amount'
                             );

    }
}