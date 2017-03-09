<?php

namespace App\Http\Controllers;

use App\Http\Requests\VendorRequest;
use App\Http\Requests\CreateVendorRequest;
use App\Http\Requests\UpdateVendorRequest;
use App\Models\Vendor;
use App\Ninja\Repositories\VendorRepository;
use Input;
use Response;
use Utils;

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
     *   summary="List vendors",
     *   operationId="listVendors",
     *   tags={"vendor"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of vendors",
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
     * @SWG\Get(
     *   path="/vendors/{vendor_id}",
     *   summary="Retrieve a vendor",
     *   operationId="getVendor",
     *   tags={"client"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="vendor_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single vendor",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Vendor"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(VendorRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/vendors",
     *   summary="Create a vendor",
     *   operationId="createVendor",
     *   tags={"vendor"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="vendor",
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
     *   summary="Update a vendor",
     *   operationId="updateVendor",
     *   tags={"vendor"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="vendor_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="vendor",
     *     @SWG\Schema(ref="#/definitions/Vendor")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated vendor",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Vendor"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
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
     *   summary="Delete a vendor",
     *   operationId="deleteVendor",
     *   tags={"vendor"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="vendor_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted vendor",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Vendor"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateVendorRequest $request)
    {
        $vendor = $request->entity();

        $this->vendorRepo->delete($vendor);

        return $this->itemResponse($vendor);
    }
}
