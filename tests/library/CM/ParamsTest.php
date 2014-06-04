<?php

class CM_ParamsTest extends CMTest_TestCase {

    public function testHas() {
        $params = new CM_Params(array('1' => 0, '2' => 'ababa', '3' => new stdClass(), '4' => null, '5' => false));

        $this->assertTrue($params->has('1'));
        $this->assertTrue($params->has('2'));
        $this->assertTrue($params->has('3'));
        $this->assertFalse($params->has('4'));
        $this->assertTrue($params->has('5'));
        $this->assertFalse($params->has('6'));
    }

    public function testGetString() {
        $text = "Foo Bar, Bar Foo";
        $notText = new stdClass();
        $params = new CM_Params(array('text1' => CM_Params::encode($text), 'text2' => $text, 'text3' => $notText));

        $this->assertEquals($text, $params->getString('text1'));
        $this->assertEquals($text, $params->getString('text2'));
        try {
            $params->getString('text3');
            $this->fail('invalid param. should not exist');
        } catch (CM_Exception_InvalidParam $e) {
            $this->assertTrue(true);
        }
        $this->assertEquals('foo', $params->getString('text4', 'foo'));
    }

    public function testGetStringArray() {

        $params = new CM_Params(array('k1' => 9, 'k2' => array(99, '121', '72', 0x3f), 'k3' => array('4', '8' . '3', '43', 'pong')));

        $this->assertSame(array('4', '83', '43', 'pong'), $params->getStringArray('k3'));

        try {
            $params->getStringArray('k1');
            $this->fail('Is not an array of strings!');
        } catch (CM_Exception_InvalidParam $e) {
            $this->assertTrue(true);
        }

        try {
            $params->getStringArray('k2');
            $this->fail('Is not an array of strings!');
        } catch (CM_Exception_InvalidParam $e) {
            $this->assertTrue(true);
        }
    }

    public function testGetInt() {
        $number1 = 12345678;
        $number2 = '12345678';
        $number3 = 'foo';
        $params = new CM_Params(array('number1' => $number1, 'number2' => CM_Params::encode($number2), 'number3' => $number3));

        $this->assertEquals($number1, $params->getInt('number1'));
        $this->assertEquals($number2, $params->getInt('number2'));
        try {
            $params->getInt('number3');
            $this->fail('invalid param. should not exist');
        } catch (CM_Exception_InvalidParam $e) {
            $this->assertTrue(true);
        }
        $this->assertEquals(4, $params->getInt('number4', 4));
    }

    public function testGetIntArray() {

        $params = new CM_Params(array('k1' => '7', 'k2' => array('99', '121', 72, 0x3f), 'k3' => array(4, 88, '43', 'pong')));

        $this->assertSame(array(99, 121, 72, 63), $params->getIntArray('k2'));

        try {
            $params->getIntArray('k1');
            $this->fail('Is not an array of integers!');
        } catch (CM_Exception_InvalidParam $e) {
            $this->assertTrue(true);
        }

        try {
            $params->getIntArray('k3');
            $this->fail('Is not an array of integers!');
        } catch (CM_Exception_InvalidParam $e) {
            $this->assertTrue(true);
        }
    }

