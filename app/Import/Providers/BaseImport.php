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

use App\Import\ImportException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;
use Symfony\Component\HttpFoundation\ParameterBag;

class BaseImport {

	public Company $company;

	public array $request;

	public array $error_array = [];

	public $request_name;

    public $repository_name;

    public $factory_name;

    public $repository;

    public $transformer;
    

    public function __construct( array $request, Company $company ) {
        $this->company     = $company;
        $this->request 	   = $request;
        $this->hash        = $request['hash'];
        $this->import_type = $request['import_type'];
        $this->skip_header = $request['skip_header'] ?? null;
        $this->column_map  =
            ! empty( $request['column_map'] ) ?
                array_combine( array_keys( $request['column_map'] ), array_column( $request['column_map'], 'mapping' ) ) : null;

        auth()->login( $this->company->owner(), true );
        
        auth()->user()->setCompany($this->company);
    }

	protected function findUser( $user_hash ) {
		$user = User::where( 'account_id', $this->company->account_id )
					->where( DB::raw( 'CONCAT_WS(" ", first_name, last_name)' ), 'like', '%' . $user_hash . '%' )
					->first();

		if ( $user ) {
			return $user->id;
		} else {
			return $this->company->owner()->id;
		}
	}

	protected function getCsvData( $entity_type ) {

		$base64_encoded_csv = Cache::pull( $this->hash . '-' . $entity_type );
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
				$first_cell = $headers[0];
				if ( strstr( $first_cell, config( 'ninja.app_name' ) ) ) {
					array_shift( $data ); // Invoice Ninja...
					array_shift( $data ); // <blank line>
					array_shift( $data ); // Enitty Type Header
				}
			}
		}

		return $data;
	}

	public function mapCSVHeaderToKeys( $csvData ) {
		$keys = array_shift( $csvData );

		return array_map( function ( $values ) use ( $keys ) {
			return array_combine( $keys, $values );
		}, $csvData );
	}

	private function groupInvoices( $csvData, $key ) {
		// Group by invoice.
		$grouped = [];

		foreach ( $csvData as $line_item ) {
			if ( empty( $line_item[ $key ] ) ) {
				$this->error_array['invoice'][] = [ 'invoice' => $line_item, 'error' => 'No invoice number' ];
			} else {
				$grouped[ $line_item[ $key ] ][] = $line_item;
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
		foreach ( $data as $record ) {
            try {
                $entity = $this->transformer->transform( $record );

                /** @var \App\Http\Requests\Request $request */
                $request = new $this->request_name();

                // Pass entity data to request so it can be validated
                $request->query = $request->request = new ParameterBag( $entity );
                $validator = Validator::make( $entity, $request->rules() );

                if ( $validator->fails() ) {
                    $this->error_array[ $entity_type ][] =
                        [ $entity_type => $record, 'error' => $validator->errors()->all() ];
                } else {
                    $entity =
                        $this->repository->save(
                            array_diff_key( $entity, [ 'user_id' => false ] ),
                            $this->factory_name::create( $this->company->id, $this->getUserIDForRecord( $entity ) ) );

                    $entity->saveQuietly();

                }
            } catch ( \Exception $ex ) {
                if ( $ex instanceof ImportException ) {
                    $message = $ex->getMessage();
                } else {
                    report( $ex );
                    $message = 'Unknown error';
                }

                $this->error_array[ $entity_type ][] = [ $entity_type => $record, 'error' => $message ];
            }
        }
	}

}
