<?php
/*
Plugin Name: Hacman
Plugin URI: http://my-awesomeness-emporium.com
description: Hacman plugin with gocardless integration
Version: 0.0.0
Author: Conor Riches
Author URI: http://conorriches.co.uk
License: GPL2

Extra fields:
redirectflow_ref - where the reference for the redirectflow goes so we can assign to user when complete
gocardless_ref - the gocardless reference
gocardless_sess - a unique session
 */

require_once dirname(__FILE__) . '/wp-scb-framework/load.php';
require_once 'vendor/autoload.php';

//TODO: put these values in config
$client;

function hacman_init()
{
    global $client;
    
    $options = new scbOptions('hacman', __FILE__, array(
        'gocardless_api_key' => 'foo',
        'gocardless_env' => '',
        'gocardless_token' => '',
    ));

    //"sandbox_hdNQHHVW0GEbVc9Y9VVWH7KXw70qMhcwFVeOd1ko"
    $client = new \GoCardlessPro\Client([
        'access_token' => $options->gocardless_api_key,
        'environment' => \GoCardlessPro\Environment::SANDBOX,
    ]);

    // Admin pages
    if (is_admin()) {
        require_once dirname(__FILE__) . '/admin.php';
        new Hacman_Admin_Page(__FILE__, $options);
    }

}

/**
 * Create a redirect flow for the given user.
 * Creates a redirect flow, and stores the reference to the user's account in redirectflow_ref.
 */
function hacman_create_redirect_flow($user_id)
{
    global $client;

    $session_token = hacman_generate_random_string();

    //TODO: actual correct values
    $redirectFlow = $client->redirectFlows()->create([
        "params" => [
            // This will be shown on the payment pages
            "description" => "Hackspace Manchester Membership",
            // Not the access token
            "session_token" => $session_token,
            "success_redirect_url" => "https://conorriches.co.uk/confirm_subscription",
            // Optionally, prefill customer details on the payment page
            "prefilled_customer" => [
                "given_name" => "Tim",
                "family_name" => "Rogers",
                "email" => "tim@gocardless.com",
                "address_line1" => "338-346 Goswell Road",
                "city" => "London",
                "postal_code" => "EC1V 7LQ",
            ],
        ],
    ]);

    update_usermeta($user_id, 'redirectflow_ref', $redirectFlow->id);
    update_usermeta($user_id, 'gocardless_sess', $session_token);

    return $redirectFlow;
}

/**
 * Confirms a redirect flow given a $flow_id, and $session_id, and saves user reference
 */
function hacman_confirm_redirect_flow($flow_id, $session_id, $user_id)
{
    global $client;

    try {
        $subscription_amount = get_userdata($user_id)->initial_amount;

        $redirectFlow = $client->redirectFlows()->complete(
            $flow_id, //The redirect flow ID from above.
            ["params" => ["session_token" => $session_id]]
        );

        $client->subscriptions()->create([
            "params" => ["amount" => intval($subscription_amount) * 100,
                         "currency" => "GBP",
                         "name" => "Hacman Membership",
                         "interval_unit" => "monthly",
                         "interval" => 1,
                         "metadata" => ["cust_ref" => $redirectFlow->links->customer],
                         "links" => ["mandate" => $redirectFlow->links->mandate]]
        ]);

    } catch (Exception $e) {
        return array('success' => false, 'message' => $e);
    }

    update_usermeta(get_current_user_id(), 'gocardless_ref', $redirectFlow->links->customer);

    return array('success' => true, 'data' => $redirectFlow);
}

function hacman_registration_save( $user_id ) {

    if ( isset( $_POST['Select_13'] ) )
        update_user_meta($user_id, 'initial_amount', $_POST['Select_13']);
    
}




