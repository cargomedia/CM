/**
 * @author cargomedia.ch
 */
(function() {
	if (jserror) {
		return;
	}
	var jserror = {
		/** @type Function|Null */
		onerrorBackup: null,

		/** @type String */
		logUrl: null,

		/** @type Number */
		counter: 0,

		/**
		 * @param {String} logUrl
		 * @param {Number} [counterMax]
		 * @param {Boolean} [suppressErrors]
		 * @param {Boolean} [suppressWithoutDetails]
		 */
		install: function(logUrl, counterMax, suppressErrors, suppressWithoutDetails) {
			this.logUrl = logUrl;
			if (window.onerror) {
				this.onerrorBackup = window.onerror;
			}
			window.onerror = function(message, fileUrl, fileLine) {
				jserror.counter++;
				var originatesFromLogging = (fileUrl.indexOf(jserror.logUrl) >= 0);
				var detailsUnavailable = (0 === fileLine);
				var counterMaxReached = (counterMax && jserror.counter > counterMax);
				var suppressLogging = originatesFromLogging || (suppressWithoutDetails && detailsUnavailable) || counterMaxReached;
				if (!suppressLogging) {
					jserror.log(message, fileUrl, fileLine);
				}
				if (jserror.onerrorBackup) {
					jserror.onerrorBackup(message, fileUrl, fileLine);
				}
				if (suppressErrors) {
					return true;
				}
			}
		},

		/**
		 * @param {String} message
		 * @param {String} fileUrl
		 * @param {Number} fileLine
		 */
		log: function(message, fileUrl, fileLine) {
			var src = this.logUrl;
			src += '?counter=' + this.counter;
			src += "&url=" + encodeURIComponent(document.location.href);
			src += "&message=" + encodeURIComponent(message.trim().substr(0, 10000));
			src += "&fileUrl=" + encodeURIComponent(fileUrl);
			src += "&fileLine=" + fileLine;
			this._appendScript(src);
		},

		/**
		 * @param {String} src
		 */
		_appendScript: function(src) {
			var script = document.createElement('script');
			script.src = src;
			script.type = 'text/javascript';
			document.getElementsByTagName('head')[0].appendChild(script);
		}
	};

	jserror.install('/jserror/null', 10, false, false);

}).call(this);
