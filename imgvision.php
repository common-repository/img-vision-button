<?php
/*
Plugin Name: Img.vision image button
Plugin URI:  https://www.img.vision/wordpress-plugin.html
Description: Add a button to your editor to easily add images from your img.vision account.
Version:     1.0.0
Author:      Img.vision
Author URI:  https://img.vision
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

class WP_Img_Vision {

    // Constructor
    function __construct() {
        add_action('media_buttons', array($this, 'add_img_vision_button'), '11');
        add_action('admin_menu', array($this, 'wpa_add_menu'));
    }

    // Add the img.vision button near the Add Media button
    function add_img_vision_button() {
        echo '<a href="#" id="insert-img-vision" class="button insert-img-vision"><img src="'.plugins_url('images/img-vision-logo.png', __FILE__).'"/> Add Image</a>';
        echo '<div id="imgv-insert-dialog" class="imgv-modal">';
        echo $this->img_vision_view_images(true);
        echo '</div>';
    }

    // Actions performed at loading of admin menu
    function wpa_add_menu() {
        add_menu_page( 'img-vision-view-images', 'img.vision', 'manage_options', 'img-vision-view-images', array($this, 'img_vision_view_images'), plugins_url('images/img-vision-logo.png', __FILE__),'11');
        add_options_page("img-vision-settings", 'img.vision', 'manage_options', 'img-vision', array($this, 'img_vision_settings') );

        add_action( 'admin_init', array($this, 'setup_sections') );
        add_action('wp_enqueue_media', array($this, 'include_imgv_media_js'));

        add_settings_field("img-vision-api-key", "API Key", array($this, 'display_img_vision_api_key'), "img-vision", "img-vision");
        add_settings_field("img-vision-property-id", "Property Name", array($this, 'display_img_vision_property_id'), "img-vision", "img-vision");

        register_setting("img-vision", "img-vision-api-key");
        register_setting("img-vision", "img-vision-property-id");
    }

    // Actions performed at the initialisation of admin panel
    public function setup_sections() {
        add_settings_section( 'img-vision', 'img.vision settings', false, 'img-vision' );

        wp_register_style('imgv-bootstrap', plugins_url('css/bootstrap.min.css',__FILE__ ));
        wp_enqueue_style('imgv-bootstrap');

        wp_register_style('imgv-css', plugins_url('css/imgvision.min.css',__FILE__ ));
        wp_enqueue_style('imgv-css');

        wp_enqueue_script('imgv_media_load_scroll', plugins_url('js/jquery-load-scroll.min.js', __FILE__), array('jquery'), '', true);
    }

    // Include custom js for media button
    function include_imgv_media_js() {
        wp_enqueue_script('imgv_media_js', plugins_url('js/imgv-media.min.js', __FILE__), array('jquery'), '', true);
    }

    // Main method to display images (used in both singular page and popup)
    function img_vision_view_images($display_dialog = false) {
        $url = 'https://app.img.vision/sapi/?skey='.get_option('img-vision-api-key').'&request=list-all&property='.get_option('img-vision-property-id');

        $request = wp_remote_get($url);
        $response_code = wp_remote_retrieve_response_code($request);
        $result = wp_remote_retrieve_body($request);

        $response = json_decode($result, true);

        echo '<div class="wrap">';

        if ($response_code === 200) {

            if ($display_dialog === true) {
                echo '<span class="imgv-close">&times;</span>';
            }

            echo '<h1 class="text-center imgv-mt-20">Images on img.vision - '.$response['request']['property']['propertyName'].'</h1>';
            if (count($response['request']['images']) > 0) {

                if ($display_dialog === true) {
                    echo '<h4 class="text-center imgv-mt-20">- click on any image to insert it -</h4>';
                }

                echo '<div class="container-fluid imgv-mt-20">';

                $counter = 0;
                foreach ($response['request']['images'] as $image) {
                    if ($counter === 0) {
                        echo '<div class="row">';
                    } else if ($counter === 4) {
                        echo '</div><div class="row">';
                        $counter = 0;
                    }

                    $imageFolder = '';
                    if ($image['imageFolder'] !== '') {
                        foreach ($response['request']['folders'] as $folder) {
                            if ($image['imageFolder'] === $folder['_id']) {
                                $imageFolder = $folder;
                            }
                        }
                    }

                    echo '<div class="col-sm-3"><a href="'.$image['imageUrl'].'" class="text-center imgv-image-load-link imgv-image-link"><img class="imgv-image-load" data-src="'.(array_key_exists('imageThumbnailUrl', $image) ? $image['imageThumbnailUrl'] : $image['imageUrl']).'" src="'.plugins_url('images/loading.gif', __FILE__).'" alt="'.$image['imageName'].'"/></a><a href="'.$image['imageUrl'].'" class="text-center imgv-image-load-link">'.($imageFolder !== '' ? '<span class="dashicons dashicons-category imgv-mr-5"></span>'.$imageFolder['folderName'].' / ' : '').'<span class="dashicons dashicons-format-gallery imgv-mr-5"></span>'.$image['imageName'].'</a></div>';

                    $counter++;
                }

                echo '</div>';
                echo '</div>';
            } else {
                echo '<span class="text-center imgv-no-images">- no images inside this property -</span>';
            }
        } else {
            if ($display_dialog === true) {
                echo '<span class="imgv-close">&times;</span>';
            }

            echo '<h1 class="text-center imgv-mt-20">Error '.$response_code.': API Request failed ('.$response['message'].')</h1>';
        }

        echo '</div>';
    }

    // Display the api key field on settings page
    function display_img_vision_api_key() {
      	?>
            <input type="text" name="img-vision-api-key" id="img-vision-api-key" value="<?php echo get_option('img-vision-api-key'); ?>" />
        <?php
    }

    // Retrieve property names with api key and display them for selection
    function display_img_vision_property_id() {
        $url = 'https://app.img.vision/sapi/?skey='.get_option('img-vision-api-key').'&request=list-properties';

        $request = wp_remote_get($url);
        $response_code = wp_remote_retrieve_response_code($request);
        $result = wp_remote_retrieve_body($request);

        $response = json_decode($result, true);
        $propertyId = get_option('img-vision-property-id');

        if ($response_code === 200) {
            echo '<select name="img-vision-property-id" id="img-vision-property-id">';

            if ($propertyId === false || $propertyId === "") {
                echo '<option value=""></option>';
            }

            $found = false;
            foreach($response['request']['properties'] as $property) {
                if ($propertyId === $property['_id']) {
                  $found = true;
                }
                echo '<option value="'.$property['_id'].'"'.($propertyId === $property['_id'] ? 'selected="selected"' : '').'>'.$property['propertyName'].'</option>';
            }

            if ($propertyId !== false && $propertyId !== "" && !$found) {
                echo '<option value="" selected="selected"></option>';
            }

            echo '</select>';
        } else {
            echo 'Error '.$response_code.': API Request failed ('.$response['message'].')';
            echo '<input type="hidden" name="img-vision-property-id" id="img-vision-property-id" value="'.$propertyId.'" />';
        }
    }

    // Display fields on settings page
    function img_vision_settings() {
        ?>
            <div class="wrap">
                <form method="post" action="options.php">
                    <?php
                        settings_fields("img-vision");
                        do_settings_sections("img-vision");
                        submit_button();
                    ?>
                </form>
            </div>
        <?php
    }

}

// Initialise object
new WP_Img_Vision();
