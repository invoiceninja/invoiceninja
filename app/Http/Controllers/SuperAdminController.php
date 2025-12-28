<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Class SuperAdminController.
 * 
 * Super Admin portal for managing all companies/tenants in the SaaS system
 */
class SuperAdminController extends BaseController
{
    use MakesHash;

    /**
     * Get all companies across all databases
     *
     * @return Response
     */
    public function companies(Request $request)
    {
        $per_page = $request->input('per_page', 15);
        $search = $request->input('search');
        
        $companies = collect();
        
        if (config('ninja.db.multi_db_enabled')) {
            // Search across all databases
            foreach (MultiDB::getDbs() as $db) {
                MultiDB::setDb($db);
                
                $query = Company::with(['account', 'users'])
                    ->orderBy('created_at', 'desc');
                
                if ($search) {
                    $query->where(function($q) use ($search) {
                        $q->where('settings', 'like', "%{$search}%")
                          ->orWhereHas('account', function($q) use ($search) {
                              $q->where('email', 'like', "%{$search}%");
                          });
                    });
                }
                
                $companies = $companies->merge($query->get());
            }
            
            // Reset to default DB
            MultiDB::setDb(config('database.default'));
        } else {
            // Single database
            $query = Company::with(['account', 'users'])
                ->orderBy('created_at', 'desc');
            
            if ($search) {
                $query->where('settings', 'like', "%{$search}%");
            }
            
            $companies = $query->get();
        }
        
        // Transform companies for response
        $transformed = $companies->map(function($company) {
            return [
                'id' => $company->hashed_id,
                'name' => $company->settings->name ?? 'N/A',
                'account_id' => $company->account->hashed_id ?? null,
                'account_email' => $company->account->users()->first()->email ?? 'N/A',
                'subdomain' => $company->subdomain,
                'db' => $company->db,
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
                'is_disabled' => $company->is_disabled ?? false,
                'user_count' => $company->users()->count(),
                'client_count' => $company->clients()->count(),
            ];
        });
        
        $page = (int) $request->input('page', 1);
        $paginated = $transformed->forPage($page, $per_page);
        $total = $transformed->count();
        $totalPages = (int) ceil($total / $per_page);
        
        return response()->json([
            'data' => $paginated->values(),
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => $paginated->count(),
                    'per_page' => $per_page,
                    'current_page' => $page,
                    'total_pages' => $totalPages > 0 ? $totalPages : 1,
                ]
            ]
        ]);
    }

    /**
     * Get a specific company
     *
     * @param string $id
     * @return Response
     */
    public function showCompany($id)
    {
        $company = $this->findCompanyAcrossDbs($id);
        
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        
        return response()->json([
            'data' => [
                'id' => $company->hashed_id,
                'name' => $company->settings->name ?? 'N/A',
                'account_id' => $company->account->hashed_id ?? null,
                'subdomain' => $company->subdomain,
                'db' => $company->db,
                'settings' => $company->settings,
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
                'is_disabled' => $company->is_disabled ?? false,
                'users' => $company->users()->get()->map(function($user) {
                    return [
                        'id' => $user->hashed_id,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                    ];
                }),
                'stats' => [
                    'clients' => $company->clients()->count(),
                    'invoices' => $company->invoices()->count(),
                    'quotes' => $company->quotes()->count(),
                    'payments' => $company->payments()->count(),
                ],
            ]
        ]);
    }

    /**
     * Disable/Enable a company
     *
     * @param string $id
     * @param Request $request
     * @return Response
     */
    public function toggleCompany($id, Request $request)
    {
        $company = $this->findCompanyAcrossDbs($id);
        
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        
        $company->is_disabled = $request->input('is_disabled', false);
        $company->save();
        
        return response()->json([
            'message' => $company->is_disabled ? 'Company disabled' : 'Company enabled',
            'data' => [
                'id' => $company->hashed_id,
                'is_disabled' => $company->is_disabled,
            ]
        ]);
    }

    /**
     * Get dashboard statistics
     *
     * @return Response
     */
    public function dashboard()
    {
        $stats = [
            'total_companies' => 0,
            'total_users' => 0,
            'total_clients' => 0,
            'total_invoices' => 0,
            'active_companies' => 0,
            'disabled_companies' => 0,
        ];
        
        if (config('ninja.db.multi_db_enabled')) {
            foreach (MultiDB::getDbs() as $db) {
                MultiDB::setDb($db);
                
                $stats['total_companies'] += Company::count();
                $stats['total_users'] += User::count();
                $stats['total_clients'] += DB::table('clients')->count();
                $stats['total_invoices'] += DB::table('invoices')->count();
                $stats['active_companies'] += Company::where('is_disabled', false)->count();
                $stats['disabled_companies'] += Company::where('is_disabled', true)->count();
            }
            
            MultiDB::setDb(config('database.default'));
        } else {
            $stats['total_companies'] = Company::count();
            $stats['total_users'] = User::count();
            $stats['total_clients'] = DB::table('clients')->count();
            $stats['total_invoices'] = DB::table('invoices')->count();
            $stats['active_companies'] = Company::where('is_disabled', false)->count();
            $stats['disabled_companies'] = Company::where('is_disabled', true)->count();
        }
        
        return response()->json(['data' => $stats]);
    }

    /**
     * Find company across all databases
     *
     * @param string $id
     * @return Company|null
     */
    private function findCompanyAcrossDbs($id)
    {
        $decoded_id = $this->decodePrimaryKey($id);
        
        if (config('ninja.db.multi_db_enabled')) {
            foreach (MultiDB::getDbs() as $db) {
                MultiDB::setDb($db);
                
                if ($company = Company::find($decoded_id)) {
                    return $company;
                }
            }
            
            MultiDB::setDb(config('database.default'));
        } else {
            return Company::find($decoded_id);
        }
        
        return null;
    }
}

