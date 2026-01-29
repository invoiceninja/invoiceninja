<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    /**
     * List chart of accounts for a company
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $companyId = auth()->user()->companyId();

        return ChartOfAccount::where('company_id', $companyId)
            ->orderBy('code')
            ->get();
    }

    /**
     * Store a new account
     */
    public function store(Request $request)
    {
        $this->authorize('create', ChartOfAccount::class);

        $data = $request->validate([
            'name'      => 'required|string|max:191',
            'code'      => 'nullable|string|max:50',
            'type'      => 'required|in:asset,liability,equity,income,expense',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
        ]);

        $data['company_id'] = auth()->user()->companyId();

        return ChartOfAccount::create($data);
    }

    /**
     * Update an account
     */
    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        $this->authorizeCompany($chartOfAccount);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:191',
            'code'      => 'nullable|string|max:50',
            'type'      => 'sometimes|in:asset,liability,equity,income,expense',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active' => 'boolean',
        ]);

        $chartOfAccount->update($data);

        return $chartOfAccount;
    }

    /**
     * Soft-disable (not delete)
     */
    public function destroy(Request $request, ChartOfAccount $chartOfAccount)
    {
        $this->authorizeCompany($chartOfAccount);

        $chartOfAccount->update(['is_active' => false]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Ensure record belongs to current company
     */
    protected function authorizeCompany(ChartOfAccount $chartOfAccount)
    {
        if ($chartOfAccount->company_id !== auth()->user()->companyId()) {
            abort(403);
        }
    }
}
