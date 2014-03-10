<?php

class CM_Usertext_Filter_CutWhitespaceTest extends CMTest_TestCase {

    public function testProcess() {
        $text = "\n\n \t   foo  \nbar     \n \r \t";
        $expected = "foo\nbar";
        $filter = new CM_Usertext_Filter_CutWhitespace();
        $actual = $filter->transform($text, new CM_Render());

        $this->assertSame($expected, $actual);
    }
}
