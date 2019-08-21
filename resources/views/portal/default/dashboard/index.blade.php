@extends('portal.default.layouts.master')

@section('body')
<main class="main">
    <div class="container-fluid">	
		<div class="row" style="margin-top: 30px;">
			<div class="col-6 col-lg-4">
				<div class="card">
					<div class="card-body p-3 d-flex align-items-center">
							<i class="fa fa-money bg-primary p-3 font-2xl mr-3"></i>
						<div>
							<div class="text-value-sm text-primary">$1.999,50</div>
							<div class="text-muted text-uppercase font-weight-bold small">{{ ctrans('texts.total_invoiced')}}</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-6 col-lg-4">
				<div class="card">
					<div class="card-body p-3 d-flex align-items-center">
							<i class="fa fa-hourglass-end bg-info p-3 font-2xl mr-3"></i>
						<div>
							<div class="text-value-sm text-info">$1.999,50</div>
							<div class="text-muted text-uppercase font-weight-bold small">{{ ctrans('texts.paid_to_date') }}</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-6 col-lg-4">
				<div class="card">
					<div class="card-body p-3 d-flex align-items-center">
							<i class="fa fa-exclamation-triangle bg-warning p-3 font-2xl mr-3"></i>
						<div>
							<div class="text-value-sm text-warning">$1.999,50</div>
							<div class="text-muted text-uppercase font-weight-bold small">{{ ctrans('texts.open_balance')}}</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- update payment methods -->
		<div class="row" style="margin-top: 30px;">

		</div>
    </div>
</main>
</body>
@endsection