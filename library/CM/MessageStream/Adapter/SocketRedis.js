var CM_MessageStream_Adapter_Abstract = require('CM/MessageStream/Adapter/Abstract');

/**
 * @class CM_MessageStream_Adapter_SocketRedis
 * @extends CM_MessageStream_Adapter_Abstract
 */
var CM_MessageStream_Adapter_SocketRedis = CM_MessageStream_Adapter_Abstract.extend({

  /** @type {SocketRedis|Null} */
  _socketRedis: null,

  initialize: function(options) {
    this._socketRedis = new SocketRedis(options.sockjsUrl);
  },

  subscribe: function(channel, data, onmessage) {
    this._socketRedis.subscribe(channel, cm.options.renderStamp, data, onmessage);
  },

  unsubscribe: function(channel) {
    this._socketRedis.unsubscribe(channel);
  },

  publish: function(channel, event, data) {
    this._socketRedis.publish(channel, event, data);
  }
});


module.exports = CM_MessageStream_Adapter_SocketRedis;