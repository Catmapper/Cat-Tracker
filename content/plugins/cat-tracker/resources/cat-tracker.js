(function($) {

	function catMap( map_args ) {

		var click_count = 0;
		load_map( map_args.map_id );

		function load_map( selector ) {

			var default_layer = L.tileLayer( cat_tracker_vars.map_source, {attribution : cat_tracker_vars.map_attribution} );

			if ( cat_tracker_vars.is_submission_mode ) {

				map = L.map( selector, {
					center : [map_args.map_latitude, map_args.map_longitude],
					layers : [default_layer],
					zoom : map_args.map_zoom_level,
					maxBounds : get_max_bounds()
				});

				map.on( 'click', capture_click );

			} else {

				map = L.map( selector, {
					center : [map_args.map_latitude, map_args.map_longitude],
					layers : [default_layer],
					zoom : map_args.map_zoom_level,
					maxZoom : map_args.map_max_zoom_level,
					maxBounds : get_max_bounds()
				});
				build_markers( $.parseJSON( map_args.markers ) );

			}

		}

		function get_max_bounds() {
			var south_west_bounds = new L.LatLng( map_args.map_south_bounds, map_args.map_west_bounds ),
	  			north_east_bounds = new L.LatLng( map_args.map_north_bounds, map_args.map_east_bounds );
			return new L.LatLngBounds( south_west_bounds, north_east_bounds );
		}

		function build_markers( sightings ) {
			var markers = new L.MarkerClusterGroup();
			_.each( sightings, function( sighting ){
				markers.addLayer( L.marker( [sighting.latitude, sighting.longitude] ).bindPopup( sighting.text ) );
			});
			map.addLayer( markers );
		}

		function capture_click( e ) {
			click_count++;
			setTimeout( function(){
				if ( click_count != 1 ) {
					click_count = 0;
					return;
				}
				click_count = 0;

	    	L.popup()
	    		.setLatLng( e.latlng )
	        .setContent( cat_tracker_vars.new_submission_popup_text )
	        .openOn( map );
	      $( '#cat-tracker-submisison-latitude' ).val( e.latlng.lat );
  	    $( '#cat-tracker-submisison-longitude' ).val( e.latlng.lng );
			}, 200 );
		}


	}

	jQuery( document ).ready(function($){

		_.each( cat_tracker_vars.maps, function( map_args ){
			var cat_map = new catMap( map_args );
		});

	});

})(jQuery);