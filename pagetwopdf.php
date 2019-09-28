<?php
/**
 * Plugin Name: Page2Pdf
 * Description: Generate buttons that snapshot the current page to a PDF document. Allows for excluding sections of the page.
 * Version: 1.0
 * Author: Crane Interactive Ltd
 * Author URI: https://www.craneinteractive.co.uk
 * License: GPLv2 or later
 */
use Dompdf\Dompdf;

define('PAGETWOPDF_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once PAGETWOPDF_PLUGIN_DIR . 'class.pagetwopdf-admin.php';
require_once PAGETWOPDF_PLUGIN_DIR . 'class.pagetwopdf-admin-table.php';
require_once PAGETWOPDF_PLUGIN_DIR . 'libs/dompdf/autoload.inc.php';

class PageTwoPdf
{
    public function __construct()
    {
        add_action('admin_menu', array(new PageTwoPdfAdminPage, 'admin_menu'));
        add_action('wp_ajax_nopriv_convert_html', array($this, 'convert_html'));
        add_action('admin_post_pagetwopdf_topdf', array($this, 'topdf'));
        add_action('admin_post_nopriv_pagetwopdf_topdf', array($this, 'topdf'));

        $pages = get_option('pagetwopdf_post_settings');

        if (is_array($pages)) {
            foreach ($pages as $page) {
                add_shortcode($page['generated_shortcode'], array('PageTwoPdf', 'shortcode_callback'));
            }
        }
    }

    public static function shortcode_callback($atts, $content, $tag)
    {
        $admin_url = admin_url('admin-post.php');
        $queried_object_id = get_queried_object_id();
        $nonce = wp_nonce_field('pagetwopdf_topdf', 'wp_nonce', true, false);

        if (!is_admin()) {
            return sprintf('<form id="pagetwopdf_plugin" action="%1$s" method="POST">
                <input type="hidden" name="action" value="pagetwopdf_topdf">
                <input type="hidden" name="page_id" value="%2$s">
                %3$s
                <input type="submit" value="Print to PDF">
            </form>', $admin_url, $queried_object_id, $nonce);
        }

        return null;
    }

    public function topdf()
    {
        if (!isset($_POST['wp_nonce']) || !wp_verify_nonce($_POST['wp_nonce'], 'pagetwopdf_topdf')) {
            print 'Unverified call';
            exit();
        }

        if (isset($_POST['page_id'])) {
            $page_id = sanitize_key($_POST['page_id']);

            $response = wp_remote_get(get_post_permalink($page_id));
            $body = $response['body'];

            $body = $this->remove_tag('pagetwopdf_plugin', $body);

            $pages = get_option('pagetwopdf_post_settings');
            $settings_excluded_tags = $pages[$page_id]['excluded_section'];
            $excluded_tags = str_getcsv($settings_excluded_tags);

            foreach ($excluded_tags as $tag) {
                $body = $this->remove_tag($tag, $body);
            }

            try {
                $dompdf = new Dompdf();
                $dompdf->loadHtml($body);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->set_option('isHtml5ParserEnabled', true);
                $dompdf->set_option('isJavascriptEnabled', true);
                $dompdf->render();
                $dompdf->stream();
            } catch (DOMPDF_Exception $e) {
                echo '<pre>', print_r($e), '</pre>';
            }
        }
    }

    private function remove_tag($id, $html)
    {
        $dom = new DOMDocument;
        $dom->validateOnParse = false;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);
        $xp = new DOMXPath($dom);

        $col = $xp->query('//*[ @id="' . $id . '" ]');
        if (!empty($col)) {
            foreach ($col as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        $col = $xp->query('//*[ contains(attribute::class, "' . $id . '") ]');
        if (!empty($col)) {
            foreach ($col as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        return $dom->saveHTML();
    }
}

global $pageTwoPdf;

$pageTwoPdf = new PageTwoPdf();
