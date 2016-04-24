( function ( $, mw, OO ) {
	// Subclass Dialog class. Note that the OOjs inheritClass() method extends the parent constructor's
	// prototype and static methods and properties to the child constructor.
	mw.VideoDialog = function VideoDialog( config ) {
		// Config initialization
		config = $.extend( {}, config );

		mw.VideoDialog[ 'super' ].call( this, config );
		this.$element.addClass( 'oo-ui-videoDialog' );
	}
	OO.inheritClass( mw.VideoDialog, OO.ui.Dialog );

	// Specify a title statically (or, alternatively, with data passed to the opening() method).
	mw.VideoDialog[ 'static' ].name = 'videoDialog';
	mw.VideoDialog[ 'static' ].title = 'Simple dialog';

	// Customize the initialize() function: This is where to add content to the dialog body and set up event handlers.
	mw.VideoDialog.prototype.initialize = function () {
		// Call the parent method
		mw.VideoDialog[ 'super' ].prototype.initialize.call( this );

		// properties
		this.$navigation = $( '<div>' );
		// initialization
		this.closeButton = new OO.ui.ButtonWidget( { framed: false, icon: 'close' } );
		this.closeButton.connect( this, { click: 'onCloseButtonClick' } );
		this.closeButton.$element.addClass( 'oo-ui-videodialog-closeButton' );
		this.$navigation
			.addClass( 'oo-ui-videodialog-navigation' )
			.append( this.title.$element, this.closeButton.$element );
		this.$head.append( this.$navigation );
		this.$body.append( this.data.el() );
	};

	// Override the getBodyHeight() method to specify a custom height (or don't to use the automatically generated height)
	mw.VideoDialog.prototype.getBodyHeight = function () {
		// Base our initial height on the Aspectratio of the original player
		// Account for the 1px border on the dialog.
		return ( ( this.data.dimension( 'height' ) / this.data.dimension( 'width' ) )
			* ( OO.ui.WindowManager[ 'static' ].sizes.larger.width ) );
	};
	mw.VideoDialog.prototype.onCloseButtonClick = function () {
		this.close();
	};
} )( jQuery, mediaWiki, OO );