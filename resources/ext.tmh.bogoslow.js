( function ( mw ) {

	/**
	 * A quick CPU/JS engine benchmark to guesstimate whether we're
	 * fast enough to handle 360p video in JavaScript.
	 *
	 * Returns true if slow, false if fast
	 *
	 * @return {boolean}
	 */
	mw.isBogoSlow = function isBogoSlow() {
		var timer;
		if (window.performance && window.performance.now) {
			timer = function() {
				return window.performance.now();
			};
		} else {
			timer = function() {
				return Date.now();
			};
		}

		var ops = 0;
		var fibonacci = function(n) {
			ops++;
			if (n < 2) {
				return n;
			} else {
				return fibonacci(n - 2) + fibonacci(n - 1);
			}
		};

		var start = timer();

		fibonacci(30);

		var delta = timer() - start,
			bogoSpeed = (ops / delta);

		// 2012 Retina MacBook Pro (Safari 7)  ~150,000
		// 2009 Dell T5500         (IE 11)     ~100,000
		// iPad Air                (iOS 7)      ~65,000
		// 2010 MBP / OS X 10.9    (Safari 7)   ~62,500
		// 2010 MBP / Win7 VM      (IE 11)      ~50,000+-
		//   ^ these play 360p ok
		// ----------- line of moderate doom ----------
		//   v these play 160p ok
		// iPad Mini non-Retina    (iOS 8 beta) ~25,000
		// Dell Inspiron Duo       (IE 11)      ~25,000
		// Surface RT              (IE 11)      ~18,000
		// iPod Touch 5th-gen      (iOS 8 beta) ~16,000
		// ------------ line of total doom ------------
		//   v these play only audio, if that
		// iPod 4th-gen            (iOS 6.1)     ~6,750
		// iPhone 3Gs              (iOS 6.1)     ~4,500
		var bogoSpeedCutoff = 50000;

		mw.log('MediaElement::autoSelectSource::bench: ' + bogoSpeed + ' ops per ms bogo test (' + ops + ' / '  + delta + ')');
		return (bogoSpeed < bogoSpeedCutoff);
	};

})( mediaWiki );
