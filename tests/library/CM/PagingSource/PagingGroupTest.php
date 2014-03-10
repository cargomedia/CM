<?php

class CM_PagingSource_PagingGroupTest extends CMTest_TestCase {

    public function testGetCount() {
        $pagingSource = $this->_getPagingSource();

        $this->assertSame(101, $pagingSource->getCount());
        $this->assertSame(101, $pagingSource->getCount(5));
    }

    public function testGetItems() {
        $pagingSource = $this->_getPagingSource();

        $itemList = $pagingSource->getItems();
        $this->assertSame(21, count($itemList));
        $this->assertSame(array(1, 21, 41, 61, 81), $itemList[1]);
        $this->assertSame(array(10), $itemList[10]);

        $itemList = $pagingSource->getItems(40, 40);
        $this->assertSame(20, count($itemList));
        $this->assertSame(array(40, 60), $itemList[0]);
    }

    public function testStalenessChance() {
        $pagingSource = $this->_getPagingSource();
        $this->assertSame(0.1, $pagingSource->getStalenessChance());
    }

    private function _getPagingSource() {
        $paging = $this->getMockForAbstractClass('CM_Paging_Abstract', array(new CM_PagingSource_Array(range(0, 100))));
        $pagingSource = new CM_PagingSource_PagingGroup($paging, function ($value) {
            if (10 == $value) {
                return 'keyValue';
            }
            return $value % 20 . 'keyValue';
        });

        return $pagingSource;
    }
}


