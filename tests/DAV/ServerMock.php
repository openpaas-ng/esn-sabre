<?php

namespace ESN\DAV;

use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Xml\Property\Href;

require_once ESN_TEST_VENDOR . '/sabre/dav/tests/Sabre/HTTP/ResponseMock.php';
require_once ESN_TEST_VENDOR . '/sabre/dav/tests/Sabre/HTTP/SapiMock.php';
require_once ESN_TEST_VENDOR . '/sabre/dav/tests/Sabre/DAVACL/PrincipalBackend/Mock.php';
require_once ESN_TEST_VENDOR . '/sabre/dav/tests/Sabre/CalDAV/Backend/Mock.php';
require_once ESN_TEST_VENDOR . '/sabre/dav/tests/Sabre/CardDAV/Backend/Mock.php';
require_once ESN_TEST_VENDOR . '/sabre/dav/tests/Sabre/DAVServerTest.php';
require_once ESN_TEST_VENDOR . '/sabre/dav/tests/Sabre/DAV/Auth/Backend/Mock.php';
require_once ESN_TEST_BASE . '/DAV/Auth/PluginMock.php';

define('PRINCIPALS_USERS', 'principals/users');
define('PRINCIPALS_TECHNICAL_USER', 'principals/technicalUser');
define('PRINCIPALS_COMMUNITIES', 'principals/communities');
define('PRINCIPALS_PROJECTS', 'principals/projects');
define('PRINCIPALS_RESOURCES', 'principals/resources');
define('PRINCIPALS_DOMAINS', 'principals/domains');

/**
 * @medium
 */
class ServerMock extends \PHPUnit_Framework_TestCase {

    protected $caldavCalendar = array(
        '{DAV:}displayname' => 'Calendar',
        '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'description',
        '{http://apple.com/ns/ical/}calendar-color' => '#0190FFFF',
        '{http://apple.com/ns/ical/}calendar-order' => '2',
        'principaluri' => 'principals/users/54b64eadf6d7d8e41d263e0f',
        'uri' => 'calendar1',
    );

    protected $caldavCalendarUser2 = array(
        '{DAV:}displayname' => 'Calendar',
        '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'description',
        '{http://apple.com/ns/ical/}calendar-color' => '#0190FFFF',
        '{http://apple.com/ns/ical/}calendar-order' => '2',
        'principaluri' => 'principals/users/54b64eadf6d7d8e41d263e0e',
        'uri' => 'calendar2',
    );

    protected $publicCaldavCalendar = array(
        '{DAV:}displayname' => 'Calendar',
        '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'description',
        '{http://apple.com/ns/ical/}calendar-color' => '#0190FFFF',
        '{http://apple.com/ns/ical/}calendar-order' => '2',
        'principaluri' => 'principals/users/54b64eadf6d7d8e41d263e0e',
        'uri' => 'publicCal1',
    );

    protected $delegatedCaldavCalendar = array(
        '{DAV:}displayname' => 'delegatedCalendar',
        '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'description of the delegated calendar',
        '{http://apple.com/ns/ical/}calendar-color' => '#33333333',
        '{http://apple.com/ns/ical/}calendar-order' => '2',
        'principaluri' => 'principals/users/54b64eadf6d7d8e41d263e0f',
        'uri' => 'delegatedCal1',
    );

    protected $caldavSubscription = array(
        '{DAV:}displayname' => 'Subscription',
        '{http://calendarserver.org/ns/}source' => '',
        '{http://apple.com/ns/ical/}calendar-color' => '#33333333',
        '{http://apple.com/ns/ical/}calendar-order' => '2',
        'principaluri' => 'principals/users/54b64eadf6d7d8e41d263e0f',
        'uri' => 'subscription1',
    );

