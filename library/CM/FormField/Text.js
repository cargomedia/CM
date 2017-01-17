/**
 * @class CM_FormField_Text
 * @extends CM_FormField_Abstract
 */
var CM_FormField_Text = CM_FormField_Abstract.extend({
  _class: 'CM_FormField_Text',

  /** @type String */
  _valueLast: null,

  events: {
    'blur input, textarea': function() {
      this.trigger('blur');
    },
    'focus input, textarea': function() {
      this.trigger('focus');
    },
    'change input, textarea': function() {
      this.triggerChange();
    }
  },

  ready: function() {
    this._valueLast = this.getInput().val();
  },

  /**
   * @param {String} value
   */
  setValue: function(value) {
    this.getInput().val(value);
  },

  /**
   * @return {Boolean}
   */
  hasFocus: function() {
    return this.getInput().is(':focus');
  },

  triggerChange: function() {
    var valueCurrent = this.getInput().val();
    var valueLast = this._valueLast;
    if (valueLast !== valueCurrent) {
      this._valueLast = valueCurrent;
      this.trigger('change', {previous: valueLast, new: valueCurrent});
    }
  },

  enableTriggerChangeOnInput: function() {
    this.getInput().on('input', _.bind(this.triggerChange, this));
  }
});
