jQuery( document ).ready(function($){

	// todo: make the select2 dropdown "clearable"
	// todo: display hierarchy
	$( '#cat_tracker_map, .custom-metadata-field.taxonomy_select select' ).not( '.select-disabled' ).select2();
});