<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class NextOrderDiscount extends Module
{
    public function __construct()
    {
        $this->name = 'nextorderdiscount';
        $this->tab = 'emailing';
        $this->version = '1.0.0';
        $this->author = 'Hugo Dias';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7'
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Next Order Discount');
        $this->description = $this->l('Emails a promocode after the first order, to be used in the second order');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

//        if (!Configuration::get('NEXTORDERDISCOUNT_NAME')) {
//            $this->warning = $this->l('No name provided.');
//        }
    }

    public function install()
    {
        return (
            parent::install()
            && $this->registerHook('actionPaymentConfirmation')
            //&& $this->registerHook('actionOrderStatusUpdate')
        );
    }

    public function hookActionPaymentConfirmation($params)
    {
        $this->nextOrderDiscount($params);
    }

    /*public function hookActionOrderStatusUpdate($params)
    {
        $this->nextOrderDiscount($params);
    }*/

    /*private function nextOrderDiscount($params)
    {
        if(!empty($params['newOrderStatus'])) {

            list($customerId, $customerEmail, $customerName, $customerStats) = $this->getData($params);

            //file_put_contents('mail.txt', $customerName . ' ' . $customerEmail . ' ' . $voucherEndDate, LOCK_EX);

            if ($params['newOrderStatus']->id == Configuration::get('PS_OS_WS_PAYMENT') ||
                $params['newOrderStatus']->id == Configuration::get('PS_OS_PAYMENT')) {
                if (($customerStats['nb_orders'] == 0) || ($customerId == 3)) {
                    if ($customerId != null) {
                        list($voucher, $voucherEndDate) = $this->generateVoucher($customerId);
                        $this->sendVoucherEmail($customerEmail, $customerName, $voucher, $voucherEndDate);
                    }
                }
            }
		}
    }*/

    private function nextOrderDiscount($params)
    {
        if(!empty($params['id_order'])) {

            list($customerId, $customerEmail, $customerName, $customerStats) = $this->getData($params);

            if ($customerId != null) {

                if ($this->checkIfVoucherExists($customerId)) return;

                if ((intval($customerStats['nb_orders']) == 0) || ($customerId == 3)) {
                    list($voucher, $voucherEndDate) = $this->generateVoucher($customerId);
                    $this->sendVoucherEmail($customerEmail, $customerName, $voucher, $voucherEndDate);
                }
            }
		}
    }

    private function getData($params)
    {
        $order = new Order($params['id_order']);

        $customer =  $order->getCustomer();

        $customerName = $customer->firstname . ' ' . $customer->lastname;
        $customerStats = $customer->getStats();

        return array($customer->id, $customer->email, $customerName, $customerStats);
    }

    private function checkIfVoucherExists($customerId)
    {
        $cart_rule = new CartRule();

        return $cart_rule->cartRuleExists('CV10PC' . $customerId);
    } 

    private function generateVoucher($customerId)
    {
        $cart_rule = new CartRule();

        $language_ids = LanguageCore::getIDs(false);

        foreach ($language_ids as $language_id) {
            $cart_rule->name[$language_id] = $this->trans('PROMO10');
            $cart_rule->description = $this->trans('Desconto de 10% na próxima encomenda.');
        }

        $now = time();
        $cart_rule->date_from = date('Y-m-d H:i:s', $now);
        $cart_rule->date_to = date('Y-m-d H:i:s', strtotime('+3 month'));

        $cart_rule->highlight = true;

        $cart_rule->code = 'CV10PC' . $customerId;
        $cart_rule->id_customer = $customerId;

        $cart_rule->reduction_percent = 10;
        $cart_rule->reduction_exclude_special = true;

        $cart_rule->add();

        return array($cart_rule->code, $cart_rule->date_to);
    }

    private function sendVoucherEmail($customerEmail, $customerName, $voucher, $voucherEndDate)
    {
        return Mail::Send(
            $this->context->language->id,
            'next_order_discount',
            'Voucher com desconto de 10% na próxima encomenda',
            array(
                '{name}' => $customerName,
                '{voucher}' => $voucher,
                '{end_date}' => $voucherEndDate
            ),
            $customerEmail,
            $customerName,
            'admin@cascaviva.pt',
            'Casca Viva',
            null,
            null,
            dirname(__FILE__) . '/mails/',
            false,
            $this->context->shop->id
        );
    }

    public function uninstall()
    {
        return (
            parent::uninstall()
        );
    }
}