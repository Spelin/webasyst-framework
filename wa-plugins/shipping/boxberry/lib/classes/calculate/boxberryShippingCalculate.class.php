<?php

/**
 * Class boxberryShippingCalculate
 */
abstract class boxberryShippingCalculate
{
    /**
     * To make FROM prices in the new checkout
     */
    const MAGIC_NUMBER_TO_MAKE_RANGE = 1;

    /**
     * @return array
     */
    abstract function getVariants();

    abstract public function getPrefix();


    /**
     * @var boxberryShipping|null
     */
    protected $bxb = null;

    /**
     * boxberryShippingCalculate constructor.
     * @param boxberryShipping $bxb
     */
    public function __construct(boxberryShipping $bxb)
    {
        $this->bxb = $bxb;
    }

    /**
     * @param $data
     * @return array
     */
    public function getDeliveryCostsAPI($data)
    {
        $data['targetstart'] = $this->bxb->targetstart;
        $data['weight'] = $this->bxb->getParcelWeight();
        $data['ordersum'] = $this->bxb->getAssessedPrice();
        $data = array_merge($data, $this->getDimensions());

        $api_manager = $this->getApiManager();
        $rate = $api_manager->getDeliveryCosts($data);

        //If the price is less than 10 rubles, then something went wrong.
        if (empty($rate) || (isset($rate['price']) && $rate['price'] < 10)) {
            $rate['price'] = false;
        }

        if ($rate['price']) {
            $free_price = (float)$this->bxb->free_price;

            // Check if you need to make delivery free
            if ($free_price > 0 && $this->bxb->getTotalPrice() > $free_price) {
                $rate['price'] = 0;
            }
        }

        $result = [
            'price'           => $rate['price'],
            'delivery_period' => ifset($rate, 'delivery_period', 0)
        ];

        return $result;
    }

    /**
     * get package sizes
     *
     * @return array
     */
    protected function getDimensions()
    {
        $plugin_sizes = $this->bxb->getTotalSize();
        $result = [];

        $height = ifset($plugin_sizes, 'height', 0);
        $width = ifset($plugin_sizes, 'width', 0);
        $length = ifset($plugin_sizes, 'length', 0);

        // If some size is not valid, then we take the standard sizes
        if (empty($length) || empty($width) || empty($height)) {
            $height = $this->bxb->default_height;
            $width = $this->bxb->default_width;
            $length = $this->bxb->default_length;
        }

        // convert to cm
        // Centimeters are the requirements of boxberry
        $result['height'] = (float)$length * 100;
        $result['width'] = (float)$width * 100;
        $result['depth'] = (float)$height * 100;

        return $result;
    }

    /**
     * Returns information about whether a specific point was selected.
     * @return bool
     */
    protected function isVariantSelected()
    {
        $id = $this->bxb->getSelectedServiceId();

        $result = false;
        if ($id && strpos($id, $this->getPrefix()) !== false) {
            $result = true;
        }

        return $result;
    }

    /**
     * Returns the payment method for the plugin
     *
     * @return array
     */
    protected function getPayment()
    {
        $result = [];
        $mode = $this->getMode();

        if ($mode === 'all') {
            $result = [waShipping::PAYMENT_TYPE_CARD, waShipping::PAYMENT_TYPE_CASH, waShipping::PAYMENT_TYPE_PREPAID];
        } elseif ($mode === 'prepayment') {
            $result = [waShipping::PAYMENT_TYPE_PREPAID];
        }

        return $result;
    }

    /**
     * Returns the amount of cash on delivery if the payment option is not an advance payment
     * If the payment option is not selected, we always consider the minimum cost
     *
     * @return float
     */
    protected function getPaysum()
    {
        $result = 0.0;

        $payment_type = $this->bxb->getSelectedPaymentTypes();
        // Если плагин оплаты позволят рассчитаться и авансом и наместе, то значит что-то пошло не так.
        // В таком случае считаем минимальную цену.
        if ($payment_type && !in_array(waShipping::PAYMENT_TYPE_PREPAID, $payment_type)) {
            $result = $this->bxb->getTotalPrice();
        }

        return $result;
    }

    /**
     * For prepayment, you can only pay with remote payments.
     *
     * @return bool
     */
    protected function getErrors()
    {
        $payment_type = $this->bxb->getSelectedPaymentTypes();
        $not_only_prepayment = count($payment_type) > 1 || !in_array(waShipping::PAYMENT_TYPE_PREPAID, $payment_type);

        $result = false;
        if ($this->getMode() === 'prepayment' && $payment_type && $not_only_prepayment) {
            $result = true;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getTimezone()
    {
        return 'Europe/Moscow';
    }

    /**
     * @return boxberryShippingApiManager
     */
    protected function getApiManager()
    {
        return new boxberryShippingApiManager($this->bxb->token, $this->bxb->api_url);
    }

    /**
     * @return string
     */
    public static function getVariantSeparator()
    {
        return '__';
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return '';
    }
}