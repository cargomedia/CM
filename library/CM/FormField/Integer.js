/**
 * @class CM_FormField_Integer
 * @extends CM_FormField_Abstract
 */
var CM_FormField_Integer = CM_FormField_Abstract.extend({
	_class: 'CM_FormField_Integer',

	ready: function() {
		var field = this;
		var $slider = this.$(".slider");
		var $input = this.$("input");
		var input = $input.get(0);
		$slider.slider({
			value: $input.val(),
			min: field.getOption("min"),
			max: field.getOption("max"),
			step: field.getOption("step"),
			slide: function(event, ui) {
				var value = ui.value + 0;
				input.value = value;
				$(this).children(".ui-slider-handle").text(value);
			},
			change: function(event, ui) {
				var value = ui.value + 0;
				input.value = value;
				$(this).children(".ui-slider-handle").text(value);
			}
		});
		$slider.children(".ui-slider-handle").text($input.val());

		$input.watch("disabled", function(propName, oldVal, newVal) {
			$slider.slider("option", "disabled", newVal);
		});

		$input.watch("value", function(propName, oldVal, newVal) {
			$slider.slider("option", "value", newVal);
			field.trigger('change');
		});

		this.on('destruct', function() {
			$input.unwatch('disabled');
			$input.unwatch('value');
		});
	}
});
