( function( $, mw ) {

	mw.OgvJsSupport = {
		/**
		 * Return a stub audio context
		 */
		initAudioContext: function() {
			var AudioContext = window.AudioContext || window.webkitAudioContext;
			if (AudioContext) {
				var context = new AudioContext(),
					node;
				if (context.createScriptProcessor) {
					node = context.createScriptProcessor(1024, 0, 2);
				} else if (context.createJavaScriptNode) {
					node = context.createJavaScriptNode(1024, 0, 2);
				} else {
					throw new Error("Bad version of web audio API?");
				}

				// Don't actually run any audio, just start & stop the node
				node.connect(context.destination);
				node.disconnect();
				
				return context;
			} else {
				return null;
			}
		}
	}

} )( jQuery, mw );
