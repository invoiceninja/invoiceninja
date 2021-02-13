<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Import;

use App\Factory\ClientFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\ProductFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Import\Transformers\BaseTransformer;
use App\Import\Transformers\ClientTransformer;
use App\Import\Transformers\InvoiceItemTransformer;
use App\Import\Transformers\InvoiceTransformer;
use App\Import\Transformers\PaymentTransformer;
use App\Import\Transformers\ProductTransformer;
use App\Jobs\Mail\MailRouter;
use App\Libraries\MultiDB;
use App\Mail\Import\ImportCompleted;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Utils\Traits\CleanLineItems;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;

class CSVImport implements ShouldQueue {
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CleanLineItems;

	public $invoice;

	public $company;

	public $hash;

	public $import_type;

	public $skip_header;

	public $column_map;

	public $import_array;

	public $error_array = [];

	public $maps;

	public function __construct( array $request, Company $company ) {
		$this->company = $company;

		$this->hash = $request['hash'];

		$this->import_type = $request['import_type'];

		$this->skip_header = $request['skip_header'] ?? null;

		$this->column_map = $request['column_map'] ?? null;
	}

	/**
	 * Execute the job.
	 *
	 *
	 * @return void
	 */
	public function handle() {

		MultiDB::setDb( $this->company->db );

		$this->company->owner()->setCompany( $this->company );
		Auth::login( $this->company->owner(), true );

		$this->buildMaps();

		//sort the array by key
		foreach ( $this->column_map as $entityType => &$map ) {
			ksort( $map );
		}

		nlog( "import" . ucfirst( $this->import_type ) );
		$this->{"import" . ucfirst( $this->import_type )}();

		$data = [
			'errors'   => $this->error_array,
			'company'=>$this->company,
		];


		MailRouter::dispatchNow( new ImportCompleted( $data ), $this->company, auth()->user() );
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////

	private function importCsv() {
		foreach ( [ 'client', 'product', 'invoice', 'payment', 'vendor', 'expense' ] as $entityType ) {
			if ( empty( $this->column_map[ $entityType ] ) ) {
				continue;
			}

			$csvData = $this->getCsvData( $entityType );

			if ( ! empty( $csvData ) ) {
				$importFunction = "import" . Str::plural( Str::title( $entityType ) );

				if ( method_exists( $this, $importFunction ) ) {
					// If there's an entity-specific import function, use that.
					$this->$importFunction( $csvData );
				} else {
					// Otherwise, use the generic import function.
					$this->importEntities( $csvData, $entityType );
				}
			}
		}
	}

	private function importInvoices( $records ) {
		$invoice_transformer = new InvoiceTransformer( $this->maps );

		if ( $this->skip_header ) {
			array_shift( $records );
		}

		$keys               = $this->column_map['invoice'];
		$invoice_number_key = array_search( 'invoice.number', $keys );
		if ( $invoice_number_key === false ) {
			nlog( "no invoice number to use as key - returning" );

			return;
		}

		$items_by_invoice = [];

		// Group line items by invoice and map columns to keys.
		foreach ( $records as $key => $value ) {
			$items_by_invoice[ $value[ $invoice_number_key ] ][] = array_combine( $keys,array_intersect_key(  $value , $keys ));
		}

		foreach ( $items_by_invoice as $invoice_number => $line_items ) {
			$invoice_data = array_combine( $keys, reset( $line_items ) );

			$invoice = $invoice_transformer->transform( $invoice_data );

			$this->processInvoice( $line_items, $invoice );
		}
	}

	private function processInvoice( $line_items, $invoice ) {
		$invoice_repository = new InvoiceRepository();
		$item_transformer   = new InvoiceItemTransformer( $this->maps );
		$items              = [];

		foreach ( $line_items as $record ) {
			$items[] = $item_transformer->transform( $record );
		}

		$invoice['line_items'] = $this->cleanItems( $items );

		$validator = Validator::make( $invoice, ( new StoreInvoiceRequest() )->rules() );

		if ( $validator->fails() ) {
			$this->error_array['invoice'][] = [ 'invoice' => $invoice, 'error' => $validator->errors()->all() ];
		} else {
			$invoice =
				$invoice_repository->save( $invoice, InvoiceFactory::create( $this->company->id, $this->getUserIDForRecord( $record ) ) );

			$this->addInvoiceToMaps( $invoice );

			// If there's no payment import, try importing payment data from the invoices CSV.
			if ( empty( $this->column_map['payment'] ) ) {
				$payment_data = reset( $line_items );
				// Check for payment columns
				if ( ! empty( $payment_data['payment.amount'] ) ) {
					// Transform the payment to be saved
					$payment_transformer = new PaymentTransformer( $this->maps );

					/** @var PaymentRepository $payment_repository */
					$payment_repository               = app()->make( PaymentRepository::class );
					$transformed_payment              = $payment_transformer->transform( $payment_data );
					$transformed_payment['user_id']   = $invoice->user_id;
					$transformed_payment['client_id'] = $invoice->client_id;
					$transformed_payment['invoices']  = [
						[
							'invoice_id' => $invoice->id,
							'amount'     => $transformed_payment['amount'],
						],
					];

					$payment_repository->save(
						$transformed_payment,
						PaymentFactory::create( $this->company->id, $invoice->user_id, $invoice->client_id )
					);
				}
			}

			$this->actionInvoiceStatus( $invoice, $record['invoice.status']??null, $invoice_repository );
		}
	}

	private function actionInvoiceStatus( $invoice, $status, $invoice_repository ) {
		switch ( $status ) {
			case 'Archived':
				$invoice_repository->archive( $invoice );
				$invoice->fresh();
				break;
			case 'Sent':
				$invoice = $invoice->service()->markSent()->save();
				break;
			case 'Viewed':
				$invoice = $invoice->service()->markSent()->save();
				break;
			default:
				# code...
				break;
		}

		if($invoice->status_id <= Invoice::STATUS_SENT){
			if ( $invoice->balance < $invoice->amount) {
				$invoice->status_id = Invoice::STATUS_PARTIAL;
				$invoice->save();
			} elseif($invoice->balance <=0){
				$invoice->status_id = Invoice::STATUS_PAID;
				$invoice->save();
			}
		}


		return $invoice;
	}

	private function importEntities( $records, $entity_type ) {
		$entity_type           = Str::slug( $entity_type, '_' );
		$formatted_entity_type = Str::title( $entity_type );

		$request          = "\\App\\Http\\Requests\\${formatted_entity_type}\\Store${formatted_entity_type}Request";
		$repository_name  = '\\App\\Repositories\\'.$formatted_entity_type . 'Repository';
		$transformer_name = '\\App\\Import\\Transformers\\'.$formatted_entity_type . 'Transformer';
		$factoryName      = '\\App\\Factory\\'.$formatted_entity_type . 'Factory';

		$repository  = app()->make($repository_name);
		$transformer = new $transformer_name( $this->maps );

		if ( $this->skip_header ) {
			array_shift( $records );
		}

		foreach ( $records as $record ) {
			$keys   = $this->column_map[ $entity_type ];
			$values = array_intersect_key( $record, $keys );

			$data = array_combine( $keys, $values );

			$entity = $transformer->transform( $data );

			$validator = Validator::make( $entity, ( new $request() )->rules() );

			if ( $validator->fails() ) {
				$this->error_array[ $entity_type ][] =
					[ $entity_type => $entity, 'error' => $validator->errors()->all()  ];
			} else {
				$entity =
					$repository->save( $entity, $factoryName::create( $this->company->id, $this->getUserIDForRecord( $data) ) );

				$entity->save();
				$this->{'add' . $formatted_entity_type . 'ToMaps'}( $entity );
			}
		}
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////
	private function buildMaps() {
		$this->maps = [
			'company'            => $this->company,
			'client'             => [],
			'contact'            => [],
			'invoice'            => [],
			'invoice_client'     => [],
			'product'            => [],
			'countries'          => [],
			'countries2'         => [],
			'currencies'         => [],
			'client_ids'         => [],
			'invoice_ids'        => [],
			'vendors'            => [],
			'expense_categories' => [],
			'tax_rates'          => [],
			'tax_names'          => [],
		];

		$clients = Client::scope()->get();
		foreach ( $clients as $client ) {
			$this->addClientToMaps( $client );
		}

		$contacts = ClientContact::scope()->get();
		foreach ( $contacts as $contact ) {
			$this->addContactToMaps( $contact );
		}

		$invoices = Invoice::scope()->get();
		foreach ( $invoices as $invoice ) {
			$this->addInvoiceToMaps( $invoice );
		}

		$products = Product::scope()->get();
		foreach ( $products as $product ) {
			$this->addProductToMaps( $product );
		}

		$countries = Country::all();
		foreach ( $countries as $country ) {
			$this->maps['countries'][ strtolower( $country->name ) ]        = $country->id;
			$this->maps['countries2'][ strtolower( $country->iso_3166_2 ) ] = $country->id;
		}

		$currencies = Currency::all();
		foreach ( $currencies as $currency ) {
			$this->maps['currencies'][ strtolower( $currency->code ) ] = $currency->id;
		}

		$vendors = Vendor::scope()->get();
		foreach ( $vendors as $vendor ) {
			$this->addVendorToMaps( $vendor );
		}

		$expenseCaegories = ExpenseCategory::scope()->get();
		foreach ( $expenseCaegories as $category ) {
			$this->addExpenseCategoryToMaps( $category );
		}

		$taxRates = TaxRate::scope()->get();
		foreach ( $taxRates as $taxRate ) {
			$name                             = trim( strtolower( $taxRate->name ) );
			$this->maps['tax_rates'][ $name ] = $taxRate->rate;
			$this->maps['tax_names'][ $name ] = $taxRate->name;
		}
	}

	/**
	 * @param Invoice $invoice
	 */
	private function addInvoiceToMaps( Invoice $invoice ) {
		if ( $number = strtolower( trim( $invoice->invoice_number ) ) ) {
			$this->maps['invoices'][ $number ]                = $invoice;
			$this->maps['invoice'][ $number ]                 = $invoice->id;
			$this->maps['invoice_client'][ $number ]          = $invoice->client_id;
			$this->maps['invoice_ids'][ $invoice->public_id ] = $invoice->id;
		}
	}

	/**
	 * @param Client $client
	 */
	private function addClientToMaps( Client $client ) {
		if ( $name = strtolower( trim( $client->name ) ) ) {
			$this->maps['client'][ $name ]                  = $client->id;
			$this->maps['client_ids'][ $client->public_id ] = $client->id;
		}
		if ( $client->contacts->count() ) {
			$contact = $client->contacts[0];
			if ( $email = strtolower( trim( $contact->email ) ) ) {
				$this->maps['client'][ $email ] = $client->id;
			}
			if ( $name = strtolower( trim($contact->first_name.' '.$contact->last_name) ) ) {
				$this->maps['client'][ $name ] = $client->id;
			}
			$this->maps['client_ids'][ $client->public_id ] = $client->id;
		}
	}

	/**
	 * @param ClientContact $contact
	 */
	private function addContactToMaps( ClientContact $contact ) {
		if ( $key = strtolower( trim( $contact->email ) ) ) {
			$this->maps['contact'][ $key ] = $contact;
		}
	}

	/**
	 * @param Product $product
	 */
	private function addProductToMaps( Product $product ) {
		if ( $key = strtolower( trim( $product->product_key ) ) ) {
			$this->maps['product'][ $key ] = $product;
		}
	}

	private function addVendorToMaps( Vendor $vendor ) {
		$this->maps['vendor'][ strtolower( $vendor->name ) ] = $vendor->id;
	}

	private function addExpenseCategoryToMaps( ExpenseCategory $category ) {
		if ( $name = strtolower( $category->name ) ) {
			$this->maps['expense_category'][ $name ] = $category->id;
		}
	}


	private function getUserIDForRecord( $record ) {
		if ( !empty($record['client.user_id']) ) {
			return $this->findUser( $record[ 'client.user_id' ] );
		} else {
			return $this->company->owner()->id;
		}
	}

	private function findUser( $user_hash ) {
		$user = User::where( 'company_id', $this->company->id )
					->where( \DB::raw( 'CONCAT_WS(" ", first_name, last_name)' ), 'like', '%' . $user_hash . '%' )
					->first();

		if ( $user ) {
			return $user->id;
		} else {
			return $this->company->owner()->id;
		}
	}

	private function getCsvData( $entityType ) {
		$base64_encoded_csv = Cache::get( $this->hash . '-' . $entityType );
		if ( empty( $base64_encoded_csv ) ) {
			return null;
		}

		$csv = base64_decode( $base64_encoded_csv );
		$csv = Reader::createFromString( $csv );

		$stmt = new Statement();
		$data = iterator_to_array( $stmt->process( $csv ) );

		if ( count( $data ) > 0 ) {
			$headers = $data[0];

			// Remove Invoice Ninja headers
			if ( count( $headers ) && count( $data ) > 4 && $this->import_type === 'csv' ) {
				$firstCell = $headers[0];
				if ( strstr( $firstCell, config( 'ninja.app_name' ) ) ) {
					array_shift( $data ); // Invoice Ninja...
					array_shift( $data ); // <blank line>
					array_shift( $data ); // Enitty Type Header
				}
			}
		}

        return $data;
    }
}
