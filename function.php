function mpesa_donation_form() {
    return '
    <div class="donation-form-container" style="max-width: 400px; margin: 20px auto; padding: 20px;">
        <form id="donation-form">
            <div style="margin-bottom: 15px;">
                <input type="text" id="phone" placeholder="Phone Number (254...)" style="width: 100%; padding: 8px;">
            </div>
            <div style="margin-bottom: 15px;">
                <input type="number" id="amount" placeholder="Amount" style="width: 100%; padding: 8px;">
            </div>
            <button type="submit" style="background: #0073aa; color: white; padding: 10px 20px; border: none; cursor: pointer;">Donate</button>
        </form>
    </div>';
}
add_shortcode('donation_form', 'mpesa_donation_form');

function mpesa_donation_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('mpesa-donation', get_template_directory_uri() . '/js/donation.js', array('jquery'), '1.0', true);
    wp_localize_script('mpesa-donation', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'mpesa_donation_scripts');

function process_mpesa_payment() {
 
    $consumer_key = 'I4EqrgK7xYr8uyn98UIe4LfUssQQOq2XF0AJbQIXyAhBWD6V';
    $consumer_secret = 'CxWm53Ay5OJfSdy6Imo71ybixwOB8mIOLo7ylh0LTzAtZCMFTiSy82rBj3GqbdYR';
    $Business_Code = '174379';
    $Passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';

    // Generate Access Token
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($curl);
    $result = json_decode($result);

    $access_token = $result->access_token;

    // Process STK Push
    $timestamp = date('YmdHis');
    $password = base64_encode($Business_Code . $Passkey . $timestamp);

    $stk_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    $curl_post_data = array(
        'BusinessShortCode' => $Business_Code,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $_POST['amount'],
        'PartyA' => $_POST['phone'],
        'PartyB' => $Business_Code,
        'PhoneNumber' => $_POST['phone'],
'CallBackURL' => "https://sandbox.safaricom.co.ke/mpesa/stkpushcallback/v1/callback",
        'AccountReference' => 'DONATION',
        'TransactionDesc' => 'Donation Payment'
    );

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $stk_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $access_token));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($curl_post_data));
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($curl);

    echo $response;
    wp_die();
}
add_action('wp_ajax_process_mpesa', 'process_mpesa_payment');
add_action('wp_ajax_nopriv_process_mpesa', 'process_mpesa_payment');

function mpesa_callback_handler() {
    // Receive raw M-Pesa callback data
    $raw_data = file_get_contents('php://input');
    $mpesa_response = json_decode($raw_data, true);

   
    error_log('M-Pesa Callback: ' . print_r($mpesa_response, true));

    // Validate transaction status
    if (isset($mpesa_response['ResultCode']) && $mpesa_response['ResultCode'] == 0) {
        // Successful transaction
        $transaction_id = $mpesa_response['TransactionID'];
        $amount = $mpesa_response['Amount'];
        $phone_number = $mpesa_response['PhoneNumber'];

        // Update your order/payment status in the database
   
    
        wp_send_json_success(['status' => 'Transaction processed']);
    } else {
       
        wp_send_json_error(['status' => 'Transaction failed']);
    }
}
add_action('wp_ajax_mpesa_callback', 'mpesa_callback_handler');
add_action('wp_ajax_nopriv_mpesa_callback', 'mpesa_callback_handler');