    protected $caldavCalendarObjects = array(
        'event1.ics' =>
            'BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
CREATED:20120313T142342Z
UID:171EBEFC-C951-499D-B234-7BA7D677B45D
DTEND;TZID=Europe/Berlin:20120227T000000
TRANSP:OPAQUE
SUMMARY:Monday 0h
DTSTART;TZID=Europe/Berlin:20120227T000000
DTSTAMP:20120313T142416Z
SEQUENCE:4
END:VEVENT
END:VCALENDAR
',
        'event2.ics' =>
            'BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
CREATED:20120313T142342Z
UID:28CCB90C-0F2F-48FC-B1D9-33A2BA3D9594
TRANSP:OPAQUE
SUMMARY:Event 2
DTSTART:20130401T000000Z
DTEND:20130401T010000Z
DTSTAMP:20120313T142416Z
SEQUENCE:1
END:VEVENT
END:VCALENDAR
',
        'recur.ics' =>
            'BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:75EE3C60-34AC-4A97-953D-56CC004D6705
SUMMARY:Recurring
DTSTART:20150227T010000
DTEND:20150227T020000
RRULE:FREQ=DAILY
END:VEVENT
BEGIN:VEVENT
UID:75EE3C60-34AC-4A97-953D-56CC004D6705
RECURRENCE-ID:20150228T010000
SUMMARY:Recurring
DTSTART:20150228T030000
DTEND:20150228T040000
END:VEVENT
END:VCALENDAR
',
    );

    protected $privateRecurEvent =
    'BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:75EE3C60-34AC-4A97-953D-56CC004D6706
SUMMARY:RecurringPrivate
DTSTART:20150227T010000
DTEND:20150227T020000
LOCATION:Paris
RRULE:FREQ=DAILY
CLASS:PRIVATE
END:VEVENT
BEGIN:VEVENT
UID:75EE3C60-34AC-4A97-953D-56CC004D6706
RECURRENCE-ID:20150228T010000
SUMMARY:Exception
DTSTART:20150228T030000
DTEND:20150228T040000
END:VEVENT
END:VCALENDAR
';

    protected $freeBusyReport =
        'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sabre//Sabre VObject 4.1.3//EN
CALSCALE:GREGORIAN
BEGIN:VFREEBUSY
DTSTART:20120101T000000Z
DTEND:20150101T000000Z
DTSTAMP:**ANY**
FREEBUSY:20120226T230000Z/20120226T230000Z
FREEBUSY:20130401T000000Z/20130401T010000Z
END:VFREEBUSY
END:VCALENDAR
';

    protected $timeRangeData = [
          'match' => [ 'start' => '20120225T230000Z', 'end' => '20130228T225959Z' ],
          'scope' => [ 'calendars' => [ '/calendars/54b64eadf6d7d8e41d263e0f/calendar1' ] ]
        ];

    protected $freeBusyTimeRangeData = [
        'type' => 'free-busy-query',
        'match' => [
            'start' => '20120101T000000Z',
            'end' => '20150101T000000Z'
        ]
    ];

    protected $timeRangeDataBothEvents = [
        'match' => [ 'start' => '20120101T000000Z', 'end' => '20150101T000000Z' ],
        'scope' => [ 'calendars' => [ '/calendars/54b64eadf6d7d8e41d263e0f/calendar1' ] ]
    ];

    protected $timeRangeDataRecur = [
          'match' => [ 'start' => '20150227T000000Z', 'end' => '20150229T030000Z' ],
          'scope' => [ 'calendars' => [ '/calendars/54b64eadf6d7d8e41d263e0f/calendar1' ] ]
        ];

    protected $carddavAddressBook = array(
        'uri' => 'book1',
        'principaluri' => 'principals/users/54b64eadf6d7d8e41d263e0f',
    );

    protected $carddavCards = array(
        'card1' => "BEGIN:VCARD\r\nFN:d\r\nEND:VCARD\r\n",
        'card2' => "BEGIN:VCARD\r\nFN:c\r\nEND:VCARD",
        'card3' => "BEGIN:VCARD\r\nFN:b\r\nEND:VCARD\r\n",
        'card4' => "BEGIN:VCARD\nFN:a\nEND:VCARD\n",
    );

    protected $uidQueryData = [ 'uid' => '171EBEFC-C951-499D-B234-7BA7D677B45D' ];

    protected $uidQueryDataRecur = [ 'uid' => '75EE3C60-34AC-4A97-953D-56CC004D6705' ];

