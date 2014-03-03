/**
 * @class CM_FormField_Text
 * @extends CM_FormField_Abstract
 */
var CM_FormField_Text = CM_FormField_Abstract.extend({
  _class: 'CM_FormField_Text',

  /** @type Boolean */
  _skipTriggerChange: false,

  events: {
    'blur input': function() {
      this.trigger('blur');
    },
    'focus input': function() {
      this.trigger('focus');
    }
  },

  /**
   * @param {String} value
   */
  setValue: function(value) {
    this._skipTriggerChange = true;
    this.$('input').val(value);
    this._skipTriggerChange = false;
  },

  setFocus: function() {
    this.$('input').focus();
  },

  /**
   * @return {Boolean}
   */
  hasFocus: function() {
    return this.$('input').is(':focus');
  },

  enableTriggerChange: function() {
    var self = this;
    var $input = this.$('input');
    var valueLast = $input.val();
    var callback = function() {
      var value = this.value;
      if (value != valueLast) {
        valueLast = value;
        if (!self._skipTriggerChange) {
          self.trigger('change');
        }
      }
    };
    // `propertychange` and `keyup` needed for IE9
    $input.on('input propertychange keyup', callback);
  }
});
