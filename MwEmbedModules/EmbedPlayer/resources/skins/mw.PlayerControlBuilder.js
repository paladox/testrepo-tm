/**
* Msg text is inherited from embedPlayer
*/

( function( mw, $ ) { "use strict";
/**
* mw.PlayerControlBuilder object
*	@param the embedPlayer element we are targeting
*/
mw.PlayerControlBuilder = function( embedPlayer, options ) {
	return this.init( embedPlayer, options );
};

/**
 * ControlsBuilder prototype:
 */
mw.PlayerControlBuilder.prototype = {
	//Default Local values:

	// Parent css Class name
	playerClass : 'mv-player',

	// Long string display of time value
	longTimeDisp: true,

	// Default volume layout is "vertical"
	volumeLayout : 'vertical',

	// Default control bar height
	height: mw.config.get( 'EmbedPlayer.ControlsHeight' ),

	// Default supported components is merged with embedPlayer set of supported types
	supportedComponents: {
		// All playback types support options
		'options': true
	},

	// Default supported menu items is merged with skin menu items
	supportedMenuItems: {
		// Player Select
		'playerSelect' : true,

		// Download the file menu
		'download' : true,

		// Share the video menu
		'share' : true,

		// Player library link
		'aboutPlayerLibrary': true
	},

	// Flag to store the current fullscreen mode
	inFullScreen: false,

	// Flag to store if a warning binding has been added
	addWarningFlag: false,

	// Flag to store state of overlay on player
	displayOptionsMenuFlag: false,

	// Local storage of ControlBar Callback
	hideControlBarCallback: false,

	// Flag to store controls status (disabled/enabled)
	controlsDisabled: false,

	// binding postfix
	bindPostfix: '.controlBuilder',

	/**
	* Initialization Object for the control builder
	*
	* @param {Object} embedPlayer EmbedPlayer interface
	*/
	init: function( embedPlayer ) {
		var _this = this;
		this.embedPlayer = embedPlayer;
		// Check for skin overrides for controlBuilder
		var skinClass = embedPlayer.skinName.substr(0,1).toUpperCase() + embedPlayer.skinName.substr( 1 );
		if ( mw['PlayerSkin' + skinClass ] ) {
			// Clone as to not override prototype with the skin config
			var _this = $.extend( true, { }, this, mw['PlayerSkin' + skinClass ] );
			return _this;
		}
		// Return the controlBuilder Object:
		return this;
	},

	/**
	* Get the control bar height
	* @return {Number} control bar height
	*/
	getHeight: function(){
		return this.height;
	},


	/**
	* Add the controls html to player interface
	*/
	addControls: function() {
		// Set up local pointer to the embedPlayer
		var embedPlayer = this.embedPlayer;

		// Set up local controlBuilder
		var _this = this;

		// Remove any old controls & old overlays:
		embedPlayer.getInterface().find( '.control-bar,.overlay-win' ).remove();

		// Reset flags:
		_this.displayOptionsMenuFlag = false;

		// Setup the controlBar container ( starts hidden )
		var $controlBar = $('<div />')
			.addClass( 'ui-state-default ui-widget-header ui-helper-clearfix control-bar' )
			.css( 'height', this.height );

		// Controls are hidden by default if overlaying controls:
		if( _this.isOverlayControls() ){
			$controlBar.hide();
		} else {
			// Include the control bar height when calculating the layout
			$controlBar.addClass('block');
		}

		// Make room for audio controls in the interface:
		if( embedPlayer.isAudio() && embedPlayer.getInterface().height() == 0 ){
			embedPlayer.getInterface().css( {
				'height' : this.height
			} );
		}

		// Add the controls to the interface
		embedPlayer.getInterface().append( $controlBar );

        if ( $.browser.mozilla && parseFloat( $.browser.version ) < 2 ) {
			embedPlayer.triggerHelper( 'resizeIframeContainer', [ {'height' : embedPlayer.height + $controlBar.height() - 1} ] );
        }

		// Add the Controls Component
		this.addControlComponents();

		// Add top level Controls bindings
		this.addControlBindings();
	},

	/**
	* Add control components as defined per this.components
	*/
	addControlComponents: function( ) {
		var _this = this;

		// Set up local pointer to the embedPlayer
		var embedPlayer = this.embedPlayer;

		//Set up local var to control container:
		var $controlBar = embedPlayer.getInterface().find( '.control-bar' );

		this.availableWidth = embedPlayer.getPlayerWidth();

		mw.log( 'PlayerControlsBuilder:: addControlComponents into:' + this.availableWidth );
		// Build the supportedComponents list
		this.supportedComponents = $.extend( this.supportedComponents, embedPlayer.supports );

		// Check for Attribution button
		if( mw.config.get( 'EmbedPlayer.AttributionButton' ) && embedPlayer.attributionbutton ){
			this.supportedComponents[ 'attributionButton' ] = true;
		}
		// Check global fullscreen enabled flag
		if( mw.config.get( 'EmbedPlayer.EnableFullscreen' ) === false ){
			this.supportedComponents[ 'fullscreen'] = false;
		}
		// Check if the options item is available
		if( mw.config.get( 'EmbedPlayer.EnableOptionsMenu' ) === false ){
			this.supportedComponents[ 'options'] = false;
		}
		// Check for volume control
		if( mw.config.get( 'EmbedPlayer.EnableVolumeControl') === false ){
			this.supportedComponents[ 'volumeControl'] = false;
		}

		// Check if we have multiple playable sources ( if only one source don't display source switch )
		if( embedPlayer.mediaElement.getPlayableSources().length == 1 ){
			this.supportedComponents[ 'sourceSwitch'] = false;
		}

		$( embedPlayer ).trigger( 'addControlBarComponent', this );

		var components = [];
		var largestPos = 0;
		var addComponent = function( componentId ){
			if ( _this.supportedComponents[ componentId ] ) {
				if ( _this.availableWidth > _this.components[ componentId ].w ) {
					_this.availableWidth -= _this.components[ componentId ].w;
					// Check if position is defined, if not, place at end of known positions
					var position =  _this.components[ componentId ].position ?
							_this.components[ componentId ].position:
							largestPos+1
					if( position > largestPos ){
						largestPos = position;
					}
					components.push({
						'id': componentId,
						'position': position
					});
					//mw.log(" availableWidth:" + _this.availableWidth + ' ' + componentId + ' took: ' +  _this.components[ componentId ].w )
				} else {
					mw.log( 'PlayerControlBuilder:: Not enough space for control component:' + componentId );
				}
			}
		};

		var addComponents = function() {
			components.sort(function(a, b) {
				return b.position - a.position;
			});
			for(var i=0;i<components.length;i++) {
				$controlBar.append(
					_this.getComponent( components[ i ]['id'] )
				);
			}
		}

		// Output components
		for ( var componentId in this.components ) {
			// Check for (component === false ) and skip
			if( this.components[ componentId ] === false ){
				continue;
			}

			// Special case with playhead and time ( to make sure they are to the left of everything else )
			if ( componentId == 'playHead' || componentId == 'timeDisplay'){
				continue;
			}

			// Skip "fullscreen" button for assets or where height is 0px ( audio )
			if( componentId == 'fullscreen' && this.embedPlayer.isAudio() ){
				continue;
			}
			addComponent( componentId );
		}
		// Add special case remaining components:
		if( mw.config.get( 'EmbedPlayer.EnableTimeDisplay' ) ){
			addComponent( 'timeDisplay' );
		}
		if( this.availableWidth > 30 ){
			addComponent( 'playHead' );
		}
		addComponents();
		$(embedPlayer).trigger( 'controlBarBuildDone' );
	},

	/**
	* Get a window size for the player while preserving aspect ratio:
	*
	* @@TODO This has similar logic to mw.embedPlayerNative applyIntrinsicAspect we should look
	* at merging their functionality.
	*
	* @param {object} windowSize
	* 		object that set { 'width': {width}, 'height':{height} } of target window
	* @return {object}
	* 	 css settings for fullscreen player
	*/
	getAspectPlayerWindowCss: function( windowSize ) {
		var embedPlayer = this.embedPlayer;
		var _this = this;
		// Setup target height width based on max window size
		if( !windowSize ){
			var windowSize = {
				'width' : $( window ).width(),
				'height' : $( window ).height()
			};
		}
		windowSize.width = parseInt( windowSize.width );
		windowSize.height = parseInt( windowSize.height );
		// See if we need to leave space for control bar
		if( !_this.isOverlayControls() ){
			//targetHeight =  targetHeight - this.height;
			windowSize.height = windowSize.height - this.height;
		}

		// Set target width
		var targetWidth = windowSize.width;
		var targetHeight = targetWidth * ( 1 / _this.getIntrinsicAspect() );
		// Check if it exceeds the height constraint:
		if( targetHeight > windowSize.height ){
			targetHeight = windowSize.height;
			targetWidth = parseInt( targetHeight * _this.getIntrinsicAspect() );
		}
		var offsetTop = 0;
		//  Move the video down 1/2 of the difference of window height
		offsetTop+= ( targetHeight < windowSize.height )? ( windowSize.height- targetHeight ) / 2 : 0;
		// if the video is very tall in a short window adjust the size:
		var offsetLeft = ( targetWidth < windowSize.width )? parseInt( windowSize.width- targetWidth ) / 2 : 0;

		var position = (mw.isIOS4() && mw.isIphone()) ? 'static' : 'absolute';
		mw.log( 'PlayerControlBuilder::getAspectPlayerWindowCss: ' + ' h:' + targetHeight + ' w:' + targetWidth + ' t:' + offsetTop + ' l:' + offsetLeft );
		return {
			'position' : position,
			'height': parseInt( targetHeight ),
			'width' : parseInt( targetWidth ),
			'top' : parseInt( offsetTop ),
			'left': parseInt( offsetLeft)
		};
	},

	/**
	 * Get the intrinsic aspect ratio of media  ( width / height )
	 * @return {float}
	 * 			size object with width and height
	 */
	getIntrinsicAspect: function(){
		var vid = this.embedPlayer.getPlayerElement();
		// Check for raw intrinsic media size:
		if( vid && vid.videoWidth && vid.videoHeight ){
			return vid.videoWidth / vid.videoHeight;
		}

		// See if we have source data attributes available:
		if( this.embedPlayer.mediaElement &&
			this.embedPlayer.mediaElement.selectedSource )
		{
			var ss = this.embedPlayer.mediaElement.selectedSource;
			// See if we have a hardcoded aspect to the source ( Adaptive streams don't have width / height )
			if( ss.aspect ){
				return ss.aspect;
			}

			if( ss.width && ss.height ){
				return ss.width / ss.height
			}
		}

		// check for posterImage size: ( should have Intrinsic aspect size as well )
		var img = this.embedPlayer.getInterface().find('.playerPoster')[0];
		if( img && img.naturalWidth && img.naturalHeight){
			return img.naturalWidth /  img.naturalHeight
		}

		// if all else fails use embedPlayer.getWidth()
		return this.embedPlayer.getWidth() / this.embedPlayer.getHeight()
	},

	/**
	* Get the play button css
	*/
	getPlayButtonPosition: function() {
		var _this = this;
		return {
			'position' : 'absolute',
			'left' : '50%',
			'top' : '50%',
			'margin-left' : - .5 * this.getComponentWidth( 'playButtonLarge' ),
			'margin-top' : - .5 * this.getComponentHeight( 'playButtonLarge' )
		};
	},

	/**
	 * Check if we're in Fullscreen
	 * @return {boolean)
	 */
	isInFullScreen: function() {
		return this.inFullScreen;
	},

	/**
	 * Toggles full screen by calling
	 *  doFullScreenPlayer to enable fullscreen mode
	 *  restoreWindowPlayer to restore window mode
	 */
	toggleFullscreen: function( forceClose ) {
		var _this = this;
		// Do normal in-page fullscreen handling:
		if( this.isInFullScreen() ){
			this.restoreWindowPlayer();
		}else {
			this.doFullScreenPlayer();
		}
		// Don't follow the # link:
		return false;
	},

	/**
	* Do full-screen mode
	*/
	doFullScreenPlayer: function( callback ) {
		mw.log("PlayerControlBuilder:: doFullScreenPlayer" );
		// Setup pointer to control builder :
		var _this = this;

		// Store the page vertical scroll
		var doc =  window.document;
		var context = window;
		this.verticalScrollPosition = doc.all ? doc.scrollTop : context.pageYOffset;
		
		// Setup local reference to embed player:
		var embedPlayer = this.embedPlayer;

		// Setup a local reference to the player interface:
		var $interface = embedPlayer.getInterface();
		// Check fullscreen state ( if already true do nothing )
		if( this.isInFullScreen() == true ){
			return ;
		}
		this.inFullScreen = true;

		// Add fullscreen class to interface:
		$interface.addClass( 'fullscreen' );

		// if overlaying controls add hide show player binding.
		if( _this.isOverlayControls() && !embedPlayer.isTouchDevice() ){
			_this.addFullscreenMouseMoveHideShowControls();
		}

		// Store the current scroll location on the iframe:
		$( embedPlayer ).trigger( 'fullScreenStoreVerticalScroll' );
		
		if( window.fullScreenApi.supportsFullScreen ) {
			_this.preFullscreenPlayerSize = this.getPlayerSize();
			var fullscreenHeight = null;
			var fsTarget = this.getFsTarget();

			var escapeFullscreen = function( event ) {
				// grab the correct document target to check for fullscreen
				if ( ! window.fullScreenApi.isFullScreen( window.document ) ) {
					_this.restoreWindowPlayer();
				}
			}
			// remove any old binding:
			fsTarget.removeEventListener(  fullScreenApi.fullScreenEventName, escapeFullscreen );
			// Add a binding to catch "escape" fullscreen
			fsTarget.addEventListener( fullScreenApi.fullScreenEventName, escapeFullscreen );
			// Make the iframe fullscreen:
			window.fullScreenApi.requestFullScreen( fsTarget );

			// There is a bug with mozfullscreenchange event in all versions of firefox with supportsFullScreen
			// https://bugzilla.mozilla.org/show_bug.cgi?id=724816
			// so we have to have an extra binding to check for size change and then restore.
			if( $.browser.mozilla ){
				_this.fullscreenRestoreCheck = setInterval( function(){
					if( fullscreenHeight && $(window).height() < fullscreenHeight ){
						// Mozilla triggered size change:
						clearInterval ( _this.fullscreenRestoreCheck );
						_this.restoreWindowPlayer();
					}
					// set fullscreen height:
					if( ! fullscreenHeight && _this.preFullscreenPlayerSize.height != $(window).height() ){
						fullscreenHeight = $(window).height();
					}
				}, 250 );
			}
		} else {
			// Check for hybrid html controls / native fullscreen support:
			var vid = this.embedPlayer.getPlayerElement();
			if( mw.config.get('EmbedPlayer.EnableIpadNativeFullscreen')
					&&
				vid && vid.webkitSupportsFullscreen
			){
				this.doHybridNativeFullscreen();
				return ;
			} else {
				// make the player traget or iframe fullscreen
				this.doContextTargetFullscreen();
			}
		}

		// Bind escape to restore in page clip ( IE9 needs a secondary escape binding )
		$( window ).keyup( function( event ) {
			// Escape check
			if( event.keyCode == 27 ){
				_this.restoreWindowPlayer();
			}
		} );

		// trigger the open fullscreen event:
		$( embedPlayer ).trigger( 'onOpenFullScreen' );

		// re draw the controls after a timeout ( to allow the screen dom to update )
		setTimeout( function(){
			_this.addControls();
		},100)
	},

	/**
	 * Make the target player interface or iframe fullscreen
	 */
	doContextTargetFullscreen: function() {
		var
		_this = this,
		doc = window.document,
		$doc = $( doc ),
		$target = $( this.getFsTarget() ),
		context = window;

		// update / reset local restore properties
		this.parentsAbsoluteList = [];
		this.parentsRelativeList = [];

		// Set the original parent page scale if possible:
		this.orginalParnetViewPortContent = $doc.find( 'meta[name="viewport"]' ).attr( 'content' );
		this.orginalTargetElementLayout = {
			'style' : $target[0].style.cssText,
			'width' : $target.width(),
			'height' : $target.height()
		};

		mw.log("PlayerControls:: doParentIframeFullscreen> verticalScrollPosition:" + this.verticalScrollPosition);
		context.scroll(0, 0);

		// Make sure the parent page page has a zoom of 1:
		if( ! $doc.find('meta[name="viewport"]').length ){
			$doc.find('head').append( $( '<meta />' ).attr('name', 'viewport') );
		}
		$doc.find('meta[name="viewport"]').attr('content', 'initial-scale=1; maximum-scale=1; minimum-scale=1;' );

		// iPad 5 supports fixed position in a bad way, use absolute pos for iOS
		var playerCssPosition = ( mw.isIOS() ) ? 'absolute': 'fixed';

		// Remove absolute css of the $target's parents
		$target.parents().each( function() {
			var $parent = $( this );
			if( $parent.css( 'position' ) == 'absolute' ) {
				_this.parentsAbsoluteList.push( $parent );
				$parent.css( 'position', 'static' );
			}
			if( $parent.css( 'position' ) == 'relative' ) {
				_this.parentsRelativeList.push( $parent );
				$parent.css( 'position', 'static' );
			}
		});

		// Make the $target fullscreen
		$target
			.css({
				'z-index': mw.config.get( 'EmbedPlayer.FullScreenZIndex' ),
				'position': playerCssPosition,
				'top' : '0px',
				'left' : '0px',
				'margin': 0
			})
			.data(
				'isFullscreen', true
			);

		var updateTargetSize = function() {
			context.scroll(0, 0);
			$target.css({
				'width' : context.innerWidth,
				'height' : context.innerHeight
			});
			// update player size if needed:
			_this.embedPlayer.applyIntrinsicAspect();
		};

		updateTargetSize();

		// Bind orientation change to resize player ( if fullscreen )
		$( context ).bind( 'orientationchange', function(e){
			if( _this.isInFullScreen() ){
				updateTargetSize();
			}
		});

		// prevent scrolling when in fullscreen: ( both iframe and dom target use document )
		document.ontouchmove = function( e ){
			if( _this.isInFullScreen() ){
				e.preventDefault();
			}
		};
	},
	/**
	 * Restore the player interface or iframe to a window player
	 */
	restoreContextPlayer: function(){
		var
		_this = this,
		doc = window.document,
		$doc = $( doc ),
		$target = $( this.getFsTarget() ),
		context = window;

		mw.log("PlayerControlsBuilder:: restoreContextPlayer> verticalScrollPosition:" + this.verticalScrollPosition );

		// Restore document zoom:
		if( this.orginalParnetViewPortContent ){
			$doc.find('meta[name="viewport"]').attr('content', this.orginalParnetViewPortContent );
		} else {
			// Restore user zoom: ( NOTE, there does not appear to be a way to know the
			// initial scale, so we just restore to 1 in the absence of explicit viewport tag )
			// In order to restore zoom, we must set maximum-scale to a valid value
			$doc.find('meta[name="viewport"]').attr('content', 'initial-scale=1; maximum-scale=8; minimum-scale=1;' );
		}
		if( this.orginalTargetElementLayout ) {
			$target[0].style.cssText = this.orginalTargetElementLayout.style;
			$target.attr({
				'width': this.orginalTargetElementLayout.width,
				'height': this.orginalTargetElementLayout.height
			});
			// update player size if needed:
			_this.embedPlayer.applyIntrinsicAspect();
		}
		// Restore any parent absolute pos:
		$doc.find( _this.parentsAbsoluteList ).each( function() {
			$( this ).css( 'position', 'absolute' );
		} );
		$doc.find( _this.parentsRelativeList ).each( function() {
			$( this ).css( 'position', 'relative' );
		} );
	},

	/**
	 * Supports hybrid native fullscreen, player html controls, and fullscreen is native
	 */
	doHybridNativeFullscreen: function(){
		var vid = this.embedPlayer.getPlayerElement();
		var _this = this;
		vid.webkitEnterFullscreen();
		// start to pull for exit fullscreen:
		this.fsIntervalID = setInterval( function(){
			var currentFS = vid.webkitDisplayingFullscreen;
			// Check if we have entered fullscreen but the player
			// has exited fullscreen with native controls click
			if( _this.isInFullScreen() && !currentFS ){
				// restore non-fullscreen player state
				_this.inFullScreen = false;
				// Trigger the onCloseFullscreen event:
				$( _this.embedPlayer ).trigger( 'onCloseFullScreen' );
				// stop polling for state change.
				clearInterval( _this.fsIntervalID );
			}
		}, 250 );
	},
	getWindowSize: function(){
		return {
			'width' : $(window).width(),
			'height' : $(window).height()
		};
	},
	doDomFullscreen: function(){
		var _this = this;
		var embedPlayer = this.embedPlayer;
		var $interface = embedPlayer.getInterface();
		// Remove any old mw-fullscreen-overlay
		$( '.mw-fullscreen-overlay' ).remove();

		_this.preFullscreenPlayerSize = this.getPlayerSize();

		// Add the css fixed fullscreen black overlay as a sibling to the video element
		// iOS4 does not respect z-index
		$interface.after(
			$( '<div />' )
			.addClass( 'mw-fullscreen-overlay' )
			// Set some arbitrary high z-index
			.css('z-index', mw.config.get( 'EmbedPlayer.FullScreenZIndex' ) )
			.hide()
			.fadeIn("slow")
		);

		// get the original interface to absolute positioned:
		if( ! this.windowPositionStyle  ){
			this.windowPositionStyle = $interface.css( 'position' );
		}
		if( !this.windowZindex ){
			this.windowZindex = $interface.css( 'z-index' );
		}
		// Get the base offset:
		this.windowOffset = this.getWindowOffset();

		// Change the z-index of the interface
		$interface.css( {
			'position' : 'fixed',
			'z-index' : mw.config.get( 'EmbedPlayer.FullScreenZIndex' ) + 1,
			'top' : this.windowOffset.top,
			'left' : this.windowOffset.left
		} );

		// If native persistent native player update z-index:
		if( embedPlayer.isPersistentNativePlayer() ){
			$( embedPlayer.getPlayerElement() ).css( {
				'z-index': mw.config.get( 'EmbedPlayer.FullScreenZIndex' ) + 1,
				'position': 'absolute'
			});
		}

		// Empty out the parent absolute index
		_this.parentsAbsolute = [];

		// Hide the body scroll bar
		$('body').css( 'overflow', 'hidden' );

		var topOffset = '0px';
		var leftOffset = '0px';

		// Check if we have an offsetParent
		if( $interface.offsetParent()[0].tagName
				&&
			$interface.offsetParent()[0].tagName.toLowerCase() != 'body' )
		{
			topOffset = -this.windowOffset.top + 'px';
			leftOffset = -this.windowOffset.left + 'px';
		}

		// Overflow hidden in fullscreen:
		$interface.css( 'overlow', 'hidden' );

		// Remove absolute css of the interface parents
		$interface.parents().each( function() {
			//mw.log(' parent : ' + $( this ).attr('id' ) + ' class: ' + $( this ).attr('class') + ' pos: ' + $( this ).css( 'position' ) );
			if( $( this ).css( 'position' ) == 'absolute' ) {
				_this.parentsAbsolute.push( $( this ) );
				$( this ).css( 'position', null );
				mw.log( 'PlayerControlBuilder::  should update position: ' + $( this ).css( 'position' ) );
			}
		});

		// Bind escape to restore in page clip
		$( window ).keyup( function( event ) {
			// Escape check
			if( event.keyCode == 27 ){
				_this.restoreWindowPlayer();
			}
		} );
	},
	addFullscreenMouseMoveHideShowControls:function(){
		var _this = this;
		// Bind mouse move in interface to hide control bar
		_this.mouseMovedFlag = false;
		_this.embedPlayer.getInterface().mousemove( function(e){
			_this.mouseMovedFlag = true;
		});

		// Check every 2 seconds reset flag status if controls are overlay
		var checkMovedMouse = function(){
			if( _this.isInFullScreen() ){
				if( _this.mouseMovedFlag ){
					_this.mouseMovedFlag = false;
					_this.showControlBar();
					// Once we move the mouse keep displayed for 3 seconds
					setTimeout(checkMovedMouse, 3000);
				} else {
					// Check for mouse movement every 250ms
					_this.hideControlBar();
					setTimeout(checkMovedMouse, 250 );
				}
				return;
			}
		};
		// always initially show the control bar:
		_this.showControlBar();
		// start monitoring for moving mouse
		checkMovedMouse();
	},
	getWindowOffset: function(){
		var windowOffset = this.embedPlayer.getInterface().offset();
		windowOffset.top = windowOffset.top - $(document).scrollTop();
		windowOffset.left = windowOffset.left - $(document).scrollLeft();
		this.windowOffset = windowOffset;
		return this.windowOffset;
	},
	// Display a fullscreen tip if configured to do and the browser supports it.
	displayFullscreenTip: function(){
		var _this = this;
		// Mobile devices don't have f11 key
		if( mw.isMobileDevice() ){
			return ;
		}
		// Safari does not have a DOM fullscreen ( no subtitles, no controls )
		if( $.browser.safari && ! /chrome/.test( navigator.userAgent.toLowerCase() ) ){
			return ;
		}

		// OSX has a different short cut than windows and liux
		var toolTipMsg = ( navigator.userAgent.indexOf('Mac OS X') != -1 )?
				gM( 'mwe-embedplayer-fullscreen-tip-osx') :
				gM( 'mwe-embedplayer-fullscreen-tip');

		var $targetTip = this.addWarningBinding( 'EmbedPlayer.FullscreenTip',
			$('<h3/>').html(
				toolTipMsg
			)
		);

		// Display the target warning:
		$targetTip.show();

		var hideTip = function(){
			mw.setConfig('EmbedPlayer.FullscreenTip', false );
			$targetTip.fadeOut('fast');
		};

		// Hide fullscreen tip if:
		// We leave fullscreen,
		$( this.embedPlayer ).bind( 'onCloseFullScreen', hideTip );
		// After 5 seconds,
		setTimeout( hideTip, 5000 );
		// Or if we catch an f11 button press
		$( document ).keyup( function( event ){
			if( event.keyCode == 122 ){
				hideTip();
			}
			return true;
		});
	},
	// TOOD fullscreen iframe vs inpage object abstraction
	//( avoid repatiave conditionals in getters )
	getPlayerSize: function(){
		var height = $(window).height() - this.getHeight();
		if( mw.config.get('EmbedPlayer.IsIframeServer' ) ){
			return {
				'height' : height,
				'width' : $(window).width()
			}
		} else {
			return {
				'height' : this.embedPlayer.getInterface().height(),
				'width' : this.embedPlayer.getInterface().width()
			}
		}
	},
	getFsTarget: function(){
		var $interface = this.embedPlayer.getInterface();
		return $interface[0];
	},
	/**
	* Restore the window player
	*/
	restoreWindowPlayer: function() {
		var _this = this;
		mw.log("PlayerControlBuilder :: restoreWindowPlayer" );
		var embedPlayer = this.embedPlayer;

		// Check if fullscreen mode is already restored:
		if( this.isInFullScreen() === false ){
			return ;
		}
		// Set fullscreen mode to false
		this.inFullScreen = false;

		// remove the fullscreen interface
		embedPlayer.getInterface().removeClass( 'fullscreen' );

		// Check for native support for fullscreen and support native fullscreen restore
		if ( window.fullScreenApi.supportsFullScreen ) {
			var fsTarget = this.getFsTarget();
			window.fullScreenApi.cancelFullScreen( fsTarget );
		}

		// Restore the iFrame context player
		this.restoreContextPlayer();

		// Restore scrolling on iPad
		$( document ).unbind( 'touchend.fullscreen' );

		// Trigger the onCloseFullscreen event:
		$( embedPlayer ).trigger( 'onCloseFullScreen' );

		// Scroll back to the previews position ( do in async call to allow dom fullscreen restore ) 
		setTimeout( function(){
			window.scroll( 0, _this.verticalScrollPosition );
		}, 100 );
		
		// re draw the controls after a timeout ( to allow the screen dom to update )
		setTimeout( function(){
			_this.addControls();
		},100)
	},
	restoreDomPlayer: function(){
		var _this = this;
		// local ref to embedPlayer:
		var embedPlayer = this.embedPlayer;

		var $interface = embedPlayer.$interface;
		var interfaceHeight = ( _this.isOverlayControls() )
			? embedPlayer.getHeight()
			: embedPlayer.getHeight() + _this.getHeight();

		mw.log( 'restoreWindowPlayer:: h:' + interfaceHeight + ' w:' + embedPlayer.getWidth());
		$('.mw-fullscreen-overlay').remove( 'slow' );

		mw.log( 'restore embedPlayer:: ' + embedPlayer.getWidth() + ' h: ' + embedPlayer.getHeight() );

		// Restore the player:
		embedPlayer.getInterface().css( {
			'width' : _this.preFullscreenPlayerSize.width,
			'height' : _this.preFullscreenPlayerSize.height
		});
		var topPos = {
			'position' : _this.windowPositionStyle,
			'z-index' : _this.windowZindex,
			'overlow' : 'visible',
			'top' : '0px',
			'left' : '0px'
		};
		// Restore non-absolute layout:
		$( [ $interface, $interface.find('.playerPoster'), embedPlayer ] ).css( topPos );
		if( embedPlayer.getPlayerElement() ){
			$( embedPlayer.getPlayerElement() )
				.css( topPos )
		}
		// Restore the body scroll bar
		$('body').css( 'overflow', 'auto' );

		// If native player restore z-index:
		if( embedPlayer.isPersistentNativePlayer() ){
			$( embedPlayer.getPlayerElement() ).css( {
				'z-index': 'auto'
			});
		}
	},
	/**
	* Get minimal width for interface overlay
	*/
	getOverlayWidth: function( ) {
		return ( this.embedPlayer.getPlayerWidth() < 300 )? 300 : this.embedPlayer.getPlayerWidth();
	},

	/**
	* Get minimal height for interface overlay
	*/
	getOverlayHeight: function( ) {
		return ( this.embedPlayer.getPlayerHeight() < 200 )? 200 : this.embedPlayer.getPlayerHeight();
	},

	/**
	* addControlBindings
	* Adds control hooks once controls are in the DOM
	*/
	addControlBindings: function( ) {
		// Set up local pointer to the embedPlayer
		var embedPlayer = this.embedPlayer;
		var _this = this;
		var $interface = embedPlayer.getInterface();

		_this.onControlBar = false;

		// Remove any old interface bindings
		$( embedPlayer ).unbind( this.bindPostfix );

		var bindFirstPlay = false;
		_this.addRightClickBinding();

		// add the player click bindings
		_this.addPlayerClickBindings();

		// Bind into play.ctrl namespace ( so we can unbind without affecting other play bindings )
		$( embedPlayer ).bind( 'onplay' + this.bindPostfix, function() { //Only bind once played
			// add right click binding again ( in case the player got swaped )
			embedPlayer.controlBuilder.addRightClickBinding();
		});

		$( embedPlayer ).bind( 'timeupdate' + this.bindPostfix, function(){
			embedPlayer.updatePlayheadStatus()
		});

		// Update buffer information TODO move to controlBuilder
		$( embedPlayer ).bind( 'progress' + this.bindPostfix, function(){
			embedPlayer.updateBufferStatus();
		});

		// Bind to EnableInterfaceComponents
		$( embedPlayer ).bind( 'onEnableInterfaceComponents' + this.bindPostfix, function() {
			embedPlayer.controlBuilder.controlsDisabled = false;
			embedPlayer.controlBuilder.addPlayerClickBindings();
		});

		// Bind to DisableInterfaceComponents
		$( embedPlayer ).bind( 'onDisableInterfaceComponents' + this.bindPostfix, function() {
			embedPlayer.controlBuilder.controlsDisabled = true;
			embedPlayer.controlBuilder.removePlayerClickBindings();
		});


		// TODO select a player on the page
		var bindSpaceUp = function(){
			$(window).bind('keyup' + _this.bindPostfix, function(e) {
				if( e.keyCode == 32 ) {
					if(embedPlayer.paused) {
						embedPlayer.play();
					} else {
						embedPlayer.pause();
					}
					return false;
				}
			});
		};

		var bindSpaceDown = function() {
			$(window).unbind( 'keyup' + _this.bindPostfix );
		};

		// Bind to resize event
		/*
		var triggerUpdate;
		$( window ).resize(function() {
			// We use setTimeout because of iOS 4.2 issues
			clearTimeout(triggerUpdate);
			triggerUpdate = setTimeout(function() {
				//embedPlayer.triggerHelper('updateLayout');
			}, 100);
		});
		*/

		$(window).on("debouncedresize", function() {
			embedPlayer.triggerHelper('updateLayout');
		});

		// Add hide show bindings for control overlay (if overlay is enabled )
		if( ! _this.isOverlayControls() ) {
			$interface
				.show()
				.hover( bindSpaceUp, bindSpaceDown );

			// include touch start pause binding
			$( embedPlayer).bind( 'touchstart' + this.bindPostfix, function() {
				embedPlayer._playContorls = true;
				mw.log( "PlayerControlBuilder:: touchstart:"  + ' isPause:' + embedPlayer.paused);
				if( embedPlayer.paused ) {
					embedPlayer.play();
				} else {
					embedPlayer.pause();
				}
			});
		} else { // hide show controls:
			// Bind a startTouch to show controls
			$( embedPlayer).bind( 'touchstart' + this.bindPostfix, function() {
				if ( embedPlayer.getInterface().find( '.control-bar' ).is( ':visible' ) ) {
					if( embedPlayer.paused ) {
						embedPlayer.play();
					} else {
						embedPlayer.pause();
					}
				} else {
					_this.showControlBar();
				}
				clearTimeout( _this.hideControlBarCallback );
				_this.hideControlBarCallback = setTimeout( function() {
					_this.hideControlBar();
				}, 60000 );
				// ( Once the user touched the video "don't hide" )
				return true;
			} );

			var hoverIntentConfig = {
					'sensitivity': 100,
					'timeout' : 1000,
					'over' : function(e){
						// Clear timeout on IE9
						if( mw.isIE9() ) {
							clearTimeout(_this.hideControlBarCallback);
							_this.hideControlBarCallback = false;
						}
						// Show controls with a set timeout ( avoid fade in fade out on short mouse over )
						_this.showControlBar();
						bindSpaceUp();
					},
					'out' : function(e){
						_this.hideControlBar();
						bindSpaceDown();
					}
				};

			// Check if we should display the interface:
			// special check for IE9 ( does not count hover on non-visiable inerface div
			if( mw.isIE9() ){
				$( embedPlayer.getPlayerElement() ).hoverIntent( hoverIntentConfig );

				// Add hover binding to control bar
				embedPlayer.getInterface().find( '.control-bar' ).hover( function(e) {
					_this.onControlBar = true;
					embedPlayer.getInterface().find( '.control-bar' ).show();
				}, function( e ) {
					if (!_this.hideControlBarCallback) {
						_this.hideControlBarCallback = setTimeout(function(){
							_this.hideControlBar();
						},1000);
					}
					_this.onControlBar = false;
				});

			} else {
				if ( !mw.isIpad() ) {
					$interface.hoverIntent( hoverIntentConfig );
				}
			}

		}

		// Add recommend firefox if we have non-native playback:
		if ( _this.checkNativeWarning( ) ) {
			_this.addWarningBinding(
				'EmbedPlayer.ShowNativeWarning',
				gM( 'mwe-embedplayer-for_best_experience',
					$('<a />')
						.attr({
							'href': 'http://www.mediawiki.org/wiki/Extension:TimedMediaHandler/Client_download',
							'target' : '_new'
						})
				)
			);
		}

		// Do png fix for ie6
		if ( $.browser.msie && $.browser.version <= 6 ) {
			$( '#' + embedPlayer.id + ' .play-btn-large' ).pngFix();
		}

		this.doVolumeBinding();

		// Check if we have any custom skin Bindings to run
		if ( this.addSkinControlBindings && typeof( this.addSkinControlBindings ) == 'function' ){
			this.addSkinControlBindings();
		}

		mw.log( 'trigger::addControlBindingsEvent' );
		$( embedPlayer ).trigger( 'addControlBindingsEvent' );
	},
	removePlayerClickBindings: function(){
		$( this.embedPlayer )
			.unbind( "click" + this.bindPostfix )
			.unbind( "dblclick" + this.bindPostfix );
	},
	addPlayerClickBindings: function(){

		var _this = this;
		var embedPlayer = this.embedPlayer;

		// prevent scrolling when in fullscreen:
		document.ontouchmove = function( e ){
			if( _this.isInFullScreen() ){
				e.preventDefault();
			}
		};
		// Remove old click bindings before adding:
		this.removePlayerClickBindings();

		// Setup "dobuleclick" fullscreen binding to embedPlayer ( if enabled )
		if ( this.supportedComponents['fullscreen'] ){
			$( embedPlayer ).bind( "dblclick" + _this.bindPostfix, function(){
				embedPlayer.fullscreen();
			});
		}

		var dblClickTime = 300;
		var lastClickTime = 0;
		var didDblClick = false;
		
		var playerClickCb = function( event ) {
			// make sure the event matches:
			if( event.currentTarget.id != embedPlayer.id ){
				embedPlayer = $( '#' + event.currentTarget.id )[0];
			}
			mw.log( "PlayerControlBuilder:: click:" + embedPlayer.id + ' isPause:' + embedPlayer.paused);
			// Don't do anything if touch interface or native controls are shown
			if( embedPlayer.useNativePlayerControls()
					||
				_this.isControlsDisabled()
					||
				embedPlayer.isTouchDevice()
			) {
				return true;
			}
			var clickTime = new Date().getTime();
			if( clickTime -lastClickTime < dblClickTime ) {
				didDblClick = true;
				setTimeout( function(){
					didDblClick = false;
				},  dblClickTime + 10 );
			}
			lastClickTime = clickTime;
			setTimeout( function(){
				// check if no click has since the time we called the setTimeout
				if( !didDblClick ){
					if( embedPlayer.paused ) {
						embedPlayer.play();
					} else {
						embedPlayer.pause();
					}
				}
			}, dblClickTime );
			return true;
		};
		if (!embedPlayer.addEventListener) {
			embedPlayer.attachEvent("onclick", playerClickCb);
		} else{
			// For some reason jquery .bind( 'click' ) is doing evil things
			// Firefox 3.5 requires third argument to addEventListener
			embedPlayer.addEventListener('click', playerClickCb, false );
		}

	},
	addRightClickBinding: function(){
		var embedPlayer = this.embedPlayer;
		// check config:
		if( mw.config.get( 'EmbedPlayer.EnableRightClick') === false ){
			document.oncontextmenu= function(e){return false;};
			$(embedPlayer).mousedown(function(e){
				if( e.button == 2 ) {
					return false;
				}
			});
		}
	},
	/**
	* Hide the control bar.
	*/
	hideControlBar : function(){
		var animateDuration = 'fast';
		var _this = this;

		// Do not hide control bar if overlay menu item is being displayed:
		if( _this.displayOptionsMenuFlag || _this.keepControlBarOnScreen ) {
			setTimeout( function(){
				_this.hideControlBar();
			}, 200 );
			return ;
		}

		// IE9: If the user mouse is on the control bar, don't hide it
		if( this.onControlBar === true ) {
			return ;
		}

		// Hide the control bar
		this.embedPlayer.getInterface().find( '.control-bar')
			.fadeOut( animateDuration );
		//mw.log('about to trigger hide control bar')
		// Allow interface items to update:
		$( this.embedPlayer ).trigger('onHideControlBar', {'bottom' : 15} );

	},
	restoreControlsHover:function(){
		if( this.isOverlayControls() ){
			this.keepControlBarOnScreen = false;
		}
	},
	/**
	* Show the control bar
	*/
	showControlBar: function( keepOnScreen ){
		var animateDuration = 'fast';
		if(! this.embedPlayer )
			return ;

		if( this.embedPlayer.getPlayerElement && ! this.embedPlayer.isPersistentNativePlayer() ){
			$( this.embedPlayer.getPlayerElement() ).css( 'z-index', '1' );
		}
		mw.log( 'PlayerControlBuilder:: ShowControlBar,  keep on screen: ' + keepOnScreen );

		// Show interface controls
		this.embedPlayer.getInterface().find( '.control-bar' )
			.fadeIn( animateDuration );

		if( keepOnScreen ){
			this.keepControlBarOnScreen = true;
		}

		// Trigger the screen overlay with layout info:
		$( this.embedPlayer ).trigger( 'onShowControlBar', {
			'bottom' : this.getHeight() + 15
		} );
	},

	/**
	* Checks if the browser supports overlays and the controlsOverlay is
	* set to true for the player or via config
	*/
	isOverlayControls: function(){
		//if the player "supports" overlays:
		if( ! this.embedPlayer.supports['overlays'] ){
			return false;
		}

		// If disabled via the player
		if( this.embedPlayer.overlaycontrols === false ){
			return false;
		}

		// Don't overlay controls if in audio mode:
		if( this.embedPlayer.isAudio() ){
			return false;
		}


		// If the config is false
		if( mw.config.get( 'EmbedPlayer.OverlayControls' ) === false){
			return false;
		}

		if( this.embedPlayer.controls === false ){
			return false;
		}

		// Past all tests OverlayControls is true:
		return true;
	},

	/* Check if the controls are disabled */

	isControlsDisabled: function() {
		return this.controlsDisabled;
	},

	/**
	* Check if a warning should be issued to non-native playback systems
	*
	* dependent on mediaElement being setup
	*/
	checkNativeWarning: function( ) {
		if( mw.config.get( 'EmbedPlayer.ShowNativeWarning' ) === false ){
			return false;
		}

		// Don't show for imageOverlay player:
		if( this.embedPlayer.instanceOf == 'ImageOverlay' ){
			return false;
		}

		// If the resolution is too small don't display the warning
		if( parseInt( this.embedPlayer.getPlayerHeight() ) < 199 ){
			return false;
		}

		// See if we have we have native support
		if( this.embedPlayer.instanceOf == 'Native' ){
			return false;
		}

		// Chrome's webM support is oky though:
		if( /chrome/.test(navigator.userAgent.toLowerCase() ) &&
			mw.EmbedTypes.getMediaPlayers().getMIMETypePlayers( 'video/webm' ).length ){
			return false;
		}


		// Check for h264 and or flash/flv source and playback support and don't show warning
		if(
			( mw.EmbedTypes.getMediaPlayers().getMIMETypePlayers( 'video/h264' ).length
			&& this.embedPlayer.mediaElement.getSources( 'video/h264' ).length )
			||
			( mw.EmbedTypes.getMediaPlayers().getMIMETypePlayers( 'video/x-flv' ).length
			&& this.embedPlayer.mediaElement.getSources( 'video/x-flv' ).length )
			||
			( mw.EmbedTypes.getMediaPlayers().getMIMETypePlayers( 'application/vnd.apple.mpegurl' ).length
			&& this.embedPlayer.mediaElement.getSources( 'application/vnd.apple.mpegurl' ).length )
			||
			( mw.EmbedTypes.getMediaPlayers().getMIMETypePlayers( 'audio/mpeg' ).length
			&& this.embedPlayer.mediaElement.getSources( 'audio/mpeg' ).length )
		){
			// No firefox link if a h.264 or flash/flv stream is present
			return false;
		}

		// Should issue the native warning
		return true;
	},

	/**
	* Does a native warning check binding to the player on mouse over.
	* @param {string} preferenceId The preference Id
	* @param {object} warningMsg The jQuery object warning message to be displayed.
	*
	*/
	/**
	* Display a warning message on the player
	* checks a preference Id to enable or disable it.
	* @param {string} preferenceId The preference Id
	* @param {object} warningMsg The jQuery object warning message to be displayed.
	* @param {boolean} if the hide ui should be exposed
	*
	*/
	addWarningBinding: function( preferenceId, warningMsg, hideDisableUi ) {
		mw.log( 'mw.PlayerControlBuilder: addWarningBinding: ' + preferenceId + ' wm: ' + warningMsg);
		// Set up local pointer to the embedPlayer
		var embedPlayer = this.embedPlayer;
		var _this = this;
		// make sure the player is large enough
		if( embedPlayer.getWidth() < 200 ){
			return false;
		}

		// Can be uncommented to reset hide prefrence
		//$.cookie( preferenceId, '' );

		// Check if a cookie has been set to hide the warning:
		if ( mw.config.get( preferenceId ) === true && $.cookie( preferenceId ) == 'hidewarning' ){
			return ;
		}

		var warnId = "warningOverlay_" + embedPlayer.id;
		$( '#' + warnId ).remove();

		// Add the targetWarning:
		var $targetWarning = $('<div />')
		.attr( {
			'id': warnId
		} )
		.addClass( 'ui-corner-all' )
		.css({
			'position' : 'absolute',
			'background' : '#FFF',
			'color' : '#111',
			'top' : '10px',
			'left' : '10px',
			'right' : '10px',
			'padding' : '4px',
			'z-index' : 2
		})
		.html( warningMsg );

		embedPlayer.getInterface().append(
			$targetWarning
		);

		$targetWarning.append(
			$('<br />')
		);
		// check if we should show the checkbox
		if( !hideDisableUi ){

			$targetWarning.append(
				$( '<input type="checkbox" />' )
				.attr({
					'id' : 'ffwarn_' + embedPlayer.id,
					'name' : 'ffwarn_' + embedPlayer.id
				})
				.click( function() {
					mw.log("WarningBindinng:: set " + preferenceId + ' to hidewarning ' );
					// Set up a cookie for 30 days:
					$.cookie( preferenceId, 'hidewarning', {expires: 30} );
					// Set the current instance
					mw.setConfig( preferenceId, false );
					$( '#warningOverlay_' + embedPlayer.id ).fadeOut( 'slow' );
					// set the local preference to false
					_this.addWarningFlag = false;
				} )
			);
			$targetWarning.append(
				$('<label />')
				.text( gM( 'mwe-embedplayer-do_not_warn_again' ) )
				.attr( 'for', 'ffwarn_' + embedPlayer.id )
			);
		}

		return $targetWarning;
	},

	/**
	* Binds the volume controls
	*/
	doVolumeBinding: function( ) {
		var embedPlayer = this.embedPlayer;
		var _this = this;
		embedPlayer.getInterface().find( '.volume_control' ).unbind().buttonHover().click( function() {
			mw.log( 'Volume control toggle' );
			embedPlayer.toggleMute();
		} );

		// Add vertical volume display hover
		if ( this.volumeLayout == 'vertical' ) {
			// Default volume binding:
			var hoverOverDelay = false;
			var $targetvol = embedPlayer.getInterface().find( '.vol_container' ).hide();
			embedPlayer.getInterface().find( '.volume_control' ).hover(
				function() {
					$targetvol.addClass( 'vol_container_top' );
					// Set to "below" if playing and embedType != native
					if ( embedPlayer && embedPlayer.isPlaying && embedPlayer.isPlaying() && !embedPlayer.supports['overlays'] ) {
						$targetvol.removeClass( 'vol_container_top' ).addClass( 'vol_container_below' );
					}
					$targetvol.fadeIn( 'fast' );
					hoverOverDelay = true;
				},
				function() {
					hoverOverDelay = false;
					setTimeout( function() {
						if ( !hoverOverDelay ) {
							$targetvol.fadeOut( 'fast' );
						}
					}, 500 );
				}
			);
		}
		var userSlide=false;
		// Setup volume slider:
		var sliderConf = {
			range: "min",
			value: 80,
			min: 0,
			max: 100,
			slide: function( event, ui ) {
				var percent = ui.value / 100;
				mw.log('PlayerControlBuilder::slide:update volume:' + percent);
				embedPlayer.setVolume( percent );
				userSlide = true;
			},
			change: function( event, ui ) {
				var percent = ui.value / 100;
				if ( percent == 0 ) {
					embedPlayer.getInterface().find( '.volume_control span' ).removeClass( 'ui-icon-volume-on' ).addClass( 'ui-icon-volume-off' );
				} else {
					embedPlayer.getInterface().find( '.volume_control span' ).removeClass( 'ui-icon-volume-off' ).addClass( 'ui-icon-volume-on' );
				}
				mw.log('PlayerControlBuilder::change:update volume:' + percent);
				embedPlayer.setVolume( percent, userSlide );
				userSlide = false;
			}
		};

		if ( this.volumeLayout == 'vertical' ) {
			sliderConf[ 'orientation' ] = "vertical";
		}

		embedPlayer.getInterface().find( '.volume-slider' ).slider( sliderConf );
	},

	/**
	* Get the options menu ul with li menu items
	*/
	getOptionsMenu: function( ) {
		var $optionsMenu = $( '<ul />' );
		for( var menuItemKey in this.optionMenuItems ){

			// Make sure its supported in the current controlBuilder config:
			if( $.inArray( menuItemKey, mw.config.get( 'EmbedPlayer.EnabledOptionsMenuItems' ) ) === -1 ) {
			 	continue;
			}

			$optionsMenu.append(
				this.optionMenuItems[ menuItemKey ]( this )
			);
		}
		return $optionsMenu;
	},

	/**
	* Allow the controlBuilder to do interface actions onDone
	*/
	onClipDone: function(){
		// Related videos could be shown here
	},

	/**
	 * The ctrl builder updates the interface on seeking
	 */
	onSeek: function(){
		//mw.log( "controlBuilder:: onSeek" );
		// Update the interface:
		this.setStatus( gM( 'mwe-embedplayer-seeking' ) );
		// add a loading spinner:
		this.embedPlayer.addPlayerSpinner();
		// hide once playing again:
		this.embedPlayer.hideSpinnerOncePlaying();
	},

	/**
	* Updates the player status that displays short text msgs and the play clock
	* @param {String} value Status string value to update
	*/
	setStatus: function( value ) {
		// update status:
		if( this.embedPlayer.getInterface() ){
			this.embedPlayer.getInterface().find( '.time-disp' ).text( value );
		}
	},

	/**
	* Option menu items
	*
	* @return
	* 	'li' a li line item with click action for that menu item
	*/
	optionMenuItems: {
		// Share the video menu
		'share': function( ctrlObj ) {
			return $.getLineItem(
				gM( 'mwe-embedplayer-share' ),
				'mail-closed',
				function( ) {
					ctrlObj.displayMenuOverlay(
						ctrlObj.getShare()
					);
					$( ctrlObj.embedPlayer ).trigger( 'showShareEvent' );
				}
			);
		},

		'aboutPlayerLibrary' : function( ctrlObj ){
			return $.getLineItem(
					gM( 'mwe-embedplayer-about-library' ),
					'info',
					function( ) {
						ctrlObj.displayMenuOverlay(
							ctrlObj.aboutPlayerLibrary()
						);
						$( ctrlObj.embedPlayer ).trigger( 'aboutPlayerLibrary' );
					}
				);
		}
	},

	/**
	* Close a menu overlay
	*/
	closeMenuOverlay: function(){
		var _this = this;
		var embedPlayer = this.embedPlayer;
		var $overlay = embedPlayer.getInterface().find( '.overlay-win,.ui-widget-overlay,.ui-widget-shadow' );

		this.displayOptionsMenuFlag = false;
		//mw.log(' closeMenuOverlay: ' + this.displayOptionsMenuFlag);

		$overlay.fadeOut( "slow", function() {
			$overlay.remove();
		} );

		// Show the big play button: ( if not in an ad .. TODO clean up )
		if( embedPlayer.isStopped() &&
				(
					embedPlayer.sequenceProxy &&
					embedPlayer.sequenceProxy.isInSequence == false
				)
		){
			embedPlayer.getInterface().find( '.play-btn-large' ).fadeIn( 'slow' );
		}

		$(embedPlayer).trigger( 'closeMenuOverlay' );

		return false; // onclick action return false
	},

	/**
	* Generic function to display custom HTML overlay on video.
	*
	* @param {String} overlayContent content to be displayed
	*/
    displayMenuOverlay: function( overlayContent, closeCallback, hideCloseButton ) {
		var _this = this;
		var embedPlayer = this.embedPlayer;
		mw.log( 'PlayerControlBuilder:: displayMenuOverlay' );
		//	set the overlay display flag to true:
		this.displayOptionsMenuFlag = true;

		if ( !this.supportedComponents[ 'overlays' ] ) {
			embedPlayer.stop();
		}


		// Hide the big play button:
		embedPlayer.hideLargePlayBtn();

		// Check if overlay window is already present:
		if ( embedPlayer.getInterface().find( '.overlay-win' ).length != 0 ) {
			//Update the content
			embedPlayer.getInterface().find( '.overlay-content' ).html(
				overlayContent
			);
			return ;
		}

		// Add an overlay
		embedPlayer.getInterface().append(
			$('<div />')
			.addClass( 'ui-widget-overlay' )
			.css( {
				'height' : '100%',
				'width' : '100%',
				'z-index' : 2
			} )
		);

        var $closeButton = [];

		if ( !hideCloseButton ) {
            // Setup the close button
            $closeButton = $('<div />')
            .addClass( 'ui-state-default ui-corner-all ui-icon_link rButton')
            .css({
                'position': 'absolute',
                'cursor' : 'pointer',
                'top' : '2px',
                'right' : '2px'
            })
            .click( function() {
                _this.closeMenuOverlay();
                if( closeCallback ){
                    closeCallback();
                }
            } )
            .append(
                    $('<span />')
                .addClass( 'ui-icon ui-icon-closethick' )
            );
        }

		var controlBarHeight = embedPlayer.getInterface().find( '.control-bar' ).height();
		var overlayWidth = (embedPlayer.getWidth() - 30);
		var overlayHeight = (embedPlayer.getHeight() - (controlBarHeight + 30));
		var overlayTop = (( (embedPlayer.getInterface().height() - controlBarHeight) - overlayHeight) / 2);
		var overlayLeft = ((embedPlayer.getInterface().width() - overlayWidth) / 2);

		var overlayMenuCss = {
			'height' : overlayHeight + 'px',
			'width' : overlayWidth + 'px',
			'position' : 'absolute',
			'top' : overlayTop + 'px',
			'left': overlayLeft + 'px',
			'margin': '0 10px 10px 0',
			'overflow' : 'auto',
			'padding' : '4px',
			'z-index' : 3
		};
		var $overlayMenu = $('<div />')
			.addClass( 'overlay-win ui-state-default ui-widget-header ui-corner-all' )
			.css( overlayMenuCss )
			.append(
				$closeButton,
				$('<div />')
					.addClass( 'overlay-content' )
					.append( overlayContent )
			);


		// Append the overlay menu to the player interface
		embedPlayer.getInterface().prepend(
			$overlayMenu
		)
		.find( '.overlay-win' )
		.fadeIn( "slow" );

		// Trigger menu overlay display
		$( embedPlayer ).trigger( 'displayMenuOverlay' );

		return false; // onclick action return false
	},

    /**
    * Close an alert
    */
    closeAlert: function( keepOverlay ) {
		var embedPlayer = this.embedPlayer;
        var $alert = $( '#alertContainer' );

        mw.log( 'mw.PlayerControlBuilder::closeAlert' );
        if ( !keepOverlay || ( mw.isIpad() && this.inFullScreen ) ) {
			embedPlayer.controlBuilder.closeMenuOverlay();
			if ( mw.isIpad() ) {
				embedPlayer.disablePlayControls();
			}
		}

        $alert.remove();

        return false; // onclick action return false;
    },

    /**
    * Generic function to display custom alert overlay on video.
    *
    * @param (Object) Object which includes:
    *   title Alert Title
    *   body Alert body
    *   buttonSet[label,callback] Array of buttons
    *   style CSS object
    */
    displayAlert: function( alertObj ) {
		var embedPlayer = this.embedPlayer;
        var callback;
		mw.log( 'PlayerControlBuilder::displayAlert:: ' + alertObj.title );
        // Check if callback is external or internal (Internal by default)

        // Check if overlay window is already present:
		if ( embedPlayer.getInterface().find( '.overlay-win' ).length != 0 ) {
            return;
        }
        if( typeof alertObj.callbackFunction == 'string' ) {
            if ( alertObj.isExternal ) {
                // TODO better support of running external JS functions, instead of window.parent
            	try{
                callback = window.parent[ alertObj.callbackFunction ];
            	} catch ( e ){
            		// could not call parent method
            	}
            } else {
                callback = window[ alertObj.callbackFunction ];
            }
        } else if( typeof alertObj.callbackFunction == 'function' ) {
            // Make life easier for internal usage of the listener mapping by supporting
            // passing a callback by function ref
            callback = alertObj.callbackFunction;
        } else {
            mw.log( "PlayerControlBuilder :: displayAlert :: Error: bad callback type" );
            callback = function() {};
        }

        var $container = $( '<div />' ).attr( 'id', 'alertContainer' ).addClass( 'alert-container' );
        var $title = $( '<div />' ).text( alertObj.title ).addClass( 'alert-title alert-text' );
        if ( alertObj.props && alertObj.props.titleTextColor ) {
            $title.removeClass( 'alert-text' );
            $title.css( 'color', mw.getHexColor( alertObj.props.titleTextColor ) );
        }
        var $message = $( '<div />' ).text( alertObj.message ).addClass( 'alert-message alert-text' );
		if ( alertObj.isError ) {
			$message.addClass( 'error' );
		}
        if ( alertObj.props && alertObj.props.textColor ) {
            $message.removeClass( 'alert-text' );
            $message.css( 'color', mw.getHexColor( alertObj.props.textColor ) );
        }
        var $buttonsContainer = $( '<div />' ).addClass( 'alert-buttons-container' );
        if ( alertObj.props && alertObj.props.buttonRowSpacing ) {
            $buttonsContainer.css( 'margin-top', alertObj.props.buttonRowSpacing );
        }
        var $buttonSet = alertObj.buttons || [];

        // If no button was passed display just OK button
        var buttonsNum = $buttonSet.length;
        if ( buttonsNum == 0 && !alertObj.noButtons ) {
            $buttonSet = ["OK"];
            buttonsNum++;
        }

        $.each( $buttonSet, function(i) {
            var label = this.toString();
            var $currentButton = $( '<button />' )
                .addClass( 'alert-button' )
                .text( label )
                .click( function( eventObject ) {
                    callback( eventObject );
                    embedPlayer.controlBuilder.closeAlert( alertObj.keepOverlay );
                } );
            if ( alertObj.props && alertObj.props.buttonHeight ) {
                $currentButton.css( 'height', alertObj.props.buttonHeight );
            }
            // Apply buttons spacing only when more than one is present
            if (buttonsNum > 1) {
                if (i < buttonsNum-1) {
                    if ( alertObj.props && alertObj.props.buttonSpacing ) {
                        $currentButton.css( 'margin-right', alertObj.props.buttonSpacing );
                    }
                }
            }
            $buttonsContainer.append( $currentButton );
        } )
        $container.append( $title, $message, $buttonsContainer );
        return embedPlayer.controlBuilder.displayMenuOverlay( $container, false, true );
    },

	aboutPlayerLibrary: function(){
		return $( '<div />' )
			.append(
				$( '<h2 />' )
					.text(
						gM('mwe-embedplayer-about-library')
					)
				,
				$( '<span />')
					.append(
						gM('mwe-embedplayer-about-library-desc',
							$('<a />').attr({
								'href' : mw.config.get( 'EmbedPlayer.LibraryPage' ),
								'target' : '_new'
							})
						)
					)
			);
	},
	/**
	* Get the "share" interface
	*
	* TODO share should be enabled via <embed> tag usage to be compatible
	* with sites social networking sites that allow <embed> tags but not js
	*
	* @param {Object} $target Target jQuery object to set share html
	*/
	getShare: function( ) {
		var embedPlayer = this.embedPlayer;
		var embed_code = embedPlayer.getSharingEmbedCode();
		var embed_wiki_code = embedPlayer.getWikiEmbedCode();
		var _this = this;

		var $shareInterface = $('<div />');

		var $shareList = $( '<ul />' );

		$shareList
		.append(
			$('<li />').text(
				gM( 'mwe-embedplayer-embed_site_or_blog' )
			)
			/*
			.append(
				$('<a />')
				.attr('href', '#')
				.addClass( 'active' )
				.text(
					gM( 'mwe-embedplayer-embed_site_or_blog' )
				)
			)
			*/
		);

		$shareInterface.append(
			$( '<h2 />' )
			.text( gM( 'mwe-embedplayer-share_this_video' ) )
		);

		if ( embed_wiki_code ) {
			$shareInterface.append(
				$('<ul />').append(
					$('<li />').text(
						gM( 'mwe-embedplayer-embed_wiki' )
					)
				),
				$( '<textarea />' )
				.attr( 'rows', 1 )
				.html( embed_wiki_code )
				.click( function() {
					$( this ).select();
				}),
				$('<br />')
			);
		}

		$shareInterface.append(
			$shareList
		);

		$shareInterface.append(

			$( '<textarea />' )
			.attr( 'rows', 4 )
			.html( embed_code )
			.click( function() {
				$( this ).select();
			}),

			$('<br />'),
			$('<br />')
		);
		return $shareInterface;
	},

	/**
	* Shows the Player Select interface
	*
	* @param {Object} $target jQuery target for output
	*/
	getPlayerSelect: function( ) {
		mw.log('PlayerControlBuilder::getPlayerSelect: source:' +
				this.embedPlayer.mediaElement.selectedSource.getSrc() +
				' player: ' + this.embedPlayer.selectedPlayer.id );

		var embedPlayer = this.embedPlayer;

		var _this = this;

		var $playerSelect = $('<div />')
		.append(
			$( '<h2 />' )
			.text( gM( 'mwe-embedplayer-choose_player' ) )
		);

		$.each( embedPlayer.mediaElement.getPlayableSources(), function( sourceId, source ) {

			var isPlayable = (typeof mw.EmbedTypes.getMediaPlayers().defaultPlayer( source.getMIMEType() ) == 'object' );
			var isSelected = ( source.getSrc() == embedPlayer.mediaElement.selectedSource.getSrc() );

			$playerSelect.append(
				$( '<h3 />' )
				.text( source.getTitle() )
			);

			if ( isPlayable ) {
				var $playerList = $('<ul />');
				// output the player select code:

				var supportingPlayers = mw.EmbedTypes.getMediaPlayers().getMIMETypePlayers( source.getMIMEType() );

				for ( var i = 0; i < supportingPlayers.length ; i++ ) {
					// Add link to select the player if not already selected )
					if( embedPlayer.selectedPlayer.id == supportingPlayers[i].id && isSelected ) {
						// Active player ( no link )
						var $playerLine = $( '<span />' )
						.append(
							$('<a />')
							.attr({
								'href' : '#'
							})
							.addClass( 'active')
							.text(
								supportingPlayers[i].getName()
							).click( function(){
								embedPlayer.controlBuilder.closeMenuOverlay();
								// Don't follow the # link:
								return false;
							})
						);
						//.addClass( 'ui-state-highlight ui-corner-all' ); removed by ran
					} else {
						// Non active player add link to select:
						$playerLine = $( '<a />')
							.attr({
								'href' : '#',
								'id' : 'sc_' + sourceId + '_' + supportingPlayers[i].id
							})
							.addClass( 'ui-corner-all')
							.text( supportingPlayers[i].getName() )
							.click( function() {
								var iparts = $( this ).attr( 'id' ).replace(/sc_/ , '' ).split( '_' );
								var sourceId = iparts[0];
								var player_id = iparts[1];
								mw.log( 'PlayerControlBuilder:: source id: ' + sourceId + ' player id: ' + player_id );

								embedPlayer.controlBuilder.closeMenuOverlay();

								// Close fullscreen if we are in fullscreen mode
								if( _this.isInFullScreen() ){
									_this.restoreWindowPlayer();
								}

								embedPlayer.mediaElement.setSourceByIndex( sourceId );
								var playableSources = embedPlayer.mediaElement.getPlayableSources();

								mw.EmbedTypes.getMediaPlayers().setPlayerPreference(
									player_id,
									playableSources[ sourceId ].getMIMEType()
								);

								// Issue a stop
								embedPlayer.stop();

								// Don't follow the # link:
								return false;
							} )
							.hover(
								function(){
									$( this ).addClass('active');
								},
								function(){
									$( this ).removeClass('active');
								}
							);
					}

					// Add the player line to the player list:
					$playerList.append(
						$( '<li />' ).append(
							$playerLine
						)
					);
				}

				// Append the player list:
				$playerSelect.append( $playerList );

			} else {
				// No player available:
				$playerSelect.append( gM( 'mwe-embedplayer-no-player', source.getTitle() ) );
			}
		} );

		// Return the player select elements
		return $playerSelect;
	},

	/**
	* Loads sources and calls showDownloadWithSources
	* @param {Object} $target jQuery target to output to
	*/
	showDownload: function( $target ) {
		var _this = this;
		var embedPlayer = this.embedPlayer;
		_this.showDownloadWithSources( $target );
	},

	/**
	* Shows the download interface with sources loaded
	* @param {Object} $target jQuery target to output to
	*/
	showDownloadWithSources : function( $target ) {
		var _this = this;
		mw.log( 'PlayerControlBuilder:: showDownloadWithSources::' + $target.length );
		var embedPlayer = this.embedPlayer;
		// Empty the target:
		$target.empty();
		$target.append( $('<div />') );
		$target = $target.find('div');

		var $mediaList = $( '<ul />' );
		var $textList =  $( '<ul />' );
		$.each( embedPlayer.mediaElement.getSources(), function( index, source ) {
			if( source.getSrc() ) {
				mw.log("showDownloadWithSources:: Add src: " + source.getTitle() );
				var fileName = source.mwtitle;
				if ( !fileName ) {
					var path = new mw.Uri( source.getSrc() ).path;
					var pathParts = path.split( '/' );
					fileName = pathParts[ pathParts.length -1 ];
				}
				var $dlLine = $( '<li />').append(
					$('<a />')
					.attr( {
						'href': source.getSrc(),
						'download': fileName
					})
					.text( source.getTitle() )
				);
				// Add link to correct "bucket"

				//Add link to time segment:
				if ( source.getSrc().indexOf( '?t=' ) !== -1 ) {
					$target.append( $dlLine );
				} else if ( this.getMIMEType().indexOf('text') === 0 ) {
					// Add link to text list
					$textList.append( $dlLine );
				} else {
					// Add link to media list
					$mediaList.append( $dlLine );
				}

			}
		} );
		if( $mediaList.find('li').length != 0 ) {
			$target.append(
				$('<h2 />')
				.text( gM( 'mwe-embedplayer-download_full' ) ),
				$mediaList
			);
		}

		if( $textList.find('li').length != 0 ) {
			$target.append(
				$('<h2 />')
				.html( gM( 'mwe-embedplayer-download_text' ) ),
				$textList
			);
		}
	},
	getSwitchSourceMenu: function(){
		var _this = this;
		var embedPlayer = this.embedPlayer;
		// for each source with "native playback"
		var $sourceMenu = $('<ul />');

		// Local function to closure the "source" variable scope:
		function addToSourceMenu( source ){
			// Check if source is selected:
			var icon = ( source.getSrc() == embedPlayer.mediaElement.selectedSource.getSrc() ) ? 'bullet' : 'radio-on';
			$sourceMenu.append(
				$.getLineItem( source.getShortTitle() , icon, function(){
					mw.log( 'PlayerControlBuilder::SwitchSourceMenu: ' + source.getSrc() );
					// update menu selecting parent li siblings
					$( this ).parent().siblings().find('span.ui-icon').removeClass( 'ui-icon-bullet').addClass( 'ui-icon-radio-on' );
					$( this ).find('span.ui-icon').removeClass( 'ui-icon-radio-on').addClass( 'ui-icon-bullet' );
					// update control bar text
					embedPlayer.getInterface().find( '.source-switch' ).text( source.getShortTitle() );


					// TODO this logic should be in mw.EmbedPlayer
					embedPlayer.mediaElement.setSource( source );
					if( ! _this.embedPlayer.isStopped() ){
						// Get the exact play time from the video element ( instead of parent embed Player )
						var oldMediaTime = _this.embedPlayer.getPlayerElement().currentTime;
						var oldPaused =  _this.embedPlayer.paused;
						// Do a live switch
						embedPlayer.playerSwitchSource( source, function( vid ){
							// issue a seek
							embedPlayer.setCurrentTime( oldMediaTime, function(){
								// reflect pause state
								if( oldPaused ){
									embedPlayer.pause();
								}
							} );
						});
					}
				})
			);
		}
		$.each( this.embedPlayer.mediaElement.getPlayableSources(), function( sourceIndex, source ) {
			// Output the player select code:
			var supportingPlayers = mw.EmbedTypes.getMediaPlayers().getMIMETypePlayers( source.getMIMEType() );
			for ( var i = 0; i < supportingPlayers.length ; i++ ) {
				if( supportingPlayers[i].library == 'Native' ){
					addToSourceMenu( source );
				}
			}
		});
		return $sourceMenu;
	},

	/**
	* Get component
	*
	* @param {String} componentId Component key to grab html output
	*/
	getComponent: function( componentId ) {
		if ( this.components[ componentId ] ) {
			return this.components[ componentId ].o( this );
		} else {
			return false;
		}
	},

	/**
	 * Get a component height
	 *
	 * @param {String} componentId Component key to grab height
	 * @return height or false if not set
	 */
	getComponentHeight: function( componentId ) {
		if ( this.components[ componentId ]
			&& this.components[ componentId ].h )
		{
			return this.components[ componentId ].h;
		}
		return 0;
	},

	/**
	* Get a component width
	* @param {String} componentId Component key to grab width
	* @return width or false if not set
	*/
	getComponentWidth: function( componentId ){
		if ( this.components[ componentId ]
			&& this.components[ componentId ].w )
		{
			return this.components[ componentId ].w;
		}
		return 0;
	},

	// Set up the disable playhead function:
	// TODO this will move into the disableSeekBar binding in the new theme framework
	disableSeekBar : function(){
		var $playHead = this.embedPlayer.getInterface().find( ".play_head" );
		if( $playHead.length ){
			$playHead.slider( "option", "disabled", true );
		}
	},
	enableSeekBar : function(){
		var $playHead = this.embedPlayer.getInterface().find( ".play_head" );
		if( $playHead.length ){
			$playHead.slider( "option", "disabled", false);
		}
	},

	/**
	* Components Object
	* Take in the embedPlayer and return some html for the given component.
	*
	* components can be overwritten by skin javascript
	*
	* Component JSON structure is as follows:
	* 'o' Function to return a binded jQuery object ( accepts the ctrlObject as a parameter )
	* 'w' The width of the component
	* 'h' The height of the component ( if height is undefined the height of the control bar is used )
	* 'position' elements are inserted into the dom based on component order and available space.
	*  if the element is inserted, position is then used to set relative dom insert order. 
	*/
	components: {
		/**
		* The pause / play button
		*/
		'pause': {
			'w': 28,
			'position': 1,
			'o': function( ctrlObj ) {
				return $( '<div />' )
						.attr( 'title', gM( 'mwe-embedplayer-play_clip' ) )
						.addClass ( "ui-state-default ui-corner-all ui-icon_link lButton play-btn" )
						.append(
							$( '<span />' )
							.addClass( "ui-icon ui-icon-play" )
						)
						// Play / pause binding
						.buttonHover()
						.click( function() {
							ctrlObj.embedPlayer.play();
							// Don't follow the # link:
							return false;
						});
			}
		},

		/**
		* The volume control interface html
		*/
		'volumeControl': {
			'w' : 28,
			'position': 7,
			'o' : function( ctrlObj ) {
				mw.log( 'PlayerControlBuilder::Set up volume control for: ' + ctrlObj.embedPlayer.id );
				var $volumeOut = $( '<span />' );
				if ( ctrlObj.volumeLayout == 'horizontal' ) {
					$volumeOut.append(
						$( '<div />' )
						.addClass( "ui-slider ui-slider-horizontal rButton volume-slider" )
					);
				}

				// Add the volume control icon
				$volumeOut.append(
				 	$('<div />')
				 	.attr( 'title', gM( 'mwe-embedplayer-volume_control' ) )
				 	.addClass( "ui-state-default ui-corner-all ui-icon_link rButton volume_control" )
				 	.append(
				 		$( '<span />' )
				 		.addClass( "ui-icon ui-icon-volume-on" )
				 	)
				 );
				if ( ctrlObj.volumeLayout == 'vertical' ) {
					$volumeOut.find('.volume_control').append(
						$( '<div />' )
						.hide()
						.addClass( "vol_container ui-corner-all" )
						.append(
							$( '<div />' )
							.addClass ( "volume-slider" )
						)
					);
				}
				//Return the inner html
				return $volumeOut.html();
			}
		},

		/**
		* The large play button in center of the player
		*/
		'playButtonLarge': {
			'w' : 70,
			'h' : 53,
			'position': 2,
			'o' : function( ctrlObj ) {
				return $( '<div />' )
					.attr( {
						'title'	: gM( 'mwe-embedplayer-play_clip' ),
						'class'	: "play-btn-large"
					} )
					// Get dynamic position for big play button
					.css( ctrlObj.getPlayButtonPosition() )
					// Add play hook:
					.click( function() {
						ctrlObj.embedPlayer.play();
						return false; // Event Stop Propagation
					} );
			}
		},

		/**
		* The Attribution button ( by default this is kaltura-icon
		*/
		'attributionButton' : {
			'w' : 28,
			'position': 3,
			'o' : function( ctrlObj ){
				var buttonConfig = mw.config.get( 'EmbedPlayer.AttributionButton');
				// Check for source ( by configuration convention this is a 16x16 image
				if( buttonConfig.iconurl ){
					var $icon =  $('<img />')
						.attr('src', buttonConfig.iconurl );
				} else {
					var $icon = $('<span />')
					.addClass( 'ui-icon' );
					if( buttonConfig['class'] ){
						$icon.addClass( buttonConfig['class'] );
					}
				}
				if( typeof buttonConfig.style != 'object'){
					buttonConfig.style = {};
				}
				// update the configured size of the attribution button if we have a specific width configured
				if( buttonConfig.style.width ){
					this.w = parseInt( buttonConfig.style.width );
				} else {
					 buttonConfig.style.width = parseInt( this.w ) + 'px';
				}

				return $( '<div />' )
						.addClass( 'rButton' )
						.css({
							'top' : '1px',
							'left' : '2px'
						})
						// Allow button config style to override
						.css( buttonConfig.style )
						.append(
							$('<a />')
							.attr({
								'href': buttonConfig.href,
								'title' : buttonConfig.title,
								'target' : '_new'
							})
							.append( $icon )
				);
			}
		},

		/**
		* The options button, invokes display of the options menu
		*/
		'options': {
			'w': 28,
			'position': 10,
			'o': function( ctrlObj ) {
				return $( '<div />' )
						.attr( 'title', gM( 'mwe-embedplayer-player_options' ) )
						.addClass( 'ui-state-default ui-corner-all ui-icon_link rButton options-btn' )
						.append(
							$('<span />')
							.addClass( 'ui-icon ui-icon-wrench' )
						)
						.buttonHover()
						// Options binding:
						.embedMenu( {
							'content' : ctrlObj.getOptionsMenu(),
							'zindex' : mw.config.get( 'EmbedPlayer.FullScreenZIndex' ) + 2,
							'positionOpts': {
								'directionV' : 'up',
								'offsetY' : 30,
								'directionH' : 'left',
								'offsetX' : -28
							}
						} );
			}
		},

		/**
		* The fullscreen button for displaying the video fullscreen
		*/
		'fullscreen': {
			'w': 24,
			'position': 8,
			'o': function( ctrlObj ) {
				var $btn = $( '<div />' )
						.attr( 'title', gM( 'mwe-embedplayer-player_fullscreen' ) )
						.addClass( "ui-state-default ui-corner-all ui-icon_link rButton fullscreen-btn" )
						.append(
							$( '<span />' )
							.addClass( "ui-icon ui-icon-arrow-4-diag" )
						)
						// Fullscreen binding:
						.buttonHover();
				// Link out to another window if iPad 3x ( broken iframe resize )
				if( (
						mw.config.get('EmbedPlayer.IsIframeServer')
						&&
						mw.isIpad3()
					)
						||
					  mw.config.get( "EmbedPlayer.NewWindowFullscreen" )
					  	||
					( mw.config.get('EmbedPlayer.IsIframeServer')  && mw.config.get('EmbedPlayer.EnableIframeApi') === false )
				){
					// Get the iframe url:
					var url = ctrlObj.embedPlayer.getIframeSourceUrl();
					// Change button into new window ( of the same url as the iframe ) :
					return	$('<a />').attr({
							'href': url,
							'target' : '_new'
						})
						.click(function(){
							// Update the url:
							var url = $(this).attr('href');
							var iframeMwConfig = {};

							iframeMwConfig['EmbedPlayer.IsFullscreenIframe'] = true;
							// add a seek offset:
							iframeMwConfig['EmbedPlayer.IframeCurrentTime'] =  ctrlObj.embedPlayer.currentTime;
							// add play state:
							iframeMwConfig['EmbedPlayer.IframeIsPlaying'] = ctrlObj.embedPlayer.isPlaying();

							// Append the configuration and request domain to the iframe hash:

							// Add the parentUrl to the iframe config:
							iframeMwConfig['EmbedPlayer.IframeParentUrl'] = document.URL;

							url += '#' + encodeURIComponent(
								JSON.stringify({
									'mwConfig' :iframeMwConfig,
									'playerId' : playerId
								})
							);
							ctrlObj.embedPlayer.pause();
							// try and do a browser popup:
							var newwin = window.open(
								 url,
								 ctrlObj.embedPlayer.id,
								 // Fullscreen window params:
								'width=' + screen.width +
								', height=' + ( screen.height - 90 ) +
								', top=0, left=0' +
								', fullscreen=yes'
							);
							// if for some reason we could not open the window run the href link:
							if( newwin === null){
								return true;
							}
							if ( window.focus ) {
								newwin.focus();
							}
							// Else do not follow the href link
							return false;
						})
						.append($btn);
				} else {
					return $btn.click( function() {
						ctrlObj.embedPlayer.fullscreen();
					} );
				}
			}
		},

		'sourceSwitch' : {
			'w' : 70,
			'position': 9,
			'o' : function( ctrlObj ){
				var $menuContainer = $('<div />').addClass( 'swMenuContainer' ).hide();
				ctrlObj.embedPlayer.getInterface().append(
						$menuContainer
				)
				// Stream switching widget ( display the current selected stream text )
				return $( '<div />' )
					.addClass('ui-widget source-switch')
					.append(
						ctrlObj.embedPlayer.mediaElement.selectedSource.getShortTitle()
					).embedMenu( {
						'content' : ctrlObj.getSwitchSourceMenu(),
						'zindex' : mw.config.get( 'EmbedPlayer.FullScreenZIndex' ) + 2,
						'keepPosition' : true,
						'targetMenuContainer' : $menuContainer,
						'width' : 130,
						'showSpeed': 0,
						'createMenuCallback' : function(){
							var $interface = ctrlObj.embedPlayer.getInterface();
							var $sw = $interface.find( '.source-switch' );
							var $swMenuContainer = $interface.find('.swMenuContainer');
							var height = $swMenuContainer.find( 'li' ).length * 30;
							// position from top ( unkown why we can't use bottom here )
							var top = $interface.height() - height - ctrlObj.getHeight() - 6;
							$menuContainer.css({
								'position' : 'absolute',
								'left': $sw[0].offsetLeft,
								'top' : top,
								'bottom': ctrlObj.getHeight(),
								'height' : height
							})
							ctrlObj.showControlBar( true );
						},
						'closeMenuCallback' : function(){
							ctrlObj.restoreControlsHover()
						}
					} );
			}
		},
		/*
		* The time display area
		*/
		'timeDisplay': {
			'w' : mw.config.get( 'EmbedPlayer.TimeDisplayWidth' ),
			'position': 6,
			'o' : function( ctrlObj ) {
				return $( '<div />' )
				.addClass( "ui-widget time-disp" )
				.append(
					ctrlObj.embedPlayer.getTimeRange()
				);
			}
		},

		/**
		* The playhead component
		*/
		'playHead': {
			'w':0, // special case (takes up remaining space)
			'position': 5,
			'o':function( ctrlObj ) {

				var sliderConfig = {
						range: "min",
						value: 0,
						min: 0,
						max: 1000,
						start: function( event, ui ) {
							var id = ( embedPlayer.pc != null ) ? embedPlayer.pc.pp.id:embedPlayer.id;
							embedPlayer.userSlide = true;
							$( id + ' .play-btn-large' ).fadeOut( 'fast' );
							// If playlist always start at 0
							embedPlayer.startTimeSec = ( embedPlayer.instanceOf == 'mvPlayList' ) ? 0:
											mw.npt2seconds( embedPlayer.getTimeRange().split( '/' )[0] );
						},
						slide: function( event, ui ) {
							var perc = ui.value / 1000;
							embedPlayer.jumpTime = mw.seconds2npt( parseFloat( parseFloat( embedPlayer.getDuration() ) * perc ) + embedPlayer.startTimeSec );
							// mw.log('perc:' + perc + ' * ' + embedPlayer.getDuration() + ' jt:'+ this.jumpTime);
							if ( _this.longTimeDisp ) {
								ctrlObj.setStatus( gM( 'mwe-embedplayer-seek_to', embedPlayer.jumpTime ) );
							} else {
								ctrlObj.setStatus( embedPlayer.jumpTime );
							}
							// Update the thumbnail / frame
							if ( embedPlayer.isPlaying == false ) {
								embedPlayer.updateThumbPerc( perc );
							}
						},
						change: function( event, ui ) {
							// Only run the onChange event if done by a user slide
							// (otherwise it runs times it should not)
							if ( embedPlayer.userSlide ) {
								embedPlayer.userSlide = false;
								embedPlayer.seeking = true;

								var perc = ui.value / 1000;
								// set seek time (in case we have to do a url seek)
								embedPlayer.seekTimeSec = mw.npt2seconds( embedPlayer.jumpTime, true );
								mw.log( 'PlayerControlBuilder:: seek to: ' + embedPlayer.jumpTime + ' perc:' + perc + ' sts:' + embedPlayer.seekTimeSec );
								ctrlObj.setStatus( gM( 'mwe-embedplayer-seeking' ) );
								if( embedPlayer.isStopped() ){
									embedPlayer.play();
								}
								embedPlayer.seek( perc );
							}
						}
					};

				var embedPlayer = ctrlObj.embedPlayer;
				var _this = this;
				var $playHead = $( '<div />' )
					.addClass ( "play_head" )
					.css({
						"position" : 'absolute',
						"left" : '33px',
						"right" : ( ( embedPlayer.getPlayerWidth() - ctrlObj.availableWidth - 33 ) ) + 'px'
					})
					// Playhead binding
					.slider( sliderConfig );

				// Up the z-index of the default status indicator:
				$playHead.find( '.ui-slider-handle' ).css( 'z-index', 4 );
				$playHead.find( '.ui-slider-range' ).addClass( 'ui-corner-all' ).css( 'z-index', 2 );

				// Add buffer html:
				$playHead.append(
					$('<div />')
					.addClass( "ui-slider-range ui-slider-range-min ui-widget-header")
					.addClass( "ui-state-highlight ui-corner-all mw_buffer")
				);

				return $playHead;
			}
		}
	}
};

} )( window.mediaWiki, window.jQuery );