    protected $itipRequestData = [
        'method' => 'REPLY',
        'uid' => '75EE3C60-34AC-4A97-953D-56CC004D6705',
        'sequence' => '1',
        'sender' => 'a@linagora.com',
        'recipient' => 'b@linagora.com',
        'ical' => 'BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
CREATED:20120313T142342Z
UID:171EBEFC-C951-499D-B234-7BA7D677B45D
DTEND;TZID=Europe/Berlin:20120227T000000
TRANSP:OPAQUE
SUMMARY:Monday 0h
DTSTART;TZID=Europe/Berlin:20120227T000000
DTSTAMP:20120313T142416Z
SEQUENCE:4
END:VEVENT
END:VCALENDAR'
    ];

    protected $cal;

    function setUp() {
        $mcesn = new \MongoDB\Client(ESN_MONGO_ESNURI);
        $this->esndb = $mcesn->{ESN_MONGO_ESNDB};

        $mcsabre = new \MongoDB\Client(ESN_MONGO_SABREURI);
        $this->sabredb = $mcsabre->{ESN_MONGO_SABREDB};

        $this->sabredb->drop();
        $this->esndb->drop();

        $this->esndb->users->insertOne([
            '_id' => new \MongoDB\BSON\ObjectId('54b64eadf6d7d8e41d263e0f'),
            'firstname' => 'Roberto',
            'lastname' => 'Carlos',
            'accounts' => [
                [
                    'type' => 'email',
                    'emails' => [
                      'robertocarlos@realmadrid.com'
                    ]
                ]
                    ],
            'domains' => []
        ]);
        $this->esndb->users->insertOne([
            '_id' => new \MongoDB\BSON\ObjectId('54b64eadf6d7d8e41d263e0e'),
            'accounts' => [
                [
                    'type' => 'email',
                    'emails' => [
                      'johndoe@example.org'
                    ]
                ]
            ],
            'domains' => []
        ]);
        $this->esndb->users->insertOne([
            '_id' => new \MongoDB\BSON\ObjectId('54b64eadf6d7d8e41d263e0d'),
            'accounts' => [
                [
                    'type' => 'email',
                    'emails' => [
                      'johndoe2@example.org'
                    ]
                ]
            ],
            'domains' => []
        ]);
        $this->esndb->users->insertOne([
            '_id' => new \MongoDB\BSON\ObjectId('54b64eadf6d7d8e41d263e0c'),
            'accounts' => [
                [
                    'type' => 'email',
                    'emails' => [
                      'janedoe@example.org'
                    ]
                ]
            ],
            'domains' => []
        ]);

        $this->esndb->resources->insertOne([
            '_id' => new \MongoDB\BSON\ObjectId('62b64eadf6d7d8e41d263e0c'),
            'type' => 'calendar',
            'name' => 'cal resource'
        ]);

        $this->principalBackend = new \ESN\DAVACL\PrincipalBackend\EsnRequest($this->esndb);
        $this->caldavBackend = new \ESN\CalDAV\Backend\Mongo($this->sabredb);
        $this->carddavBackend = new \ESN\CardDAV\Backend\Esn($this->sabredb);

        $this->tree[] = new \Sabre\DAV\SimpleCollection('principals', [
          new \Sabre\CalDAV\Principal\Collection($this->principalBackend, 'principals/users'),
          new \Sabre\CalDAV\Principal\Collection($this->principalBackend, 'principals/domains')
        ]);
        $this->tree[] = new \ESN\CardDAV\AddressBookRoot(
            $this->principalBackend,
            $this->carddavBackend
        );
        $this->tree[] = new \ESN\CalDAV\CalendarRoot(
            $this->principalBackend,
            $this->caldavBackend,
            $this->esndb
        );

        $this->server = new \Sabre\DAV\Server($this->tree);
        $this->server->sapi = new \Sabre\HTTP\SapiMock();
        $this->server->debugExceptions = true;

        $caldavPlugin = new \ESN\CalDAV\Plugin();
        $this->server->addPlugin($caldavPlugin);

        $carddavPlugin = new \Sabre\CardDAV\Plugin();
        $this->server->addPlugin($carddavPlugin);

        $this->carddavPlugin = new \ESN\CardDAV\Plugin();
        $this->server->addPlugin($this->carddavPlugin);

        $this->carddavSubscriptionsPlugin = new \ESN\CardDAV\Subscriptions\Plugin();
        $this->server->addPlugin($this->carddavSubscriptionsPlugin);

        $this->server->addPlugin(new \Sabre\DAV\Sharing\Plugin());
        $this->server->addPlugin(new \Sabre\CalDAV\SharingPlugin());

        $this->authBackend = new \Sabre\DAV\Auth\Backend\Mock();
        $this->authBackend->setPrincipal('principals/users/54b64eadf6d7d8e41d263e0f');
        $authPlugin = new \ESN\DAV\Auth\PluginMock($this->authBackend);
        $this->server->addPlugin($authPlugin);

        $aclPlugin = new \Sabre\DAVACL\Plugin();
        $aclPlugin->principalCollectionSet = [
            PRINCIPALS_USERS,
            PRINCIPALS_COMMUNITIES,
            PRINCIPALS_PROJECTS,
            PRINCIPALS_RESOURCES,
            PRINCIPALS_DOMAINS
        ];
        $aclPlugin->adminPrincipals[] = PRINCIPALS_TECHNICAL_USER;
        $this->server->addPlugin($aclPlugin);

        $this->cal = $this->caldavCalendar;
        $this->cal['id'] = $this->caldavBackend->createCalendar($this->cal['principaluri'], $this->cal['uri'], $this->cal);
        foreach ($this->caldavCalendarObjects as $eventUri => $data) {
            $this->caldavBackend->createCalendarObject($this->cal['id'], $eventUri, $data);
        }

        $this->calUser2 = $this->caldavCalendarUser2;
        $this->calUser2['id'] = $this->caldavBackend->createCalendar($this->calUser2['principaluri'], $this->calUser2['uri'], $this->calUser2);

        $this->publicCal = $this->publicCaldavCalendar;
        $this->publicCal['id'] = $this->caldavBackend->createCalendar($this->publicCal['principaluri'], $this->publicCal['uri'], $this->publicCal);

        $calendarInfo = [];
        $calendarInfo['principaluri'] = $this->publicCal['principaluri'];
        $calendarInfo['uri'] = $this->publicCal['uri'];
        $this->caldavBackend->saveCalendarPublicRight($this->publicCal['id'], '{DAV:}all', $calendarInfo);
        $this->caldavBackend->createCalendarObject($this->publicCal['id'], 'privateRecurEvent.ics', $this->privateRecurEvent);

        $this->delegatedCal = $this->delegatedCaldavCalendar;
        $this->delegatedCal['id'] = $this->caldavBackend->createCalendar($this->delegatedCal['principaluri'], $this->delegatedCal['uri'], $this->delegatedCal);
        $this->delegateCalendar();

        $this->subscription = $this->caldavSubscription;
        $this->subscription['{http://calendarserver.org/ns/}source'] = new \Sabre\DAV\Xml\Property\Href('calendars/54b64eadf6d7d8e41d263e0e/publicCal1');
        $this->subscription['id'] = $this->caldavBackend->createSubscription($this->subscription['principaluri'], $this->subscription['uri'], $this->subscription);

        $this->carddavAddressBook['id'] = $this->carddavBackend->createAddressBook($this->carddavAddressBook['principaluri'],
            $this->carddavAddressBook['uri'],
            [
                '{DAV:}displayname' => 'Book 1',
                '{urn:ietf:params:xml:ns:carddav}addressbook-description' => 'Book 1 description',
                '{http://open-paas.org/contacts}type' => 'social'
            ]);

        foreach ($this->carddavCards as $card => $data) {
            $this->carddavBackend->createCard($this->carddavAddressBook['id'], $card, $data);
        }
    }

    protected function delegateCalendar() {
        $request = \Sabre\HTTP\Sapi::createFromServerArray(array(
            'REQUEST_METHOD'    => 'POST',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'       => 'application/json',
            'REQUEST_URI'       => '/calendars/54b64eadf6d7d8e41d263e0f/delegatedCalendar.json',
        ));

        $sharees = [
            'share' => [
                'set' => [
                    [
                        'dav:href'       => 'mailto:johndoe@example.org',
                        'dav:read-write' => true
                    ]
                ]
            ]
        ];

        $request->setBody(json_encode($sharees));
        $this->request($request);
    }

    protected function request($request) {

        if (is_array($request)) {
            $request = HTTP\Request::createFromServerArray($request);
        }
        $this->server->httpRequest = $request;
        $this->server->httpResponse = new \Sabre\HTTP\ResponseMock();
        $this->server->exec();

        return $this->server->httpResponse;

    }
}
