(function($) {

	var cat_tracker = {

		map : {},
		map_source : cat_tracker_vars.map_source,
		map_attribution : cat_tracker_vars.map_attribution,
		map_latitude : cat_tracker_vars.map_latitude,
		map_longitude : cat_tracker_vars.map_longitude,
		map_north_bounds : cat_tracker_vars.map_north_bounds,
		map_south_bounds : cat_tracker_vars.map_south_bounds,
		map_west_bounds : cat_tracker_vars.map_west_bounds,
		map_east_bounds : cat_tracker_vars.map_east_bounds,
		map_zoom_level :  cat_tracker_vars.map_zoom_level,

		load_map : function( selector ) {

			cat_tracker.map = L.map(selector, {
				center : [cat_tracker.map_latitude, cat_tracker.map_longitude],
				zoom : cat_tracker.map_zoom_level,
				maxBounds : cat_tracker.get_max_bounds()
			});

			L.tileLayer( cat_tracker.map_source, {
			    attribution : cat_tracker.map_attribution
			}).addTo( cat_tracker.map );


			cat_tracker.load_initial_markers();


		},

		load_initial_markers : function() {

		},

		get_max_bounds : function() {
			var south_west_bounds = new L.LatLng( cat_tracker.map_south_bounds, cat_tracker.map_west_bounds ),
	  			north_east_bounds = new L.LatLng( cat_tracker.map_north_bounds, cat_tracker.map_east_bounds );
			return new L.LatLngBounds( south_west_bounds, north_east_bounds );
		},

		// capture_click : function( event ) {
		// 	event.latLng;
		// }


	};

	jQuery( document ).ready(function($){

		cat_tracker.load_map( 'map' );

		// cat_tracker.map.on( 'click', function( event ){
		// 	cat_tracker.capture_click( event );
		// });

	});

})(jQuery);