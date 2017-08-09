<?php
namespace Transact\FrontEnd\Controllers\Post;

use Transact\FrontEnd\Controllers\Api\TransactApi;
require_once  plugin_dir_path(__FILE__) . 'transact-api.php';

use Transact\Utils\Config\Parser\ConfigParser;
require_once  plugin_dir_path(__FILE__) . '../../utils/transact-utils-config-parser.php';

use Transact\Models\transactTransactionsTable\transactSubscriptionTransactionsModel;
require_once  plugin_dir_path(__FILE__) . '../../models/transact-subscription-transactions-table.php';

use Transact\Models\transactTransactionsTable\transactTransactionsModel;
require_once  plugin_dir_path(__FILE__) . '../../models/transact-transactions-table.php';

use Transact\Utils\Settings\cpt\SettingsCpt;
require_once  plugin_dir_path(__FILE__) . '../../utils/transact-settings-cpt.php';

use Transact\FrontEnd\Controllers\Buttons\transactHandleButtons;
require_once  plugin_dir_path(__FILE__) . 'transact-handle-buttons.php';


/**
 * Class FrontEndPostExtension
 */
class FrontEndPostExtension
{
    /**
     * config controller
     * @var
     */
    protected $config;

    /**
     * Post id where this hook is called.
     *
     * @var int
     */
    protected $post_id;

    /**
     * All hooks to single_post template
     */
    public function hookSinglePost()
    {
        $this->config = new ConfigParser();
        add_filter( 'the_content', array($this, 'filter_pre_get_content' ), 999);
        add_action( 'wp_enqueue_scripts', array($this, 'load_js_xsact_library'));
        add_action( 'wp_enqueue_scripts', array($this, 'load_css_xsact_library'));

        /**
         * Registering Ajax Calls on single post (Purchase Token)
         */
        add_action( 'wp_ajax_nopriv_get_token', array($this, 'request_token_callback' ));
        add_action( 'wp_ajax_get_token',        array($this, 'request_token_callback' ));

        /**
         * Registering Ajax Calls on single post (Subscription Token)
         */
        add_action( 'wp_ajax_nopriv_get_subscription_token', array($this, 'request_subscription_token_callback' ));
        add_action( 'wp_ajax_get_subscription_token',        array($this, 'request_subscription_token_callback' ));

        /**
         * Registering Ajax Calls on single post (Donation Token)
         */
        add_action( 'wp_ajax_nopriv_get_donation_token', array($this, 'request_donation_callback' ));
        add_action( 'wp_ajax_get_donation_token',        array($this, 'request_donation_callback' ));

        /**
         * Registering callback when user buys the item
         */
        add_action( 'wp_ajax_nopriv_get_purchased_content', array($this, 'purchased_content_callback' ));
        add_action( 'wp_ajax_get_purchased_content',        array($this, 'purchased_content_callback' ));

        /**
         * Making sure comments are open only for premium users
         */
        add_filter( 'comments_array', array($this, 'comments_array'));
        add_filter( 'comments_open', array($this, 'close_comments') );

    }

    /**
     * Hooks into content, if the user is premium for that content
     * it will show the premium content for it, otherwise the normal one adding the button to buy on transact.io.
     *
     * @param string $content
     * @return string
     */
    public function filter_pre_get_content($content)
    {
        $options = get_option( 'transact-settings' );
        /**
         * If it is not the scope, we return the normal content (could be used in a archive for instance)
         */
        if (!$this->check_scope()) {
            return $content;
        }

        $transact_api = new TransactApi($this->post_id);
        /**
         * If the page is set to donation, we always use free content.
         */
        if ($this->check_if_post_is_under_donation($this->post_id)) {
            return $this->get_free_content_with_buttons($content, $transact_api);
        } else if ($transact_api->is_premium()) {
            return $this->get_premium_content();
        } else {
            return $this->get_free_content_with_buttons($content, $transact_api);
        }
    }

    /**
     * Function to get premium content from transact_premium_content metadata.
     *
     * @return string
     */
    function get_premium_content()
    {
        $premium_content = get_post_meta( $this->post_id, 'transact_premium_content' , true ) ;
        // wpautop emulates normal wp editor behaviour (adding <p> automatically)
        return wpautop(htmlspecialchars_decode(do_shortcode($premium_content)));
    }