    public function testGetFloat() {
        $testDataList = array(
            array(34.28, 34.28),
            array(-34.28, -34.28),
            array(0., 0.),
            array(-34., -34),
            array(34., 34),
            array(0., 0),
            array(34.28, '34.28'),
            array(-34.28, '-34.28'),
            array(34.2, '34.2'),
            array(-34.2, '-34.2'),
            array(34., '34.'),
            array(-34., '-34.'),
            array(4.28, '4.28'),
            array(-4.28, '-4.28'),
            array(.28, '.28'),
            array(-.28, '-.28'),
            array(.28, '0.28'),
            array(-.28, '-0.28'),
            array(0., '0.'),
            array(0., '-0.'),
            array(0., '.0'),
            array(0., '-.0'),
            array(34., '34'),
            array(-34., '-34'),
            array(0., '0'),
            array(0., '-0'),
        );
        foreach ($testDataList as $testData) {
            $expected = $testData[0];
            $userInput = $testData[1];
            $params = new CM_Params(array('userInput' => $userInput));
            $this->assertSame($expected, $params->getFloat('userInput'));
        }
        $userInputInvalidList = array('', '-', '.', '-.', '1.2.3', '12 ', ' 12', '12,345', false, true, array('1'), new stdClass(),
            fopen(__FILE__, 'r'));
        foreach ($userInputInvalidList as $userInputInvalid) {
            $params = new CM_Params(array('userInput' => $userInputInvalid));
            try {
                $params->getFloat('userInput');
                $this->fail('User input is not a float');
            } catch (CM_Exception_InvalidParam $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function testGetObject() {
        $language = CM_Model_Language::createStatic(array('name' => 'English', 'abbreviation' => 'en', 'enabled' => '1'));
        $params = new CM_Params(array('language' => $language, 'languageId' => $language->getId(), 'no-object-param' => 'xyz'));
        $this->assertEquals($language, $params->getLanguage('language'));
        $this->assertEquals($language, $params->getLanguage('languageId'));
        try {
            $params->getLanguage('no-object-param');
            $this->fail('getObject should fail and throw exception');
        } catch (CM_Exception $e) {
            $this->assertContains(get_class($language), $e->getMessage());
        }
    }

    public function testGetFile() {
        $file = new CM_File(CM_Bootloader::getInstance()->getDirTmp() . 'foo');
        $params = new CM_Params(array('file' => $file, 'filename' => $file->getPath()));
        $this->assertEquals($file, $params->getFile('file'));
        $this->assertEquals($file, $params->getFile('filename'));
    }

    public function testGetFileNonexistent() {
        $fileNonexistent = new CM_File('foo/bar');
        $params = new CM_Params(array('nonexistent' => $fileNonexistent->getPath()));
        $this->assertEquals($fileNonexistent, $params->getFile('nonexistent'));
    }

    public function testGetFileGeoPoint() {
        $point = new CM_Geo_Point(1, 2);
        $params = new CM_Params(array('point' => $point));
        $value = $params->getGeoPoint('point');
        $this->assertInstanceOf('CM_Geo_Point', $value);
        $this->assertSame(1.0, $value->getLatitude());
        $this->assertSame(2.0, $value->getLongitude());
    }

    /**
     * @expectedException ErrorException
     * @expectedExceptionMessage Missing argument 2
     */
    public function testGetGeoPointException() {
        $params = new CM_Params(array('point' => 'foo'));
        $params->getGeoPoint('point');
    }

    /**
     * @expectedException CM_Exception_Invalid
     * @expectedExceptionMessage Cannot json_decode value `foo`
     */
    public function testDecode() {
        CM_Params::decode('foo', true);
    }

    public function testGetDateTime() {
        $dateTimeList = array(
            new DateTime('2012-12-12 13:00:00 +0300'),
            new DateTime('2012-12-12 13:00:00 -0200'),
            new DateTime('2012-12-12 13:00:00 -0212'),
            new DateTime('2012-12-12 13:00:00 GMT'),
            new DateTime('2012-12-12 13:00:00 GMT+2'),
            new DateTime('2012-12-12 13:00:00 Europe/Zurich'),
        );
        foreach ($dateTimeList as $dateTime) {
            $paramsArray = json_decode(json_encode(array('date' => $dateTime)), true);
            $params = new CM_Params($paramsArray, true);

            $this->assertEquals($params->getDateTime('date'), $dateTime);
        }
    }

    public function testGetParams() {
        $params = new CM_Params(array(
            'foo1' => new CM_Params(),
            'foo2' => new CM_Params(array('bar' => 12)),
            'foo3' => array('bar' => 13),
            'foo4' => json_encode(array('bar' => 14)),
        ));

        $this->assertSame(array(), $params->getParams('foo1')->getAll());
        $this->assertSame(array('bar' => 12), $params->getParams('foo2')->getAll());
        $this->assertSame(array('bar' => 13), $params->getParams('foo3')->getAll());
        $this->assertSame(array('bar' => 14), $params->getParams('foo4')->getAll());
    }

    /**
     * @expectedException CM_Exception_InvalidParam
     */
    public function testGetParamsInvalidObject() {
        $params = new CM_Params(array('foo' => new stdClass()));
        $params->getParams('foo');
    }

    /**
     * @expectedException CM_Exception_InvalidParam
     * @expectedExceptionMessage Unexpected input of type `integer`
     */
    public function testGetParamsInvalidInt() {
        $params = new CM_Params(array('foo' => 12));
        $params->getParams('foo');
    }

    /**
     * @expectedException CM_Exception_InvalidParam
     * @expectedExceptionMessage Cannot decode input
     */
    public function testGetParamsInvalidString() {
        $params = new CM_Params(array('foo' => 'hello'));
        $params->getParams('foo');
    }
}
