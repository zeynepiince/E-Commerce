<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/OrderService.php';

use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Currency;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;
use Iyzipay\Model\CheckoutForm;

function iyzico_options(): Options
{
    payments_autoload();

    $apiKey = trim((string) (function_exists('zera_env') ? zera_env('IYZICO_API_KEY', '') : getenv('IYZICO_API_KEY')) ?: '');
    $secretKey = trim((string) (function_exists('zera_env') ? zera_env('IYZICO_SECRET_KEY', '') : getenv('IYZICO_SECRET_KEY')) ?: '');
    $baseUrl = trim((string) (function_exists('zera_env') ? zera_env('IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com') : getenv('IYZICO_BASE_URL')) ?: 'https://sandbox-api.iyzipay.com');

    if ($apiKey === '' || $secretKey === '') {
        throw new RuntimeException('iyzico API keys are not configured');
    }

    $options = new Options();
    $options->setApiKey($apiKey);
    $options->setSecretKey($secretKey);
    $options->setBaseUrl($baseUrl);

    return $options;
}

function iyzico_is_configured(): bool
{
    $apiKey = function_exists('zera_env') ? zera_env('IYZICO_API_KEY', '') : getenv('IYZICO_API_KEY');
    $secretKey = function_exists('zera_env') ? zera_env('IYZICO_SECRET_KEY', '') : getenv('IYZICO_SECRET_KEY');
    return trim((string) ($apiKey ?? '')) !== '' && trim((string) ($secretKey ?? '')) !== '';
}

function iyzico_checkout_ready(): bool
{
    return iyzico_is_configured() && iyzico_vendor_available();
}