function hacman_filter_urls($content)
{
    //TODO: set page names as settings
    // Setup Payment page
    if ($GLOBALS['post']->post_name == 'setup-payment') {

        if (get_current_user_id() == 0) {
            return "<h2>You need to <a href='/wp-login'>log in first</a> so we know who to set payment up for!</h2>";
        }

        // Check if the user already has a gocardless reference.
        $payment_ref = esc_attr(get_the_author_meta('gocardless_ref', get_current_user_id()));
        if ($payment_ref != "") {
            return "<h2>Your payment is all set up!</h2> #$payment_ref";
        } else {
            if ($_POST['send_gocardless'] == '1') {
                $redirectFlow = hacman_create_redirect_flow(get_current_user_id());
                $content = "<p>Here's your unique link to set up payment. We've linked it to your account so we'll know it's you.</p>";
                $content .= "<p><a href='$redirectFlow->redirect_url'>$redirectFlow->redirect_url</a></p><br/>";
                $content .= "<p>This link will expire in half an hour, but you can regenerate a new one by visiting this page at any time.</p>";
            }
        }

    }

    // Confirm page
    if ($GLOBALS['post']->post_name == 'confirm_subscription') {

        $this_user = get_users(
            array(
                'meta_key' => 'redirectflow_ref',
                'meta_value' => $_GET['redirect_flow_id'],
            )
        );


        if (sizeof($this_user) == 1) {
            $this_user = $this_user[0];
            $payment_ref = esc_attr(get_the_author_meta('gocardless_ref', $this_user->id));
            if ($payment_ref != "") {
                $content = "<h2>You already have a gocardless mandate - you can't reconfirm!</h2>";
            } else {

                $gocardless_sess = esc_attr(get_the_author_meta('gocardless_sess', $this_user->id));

                
                $result = hacman_confirm_redirect_flow($_GET['redirect_flow_id'], $gocardless_sess, $this_user->id);

                if ($result['success']) {
                    $content = "<h1>You're now a member!</h1>";
                    $content .= "<p>Your customer reference is " . $result['data']->links->customer . ", and this will be emailed to you.";

                } else {
                    $content = "<h1>Error - not completed.</h1>";
                    $content .= "<p>The direct debit has not been able to be set up.";
                    $content .= "<p>You may need to <a href='/setup-payment'>set up your direct debit again</a>.";
                    $content .= "<br/><br/> Reason: ";
                    $content .= $result['message']->api_error->message . "</p>";
                    var_dump( $result['message']->api_error);
                }

            }

        } else {
            $content = "Please contact board@hacman.org.uk with the error '#" . sizeof($this_user) . " user for token.'";
        }

    }
    
    // otherwise returns the database content
    return $content;
}

//Utilities
function hacman_generate_random_string($length = 20)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function hacman_process_webhook($event){
    global $client;

    $event_id = $event->id;

    //TODO: check event not seen before

    //get the payment ID
    $payment_id = $event->links->payment;


    try{
        $payment = $client->payouts()->get($payment_id);
    
        if($payment){
    
            var_dump($payment);
            //Get the customer ID
    
            //Update expiry for customer
    
        }
    
        return true;
    }catch(Exception $e){
        var_dump($e);
        return false;
    }
};

function hacman_recieve_webhook( WP_REST_Request $data ) {
    //check secret is correct
    //TODO: put this in settings
    $token = "supersecuresecret";//getenv("GC_WEBHOOK_SECRET");

    // Get header and check signature matches
    $raw_payload = file_get_contents('php://input');
    $headers = getallheaders();
    $provided_signature = $headers["Webhook-Signature"];
    $calculated_signature = hash_hmac("sha256", $raw_payload, $token);

    //TODO: SERIOUSLY UNDO THIS!!!!
    if ($provided_signature == $calculated_signature || $data->get_params()['test']) {

        // TODO: loop through each event and process webhook
        $resp = [];
        $body = json_decode($data->get_body());
       

        foreach($body->events as $event){
            array_push($resp,hacman_process_webhook($event));
        }
        
        return new WP_REST_Response(array("message" => $resp), 200); 

    } else {
        return new WP_Error( 'page_does_not_exist', __('We can\'t find the data, is your token correct?'), array( 'status' => 404 ) );
    }
}

function hacman_user_capability_search(){
    $all_users = get_users();
    $specific_users = array();


    foreach($all_users as $user){
        if(user_can($user, 'hacman_3d')){
            $keyfob = esc_attr(get_the_author_meta('hacman_keyfob', $user->id));
            $specific_users[] = $user->id;
        }

    }

    return new WP_REST_Response($specific_users, 200); 
    
}


// Hooks and filters
add_filter('the_content', 'hacman_filter_urls');
add_action( 'user_register', 'hacman_registration_save', 10, 1 );

// Custom API Routes
add_action( 'rest_api_init', function () {
    register_rest_route( 'hacman/v1', '/webhook', 
        array(
            'methods' => 'POST',
            'callback' => 'hacman_recieve_webhook',
        ) 
    );
    register_rest_route( 'hacman/v1', '/webhook', 
        array(
            'methods' => 'GET',
            'callback' => 'hacman_recieve_webhook',
        ) 
    );
    register_rest_route( 'hacman/v1', '/users/capability', 
        array(
            'methods' => 'GET',
            'callback' => 'hacman_user_capability_search',
        ) 
    );
});
scb_init('hacman_init');