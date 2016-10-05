( function ( mw, $ ) {

  $( document ).on( 'mmv-metadata.tmh', function ( e ) {
    var formats = [ 'ogg', 'oga', 'ogv', 'ogm', 'webm', 'mp4', 'm4a', 'm4v' ];
    var extension = e.image.filePageTitle.ext;
    if ( $.inArray( extension, formats ) !== -1 ) {
      // do stuff
      console.log('e.viewer', e.viewer);
      console.log('e.image', e.image);
      console.log('e.imageInfo', e.imageInfo);
      /*
      e.viewer
      e.imageInfo.url
      this.progressBar = viewer.ui.panel.progressBar;
      this.$container = viewer.ui.canvas.$imageDiv;
      var width = $( window ).width(),
        height = this.viewer.ui.canvas.$imageWrapper.height();
      */

      var viewer = e.viewer,
        image = e.image,
        $container = viewer.ui.canvas.$imageDiv;

      var iframe = document.createElement( 'iframe' ),
        $iframe = $( iframe );
      $iframe.attr( {
        width: $container.width(),
        height: $container.height(),
        border: 0,
        src: image.filePageLink + '?embedplayer=yes'
      } );
      
      $container.empty().append( $iframe );
    }
  } );

}( mediaWiki, jQuery ) );
