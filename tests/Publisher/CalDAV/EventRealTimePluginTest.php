<?php
namespace ESN\Publisher\CalDAV;
use Sabre\DAV\ServerPlugin;
use \Sabre\CalDAV\Schedule\IMipPlugin;

require_once ESN_TEST_BASE . '/CalDAV/MockUtils.php';

class EventRealTimePluginTest extends \PHPUnit_Framework_TestCase {

    const PATH = "calendars/456456/123123/uid.ics";
    const PARENT = 'calendars/456456/123123';
    const ETAG = 'The etag';

    private $icalData;

    private function getPlugin($server = null) {
        $plugin = new EventRealTimePluginMock($server, new \ESN\CalDAV\CalDAVBackendMock());
        $server = $plugin->getServer();
        $this->mockTree($server);
        $this->icalData = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:a18225bc-3bfb-4e2a-a5f1-711c8d9cf531\r\nTRANSP:OPAQUE\r\nDTSTART;TZID=Europe/Berlin:20160209T113000\r\nDTEND;TZID=Europe/Berlin:20160209T140000\r\nSUMMARY:test\r\nORGANIZER;CN=admin admin:mailto:admin@open-paas.org\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        return $plugin;
    }

    private function mockTree($server) {
        $server->tree = $this->getMockBuilder('\Sabre\DAV\Tree')->disableOriginalConstructor()->getMock();
        $server->tree->expects($this->any())->method('nodeExists')
            ->with('/'.self::PATH)
            ->willReturn(true);

        $nodeMock = $this->getMockBuilder('\Sabre\DAV\File')->getMock();
        $nodeMock->expects($this->any())->method('getETag')->willReturn(self::ETAG);

        $server->tree->expects($this->any())->method('getNodeForPath')
            ->will($this->returnValue($nodeMock));
    }

    function testCreateFileNonCalendarHome() {
        $plugin = $this->getPlugin();
        $server = $plugin->getServer();
        $client = $plugin->getClient();

        $modified = false;
        $parent = new \Sabre\DAV\SimpleCollection("root", []);

        $this->assertTrue($server->emit('beforeCreateFile', ["test", &$this->icalData, $parent, &$modified]));
        $this->assertTrue($server->emit('afterCreateFile', ["test", $parent]));
        $this->assertNull($client->message);
    }

    function testCreateFileEvent() {
        $calendarInfo = [
            'uri' => 'calendars/456456/123123',
            'id' => '123123',
            'principaluri' => 'principals/users/456456'
        ];

        $parent = new \ESN\CalDAV\SharedCalendar(new \Sabre\CalDAV\SharedCalendar(new \ESN\CalDAV\CalDAVBackendMock(), $calendarInfo));

        $server = new \Sabre\DAV\Server([
            new \Sabre\DAV\SimpleCollection("calendars", [
                $parent
            ])
        ]);

        $plugin = $this->getPlugin($server);
        $client = $plugin->getClient();

        $this->assertTrue($server->emit('beforeCreateFile', ["calendars/456456/123123/uid.ics", &$this->icalData, $parent, &$modified]));
        $this->assertTrue($server->emit('afterCreateFile', ["/calendars/456456/123123/uid.ics", $parent]));
        $this->assertNotNull($client->message);
    }

    function testUnbindNonCalendarObject() {
        $data = "BEGIN:VCALENDAR";

        $parent = new \Sabre\DAV\SimpleFile("filename", "contents");
        $server = new \Sabre\DAV\Server([
            new \Sabre\DAV\SimpleCollection("calendars", [
                new \Sabre\DAV\SimpleCollection("123123", [
                    new \Sabre\DAV\SimpleFile("uid.ics", "content")
                ])
            ])
        ]);

        $plugin = $this->getPlugin($server);
        $client = $plugin->getClient();
        $this->assertTrue($server->emit('beforeUnbind', [self::PATH]));
        $this->assertTrue($server->emit('afterUnbind', [self::PATH]));
        $this->assertNull($client->message);
    }

    function testItipDoSendMessageIfScheduleFail() {
        $plugin = $this->getMockBuilder(EventRealTimePlugin::class)
            ->setMethods(['publishMessages'])
            ->setConstructorArgs(['', new \ESN\CalDAV\CalDAVBackendMock()])
            ->getMock();
        $plugin->expects($this->never())->method('publishMessages');

        $message = new \Sabre\VObject\ITip\Message();
        $message->scheduleStatus = \ESN\CalDAV\Schedule\IMipPlugin::SCHEDSTAT_FAIL_TEMPORARY;

        $plugin->itip($message);

        $this->verifyMockObjects();
    }

    function testItipDelegateToScheduleAndPublishMessage() {
        $plugin = $this->getMockBuilder(EventRealTimePlugin::class)
            ->setMethods(['schedule', 'publishMessages'])
            ->setConstructorArgs(['', new \ESN\CalDAV\CalDAVBackendMock()])
            ->getMock();
        $plugin->expects($this->once())->method('schedule')->will($this->returnCallback(function($message) {
            $this->assertInstanceOf(\Sabre\VObject\ITip\Message::class, $message);

            return $message;
        }));
        $plugin->expects($this->once())->method('publishMessages');

        $plugin->itip(new \Sabre\VObject\ITip\Message());
        $this->verifyMockObjects();
    }

    function testBuildData() {
        $plugin = $this->getPlugin();
        $data = $plugin->buildData([
            'eventPath' => '/'.self::PATH,
            'event' => 'event'
        ]);

        $this->assertEquals($data['eventPath'], '/'.self::PATH);
        $this->assertEquals($data['etag'], self::ETAG);
        $this->assertEquals($data['event'], 'event');
    }

    function testBuildDataWithSource() {
        $path = '/path/for/calendar/event.ics';
        $plugin = $this->getPlugin();
        $data = $plugin->buildData([
            'eventPath' => $path,
            'eventSourcePath' => self::PATH,
            'event' => 'event'
        ]);

        $this->assertEquals($data['eventPath'], $path);
        $this->assertEquals($data['eventSourcePath'], self::PATH);
        $this->assertEquals($data['etag'], self::ETAG);
        $this->assertEquals($data['event'], 'event');
    }

}

class ClientMock implements \ESN\Publisher\Publisher {
    public $topic;
    public $message;

    function publish($topic, $message) {
        $this->topic = $topic;
        $this->message = $message;
    }
}

class EventRealTimePluginMock extends EventRealTimePlugin {

    function __construct($server, $backend) {
        if (!$server) $server = new \Sabre\DAV\Server([]);
        $this->initialize($server);
        $this->client = new ClientMock();
        $this->server = $server;
    }

    function getClient() {
        return $this->client;
    }

    function getMessage() {
        return $this->message;
    }

    function getServer() {
        return $this->server;
    }
}
