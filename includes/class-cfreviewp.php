<?php

/**
 * The core plugin class.
 *
 * This is used to define hooks.
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
   */
  public function run() {

    // Reference https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
    add_action('admin_enqueue_scripts', ['Cfreviewp', 'enqueue_scripts']);
    // Reference https://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
    add_action('wp_ajax_cfreviewp_review_entry', ['Cfreviewp', 'action_cfreviewp_review_entry']);

    // Register our caldera-forms processor
    add_filter('caldera_forms_get_form_processors',
      function($processors) {
        $processors['cf_processor_cfreviewp_support_reviews'] = array(
          'name' => 'PILnet Reviews',
          'description' => 'Allow custom PILnet review processing',
          'processor' => ['Cfreviewp', 'cf_processor_cfreviewp_support_reviews'],
          'meta_template' => plugin_dir_path(dirname(__FILE__)) . 'caldera-forms/templates/meta.php',
        );
        return $processors;
      }
    );

    // Register filter to change required capability for calderaforms.
    // References:
    //   - https://wordpress.org/support/topic/grant-editor-allow-full-access-to-caldera-forms/
    //   - https://calderaforms.com/doc/caldera_forms_manage_cap/
    add_filter('caldera_forms_manage_cap',
      function($cap) {
        return 'admin_caldera_forms';
      }
    );
  }

  /**
   * action handler for admin_enqueue_scripts hook.
   */
  public static function enqueue_scripts($hook) {
    if ($hook == 'toplevel_page_caldera-forms') {

      // Initialize civicrm and include civicrm core js/css resources.
      civicrm_initialize();
      CRM_Core_Resources::singleton()->addCoreResources();

      // Add our own custom js script.
      wp_register_script("cfreviewp_toplevel_page_caldera-forms", plugins_url('admin/js/toplevel_page_caldera-forms.js', dirname(__FILE__)), array('jquery'), filemtime(plugin_dir_path(dirname(__FILE__)) . 'admin/js/toplevel_page_caldera-forms.js'));
      wp_localize_script('cfreviewp_toplevel_page_caldera-forms', 'cfreviewpAjax', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        ]
      );
      wp_enqueue_script('jquery');
      wp_enqueue_script('cfreviewp_toplevel_page_caldera-forms');
    }
  }

  /**
   * Define metadata for each entry on forms handled by this cf processor.
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
    return array(
      'Review Status' => self::$REVIEW_STATUS_UNREVIEWED,
      'Case ID' => 0,
    );
  }

  /**
   * AJAX handler for action wp_ajax_cfreviewp_review_entry
   */
  public static function action_cfreviewp_review_entry() {
    // Verify our nonce is valid, and that user has appropriate cf permissions.
    if (wp_verify_nonce($_POST['nonce'], 'cfreviewp_review_entry') && current_user_can('edit_others_posts')) {
      $entryId = $_POST['entry'];
      // Get the entry details so we can get the related form.
      $entry_details = Caldera_Forms::get_entry_detail($entryId);
      // Get the related form, so we can get the processors for that form.
      $entry_form = Caldera_Forms_Forms::get_form($entry_details['form_id']);
      // Shorthand var for the system ID of our cf processor.
      $processor_id = NULL;
      // Loop through form processors to find ours.
      foreach ($entry_form['processors'] as $processor_key => $processor) {
        if ($processor['type'] == 'cf_processor_cfreviewp_support_reviews') {
          // This is our processor; store its system ID.
          $processor_id = $processor_key;
          break;
        }
      }

      // Only if this entry is for a form that has our processor (and if it doesn't,
      // really, why are we here?
      if ($processor_id) {

      // Define review status based on 'response' variable.
        if ($_POST['response'] == 1) {
          $review_meta_value = self::$REVIEW_STATUS_APPROVED;
        }
        else {
          $review_meta_value = self::$REVIEW_STATUS_REJECTED;
        }

        // Define shorthand vars for meta values on this entry.
        $metaReviewStatus = $entry_details['meta']['cf_processor_cfreviewp_support_reviews']['data'][$processor_id]['entry']['Review Status'];
        $metaCaseId = $entry_details['meta']['cf_processor_cfreviewp_support_reviews']['data'][$processor_id]['entry']['Case ID'];

        // If response is 'rejected', mark status as such.
        if ($review_meta_value == self::$REVIEW_STATUS_REJECTED) {
          $metaReviewStatus['meta_value'] = $review_meta_value;
          global $wpdb;
          $replace_count = $wpdb->replace($wpdb->prefix . 'cf_form_entry_meta', $metaReviewStatus);
        }
        elseif ($review_meta_value == self::$REVIEW_STATUS_APPROVED) {
          // If response is 'approved', process it carefully.
          if ($metaCaseId['meta_value'] == 0) {
            // If there's not already a Case associated with this entry (and if
            // there is, why are we here anyway?), then create one.
            // Get the entry data so we can pass it to the case creator.
            $entry = Caldera_Forms::get_entry($entryId, $entry_details['form_id']);
            // Pass relevant data to case creator, and get new case ID.
            $case_id = self::processApprovedEntry($entryId, $entry['data'], $entry_form['fields']);
          }
          else {
            // If somehow we're marking 'approved' an entry  which already has an
            // associated case (and really, that's not  a standard workflow; not
            // sure how we would be here), then just use the existing case ID.
            $case_id = $metaCaseId['meta_value'];
          }
          if ($case_id) {
            // If we have a valid case ID, update status and case ID meta values.
            $metaReviewStatus['meta_value'] = $review_meta_value;
            global $wpdb;
            $replace_count = $wpdb->replace($wpdb->prefix . 'cf_form_entry_meta', $metaReviewStatus);

            $metaCaseId['meta_value'] = $case_id;
            global $wpdb;
            $replace_count = $wpdb->replace($wpdb->prefix . 'cf_form_entry_meta', $metaCaseId);
          }
        }
        // Return a reasonable array of data for this entry.
        $msg = array(
          'review_status' => $metaReviewStatus['meta_value'],
          'entry_id' => $entryId,
          'case_id' => $metaCaseId['meta_value'],
        );
        wp_send_json($msg);
        // Die; we don't need to do anything else here.
        die();
      }
    }
    // If we're still here, this is either an unauthorized request or a non-standard
    // workflow. Send a 403. We can add more nuanced error handling here later if needed.
    status_header(403);
    wp_send_json_error();
  }



  /**
   * Create a case based on the given data from a cf entry.
   * @param Integer $entryId Entry ID; not really used here.
   * @param Array $entryData Array of all values from the entry, keyed to field IDs.
   * @param Array $formFields Array of form fields; useful so we can reference fields by
   *    slugs instead of field IDs.
   * @return Integer The created Case ID.
   */
  public static function processApprovedEntry($entryId, $entryData, $formFields) {
    // Build an array of entry values keyed to (more easily human-readable) field
    // slugs instead of field IDs.
    $slugValues = [];
    $supportedSlugs = [
      'name_of_the_organization',
      'email',
      'please_provide_in_no_more_than_5_lines_the_mission_goals_and_activities_of_your_organization',
      'first_name',
      'last_name',
      'position',
      'phone',
      'skype_or_other_online_communications_platform_id',
      'website',
      'city',
      'country',
      'zip__postal_code',
      'address_line_1',
      'address_line_2',
      'stateprovince',
      'please_provide_a_brief_background_of_the_project',
      'by_what_date_should_the_pro_bono_work_be_completed',
    ];
    foreach ($formFields as $formFieldId => $formField) {
      $slug = $formField['slug'];
      if (!in_array($slug, $supportedSlugs)) {
        // This is not a field we need to handle, so just skip it.
        continue;
      }
      // Pass each value through html_entity_decode() because they will be entity_encoded
      // and we don't want to store them that way in civicrm.
      $slugValues[$slug] = html_entity_decode($entryData[$formFieldId]['value']);
    }
    // Initialize civicrm so we can use apis
    civicrm_initialize();
    //  Create/update  the organization
    $orgDuplicate = civicrm_api3('Contact', 'duplicatecheck', [
      'match' => [
        'contact_type' => 'Organization',
        'organization_name' => $slugValues['name_of_the_organization'],
        'email' => $slugValues['email'],
      ],
      'sequential' => 1,
      ]
    );
    $orgContact = civicrm_api3('Contact', 'create', [
      'id' => CRM_Utils_Array::value('id', $orgDuplicate),
      'contact_type' => 'Organization',
      'organization_name' => $slugValues['name_of_the_organization'],
      'email' => $slugValues['email'],
      'custom_3' => $slugValues['please_provide_in_no_more_than_5_lines_the_mission_goals_and_activities_of_your_organization'],
      ]
    );

    //  Create/update  the individual
    $indivDuplicate = civicrm_api3('Contact', 'duplicatecheck', [
      'match' => [
        'contact_type' => 'Individual',
        'first_name' => $slugValues['first_name'],
        'last_name' => $slugValues['last_name'],
        'email' => $slugValues['email'],
      ],
      'sequential' => 1,
      ]
    );
    $indivContact = civicrm_api3('Contact', 'create', [
      'id' => CRM_Utils_Array::value('id', $indivDuplicate),
      'contact_type' => 'Individual',
      'first_name' => $slugValues['first_name'],
      'last_name' => $slugValues['last_name'],
      'email' => $slugValues['email'],
      'job_title' => $slugValues['position'],
      ]
    );

    try {
      // Create employee relationship between org and individual
      $relationship = civicrm_api3('Relationship', 'create', [
        'relationship_type_id' => 4,
        'contact_id_a' => $indivContact['id'],
        'contact_id_b' => $orgContact['id'],
        ]
      );
    }
    catch (CiviCRM_API3_Exception $e) {
      // do nothing; most likely we've just tried to create a duplicate relationship,
      // which generates an error but refuses to complete (which is what we want)
      // so nothing to worry about.
    }

    // create phone for indiv
    if ($slugValues['phone']) {
      civicrm_api3('phone', 'create', [
        'contact_id' => $indivContact['id'],
        'phone' => $slugValues['phone'],
        'is_primary' => 1,
        'location_type_id' => 2,
        ]
      );
    }

    // create im for indiv
    if ($slugValues['skype_or_other_online_communications_platform_id']) {
      civicrm_api3('im', 'create', [
        'contact_id' => $indivContact['id'],
        'name' => $slugValues['skype_or_other_online_communications_platform_id'],
        'is_primary' => 1,
        'location_type_id' => 2,
        'provider_id' => 6,
        ]
      );
    }

    // create website for org
    if ($slugValues['website']) {
      civicrm_api3('website', 'create', [
        'contact_id' => $orgContact['id'],
        'url' => $slugValues['website'],
        'is_primary' => 1,
        'website_type_id' => 2,
        ]
      );
    }

    // create address  for org
    if (
      $slugValues['city'] || $slugValues['country'] || $slugValues['zip__postal_code'] || $slugValues['address_line_1'] || $slugValues['address_line_2'] || $slugValues['stateprovince']
    ) {
      $params = [
        'contact_id' => $orgContact['id'],
        'is_primary' => 1,
        'location_type_id' => 2,
        'city' => $slugValues['city'],
        'country' => self::rectifyCivicrmCountryName($slugValues['country']),
        'postal_code' => $slugValues['zip__postal_code'],
        'street_address' => $slugValues['address_line_1'],
        'supplemental_address_1' => $slugValues['address_line_2'],
        'supplemental_address_2' => $slugValues['stateprovince'],
      ];
      try {
        civicrm_api3('address', 'create', $params);
      }
      catch (CiviCRM_API3_Exception $e) {
        \Civi::log()->error('cfreviewp: error calling civicrm api address.create: '. $e->getMessage(), $params);
        throw $e;
      }
    }

    // create the case
    $caseParams = [
      'contact_id' => $orgContact['id'],
      'case_type_id' => 5,
      'subject' => 'Online request from ' . $slugValues['name_of_the_organization'],
      // Case summary:
      'custom_34' => $slugValues['please_provide_a_brief_background_of_the_project'],
      // Deadline:
      'custom_36' => $slugValues['by_what_date_should_the_pro_bono_work_be_completed'],
      // Redundant in civicase: 'ngo_description' => $slugValues['please_provide_in_no_more_than_5_lines_the_mission_goals_and_activities_of_your_organization'],
    ];
    $caseCreateResult = civicrm_api3('case', 'create', $caseParams);
    return $caseCreateResult['id'];
  }

  /**
   * Rectify a given cf country name to its name in civicrm, if required and if possible
   * @param String $name CF country name.
   * @return String The correct country name in CiviCRM.
   */
  public static function rectifyCivicrmCountryName($name) {
    $correctedNames = [
      'Bolivia, Plurinational State of' => 'Bolivia',
      'Congo' => 'Congo, The Democratic Republic of the',
      'Côte d\'Ivoire' => 'Côte d’Ivoire',
      'Macedonia, the former Yugoslav Republic of' => 'Macedonia, Republic of',
      'Moldova, Republic of' => 'Moldova',
      'Palestinian Territory, Occupied' => 'Palestinian Territories',
      'Russian Federation' => 'Russia',
      'Saint Helena, Ascension and Tristan da Cunha' => 'Saint Helena',
      'Swaziland' => 'Eswatini',
      'Taiwan, Province of China' => 'Taiwan',
      'United States' => 'USA',
      'Venezuela, Bolivarian Republic of' => 'Venezuela',
    ];
    return CRM_Utils_Array::value($name, $correctedNames, $name);
  }

}
