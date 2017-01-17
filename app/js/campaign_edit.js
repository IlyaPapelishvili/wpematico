jQuery(document).ready(function($){

	
	jQuery('#campaign_striphtml').change(function() {
		if (jQuery('#campaign_striphtml').is(':checked')) {
			jQuery('#campaign_strip_links').attr('checked', false);
			jQuery('#div_campaign_strip_links_options').fadeOut();
		}
	});
	jQuery('#campaign_strip_links').change(function() {
		if (jQuery('#campaign_striphtml').is(':checked') && jQuery('#campaign_strip_links').is(':checked')) {
			jQuery('#campaign_strip_links').attr('checked', false);
			jQuery('#div_campaign_strip_links_options').fadeOut();
			return false;
		}
		if (jQuery('#campaign_strip_links').is(':checked')) {
			jQuery('#div_campaign_strip_links_options').fadeIn();
		} else {
			jQuery('#div_campaign_strip_links_options').fadeOut();
		}

	});
});