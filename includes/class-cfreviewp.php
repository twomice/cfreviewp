<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       authoruri
 * @since      1.0.0
 *
 * @package    Cfreviewp
 * @subpackage Cfreviewp/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Cfreviewp
 * @subpackage Cfreviewp/includes
 * @author     Allen Shaw, Joinery <allen@joineryhq.com>
 */
class Cfreviewp {

  public static $REVIEW_STATUS_UNREVIEWED = 'UNREVIEWED';
  public static $REVIEW_STATUS_APPROVED = 'ACCEPTED';
  public static $REVIEW_STATUS_REJECTED = 'REJECTED';

  /**
   * Execute all of the hooks with WordPress.
   *
   * @since    1.0.0
   */
  public function run() {

    add_action('admin_enqueue_scripts', ['Cfreviewp', 'enqueue_scripts']);
    add_action('wp_ajax_cfreviewp_review_entry', ['Cfreviewp', 'action_cfreviewp_review_entry']);

    /**
     * Register processor
     */
    add_filter('caldera_forms_get_form_processors', function( $processors ) {
      $processors['cf_processor_cfreviewp_support_reviews'] = array(
        'name' => 'PILnet Reviews',
        'description' => 'Allow custom PILnet review processing',
        'processor' => ['Cfreviewp', 'cf_processor_cfreviewp_support_reviews'],
        'meta_template' => plugin_dir_path(dirname(__FILE__)) . 'caldera-forms/templates/meta.php',
      );
      return $processors;
    }
    );
  }

  public static function enqueue_scripts($hook) {
    if ($hook == 'toplevel_page_caldera-forms') {
      $forms = [];
      $form_ids = Caldera_Forms_Forms::get_forms();
      foreach ($form_ids as $form_id) {
        $forms[$form_id] = FALSE;
        $form = Caldera_Forms_Forms::get_form($form_id);
        foreach ($form['processors'] as $processor_key => $processor) {
          if ($processor['type'] == 'cf_processor_cfreviewp_support_reviews') {
            $forms[$form_id] = TRUE;
            break;
          }
        }
      }


      wp_register_script("cfreviewp_toplevel_page_caldera-forms", plugins_url('admin/js/toplevel_page_caldera-forms.js', dirname(__FILE__)), array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__)) . 'admin/js/toplevel_page_caldera-forms.js'));
      wp_localize_script('cfreviewp_toplevel_page_caldera-forms', 'cfreviewpAjax', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'forms' => $forms,
        ]
      );
      wp_enqueue_script('jquery');
      wp_enqueue_script('cfreviewp_toplevel_page_caldera-forms');
    }
  }

  /**
   * If validate do processing
   *
   * @see Caldera_Forms_Processor_Interface_Process::process()
   *
   * @param array $config Processor config
   * @param array $form Form config
   * @param string $proccesid Process ID
   *
   * @return array Return meta data to save in entry
   */
  public static function cf_processor_cfreviewp_support_reviews($config, $form, $process_id) {
    return array('Review Status' => self::$REVIEW_STATUS_UNREVIEWED);
  }

  public static function action_cfreviewp_review_entry() {
    if (wp_verify_nonce($_POST['nonce'], 'cfreviewp_review_entry') && current_user_can('edit_others_posts')) {
      if ($_POST['response'] == 1) {
        $review_meta_value = self::$REVIEW_STATUS_APPROVED;
      }
      else {
        $review_meta_value = self::$REVIEW_STATUS_REJECTED;
      }
      $entry_details = Caldera_Forms::get_entry_detail($_POST['entry']);
      $entry_form = Caldera_Forms_Forms::get_form($entry_details['form_id']);
      $processor_id = NULL;
      foreach ($entry_form['processors'] as $processor_key => $processor) {
        if ($processor['type'] == 'cf_processor_cfreviewp_support_reviews') {
          $processor_id = $processor_key;
          break;
        }
      }
      if ($processor_id) {
        $meta = $entry_details['meta']['cf_processor_cfreviewp_support_reviews']['data'][$processor_id]['entry']['Review Status'];
        $meta['meta_value'] = $review_meta_value;
        global $wpdb;
        $replace_count = $wpdb->replace($wpdb->prefix . 'cf_form_entry_meta', $meta);
      }
      $msg = array(
        'review_status' => $review_meta_value,
        'entry_id' => $_POST['entry'],
      );
      wp_send_json($msg);
      die();
    }
    else {
      status_header(403);
      wp_send_json_error();
    }
  }

}
