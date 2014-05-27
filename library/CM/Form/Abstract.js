/**
 * @class CM_Form_Abstract
 * @extends CM_View_Abstract
 */
var CM_Form_Abstract = CM_View_Abstract.extend({
  _class: 'CM_Form_Abstract',

  _fields: {},

  _stopErrorPropagation: false,

  events: {
    'reset': function() {
      _.each(this._fields, function(field) {
        field.reset();
      });
      this.resetErrors();
    }
  },

  ready: function() {
  },

  initialize: function() {
    CM_View_Abstract.prototype.initialize.call(this);

    this._fields = {};
    _.each(this.options.fields, function(fieldInfo, name) {
      // Lazy construct
      var $field = this.$("#" + name);
      if ($field.length) {
        var fieldClass = window[fieldInfo.className];
        this.registerField(name, new fieldClass({"el": $field, "parent": this, "name": name, "options": fieldInfo.options}));
      }
    }, this);
  },


  _ready: function() {
    var handler = this;

    _.each(this.options.actions, function(action, name) {
      var $btn = $('#' + this.getAutoId() + '-' + name + '-button');
      var event = $btn.data('event');
      if (!event) {
        event = 'click';
      }
      $btn.on(event, {action: name}, function(event) {
        event.preventDefault();
        event.stopPropagation();
        return handler.submit(event.data.action);
      });
    }, this);

    this.$el.on('submit', function() {
      handler.$el.find('input[type="submit"], button[type="submit"]').first().click();
      return false;
    });

    CM_View_Abstract.prototype._ready.call(this);
  },

  /**
   * @param {String} name
   * @param {CM_FormField_Abstract} field
   */
  registerField: function(name, field) {
    this._fields[name] = field;

    field.on('change', function() {
      this.trigger('change');
    }, this);
  },

  /**
   * @return CM_Component_Abstract
   */
  getComponent: function() {
    return this.getParent();
  },

  /**
   * @return CM_FormField_Abstract|null
   */
  getField: function(name) {
    if (!this._fields[name]) {
      return null;
    }
    return this._fields[name];
  },

  /**
   * @return jQuery
   */
  $: function(selector) {
    if (!selector) {
      return this.$el;
    }
    selector = selector.replace('#', '#' + this.getAutoId() + '-');
    return $(selector, this.el);
  },

  /**
   * @param {String|Null} actionName
   */
  getData: function(actionName) {
    var form_data = this.$().serializeArray();
    var action = actionName ? this.options.actions[actionName] : null;

    var data = {};
    var regex = /^([\w\-]+)(\[([^\]]+)?\])?$/;
    var name, match;

    for (var i = 0, item; item = form_data[i]; i++) {
      match = regex.exec(item.name);
      name = match[1];
      item.value = item.value || '';

      if (action && typeof action.fields[name] == 'undefined') {
        continue;
      }

      if (!match[2]) {
        // Scalar
        data[name] = item.value;
      } else if (match[2] == '[]') {
        // Array
        if (typeof data[name] == 'undefined') {
          data[name] = [];
        }
        data[name].push(item.value);
      } else if (match[3]) {
        // Associative array
        if (typeof data[name] == 'undefined') {
          data[name] = {};
        }
        data[name][match[3]] = item.value;
      }
    }

    return data;
  },

  /**
   * @param {String} [actionName]
   * @param {Object} [options]
   * @return Promise
   */
  submit: function(actionName, options) {
    options = _.defaults(options || {}, {
      handleErrors: true,
      disableUI: true
    });
    var action = this._getAction(actionName);
    var data = this.getData(action.name);
    var deferred = $.Deferred();

    var errorList = this._getErrorList(action.name);

    if (options.handleErrors) {
      _.each(this._fields, function(field, fieldName) {
        if (errorList[fieldName]) {
          field.error(errorList[fieldName]);
        } else {
          field.error(null);
        }
      }, this);
    }

    if (_.size(errorList)) {
      deferred.reject();

    } else {
      if (options.disableUI) {
        this.disable();
      }
      this.trigger('submit', [data]);

      var handler = this;
      cm.ajax('form', {view: this.getComponent()._getArray(), form: this._getArray(), actionName: action.name, data: data}, {
        success: function(response) {
          if (response.errors) {
            if (options.handleErrors) {
              for (var i = response.errors.length - 1, error; error = response.errors[i]; i--) {
                if (_.isArray(error)) {
                  handler.getField(error[1]).error(error[0]);
                } else {
                  handler.error(error);
                }
              }
            }

            handler.trigger('error error.' + action.name);
            deferred.reject();
          }

          if (response.exec) {
            handler.evaluation = new Function(response.exec);
            handler.evaluation();
          }

          if (response.messages) {
            for (var i = 0, msg; msg = response.messages[i]; i++) {
              handler.message(msg);
            }
          }

          if (!response.errors) {
            handler.trigger('success success.' + action.name, response.data);
            deferred.resolve(response.data);
          }
        },
        error: function(msg, type, isPublic) {
          handler._stopErrorPropagation = false;
          handler.trigger('error error.' + action.name, msg, type, isPublic);
          deferred.reject();
          return !handler._stopErrorPropagation;
        },
        complete: function() {
          if (options.disableUI) {
            handler.enable();
          }
          handler.trigger('complete');
        }
      });
    }

    return deferred.promise();
  },

  stopErrorPropagation: function() {
    this._stopErrorPropagation = true;
  },

  reset: function() {
    this.el.reset();
  },

  disable: function() {
    this.$().disable();
  },

  enable: function() {
    this.$().enable();
  },

  /**
   * @param {String} message
   */
  error: function(message) {
    cm.window.hint(message);
  },

  /**
   * @param {String} message
   */
  message: function(message) {
    cm.window.hint(message);
  },

  resetErrors: function() {
    _.each(this._fields, function(field) {
      field.error(null);
    });
  },

  /**
   * @param {String} [actionName]
   * @returns {Object}
   */
  _getAction: function(actionName) {
    actionName = actionName || _.first(_.keys(this.options.actions));
    var action = this.options.actions[actionName];
    if (!action) {
      cm.error.triggerThrow('Form `' + this.getClass() + '` has no action `' + actionName + '`.');
    }
    action.name = actionName;
    return action;
  },

  /**
   * @param {String} [actionName]
   * @returns {Object}
   */
  _getErrorList: function(actionName) {
    var action = this._getAction(actionName);
    var data = this.getData(action.name);

    var errorList = {};

    _.each(_.keys(action.fields).reverse(), function(fieldName) {
      var required = action.fields[fieldName];
      var field = this.getField(fieldName);
      if (required && field.isEmpty(data[fieldName])) {
        var label;
        var $textInput = field.$('input, textarea');
        var $labels = $('label[for="' + field.getAutoId() + '-input"]');
        if ($labels.length) {
          label = $labels.first().text();
        } else if ($textInput.attr('placeholder')) {
          label = $textInput.attr('placeholder');
        }
        if (label) {
          errorList[fieldName] = cm.language.get('{$label} is required.', {label: label});
        } else {
          errorList[fieldName] = cm.language.get('Required');
        }
      }
    }, this);

    return errorList;
  }
});
