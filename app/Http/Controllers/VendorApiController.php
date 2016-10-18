<?php namespace App\Http\Controllers;
// vendor
use App\Http\Requests\UpdateVendorRequest;
use App\Http\Requests\VendorRequest;
use Utils;
use Response;
use Input;
use App\Models\Vendor;
use App\Ninja\Repositories\VendorRepository;
use App\Http\Requests\CreateVendorRequest;

class VendorApiController extends BaseAPIController
{
    protected $vendorRepo;

    protected $entityType = ENTITY_VENDOR;

    public function __construct(VendorRepository $vendorRepo)
    {
        parent::__construct();

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
        $vendors = Vendor::scope()
                    ->withTrashed()
                    ->orderBy('created_at', 'desc');

        return $this->listResponse($vendors);
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
                    ->with('country', 'vendor_contacts', 'industry', 'size', 'currency')
                    ->first();

        return $this->itemResponse($vendor);
    }

        /**
         * @SWG\Put(
         *   path="/vendors/{vendor_id}",
         *   tags={"vendor"},
         *   summary="Update a vendor",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Vendor")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Update vendor",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Vendor"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */

    public function update(UpdateVendorRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $vendor = $this->vendorRepo->save($data, $request->entity());

        $vendor->load(['vendor_contacts']);

        return $this->itemResponse($vendor);
    }


        /**
         * @SWG\Delete(
         *   path="/vendors/{vendor_id}",
         *   tags={"vendor"},
         *   summary="Delete a vendor",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Vendor")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Delete vendor",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Vendor"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */

    public function destroy(VendorRequest $request)
    {
        $vendor = $request->entity();

        $this->vendorRepo->delete($vendor);

        return $this->itemResponse($vendor);
    }
}
