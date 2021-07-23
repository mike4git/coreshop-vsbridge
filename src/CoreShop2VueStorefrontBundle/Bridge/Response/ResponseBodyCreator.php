<?php

namespace CoreShop2VueStorefrontBundle\Bridge\Response;

use Carbon\Carbon;
use CoreShop\Component\Address\Model\AddressInterface;
use CoreShop\Component\Core\Model\CustomerInterface;
use CoreShop\Component\Index\Model\IndexInterface;
use CoreShop\Component\Resource\Repository\RepositoryInterface;

class ResponseBodyCreator
{
    /** @var RepositoryInterface */
    protected $indicesRepository;

    /**
     * ResponseBodyCreator constructor.
     *
     * @param RepositoryInterface $indicesRepository
     */
    public function __construct(RepositoryInterface $indicesRepository)
    {
        $this->indicesRepository   = $indicesRepository;
    }

    /**
     * @return IndexInterface|null
     * @todo multi store support
     */
    protected function getCurrentIndex(): ?IndexInterface
    {
        return $this->indicesRepository->findOneBy(['worker' => 'elasticsearch']);
    }

    public function userCreateResponse(CustomerInterface $customer): array
    {
        $response = [];

        $response['id'] = $customer->getId();
        $response['group_id'] = 1;

        $response = $this->formatDateTimeFields($customer, $response);

        $response['created_in'] = "Default Store View";
        $response['email'] = $customer->getEmail();
        $response['firstname'] = $customer->getFirstname();
        $response['lastname'] = $customer->getLastname();
        $response['store_id'] = 1;
        $response['website_id'] = 1;
        $response['addresses'] = [];
        $response['disable_auto_group_change'] = 0;

        return $response;
    }

    public function userMeResponse(CustomerInterface $customer): array
    {
        $response = [];
        $response['id'] = $customer->getId();
        $response['group_id'] = 1;

        $defaultAddress = $customer->getDefaultAddress();
        $response['default_shipping'] = $defaultAddress->getId();
        $response['default_billing'] = $defaultAddress->getId();

        $response = $this->formatDateTimeFields($customer, $response);

        $response['email'] = $customer->getEmail();
        $response['firstname'] = $customer->getFirstname();
        $response['lastname'] = $customer->getLastname();
        $response['store_id'] = 1;
        $response['website_id'] = 1;
        foreach ($customer->getAddresses() as $address) {
            $default = $address->getId() == $defaultAddress->getId();
            $response['addresses'][] = $this->getAddress($address, $customer->getId(), $default);
        }

        $response['disable_auto_group_change'] = 0;

        return $response;
    }

    private function formatDateTimeFields(CustomerInterface $customer, $response): array
    {
        $response['created_at'] = $this->formatDate($customer->getCreationDate());
        $response['updated_at'] = $this->formatDate($customer->getModificationDate());
        return $response;
    }

    private function getAddress(AddressInterface $customerAdress, int $customerId, $default = false): array
    {
        $address = [];

        $address['id'] = $customerAdress->getId();
        $address['customer_id'] = $customerId;
        $address['region']['region_code'] = null;
        $address['region']['region'] = null;
        $address['region']['region_id'] = 0;
        $address['region_id'] = 0;
        if (!is_null($customerAdress->getCountry())) {
            $address['country_id'] = $customerAdress->getCountry()->getIsoCode();
        }
        $address['street'][] = $customerAdress->getStreet();
        $address['street'][] = $customerAdress->getNumber();
        $address['telephone'] = $customerAdress->getPhoneNumber();
        $address['postcode'] = $customerAdress->getPostcode();
        $address['city'] = $customerAdress->getCity();
        $address['firstname'] = $customerAdress->getFirstname();
        $address['lastname'] = $customerAdress->getLastname();
        $address['default_shipping'] = $default;

        return $address;
    }

    protected function formatDate(string $dateTime): string
    {
        return Carbon::createFromTimestamp($dateTime)->format('Y-m-d H:i:s');
    }
}
