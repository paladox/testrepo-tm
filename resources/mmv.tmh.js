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
      viewer.ui.panel.progressBar;
      */

      var viewer = e.viewer,
        image = e.image,
        $container = viewer.ui.canvas.$imageDiv;

      var $placeholder = $( image.thumbnail ).closest( '.media-placeholder' ),
        payload = $placeholder.attr( 'videopayload' ),
        $payload = $( payload );

      $container
        .empty()
        .append( $payload )
        .find( 'video,audio' )
          .loadVideoPlayer();

      var id = $container.find( 'div.video-js' ).attr( 'id' );
      if ( id ) {
        viewer.ui.panel.progressBar.animateTo(25);

        var player = videojs( id );
        player.ready(function() {
          viewer.ui.panel.progressBar.hide();

          // @TODO only play when explicit click?
          // @FIXME this is not working with ogv.js in Safari
          player.play();
        });
      }
    }
  } );

}( mediaWiki, jQuery ) );
