<?php
namespace Transact\FrontEnd\Controllers\Api;

/**
 * Including transact io php library
 */
require_once  plugin_dir_path(__FILE__) . '/../../vendors/transact-io-php/transact-io.php';

/**
 * We need admin settings menu to rescue the settings
 */
require_once  plugin_dir_path(__FILE__) . '/../../admin/controllers/transact-admin-settings-menu.php';
use Transact\Admin\Settings\Menu\AdminSettingsMenuExtension;

/**
 * We need admin settings menu to rescue the settings
 */
require_once  plugin_dir_path(__FILE__) . '/../../admin/controllers/transact-admin-settings-post.php';
use Transact\Admin\Settings\Post\AdminSettingsPostExtension;

/**
 * We need cookie manager
 */
require_once  plugin_dir_path(__FILE__) . '/transact-cookie-manager.php';
use Transact\FrontEnd\Controllers\Cookie\CookieManager;



/**
 * Class TransactApi
 */
class TransactApi
{
    /**
     * @var \TransactIoMsg
     */
    public $transact;

    /**
     * @var int
     */
    public $post_id;

    /**
     * All information to set on Transact Io Object
     */
    protected $recipient_id;
    protected $secret_id;
    protected $env;

    protected $price;     // price in tokens for the item
    protected $item_id;   // unique code for article (Item code)
    protected $sales_id;  // unique code for sale

    protected $article_title;
    protected $article_url;

    protected $method;
    protected $alg;

    protected $affiliate;

    /**
     * @param $post_id
     * @throws \Exception
     */
    function __construct($post_id)
    {
        $this->post_id = $post_id;
        $this->get_transact_information();
        $this->transact = new \TransactIoMsg();
    }

    /**
     * Set All info needed for Transact
     * We grab the information from Transact Settings (Dashboard)
     * and specific post.
     *
     */
    function get_transact_information()
    {
        $settings_menu_dashboard = new AdminSettingsMenuExtension();
        $this->recipient_id = $settings_menu_dashboard->get_account_id();
        $this->secret_id    = $settings_menu_dashboard->get_secret();
        $this->env          = strtoupper($settings_menu_dashboard->get_env()); // TEST or PROD

        $settings_post = new AdminSettingsPostExtension($this->post_id);
        $this->price     = $settings_post->get_transact_price();
        $this->item_id   = $settings_post->get_transact_item_code();

        // creates a uniqid, with item_id as prefix and then md5 (32 characters)
        $this->sales_id  = md5(uniqid($this->item_id, true));

        $this->article_title = get_the_title($this->post_id);
        $this->article_url   = get_permalink($this->post_id);

        /**
         * todo: what are the options?
         */
        $this->method = 'CLOSE';
        $this->alg    = 'HS256';

    }

    /**
     * Set Affiliated reference
     *
     * @param int $affiliate_id
     */
    function set_affiliate($affiliate_id)
    {
        $this->affiliate = $affiliate_id;
    }

    /**
     * Gets token to set up on transact js library
     *
     * @return string return token json {"token":"xxx"}
     */
    function get_token()
    {
        $this->init_sale_parameters($this->transact);

        $response = array(
            'token' => $this->transact->getToken()
        );
        return json_encode($response);
    }

    /**
     * Gets subscription token to set up on transact js library
     *
     * @return string return token json {"token":"xxx"}
     */
    function get_subscription_token()
    {
        $this->init_sale_parameters($this->transact);

        $response = array(
            'token' => $this->transact->getSubscriptionToken()
        );
        return json_encode($response);
    }

    /**
     * Get donation token
     *
     * @param $price
     * @param string $affiliate_id
     * @throws \Exception
     */
    function get_donation_token($price, $affiliate_id = null)
    {
        $this->init_sale_parameters($this->transact);
        $this->transact->setPrice($price);

        if ($affiliate_id) {
            $this->transact->setAffiliate($affiliate_id);
        }

        $response = array(
            'token' => $this->transact->getToken()
        );
        echo json_encode($response);
    }

    function decode_token($token)
    {
        $this->transact->setSecret($this->secret_id);
        $this->transact->setAlg($this->alg);
        return $this->transact->decodeToken($token);
    }

    /**
     * Init library with sales parameter for a given article
     *
     * @param \TransactIoMsg $transact
     */
    function init_sale_parameters($transact)
    {
        $transact->setSecret($this->secret_id);
        $transact->setAlg($this->alg);

        // Required: set ID of who gets paid
        $transact->setRecipient($this->recipient_id);

        // Required:  Set the price of the sale
        //todo: what is max value?
        $transact->setPrice($this->price);

        // Required:  Set PROD to use real money,  TEST for testing
        $transact->setClass($this->env);

        // Required:  set URL associated with this puchase
        // User should be able to return to this URL
        $transact->setURL($this->article_url);

        // Recommended: Title for customer to read for the purchase
        $transact->setTitle($this->article_title);

        $transact->setMethod($this->method); // Optional: by default close the popup


        // Unique code for seller to set to what they want
        //  This could be a code for the item your selling
        $transact->setItem($this->item_id);

        // Optional Unique ID of this sale
        $transact->setUid($this->sales_id);

        // Set Affiliated if exists
        $transact->setAffiliate($this->affiliate);

        // Set your own meta data
        // Note you must keep this short to avoid going over the 1024 byte limt
        // of the token URL
        //todo: what ist his?
        $transact->setMeta(array(
            'your' => 'data',
            'anything' => 'you want'
        ));
    }

    /**
     * It checks if a user have purchased the article
     * it checks user cookie against Transaction table DB
     *
     * @return bool
     */
    public function is_premium()
    {
        $cookie_manager = new CookieManager();
        if ($cookie_manager->validate_cookie($this->post_id)) {
            return true;
        } else {
            return false;
        }
    }
    public function get_price()
    {
        return $this->price;
    }

}
