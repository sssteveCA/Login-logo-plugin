<?php
/**
 * Plugin Name: Logo pagina login (senza debug)
 * Plugin URI: https://www.stefano.com/loginLogo
 * Description: Imposta il logo della pagina di login
 * Version: 1.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Stefano
 * Author URI: https://www.stefano.com
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 */

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
require_once('const.php');
require_once('classi/Library.php');

$logFile = ABSPATH.'logLibrary.txt';

register_activation_hook(__FILE__,'ll_create_table');

//crea la tabella all'attivazione del plugin
function ll_create_table(){
    global $wpdb;
    $table = $wpdb->prefix.'ll_library';
    $charset = $wpdb->collate;
    $create = <<<SQL
CREATE TABLE `{$table}` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `src` varchar(250) NOT NULL,
 `used` tinyint(1) NOT NULL DEFAULT 0,
 `fonte` tinyint(1) NOT NULL COMMENT '0 = immagine caricata dal PC,\r\n1 = immagine caricata dalla libreria',
 PRIMARY KEY (`id`),
 UNIQUE KEY `src` (`src`)
) COLLATE {$charset};
SQL;
    dbDelta($create);
}

register_uninstall_hook(__FILE__,'ll_delete_table');

//cancella la tabella quando il plugin viene eliminato
function ll_delete_table(){
    global $wpdb;
    $table = $wpdb->prefix.'ll_library';
    $sql = <<<SQL
DROP TABLE `{$table}`;
SQL;
    $delete = $wpdb->query($sql);   
}

add_action('admin_enqueue_scripts','ll_enqueue');

//includi i file CSS e Javascript necessari
function ll_enqueue(){
    wp_enqueue_style('ll_css1');
    wp_enqueue_media();
    wp_enqueue_script('jsButton');
}

add_action('admin_menu','ll_menu',11);

//aggiunge una voce al menu admin
function ll_menu(){
    add_menu_page('Immagine logo','Immagine logo','administrator','img_logo','ll_setLogo','',0);
}

//pagina che permette la modifica dell'immagine di logo
function ll_setLogo(){
    $image_id = get_option( 'myprefix_image_id' );
    if( intval( $image_id ) > 0 ) {
        // Change with the image size you want to use
        $image = wp_get_attachment_image( $image_id, 'medium', false, array( 'id' => 'myprefix-preview-image' ) );
    } else {
        // Some default image
        $image = '<img id="myprefix-preview-image" src="https://some.default.image.jpg" />';
    }
    $tText1 = esc_attr( $image_id );
    //$tText2 = esc_attr_e( 'Select a image','mytextdomain');
    $html = <<<HTML
<div id="ll_div_content">
    <h2 id="ll_title">Logo della pagina di login</h2>
    <div id="ll_div_form">
        <!-- <p id="ll_par_choose">Scegli l'immagine da usare per il logo della pagina di login</p> -->
        <form id="ll_form_upload" method="post" action="logoUpload.php" enctype="multipart/form-data">
            <div class="ll_div_fields">
                <label for="ll_form_upload">Carica un'immagine</label>
                <input type="file" id="ll_file_upload" accept="image/png, image/jpeg, image/gif">
                <input type="button" id="ll_button_upload" value="CARICA FILE">
            </div>
            <div class="ll_div_fields">
                <label for="ll_form_media">Scegli immagine dalla libreria</label>
                <input type="hidden" name="myprefix_image_id" id="myprefix_image_id" value="{$tText1}" class="regular-text" />
                <input type='button' class="button-primary" value="SCEGLI" id="myprefix_media_manager"/>
            </div>
            <div id="ll_div_tabella">
            </div>
        </form>
    </div>
</div>
HTML;
    echo $html;
}

add_action('init','ll_check_used_image');

//controlla se nel database c'è un'immagine che è stata scelta per essere usata come logo nella pagina di login
function ll_check_used_image(){
    $library = new Library();
    $used = $library->getUsedImage();
    if($used != null /*&& $used->fileExists()*/){
        //Immagine dell'oggetto istanziato nella pagina di login
        add_action('login_head',array($used,'setImage'));
    }//if($used != null)
    else{
    }
}

add_filter('upload_mimes','ll_file_type',99);

//tipi di files che possono essere caricati nella libreria media
function ll_file_type($types){
    $types = array();
    $types['jpg'] = 'image/jpeg';
    $types['png'] = 'image/png';
    $types['gif'] = 'image/gif';
    return $types;
}

// Ajax action to refresh the user image
add_action( 'wp_ajax_myprefix_get_image', 'myprefix_get_image');

function myprefix_get_image() {
    if(isset($_GET['id']) ){
        $image = wp_get_attachment_image( filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT ), 'medium', false, array( 'id' => 'myprefix-preview-image' ) );
        $data = array(
            'image'    => $image,
        );
        wp_send_json_success( $data );
    } else {
        wp_send_json_error();
    }
}

add_action('wp_loaded','ll_register');

//registra i file CSS e Javascript necessari
function ll_register(){
    $css1 = plugins_url().'/'.DIR_PLUGIN.'/css/loginLogo.css';
    $jsButton = plugins_url().'/'.DIR_PLUGIN.'/js/mediaButton.php';
    wp_register_style('ll_css1',$css1,array(),null);
    wp_register_script('jsButton',$jsButton,array(),null);
}
?>