    /**
     * Function to get free content with buttons
     *
     * @param $content
     * @param $transact_api
     * @return string
     */
    function get_free_content_with_buttons($content, $transact_api)
    {
        global $post;
        if (!has_shortcode($post->post_content, 'transact_button')) {
            $button_controller = new transactHandleButtons($this->post_id, $transact_api);
            $content = $content . $button_controller->print_buttons();
        }
        return $content;
    }

    /**
     * Loading Transact JS Library
     */
    public function load_js_xsact_library()
    {
        if (!$this->check_scope()) {
            return;
        }

        $redirect_page = '';
        $donation = 0;
        if ($this->check_if_post_is_under_donation($this->post_id)) {
            $redirect_page = $this->check_redirect_after_donation($this->post_id);
            $donation = 1;
        }

        /**
         * Loading external library (JS API)
         */
        wp_enqueue_script('xsact', $this->config->getJSLibrary());
        $url = array(
            'url' => plugins_url('/ajax/ajax-call.php', __FILE__),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'post_id' => $this->post_id,
            'affiliate_id' => $this->get_affiliate(),
            'donation' => $donation,
            'price' => get_post_meta($this->post_id, 'transact_price', true )
        );

        if (strlen($redirect_page) > 0) {
            $url['redirect_after_donation'] = $redirect_page;
        }

        /**
         * Loading transact scripts (callbacks)
         */
        wp_register_script( 'transact_callback',  FRONTEND_ASSETS_URL . 'transact_post.js', array('jquery') );
        wp_localize_script( 'transact_callback', 'url', $url );
        wp_enqueue_script( 'transact_callback' );
    }

    /**
     * Get Affiliated reference from url if exists
     *
     * @return int|string
     */
    function get_affiliate()
    {
        if (!$affiliate = filter_input(INPUT_GET, "aff", FILTER_VALIDATE_INT)) {
            $affiliate = '';
        }
        return $affiliate;
    }

    /**
     * Loading Transact css Library
     */
    public function load_css_xsact_library()
    {
        if (!$this->check_scope()) {
            return;
        }

        /**
         * Loading external library (JS API)
         */
        wp_enqueue_style('xsact', FRONTEND_ASSETS_URL . 'style.css');
    }

    /**
     * admin-ajax.php?action=get_token
     * get_token ajax call handler to get and set Token from Transact (onload)
     *
     */
    public function request_token_callback()
    {
        $transact = new TransactApi($_REQUEST['post_id']);
        if (!empty($_REQUEST['affiliate_id'])) {
            $transact->set_affiliate((int)$_REQUEST['affiliate_id']);
        }
        $token = $transact->get_token();
        header('Content-Type: text/javascript; charset=utf8');
        echo $token;
        exit;
    }

    /**
     * admin-ajax.php?action=get_subscription_token
     * get_subscription_token ajax call handler to get and set subscription Token from Transact (onload)
     */
    public function request_subscription_token_callback()
    {
        $transact = new TransactApi($_REQUEST['post_id']);
        if (!empty($_REQUEST['affiliate_id'])) {
            $transact->set_affiliate((int)$_REQUEST['affiliate_id']);
        }
        $token = $transact->get_subscription_token();
        header('Content-Type: text/javascript; charset=utf8');
        echo $token;
        exit;
    }

    public function request_donation_callback()
    {
        if (!$this->check_if_post_is_under_donation($_REQUEST['post_id'])) {
            header("HTTP/1.1 401 Unauthorized");
            exit;
        }

        header('Content-Type: text/javascript; charset=utf8');
        $transact = new TransactApi($_REQUEST['post_id']);
        $price = (isset($_REQUEST['price']) && is_numeric($_REQUEST['price'])) ? $_REQUEST['price'] : null;
        if (!$price) {
            echo json_encode(new ErrorResponse('400', 'Invalid Price'));
            exit;
        }
        $affiliate = (isset($_REQUEST['affiliate_id']) && is_numeric($_REQUEST['affiliate_id'])) ? $_REQUEST['affiliate_id'] : null;
        $token = $transact->get_donation_token($price, $affiliate);
        echo $token;
        exit;
    }

