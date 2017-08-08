<?php

namespace App\Services;

use App\Models\Client;
use App\Ninja\Repositories\ContactRepository;

/**
 * Class ContactService.
 */
class ContactService extends BaseService
{
    /**
     * @var ContactRepository
     */
    protected $contactRepo;

    /**
     * ContactService constructor.
     *
     * @param ContactRepository $contactRepo
     */
    public function __construct(ContactRepository $contactRepo)
    {
        $this->contactRepo = $contactRepo;
    }

    /**
     * @return ContactRepository
     */
    protected function getRepo()
    {
        return $this->contactRepo;
    }

    /**
     * @param $data
     * @param null $contact
     *
     * @return mixed|null
     */
    public function save($data, $contact = null)
    {
        if (isset($data['client_id']) && $data['client_id']) {
            $data['client_id'] = Client::getPrivateId($data['client_id']);
        }

        return $this->contactRepo->save($data, $contact);
    }

}
