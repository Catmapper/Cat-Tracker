(function($) {

	function catMap( map_args ) {

		var click_count = 0;
		var submission_marker = {};
		var default_layer = {};
		var active_marker_layers = {};
		var all_marker_layers = {};
		var all_marker_layers_by_attribute = {};
		var marker_cluster = {};
		var sortable_attributes = {};
		var markers_by_type = {};
		var address_ajax_request = false;
		var process_address_ajax_request = false;
		var process_coordinates_ajax_request = false;
		var address_typing_timer = false;
		var coordinates_typing_timer = false;
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

				enable_submission_click();

			} else {

				map = L.map( selector, {
					center : [map_args.map_latitude, map_args.map_longitude],
					layers : [default_layer],
					zoom : map_args.map_zoom_level,
					maxZoom : map_args.map_max_zoom_level,
					zoomControl : false
				});

				if ( undefined != typeof( map_args.markers ) && ! _.isEmpty( map_args.markers ) && 'null' != map_args.markers ) {
					if ( cat_tracker_vars.do_sorting ) {
						sortable_attributes = cat_tracker_vars.sortable_attributes;
						build_sortable_attributes();
					}

					markers_by_type = $.parseJSON( map_args.markers );
					build_markers();
				}
			}

			if ( ! map_args.ignore_boundaries )
				map.setMaxBounds( get_max_bounds() );

	    map.addControl( new L.Control.ZoomFS() );

  		if ( cat_tracker_vars.is_admin_submission_mode ) {
				$( '#marker_geo_information' ).on( 'click.cat_tracker_relocate', '#cat-tracker-relocate', function(e){
					e.preventDefault();
					enable_submission_click();
					$(this).attr( 'id', 'cat-tracker-relocate-done' ).attr( 'name', 'cat-tracker-relocate-done' ).val( cat_tracker_vars.relocate_done_text );
				});

				$( '#marker_geo_information' ).on( 'click.cat_tracker_relocate', '#cat-tracker-relocate-done', function(e){
					e.preventDefault();
					disable_submission_click();
					$(this).attr( 'id', 'cat-tracker-relocate' ).attr( 'name', 'cat-tracker-relocate' ).val( cat_tracker_vars.relocate_text );
				});

				$( '#marker_geo_information' ).on( 'keyup', cat_tracker_vars.submission_latitude_selector + ', ' + cat_tracker_vars.submission_longitude_selector, function(e){
					e.preventDefault();
					disable_submission_click();

					$( cat_tracker_vars.publish_button_selector ).prop( 'disabled', true );
					$( '#cat-tracker-relocate' ).prop( 'disabled', true );
					$( cat_tracker_vars.submission_address_selector ).prop( 'readonly', true );
					$( cat_tracker_vars.submission_map_selector ).addClass( 'reloading' );

					clearTimeout( coordinates_typing_timer );

					coordinates_typing_timer = setTimeout( process_coordinates_change, 2000 );
				});
			}

			$( '#marker_geo_information, #cat-tracker-new-submission' ).on( 'keyup', cat_tracker_vars.submission_address_selector, function(e){
				e.preventDefault();
				if ( ! cat_tracker_vars.is_submission_mode )
					disable_submission_click();

				$( cat_tracker_vars.publish_button_selector ).prop( 'disabled', true );
				$( '#cat-tracker-relocate' ).prop( 'disabled', true );
				$( cat_tracker_vars.submission_latitude_selector ).prop( 'readonly', true );
				$( cat_tracker_vars.submission_longitude_selector ).prop( 'readonly', true );
				$( cat_tracker_vars.submission_map_selector ).addClass( 'reloading' );

				clearTimeout( address_typing_timer );

				address_typing_timer = setTimeout( process_address_change, 2000 );
			});

		}

		function get_max_bounds() {
			var south_west_bounds = new L.LatLng( map_args.map_south_bounds, map_args.map_west_bounds ),
	  			north_east_bounds = new L.LatLng( map_args.map_north_bounds, map_args.map_east_bounds );
			return new L.LatLngBounds( south_west_bounds, north_east_bounds );
		}

		function build_sortable_attributes() {
			$.each( sortable_attributes, function( attribute_type, attribute_params ){
				all_marker_layers_by_attribute[attribute_type] = new Array();
				$.each( attribute_params.values, function( attribute_value, attribute_name ){
					all_marker_layers_by_attribute[attribute_type][attribute_value] = new Array();
				});
			});
		}

		function build_markers() {

			// loop the marker types/marker array and create a marker object for each marker
			$.each( markers_by_type, function( marker_type, marker_data ){

				// custom icon class for each marker type
				var icon = L.divIcon( { className: 'cat-tracker-map-icon icon-' + marker_type } );

				// markers for this marker type
				var markers = new Array();

				// loop the markers
				$.each( marker_data.sightings, function( i, sighting ){

					// if we're in preview / submission mode
					if ( 'preview' == marker_type ) {
						submission_marker = L.marker( [sighting.latitude, sighting.longitude], { clickable : true, draggable : true } ).bindPopup( sighting.text ).addTo( map );
						map.setView( [sighting.latitude, sighting.longitude], map_args.map_zoom_level, true );
					}

					// create the marker object
					var __marker = L.marker( [sighting.latitude, sighting.longitude], {icon: icon} ).bindPopup( sighting.text );

					// add the marker to the array of markers for this marker type
					markers.push( __marker );

					// sort the markers into the all_marker_layers_by_attribute array for filtering later on
					if ( cat_tracker_vars.do_sorting && 'undefined' != typeof( sighting.sortable_attributes ) && sighting.sortable_attributes.length > 0 ) {
						$.each( sighting.sortable_attributes, function( attribute_type, attribute_value ){
							all_marker_layers_by_attribute[attribute_type][attribute_value].push( __marker );
						});
					}

				});

				// group the markers by type
				active_marker_layers[marker_type] = L.layerGroup( markers );
				all_marker_layers[marker_type] = markers;
			});

			if ( _.has( active_marker_layers, 'preview' ) )
				return;

			// create a cluster or all the markers combined
			marker_cluster = new L.MarkerClusterGroup({
				iconCreateFunction: function (cluster) {
					var childCount = cluster.getChildCount();
					var c = ' marker-cluster-';
					if ( childCount < 10 ) {
						c += 'small';
					} else if ( childCount < 30 ) {
						c += 'medium';
					} else {
						c += 'large';
					}

					return new L.DivIcon({ html: '<div><span>' + childCount + '</span></div>', className: 'marker-cluster' + c, iconSize: new L.Point( 40, 40 ) });
				}
			});
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

				var $custom_controls = $( '#cat-tracker-custom-controls' );

				// figure out which marker type is currently enabled
				var $active_marker_types = $custom_controls.find( '.cat-tracker-layer-control-marker-type:checked' );
				var active_marker_types = new Array();
				var active_markers_by_type = new Array();
				var layer_key = '';
				$.each( $active_marker_types, function( i, marker_type_checkbox ){
					var marker_type = $( marker_type_checkbox ).data( 'marker-type' );
					layer_key += marker_type + '_';
					active_marker_types.push( marker_type );
					active_markers_by_type.push( all_marker_layers[marker_type] );
				});

				var active_markers = _.flatten( active_markers_by_type );

				// figure out which attributes are currently selected
				if ( cat_tracker_vars.do_sorting ) {
					var active_attributes = new Array();
					var active_markers_by_attribute = new Array();
					var ignore_attributes = true;
					$.each( sortable_attributes, function( attribute_type, attribute_params ){
						active_attributes[attribute_type] = new Array();
						var $active_attributes_of_type = $custom_controls.find( '.cat-tracker-layer-control-' + attribute_type + ':checked' );
						$.each( $active_attributes_of_type, function( i, active_attribute_checkbox ){
							var active_attribute = $( active_attribute_checkbox ).data( attribute_type );
							active_attributes[attribute_type].push( active_attribute );
							layer_key += active_attribute + '_';
							if ( 'all' != active_attribute ) {
								ignore_attributes = false;
								active_markers_by_attribute.push( all_marker_layers_by_attribute[attribute_type][active_attribute] );
							}
						});
					});
				}

				// combine and intersect the markers
				if ( cat_tracker_vars.do_sorting && ! ignore_attributes ) {
					active_markers_by_attribute = _.flatten( active_markers_by_attribute );
					active_markers = _.intersection( active_markers, active_markers_by_attribute );
				}

				// remove existing layers from map
				map.removeLayer( marker_cluster );

				// if no markers, bail
				if ( _.isEmpty( active_markers ) )
					return;

				// rebuild the cluster
				active_marker_layers = {};
				active_marker_layers[layer_key] = L.layerGroup( active_markers );
				marker_cluster = new L.MarkerClusterGroup({
					iconCreateFunction: function (cluster){
						var childCount = cluster.getChildCount();
						var c = ' marker-cluster-';
						if ( childCount < 10 ) {
							c += 'small';
						} else if ( childCount < 30 ) {
							c += 'medium';
						} else {
							c += 'large';
					}

					return new L.DivIcon({ html: '<div><span>' + childCount + '</span></div>', className: 'marker-cluster' + c, iconSize: new L.Point( 40, 40 ) });
					}
				});

				$.each( active_marker_layers, function( i, marker_group ){
					marker_cluster.addLayer( marker_group );
				});

				// re-add the cluster as a layer to the map
				map.addLayer( marker_cluster );

			});
		}

		function enable_submission_click() {
			if ( ! ( cat_tracker_vars.is_submission_mode || cat_tracker_vars.is_admin_submission_mode ) )
				return;

			if ( cat_tracker_vars.is_admin_submission_mode )
				$( cat_tracker_vars.submission_address_selector ).prop( 'readonly', true );

			$( cat_tracker_vars.submission_latitude_selector ).prop( 'readonly', true );
			$( cat_tracker_vars.submission_longitude_selector ).prop( 'readonly', true );

			map.on( 'click', capture_submission_click );
			if ( ! _.isEmpty( submission_marker ) )
	     	submission_marker.on( 'dragend', capture_submission_marker_drag_end );
		}

		function disable_submission_click() {
			map.off( 'click', capture_submission_click );
			if ( ! _.isEmpty( submission_marker ) )
	     	submission_marker.off( 'dragend', capture_submission_marker_drag_end );

			$( cat_tracker_vars.submission_address_selector ).prop( 'readonly', false );
			$( cat_tracker_vars.submission_latitude_selector ).prop( 'readonly', false );
			$( cat_tracker_vars.submission_longitude_selector ).prop( 'readonly', false );
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
	     	capture_coordinates_and_address( e.latlng );
			}, 200 );
		}

		function capture_submission_marker_drag_end( e ) {
			capture_coordinates_and_address( e.target._latlng );
		}

		function capture_coordinates_and_address( latlng ) {
      $( cat_tracker_vars.submission_latitude_selector ).val( latlng.lat );
	    $( cat_tracker_vars.submission_longitude_selector ).val( latlng.lng );
	    $( cat_tracker_vars.submission_address_selector ).val( cat_tracker_vars.fetching_address_text ).prop( 'readonly', true );
			$( cat_tracker_vars.publish_button_selector ).prop( 'disabled', true );
	    if ( false != address_ajax_request && 4 != address_ajax_request.readyState )
	    	address_ajax_request.abort();
	    address_ajax_request = $.get( cat_tracker_vars.ajax_url, { action : 'cat_tracker_fetch_address_using_coordinates', latitude : latlng.lat, longitude : latlng.lng, nonce : cat_tracker_vars.address_nonce }, function( response ){

	    	if ( cat_tracker_vars.is_submission_mode )
					$( cat_tracker_vars.submission_address_selector ).prop( 'readonly', false );

	    	if ( ( 'undefined' != typeof( response.errors ) && response.errors ) || 'undefined' == typeof( response.coordinates ) || _.isEmpty( response.coordinates ) ) {
	    		if ( 'undefined' != typeof( response.errors ) )
	    			alert( response.errors );

			    $( cat_tracker_vars.submission_address_selector ).val( cat_tracker_vars.default_address );
	    		return;
	    	}

		    $( cat_tracker_vars.submission_address_selector ).val( response.coordinates.formatted_address );
		    $( cat_tracker_vars.submission_confidence_level_selector ).val( response.coordinates.confidence );
				$( cat_tracker_vars.publish_button_selector ).prop( 'disabled', false );

	    });;
		}

		function process_address_change(){
	    $( cat_tracker_vars.submission_confidence_level_selector ).val( cat_tracker_vars.fetching_address_text );
			if ( false != process_address_ajax_request && 4 != process_address_ajax_request.readyState )
				process_address_ajax_request.abort();
			process_address_ajax_request = $.get( cat_tracker_vars.ajax_url, { action : 'cat_tracker_fetch_coordinates_using_address', address : $( cat_tracker_vars.submission_address_selector ).val(), nonce : cat_tracker_vars.address_nonce }, function( response ){

	    	if ( ( 'undefined' != typeof( response.errors ) && response.errors ) || 'undefined' == typeof( response.coordinates ) || _.isEmpty( response.coordinates ) ) {
	    		if ( 'undefined' != typeof( response.errors ) )
	    			alert( response.errors );

			    $( cat_tracker_vars.submission_address_selector ).val( cat_tracker_vars.default_address );
	    		return;
	    	}

				if ( _.isEmpty( submission_marker ) ) {
	      	submission_marker = new L.Marker( [response.coordinates.latitude, response.coordinates.longitude], { title : cat_tracker_vars.new_submission_popup_text, clickable : true, draggable : true } ).addTo( map );
		     	submission_marker.on( 'dragend', capture_submission_marker_drag_end );
	     	} else {
	     		submission_marker.setLatLng( [response.coordinates.latitude, response.coordinates.longitude] );
				}

				map.setView( [response.coordinates.latitude, response.coordinates.longitude], map_args.map_zoom_level, true );

		    $( cat_tracker_vars.submission_address_selector ).val( response.coordinates.formatted_address ).prop( 'readonly', false );
		    $( cat_tracker_vars.submission_confidence_level_selector ).val( response.coordinates.confidence );
		    $( cat_tracker_vars.submission_latitude_selector ).val( response.coordinates.latitude ).prop( 'readonly', false );
		    $( cat_tracker_vars.submission_longitude_selector ).val( response.coordinates.longitude ).prop( 'readonly', false );
				$( cat_tracker_vars.submission_map_selector ).removeClass( 'reloading' );
				$( '#cat-tracker-relocate' ).prop( 'disabled', false );
				$( cat_tracker_vars.publish_button_selector ).prop( 'disabled', false );

	    });

		}

		function process_coordinates_change(){
	    $( cat_tracker_vars.submission_latitude_selector ).prop( 'readonly', true );
	    $( cat_tracker_vars.submission_longitude_selector ).prop( 'readonly', true );
	    $( cat_tracker_vars.submission_confidence_level_selector ).val( cat_tracker_vars.fetching_address_text );
			$( cat_tracker_vars.submission_map_selector ).addClass( 'reloading' );
			if ( false != process_coordinates_ajax_request && 4 != process_coordinates_ajax_request.readyState )
				process_coordinates_ajax_request.abort();
			process_coordinates_ajax_request = $.get( cat_tracker_vars.ajax_url, { action : 'cat_tracker_fetch_address_using_coordinates', latitude : $( cat_tracker_vars.submission_latitude_selector ).val(), longitude : $( cat_tracker_vars.submission_longitude_selector ).val(), nonce : cat_tracker_vars.address_nonce }, function( response ){

				if ( ( 'undefined' != typeof( response.errors ) && response.errors ) || 'undefined' == typeof( response.coordinates ) || _.isEmpty( response.coordinates ) ) {
					if ( 'undefined' != typeof( response.errors ) )
						alert( response.errors );

					return;
				}

		    $( cat_tracker_vars.submission_latitude_selector ).val( response.coordinates.latitude ).prop( 'readonly', false );
		    $( cat_tracker_vars.submission_longitude_selector ).val( response.coordinates.longitude ).prop( 'readonly', false );
		    $( cat_tracker_vars.submission_address_selector ).val( response.coordinates.formatted_address ).prop( 'readonly', false );
		    $( cat_tracker_vars.submission_confidence_level_selector ).val( response.coordinates.confidence );

				if ( _.isEmpty( submission_marker ) ) {
	      	submission_marker = new L.Marker( [response.coordinates.latitude, response.coordinates.longitude], { title : cat_tracker_vars.new_submission_popup_text, clickable : true, draggable : true } ).addTo( map );
		     	submission_marker.on( 'dragend', capture_submission_marker_drag_end );
	     	} else {
	     		submission_marker.setLatLng( [response.coordinates.latitude, response.coordinates.longitude] );
				}

				map.setView( [response.coordinates.latitude, response.coordinates.longitude], map_args.map_zoom_level, true );

				$( cat_tracker_vars.submission_map_selector ).removeClass( 'reloading' );
				$( '#cat-tracker-relocate' ).prop( 'disabled', false );
				$( cat_tracker_vars.publish_button_selector ).prop( 'disabled', false );

	    });

		}

	}

	jQuery( document ).ready(function($){

		$.each( cat_tracker_vars.maps, function( i, map_args ){
			var cat_map = new catMap( map_args );
		});

	});

})(jQuery);