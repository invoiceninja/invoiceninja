<?php namespace App\Http\Controllers;
// vendor
use Utils;
use Response;
use Input;
use Auth;
use App\Models\Vendor;
use App\Ninja\Repositories\VendorRepository;
use App\Http\Requests\CreateVendorRequest;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\VendorTransformer;

class VendorApiController extends BaseAPIController
{
    protected $vendorRepo;

    public function __construct(VendorRepository $vendorRepo)
    {
        //parent::__construct();

        $this->vendorRepo = $vendorRepo;
    }

    public function ping()
    {
        $headers = Utils::getApiHeaders();

        return Response::make('', 200, $headers);
    }

    /**
     * @SWG\Get(
     *   path="/vendors",
     *   summary="List of vendors",
     *   tags={"vendor"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with vendors",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Vendor"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $vendors    = Vendor::scope()
                    ->with($this->getIncluded())
                    ->withTrashed()
                    ->orderBy('created_at', 'desc')
                    ->paginate();

        $transformer    = new VendorTransformer(Auth::user()->account, Input::get('serializer'));
        $paginator      = Vendor::scope()->paginate();
        $data           = $this->createCollection($vendors, $transformer, ENTITY_VENDOR, $paginator);

        return $this->response($data);
    }

    /**
     * @SWG\Post(
     *   path="/vendors",
     *   tags={"vendor"},
     *   summary="Create a vendor",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Vendor")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New vendor",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Vendor"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateVendorRequest $request)
    {
        $vendor = $this->vendorRepo->save($request->input());

        $vendor = Vendor::scope($vendor->public_id)
                    ->with('country', 'vendorcontacts', 'industry', 'size', 'currency')
                    ->first();

        $transformer = new VendorTransformer(Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($vendor, $transformer, ENTITY_VENDOR);
        return $this->response($data);
    }
}
