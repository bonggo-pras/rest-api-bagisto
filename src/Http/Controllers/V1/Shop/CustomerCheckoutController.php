<?php

namespace Webkul\RestApi\Http\Controllers\V1\Shop;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Http\Requests\CustomerAddressForm;
use Webkul\Payment\Facades\Payment;
use Webkul\RestApi\Http\Resources\V1\Checkout\CartResource;
use Webkul\RestApi\Http\Resources\V1\Checkout\CartShippingRateResource;
use Webkul\RestApi\Http\Resources\V1\Sales\OrderResource;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Shipping\Facades\Shipping;

class CustomerCheckoutController extends ResourceController
{
    /**
     * Save customer address.
     *
     * @param  \Webkul\Checkout\Http\Requests\CustomerAddressForm  $request
     * @return \Illuminate\Http\Response
     */
    public function saveAddress(CustomerAddressForm $request)
    {
        $data = $request->all();

        $data['billing']['address1'] = implode(PHP_EOL, array_filter($data['billing']['address1']));

        $data['shipping']['address1'] = implode(PHP_EOL, array_filter($data['shipping']['address1']));

        if (isset($data['billing']['id']) && str_contains($data['billing']['id'], 'address_')) {
            unset($data['billing']['id']);
            unset($data['billing']['address_id']);
        }

        if (isset($data['shipping']['id']) && Str::contains($data['shipping']['id'], 'address_')) {
            unset($data['shipping']['id']);
            unset($data['shipping']['address_id']);
        }

        if (Cart::hasError() || ! Cart::saveCustomerAddress($data) || ! Shipping::collectRates()) {
            abort(400);
        }

        $rates = [];

        foreach (Shipping::getGroupedAllShippingRates() as $code => $shippingMethod) {
            $rates[] = [
                'carrier_title' => $shippingMethod['carrier_title'],
                'rates'         => CartShippingRateResource::collection(collect($shippingMethod['rates'])),
            ];
        }

        Cart::collectTotals();

        return response([
            'data' => [
                'rates' => $rates,
                'cart'  => new CartResource(Cart::getCart()),
            ],
        ]);
    }

    /**
     * Save shipping method.
     *
     * @return \Illuminate\Http\Response
     */
    public function saveShipping(Request $request)
    {
        $shippingMethod = $request->get('shipping_method');

        if (Cart::hasError()
            || ! $shippingMethod
            || ! Cart::saveShippingMethod($shippingMethod)
        ) {
            abort(400);
        }

        Cart::collectTotals();

        return response([
            'data' => [
                'methods' => Payment::getPaymentMethods(),
                'cart'    => new CartResource(Cart::getCart()),
            ],
        ]);
    }

    /**
     * Save payment method.
     *
     * @return \Illuminate\Http\Response
     */
    public function savePayment(Request $request)
    {
        $payment = $request->get('payment');

        if (Cart::hasError() || ! $payment || ! Cart::savePaymentMethod($payment)) {
            abort(400);
        }

        return response([
            'data' => [
                'cart' => new CartResource(Cart::getCart()),
            ],
        ]);
    }

    /**
     * Check for minimum order.
     *
     * @return \Illuminate\Http\Response
     */
    public function checkMinimumOrder()
    {
        $minimumOrderAmount = (float) core()->getConfigData('sales.orderSettings.minimum-order.minimum_order_amount') ?? 0;

        $status = Cart::checkMinimumOrder();

        return response([
            'data'    => [
                'cart'   => new CartResource(Cart::getCart()),
                'status' => ! $status ? false : true,
            ],
            'message' => ! $status ? __('shop::app.checkout.cart.minimum-order-message', ['amount' => core()->currency($minimumOrderAmount)]) : 'Success',
        ]);
    }

    /**
     * Save order.
     *
     * @param  \Webkul\Sales\Repositories\OrderRepository  $orderRepository
     * @return \Illuminate\Http\Response
     */
    public function saveOrder(OrderRepository $orderRepository)
    {
        if (Cart::hasError()) {
            abort(400);
        }

        Cart::collectTotals();

        $this->validateOrder();

        $cart = Cart::getCart();

        if ($redirectUrl = Payment::getRedirectUrl($cart)) {
            return response([
                'redirect_url' => $redirectUrl,
            ]);
        }

        $order = $orderRepository->create(Cart::prepareDataForOrder());

        Cart::deActivateCart();

        return response([
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Validate order before creation.
     *
     * @return void|\Exception
     */
    protected function validateOrder()
    {
        $cart = Cart::getCart();

        $minimumOrderAmount = core()->getConfigData('sales.orderSettings.minimum-order.minimum_order_amount') ?? 0;

        if (! $cart->checkMinimumOrder()) {
            throw new \Exception(__('shop::app.checkout.cart.minimum-order-message', ['amount' => core()->currency($minimumOrderAmount)]));
        }

        if ($cart->haveStockableItems() && ! $cart->shipping_address) {
            throw new \Exception(__('Please check shipping address.'));
        }

        if (! $cart->billing_address) {
            throw new \Exception(__('Please check billing address.'));
        }

        if ($cart->haveStockableItems() && ! $cart->selected_shipping_rate) {
            throw new \Exception(__('Please specify shipping method.'));
        }

        if (! $cart->payment) {
            throw new \Exception(__('Please specify payment method.'));
        }
    }
}
