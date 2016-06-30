/**
 * @class CM_Page_Abstract
 * @extends CM_Component_Abstract
 */
var CM_Page_Abstract = CM_Component_Abstract.extend({

  /** @type String */
  _class: 'CM_Page_Abstract',

  /** @type String[]|Null */
  _stateParams: null,

  /** @type Object|Null */
  _state: null,

  /** @type String|Null */
  _url: null,

  _ready: function() {
    CM_Component_Abstract.prototype._ready.call(this);

    if (this.hasStateParams()) {
      var location = window.location;
      var params = cm.request.parseQueryParams(location.search);
      var state = _.pick(params, _.intersection(_.keys(params), this.getStateParams()));
      this.routeToState(state, location.href);
    }
  },

  /**
   * @returns {String|Null}
   */
  getUrl: function() {
    return this._url;
  },

  /**
   * @returns {Boolean}
   */
  hasStateParams: function() {
    return null !== this._stateParams;
  },

  /**
   * @returns {String[]}
   */
  getStateParams: function() {
    if (!this.hasStateParams()) {
      throw new CM_Exception('Page has no state params');
    }
    return this._stateParams;
  },

  /**
   * @returns {Object}
   */
  getState: function() {
    if (!this.hasStateParams()) {
      throw new CM_Exception('Page has no state params');
    }
    return this._state;
  },

  /**
   * @param {Object} state
   */
  setState: function(state) {
    if (!_.isEmpty(_.difference(_.keys(state), this.getStateParams()))) {
      throw new CM_Exception('Invalid state');
    }
    this._state = state;
  },

  /**
   * @param {Object} state
   * @param {String} url
   * @returns {Boolean}
   */
  routeToState: function(state, url) {
    this._url = url;
    this.setState(state);
    return this._changeState(state);
  },

  /**
   * @param {Object} state
   * @returns {Boolean}
   */
  _changeState: function(state) {
    return false;
  }

});
