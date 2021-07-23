<?php

namespace CoreShop2VueStorefrontBundle\Bridge\Customer;

use CoreShop\Bundle\CoreBundle\Customer\CustomerAlreadyExistsException;
use CoreShop\Bundle\CoreBundle\Customer\RegistrationService;
use CoreShop\Bundle\CoreBundle\Doctrine\ORM\CountryRepository;
use CoreShop\Bundle\CustomerBundle\Pimcore\Repository\CustomerRepository;
use CoreShop\Component\Address\Model\AddressInterface;
use CoreShop\Component\Core\Model\CustomerInterface;
use CoreShop\Component\Pimcore\DataObject\ObjectServiceInterface;
use CoreShop\Component\Resource\Factory\PimcoreFactoryInterface;
use LogicException;

class CustomerManager
{
    const DEFAULT_COUNTRY_CODE = "DE";

    /** @var RegistrationService */
    private $registrationService;
    /** @var CountryRepository */
    private $countryRepository;
    /** @var PimcoreFactoryInterface */
    private $customerFactory;
    /** @var PimcoreFactoryInterface */
    private $addressFactory;
    /** @var CustomerRepository */
    private $customerRepository;

    private $objectService;

    public function __construct(
        PimcoreFactoryInterface $customerFactory,
        PimcoreFactoryInterface $addressFactory,
        ObjectServiceInterface $objectService,
        RegistrationService $registrationService,
        CountryRepository $countryRepository,
        CustomerRepository $customerRepository
    )
    {
        $this->customerFactory = $customerFactory;
        $this->addressFactory = $addressFactory;
        $this->registrationService = $registrationService;
        $this->countryRepository = $countryRepository;
        $this->customerRepository = $customerRepository;
        $this->objectService = $objectService;
    }

    /**
     * @param array $user
     *
     * @return CustomerInterface
     *
     * @throws CustomerAlreadyExistsException
     */
    public function createCustomer(array $user): CustomerInterface
    {
        $customer = $this->customerFactory->createNew();
        $customer->setEmail($user['email']);
        $customer->setFirstname($user['firstname']);
        $customer->setLastname($user['lastname']);
        $customer->setPassword($user['password']);

        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();
        $defaultCountry = $this->countryRepository->findByCode(self::DEFAULT_COUNTRY_CODE);
        $address->setCountry($defaultCountry->getId());
        $address->setFirstname($customer->getFirstname());
        $address->setLastname($customer->getLastname());

        $formData['customer'] = $customer;
        $formData['address'] = $address;

        $this->registrationService->registerCustomer(
            $customer,
            $address,
            $formData
        );

        return $customer;
    }

    /**
     * @param array $customerData
     * @return array|bool|mixed
     */
    public function editCustomer(array $customerData): CustomerInterface
    {
        $customer = $this->customerRepository->findCustomerByEmail($customerData['email']);

        if (!$customer instanceof CustomerInterface) {
            throw new LogicException("Username could not be found.");
        }

        $customer->setFirstname($customerData['firstname']);
        $customer->setLastname($customerData['lastname']);
        $customer->save();

        $addresses = $customer->getAddresses();

        foreach ($customerData['addresses'] as $requestAddress) {
            $address = null;
            if (array_key_exists('id', $requestAddress)) {
                foreach ($addresses as $customerAddress) {
                    if ($customerAddress->getId() == $requestAddress['id']) {
                        $address = $customerAddress;
                        break;
                    }
                }
            }

            if (!isset($address)) {
                $address = $this->addressFactory->createNew();
                $address->setPublished(true);
                $address->setKey(uniqid());
                $address->setParent($this->objectService->createFolderByPath(sprintf(
                    '/%s/%s',
                    $customer->getFullPath(),
                    'addresses'
                )));
            }

            if (!is_null($this->countryRepository->findByCode($requestAddress['country_id']))) {
                $byCodeCountryId = $this->countryRepository->findByCode($requestAddress['country_id'])->getId();
                $requestAddress['country_id'] = $byCodeCountryId;
            }

            $address->setLastname($requestAddress['lastname']);
            $address->setFirstname($requestAddress['firstname']);
            $address->setCompany($requestAddress['company']);
            $address->setPostcode($requestAddress['postcode']);
            $address->setCity($requestAddress['city']);
            $address->setPhoneNumber($requestAddress['telephone']);
            $address->setCountry($requestAddress['country_id']);
            $address->setStreet($requestAddress['street'][0]);
            $address->setNumber($requestAddress['street'][1]);
            $address->save();

            $customer->addAddress($address);
        }
        $customer->save();
        return $customer;
    }
}
