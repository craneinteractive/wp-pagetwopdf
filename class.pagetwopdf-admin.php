<?php

class PageTwoPdfAdminPage
{
    public function __construct()
    {
        add_action('admin_init', array($this, 'setup_sections'));
        add_action('admin_init', array($this, 'setup_fields'));
    }

    public function setup_sections()
    {
        add_settings_section('admin_table', 'Pages', array($this, 'admin_sections'), 'pagetwopdf');
        add_settings_section('admin_fields', 'Edit', array($this, 'admin_sections'), 'pagetwopdf-edit');
    }

    public function admin_menu()
    {
        add_menu_page(
            'Page2Pdf',
            'Page2Pdf',
            'manage_options',
            'pagetwopdf',
            array(&$this, 'admin_page_callback')
        );

        add_submenu_page(
            null,
            'Page2Pdf Edit',
            'Page2Pdf Edit',
            'manage_options',
            'pagetwopdf-edit',
            array(&$this, 'edit_page_callback')
        );     
    }

    public function admin_page_callback()
    {
        if (isset($_GET['id']) && sanitize_key($_GET['action']) == 'delete') {
            $page_id = sanitize_key($_GET['id']);
            $settings = get_option('pagetwopdf_post_settings');
            unset($settings[$page_id]);
            update_option('pagetwopdf_post_settings', $settings);
        }        
        ?>
            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h1>Page2Pdf <a href="<?php echo admin_url() ?>admin.php?page=pagetwopdf-edit" class="page-title-action">Add New</a></h1>
                
                <?php settings_errors(); ?>
                <form method="POST" action="options.php">
                    <?php
                        settings_fields('pagetwopdf');
        do_settings_sections('pagetwopdf');
        submit_button(); ?>
                </form>

            </div>
        <?php
    }

    public function edit_page_callback()
    {   
        ?>
            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h1>Page2Pdf</h1>
                
                <?php settings_errors(); ?>
                <form method="POST" action="options.php">
                    <?php
                        settings_fields('pagetwopdf-edit');
        do_settings_sections('pagetwopdf-edit');
        submit_button(); ?>
                </form>

            </div>
        <?php
    }
    
    public function admin_sections($arguments)
    {
        switch ($arguments['id']) {
            case 'admin_table':
                $adminTable = new PageTwoPdfAdminTable();
                $adminTable->prepare_items();
                $adminTable->display();
                break;
        }
    }

    public function field_callback($arguments)
    {
        $id = null;
        if (isset($_GET['id'])) {
            $id = sanitize_key($_GET['id']);
        }

        $editmode = isset($id) ? true : false;

        $value = get_option($arguments['uid']);
        if (! $value) {
            $value = $arguments['default'];
        }
        
        printf('<input type="hidden" name="editmode" value="%1$s">', $editmode);

        switch ($arguments['type']) {
            case 'text':
                printf('<input name="pagetwopdf_post_settings[%1$s]" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" />', $arguments['uid'], $arguments['type'], $arguments['placeholder'], $value);
                break;
                case 'textarea':
                printf('<textarea name="pagetwopdf_post_settings[%1$s]" id="%1$s" placeholder="%2$s" rows="5" cols="50">%3$s</textarea>', $arguments['uid'], $arguments['placeholder'], $value);
                break;
            case 'select':
                if (! empty($arguments['options']) && is_array($arguments['options'])) {
                    $options_markup = '';
                    foreach ($arguments['options'] as $key => $label) {
                        $options_markup .= sprintf('<option value="%s" %s>%s</option>', $key, selected($value, $key, false), $label);
                    }
                    $enabled = $arguments['enabled'] == false ? 'disabled' : '';
                    printf('<select name="pagetwopdf_post_settings[%1$s]" id="%1$s" %2$s>%3$s</select>', $arguments['uid'], $enabled, $options_markup);
                    if ($arguments['enabled'] == false) {
                        printf('<input type="hidden" name="pagetwopdf_post_settings[%1$s]" value="%2$s"/>', $arguments['uid'], $value);
                    }
                }
                break;
        }
    
        if ($helper = $arguments['helper']) {
            printf('<span class="helper"> %s</span>', $helper);
        }
    
        if ($supplimental = $arguments['supplemental']) {
            printf('<p class="description">%s</p>', $supplimental);
        }
    }

