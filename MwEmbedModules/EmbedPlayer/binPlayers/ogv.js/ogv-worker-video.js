(function() {
	var global = this;

	var scriptMap = {
		OGVDemuxerOgg: 'ogv-demuxer-ogg.js',
		OGVDemuxerWebM: 'ogv-demuxer-webm.js',
		OGVDecoderAudioOpus: 'ogv-decoder-audio-opus.js',
		OGVDecoderAudioVorbis: 'ogv-decoder-audio-vorbis.js',
		OGVDecoderVideoTheora: 'ogv-decoder-video-theora.js',
		OGVDecoderVideoVP8: 'ogv-decoder-video-vp8.js'
	};

	var proxyMap = {
		OGVDecoderAudioOpus: 'OGVDecoderAudioProxy',
		OGVDecoderAudioVorbis: 'OGVDecoderAudioProxy',
		OGVDecoderVideoTheora: 'OGVDecoderVideoProxy',
		OGVDecoderVideoVP8: 'OGVDecoderVideoProxy'
	};
	var workerMap = {
		OGVDecoderAudioProxy: 'ogv-worker-audio.js',
		OGVDecoderVideoProxy: 'ogv-worker-video.js'
	};

	function urlForClass(className) {
		var scriptName = scriptMap[className];
		if (scriptName) {
			return urlForScript(scriptName);
		} else {
			throw new Error('asked for URL for unknown class ' + className);
		}
	};

	function urlForScript(scriptName) {
		if (scriptName) {
			var base = OGVLoader.base;
			if (base) {
				base += '/';
			}
			return base + scriptName + '?version=' + encodeURIComponent(OGVVersion);
		} else {
			throw new Error('asked for URL for unknown script ' + scriptName);
		}
	};

	var scriptStatus = {},
		scriptCallbacks = {};
	function loadWebScript(src, callback) {
		console.log('loading web js', src);
		if (scriptStatus[src] == 'done') {
			callback();
		} else if (scriptStatus[src] == 'loading') {
			scriptCallbacks[src].push(callback);
		} else {
			scriptStatus[src] = 'loading';
			scriptCallbacks[src] = [callback];

			var scriptNode = document.createElement('script');
			function done(event) {
				var callbacks = scriptCallbacks[src];
				delete scriptCallbacks[src];
				scriptStatus[src] = 'done';

				callbacks.forEach(function(cb) {
					cb();
				});
			}
			scriptNode.addEventListener('load', done);
			scriptNode.addEventListener('error', done);
			scriptNode.src = src;
			document.querySelector('head').appendChild(scriptNode);
		}
	}

	OGVLoader = {
		base: '',

		loadClass: function(className, callback, options) {
			options = options || {};
			if (options.worker) {
				this.workerProxy(className, callback);
			} else if (typeof global[className] === 'function') {
				// already loaded!
				callback(global[className]);
			} else if (typeof global.window === 'object') {
				loadWebScript(urlForClass(className), function() {
					callback(global[className]);
				});
			} else if (typeof global.importScripts === 'function') {
				// worker has convenient sync importScripts
				global.importScripts(urlForClass(className));
				callback(global[className]);
			}
		},

		workerProxy: function(className, callback) {
			var proxyClass = proxyMap[className],
				workerScript = workerMap[proxyClass];

			if (!proxyClass) {
				throw new Error('Requested worker for class with no proxy: ' + className);
			}
			if (!workerScript) {
				throw new Error('Requested worker for class with no worker: ' + className);
			}

			this.loadClass(proxyClass, function(classObj) {
				var construct = function(options) {
					var worker = new Worker(urlForScript(workerScript));
					return new classObj(worker, className, options);
				};
				callback(construct);
			});
		}
	};
})();
/**
 * Web Worker wrapper for codec fun
 */
