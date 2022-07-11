<?php
/**
 * Plugin Name: ITS One Login
 * Version: 1.0.0
 * Plugin URI: http://www.shubbaktech.com/
 * Description: This plugin seemlessly integrates with ITS One Login provided by its52.com website.
 * Author: Husain Basrawala
 * Author URI: http://www.shubbaktech.com/
 * Text Domain: its-one-login
 * Domain Path: /lang/
 *
 * @package WordPress
 * @author Husain Basrawala
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-its-one-login.php';
require_once 'includes/class-its-one-login-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-its-one-login-admin-api.php';
require_once 'includes/lib/class-its-one-login-post-type.php';
require_once 'includes/lib/class-its-one-login-taxonomy.php';

/**
 * Returns the main instance of ITS_One_Login to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object ITS_One_Login
 */
function its_one_login() {
	$instance = ITS_One_Login::instance( __FILE__, '1.0.0' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = ITS_One_Login_Settings::instance( $instance );
	}

	return $instance;
}

its_one_login();

function redirect_to_its() {
    do_action('iol_redirect_to_its');
    wp_redirect('https://www.its52.com/Login.aspx?OneLogin='.get_option('iol_idara_domain_name'));
}

// add_action('init', 'validate_login');
// function validate_session() {
//     if (!is_user_logged_in()) {
//         redirect_to_its();
//     }
// }

function its_create_user($user_information) {
    
    do_action('iol_create_user',$user_information);

    if(trim($user_information)!=""){
        $user_information=explode(",",$user_information);
        $its_id=$user_information[0];
        $user_name=$user_information[1];
        $jamaat=$user_information[4];
        $jamiaat=$user_information[5];
        $wp_username=$its_id;
        $wp_password=$wp_username."@123$";
        $user = get_user_by( 'login',$its_id );
        if($user){
            #login the user
            update_user_meta( $user->ID,'first_name', $user_name);
            update_user_meta( $user->ID,'display_name', $user_name);
            update_user_meta( $user->ID, 'jamaat', $jamaat);
            update_user_meta( $user->ID, 'jamiaat', $jamiaat );
            wp_clear_auth_cookie();
            wp_set_current_user ( $user->ID );
            wp_set_auth_cookie  ( $user->ID );
        }else{
            $user_id = wp_create_user($wp_username, $wp_password, "");
            update_user_meta( $user_id,'first_name', $user_name);
            update_user_meta( $user_id,'display_name', $user_name);
            update_user_meta( $user_id,'its_id', $its_id);
            update_user_meta( $user_id, 'jamaat', $jamaat);
            update_user_meta( $user_id, 'jamiaat', $jamiaat );
            $user = new WP_User( $user_id );
            $user->remove_role($user->roles[0] );
            $user->add_role( 'subscriber' );
            wp_clear_auth_cookie();
            wp_set_current_user ( $user_id );
            wp_set_auth_cookie  ( $user_id );
            
       }
    }
}

function decrypter($cipherText) {
    $token = get_option('iol_token_key'); //'AU68vf26spwX'; // Change to the token issued to your domain
    $key = openssl_pbkdf2($token, 'V*GH^|9^TO#cT', 32, 1000);
    $data = utf8_decode(openssl_decrypt((urldecode($cipherText)), 'AES-128-CBC', substr($key, 0, 16), OPENSSL_ZERO_PADDING, substr($key, 16, 16)));
    return preg_replace('/[\x00]/','',$data);
}

add_action('parse_request', 'onelogin_handler');
function onelogin_handler() {

    if($_SERVER["REQUEST_URI"] == '/'.get_option('iol_return_url')) {
        if(isset($_GET['TOKEN']) && isset($_GET['DT'])) {
            $token = get_option('iol_token_key');

            if (decrypter($_GET['TOKEN']) == $token) {
                $data = decrypter($_GET['DT']);
                $its = explode(",",$data);
                setcookie('wp_iol_onelogin_time', date('F j, Y  g:i a'), time()+86400);
                setcookie('wp_iol_onelogin_its',$its);
            } else {
                echo 'Token Verification failed.';
            }
            if(get_option('iol_create_user') == 'true' || get_option('iol_create_user') === true) {
                its_create_user($data);
            }
        }
   }
   if(!isset($_COOKIE['wp_iol_onelogin_its']) && !isset($_COOKIE['wp_iol_onelogin_time'])) {
        redirect_to_its();
   }

}


