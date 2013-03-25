/**
 * @class CM_FormField_Color
 * @extends CM_FormField_Abstract
 */
var CM_FormField_Color = CM_FormField_Abstract.extend({
	_class: 'CM_FormField_Color',

	ready: function() {
		var field = this;
		/*
		 * mColorPicker's "replace" option cannot be set to false here (too late)
		 * Set it within mColorPicker!
		 */
		$.fn.mColorPicker.init.replace = false;
		$.fn.mColorPicker.init.allowTransparency = false;
		$.fn.mColorPicker.init.showLogo = false;
		$.fn.mColorPicker.init.enhancedSwatches = false;
		this.$('input').mColorPicker({
			"imageFolder": cm.getUrlResource('layout', 'img/jquery.mColorPicker/')
		});
		$("#mColorPickerFooter").remove();
		$("#mColorPicker").height(158);
		this.$('input').change(function() {
			field.trigger('change');
		});
	}
});
