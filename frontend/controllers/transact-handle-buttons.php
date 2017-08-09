<?php

namespace Transact\FrontEnd\Controllers\Buttons;
/**
 * It will take care of print different buttons
 *
 * Class transactHandleButtons
 */
class transactHandleButtons
{
    /**
     * text to be included on the button
     */
    const BUTTON_TEXT = 'Purchase with Transact for';
    const TOKENS_TEXT = 'cents';
    const TOKEN_TEXT = 'cent';
    const SUBSCRIBE_TEXT = 'Subscribe';
    const DONATE_TEXT = 'Donate';

    /**
     * Keys for buttons options, by default PURCHASE_AND_SUBSCRIPTION
     */
    const PURCHASE_AND_SUBSCRIPTION = 1;
    const ONLY_PURCHASE = 2;
    const ONLY_SUBSCRIBE = 3;

    /**
     * @var int post_id
     */
    protected $post_id;

    /**
     * @var Transact\FrontEnd\Controllers\Api\TransactApi
     */
    protected $transact_api;

    protected $options;


    public function __construct($post_id, $transact_api)
    {
        $this->post_id = $post_id;
        $this->transact_api = $transact_api;
        $this->options = get_option( 'transact-settings' );
    }

    /**
     * Will check if user wants donation on this article, in that case will show donation placeholder otherwise
     * Will check if the user have a subscription and which kind of button want to use (given on post settings)
     *
     * @return string
     */
    public function print_buttons()
    {
        if ($this->get_if_article_donation()) {
            return $this->print_donation_button();
        } else {
            $button_type = $this->get_button_type();
            switch($button_type) {
                case (self::PURCHASE_AND_SUBSCRIPTION):
                    return $this->print_purchase_and_subscription($this->options, $this->transact_api);
                    break;
                case (self::ONLY_PURCHASE):
                    return $this->print_single_button($this->options, $this->transact_api, $button_type);
                    break;
                case (self::ONLY_SUBSCRIBE):
                    return $this->print_single_button($this->options, $this->transact_api, $button_type);
                    break;
                default:
                    return $this->print_single_button($this->options, $this->transact_api, self::ONLY_PURCHASE);
                    break;
            }
        }
    }

    /**
     * Returning full donation button with price input.
     *
     * @return string
     */
    public function print_donation_button() {
        $price = get_post_meta($this->post_id, 'transact_price', true );
        $button = $this->print_donate_button($this->options);
        $input = '<input type="number" name="donate" id="donate_val" onchange="setDonateAmount()" value="'.$price.'"/>';
        return $button . $input;
    }

    /**
     * Get button type from post options
     *
     * @return mixed
     */
    public function get_button_type()
    {
        return get_post_meta( $this->post_id, 'transact_display_button' , true );
    }

    /**
     * Checks if donations are activated on transact settings and on post level.
     * @return bool
     */
    public function get_if_article_donation()
    {
        if (isset($this->options['donations']) && $this->options['donations']) {
            if (get_post_meta( $this->post_id, 'transact_donations' , true )) {
                return true;
            }
        }
        return false;
    }

    /**
     * It prints purchase button and subscription button together
     *
     * @param $options
     * @param $transact_api
     * @return string
     */
    protected function print_purchase_and_subscription($options, $transact_api)
    {
        $content = $this->print_single_button($options, $transact_api, self::ONLY_PURCHASE);
        $content .= $this->print_single_button($options, $transact_api, self::ONLY_SUBSCRIBE);
        return $content;
    }

    /**
     * It prints a single button, either subscription or purchase
     *
     * @param $options
     * @param $transact_api
     * @param $button_type button type to print subscription or purchase
     * @return string html button
     */
    protected function print_single_button($options, $transact_api, $button_type)
    {
        $button_text = $this->get_button_text($button_type);

        $button_background_color_style = (isset($options['background_color']) ? 'background-color:' . esc_attr($options['background_color']) . ';' : '');
        $button_text_color_style = (isset($options['text_color']) ? 'color:' . esc_attr($options['text_color']) . ';' : '');
        $background_fade_color_style = '';
        if(isset($options['page_background_color'])) {
            list($r, $g, $b) = sscanf($options['page_background_color'], "#%02x%02x%02x");
            $background_fade_color_style = "background:linear-gradient(to bottom, rgba($r,$g,$b,0), rgba($r,$g,$b,1) 68%, rgba($r,$g,$b,1))";
        }

        $onclick = ($button_type == self::ONLY_PURCHASE) ? 'doPurchase()' : 'doSubscription()';
        $extra_id = ($button_type == self::ONLY_PURCHASE) ? 'purchase' : 'subscription';

        $button = sprintf(
            '<div class="transact_purchase_button fade" style="%s"><button style="%s" id="button_purchase %s" onclick="%s">%s</button></div>',
            $background_fade_color_style,
            $button_background_color_style . $button_text_color_style,
            $extra_id,
            $onclick,
            $button_text
        );

        return $button;
    }

    /**
     * Taking care or printing only button with transact styling taken from settings
     *
     * @param $options
     * @return string
     */
    protected function print_donate_button($options)
    {
        $button_text = self::DONATE_TEXT;

        $button_background_color_style = (isset($options['background_color']) ? 'background-color:' . esc_attr($options['background_color']) . ';' : '');
        $button_text_color_style = (isset($options['text_color']) ? 'color:' . esc_attr($options['text_color']) . ';' : '');

        $onclick = 'doDonate()';
        $extra_id = 'donation';

        $button = sprintf(
            '<button style="%s" id="button_purchase %s" onclick="%s">%s</button>',
            $button_background_color_style . $button_text_color_style,
            $extra_id,
            $onclick,
            $button_text
        );

        return $button;
    }

    /**
     * Getting button text depending button type
     * @param $button_type
     * @return string|void
     */
    protected function get_button_text($button_type)
    {
        if ($button_type == self::ONLY_PURCHASE) {
            $price = get_post_meta($this->post_id, 'transact_price', true );
            if ($price == 1) {
                $token_text = __(self::TOKEN_TEXT, 'transact');
            } else {
                $token_text = __(self::TOKENS_TEXT, 'transact');
            }
            $button_text = __(self::BUTTON_TEXT, 'transact') . ' '.  $price . ' ' . $token_text;
        } else {
            $button_text = __(self::SUBSCRIBE_TEXT, 'transact');
        }
        return $button_text;
    }

}
