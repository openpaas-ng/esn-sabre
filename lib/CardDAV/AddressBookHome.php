<?php

namespace ESN\CardDAV;

use Sabre\DAV\MkCol;

class AddressBookHome extends \Sabre\CardDAV\AddressBookHome {

    /**
     * Constructor
     *
     * @param Backend\BackendInterface $carddavBackend
     * @param array $principal
     */
    function __construct(\Sabre\CardDAV\Backend\BackendInterface $carddavBackend, $principal) {
        $this->principal = $principal;

        parent::__construct($carddavBackend, $principal['uri']);
    }

    /**
     * Returns a list of addressbooks. In contrast to the sabre version of this
     * method, the returned addressbook instance has extra methods.
     *
     * @return array
     */
    function getChildren() {
        $this->sourcesOfSharedAddressBooks = [];
        $children = [];

        $addressbooks = $this->carddavBackend->getAddressBooksForUser($this->principalUri);

        foreach($addressbooks as $addressbook) {
            $children[] = new \ESN\CardDAV\AddressBook($this->carddavBackend, $addressbook);
        }

        // If the backend supports subscriptions, we'll add those as well
        if ($this->carddavBackend instanceof Backend\SubscriptionSupport) {
            $children = $this->updateChildrenWithSubscriptionAddressBooks($children);
        }

        // If the backend supports shared address books, we'll add those as well
        if ($this->carddavBackend instanceof Backend\SharingSupport) {
            $children = $this->updateChildrenWithSharedAddressBooks($children);
        }

        // Add group address books
        if (isset($this->principal['groupPrincipals'])) {
            $children = $this->updateChildrenWithGroupAddressBooks($children);
        }

        return $children;
    }

    /**
     * Creates a new address book.
     *
     * @param string $name
     * @param MkCol $mkCol
     * @throws DAV\Exception\InvalidResourceType
     * @return void
     */
    function createExtendedCollection($name, MkCol $mkCol) {
        $isAddressBook = false;
        $isSubscription = false;

        foreach ($mkCol->getResourceType() as $rt) {
            switch ($rt) {
                case '{DAV:}collection' :
                    // ignore
                    break;
                case '{' . \Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook' :
                    $isAddressBook = true;
                    break;
                case '{http://open-paas.org/contacts}subscribed' :
                    $isSubscription = true;
                    break;
                default :
                    throw new DAV\Exception\InvalidResourceType('Unknown resourceType: ' . $rt);
            }
        }

        $properties = $mkCol->getRemainingValues();
        $mkCol->setRemainingResultCode(201);

        if ($isSubscription) {
            if (!$this->carddavBackend instanceof Backend\SubscriptionSupport) {
                throw new DAV\Exception\InvalidResourceType('This backend does not support subscriptions');
            }

            $this->carddavBackend->createSubscription($this->principalUri, $name, $properties);

        } elseif ($isAddressBook) {

            $this->carddavBackend->createAddressBook($this->principalUri, $name, $properties);

        } else {
            throw new DAV\Exception\InvalidResourceType('You can only create address book and subscriptions in this collection');
        }
    }

    /**
     * Allows authenticated users can list address books of a user
     */
    function getACL() {
        $acl = parent::getACL();
        $acl[] = [
            'privilege' => '{DAV:}read',
            'principal' => '{DAV:}authenticated',
            'protected' => true
        ];

        return $acl;
    }

    protected function updateChildrenWithSubscriptionAddressBooks($children) {
        foreach ($this->carddavBackend->getSubscriptionsForUser($this->principalUri) as $subscription) {
            $children[] = new \ESN\CardDAV\Subscriptions\Subscription($this->carddavBackend, $subscription);
        }

        return $children;
    }

    protected function updateChildrenWithSharedAddressBooks($children) {
        foreach ($this->carddavBackend->getSharedAddressBooksForUser($this->principalUri) as $sharedAddressBook) {
            $sharedAddressBookInstance = new Sharing\SharedAddressBook($this->carddavBackend, $sharedAddressBook);

            $this->sourcesOfSharedAddressBooks[(string)$sharedAddressBook['addressbookid']] = $sharedAddressBookInstance;

            $children[] = $sharedAddressBookInstance;
        }

        return $children;
    }

    protected function updateChildrenWithGroupAddressBooks($children) {
        foreach ($this->principal['groupPrincipals'] as $groupPrincipal) {
            foreach ($this->carddavBackend->getAddressBooksFor($groupPrincipal['uri']) as $addressBook) {
                $addressBook['administrators'] = $groupPrincipal['administrators'];
                $addressBook['members'] = $groupPrincipal['members'];
                $groupAddressBook = new Group\GroupAddressBook($this->carddavBackend, $addressBook);

                if ($groupAddressBook->isDisabled()) {
                    // Do not return shared address books which are delegated from disabled group address book
                    if (isset($this->sourcesOfSharedAddressBooks[(string)$addressBook['id']])) {
                        $index = array_search($this->sourcesOfSharedAddressBooks[(string)$addressBook['id']], $children);

                        array_splice($children, $index, 1);
                    }

                    continue;
                }

                // Once group address book is delegated to user, the delegated one will override the source.
                if (isset($this->sourcesOfSharedAddressBooks[(string)$addressBook['id']])) continue;

                $children[] = $groupAddressBook;
            }
        }

        return $children;
    }
}
