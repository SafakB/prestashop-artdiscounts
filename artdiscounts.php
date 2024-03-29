<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ArtDiscounts extends Module
{
    public function __construct()
    {
        $this->name = 'artdiscounts';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Artonomi';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->displayName = $this->l('Art Discounts');
        $this->description = $this->l('Apply an instant discount on the cheapest product when there are 3 or more products in the cart.');
        parent::__construct();
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('actionCartUpdateQuantityBefore') &&
            $this->registerHook('actionCartUpdateQuantityAfter') &&
            $this->registerHook('displayShoppingCartFooter') &&
            $this->registerHook('actionOrderStatusPostUpdate');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function hookHeader()
    {
        $this->context->controller->registerStylesheet(
            'artdiscounts-css',
            'modules/' . $this->name . '/artdiscounts.css',
            ['media' => 'all', 'priority' => 150]
        );

        $this->context->controller->registerJavascript(
            'artdiscounts',
            'modules/' . $this->name . '/artdiscounts.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }

    public function hookDisplayShoppingCartFooter($params)
    {
        return $this->hookActionCartSave($params);
    }

    public function hookActionCartUpdateQuantityBefore($params)
    {
        $this->hookActionCartSave($params);
    }

    public function hookActionCartUpdateQuantityAfter($params)
    {
        $this->hookActionCartSave($params);
    }

    public function hookActionCartSave($params)
    {
        $cart = $this->context->cart;
        $products = $cart->getProducts();
        $cheapestProductPrice = null;

        $productCount = 0;
        foreach ($products as $product) {
            $productCount += $product['quantity'];
        }


        if ($productCount >= 3) {
            foreach ($products as $product) {
                if ($cheapestProductPrice === null || $product['price'] < $cheapestProductPrice) {
                    $cheapestProductPrice = $product['price'];
                }
            }

            $discountId = (int) Configuration::get('ART_DISCOUNTS_DISCOUNT_ID_' . $cart->id);
            $cartRule = new CartRule($discountId);

            if (!Validate::isLoadedObject($cartRule)) {
                $cartRule = new CartRule();
                $cartRule->name = array_fill_keys(Language::getIDs(), '3 Al 2 Öde');
                //$cartRule->id_customer = $cart->id_customer;
                $cartRule->date_from = date('Y-m-d H:i:s');
                $cartRule->date_to = date('Y-m-d H:i:s', strtotime('+1 week'));
                $cartRule->quantity = 9999;
                $cartRule->quantity_per_user = 9999;
                $cartRule->free_shipping = false;
                $cartRule->reduction_percent = 0;
                $cartRule->active = 1;
            }

            $cartRule->reduction_amount = $cheapestProductPrice;
            $cartRule->save();

            //if (!$cart->hasCartRule($cartRule->id)) {
            $cart->removeCartRule((int) Configuration::get('ART_DISCOUNTS_DISCOUNT_ID_' . $cart->id));
            $cart->addCartRule($cartRule->id);

            Configuration::updateValue('ART_DISCOUNTS_DISCOUNT_ID_' . $cart->id, $cartRule->id);
            //}
        } else {
            $cart->removeCartRule((int) Configuration::get('ART_DISCOUNTS_DISCOUNT_ID_' . $cart->id));
        }
        // Assign the variables to the Smarty template
        $this->context->smarty->assign([
            'cart' => $cart,
        ]);
        return $this->display(__FILE__, 'views/templates/hook/displayCartTotalPriceBlock.tpl');
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        if ($params['newOrderStatus']->id == Configuration::get('PS_OS_PAYMENT')) {
            $order = new Order((int)$params['id_order']);
            $cart_id = $order->id_cart;

            $cart_rule_id = Configuration::get('ART_DISCOUNTS_DISCOUNT_ID_' . $cart_id);
            if ($cart_rule_id) {
                $cart_rule = new CartRule($cart_rule_id);

                if (Validate::isLoadedObject($cart_rule)) {
                    $cart_rule->delete();
                }

                Configuration::deleteByName('ART_DISCOUNTS_DISCOUNT_ID_' . $cart_id);
            }
        }
    }

    public function hookDisplayCartTotalPriceBlock($params)
    {
        return $this->display(__FILE__, 'views/templates/hook/displayCartTotalPriceBlock.tpl');
    }
}
