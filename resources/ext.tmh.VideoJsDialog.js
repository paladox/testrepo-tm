( function ( $, mw, OO ) {
	// Subclass Dialog class. Note that the OOjs inheritClass() method extends the parent constructor's
	// prototype and static methods and properties to the child constructor.
	mw.VideoJsDialog = function VideoJsDialog( config ) {
		// Config initialization
		config = $.extend( {}, config );

		this.$mediaElement = config.$mediaElement.clone();

		mw.VideoJsDialog[ 'super' ].call( this, config );
		this.$element.addClass( 'oo-ui-videoJsDialog' );
	};
	OO.inheritClass( mw.VideoJsDialog, OO.ui.Dialog );

	// Specify a title statically (or, alternatively, with data passed to the opening() method).
	mw.VideoJsDialog[ 'static' ].name = 'videoJsDialog';
	mw.VideoJsDialog[ 'static' ].title = 'Simple dialog';

	// Customize the initialize() function: This is where to add content to the dialog body and set up event handlers.
	mw.VideoJsDialog.prototype.initialize = function () {
		// Call the parent method
		mw.VideoJsDialog[ 'super' ].prototype.initialize.call( this );

		// properties
		this.$navigation = $( '<div>' );
		// initialization
		this.closeButton = new OO.ui.ButtonWidget( { framed: false, icon: 'close' } );
		this.closeButton.connect( this, { click: 'onCloseButtonClick' } );
		this.closeButton.$element.addClass( 'oo-ui-videoJsDialog-closeButton' );
		this.$navigation
			.addClass( 'oo-ui-videoJsDialog-navigation' )
			.append( this.title.$element, this.closeButton.$element );
		this.$head.append( this.$navigation );
		this.$body.append( this.$mediaElement );
	};

	mw.VideoJsDialog.prototype.setPlayer = function ( player ) {
		this.player = player;
	};
	mw.VideoJsDialog.prototype.getPlayer = function () {
		if ( !this.player ) {
			console.error( 'Need to fix this somehow' );
			return;
		}
		return this.player;
	};

	// Override the getBodyHeight() method to specify a custom height (or don't to use the automatically generated height)
	mw.VideoJsDialog.prototype.getBodyHeight = function () {
		// Base our initial height on the Aspectratio of the original player
		// Account for the 1px border on the dialog.
		return ( ( this.getPlayer().dimension( 'height' ) / this.getPlayer().dimension( 'width' ) )
			* ( OO.ui.WindowManager[ 'static' ].sizes.larger.width ) );
	};
	mw.VideoJsDialog.prototype.onCloseButtonClick = function () {
		this.close();
	};
} )( jQuery, mediaWiki, OO );
