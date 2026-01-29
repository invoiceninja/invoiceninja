@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Accounting Reports</h1>

    <ul class="nav nav-tabs" id="acctTabs">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#trial">Trial Balance</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#balance">Balance Sheet</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#income">Income Statement</a>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <div class="tab-pane fade show active" id="trial">
            <pre id="trialData">Loading…</pre>
        </div>
        <div class="tab-pane fade" id="balance">
            <pre id="balanceData">Loading…</pre>
        </div>
        <div class="tab-pane fade" id="income">
            <pre id="incomeData">Loading…</pre>
        </div>
    </div>
</div>

<script>
    async function loadReport(id, url) {
        const res = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        document.getElementById(id).textContent =
            JSON.stringify(await res.json(), null, 2);
    }

    loadReport('trialData', '/api/v1/reports/trial_balance');
    loadReport('balanceData', '/api/v1/reports/balance_sheet');
    loadReport('incomeData', '/api/v1/reports/income_statement');
</script>
@endsection