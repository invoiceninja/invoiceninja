<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Http\Requests\CreateContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Ninja\Repositories\ContactRepository;
use Input;
use Response;
use Utils;
use App\Services\ContactService;

class ContactApiController extends BaseAPIController
{
    protected $contactRepo;
    protected $contactService;

    protected $entityType = ENTITY_CONTACT;

    public function __construct(ContactRepository $contactRepo, ContactService $contactService)
    {
        parent::__construct();

        $this->contactRepo = $contactRepo;
        $this->contactService = $contactService;
    }

    /**
     * @SWG\Get(
     *   path="/contacts",
     *   summary="List contacts",
     *   tags={"contact"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of contacts",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Contact"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $contacts = Contact::scope()
                    ->withTrashed()
                    ->orderBy('created_at', 'desc');

        return $this->listResponse($contacts);
    }

    /**
     * @SWG\Get(
     *   path="/contacts/{contact_id}",
     *   summary="Retrieve a contact",
     *   tags={"contact"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="contact_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single contact",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Contact"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(ContactRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/contacts",
     *   tags={"contact"},
     *   summary="Create a contact",
     *   @SWG\Parameter(
     *     in="body",
     *     name="contact",
     *     @SWG\Schema(ref="#/definitions/Contact")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New contact",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Contact"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateContactRequest $request)
    {
        $contact = $this->contactService->save($request->input());

        return $this->itemResponse($contact);
    }

    /**
     * @SWG\Put(
     *   path="/contacts/{contact_id}",
     *   tags={"contact"},
     *   summary="Update a contact",
     *   @SWG\Parameter(
     *     in="path",
     *     name="contact_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="contact",
     *     @SWG\Schema(ref="#/definitions/Contact")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated contact",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Contact"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
     */
    public function update(UpdateContactRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $contact = $this->contactService->save($data, $request->entity());

        return $this->itemResponse($contact);
    }

    /**
     * @SWG\Delete(
     *   path="/contacts/{contact_id}",
     *   tags={"contact"},
     *   summary="Delete a contact",
     *   @SWG\Parameter(
     *     in="path",
     *     name="contact_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted contact",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Contact"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateContactRequest $request)
    {
        $contact = $request->entity();

        $this->contactRepo->delete($contact);

        return $this->itemResponse($contact);
    }
}
