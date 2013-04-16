<?php

class CM_FormField_SetTest extends CMTest_TestCase {

	public function testConstructor() {
		$field = new CM_FormField_Set('foo');
		$this->assertInstanceOf('CM_FormField_Set', $field);
	}

	public function testSetGetValue() {
		$field = new CM_FormField_Set('foo');

		$values = array(32 => 'apples');
		$field->setValue($values);
		$this->assertSame($values, $field->getValue());

		$value = 'bar';
		$field->setValue($value);
		$this->assertSame($value, $field->getValue());
	}

	public function testValidate() {
		$data = array(32 => 'apples', 64 => 'oranges', 128 => 'bananas');
		$field = new CM_FormField_Set('foo', $data, true);

		$userInputGood = array(32, 64, 128);
		$response = $this->getMockForAbstractClass('CM_Response_Abstract', array(), '', false);
		$validationResult = $field->validate($userInputGood, $response);
		$this->assertSame($userInputGood, $validationResult);

		$userInputTainted = array(32, 23, 132);
		$validationResult = $field->validate($userInputTainted, $response);
		$this->assertSame(array(32), $validationResult);
	}

	public function testRender() {
		$name = 'foo';
		$data = array(32 => 'apples', 64 => 'oranges', 128 => 'bananas');
		$form = $this->getMockForm();
		$field = new CM_FormField_Set($name, $data, true);
		$values = array(64, 128);
		$field->setValue($values);
		$cssWidth = '50%';
		$field->setColumnSize($cssWidth);
		$doc = $this->_renderFormField($form, $field);
		$this->assertTrue($doc->exists('ul[id="' . $form->getAutoId() . '-' . $name . '-input"]'));
		$this->assertSame(count($data), $doc->getCount('label'));
		$this->assertSame(count($data), $doc->getCount('input'));
		$this->assertSame($cssWidth, preg_replace('/(^width: |;$)/', '', $doc->getAttr('li', 'style')));
		foreach ($data as $value => $label) {
			$this->assertTrue($doc->exists('li.' . $name . '_value_' . $value));
			$spanQuery = 'label[class="' . $name . '_label_' . $value . '"]';
			$this->assertTrue($doc->exists($spanQuery));
			$this->assertSame($label, trim($doc->getText($spanQuery)));
			$this->assertTrue($doc->exists('input[value="' . $value . '"]'));
			if (in_array($value, $values)) {
				$this->assertSame('checked', $doc->getAttr('input[value="' . $value . '"]', 'checked'));
			}
		}
	}
}
