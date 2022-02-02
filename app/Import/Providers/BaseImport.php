<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
namespace App\Import\Providers;

use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Import\ImportException;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Utils\Traits\CleanLineItems;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;
use Symfony\Component\HttpFoundation\ParameterBag;

class BaseImport
{
	use CleanLineItems;

	public Company $company;

	public array $request;

	public array $error_array = [];

	public $request_name;

	public $repository_name;

	public $factory_name;

	public $repository;

	public $transformer;

	public function __construct(array $request, Company $company)
	{
		$this->company = $company;
		$this->request = $request;
		$this->hash = $request['hash'];
		$this->import_type = $request['import_type'];
		$this->skip_header = $request['skip_header'] ?? null;
		$this->column_map = !empty($request['column_map'])
			? array_combine(
				array_keys($request['column_map']),
				array_column($request['column_map'], 'mapping')
			)
			: null;

		auth()->login($this->company->owner(), true);

		auth()
			->user()
			->setCompany($this->company);
	}

	protected function getCsvData($entity_type)
	{
		$base64_encoded_csv = Cache::pull($this->hash . '-' . $entity_type);
		if (empty($base64_encoded_csv)) {
			return null;
		}

		$csv = base64_decode($base64_encoded_csv);
		$csv = Reader::createFromString($csv);

		$stmt = new Statement();
		$data = iterator_to_array($stmt->process($csv));

		if (count($data) > 0) {
			$headers = $data[0];

			// Remove Invoice Ninja headers
			if (
				count($headers) &&
				count($data) > 4 &&
				$this->import_type === 'csv'
			) {
				$first_cell = $headers[0];
				if (strstr($first_cell, config('ninja.app_name'))) {
					array_shift($data); // Invoice Ninja...
					array_shift($data); // <blank line>
					array_shift($data); // Enitty Type Header
				}
			}
		}

		return $data;
	}

	public function mapCSVHeaderToKeys($csvData)
	{
		$keys = array_shift($csvData);

		return array_map(function ($values) use ($keys) {
			return array_combine($keys, $values);
		}, $csvData);
	}

	private function groupInvoices($csvData, $key)
	{
		// Group by invoice.
		$grouped = [];

		foreach ($csvData as $line_item) {
			if (empty($line_item[$key])) {
				$this->error_array['invoice'][] = [
					'invoice' => $line_item,
					'error' => 'No invoice number',
				];
			} else {
				$grouped[$line_item[$key]][] = $line_item;
			}
		}

		return $grouped;
	}

	public function getErrors()
	{
		return $this->error_array;
	}

	public function ingest($data, $entity_type)
	{
		$count = 0;

		foreach ($data as $record) {
			try {
				$entity = $this->transformer->transform($record);

				/** @var \App\Http\Requests\Request $request */
				$request = new $this->request_name();

				// Pass entity data to request so it can be validated
				$request->query = $request->request = new ParameterBag($entity);
				$validator = Validator::make($entity, $request->rules());

				if ($validator->fails()) {
					$this->error_array[$entity_type][] = [
						$entity_type => $record,
						'error' => $validator->errors()->all(),
					];
				} else {
					$entity = $this->repository->save(
						array_diff_key($entity, ['user_id' => false]),
						$this->factory_name::create(
							$this->company->id,
							$this->getUserIDForRecord($entity)
						)
					);

					$entity->saveQuietly();
					$count++;
				}
			} catch (\Exception $ex) {
				if ($ex instanceof ImportException) {
					$message = $ex->getMessage();
				} else {
					report($ex);
					$message = 'Unknown error';
				}

				$this->error_array[$entity_type][] = [
					$entity_type => $record,
					'error' => $message,
				];
			}

			return $count;
		}
	}

