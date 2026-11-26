<?php
// Talks to the Paystack API (https://paystack.com/docs/api/).
// Only two calls are needed:
//   1. initialize - start a payment and get the Paystack page to send the customer to
//   2. verify     - after the customer comes back, ask Paystack if they really paid
// The secret key stays on the server. It is never sent to the browser.

// Load the key if the file exists. If it is missing, paystack_ready()
// says no, and the shop shows "online payment is not available"
// instead of every order page crashing.
if (is_file(__DIR__ . '/paystack_keys.php')) {
    require_once __DIR__ . '/paystack_keys.php';
}

const PAYSTACK_API = 'https://api.paystack.co';

/**
 * Is a Paystack TEST key set up?
 * Live keys (sk_live_...) are refused on purpose, so the project can
 * never take real money by mistake.
 */
function paystack_ready(): bool
{
    return defined('PAYSTACK_SECRET_KEY')
        && strpos(PAYSTACK_SECRET_KEY, 'sk_test_') === 0
        && strpos(PAYSTACK_SECRET_KEY, 'PASTE_YOUR') === false;
}

/**
 * Send one request to Paystack and read the JSON reply.
 * @return array ['ok' => bool, 'data' => array|null, 'message' => text]
 */
function paystack_request(string $method, string $path, ?array $body = null): array
{
    if (!paystack_ready()) {
        return ['ok' => false, 'data' => null, 'message' => 'Paystack test key is not set up.'];
    }

    $curl = curl_init(PAYSTACK_API . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json',
        ],
        // The SSL certificate is always checked, so nobody can pretend to be Paystack
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($curl);
    $error    = curl_error($curl);
    // No curl_close() needed: since PHP 8 the connection closes by itself
    // (curl_close() does nothing and is deprecated in PHP 8.5)

    if ($response === false) {
        log_error('Paystack connection failed: ' . $error);
        return ['ok' => false, 'data' => null, 'message' => 'Could not reach Paystack.'];
    }

    $json = json_decode($response, true);
    if (!is_array($json) || empty($json['status'])) {
        $message = is_array($json) && isset($json['message']) ? $json['message'] : 'Unexpected reply';
        log_error('Paystack ' . $path . ' refused: ' . $message);
        return ['ok' => false, 'data' => null, 'message' => $message];
    }

    return ['ok' => true, 'data' => $json['data'] ?? [], 'message' => $json['message'] ?? ''];
}

/**
 * Start a payment. On success, data['authorization_url'] is the Paystack
 * page to send the customer to.
 * @param int $amountPesewas  the amount in pesewas (GH₵ 1 = 100 pesewas)
 */
function paystack_initialize(string $email, int $amountPesewas, string $reference, string $callbackUrl): array
{
    return paystack_request('POST', '/transaction/initialize', [
        'email'        => $email,
        'amount'       => $amountPesewas,
        'currency'     => 'GHS',
        'reference'    => $reference,
        'callback_url' => $callbackUrl,
    ]);
}

/**
 * Ask Paystack what happened to a payment.
 * data['status'] is 'success' only when the money was really taken.
 */
function paystack_verify(string $reference): array
{
    return paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));
}