    public function setup_fields()
    {
        $pages = get_posts(array('post_type' => 'any'));
        $settings = array();
        $editmode = false;

        if (isset($_GET['id'])) {
            $page_id = sanitize_key($_GET['id']);
            $settings = $this->_get_options_for_page_id($page_id);
            $editmode = true;
        }
   
        $options = array();
        foreach ($pages as $page) {
            $options[$page->ID] = $page->post_title;
        }

        $fields = array(
            array(
                'uid' => 'page_id',
                'label' => 'Page Title',
                'section' => 'admin_fields',
                'type' => 'select',
                'options' => $options,
                'placeholder' => 'Page Title',
                'helper' => 'Select a post or page',
                'supplemental' => '',
                'default' => count($settings) > 0 ? $settings['page_id'] : '',
                'enabled' => !$editmode,
            ),
            array(
                'uid' => 'excluded_section',
                'label' => 'Excluded IDs',
                'section' => 'admin_fields',
                'type' => 'text',
                'options' => false,
                'placeholder' => 'Excluded IDs',
                'helper' => 'Enter HTML IDs to exclude from the ID. Separate with commas.',
                'supplemental' => 'Separate with commas.',
                'default' => count($settings) > 0 ? $settings['excluded_section'] : '',
            ),
        );
        foreach ($fields as $field) {
            add_settings_field($field['uid'], $field['label'], array( $this, 'field_callback' ), 'pagetwopdf-edit', $field['section'], $field);
        }

        register_setting('pagetwopdf-edit', 'pagetwopdf_post_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'pagetwopdf_post_settings_validation_callback'),
        ));
    }

    public function pagetwopdf_post_settings_validation_callback($input)
    {
        if (!isset($input['page_id']) || empty($input)) {
            return $input;
        }

        // TODO: Validate ID?
        $page_id = sanitize_key($input['page_id']);
        $pages = get_option('pagetwopdf_post_settings');

        if ($pages == false) {
            $pages = array();
            add_option('pagetwopdf_post_settings', $pages);
        }

        if ($pages == false) {
            $pages = array();
        }

        $editmode = isset($_POST['editmode']) ? $_POST['editmode'] : false;

        if ($editmode == false && !isset($pages[$page_id]['generated_shortcode'])) {
            $input['generated_shortcode'] = $this->_generate_shortcode();
        }
        
        if ($editmode == true && isset($pages[$page_id]['generated_shortcode'])) {
            $input['generated_shortcode'] = $pages[$page_id]['generated_shortcode'];
        }

        $excludes_arr  = explode(',', sanitize_text_field(str_replace(' ', '', $input['excluded_section'])));
        $excludes = '';
        $i = 0;
        foreach ($excludes_arr as $ex) {
            $excludes .= sanitize_html_class($ex);
            $i++;

            if (count($excludes_arr) != $i) {
                $excludes .= ',';
            }
        }
        $input['excluded_section'] = trim($excludes, ',');

        $pages[$page_id] = $input;

        return $pages;
    }

    private function _get_options_for_page_id($id)
    {
        $pages = get_option('pagetwopdf_post_settings');

        return isset($pages[$id]) ? $pages[$id] : array();
    }

    private function _generate_shortcode()
    {
        $pages = get_option('pagetwopdf_post_settings');

        $next_shortcode_number = 1;
        if (is_array($pages)) {
            $last_set = end($pages);
            $last_shortcode = $last_set['generated_shortcode'];
            $matches = array();
            if (preg_match('#(\d+)$#', $last_shortcode, $matches)) {
                $next_shortcode_number = $matches[1] + 1;
            }
        }
        return 'pagetwopdf-'.$next_shortcode_number;
    }
}
