<?php

class CM_Geometry_Vector3Test extends CMTest_TestCase {

    public function testConstruction() {
        $vector3 = new CM_Geometry_Vector3(2, 3.4, 5);
        $this->assertInstanceOf('CM_Geometry_Vector3', $vector3);
        $this->assertSame(2.0, $vector3->getX());
        $this->assertSame(3.4, $vector3->getY());
        $this->assertSame(5.0, $vector3->getZ());

        $exception = $this->catchException(function () {
            new CM_Geometry_Vector3(2, 3, 'bar');
        });
        $this->assertInstanceOf('CM_Exception_Invalid', $exception);
        /** @var CM_Exception_Invalid $exception */
        $this->assertSame('Non numeric value', $exception->getMessage());
        $this->assertSame(['value' => 'bar'], $exception->getMetaInfo());

        $vector3 = CM_Geometry_Vector3::fromArray([
            'x' => 1.2,
            'y' => 3.4,
            'z' => 5.6,
        ]);
        $this->assertInstanceOf('CM_Geometry_Vector3', $vector3);
        $this->assertSame(1.2, $vector3->getX());
        $this->assertSame(3.4, $vector3->getY());
        $this->assertSame(5.6, $vector3->getZ());

        $exception = $this->catchException(function () {
            CM_Geometry_Vector3::fromArray([
                'x' => 1.2,
                'y' => 3.4,
            ]);
        });
        $this->assertInstanceOf('ErrorException', $exception);
        $this->assertContains('Undefined index: z', $exception->getMessage());
    }

    public function testToArray() {
        $vector3 = new CM_Geometry_Vector3(2, 3.4, 5);
        $this->assertSame([
            'x' => 2.0,
            'y' => 3.4,
            'z' => 5.0,
        ], $vector3->toArray());
    }
}
