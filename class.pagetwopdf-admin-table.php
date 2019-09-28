<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class PageTwoPdfAdminTable extends WP_List_Table
{
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = $this->table_data();
        usort($data, array(&$this, 'sort_data'));

        $perPage = 10;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page' => $perPage
        ));

        $data = array_slice($data, (($currentPage-1) * $perPage), $perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    public function get_columns()
    {
        $columns = array(
            'page'      => 'Page',
            'excluded'  => 'Excluded Sections',
            'shortcode' => 'Shortcode',
            'delete'    => 'Actions',
        );

        return $columns;
    }

    public function get_hidden_columns()
    {
        return array();
    }

    public function get_sortable_columns()
    {
        return array(
            'page' => array('page', false),
            'excluded' => array('excluded', false),
            'shortcode' => array('shortcode', false)
        );
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'page':
            case 'excluded':
            case 'shortcode':
            case 'delete':
                return $item[$column_name];

            default:
                return print_r($item, true);
        }
    }

    private function sort_data($a, $b)
    {
        $orderby = 'shortcode';
        $order = 'asc';
        
        if (!empty($_GET['orderby'])) {
            $orderby = sanitize_key($_GET['orderby']);
        }

        if (!empty($_GET['order'])) {
            $order = sanitize_key($_GET['order']);
        }

        $result = strcmp($a[$orderby], $b[$orderby]);

        if ($order === 'asc') {
            return $result;
        }

        return -$result;
    }

    private function table_data()
    {
        $pages = get_option('pagetwopdf_post_settings');
        $data = array();

        if (is_array($pages)) {
            foreach ($pages as $page) {
                $link = add_query_arg(
                    array(
                    'page' => 'pagetwopdf-edit',
                    'id' => $page['page_id']
                ),
                    admin_url('admin.php')
                );

                $delete_link = add_query_arg(
                    array(
                    'page' => 'pagetwopdf',
                    'action' => 'delete',
                    'id' => $page['page_id']
                ),
                    admin_url('admin.php')
                );

                $page_title = get_the_title($page['page_id']);

                $data[] = array(
                'page' => '<a href="'.$link.'">'.$page_title.'</a>',
                'excluded' => $page['excluded_section'],
                'shortcode' => $page['generated_shortcode'],
                'delete' => '<a class="button button-secondary" href="'.$delete_link.'">Delete</a>',
            );
            }
        }
        
        return $data;
    }
}
