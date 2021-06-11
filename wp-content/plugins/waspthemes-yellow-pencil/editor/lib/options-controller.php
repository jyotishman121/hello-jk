<?php

// Don't run this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/* ---------------------------------------------------- */
/* Slider Option
/* ---------------------------------------------------- */
function yp_get_slider_markup($cssName, $name, $default = 'inherit', $decimals, $pxv, $pcv, $emv, $title = null, $dataFormats = null){

    // Default
    if ($default === false || $default == '') {
        $default = 'no-defined'; // to not be same with empty datas.
    }

    // Dev CSS Filter
    $CSSID = yp_css_id($cssName);
    $option_status = apply_filters( 'yp_property__'.$CSSID, TRUE);
    if(!$option_status){return;}

    // Pro Label
    $proLabel = "";
    if(!defined("WTFV")){
        if($cssName == "font-family" || $cssName == "color" || $cssName == "background-color" || $cssName == "background-image" || $cssName == "width" || $cssName == "height" || $cssName == "animation-name"){
            $proLabel = "<a target='_blank' href='https://waspthemes.com/yellow-pencil/buy' class='yp-lite yp-pro-label'>GO PRO</a>";
        }
    }

    // px, em etc.
    if($dataFormats != null){
        $dataFormats = "data-support-formats='".$dataFormats."'";
    }else{
        $dataFormats = "";
    }

    // default
    if($default != "no-defined"){
        $default = " data-default='".$default."'";
    }else{
        $default = "";
    }

    // Option HTML
    return "<div id='" . $cssName . "-group' class='op-g sl-o'".$dataFormats."".$default." data-css='" . $cssName . "' data-decimals='" . $decimals . "' data-px='" . $pxv . "' data-pc='" . $pcv . "' data-em='" . $emv . "'><div class='op-c'><span class='op-l'>".$proLabel."<span class='di-btn' title='".$title."'>" . $name . "</span><i class='mo-i'></i></span><div id='yp-" . $cssName . "' class='sl-d'><div class='sl-cu'></div></div><div class='yp-after'><input type='text' id='" . $cssName . "-value' class='css-va' /><input type='text' id='" . $cssName . "-after' class='css-un' /></div></div><div class='un-s'></div></div>";

}


/* ---------------------------------------------------- */
/* Grid Builder
/* ---------------------------------------------------- */
function yp_grid_builder($cssName, $name, $title = null){

    // Dev CSS Filter
    $CSSID = yp_css_id($cssName);
    $option_status = apply_filters( 'yp_property__'.$CSSID, TRUE);
    if(!$option_status){return;}

    // Option HTML
    $return = "<div id='" . $cssName . "-group' class='op-g gr-o' data-css='" . $cssName . "'><div class='op-c'><span class='op-l'><span class='di-btn' title='".$title."'>" . $name . "</span><i class='mo-i'></i></span>";


    // End Option
    $return .= "<input id='yp-" . $cssName . "' class='gr-bu-in' type='text' value='' /><div class='gr-bu-ar'></div></div></div>";

    // Return
    return $return;

}


