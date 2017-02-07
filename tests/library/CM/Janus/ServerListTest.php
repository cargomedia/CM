<?php

class CM_Janus_ServerListTest extends CMTest_TestCase {

    public function testFindByKey() {
        $server1 = $this->mockClass('CM_Janus_Server')->newInstanceWithoutConstructor();
        $server1->mockMethod('getKey')->set('foo');
        $server2 = $this->mockClass('CM_Janus_Server')->newInstanceWithoutConstructor();
        $server2->mockMethod('getKey')->set('bar');

        $serverList = new CM_Janus_ServerList([$server1, $server2]);

        $this->assertSame($server1, $serverList->findByKey('foo'));
        $this->assertSame($server2, $serverList->findByKey('bar'));
        $this->assertSame(null, $serverList->findByKey('zoo'));
    }

    public function testFilterByPlugin() {
        $server1 = $this->mockClass('CM_Janus_Server')->newInstanceWithoutConstructor();
        $server1->mockMethod('getPluginList')->set(['audio', 'audioHD', 'video']);
        $server2 = $this->mockClass('CM_Janus_Server')->newInstanceWithoutConstructor();
        $server2->mockMethod('getPluginList')->set(['video', 'videoHD']);

        $serverList = new CM_Janus_ServerList([$server1, $server2]);

        $this->assertSame([$server1], $serverList->filterByPlugin('audio')->getAll());
        $this->assertSame([$server1], $serverList->filterByPlugin('audioHD')->getAll());
        $this->assertSame([$server1, $server2], $serverList->filterByPlugin('video')->getAll());
        $this->assertSame([], $serverList->filterByPlugin('bar')->getAll());
    }

    public function testGetById() {
        $server = $this->mockClass('CM_Janus_Server')->newInstanceWithoutConstructor();
        $server->mockMethod('getId')->set(1);
        $serverList = new CM_Janus_ServerList([$server]);

        $this->assertSame($server, $serverList->getById(1));

        $exception = $this->catchException(function () use ($serverList) {
            $serverList->getById(2);
        });
        $this->assertTrue($exception instanceof CM_Exception_Invalid);
        /** @var CM_Exception_Invalid $exception */
        $this->assertSame('Cannot find server', $exception->getMessage());
        $this->assertSame(['serverId' => 2], $exception->getMetaInfo());
    }

    public function testFilterByClosestDistanceTo() {
        $location1 = new CM_Geo_Point(47, 41);
        $location2 = new CM_Geo_Point(50, 10);
        $serverLocation1 = new CM_Geo_Point(51, 0);
        $serverLocation2 = new CM_Geo_Point(55, 20);
        $serverLocation3 = new CM_Geo_Point(85, 25);

        $serverClass = $this->mockClass('CM_Janus_Server');
        $server1 = $serverClass->newInstanceWithoutConstructor();
        $server1->mockMethod('getLocation')->set($serverLocation1);

        $server2 = $serverClass->newInstanceWithoutConstructor();
        $server2->mockMethod('getLocation')->set($serverLocation2);
        $server3 = $serverClass->newInstanceWithoutConstructor();
        $server3->mockMethod('getLocation')->set($serverLocation2);

        $server4 = $serverClass->newInstanceWithoutConstructor();
        $server4->mockMethod('getLocation')->set($serverLocation3);

        $serverList = new CM_Janus_ServerList([
            $server1,
            $server2,
            $server3,
            $server4,
        ]);

        $this->assertSame([$server2, $server3], $serverList->filterByClosestDistanceTo($location1)->getAll());
        $this->assertSame([$server1], $serverList->filterByClosestDistanceTo($location2)->getAll());
    }

    public function testGetForIdentifier() {
        $serverList = new CM_Janus_ServerList();
        $serverClass = $this->mockClass('CM_Janus_Server');
        for ($i = 0; $i < 100; $i++) {
            /** @var CM_Janus_Server $server */
            $server = $serverClass->newInstanceWithoutConstructor();
            $serverList->addServer($server);
        }

        $id = rand(0, 1000);
        $server1 = $serverList->getForIdentifier($id);
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($server1, $serverList->getForIdentifier($id));
        }
        $id++;
        $server2 = $serverList->getForIdentifier($id);
        $this->assertNotSame($server1, $server2);
    }
}
