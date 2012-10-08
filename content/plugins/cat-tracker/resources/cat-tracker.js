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
		map_zoom_level : cat_tracker_vars.map_zoom_level,
		sightings : $.parseJSON( cat_tracker_vars.markers ),

		load_map : function( selector ) {

			default_layer = L.tileLayer( cat_tracker.map_source, {attribution : cat_tracker.map_attribution} );
			cat_tracker.markers = cat_tracker.build_markers( cat_tracker.sightings );
			console.log( cat_tracker.markers );

			cat_tracker.map = L.map(selector, {
				center : [cat_tracker.map_latitude, cat_tracker.map_longitude],
				layers : [default_layer, cat_tracker.markers],
				zoom : cat_tracker.map_zoom_level,
				maxBounds : cat_tracker.get_max_bounds()
			});

		},

		get_max_bounds : function() {
			var south_west_bounds = new L.LatLng( cat_tracker.map_south_bounds, cat_tracker.map_west_bounds ),
	  			north_east_bounds = new L.LatLng( cat_tracker.map_north_bounds, cat_tracker.map_east_bounds );
			return new L.LatLngBounds( south_west_bounds, north_east_bounds );
		},

		build_markers : function( sightings ) {
			var markers = new Array();
			_.each( sightings, function( sighting ){
				markers.push( L.marker( [sighting.latitude, sighting.longitude], { title : sighting.title } ).bindPopup( sighting.text ) );
			});
			return L.layerGroup( markers );
		},

		capture_click : function( e ) {
			console.log( e.latlng );
		}


	};

	jQuery( document ).ready(function($){

		cat_tracker.load_map( 'map' );

		cat_tracker.map.on( 'click', function( e ){
			cat_tracker.capture_click( e );
		});

	});

})(jQuery);