/** Demo checkout — gerçek ödeme olmadan sipariş tamamlama. */
function checkout_demo_mode_enabled(): bool
{
    $flag = function_exists('zera_env') ? zera_env('CHECKOUT_DEMO_MODE') : getenv('CHECKOUT_DEMO_MODE');
    if ($flag === null || $flag === false || $flag === '') {
        return !iyzico_is_configured();
    }
    $flag = strtolower(trim((string) $flag));
    if (in_array($flag, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function checkout_can_process(): bool
{
    return iyzico_checkout_ready() || checkout_demo_mode_enabled();
}

/**
 * @param array<string, mixed> $shipping
 * @param array<int, array{product_id: ?int, qty: int, unit_price: float, name: string}> $lines
 * @return array{token: string, payment_page_url: string, paid_price_try: float, price_try: float}
 */
function iyzico_initialize_checkout(
    int $orderId,
    string $conversationId,
    float $totalUsd,
    array $lines,
    array $shipping,
    array $userRow,
    string $callbackUrl,
    string $locale = 'tr'
): array {
    $options = iyzico_options();
    $rate = iyzico_usd_to_try_rate();
    $priceTry = round($totalUsd * $rate, 2);
    $paidPriceTry = $priceTry;

    $request = new CreateCheckoutFormInitializeRequest();
    $request->setLocale($locale === 'en' ? Locale::EN : Locale::TR);
    $request->setConversationId($conversationId);
    $request->setPrice(format_try_amount($priceTry));
    $request->setPaidPrice(format_try_amount($paidPriceTry));
    $request->setCurrency(Currency::TL);
    $request->setBasketId('zera_basket_' . $orderId);
    $request->setPaymentGroup(PaymentGroup::PRODUCT);
    $request->setCallbackUrl($callbackUrl);
    $request->setEnabledInstallments([2, 3, 6, 9]);

    $fullName = trim((string) ($shipping['full_name'] ?? $userRow['full_name'] ?? 'Customer'));
    $nameParts = preg_split('/\s+/u', $fullName, 2) ?: [];
    $firstName = $nameParts[0] ?? 'Customer';
    $lastName = $nameParts[1] ?? 'User';

    $phone = preg_replace('/\D+/', '', (string) ($shipping['phone'] ?? ''));
    if ($phone === '') {
        $phone = '5555555555';
    }
    if (strlen($phone) > 11) {
        $phone = substr($phone, -11);
    }

    $buyer = new Buyer();
    $buyer->setId('user_' . (int) ($userRow['user_id'] ?? 0));
    $buyer->setName($firstName);
    $buyer->setSurname($lastName);
    $buyer->setGsmNumber($phone);
    $buyer->setEmail((string) ($shipping['email'] ?? $userRow['email'] ?? 'customer@example.com'));
    $buyer->setIdentityNumber((string) ((function_exists('zera_env') ? zera_env('IYZICO_DEFAULT_IDENTITY', '11111111111') : getenv('IYZICO_DEFAULT_IDENTITY')) ?: '11111111111'));
    $buyer->setRegistrationAddress((string) ($shipping['address'] ?? 'Address'));
    $buyer->setIp($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $buyer->setCity((string) ($shipping['city'] ?? 'Istanbul'));
    $buyer->setCountry('Turkey');
    $buyer->setZipCode((string) ($shipping['zip'] ?? '34000'));
    $request->setBuyer($buyer);

    $address = new Address();
    $address->setContactName($fullName);
    $address->setCity((string) ($shipping['city'] ?? 'Istanbul'));
    $address->setCountry('Turkey');
    $address->setAddress((string) ($shipping['address'] ?? 'Address'));
    $address->setZipCode((string) ($shipping['zip'] ?? '34000'));
    $request->setShippingAddress($address);
    $request->setBillingAddress($address);

    $basketItems = [];
    $allocated = 0.0;
    $lineCount = count($lines);
    foreach ($lines as $index => $line) {
        $lineUsd = (float) ($line['unit_price'] ?? 0) * (int) ($line['qty'] ?? 1);
        $lineTry = round($lineUsd * $rate, 2);
        if ($index === $lineCount - 1) {
            $lineTry = round($paidPriceTry - $allocated, 2);
        } else {
            $allocated += $lineTry;
        }
        if ($lineTry <= 0) {
            $lineTry = 0.01;
        }

        $item = new BasketItem();
        $item->setId('item_' . ($line['product_id'] ?? $index));
        $item->setName(mb_substr((string) ($line['name'] ?? 'Product'), 0, 100));
        $item->setCategory1('Retail');
        $item->setItemType(BasketItemType::PHYSICAL);
        $item->setPrice(format_try_amount($lineTry));
        $basketItems[] = $item;
    }
    $request->setBasketItems($basketItems);

    $checkout = CheckoutFormInitialize::create($request, $options);
    $raw = $checkout->getRawResult();
    $decoded = is_string($raw) ? json_decode($raw, true) : null;

    if ($checkout->getStatus() !== 'success') {
        $message = $checkout->getErrorMessage() ?: 'iyzico checkout initialization failed';
        throw new RuntimeException($message);
    }

    return [
        'token' => (string) $checkout->getToken(),
        'payment_page_url' => (string) $checkout->getPaymentPageUrl(),
        'checkout_form_content' => (string) $checkout->getCheckoutFormContent(),
        'paid_price_try' => $paidPriceTry,
        'price_try' => $priceTry,
        'raw' => is_array($decoded) ? $decoded : [],
    ];
}

/**
 * @return array{success: bool, conversation_id: string, payment_status: string, raw: array<string, mixed>}
 */
function iyzico_retrieve_checkout(string $token, string $locale = 'tr'): array
{
    $options = iyzico_options();

    $request = new RetrieveCheckoutFormRequest();
    $request->setLocale($locale === 'en' ? Locale::EN : Locale::TR);
    $request->setToken($token);

    $checkout = CheckoutForm::retrieve($request, $options);
    $raw = $checkout->getRawResult();
    $decoded = is_string($raw) ? json_decode($raw, true) : [];
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $paymentStatus = (string) ($checkout->getPaymentStatus() ?? '');
    $success = strtolower($paymentStatus) === 'success' || (string) $checkout->getStatus() === 'success';
    $conversationId = (string) ($checkout->getConversationId() ?? ($decoded['conversationId'] ?? ''));

    return [
        'success' => $success,
        'conversation_id' => $conversationId,
        'payment_status' => $paymentStatus,
        'raw' => $decoded,
    ];
}