	public function ingestInvoices($invoices, $invoice_number_key)
	{
		$invoice_transformer = $this->transformer;

		/** @var PaymentRepository $payment_repository */
		$payment_repository = app()->make(PaymentRepository::class);
		$payment_repository->import_mode = true;

		/** @var ClientRepository $client_repository */
		$client_repository = app()->make(ClientRepository::class);
		$client_repository->import_mode = true;

		$invoice_repository = new InvoiceRepository();
		$invoice_repository->import_mode = true;

		$invoices = $this->groupInvoices($invoices, $invoice_number_key);

		foreach ($invoices as $raw_invoice) {
			try {
				$invoice_data = $invoice_transformer->transform($raw_invoice);
				$invoice_data['line_items'] = $this->cleanItems(
					$invoice_data['line_items'] ?? []
				);

				// If we don't have a client ID, but we do have client data, go ahead and create the client.
				if (
					empty($invoice_data['client_id']) &&
					!empty($invoice_data['client'])
				) {
					$client_data = $invoice_data['client'];
					$client_data['user_id'] = $this->getUserIDForRecord(
						$invoice_data
					);

					$client_repository->save(
						$client_data,
						$client = ClientFactory::create(
							$this->company->id,
							$client_data['user_id']
						)
					);
					$invoice_data['client_id'] = $client->id;
					unset($invoice_data['client']);
				}

				$validator = Validator::make(
					$invoice_data,
					(new StoreInvoiceRequest())->rules()
				);
				if ($validator->fails()) {
					$this->error_array['invoice'][] = [
						'invoice' => $invoice_data,
						'error' => $validator->errors()->all(),
					];
				} else {
					$invoice = InvoiceFactory::create(
						$this->company->id,
						$this->getUserIDForRecord($invoice_data)
					);
					if (!empty($invoice_data['status_id'])) {
						$invoice->status_id = $invoice_data['status_id'];
					}
					$invoice_repository->save($invoice_data, $invoice);

					// If we're doing a generic CSV import, only import payment data if we're not importing a payment CSV.
					// If we're doing a platform-specific import, trust the platform to only return payment info if there's not a separate payment CSV.
					if (
						$this->import_type !== 'csv' ||
						empty($this->column_map['payment'])
					) {
						// Check for payment columns
						if (!empty($invoice_data['payments'])) {
							foreach (
								$invoice_data['payments']
								as $payment_data
							) {
								$payment_data['user_id'] = $invoice->user_id;
								$payment_data['client_id'] =
									$invoice->client_id;
								$payment_data['invoices'] = [
									[
										'invoice_id' => $invoice->id,
										'amount' =>
											$payment_data['amount'] ?? null,
									],
								];

								/* Make sure we don't apply any payments to invoices with a Zero Amount*/
								if ($invoice->amount > 0) {
									$payment_repository->save(
										$payment_data,
										PaymentFactory::create(
											$this->company->id,
											$invoice->user_id,
											$invoice->client_id
										)
									);
								}
							}
						}
					}

					$this->actionInvoiceStatus(
						$invoice,
						$invoice_data,
						$invoice_repository
					);
				}
			} catch (\Exception $ex) {
				if ($ex instanceof ImportException) {
					$message = $ex->getMessage();
				} else {
					report($ex);
					$message = 'Unknown error';
				}

				$this->error_array['invoice'][] = [
					'invoice' => $raw_invoice,
					'error' => $message,
				];
			}
		}
	}

	private function actionInvoiceStatus(
		$invoice,
		$invoice_data,
		$invoice_repository
	) {
		if (!empty($invoice_data['archived'])) {
			$invoice_repository->archive($invoice);
			$invoice->fresh();
		}

		if (!empty($invoice_data['viewed'])) {
			$invoice = $invoice
				->service()
				->markViewed()
				->save();
		}

		if ($invoice->status_id === Invoice::STATUS_DRAFT) {
		} elseif ($invoice->status_id === Invoice::STATUS_SENT) {
			$invoice = $invoice
				->service()
				->markSent()
				->save();
		} elseif (
			$invoice->status_id <= Invoice::STATUS_SENT &&
			$invoice->amount > 0
		) {
			if ($invoice->balance <= 0) {
				$invoice->status_id = Invoice::STATUS_PAID;
				$invoice->save();
			} elseif ($invoice->balance != $invoice->amount) {
				$invoice->status_id = Invoice::STATUS_PARTIAL;
				$invoice->save();
			}
		}

		return $invoice;
	}

	protected function getUserIDForRecord($record)
	{
		if (!empty($record['user_id'])) {
			return $this->findUser($record['user_id']);
		} else {
			return $this->company->owner()->id;
		}
	}

	protected function findUser($user_hash)
	{
		$user = User::where('account_id', $this->company->account->id)
			->where(
				\DB::raw('CONCAT_WS(" ", first_name, last_name)'),
				'like',
				'%' . $user_hash . '%'
			)
			->first();

		if ($user) {
			return $user->id;
		} else {
			return $this->company->owner()->id;
		}
	}
}
