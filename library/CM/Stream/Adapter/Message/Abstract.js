/**
 * @class CM_Stream_Adapter_Message_Abstract
 * @extends CM_Class_Abstract
 */
var CM_Stream_Adapter_Message_Abstract = CM_Class_Abstract.extend({
	/**
	 * @constructor
	 * @param {Object} options
	 */
	initialize: function(options) {

	},

	/**
	 * @param {String} channel
	 * @param {Object} data
	 * @param {Function} onmessage fn(event, data)
	 */
	subscribe: function(channel, data, onmessage) {
		throw 'Not implemented';
	},

	/**
	 * @param {String} channel
	 */
	unsubscribe: function(channel) {
		throw 'Not implemented';
	},

	/**
	 * @param {String} channel
	 * @param {String} event
	 * @param {Object} data
	 */
	publish: function(channel, event, data) {
		throw 'Not implemented';
	}
});