function OGVWorkerSupport(propList, handlers) {

	var transferables = (function() {
		var buffer = new ArrayBuffer(1024),
			bytes = new Uint8Array(buffer);
		postMessage({
			action: 'transferTest',
			bytes: bytes
		}, [buffer]);
		if (buffer.byteLength) {
			// No transferable support
			return false;
		} else {
			return true;
		}
	})();

	var self = this;
	self.target = null;

	var sentProps = {};

	function copyObject(obj) {
		var copy = {};
		for (var prop in obj) {
			if (obj.hasOwnProperty(prop)) {
				copy[prop] = obj[prop];
			}
		}
		return copy;
	}

	function copyAudioBuffer(data) {
		if (data == null) {
			return null;
		} else {
			// Array of Float32Arrays
			var copy = [];
			for (var i = 0; i < data.length; i++) {
				copy[i] = new Float32Array(data[i]);
			}
			return copy;
		}
	}

	function copyByteArray(bytes) {
		// Hella slow in IE 10/11!
		//return new Uint8Array(bytes);

		// This claims to be faster in profiling but I don't see it in counters...
		var heap = bytes.buffer,
			extract = heap.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength),
			copy = new Uint8Array(extract);
		return copy;
	}

	function copyFrameBuffer(buffer) {
		if (buffer == null) {
			return null;
		} else {
			var copy = copyObject(buffer);
			copy.bytesY = copyByteArray(buffer.bytesY);
			copy.bytesCb = copyByteArray(buffer.bytesCb);
			copy.bytesCr = copyByteArray(buffer.bytesCr);
			return copy;
		}
	}

	handlers.construct = function(args, callback) {
		var className = args[0],
			options = args[1];
		console.log('construct', args);
		OGVLoader.loadClass(className, function(classObj) {
			self.target = new classObj(options);
			callback();
		});
	};

	addEventListener('message', function(event) {
		var data = event.data;
	
		if (data && data.action == 'transferTest') {
			// ignore
			return;
		}

		if (typeof data !== 'object' || typeof data.action !== 'string' || typeof data.callbackId !== 'string' || typeof data.args !== 'object') {
			console.log('invalid message data', data);
		} else if (!(data.action in handlers)) {
			console.log('invalid message action', data.action);
		} else {
			handlers[data.action].call(self, data.args, function(args) {
				args = args || [];

				// Collect and send any changed properties...
				var props = {},
					transfers = [];
				propList.forEach(function(propName) {
					var propVal = self.target[propName];

					if (sentProps[propName] !== propVal) {
						// Save this value for later reference...
						sentProps[propName] = propVal;

						if (propName == 'duration' && isNaN(propVal) && isNaN(sentProps[propName])) {
							// NaN is not === itself. Nice!
							// no need to update it here.
						} else if (propName == 'audioBuffer') {
							// Don't send the entire emscripten heap!
							propVal = copyAudioBuffer(propVal);
							props[propName] = propVal;
							if (propVal) {
								for (i = 0; i < propVal.length; i++) {
									transfers.push(propVal[i].buffer);
								}
							}
						} else if (propName == 'frameBuffer') {
							// Don't send the entire emscripten heap!
							propVal = copyFrameBuffer(propVal);
							props[propName] = propVal;
							if (propVal) {
								transfers.push(propVal.bytesY.buffer);
								transfers.push(propVal.bytesCb.buffer);
								transfers.push(propVal.bytesCr.buffer);
							}
						} else {
							props[propName] = propVal;
						}
					}
				});

				var out = {
					action: 'callback',
					callbackId: data.callbackId,
					args: args,
					props: props
				};
				if (transferables) {
					postMessage(out, transfers);
				} else {
					postMessage(out);
				}
			});
		}
	});

}
proxy = new OGVWorkerSupport([
	'loadedMetadata',
	'videoFormat',
	'frameBuffer'
], {
	init: function(args, callback) {
		this.target.init(callback);
	},

	processHeader: function(args, callback) {
		this.target.processHeader(args[0], function(ok) {
			callback([ok]);
		});
	},

	processFrame: function(args, callback) {
		this.target.processFrame(args[0], function(ok) {
			callback([ok]);
		});
	}
});
this.OGVVersion = "0.9.3-20150802012126-f1a2af2";