    /**
     * Checks if user has set $post_id as donation post
     *
     * @param $post_id
     * @return bool
     */
    public function check_if_post_is_under_donation($post_id)
    {
        $options = get_option( 'transact-settings' );
        if (isset($options['donations']) && $options['donations']) {
            if (get_post_meta( $post_id, 'transact_donations' , true )) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has set a redirect after donation.
     *
     * @param $post_id
     * @return string empty if not url
     */
    public function check_redirect_after_donation($post_id) {
        $redirect_url = '';
        $redirect_id = get_post_meta( $post_id, 'transact_redirect_after_donation' , true );
        if (is_numeric($redirect_id) && $redirect_id > 0) {
            $redirect_url = get_page_link($redirect_id);
        }
        return $redirect_url;
    }

    /**
     * Callback from purchase
     * Check if it is subscription or a normal purchase
     * Acts accordingly (record on DB)
     */
    public function purchased_content_callback()
    {
        $transact = new TransactApi($_REQUEST['post_id']);

        header('Content-Type: text/javascript; charset=utf8');
        try {
            $decoded = $transact->decode_token($_REQUEST['t']);
            $subscription = 0;

            /**
             * If it is a subscription
             */
            if (isset($decoded->sub) && ($decoded->sub == true)) {
                $tableModel = new transactSubscriptionTransactionsModel();
                $tableModel->create_subscription($decoded->sub_expires, $decoded->uid, $decoded->iat);
                $subscription = 1;
            } else {
                /**
                 * Creates a row on transaction table with purchase info
                 */
                $tableModel = new transactTransactionsModel();
                $tableModel->create_transaction($_REQUEST['post_id'], $decoded->uid, $decoded->iat);
            }
                echo json_encode(array(
                    'status' => 'OK',
                    'decoded' => $decoded,
                    'subscription' => $subscription
                ));

        } catch (Exception $e) {

            echo json_encode(array(
                'content' => 'Failed validation',
                'status' => 'ERROR',
                'message' =>  $e->getMessage(),
            ));
        }
        exit;
    }

    /**
     * First we check if the settings have been set on the Dashboard,
     * after:
     * We want the previous filters to work only on the proper scope
     * and that is single posts (singe_post templates) or CPT enabled or pages
     *
     * @return bool
     */
    public function check_scope()
    {
        /**
         * Setting the post id, is the only scope where I can get it.
         */
        $this->post_id = get_the_ID();

        if (get_transient(SETTING_VALIDATION_TRANSIENT) &&
            get_post_meta( $this->post_id, 'transact_item_code', true ) &&
            get_post_meta( $this->post_id, 'transact_price', true ) &&
            ((is_single() && (get_post_type() == 'post') || (in_array(get_post_type(), SettingsCpt::get_cpts_enable_for_transact()))) ||
                get_post_type() == 'page'))
        {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Hook for comments_open
     * It decides if the user is not premium, cannot write on comments
     *
     * @param $open
     * @return bool
     */
    function close_comments($open)
    {
        /**
         * If the user is submitting the comment, we have to open the comments again
         * on that step not detected by is_premium
         */
        if (strpos($_SERVER['REQUEST_URI'], 'wp-comments-post')) {
            return $open;
        }

        if ((new TransactApi($this->post_id))->is_premium() == false) {
            return false;
        } else {
            return $open;
        }
    }

    /**
     *
     * Hook for comments_array
     * If the user is premium, it will be shown the comments, otherwise no
     *
     * @param $comments
     * @return bool
     */
    function comments_array($comments)
    {
        if ((new TransactApi($this->post_id))->is_premium() == false) {
            return false;
        } else {
            return $comments;
        }
    }

    /**
     * todo: not used yet, future development
     * Get Premium Comment setting for the post
     *
     * @return bool
     */
    function premium_comments_settings()
    {
        $premium_comments = get_post_meta( $this->post_id, 'transact_premium_comments', true );
        return ($premium_comments == 1) ? true : false;
    }
}

