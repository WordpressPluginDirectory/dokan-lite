<?php

namespace WeDevs\Dokan\Emails;

use WC_Email;

/**
 * New Product Email.
 *
 * An email sent to the admin when a new Product is created by vendor.
 *
 * @class       Dokan_Email_New_Product_Pending
 * @version     2.6.8
 * @package     Dokan/Classes/Emails
 * @author      weDevs
 * @extends     WC_Email
 */
class NewProductPending extends WC_Email {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id               = 'new_product_pending';
        $this->title            = __( 'Dokan New Pending Product', 'dokan-lite' );
        $this->description      = __( 'New Pending Product emails are sent to chosen recipient(s) when a new product is created by vendors.', 'dokan-lite' );
        $this->template_html    = 'emails/new-product-pending.php';
        $this->template_plain   = 'emails/plain/new-product-pending.php';
        $this->template_base    = DOKAN_DIR . '/templates/';

        // Triggers for this email
        add_action( 'dokan_new_product_added', array( $this, 'trigger' ), 30, 1 );
        add_action( 'dokan_product_updated', array( $this, 'trigger' ), 30, 1 );

        // Call parent constructor
        parent::__construct();

        // Other settings
        $this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
    }

    /**
     * Get email subject.
     *
     * @since  3.1.0
     * @return string
     */
    public function get_default_subject() {
            return __( '[{site_title}] A New product is pending from ({seller_name}) - {product_title}', 'dokan-lite' );
    }

    /**
     * Get email heading.
     *
     * @since  3.1.0
     * @return string
     */
    public function get_default_heading() {
            return __( 'New pending product added by Vendor {seller_name}', 'dokan-lite' );
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int $product_id The product ID.
     */
    public function trigger( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        // we've added _dokan_new_product_email_sent from version 3.8.2
        // so, we are assuming if the meta doesn't exist, email was already sent to the client
        $email_sent = $product->get_meta( '_dokan_new_product_email_sent' );
        if ( empty( $email_sent ) || true === wc_string_to_bool( $email_sent ) ) {
            return;
        }

        if ( 'pending' !== $product->get_status() ) {
            return;
        }

        $product->update_meta_data( '_dokan_new_product_email_sent', 'yes' );
        $product->save();

        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            return;
        }

        $seller_id     = get_post_field( 'post_author', $product_id );
        $seller        = get_user_by( 'id', $seller_id );
        $category      = wp_get_post_terms( dokan_get_prop( $product, 'id' ), 'product_cat', array( 'fields' => 'names' ) );
        $category_name = $category ? reset( $category ) : 'N/A';

        if ( is_a( $product, 'WC_Product' ) ) {
            $this->object = $product;

            $this->find['product-title'] = '{product_title}';
            $this->find['price']         = '{price}';
            $this->find['seller-name']   = '{seller_name}';
            $this->find['seller_url']    = '{seller_url}';
            $this->find['category']      = '{category}';
            $this->find['product_link']  = '{product_link}';
            $this->find['site_name']     = '{site_name}';
            $this->find['site_url']      = '{site_url}';

            $this->replace['product-title'] = $product->get_title();
            $this->replace['price']         = $product->get_price();
            $this->replace['seller-name']   = $seller->display_name;
            $this->replace['seller_url']    = dokan_get_store_url( $seller->ID );
            $this->replace['category']      = $category_name;
            $this->replace['product_link']  = admin_url( 'post.php?action=edit&post=' . $product_id );
            $this->replace['site_name']     = $this->get_from_name();
            $this->replace['site_url']      = site_url();
        }

        $this->setup_locale();
        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        $this->restore_locale();
    }

        /**
     * Get content html.
     *
     * @access public
     * @return string
     */
    public function get_content_html() {
            ob_start();
                wc_get_template(
                    $this->template_html, array(
						'product'       => $this->object,
						'email_heading' => $this->get_heading(),
						'sent_to_admin' => true,
						'plain_text'    => false,
						'email'         => $this,
						'data'          => $this->replace,
                    ), 'dokan/', $this->template_base
                );
            return ob_get_clean();
    }

    /**
     * Get content plain.
     *
     * @access public
     * @return string
     */
    public function get_content_plain() {
            ob_start();
                wc_get_template(
                    $this->template_html, array(
						'product'       => $this->object,
						'email_heading' => $this->get_heading(),
						'sent_to_admin' => true,
						'plain_text'    => true,
						'email'         => $this,
						'data'          => $this->replace,
                    ), 'dokan/', $this->template_base
                );
            return ob_get_clean();
    }

    /**
     * Initialise settings form fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'         => __( 'Enable/Disable', 'dokan-lite' ),
                'type'          => 'checkbox',
                'label'         => __( 'Enable this email notification', 'dokan-lite' ),
                'default'       => 'yes',
            ),
            'recipient' => array(
                'title'         => __( 'Recipient(s)', 'dokan-lite' ),
                'type'          => 'text',
                // translators: 1) admin email address
                'description'   => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %1$s.', 'dokan-lite' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
                'placeholder'   => '',
                'default'       => '',
                'desc_tip'      => true,
            ),
            'subject' => array(
                'title'         => __( 'Subject', 'dokan-lite' ),
                'type'          => 'text',
                'desc_tip'      => true,
                /* translators: %s: list of placeholders */
                'description'   => sprintf( __( 'Available placeholders: %s', 'dokan-lite' ), '<code>{site_title}, {product_title}, {seller_name}</code>' ),
                'placeholder'   => $this->get_default_subject(),
                'default'       => '',
            ),
            'heading' => array(
                'title'         => __( 'Email heading', 'dokan-lite' ),
                'type'          => 'text',
                'desc_tip'      => true,
                /* translators: %s: list of placeholders */
                'description'   => sprintf( __( 'Available placeholders: %s', 'dokan-lite' ), '<code>{site_title}, {product_title}, {seller_name}</code>' ),
                'placeholder'   => $this->get_default_heading(),
                'default'       => '',
            ),
            'email_type' => array(
                'title'         => __( 'Email type', 'dokan-lite' ),
                'type'          => 'select',
                'description'   => __( 'Choose which format of email to send.', 'dokan-lite' ),
                'default'       => 'html',
                'class'         => 'email_type wc-enhanced-select',
                'options'       => $this->get_email_type_options(),
                'desc_tip'      => true,
            ),
        );
    }
}
