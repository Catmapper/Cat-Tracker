(function($) {

	function catMap( map_args ) {

		var click_count = 0;
		var submission_marker = {};
		var default_layer = {};
		var active_marker_layers = {};
		var all_marker_layers = {};
		var marker_cluster = {};
		load_map( map_args.map_id );

		function load_map( selector ) {

			var default_layer = L.tileLayer( cat_tracker_vars.map_source, {attribution : cat_tracker_vars.map_attribution} );

 			// map for submission mode doesn't contain any markers
			if ( cat_tracker_vars.is_submission_mode ) {

				map = L.map( selector, {
					center : [map_args.map_latitude, map_args.map_longitude],
					layers : [default_layer],
					zoom : map_args.map_zoom_level,
					maxBounds : get_max_bounds()
				});

				map.on( 'click', capture_submission_click );

			} else {

				map = L.map( selector, {
					center : [map_args.map_latitude, map_args.map_longitude],
					layers : [default_layer],
					zoom : map_args.map_zoom_level,
					maxZoom : map_args.map_max_zoom_level,
					maxBounds : get_max_bounds(),
					zoomControl : false
				});
				build_markers( $.parseJSON( map_args.markers ) );
			}

	    map.addControl( new L.Control.ZoomFS() );

		}

		function get_max_bounds() {
			var south_west_bounds = new L.LatLng( map_args.map_south_bounds, map_args.map_west_bounds ),
	  			north_east_bounds = new L.LatLng( map_args.map_north_bounds, map_args.map_east_bounds );
			return new L.LatLngBounds( south_west_bounds, north_east_bounds );
		}

		function build_markers( marker_types ) {

			// loop the marker types/marker array and create a marker object for each marker
			$.each( marker_types, function( marker_type, marker_data ){
				var icon = L.divIcon( { className: 'cat-tracker-map-icon icon-' + marker_type } );
				var markers = new Array();
				$.each( marker_data.sightings, function( i, sighting ){
					markers.push( L.marker( [sighting.latitude, sighting.longitude], {icon: icon} ).bindPopup( sighting.text ) );
				});

				// group the markers by type
				active_marker_layers[marker_type] = L.layerGroup( markers );
				all_marker_layers[marker_type] = L.layerGroup( markers );
			});

			// create a cluster or all the markers combined
			marker_cluster = new L.MarkerClusterGroup();
			$.each( active_marker_layers, function( i, marker_group ){
					marker_cluster.addLayer( marker_group );
			});

			// add the cluster as a layer to the map
			map.addLayer( marker_cluster );
			init_legend();
		}

		function init_legend() {
			$( '#cat-tracker-custom-controls' ).appendTo( '.leaflet-top.leaflet-right' );
			$( '#cat-tracker-custom-controls' ).on( 'change.cat_tracker_controls', '.cat-tracker-layer-control', function( e ) {

				var marker_type = $( this ).data( 'marker-type' );
				var add = $( this ).prop( 'checked' );

				// add or remove the (un)checked type from the active layers
				if ( add ) {
					active_marker_layers[marker_type] = all_marker_layers[marker_type];
				} else {
					delete active_marker_layers[marker_type];
				}

				// remove the cluster layer from the map
				map.removeLayer( marker_cluster );

				// rebuild the cluster
				marker_cluster = new L.MarkerClusterGroup();
				$.each( active_marker_layers, function( i, marker_group ){
					marker_cluster.addLayer( marker_group );
				});

				// re-add the cluster as a layer to the map
				map.addLayer( marker_cluster );

			});
		}

		function capture_submission_click( e ) {
			click_count++;
			setTimeout( function(){
				if ( click_count != 1 ) {
					click_count = 0;
					return;
				}
				click_count = 0;

				if ( _.isEmpty( submission_marker ) ) {
	      	submission_marker = new L.Marker( e.latlng , { title : cat_tracker_vars.new_submission_popup_text, clickable : true, draggable : true } ).addTo( map );
		     	submission_marker.on( 'dragend', capture_submission_marker_drag_end );
	     	} else {
	     		submission_marker.setLatLng( e.latlng )
	     	}
	      $( '#cat-tracker-submisison-latitude' ).val( e.latlng.lat );
  	    $( '#cat-tracker-submisison-longitude' ).val( e.latlng.lng );
			}, 200 );
		}

		function capture_submission_marker_drag_end( e ) {
      $( '#cat-tracker-submisison-latitude' ).val( e.target._latlng.lat );
	    $( '#cat-tracker-submisison-longitude' ).val( e.target._latlng.lng );
		}


	}

	jQuery( document ).ready(function($){

		$.each( cat_tracker_vars.maps, function( i, map_args ){
			var cat_map = new catMap( map_args );
		});

	});

})(jQuery);