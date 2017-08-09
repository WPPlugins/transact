<?php
namespace Transact\Admin\Settings\Post;

use Transact\Utils\Settings\cpt\SettingsCpt;
require_once  plugin_dir_path(__FILE__) . '../../utils/transact-settings-cpt.php';

use Transact\Admin\Settings\Shortcode\transactShortcode;
require_once  plugin_dir_path(__FILE__) . 'transact-shortcode.php';

/**
 * Class AdminSettingsPostExtension
 */
class AdminSettingsPostExtension
{
    /**
     * @var Saves the post_id we are handling
     */
    protected $post_id;

    /**
     * Keys for buttons options, by default PURCHASE_AND_SUBSCRIPTION
     */
    const PURCHASE_AND_SUBSCRIPTION = 1;
    const ONLY_PURCHASE = 2;
    const ONLY_SUBSCRIBE = 3;

    /**
     * Text for No redirect (donations)
     */
    const NO_REDIRECT_TEXT = 'No Redirect';
    const NO_REDIRECT_VALUE = '0';


    /**
     * All hooks to dashboard
     */
    public function hookToDashboard()
    {
        add_action( 'add_meta_boxes',     array($this, 'add_transact_metadata_post') );
        add_action( 'save_post',          array($this, 'save_meta_box') );
        add_shortcode( 'transact_button', array($this, 'transact_shortcode') );
    }

    /**
     * If this class is initiated outside dashboard, we set post_id
     * to ble able to consult metadata
     *
     * @param int|null $post_id
     */
    public function __construct($post_id = null)
    {
        if ($post_id) {
            $this->post_id = $post_id;
        }
    }

    /**
     * Including transact.io metabox on post
     */
    public function add_transact_metadata_post()
    {
        $enabled_by_default = array('post', 'page');
        $cpts_to_enable = SettingsCpt::get_cpts_enable_for_transact();
        add_meta_box('transact_metadata', 'transact.io', array($this,'transact_metadata_post_callback'), array_merge($enabled_by_default, $cpts_to_enable), 'advanced');
    }