/* ---------------------------------------------------- */
/* Select Option
/* ---------------------------------------------------- */
function yp_get_select_markup($cssName, $name, $values, $default = 'none', $title = null){

    // Default 1
    $defaultLink = '';

    // Default 2
    if ($cssName == 'animation-name') {

    	$filter_animation_tools = apply_filters( 'yp_animation_tools', TRUE);

    	if($filter_animation_tools){
        	$defaultLink = "<span class='yicon icon-controls-play anim-player-icon'></span><a class='yp-visual-editor-link'>Animator</a>";
	    }else{
        	$defaultLink = "<span class='yicon icon-controls-play anim-player-icon'></span><a class='yp-visual-editor-link-holder'></a>";
	    }

    }

    // Dev CSS Filter
    $CSSID = yp_css_id($cssName);
    $option_status = apply_filters( 'yp_property__'.$CSSID, TRUE);
    if(!$option_status){return;}

	// Create json list
    if(is_array($values)){

    	$rArray = "[";

    	$i = 0;
	    foreach($values as $key => $value){

	    	if($i != 0){
	    		$rArray .= ",";
	    	}

            $thisCat = "";

            if(is_array($value)){
                $thisCat = $value['1'];
                $value = $value['0'];
            }

	        $rArray .= '{"value":"'.$key.'","label":"'.$value.'", "category":"'.$thisCat.'"}';

	        $i++;

	    }

    	$values = $rArray . "]";

    }else{
    	$values = plugins_url('/../json/'.$values, __FILE__);
    }

    // Pro Label
    $proLabel = "";
    if(!defined("WTFV")){
        if($cssName == "font-family" || $cssName == "color" || $cssName == "background-color" || $cssName == "background-image" || $cssName == "width" || $cssName == "height" || $cssName == "animation-name"){
            $proLabel = "<a target='_blank' href='https://waspthemes.com/yellow-pencil/buy' class='yp-lite yp-pro-label'>GO PRO</a>";
        }
    }

    // checkbox
    $fontFamilyCheckbox = "";
    if($cssName == "font-family"){
        $fontFamilyCheckbox = '<label id="include-webfont-label"><input type="checkbox" checked="checked"><span class="include-webfont-input"></span></label>';
    }

    // Option HTML
    $return = "<div id='" . $cssName . "-group' class='op-g se-o' data-css='" . $cssName . "'><div class='op-c'><span class='op-l'>".$proLabel."<span class='di-btn' title='".$title."'>" . $name . "</span><i class='mo-i'></i>" . $defaultLink . $fontFamilyCheckbox . "</span><textarea tabindex='-1' disabled='disabled'>".$values."</textarea>";

    // End Option
    $return .= "<div class='ac-p-d'><input id='yp-" . $cssName . "' type='text' class='in-ac' value='' /><div id='ac-pl-" . $cssName . "' class='ac-d'></div></div></div></div>";

	// Return
    return $return;

}



/* ---------------------------------------------------- */
/* Radio Option
/* ---------------------------------------------------- */
function yp_get_radio_markup($cssName, $name, $values, $default = 'none',$title = null){

    // Default
    $defaultLink = '';

    // Dev CSS Filter
    $CSSID = yp_css_id($cssName);
    $option_status = apply_filters( 'yp_property__'.$CSSID, TRUE);
    if(!$option_status){return;}

    // Pro Label
    $proLabel = "";
    if(!defined("WTFV")){
        if($cssName == "font-family" || $cssName == "color" || $cssName == "background-color" || $cssName == "background-image" || $cssName == "width" || $cssName == "height" || $cssName == "animation-name"){
            $proLabel = "<a target='_blank' href='https://waspthemes.com/yellow-pencil/buy' class='yp-lite yp-pro-label'>GO PRO</a>";
        }
    }

    // Option HTML
    $return = "<div id='" . $cssName . "-group' class='op-g ra-o' data-css='" . $cssName . "'><div class='op-c'><span class='op-l'>".$proLabel."<span class='di-btn' title='".$title."'>" . $name . "</span><i class='mo-i'></i> " . $defaultLink . " </span><div class='ra-gr-" . count($values) . " ra-c' id='yp-" . $cssName . "'>";

    // Radio Settings
    foreach ($values as $key => $value) {
				$keyA = str_replace(" ", "-", $key);
        $return .= '<div class="ra"><input type="radio" name="' . $cssName . '" value="' . $key . '"><label id="'.$cssName.'-' . $keyA . '">' . $value . '</label></div>';
    }

    // Close
    $return .= "</div></div>";

    if($cssName == "background-size"){
        $return .= "<div class='background-size-custom-group yp-after'><div class='background-size-x-group'><input type='text' id='" . $cssName . "-x-value' class='yp-bgs-css-val' /><input type='text' id='" . $cssName . "-x-custom' class='yp-bgs-prefix' /><span class='property-title'>Width</span><div class='un-s'></div></div><div class='background-size-y-group'><input type='text' id='" . $cssName . "-y-value' class='yp-bgs-css-val' /><input type='text' id='" . $cssName . "-y-custom' class='yp-bgs-prefix' /><span class='property-title'>Height</span><div class='un-s'></div></div></div>";
    }

    $return .= "</div>";

    // Return
    return $return;

}



/* ---------------------------------------------------- */
/* Colorpicker Option
/* ---------------------------------------------------- */
function yp_get_color_markup($cssName, $name,$title = null){

	// Dev CSS Filter
    $CSSID = yp_css_id($cssName);
    $option_status = apply_filters( 'yp_property__'.$CSSID, TRUE);
    if(!$option_status){return;}

    // Pro Label
    $proLabel = "";
    if(!defined("WTFV")){
        if($cssName == "font-family" || $cssName == "color" || $cssName == "background-color" || $cssName == "background-image" || $cssName == "width" || $cssName == "height" || $cssName == "animation-name"){
            $proLabel = "<a target='_blank' href='https://waspthemes.com/yellow-pencil/buy' class='yp-lite yp-pro-label'>GO PRO</a>";
        }
    }

    // Option HTML
    $return = "<div id='" . $cssName . "-group' class='op-g co-o' data-css='" . $cssName . "'><div class='op-c'><span class='op-l'>".$proLabel."<span class='di-btn' title='".$title."'>" . $name . "</span><i class='mo-i'></i> </span><div class='co-in-bo'><input id='yp-" . $cssName . "' type='text' maxlength='22' size='22' class='co-p' value='' /><span class='yp-color-background'><span class='co-sw-co'></span></span></div></div></div>";

    // Return
    return $return;

}




/* ---------------------------------------------------- */
/* Input Option
/* ---------------------------------------------------- */
function yp_get_input_markup($cssName, $name, $title = null){

	// Dev CSS Filter
    $CSSID = yp_css_id($cssName);
    $option_status = apply_filters( 'yp_property__'.$CSSID, TRUE);
    if(!$option_status){return;}

    // Pro Label
    $proLabel = "";
    if(!defined("WTFV")){
        if($cssName == "font-family" || $cssName == "color" || $cssName == "background-color" || $cssName == "background-image" || $cssName == "width" || $cssName == "height" || $cssName == "animation-name"){
            $proLabel = "<a target='_blank' href='https://waspthemes.com/yellow-pencil/buy' class='yp-lite yp-pro-label'>GO PRO</a>";
        }
    }

    // Option HTML
    $return = "<div id='" . $cssName . "-group' class='op-g in-o' data-css='" . $cssName . "'><div class='op-c'><span class='op-l'>".$proLabel."<span class='di-btn' title='".$title."'>" . $name . "</span><i class='mo-i'></i> </span><div class='in-wr'><input placeholder='none' id='yp-" . $cssName . "' type='text' class='yp-input' value='' />";

    // Upload image icon
    if($cssName == 'list-style-image' || $cssName == "background-image"){
        $return .= "<span class='yp-upload-btn yp-gallery-btn'></span>";
        $return .= "<span class='yp-clear-btn yicon icon-no-alt'></span>";
    }

    $return .= "</div>";



	// Background Image
	if($cssName == "background-image"){

		$return .= "<div style='clear:both;'></div><a class='yp-unsplash-btn'>Stock Images</a><a data-json='".WT_PLUGIN_URL . 'editor/json/gradients.json'."' class='yp-gradient-btn'>Gradients</a><a data-json='".WT_PLUGIN_URL . '/editor/json/patterns.json'."' class='yp-bg-img-btn'>Patterns</a><div style='clear:both;'></div>";


		// Background patterns section starts
		$return .= "<div class='yp-background-asts'>";
		$return .= "</div>";
		// Background patterns section end.

		// Background gradient section starts
		$return .= '<div class="yp-gradient-section"><div class="gradient-editor"><div class="yp-gradient-bar-background"><div class="yp-gradient-bar"></div><div class="yp-gradient-pointer-area"></div></div><input id="iris-gradient-color" type="text" /><div class="yp-gradient-space"></div><div class="yp-gradient-orientation" data-degree="90"><b>Orientation</b><i></i></div></div><div class="yp-gradient-list"></div><div class="uigradient-api">by <a target="_blank" href="https://uigradients.com">uiGradients</a></div></div>';
		// Background gradient section end

        $return .= "<div class='yp-unsplash-section'>";
		$return .= "<div class='yp-unsplash-inner'><input id='unsplash-search' type='text' value='' placeholder='Search an image' />";
		$return .= "<div class='yp-unsplash-list'></div></div>";
		$return .= '<div class="unsplash-api">by <a target="_blank" href="https://unsplash.com">Unsplash</a></div>';
		$return .= "</div>";

	}

	// Close
    $return .= "</div></div>";

    // Return
    return $return;

}