    /**
     * Hook when saving post, to save/update values
     * @param $post_id
     */
    public function save_meta_box( $post_id )
    {
        /**
         * First, we check if setting have been set in the plugin
         */
        $transact_setting_transient = get_transient(SETTING_VALIDATION_TRANSIENT);
        if (!$transact_setting_transient) {
            return;
        }

        /*
         * We need to verify this came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */
        // Check if our nonce is set.
        if ( ! isset( $_POST['transact_inner_custom_box_nonce'] ) ) {
            return $post_id;
        }

        $nonce = $_POST['transact_inner_custom_box_nonce'];

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'transact_inner_custom_box' ) ) {
            return $post_id;
        }

        /*
         * If this is an autosave, our form has not been submitted,
         * so we don't want to do anything.
         */
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        // Check the user's permissions.
        if ( 'page' == $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return $post_id;
            }
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
            }
        }

        /**
         * Create transact item code first time post is used
         */
        $transact_item_code = get_post_meta( $post_id, 'transact_item_code' );
        if (empty($transact_item_code)) {
            $transact_item_code = md5($post_id . time());
            update_post_meta( $post_id, 'transact_item_code', $transact_item_code );

        }

        /* OK, it's safe for us to save the data now. */

        // Sanitize the user input.
        $mydata[1] = sanitize_text_field( $_POST['transact_price'] );
        // Update the meta field.
        update_post_meta( $post_id, 'transact_price', $mydata[1] );

        /**
         * Set up a stamp to allow other plugins to know when you are premium content
         */
        $transact_stamp = '<div id="transact_premium_content_stamp"></div>';
        $content = htmlspecialchars( $_POST['transact_premium_content'] );
        if (!strpos($content, 'transact_premium_content_stamp')) {
            $content = $transact_stamp . $content;
        }
        update_post_meta( $post_id, 'transact_premium_content', $content );

        $display_button = sanitize_text_field( $_POST['transact_display_button'] );
        update_post_meta( $post_id, 'transact_display_button', $display_button );

        $donations = sanitize_text_field( $_POST['transact_donations'] );
        update_post_meta( $post_id, 'transact_donations', $donations );

        $redirect_after_donation = sanitize_text_field( $_POST['transact_redirect_after_donation'] );
        update_post_meta( $post_id, 'transact_redirect_after_donation', $redirect_after_donation );


        /**
         *
         *  todo: comments premium future development
        $premium_comments = (isset($_POST['transact_premium_comments'])) ? sanitize_text_field( $_POST['transact_premium_comments'] ) : 0;
        update_post_meta( $post_id, 'transact_premium_comments', $premium_comments );
         */
    }


    /**
     * Creating callback to include transact settings on post
     *
     * @param $post
     */
    public function transact_metadata_post_callback($post)
    {
        /**
         * First, we check if setting have been set in the plugin
         */
        $transact_setting_transient = get_transient(SETTING_VALIDATION_TRANSIENT);
        if (!$transact_setting_transient) {
            _e('Please, You need to activate your transact settings properly', 'transact');
            return;
        }

        // Add an nonce field so we can check for it later.
        wp_nonce_field( 'transact_inner_custom_box', 'transact_inner_custom_box_nonce' );

        // Use get_post_meta to retrieve an existing value from the database.
        $value[1] = get_post_meta( $post->ID, 'transact_price', true );
        $value[2] = get_post_meta( $post->ID, 'transact_item_code', true );
        $value[3] = get_post_meta( $post->ID, 'transact_premium_content' , true );
        $value[4] = get_post_meta( $post->ID, 'transact_display_button' , true );
        $value[5] = get_post_meta( $post->ID, 'transact_donations' , true );
        $value[6] = get_post_meta( $post->ID, 'transact_redirect_after_donation' , true );

        /**
         *  todo: comments premium future development
         *
        $value[4] = get_post_meta( $post->ID, 'transact_premium_comments' , true ) ;
        $premium_comment_selected = ($value[4] == 1) ? 'checked' : '';
         **/


        /**
         * Premium Content
         */
        _e('<h3>Premium Content</h3>', 'transact');
        wp_editor( htmlspecialchars_decode($value[3]), 'transact_premium_content');

        /**
         * Rest of the form price
         */
        ?>

        <?php

        /**
         *  * Piece of JS to manage the checkbox
         *  todo: comments premium future development
         *
        <script>
            // Handles checkbox for premium comments
            jQuery( document ).ready(function() {
                jQuery('#transact_premium_comments').click(function(){
                    if( jQuery("#transact_premium_comments").is(':checked')) {
                        jQuery("#transact_premium_comments").val(1);
                    } else {
                        jQuery("#transact_premium_comments").val(0);
                    }
                });
            });
        </script>
        **/
        ?>
        <br xmlns="http://www.w3.org/1999/html"/>
        <label for="transact_price">
            <?php _e( 'Premium Price', 'transact' ); ?>
        </label>
        <input type="number" min="1" max="99999" id="transact_price" name="transact_price" value="<?php echo esc_attr( $value[1] ); ?>" />
        <br/>
        <label for="transact_item_code">
            <?php _e( 'Item Code', 'transact' ); ?>
        </label>
        <input readonly type="text" size="35" id="transact_item_code" name="transact_item_code" value="<?php echo esc_attr( $value[2] ); ?>" />
        <br/>

        <?php
        /**
         * Check if donations is enable by the publisher to show donations options
         */
        $options = get_option('transact-settings');
        $donations_options = isset($options['donations']) ? $options['donations'] : 0;
        if ($donations_options) {
            $donations_selected = (($value[5] == 1) ? 'checked' : '');
            ?>
            <label for="transact_display_button">
                <?php _e( 'Article with Donations', 'transact' ); ?>
            </label>
            <input type="checkbox" id="transact_donations" name="transact_donations" value="1" <?php echo $donations_selected;?>>
            <br/>
            <?php

            /**
             * If Donations are on, client will select which page the user will be redirected after donation (if it is wanted by publisher)
             * We get ALL pages information
             */
            $args = array(
                'sort_order' => 'asc',
                'sort_column' => 'post_title',
                'post_type' => 'page',
                'post_status' => 'publish'
            );
            $pages = get_pages($args);
            ?>
            <label for="transact_redirect_after_donation">
                <?php _e( 'Redirect After Donation', 'transact' ); ?>
            </label>
            <select id="transact_redirect_after_donation" name="transact_redirect_after_donation">
                <option value="<?php echo self::NO_REDIRECT_VALUE;?>">
                    <?php _e(self::NO_REDIRECT_TEXT, 'transact');?>
                </option>
                <?php foreach ($pages as $page): ?>
                    <?php $selected = ($value[6] == $page->ID) ? 'selected' : ''; ?>
                    <option <?php echo $selected; ?> value="<?php echo $page->ID; ?>">
                        <?php echo $page->post_title; ?>
                    </option>
                <?php endforeach;?>
            </select>
            <br/>
            <?php
        }
        ?>

        <?php
        /**
         * Check if subscription is enable by the publisher to show button options
         * And if donation is not selected on transact settings and post, otherwise does not make sense
         * to choose kind of button
         */
        $subscription_options = isset($options['subscription']) ? $options['subscription'] : 0;
        if ($subscription_options && (!($donations_options && $value[5]))) {
            $selected_purchased_and_subscription = ($value[4] == self::PURCHASE_AND_SUBSCRIPTION) ? 'selected' : '';
            $selected_purchased = ($value[4] == self::ONLY_PURCHASE) ? 'selected' : '';
            $selected_subscription = ($value[4] == self::ONLY_SUBSCRIBE) ? 'selected' : '';
            ?>
            <label for="transact_display_button">
                <?php _e( 'Display Button', 'transact' ); ?>
            </label>
            <select id="transact_display_button" name="transact_display_button">
                <option <?php echo $selected_purchased_and_subscription;?> value="<?php echo self::PURCHASE_AND_SUBSCRIPTION;?>">
                    <?php _e('Display Purchase and Subscribe Button', 'transact');?>
                </option>
                <option <?php echo $selected_purchased;?> value="<?php echo self::ONLY_PURCHASE;?>">
                    <?php _e('Display Only Purchase Button', 'transact');?>
                </option>
                <option <?php echo $selected_subscription;?> value="<?php echo self::ONLY_SUBSCRIBE;?>">
                    <?php _e('Display Only Subscribe Button', 'transact');?>
                </option>
            </select>
            <?php
        }
        ?>
        <?php
        /**
         *  todo: comments premium future development
         *
        <label for="transact_premium_comments">
            <?php _e( 'Premium comments', 'transact' ); ?>
        </label>
        <input type="checkbox" id="transact_premium_comments" name="transact_premium_comments" value="<?php echo esc_attr( $value[4] ); ?>" <?php echo $premium_comment_selected; ?>/>
        <br/>
         */
        ?>
        <?php

    }

    /**
     * Get Transact price
     * @return int
     */
    public function get_transact_price()
    {
        return get_post_meta( $this->post_id, 'transact_price', true );
    }

    /**
     * Get Transact item code
     * @return int
     */
    public function get_transact_item_code()
    {
        return get_post_meta( $this->post_id, 'transact_item_code', true );
    }

    /**
     * Creating shortcode to show the button on the editor.
     *
     * @param string $atts coming from shortcode can be "id" and "text"
     * @return string $button button html
     */
    public function transact_shortcode( $atts )
    {
        global $post;
        $shortcode = new transactShortcode($atts, $post->ID);
        return $shortcode->print_shortcode();
    }

}

