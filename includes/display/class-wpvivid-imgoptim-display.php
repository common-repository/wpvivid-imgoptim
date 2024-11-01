<?php

if (!defined('WPVIVID_IMGOPTIM_DIR'))
{
    die;
}

if ( ! class_exists( 'WP_List_Table' ) )
{
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPvivid_Optimized_Image_List extends WP_List_Table
{
    public $list;
    public $type;
    public $page_num;
    public $parent;

    public function __construct( $args = array() )
    {
        parent::__construct(
            array(
                'plural' => 'upload_files',
                'screen' => 'upload_files',
            )
        );
    }

    public function set_list($list,$page_num=1)
    {
        $this->list=$list;
        $this->page_num=$page_num;
    }

    protected function get_table_classes()
    {
        return array( 'widefat  media striped' );
    }

    public function print_column_headers( $with_id = true )
    {
        list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

        if (!empty($columns['cb']))
        {
            static $cb_counter = 1;
            $columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __('Select All') . '</label>'
                . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox"/>';
            $cb_counter++;
        }

        foreach ( $columns as $column_key => $column_display_name )
        {

            $class = array( 'manage-column', "column-$column_key" );

            if ( in_array( $column_key, $hidden ) )
            {
                $class[] = 'hidden';
            }


            if ( $column_key === $primary )
            {
                $class[] = 'column-primary';
            }

            if ( $column_key === 'cb' )
            {
                $class[] = 'check-column';
            }
            $tag='th';
            $tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
            $scope = ( 'th' === $tag ) ? 'scope="col"' : '';
            $id    = $with_id ? "id='$column_key'" : '';

            if ( ! empty( $class ) )
            {
                $class = "class='" . join( ' ', $class ) . "'";
            }

            $html= '<'.$tag.' '.$scope.' '.$id.' '.$class.'>';
            $html_end='</'.$tag.'>';

            echo wp_kses_post($html);
            if ( $column_key === 'cb' )
            {
               echo '<label class="screen-reader-text" for="cb-select-all-1">' . esc_html__('Select All', 'wpvivid-imgoptim') . '</label>';
               echo '<input id="cb-select-all-1" type="checkbox"/>';
            }
            else
            {
                echo wp_kses_post($column_display_name);
            }

            echo wp_kses_post($html_end);
        }
    }

    public function get_columns()
    {
        $sites_columns = array(
            'cb'           => ' ',
            'title'        => __( 'Images (Media Library)' ),
            'status'       => __('Status'),
            'optimization' => __('Result (including thumbnails)')
        );

        return $sites_columns;
    }

    public function get_pagenum()
    {
        if($this->page_num=='first')
        {
            $this->page_num=1;
        }
        else if($this->page_num=='last')
        {
            $this->page_num=$this->_pagination_args['total_pages'];
        }
        $pagenum = $this->page_num ? $this->page_num : 0;

        if ( isset( $this->_pagination_args['total_pages'] ) && $pagenum > $this->_pagination_args['total_pages'] )
        {
            $pagenum = $this->_pagination_args['total_pages'];
        }

        return max( 1, $pagenum );
    }

    public function column_cb( $item )
    {
        echo '<input type="checkbox" name="opt" value="'.esc_attr($item['id']).'" />';
    }

    public function column_title($item)
    {
        $thumb      = wp_get_attachment_image( $item['id'], array( 60, 60 ), true, array( 'alt' => '' ) );
        $title      = _draft_or_post_title($item['id']);

        $post=get_post($item['id']);

        list( $mime ) = explode( '/', $post->post_mime_type );

        $link_start = $link_end = '';

        if ( current_user_can( 'edit_post', $post->ID ) )
        {
            $link_start = sprintf(
                '<a href="%s" aria-label="%s">',
                get_edit_post_link( $post->ID ),
                /* translators: %s: attachment title */
                esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $title ) )
            );
            $link_end = '</a>';
        }

        $class = $thumb ? ' class="has-media-icon"' : '';
        ?>
        <strong<?php echo esc_attr($class); ?>>
            <?php
            echo wp_kses_post($link_start);
            if ( $thumb ) :
                ?>
                <span class="media-icon <?php echo sanitize_html_class( $mime . '-icon' ); ?>"><?php echo wp_kses_post($thumb); ?></span>
            <?php
            endif;
            echo wp_kses_post($title) . wp_kses_post($link_end);
            _media_states( $post );
            ?>
        </strong>
        <p class="filename">
            <span class="screen-reader-text"><?php esc_html_e( 'File name:' ); ?> </span>
            <?php
            $file = get_attached_file( $post->ID );
            echo esc_html( wp_basename( $file ) );
            ?>
        </p>
        <?php
    }

    public function column_status($item)
    {
        $status=true;

        $options=get_option('wpvivid_optimization_options',array());

        foreach ($item['size'] as $size_key=>$size)
        {
            if(isset($options['skip_size'])&&isset($options['skip_size'][$size_key]))
            {
                if($options['skip_size'][$size_key])
                    continue;
            }

            if($size['opt_status']==1)
            {
                $status=true;
            }
            else
            {
                $status=false;
            }
        }

        if($status)
        {
            $status=__('Complete');
        }
        else
        {
            $status=__('Unfinished');
        }

        echo esc_html($status);
    }

    public function column_optimization($item)
    {
        $allowed_mime_types = array(
            'image/jpg',
            'image/jpeg',
            'image/png');

        if ( ! wp_attachment_is_image( $item['id'] ) || ! in_array( get_post_mime_type( $item['id'] ),$allowed_mime_types ) )
        {
            return 'Not support';
        }

        $meta=get_post_meta( $item['id'],'wpvivid_image_optimize_meta', true );

        $html='<div class="wpvivid-media-item" data-id="'.$item['id'].'">';
        $task=new WPvivid_ImgOptim_Task();
        $options=get_option('wpvivid_optimization_options',array());

        $status=true;
        foreach ($item['size'] as $size_key=>$size)
        {
            if(isset($options['skip_size'])&&isset($options['skip_size'][$size_key]))
            {
                if($options['skip_size'][$size_key])
                    continue;
            }
            if($size['opt_status']==1)
            {
                $status=true;
            }
            else
            {
                $status=false;
            }
        }

        if($status)
        {
            if($meta['sum']['og_size']==0)
            {
                $percent=0;
            }
            else
            {
                $percent=round(100-($meta['sum']['opt_size']/$meta['sum']['og_size'])*100,2);
            }
            $html.='<ul>';
            $html.= '<li><span>'.__('Optimized size','wpvivid-imgoptim').' : </span><strong>'.size_format($meta['sum']['opt_size'],2).'</strong></li>';
            $html.= '<li><span>'.__('Saved','wpvivid-imgoptim').' : </span><strong>'.$percent.'%</strong></li>';
            $html.= '<li><span>'.__('Original size','wpvivid-imgoptim').' : </span><strong>'.size_format($meta['sum']['og_size'],2).'</strong></li>';
            $html.='</ul>';
        }
        else
        {

        }

        $html.='</div>';

        return $html;
    }

    public function has_items()
    {
        return !empty($this->list);
    }

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $total_items =sizeof($this->list);

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => 20,
            )
        );
    }

    public function display_rows()
    {
        $this->_display_rows( $this->list );
    }

    private function _display_rows( $list )
    {
        $page=$this->get_pagenum();

        $page_list=$list;
        $temp_page_list=array();

        $count=0;
        while ( $count<$page )
        {
            $temp_page_list = array_splice( $page_list, 0, 20);
            $count++;
        }

        foreach ( $temp_page_list as $key=>$item)
        {
            $this->single_row($item);
        }
    }

    public function single_row($item)
    {
        ?>
        <tr>
            <?php $this->single_row_columns( $item ); ?>
        </tr>
        <?php
    }

    protected function display_tablenav( $which ) {
        $css_type = '';
        if ( 'top' === $which ) {
            wp_nonce_field( 'bulk-' . $this->_args['plural'] );
            $css_type = 'margin: 0 0 10px 0';
            $id='wpvivid_image_opt_bulk_top_action';
            $class='top-action';
        }
        else if( 'bottom' === $which )
        {
            $css_type = 'margin: 10px 0 0 0';
            $id='wpvivid_image_opt_bulk_bottom_action';
            $class='bottom-action';
        }
        else
        {
            $id='';
            $class='';
        }

        $total_pages     = $this->_pagination_args['total_pages'];
        if ( $total_pages >1)
        {
            ?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>" style="<?php echo esc_attr($css_type); ?>">
                <div class="alignleft actions bulkactions">
                    <label for="wpvivid_uc_bulk_action" class="screen-reader-text"><?php esc_html_e('Select bulk action','wpvivid-imgoptim')?></label>
                    <select name="action" id="<?php echo esc_attr( $id ); ?>">
                        <option value="-1"><?php esc_html_e('Bulk Actions','wpvivid-imgoptim')?></option>
                        <option value="wpvivid_restore_selected_image"><?php esc_html_e('Restore selected images','wpvivid-imgoptim')?></option>
                        <option value="wpvivid_restore_all_image"><?php esc_html_e('Restore all images','wpvivid-imgoptim')?></option>
                    </select>
                    <input type="submit" class="button action <?php echo esc_attr( $class ); ?>" value="<?php esc_html_e('Apply','wpvivid-imgoptim')?>">
                </div>
                <?php
                $this->extra_tablenav( $which );
                $this->pagination( $which );
                ?>
                <br class="clear" />
            </div>
            <?php
        }
        else
        {
            ?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>" style="<?php echo esc_attr($css_type); ?>">
                <div class="alignleft actions bulkactions">
                    <label for="wpvivid_uc_bulk_action" class="screen-reader-text"><?php esc_html_e('Select bulk action','wpvivid-imgoptim')?></label>
                    <select name="action" id="<?php echo esc_attr( $id ); ?>">
                        <option value="-1"><?php esc_html_e('Bulk Actions','wpvivid-imgoptim')?></option>
                        <option value="wpvivid_restore_selected_image"><?php esc_html_e('Restore selected images','wpvivid-imgoptim')?></option>
                        <option value="wpvivid_restore_all_image"><?php esc_html_e('Restore all images','wpvivid-imgoptim')?></option>
                    </select>
                    <input type="submit" class="button action <?php echo esc_attr( $class ); ?>" value="<?php esc_html_e('Apply','wpvivid-imgoptim')?>">
                </div>

                <br class="clear" />
            </div>
            <?php
        }
    }
}

class WPvivid_ImgOptim_Display
{
     public $main_tab;
     public $screen_ids;
     public $submenus;

    public function __construct()
    {
        add_filter('wpvivid_imgoptim_get_screen_ids',array($this,'get_screen_ids'),10);

        add_action('admin_enqueue_scripts',array( $this,'enqueue_styles'));
        add_action('admin_enqueue_scripts',array( $this,'enqueue_scripts'));

        if(is_multisite())
        {
            add_action('network_admin_menu',array( $this,'add_plugin_admin_menu'));
        }
        else
        {
            add_action('admin_menu',array( $this,'add_plugin_admin_menu'));
        }

        add_filter('wpvivid_imgoptim_get_admin_menus',array($this,'get_admin_menus'),25);
        $this->load_ajax_hook();
    }

    public function load_ajax_hook()
    {
        add_action('wp_ajax_wpvivid_get_opt_progress',array($this, 'get_opt_progress'));
        add_action('wp_ajax_wpvivid_get_server_status',array($this, 'get_server_status'));
        add_action('wp_ajax_wpvivid_view_optimize_log_ex', array($this, 'view_log_ex'));
        add_action('wp_ajax_wpvivid_empty_optimize_log', array($this, 'empty_optimize_log'));
        add_action('wp_ajax_wpvivid_open_progressing_optimize_log', array($this, 'open_progressing_optimize_log'));
        //
        add_action('wp_ajax_wpvivid_get_opt_list',array($this, 'get_opt_list'));

        add_action('wp_ajax_wpvivid_init_opt_task', array($this, 'init_opt_task'));
        add_action('wp_ajax_wpvivid_start_opt_task',array($this, 'start_opt_task'));
        add_action('wp_ajax_wpvivid_cancel_opt_task',array($this,'cancel_opt_task'));
        add_action('wp_ajax_wpvivid_opt_image',array($this, 'opt_image'));
        add_action('wp_ajax_wpvivid_restore_selected_opt_image',array($this, 'restore_image'));
        add_action('wp_ajax_wpvivid_restore_all_opt_image',array($this, 'restore_all_image'));

        add_action('wp_ajax_wpvivid_start_get_overview',array($this, 'start_get_overview'));
        add_action('wp_ajax_wpvivid_get_overview',array($this, 'get_overview'));
        //
    }

    public function get_screen_ids()
    {
        $screen_ids[]='toplevel_page_'.WPVIVID_IMGOPTIM_SLUG;
        return $screen_ids;
    }

    public function enqueue_styles()
    {
        $this->screen_ids=apply_filters('wpvivid_imgoptim_get_screen_ids',array());

        if(in_array(get_current_screen()->id,$this->screen_ids))
        {
            wp_enqueue_style(WPVIVID_IMGOPTIM_SLUG, WPVIVID_IMGOPTIM_URL . '/includes/display/css/wpvivid-backup-custom.css', array(), WPVIVID_IMGOPTIM_VERSION, 'all');
            wp_enqueue_style(WPVIVID_IMGOPTIM_SLUG.'_Optimize_Display', WPVIVID_IMGOPTIM_URL . '/includes/display/css/wpvividdashboard-style.css', array(), WPVIVID_IMGOPTIM_VERSION, 'all');
        }
    }

    public function enqueue_scripts()
    {
        $this->screen_ids=apply_filters('wpvivid_imgoptim_get_screen_ids',array());

        if(in_array(get_current_screen()->id,$this->screen_ids))
        {
            wp_enqueue_script(WPVIVID_IMGOPTIM_SLUG, WPVIVID_IMGOPTIM_URL . '/includes/display/js/wpvivid-imgoptim.js', array('jquery'), WPVIVID_IMGOPTIM_VERSION, false);
            wp_localize_script(WPVIVID_IMGOPTIM_SLUG, 'wpvivid_ajax_object', array('ajax_url' => admin_url('admin-ajax.php'),'ajax_nonce'=>wp_create_nonce('wpvivid_ajax')));
            wp_localize_script(WPVIVID_IMGOPTIM_SLUG, 'wpvividlion', array(
                'warning' => __('Warning:', 'wpvivid-imgoptim'),
                'error' => __('Error:', 'wpvivid-imgoptim'),
                'remotealias' => __('Warning: An alias for remote storage is required.', 'wpvivid-imgoptim'),
                'remoteexist' => __('Warning: The alias already exists in storage list.', 'wpvivid-imgoptim'),
                'backup_calc_timeout' => __('Calculating the size of files, folder and database timed out. If you continue to receive this error, please go to the plugin settings, uncheck \'Calculate the size of files, folder and database before backing up\', save changes, then try again.', 'wpvivid-imgoptim'),
                'restore_step1' => __('Step One: In the backup list, click the \'Restore\' button on the backup you want to restore. This will bring up the restore tab', 'wpvivid-imgoptim'),
                'restore_step2' => __('Step Two: Choose an option to complete restore, if any', 'wpvivid-imgoptim'),
                'restore_step3' => __('Step Three: Click \'Restore\' button', 'wpvivid-imgoptim'),
                'get_key_step1' => __('1. Visit Key tab page of WPvivid backup plugin of destination site.', 'wpvivid-imgoptim'),
                'get_key_step2' => __('2. Generate a key by clicking Generate button and copy it.', 'wpvivid-imgoptim'),
                'get_key_step3' => __('3. Go back to this page and paste the key in key box below. Lastly, click Save button.', 'wpvivid-imgoptim'),
            ));
            wp_enqueue_script('plupload-all');
        }
    }

    public function add_plugin_admin_menu()
    {
        $menu['page_title']= 'WPvivid Imgoptim';
        $menu['menu_title']= 'WPvivid Imgoptim';
        $menu['capability']='administrator';
        $menu['menu_slug']=WPVIVID_IMGOPTIM_SLUG;
        $menu['function']=array($this, 'display');
        $menu['icon_url']='dashicons-cloud';
        $menu['position']=100;

        $menu=apply_filters('wpvivid_imgoptim_get_main_admin_menus', $menu);
        if($menu!==false)
        {
            add_menu_page( $menu['page_title'],$menu['menu_title'], $menu['capability'], $menu['menu_slug'], $menu['function'], $menu['icon_url'], $menu['position']);
        }

        $this->submenus = apply_filters('wpvivid_imgoptim_get_admin_menus', $this->submenus);

        if(!empty($this->submenus))
        {
            usort($this->submenus, function ($a, $b)
            {
                if ($a['index'] == $b['index'])
                    return 0;

                if ($a['index'] > $b['index'])
                    return 1;
                else
                    return -1;
            });

            foreach ($this->submenus as $submenu)
            {
                add_submenu_page(
                    $submenu['parent_slug'],
                    $submenu['page_title'],
                    $submenu['menu_title'],
                    $submenu['capability'],
                    $submenu['menu_slug'],
                    $submenu['function']);
            }
        }
    }

    public function get_admin_menus($submenus)
    {
        $submenu['parent_slug']=WPVIVID_IMGOPTIM_SLUG;
        $submenu['page_title']= 'Image Optimization';
        $submenu['menu_title']=__('Image Optimization', 'wpvivid-imgoptim');
        $submenu['capability']='administrator';
        $submenu['menu_slug']=WPVIVID_IMGOPTIM_SLUG;
        $submenu['index']=1;
        $submenu['function']=array($this, 'display');
        $submenus[$submenu['menu_slug']]=$submenu;
        return $submenus;
    }

    public function display()
    {

        do_action('wpvivid_show_imgoptim_notice');
        ?>
        <div class="wrap">
            <div id="icon-options-general" class="icon32"></div>
            <h1><?php esc_attr_e( 'WPvivid Plugins - Image Optimization', 'wpvivid-imgoptim' ); ?></h1>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <div class="wpvivid-backup">
                                <?php
                                $this->welcome_bar();
                                ?>
                                <div class="wpvivid-canvas wpvivid-clear-float">
                                    <div class="wpvivid-one-coloum" style="padding-top:0;">
                                            <?php
                                            if(get_option('wpvivid_imgoptim_user',false)===false)
                                            {
                                                $this->license_box();
                                            }
                                            ?>
                                        <div class="wpvivid-one-coloum wpvivid-workflow wpvivid-clear-float" style="">
                                            <?php
                                            $this->progress();
                                            $this->overview();
                                            $this->optimize();
                                            ?>
                                        </div>

                                        <div>
                                            <?php
                                            if(!class_exists('WPvivid_Tab_Page_Container_Ex'))
                                            {
                                                include_once WPVIVID_IMGOPTIM_DIR . '/includes/class-wpvivid-tab-page-container-ex.php';
                                            }

                                            $args['is_parent_tab']=0;
                                            $args['transparency']=1;
                                            $this->main_tab=new WPvivid_Tab_Page_Container_Ex();
                                            $args['span_class']='dashicons dashicons-backup';
                                            $args['span_style']='color:#007cba; padding-right:0.5em;margin-top:0.1em;';
                                            $args['div_style']='padding:0;display:block;';
                                            $args['is_parent_tab']=0;
                                            $overview=$this->get_optimize_data();

                                            $tabs['scan']['title']=__('Optimized Images', 'wpvivid-imgoptim').'('.$overview['optimized_images'].')';
                                            $tabs['scan']['slug']='optimize';
                                            $tabs['scan']['callback']=array($this, 'optimized_images');
                                            $tabs['scan']['args']=$args;

                                            $args['span_class']='dashicons dashicons-welcome-write-blog';
                                            $args['span_style']='color:grey;padding-right:0.5em;margin-top:0.1em;';
                                            $args['div_style']='padding:0;';
                                            $args['is_parent_tab']=0;
                                            $tabs['isolate']['title']=__('Logs','wpvivid-imgoptim');
                                            $tabs['isolate']['slug']='log';
                                            $tabs['isolate']['callback']=array($this, 'error_log');
                                            $tabs['isolate']['args']=$args;

                                            foreach ($tabs as $key=>$tab)
                                            {
                                                $this->main_tab->add_tab($tab['title'],$tab['slug'],$tab['callback'], $tab['args']);
                                            }
                                            $this->main_tab->display();
                                            ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $this->sidebar();?>
                </div>
            </div>
        </div>
        <?php
    }

    public function welcome_bar()
    {
        if ( ! function_exists( 'is_plugin_active' ) )
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }

        if(is_plugin_active('wpvivid-backuprestore/wpvivid-backuprestore.php'))
        {
            $url='admin.php?page=WPvivid';
        }
        else
        {
            $url='plugin-install.php?s=wpvivid&tab=search&type=term';
        }

        ?>
        <div class="wpvivid-welcome-bar wpvivid-clear-float">
            <div class="wpvivid-welcome-bar-left">
                <p></p>
                <div>
                    <span class="dashicons dashicons-format-gallery wpvivid-dashicons-large wpvivid-dashicons-red"></span>
                    <span class="wpvivid-page-title"><?php esc_html_e('Image Bulk Optimization', 'wpvivid-imgoptim');?>
                        <span class="wpvivid-rectangle-small wpvivid-grey"><?php echo esc_html(WPVIVID_IMGOPTIM_VERSION); ?></span>
                    </span>
                </div>
                <p>
                    <span class="about-description"><?php esc_html_e('The page allows you to optimize images or media files on your website in bulk.', 'wpvivid-imgoptim');?></span>
                </p>
            </div>
        </div>
        <div class="wpvivid-nav-bar wpvivid-clear-float">
            <span class="dashicons dashicons-lightbulb wpvivid-dashicons-orange"></span>
            <span><?php esc_html_e('It is recommended to back up the entire website with', 'wpvivid-imgoptim');?> <a href="<?php echo esc_url($url)?>">WPvivid Backup & Migration plugin (It's free)</a> before optimizing images.</span>
        </div>
        <?php
    }

    public function sidebar()
    {
        global $wpvivid_imgoptim;
        $wpvivid_imgoptim->sidebar();
    }

    public function license_box()
    {
        do_action('wpvivivd_image_optimization_license_box');
    }

    public function progress()
    {
        $task=new WPvivid_ImgOptim_Task();
        $result=$task->get_task_progress();

        if(get_option('wpvivid_imgoptim_user',false)===false)
        {
            ?>
            <div id="wpvivid_image_optimize_progress" style="display: none">
                <p>
                    <span>
                        <strong><?php esc_html_e('Bulk Optimization Progress', 'wpvivid-imgoptim');?></strong>
                    </span>
                    <span style="float:right;">
                    <span class="wpvivid-rectangle wpvivid-green"><?php esc_html_e('Optimized', 'wpvivid-imgoptim');?></span>
                    <span class="wpvivid-rectangle wpvivid-grey"><?php esc_html_e('Un-optimized', 'wpvivid-imgoptim');?></span>
                    </span>
                </p>
                <p>
                    <span class="wpvivid-span-progress">
                    <span class="wpvivid-span-processed-progress" style="width:0%;">0% <?php esc_html_e('completed', 'wpvivid-imgoptim');?></span>
                    </span>
                </p>
            </div>
            <?php
            return;
        }

        if($result['result']=='success'&&$result['finished']==0)
        {
            $result['progress_html']='
                <div id="wpvivid_image_optimize_progress">
                    <p>
                        <span>
                            <strong>'.__('Bulk Optimization Progress','wpvivid-imgoptim').'</strong>
                        </span>
                        <span style="float:right;">
                            <span class="wpvivid-rectangle wpvivid-green">'.__('Optimized','wpvivid-imgoptim').'</span>
                            <span class="wpvivid-rectangle wpvivid-grey">'.__('Un-optimized','wpvivid-imgoptim').'</span>
                        </span>
                    </p>            
                    <p>
                        <span class="wpvivid-span-progress">
                            <span class="wpvivid-span-processed-progress" style="width:'.$result['percent'].'%;">'.$result['percent'].'% '.__('completed','wpvivid-imgoptim').'</span>
                        </span>
                    </p>
                    <p><span class="dashicons dashicons-flag wpvivid-dashicons-green"></span><span><strong>Processing: </strong></span>
						<span style="color:#999;">'.$result['log'].'</span>
						<span title="View logs"><a id="wpvivid_image_open_log" href="#">logs</a></span>
				    </p>
                </div>';
            echo  wp_kses_post($result['progress_html']);
        }
        else
        {
           ?>
            <div id="wpvivid_image_optimize_progress" style="display: none">
                <p>
                    <span>
                        <strong><?php esc_html_e('Bulk Optimization Progress', 'wpvivid-imgoptim');?></strong>
                    </span>
                    <span style="float:right;">
                    <span class="wpvivid-rectangle wpvivid-green"><?php esc_html_e('Optimized', 'wpvivid-imgoptim');?></span>
                    <span class="wpvivid-rectangle wpvivid-grey"><?php esc_html_e('Un-optimized', 'wpvivid-imgoptim');?></span>
                    </span>
                </p>
                <p>
                    <span class="wpvivid-span-progress">
                    <span class="wpvivid-span-processed-progress" style="width:0%;">0% <?php esc_html_e('completed', 'wpvivid-imgoptim');?></span>
                    </span>
                </p>
            </div>
            <?php
        }
    }

    public function overview()
    {
        $overview=$this->get_optimize_data();
        if(get_option('wpvivid_imgoptim_user',false)===false)
        {
            $server_cache['server_name']='N/A';
            $server_cache['total']=0;
            $server_cache['remain']=0;

            $admin_url=apply_filters('wpvivid_get_admin_url', '');
            $admin_url.='admin.php?page=wpvivid-imgoptim-setting';
        }
        else
        {
            $server_cache=get_option('wpvivid_server_cache',array());
            if(empty($server_cache))
            {
                $server_cache['total']=0;
                $server_cache['remain']=0;
            }
            $options=get_option('wpvivid_optimization_options',array());
            $region=isset($options['region'])?$options['region']:'us1';
            if($region=='us1')
            {
                $server_cache['server_name']='North American - Free';
            }
            else if($region=='us2')
            {
                $server_cache['server_name']='North American - Test';
            }
            else
            {
                $server_cache['server_name']='North American - Free';
            }

            $admin_url=apply_filters('wpvivid_get_admin_url', '');
            $admin_url.='admin.php?page=wpvivid-imgoptim-setting';
        }
        $unoptimized=max($overview['total_images']-$overview['optimized_images'],0);
        ?>
        <div class="wpvivid-two-col">
            <div class="wpvivid-features-box-image-optimiztion-plate" style="float:left;">
                <p></p>
                <div>
                    <span class="dashicons dashicons-smartphone wpvivid-dashicons-blue"></span>
                    <span class="wpvivid-dashicons"><?php esc_html_e('Credits', 'wpvivid-imgoptim');?>:</span>
                    <span id="wpvivid_credits_remain"><?php echo esc_html($server_cache['remain'])?></span>
                    <span></span>/<span id="wpvivid_credits_total"><?php echo esc_html($server_cache['total'])?></span>
                    <span class="dashicons dashicons-editor-help wpvivid-dashicons-editor-help wpvivid-tooltip">
							<div class="wpvivid-bottom">
								<!-- The content you need -->
                                <p>Credits stands for the total number of the images that you can optimize. Credits are consumed based on the actual number of images optimized.</p>
                                <p>For Example:</p>
                                <p>1. You have 10 images optimized, and they have 60 associated images, then 70 credits are used.<p>
                                <p>2. 900/1000 means you have 900 out of 1000 credits remaining in your account.</p>
                                <i></i> <!-- do not delete this line -->
                            </div>
                    </span>
                </div>
                <p></p>
                <div>
                    <span class="dashicons dashicons-clock wpvivid-dashicons-orange"></span>
                    <span class="wpvivid-dashicons"><?php esc_html_e('Un-optimized', 'wpvivid-imgoptim');?>:</span>
                    <span id="wpvivid_overview_unoptimized"><?php echo esc_html($unoptimized);?></span>
                    <span class="dashicons dashicons-editor-help wpvivid-dashicons-editor-help wpvivid-tooltip">
							<div class="wpvivid-bottom">
								<!-- The content you need -->
                                <p>The number of original images and their associated WordPress-generated images which are not optimized.</p>
                                <i></i> <!-- do not delete this line -->
                            </div>
                    </span>
                </div>
                <p></p>
                <div>
                    <span class="dashicons dashicons-yes wpvivid-dashicons-green"></span>
                    <span class="wpvivid-dashicons"><?php esc_html_e('Optimized', 'wpvivid-imgoptim');?>:</span>
                    <span id="wpvivid_overview_optimized_images"><?php echo esc_html($overview['optimized_images']);?></span>
                    <span class="dashicons dashicons-editor-help wpvivid-dashicons-editor-help wpvivid-tooltip">
							<div class="wpvivid-bottom">
								<!-- The content you need -->
                                <p>The actual number of images that have been optimized.</p>
                                <i></i> <!-- do not delete this line -->
                            </div>
                    </span>
                </div>
                <p></p>
            </div>
            <div class="wpvivid-features-box-image-optimiztion-details" style="float:left;">
                <p>
                    <span class="dashicons dashicons-thumbs-down wpvivid-dashicons wpvivid-dashicons-red"></span>
                    <span><?php esc_html_e('Original Size', 'wpvivid-imgoptim');?>:</span>
                    <span id="wpvivid_overview_original_size"><?php echo esc_html(size_format($overview['original_size'],2))?></span>
                </p>
                <p>
                    <span class="dashicons dashicons-thumbs-up wpvivid-dashicons wpvivid-dashicons-green"></span>
                    <span><?php esc_html_e('Optimized Size', 'wpvivid-imgoptim');?></span>
                    <span id="wpvivid_overview_optimized_size"><?php echo esc_html(size_format($overview['optimized_size'],2))?></span>
                </p>
                <p>
                    <span class="dashicons dashicons-chart-line wpvivid-dashicons wpvivid-dashicons-blue"></span>
                    <span><?php esc_html_e('Total Saved', 'wpvivid-imgoptim');?>:</span>
                    <span id="wpvivid_overview_optimized_percent"><?php echo esc_html($overview['optimized_percent'])?>%</span>
                </p>
            </div>
            <div style="clear: both;"></div>
            <div>
                <span class="dashicons dashicons-admin-site wpvivid-dashicons-blue"></span>
                <span><?php esc_html_e('Cloud Server', 'wpvivid-imgoptim');?>:</span>
                <span><code id="wpvivid_server_name"><?php echo esc_html($server_cache['server_name'])?></code></span>
                <span><a href="<?php echo esc_url($admin_url);?>"><?php esc_html_e('Setting', 'wpvivid-imgoptim');?></a></span>
            </div>
        </div>
        <script>
            jQuery(document).ready(function ()
            {
                <?php
                if(get_option('wpvivid_imgoptim_user',false)!==false)
                {
                    $server_cache=get_option('wpvivid_server_cache',array());
                    ?>
                    wpvivid_get_server_status();
                    <?php
                }
                ?>
                wpvivid_get_overview();
            });
            function wpvivid_get_server_status()
            {
                var ajax_data = {
                    'action': 'wpvivid_get_server_status'
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            jQuery('#wpvivid_credits_remain').html(jsonarray.status.remain);
                            jQuery('#wpvivid_credits_total').html(jsonarray.status.total);
                            jQuery('#wpvivid_server_name').html(jsonarray.status.server_name);
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            alert(jsonarray.error);
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('get server', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            function wpvivid_get_overview()
            {
                var ajax_data = {
                    'action': 'wpvivid_start_get_overview'
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            if(jsonarray.continue)
                            {
                                wpvivid_get_overview_progress();
                            }
                            else
                            {
                                jQuery('#wpvivid_overview_unoptimized').html(jsonarray.status.unoptimized);
                                jQuery('#wpvivid_overview_optimized_images').html(jsonarray.status.optimized_images);
                                jQuery('#wpvivid_overview_original_size').html(jsonarray.status.original_size_format);
                                jQuery('#wpvivid_overview_optimized_size').html(jsonarray.status.optimized_size_format);
                                jQuery('#wpvivid_overview_optimized_percent').html(jsonarray.status.optimized_percent_format);
                                jQuery('#wpvivid_tab_optimize').children().last().html("Optimized Images("+jsonarray.status.optimized_images+")");
                            }
                            //
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            alert(jsonarray.error);
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('get overview', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            function wpvivid_get_overview_progress()
            {
                var ajax_data = {
                    'action': 'wpvivid_get_overview'
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            if(jsonarray.continue)
                            {
                                wpvivid_get_overview_progress();
                            }
                            else
                            {
                                jQuery('#wpvivid_overview_unoptimized').html(jsonarray.status.unoptimized);
                                jQuery('#wpvivid_overview_optimized_images').html(jsonarray.status.optimized_images);
                                jQuery('#wpvivid_overview_original_size').html(jsonarray.status.original_size_format);
                                jQuery('#wpvivid_overview_optimized_size').html(jsonarray.status.optimized_size_format);
                                jQuery('#wpvivid_overview_optimized_percent').html(jsonarray.status.optimized_percent_format);
                                jQuery('#wpvivid_tab_optimize').children().last().html("Optimized Images("+jsonarray.status.optimized_images+")");
                            }
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            alert(jsonarray.error);
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('get server', textStatus, errorThrown);
                    alert(error_message);
                });
            }
        </script>
        <?php
    }

    public function start_get_overview()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }
        ini_set('memory_limit','512M');
        $optimize_data = array(
            'original_size'  => 0,
            'optimized_size' => 0,
            'optimized_percent'=> 0,
            'total_images'     => 0,
            'optimized_images' => 0,
        );

        $overview_array=array();
        $overview_array['unoptimized']=0;
        $overview_array['optimized_images']=0;
        $overview_array['original_size']=0;
        $overview_array['optimized_size']=0;
        $overview_array['optimized_percent']=0;
        $overview_array['total_images']=0;
        $overview_array['offset']=0;
        update_option('wpvivid_get_get_overview', $overview_array);

        global $wpdb;

        $args = array (
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 0,
        ) ;
        $attatchments = new WP_Query ($args) ;
        $count = $attatchments->found_posts ;

        $offset=0;
        $page=2000;

        $options=get_option('wpvivid_optimization_options',array());
        $not_found=array();
        $optimize_data['total_images']=0;

        $start_time=time();
        while($offset<$count)
        {
            $query_images_args = array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'post_status'    => 'inherit',
                'offset'=>$offset,
                'posts_per_page' => $page,
            );

            $query_images = new WP_Query( $query_images_args );
            $offset+=$page;

            $total_images=array();

            foreach ( $query_images->posts as $image )
            {
                if(get_post_mime_type($image->ID)=='image/jpeg'||get_post_mime_type($image->ID)=='image/jpg'||get_post_mime_type($image->ID)=='image/png')
                {
                    $total_images[] = $image->ID ;
                }
            }

            $query_images=null;
            if(empty($total_images))
            {
                continue;
            }

            foreach ($total_images as $image_id)
            {
                $image_opt_meta = get_post_meta( $image_id, 'wpvivid_image_optimize_meta', true );
                $file_path = get_attached_file( $image_id );
                if(isset($options['skip_size'])&&isset($options['skip_size']['og']))
                {
                    if($options['skip_size']['og']!=true)
                    {
                        if (!file_exists($file_path))
                        {
                            $not_found[$image_id]['og']=$file_path;
                        }
                        else
                        {
                            $optimize_data['total_images']++;
                            if(isset($image_opt_meta['size']['og']))
                            {
                                if(isset($image_opt_meta['size']['og']['opt_status'])&&$image_opt_meta['size']['og']['opt_status']==1)
                                {
                                    $optimize_data['optimized_images']++;
                                }
                            }
                        }
                    }
                }
                else
                {
                    if (!file_exists($file_path))
                    {
                        $not_found[$image_id]['og']=$file_path;
                    }
                    else
                    {
                        $optimize_data['total_images']++;
                        if(isset($image_opt_meta['size']['og']))
                        {
                            if(isset($image_opt_meta['size']['og']['opt_status'])&&$image_opt_meta['size']['og']['opt_status']==1)
                            {
                                $optimize_data['optimized_images']++;
                            }
                        }
                    }
                }

                if(!empty($image_opt_meta['sum']))
                {
                    $optimize_data['original_size']  += ! empty( $image_opt_meta['sum']['og_size'] ) ? (int) $image_opt_meta['sum']['og_size'] : 0;
                    $optimize_data['optimized_size']   += ! empty( $image_opt_meta['sum']['opt_size'] ) ? (int) $image_opt_meta['sum']['opt_size'] : 0;
                }

                $meta = wp_get_attachment_metadata( $image_id, true );
                if(!empty($meta['sizes']))
                {
                    foreach ( $meta['sizes'] as $size_key => $size_data )
                    {
                        $filename = path_join(dirname($file_path), $size_data['file']);

                        if (!file_exists($filename))
                        {
                            $not_found[$image_id][$size_key]=$filename;
                            continue;
                        }


                        if(isset($options['skip_size'])&&isset($options['skip_size'][$size_key]))
                        {
                            if($options['skip_size'][$size_key])
                                continue;
                        }

                        $optimize_data['total_images']++;
                        if(isset($image_opt_meta['size'][$size_key]))
                        {
                            if(isset($image_opt_meta['size'][$size_key]['opt_status'])&&$image_opt_meta['size'][$size_key]['opt_status']==1)
                            {
                                $optimize_data['optimized_images']++;
                            }
                        }
                    }
                }
            }

            $total_images=null;
            $current_time=time();
            if($current_time - $start_time >= 21)
            {
                $overview_array=array();
                $overview_array['unoptimized']=isset($optimize_data['unoptimized'])?$optimize_data['unoptimized']:0;
                $overview_array['optimized_images']=isset($optimize_data['optimized_images'])?$optimize_data['optimized_images']:0;
                $overview_array['original_size']=isset($optimize_data['original_size'])?$optimize_data['original_size']:0;
                $overview_array['optimized_size']=isset($optimize_data['optimized_size'])?$optimize_data['optimized_size']:0;
                $overview_array['optimized_percent']=isset($optimize_data['optimized_percent'])?$optimize_data['optimized_percent']:0;
                $overview_array['total_images']=isset($optimize_data['total_images'])?$optimize_data['total_images']:0;
                $overview_array['offset']=$offset;
                update_option('wpvivid_get_get_overview', $overview_array);
                $ret['result']='success';
                $ret['continue']=1;
                echo wp_json_encode($ret);
                die();
            }
        }

        if($optimize_data['optimized_size']>0&&$optimize_data['original_size']>0)
        {
            $optimize_data['optimized_percent']=intval(100-($optimize_data['optimized_size']/$optimize_data['original_size'])*100);
        }

        $optimize_data['unoptimized']=max($optimize_data['total_images']-$optimize_data['optimized_images'],0);

        $optimize_data['original_size_format']=size_format($optimize_data['original_size'],2);
        $optimize_data['optimized_size_format']=size_format($optimize_data['optimized_size'],2);
        $optimize_data['optimized_percent_format']= $optimize_data['optimized_percent'].'%';
        $ret['status']=$optimize_data;
        $ret['result']='success';
        $ret['continue']=0;
        update_option('wpvivid_imgoptim_overview',$optimize_data);
        echo wp_json_encode($ret);
        die();
    }

    public function get_overview()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }

        $optimize_data = array(
            'original_size'  => 0,
            'optimized_size' => 0,
            'optimized_percent'=> 0,
            'total_images'     => 0,
            'optimized_images' => 0,
        );

        global $wpdb;

        $overview_array=get_option('wpvivid_get_get_overview', array());
        $optimize_data['unoptimized']=isset($overview_array['unoptimized'])?$overview_array['unoptimized']:0;
        $optimize_data['optimized_images']=isset($overview_array['optimized_images'])?$overview_array['optimized_images']:0;
        $optimize_data['original_size']=isset($overview_array['original_size'])?$overview_array['original_size']:0;
        $optimize_data['optimized_size']=isset($overview_array['optimized_size'])?$overview_array['optimized_size']:0;
        $optimize_data['optimized_percent']=isset($overview_array['optimized_percent'])?$overview_array['optimized_percent']:0;
        $optimize_data['total_images']=isset($overview_array['total_images'])?$overview_array['total_images']:0;

        $args = array (
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 0,
        ) ;
        $attatchments = new WP_Query ($args) ;
        $count = $attatchments->found_posts ;

        $offset=isset($overview_array['offset'])?$overview_array['offset']:0;
        $page=2000;

        $options=get_option('wpvivid_optimization_options',array());
        $not_found=array();
        //$optimize_data['total_images']=0;

        $start_time=time();
        while($offset<$count)
        {
            $query_images_args = array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'post_status'    => 'inherit',
                'offset'=>$offset,
                'posts_per_page' => $page,
            );

            $query_images = new WP_Query( $query_images_args );
            $offset+=$page;

            $total_images=array();

            foreach ( $query_images->posts as $image )
            {
                if(get_post_mime_type($image->ID)=='image/jpeg'||get_post_mime_type($image->ID)=='image/jpg'||get_post_mime_type($image->ID)=='image/png')
                {
                    $total_images[] = $image->ID ;
                }
            }

            $query_images=null;
            if(empty($total_images))
            {
                continue;
            }

            foreach ($total_images as $image_id)
            {
                $image_opt_meta = get_post_meta( $image_id, 'wpvivid_image_optimize_meta', true );
                $file_path = get_attached_file( $image_id );
                if(isset($options['skip_size'])&&isset($options['skip_size']['og']))
                {
                    if($options['skip_size']['og']!=true)
                    {
                        if (!file_exists($file_path))
                        {
                            $not_found[$image_id]['og']=$file_path;
                        }
                        else
                        {
                            $optimize_data['total_images']++;
                            if(isset($image_opt_meta['size']['og']))
                            {
                                if(isset($image_opt_meta['size']['og']['opt_status'])&&$image_opt_meta['size']['og']['opt_status']==1)
                                {
                                    $optimize_data['optimized_images']++;
                                }
                            }
                        }
                    }
                }
                else
                {
                    if (!file_exists($file_path))
                    {
                        $not_found[$image_id]['og']=$file_path;
                    }
                    else
                    {
                        $optimize_data['total_images']++;
                        if(isset($image_opt_meta['size']['og']))
                        {
                            if(isset($image_opt_meta['size']['og']['opt_status'])&&$image_opt_meta['size']['og']['opt_status']==1)
                            {
                                $optimize_data['optimized_images']++;
                            }
                        }
                    }
                }

                if(!empty($image_opt_meta['sum']))
                {
                    $optimize_data['original_size']  += ! empty( $image_opt_meta['sum']['og_size'] ) ? (int) $image_opt_meta['sum']['og_size'] : 0;
                    $optimize_data['optimized_size']   += ! empty( $image_opt_meta['sum']['opt_size'] ) ? (int) $image_opt_meta['sum']['opt_size'] : 0;
                }

                $meta = wp_get_attachment_metadata( $image_id, true );
                if(!empty($meta['sizes']))
                {
                    foreach ( $meta['sizes'] as $size_key => $size_data )
                    {
                        $filename = path_join(dirname($file_path), $size_data['file']);

                        if (!file_exists($filename))
                        {
                            $not_found[$image_id][$size_key]=$filename;
                            continue;
                        }


                        if(isset($options['skip_size'])&&isset($options['skip_size'][$size_key]))
                        {
                            if($options['skip_size'][$size_key])
                                continue;
                        }

                        $optimize_data['total_images']++;
                        if(isset($image_opt_meta['size'][$size_key]))
                        {
                            if(isset($image_opt_meta['size'][$size_key]['opt_status'])&&$image_opt_meta['size'][$size_key]['opt_status']==1)
                            {
                                $optimize_data['optimized_images']++;
                            }
                        }
                    }
                }
            }

            $total_images=null;
            $current_time=time();
            if($current_time - $start_time >= 21)
            {
                $overview_array=array();
                $overview_array['unoptimized']=isset($optimize_data['unoptimized'])?$optimize_data['unoptimized']:0;
                $overview_array['optimized_images']=isset($optimize_data['optimized_images'])?$optimize_data['optimized_images']:0;
                $overview_array['original_size']=isset($optimize_data['original_size'])?$optimize_data['original_size']:0;
                $overview_array['optimized_size']=isset($optimize_data['optimized_size'])?$optimize_data['optimized_size']:0;
                $overview_array['optimized_percent']=isset($optimize_data['optimized_percent'])?$optimize_data['optimized_percent']:0;
                $overview_array['total_images']=isset($optimize_data['total_images'])?$optimize_data['total_images']:0;
                $overview_array['offset']=$offset;
                update_option('wpvivid_get_get_overview', $overview_array);
                $ret['result']='success';
                $ret['continue']=1;
                echo wp_json_encode($ret);
                die();
            }
        }

        if($optimize_data['optimized_size']>0&&$optimize_data['original_size']>0)
        {
            $optimize_data['optimized_percent']=intval(100-($optimize_data['optimized_size']/$optimize_data['original_size'])*100);
        }

        $optimize_data['unoptimized']=max($optimize_data['total_images']-$optimize_data['optimized_images'],0);

        $optimize_data['original_size_format']=size_format($optimize_data['original_size'],2);
        $optimize_data['optimized_size_format']=size_format($optimize_data['optimized_size'],2);
        $optimize_data['optimized_percent_format']= $optimize_data['optimized_percent'].'%';
        $ret['status']=$optimize_data;
        $ret['result']='success';
        $ret['continue']=0;
        update_option('wpvivid_imgoptim_overview',$optimize_data);
        echo wp_json_encode($ret);
        die();
    }

    public function get_optimize_data()
    {
        $optimize_data=get_option('wpvivid_imgoptim_overview',array());
        if(empty($optimize_data))
        {
            $optimize_data = array(
                'original_size'  => 0,
                'optimized_size' => 0,
                'optimized_percent'=> 0,
                'total_images'     => 0,
                'optimized_images' => 0,
            );
        }

        return $optimize_data;
    }

    public function optimize()
    {
        $task=new WPvivid_ImgOptim_Task();
        $result=$task->get_task_progress();

        if ( ! function_exists( 'is_plugin_active' ) )
        {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
        }

        if(is_plugin_active('wpvivid-backuprestore/wpvivid-backuprestore.php'))
        {
            $url='admin.php?page=WPvivid';
        }
        else
        {
            $url='plugin-install.php?s=wpvivid&tab=search&type=term';
        }

        ?>
        <div class="wpvivid-two-col">
            <div style="padding:1em;width:50%;margin:auto;">
                <fieldset>
                    <label class="wpvivid-radio"><?php esc_html_e('Resizing images only', 'wpvivid-imgoptim');?>
                        <input type="radio" name="imgopt_resize" value="1">
                        <span class="wpvivid-radio-checkmark"></span>
                    </label>
                    <label class="wpvivid-radio"><?php esc_html_e('Compress and resize all images', 'wpvivid-imgoptim');?>
                        <input type="radio" name="imgopt_resize" value="0" checked>
                        <span class="wpvivid-radio-checkmark" ></span>
                    </label>
                </fieldset>
            </div>
            <div style="width:50%;margin:auto;">
                <input class="button-primary" style="width: 200px; height: 50px; font-size: 20px; margin-bottom: 10px; pointer-events: auto; opacity: 1;" id="wpvivid_start_opt" type="submit" value="Optimize Now">
                <input class="button-primary" style="display:none;width: 200px; height: 50px; font-size: 20px; margin-bottom: 10px; pointer-events: auto; opacity: 1;" id="wpvivid_cancel_opt" type="submit" value="Cancel">
            </div>
            <div>
                <p style="text-align:center;">Recommended: <a href="<?php echo esc_url($url);?>">Backup the website</a> before optimizing images
            </div>
        </div>
        <script>
            jQuery('#wpvivid_start_opt').click(function()
            {
                wpvivid_start_opt();
            });

            jQuery('#wpvivid_cancel_opt').click(function()
            {
                if (confirm('Are you sure you want to cancel optimization?'))
                {
                    wpvivid_cancel_opt();
                }
            });

            function wpvivid_cancel_opt()
            {
                jQuery('#wpvivid_cancel_opt').prop('disabled', true);

                var ajax_data = {
                    'action': 'wpvivid_cancel_opt_task',
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('cancel task', textStatus, errorThrown);
                    alert(error_message);

                    jQuery('#wpvivid_cancel_opt').prop('disabled', false);
                });
            }

            function wpvivid_start_opt()
            {
                var html='<p><span><strong>Bulk Optimization Progress</strong></span>' +
                    '<span style="float:right;"><span class="wpvivid-rectangle wpvivid-green">Optimized</span>' +
                    '<span class="wpvivid-rectangle wpvivid-grey">Un-optimized</span></span></p>' +
                    '<p><span class="wpvivid-span-progress"><span class="wpvivid-span-processed-progress" style="width:0%;">0% completed</span></span></p>'
                    +'<p><span class="dashicons dashicons-flag wpvivid-dashicons-green"></span><span><strong>Processing</strong></span></p>';
                jQuery('#wpvivid_image_optimize_progress').show();
                jQuery('#wpvivid_image_optimize_progress').html(html);
                jQuery('#wpvivid_start_opt').prop('disabled', true);
                jQuery('#wpvivid_start_opt').hide();
                jQuery('#wpvivid_cancel_opt').show();
                var resize='0';
                jQuery('input:radio[name=imgopt_resize]').each(function()
                {
                    if(jQuery(this).prop('checked'))
                    {
                        resize = jQuery(this).prop('value');
                    }
                });

                var ajax_data = {
                    'action': 'wpvivid_init_opt_task',
                    'resize': resize
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            if(typeof jsonarray.continue !== 'undefined' && jsonarray.continue)
                            {
                                wpvivid_start_opt_ex();
                            }
                            else
                            {
                                wpvivid_get_opt_progress();
                            }
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            alert(jsonarray.error);
                            jQuery('#wpvivid_image_optimize_progress').hide();
                            jQuery('#wpvivid_start_opt').prop('disabled', false);
                            jQuery('#wpvivid_start_opt').show();
                            jQuery('#wpvivid_cancel_opt').hide();
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        jQuery('#wpvivid_image_optimize_progress').hide();
                        jQuery('#wpvivid_start_opt').prop('disabled', false);
                        jQuery('#wpvivid_start_opt').show();
                        jQuery('#wpvivid_cancel_opt').hide();
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('start task', textStatus, errorThrown);
                    alert(error_message);

                    jQuery('#wpvivid_image_optimize_progress').hide();
                    jQuery('#wpvivid_start_opt').prop('disabled', false);
                    jQuery('#wpvivid_start_opt').show();
                    jQuery('#wpvivid_cancel_opt').hide();
                });
            }

            function wpvivid_start_opt_ex()
            {
                var html='<p><span><strong>Bulk Optimization Progress</strong></span>' +
                    '<span style="float:right;"><span class="wpvivid-rectangle wpvivid-green">Optimized</span>' +
                    '<span class="wpvivid-rectangle wpvivid-grey">Un-optimized</span></span></p>' +
                    '<p><span class="wpvivid-span-progress"><span class="wpvivid-span-processed-progress" style="width:0%;">0% completed</span></span></p>'
                +'<p><span class="dashicons dashicons-flag wpvivid-dashicons-green"></span><span><strong>Processing</strong></span></p>';
                jQuery('#wpvivid_image_optimize_progress').show();
                jQuery('#wpvivid_image_optimize_progress').html(html);
                jQuery('#wpvivid_start_opt').prop('disabled', true);
                jQuery('#wpvivid_start_opt').hide();
                jQuery('#wpvivid_cancel_opt').show();
                var resize='0';
                jQuery('input:radio[name=imgopt_resize]').each(function()
                {
                    if(jQuery(this).prop('checked'))
                    {
                        resize = jQuery(this).prop('value');
                    }
                });

                var ajax_data = {
                    'action': 'wpvivid_start_opt_task',
                    'resize': resize
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            if(typeof jsonarray.continue !== 'undefined' && jsonarray.continue)
                            {
                                wpvivid_start_opt_ex();
                            }
                            else
                            {
                                wpvivid_get_opt_progress();
                            }
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            alert(jsonarray.error);
                            jQuery('#wpvivid_image_optimize_progress').hide();
                            jQuery('#wpvivid_start_opt').prop('disabled', false);
                            jQuery('#wpvivid_start_opt').show();
                            jQuery('#wpvivid_cancel_opt').hide();
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        jQuery('#wpvivid_image_optimize_progress').hide();
                        jQuery('#wpvivid_start_opt').prop('disabled', false);
                        jQuery('#wpvivid_start_opt').show();
                        jQuery('#wpvivid_cancel_opt').hide();
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('start task', textStatus, errorThrown);
                    alert(error_message);

                    jQuery('#wpvivid_image_optimize_progress').hide();
                    jQuery('#wpvivid_start_opt').prop('disabled', false);
                    jQuery('#wpvivid_start_opt').show();
                    jQuery('#wpvivid_cancel_opt').hide();
                });
            }

            function wpvivid_get_opt_progress()
            {
                var ajax_data = {
                    'action': 'wpvivid_get_opt_progress'
                };

                wpvivid_post_request(ajax_data, function(data)
                {
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            jQuery('#wpvivid_image_optimize_progress').html(jsonarray.progress_html);
                            if(jsonarray.continue)
                            {
                                setTimeout(function ()
                                {
                                    wpvivid_get_opt_progress();
                                }, 1000);
                            }
                            else if(jsonarray.finished)
                            {
                                alert("The optimization successes!");
                                location.reload();
                            }
                            else
                            {
                                wpvivid_opt_image();
                            }
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            if(jsonarray.timeout)
                            {
                                wpvivid_opt_image();
                            }
                            else
                            {
                                alert(jsonarray.error);
                                jQuery('#wpvivid_image_optimize_progress').hide();
                                jQuery('#wpvivid_start_opt').prop('disabled', false);
                                jQuery('#wpvivid_start_opt').show();
                                jQuery('#wpvivid_cancel_opt').hide();
                            }
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        jQuery('#wpvivid_image_optimize_progress').hide();
                        jQuery('#wpvivid_start_opt').prop('disabled', false);
                        jQuery('#wpvivid_start_opt').show();
                        jQuery('#wpvivid_cancel_opt').hide();
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    setTimeout(function ()
                    {
                        wpvivid_get_opt_progress();
                    }, 1000);
                });
            }

            function wpvivid_opt_image()
            {
                var ajax_data = {
                    'action': 'wpvivid_opt_image'
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            setTimeout(function ()
                            {
                                wpvivid_get_opt_progress();
                            }, 1000);
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            alert(jsonarray.error);
                            jQuery('#wpvivid_image_optimize_progress').hide();
                            jQuery('#wpvivid_start_opt').prop('disabled', false);
                            jQuery('#wpvivid_start_opt').show();
                            jQuery('#wpvivid_cancel_opt').hide();
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        jQuery('#wpvivid_image_optimize_progress').hide();
                        jQuery('#wpvivid_start_opt').prop('disabled', false);
                        jQuery('#wpvivid_start_opt').show();
                        jQuery('#wpvivid_cancel_opt').hide();
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    wpvivid_get_opt_progress();
                });
            }

            jQuery(document).ready(function ()
            {
                <?php
                $info= get_option('wpvivid_imgoptim_user',false);
                if($info===false)
                {
                    ?>
                    jQuery('#wpvivid_start_opt').prop('disabled', true);
                    return;
                    <?php
                }

                if($result['result']=='success'&&$result['finished']==0)
                {
                    $cancel=get_option('wpvivid_image_opt_task_cancel',false);
                    if($cancel)
                    {
                    ?>
                    jQuery('#wpvivid_cancel_opt').prop('disabled', true);
                    jQuery('#wpvivid_start_opt').hide();
                    jQuery('#wpvivid_cancel_opt').show();
                    <?php
                    }
                    else
                    {
                    ?>
                    jQuery('#wpvivid_start_opt').prop('disabled', false);
                    jQuery('#wpvivid_start_opt').hide();
                    jQuery('#wpvivid_cancel_opt').show();
                    <?php
                    }
                }
                else
                {
                ?>
                jQuery('#wpvivid_start_opt').prop('disabled', false);
                jQuery('#wpvivid_start_opt').show();
                jQuery('#wpvivid_cancel_opt').hide();
                <?php
                }

                ?>

                var ajax_data = {
                    'action': 'wpvivid_get_opt_progress'
                };

                wpvivid_post_request(ajax_data, function(data)
                {
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            jQuery('#wpvivid_image_optimize_progress').html(jsonarray.progress_html);
                            if(jsonarray.continue)
                            {
                                setTimeout(function ()
                                {
                                    wpvivid_get_opt_progress();
                                }, 1000);
                            }
                            else if(jsonarray.finished)
                            {

                            }
                            else
                            {
                                wpvivid_opt_image();
                            }
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            if(jsonarray.timeout)
                            {
                                wpvivid_opt_image();
                            }
                            else
                            {
                                jQuery('#wpvivid_image_optimize_progress').hide();
                                jQuery('#wpvivid_start_opt').prop('disabled', false);
                                jQuery('#wpvivid_start_opt').show();
                                jQuery('#wpvivid_cancel_opt').hide();
                            }
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        jQuery('#wpvivid_image_optimize_progress').hide();
                        jQuery('#wpvivid_start_opt').prop('disabled', false);
                        jQuery('#wpvivid_start_opt').show();
                        jQuery('#wpvivid_cancel_opt').hide();
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('get progress', textStatus, errorThrown);
                    alert(error_message);

                    jQuery('#wpvivid_image_optimize_progress').hide();
                    jQuery('#wpvivid_start_opt').prop('disabled', false);
                    jQuery('#wpvivid_start_opt').show();
                    jQuery('#wpvivid_cancel_opt').hide();
                });
            });
        </script>
        <?php
    }

    public function optimized_images()
    {
        ?>
        <div style="" id="wpvivid_optimized_imgae_list">
                <?php

                $result=$this->get_optimized_list();

                $list = new WPvivid_Optimized_Image_List();
                $list->set_list($result);
                $list->prepare_items();
                $list ->display();
                ?>
        </div>
        <script>
            jQuery('#wpvivid_optimized_imgae_list').on("click",'.top-action',function()
            {
                var selected=jQuery('#wpvivid_image_opt_bulk_top_action').val();
                if(selected=='wpvivid_restore_selected_image')
                {
                    wpvivid_restore_selected_image();
                }
                else if(selected=='wpvivid_restore_all_image')
                {
                    wpvivid_restore_all_image();
                }
            });

            jQuery('#wpvivid_optimized_imgae_list').on("click",'.bottom-action',function()
            {
                var selected=jQuery('#wpvivid_image_opt_bulk_bottom_action').val();
                if(selected=='wpvivid_restore_selected_image')
                {
                    wpvivid_restore_selected_image();
                }
                else if(selected=='wpvivid_restore_all_image')
                {
                    wpvivid_restore_all_image();
                }
            });

            function wpvivid_restore_selected_image()
            {
                var json = {};
                json['selected']=Array();
                jQuery('input[name=opt][type=checkbox]').each(function(index, value)
                {
                    if(jQuery(value).prop('checked'))
                    {
                        json['selected'].push(jQuery(value).val())
                    }
                });

                if(json['selected'].length>0)
                {
                    var selected= JSON.stringify(json);
                }
                else
                {
                    alert('Please select at least one item to perform this action on.');
                    return;
                }

                jQuery('#wpvivid_optimized_imgae_list').find('.action').prop('disabled', true);
                var ajax_data = {
                    'action': 'wpvivid_restore_selected_opt_image',
                    'selected':selected
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                    jQuery('#wpvivid_optimized_imgae_list').find('.action').prop('disabled', false);
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            alert('Restore Success');
                            location.reload();
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            alert(jsonarray.error);
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    jQuery('#wpvivid_optimized_imgae_list').find('.action').prop('disabled', false);
                    var error_message = wpvivid_output_ajaxerror('restore image', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            function wpvivid_restore_all_image()
            {
                jQuery('#wpvivid_optimized_imgae_list').find('.action').prop('disabled', true);
                var ajax_data = {
                    'action': 'wpvivid_restore_all_opt_image'
                };
                wpvivid_post_request(ajax_data, function(data)
                {
                    jQuery('#wpvivid_optimized_imgae_list').find('.action').prop('disabled', false);
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            alert('Restore Success');
                            location.reload();
                        }
                        else if (jsonarray.result === 'failed')
                        {
                            alert(jsonarray.error);
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                    }

                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    jQuery('#wpvivid_optimized_imgae_list').find('.action').prop('disabled', false);
                    var error_message = wpvivid_output_ajaxerror('restore image', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            function wpvivid_get_opt_list(page)
            {
                var ajax_data = {
                    'action': 'wpvivid_get_opt_list',
                    'page':page,
                };

                wpvivid_post_request(ajax_data, function (data)
                {
                    jQuery('#wpvivid_optimized_imgae_list').html('');
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === 'success')
                        {
                            jQuery('#wpvivid_optimized_imgae_list').html(jsonarray.html);
                        }
                        else
                        {
                            alert(jsonarray.error);
                        }
                    }
                    catch (err)
                    {
                        alert(err);
                    }
                }, function (XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('get list', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            jQuery('#wpvivid_optimized_imgae_list').on("click",'.first-page',function()
            {
                wpvivid_get_opt_list('first');
            });

            jQuery('#wpvivid_optimized_imgae_list').on("click",'.prev-page',function()
            {
                var page=parseInt(jQuery(this).attr('value'));
                wpvivid_get_opt_list(page-1);
            });

            jQuery('#wpvivid_optimized_imgae_list').on("click",'.next-page',function()
            {
                var page=parseInt(jQuery(this).attr('value'));
                wpvivid_get_opt_list(page+1);
            });

            jQuery('#wpvivid_optimized_imgae_list').on("click",'.last-page',function()
            {
                wpvivid_get_opt_list('last');
            });

            jQuery('#wpvivid_optimized_imgae_list').on("keypress", '.current-page', function()
            {
                if(event.keyCode === 13){
                    var page = jQuery(this).val();
                    wpvivid_get_opt_list(page);
                }
            });
        </script>
        <?php
    }

    public function get_optimized_list()
    {
        global $wpdb;

        $query=$wpdb->prepare("SELECT `post_id` FROM $wpdb->postmeta WHERE `meta_key`=%s",array('wpvivid_image_optimize_meta'));

        $ids = $wpdb->get_col( $query );

        if(count($ids)>0)
        {
            $list=array();
            foreach ( $ids as $id )
            {
                $meta=get_post_meta($id,'wpvivid_image_optimize_meta',true);
                if($this->has_optimized_image($meta))
                {
                    $meta['id']=$id;
                    $list[$id]=$meta;
                }

            }
            usort($list, function ($a, $b)
            {
                if(isset($a['last_update_time'])&&isset($b['last_update_time']))
                {
                    if($a['last_update_time']>$b['last_update_time'])
                        return -1;
                    else if($a['last_update_time']<$b['last_update_time'])
                        return 1;
                    else
                        return 0;
                }
                else if(isset($a['last_update_time'])&&!isset($b['last_update_time']))
                {
                    return -1;
                }
                else if(!isset($a['last_update_time'])&&isset($b['last_update_time']))
                {
                    return 1;
                }
                else
                {
                    return 0;
                }
            });
            return $list;
        }
        else
        {
            return array();
        }
    }

    public function has_optimized_image($meta)
    {
        $options=get_option('wpvivid_optimization_options',array());

        $status=true;
        foreach ($meta['size'] as $size_key=>$size)
        {
            if(isset($options['skip_size'])&&isset($options['skip_size'][$size_key]))
            {
                if($options['skip_size'][$size_key])
                    continue;
            }
            if($size['opt_status']==1)
            {
                $status=true;
            }
            else
            {
                $status=false;
            }
        }

        if($status)
        {
            if($meta['sum']['og_size']==0)
            {
                return false;
            }
            else
            {
                return true;
            }
        }
        else
        {
            return false;
        }
    }

    public function error_log()
    {
        $loglist=$this->get_log_list();
        ?>

        <textarea id="wpvivid_read_optimize_log_content" class="wpvivid-one-coloum wpvivid-workflow" style="width:100%; height:300px; overflow-x:auto;">
        </textarea>
        <div style="clear:both;"></div>
        <div style="margin-top: 10px" class="wpvivid-log-list" id="wpvivid_optimize_log">
            <?php
            $css_type = 'margin: 0 0 0 0';
            $class='top-action';
            ?>
            <div class="tablenav <?php echo esc_attr( 'top' ); ?>" style="<?php echo esc_attr($css_type); ?>">
                <div class="alignleft actions bulkactions">
                    <label for="wpvivid_uc_bulk_action" class="screen-reader-text"><?php esc_html_e('Select bulk action', 'wpvivid-imgoptim');?></label>
                    <select name="action" id="wpvivid_image_opt_log_month">
                        <option value="-1"><?php esc_html_e('Month', 'wpvivid-imgoptim');?></option>
                        <?php
                        if(!empty($loglist['month']))
                        {
                            foreach ($loglist['month'] as $date)
                            {
                                echo '<option value="'.esc_attr($date).'">'.esc_html($date).'</option>';
                            }
                        }
                        ?>
                    </select>
                    <select name="action" id="wpvivid_image_opt_log_day">
                        <option value="-1"><?php esc_html_e('Day', 'wpvivid-imgoptim');?></option>
                        <?php
                        if(!empty($loglist['day']))
                        {
                            foreach ($loglist['day'] as $date)
                            {
                                echo '<option value="'.esc_attr($date).'">'.esc_html($date).'</option>';
                            }
                        }
                        ?>
                    </select>
                    <select name="action" id="wpvivid_image_opt_log_year">
                        <option value="-1"><?php esc_html_e('Year', 'wpvivid-imgoptim');?></option>
                        <?php
                        if(!empty($loglist['year']))
                        {
                            foreach ($loglist['year'] as $date)
                            {
                                echo '<option value="'.esc_attr($date).'">'.esc_html($date).'</option>';
                            }
                        }
                        ?>
                    </select>
                    <input type="submit" class="button action top-action search" value="Search Logs">
                    <input type="submit" class="button action top-action empty" value="Empty Logs">
                </div>
                <br class="clear" />
            </div>
        </div>

        <script>

            jQuery('#wpvivid_optimize_log').on("click",'.search',function()
            {
                var month=jQuery('#wpvivid_image_opt_log_month').val();
                var day=jQuery('#wpvivid_image_opt_log_day').val();
                var year=jQuery('#wpvivid_image_opt_log_year').val();

                if(month!=-1||day!=-1||year!=-1)
                {
                    wpvivid_open_optimize_date_log(year,month,day);
                }

            });

            jQuery('#wpvivid_optimize_log').on("click",'.empty',function()
            {
                var r=confirm("Are you sure you want to delete all logs");
                if (r==true)
                {
                    wpvivid_empty_optimize_log();
                }
            });

            jQuery('#wpvivid_image_optimize_progress').on("click",'#wpvivid_image_open_log',function()
            {
                wpvivid_open_progressing_optimize_log();
            });

            function wpvivid_empty_optimize_log()
            {
                var ajax_data = {
                    'action':'wpvivid_empty_optimize_log',
                };

                wpvivid_post_request(ajax_data, function(data)
                {
                    jQuery('#wpvivid_read_optimize_log_content').html("");
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === "success")
                        {
                        }
                        else
                        {
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        var div = "Reading the log failed. Please try again.";
                        jQuery('#wpvivid_read_optimize_log_content').html(div);
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('export the previously-exported settings', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            function wpvivid_open_optimize_date_log(year,month,day)
            {
                var ajax_data = {
                    'action':'wpvivid_view_optimize_log_ex',
                    'year': year,
                    'month': month,
                    'day': day
                };

                wpvivid_post_request(ajax_data, function(data)
                {
                    jQuery('#wpvivid_read_optimize_log_content').html("");
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === "success")
                        {
                            jQuery('#wpvivid_read_optimize_log_content').html(jsonarray.data);
                        }
                        else
                        {
                            jQuery('#wpvivid_read_optimize_log_content').html(jsonarray.error);
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        var div = "Reading the log failed. Please try again.";
                        jQuery('#wpvivid_read_optimize_log_content').html(div);
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('export the previously-exported settings', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            function wpvivid_open_optimize_log(log)
            {
                var ajax_data = {
                    'action':'wpvivid_view_optimize_log_ex',
                    'log': log
                };

                wpvivid_post_request(ajax_data, function(data)
                {
                    jQuery('#wpvivid_read_optimize_log_content').html("");
                    try
                    {
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === "success")
                        {
                            jQuery('#wpvivid_read_optimize_log_content').html(jsonarray.data);
                        }
                        else
                        {
                            jQuery('#wpvivid_read_optimize_log_content').html(jsonarray.error);
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        var div = "Reading the log failed. Please try again.";
                        jQuery('#wpvivid_read_optimize_log_content').html(div);
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('export the previously-exported settings', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            function wpvivid_open_progressing_optimize_log()
            {
                var ajax_data = {
                    'action':'wpvivid_open_progressing_optimize_log',
                };

                wpvivid_post_request(ajax_data, function(data)
                {
                    jQuery('#wpvivid_read_optimize_log_content').html("");
                    try
                    {
                        jQuery( document ).trigger( '<?php echo esc_js($this->main_tab->container_id) ?>-show','log');
                        var jsonarray = jQuery.parseJSON(data);
                        if (jsonarray.result === "success")
                        {
                            jQuery('#wpvivid_read_optimize_log_content').html(jsonarray.data);
                        }
                        else
                        {
                            jQuery('#wpvivid_read_optimize_log_content').html(jsonarray.error);
                        }
                    }
                    catch(err)
                    {
                        alert(err);
                        var div = "Reading the log failed. Please try again.";
                        jQuery('#wpvivid_read_optimize_log_content').html(div);
                    }
                }, function(XMLHttpRequest, textStatus, errorThrown)
                {
                    var error_message = wpvivid_output_ajaxerror('export the previously-exported settings', textStatus, errorThrown);
                    alert(error_message);
                });
            }

            jQuery(document).ready(function($)
            {
                <?php
                if(!empty($loglist['file']))
                {
                    $file=array_shift($loglist['file']);
                    ?>
                    wpvivid_open_optimize_log('<?php echo esc_js($file['file_name'])?>');
                    <?php
                }
                ?>
            });
        </script>
        <?php

    }

    public function get_log_list($date='')
    {
        $ret['file']=array();
        $ret['date']=array();
        $ret['month']=array();
        $ret['day']=array();
        $ret['year']=array();

        $log=new WPvivid_Image_Optimize_Log();
        $dir=$log->GetSaveLogFolder();
        $files=array();
        $regex='#^wpvivid.*_log.txt#';

        $dir.=DIRECTORY_SEPARATOR;

        if(file_exists($dir))
        {
            $handler=opendir($dir);
            if($handler!==false)
            {
                while(($filename=readdir($handler))!==false)
                {
                    if($filename != "." && $filename != "..")
                    {
                        if(is_dir($dir.$filename))
                        {
                            continue;
                        }
                        else {
                            if(preg_match($regex,$filename))
                            {
                                $files[$filename] = $dir.$filename;
                            }
                        }
                    }
                }
                if($handler)
                    @closedir($handler);
            }
        }


        foreach ($files as $file)
        {
            $log_file=array();
            $log_file['file_name']=basename($file);
            $log_file['path']=$file;
            $log_file['time']=preg_replace('/[^0-9]/', '', basename($file));

            $offset=get_option('gmt_offset');
            $localtime = strtotime($log_file['time']) + $offset * 60 * 60;
            $year =gmdate('Y',$localtime);
            $month =gmdate('m',$localtime);
            $day =gmdate('d',$localtime);

            $ret['month'][$month]=$month;
            $ret['day'][$day]=$day;
            $ret['year'][$year]=$year;
            if(!empty($date))
            {
                if($date==preg_replace('/[^0-9]/', '', basename($file)))
                {
                    $ret['date'][]=preg_replace('/[^0-9]/', '', basename($file));
                    $ret['file'][$log_file['file_name']]=$log_file;
                }
            }
            else
            {
                $ret['date'][]=preg_replace('/[^0-9]/', '', basename($file));
                $ret['file'][$log_file['file_name']]=$log_file;
            }

        }

        $ret['file'] =$this->sort_list($ret['file']);

        return $ret;
    }

    public function get_log_list_date($year,$month,$day)
    {
        $ret['file']=array();
        $ret['date']=array();

        $log=new WPvivid_Image_Optimize_Log();
        $dir=$log->GetSaveLogFolder();
        $files=array();
        $regex='#^wpvivid.*_log.txt#';

        $dir.=DIRECTORY_SEPARATOR;

        if(file_exists($dir))
        {
            $handler=opendir($dir);
            if($handler!==false)
            {
                while(($filename=readdir($handler))!==false)
                {
                    if($filename != "." && $filename != "..")
                    {
                        if(is_dir($dir.$filename))
                        {
                            continue;
                        }
                        else {
                            if(preg_match($regex,$filename))
                            {
                                $files[$filename] = $dir.$filename;
                            }
                        }
                    }
                }
                if($handler)
                    @closedir($handler);
            }
        }


        foreach ($files as $file)
        {
            $log_file=array();
            $log_file['file_name']=basename($file);
            $log_file['path']=$file;
            $log_file['time']=preg_replace('/[^0-9]/', '', basename($file));

            $offset=get_option('gmt_offset');
            $localtime = strtotime($log_file['time']) + $offset * 60 * 60;
            $log_year =gmdate('Y',$localtime);
            $log_month =gmdate('m',$localtime);
            $log_day =gmdate('d',$localtime);

            if($log_year==$year&&$log_month==$month&&$log_day==$day)
            {
                $ret['file'][$log_file['file_name']]=$log_file;
            }

        }

        $ret['file'] =$this->sort_list($ret['file']);

        return $ret;
    }

    public function sort_list($list)
    {
        uasort ($list,function($a, $b)
        {
            if($a['time']>$b['time'])
            {
                return -1;
            }
            else if($a['time']===$b['time'])
            {
                return 0;
            }
            else
            {
                return 1;
            }
        });

        return $list;
    }

    public function view_log_ex()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }
        try
        {
            if (isset($_POST['date']) && !empty($_POST['date']) && is_string($_POST['date']))
            {
                $date = sanitize_text_field($_POST['date']);
                $loglist=$this->get_log_list($date);
                $log=array_shift($loglist['file']);
                $path=$log['path'];

                if (!file_exists($path))
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid-imgoptim').$path;
                    echo wp_json_encode($json);
                    die();
                }

                $file = fopen($path, 'r');

                if (!$file) {
                    $json['result'] = 'failed';
                    $json['error'] = __('Unable to open the log file.', 'wpvivid-imgoptim');
                    echo wp_json_encode($json);
                    die();
                }

                $offset=get_option('gmt_offset');
                $localtime = strtotime($log['time']) + $offset * 60 * 60;
                $buffer = 'Open log file created:'.gmdate('Y-m-d',$localtime).' '.PHP_EOL;
                /*while (!feof($file)) {
                    $buffer .= fread($file, 1024);
                }*/
                if(filesize($path)<=1024*1024)
                {
                    while(!feof($file))
                    {
                        $buffer .= fread($file,1024);
                    }
                }
                else
                {
                    $pos=-2;
                    $eof='';
                    $n=2000;
                    $buffer_array = array();
                    while($n>0)
                    {
                        while($eof!=="\n")
                        {
                            if(!fseek($file, $pos, SEEK_END))
                            {
                                $eof=fgetc($file);
                                $pos--;
                            }
                            else
                            {
                                break;
                            }
                        }
                        $buffer_array[].=fgets($file);
                        $eof='';
                        $n--;
                    }

                    if(!empty($buffer_array))
                    {
                        $buffer_array = array_reverse($buffer_array);
                        foreach($buffer_array as $value)
                        {
                            $buffer.=$value;
                        }
                    }
                }
                fclose($file);

                $json['result'] = 'success';
                $json['data'] = $buffer;
                echo wp_json_encode($json);
            }
            else if (isset($_POST['year']) && isset($_POST['month']) && isset($_POST['day']))
            {
                $year = sanitize_text_field($_POST['year']);
                $month = sanitize_text_field($_POST['month']);
                $day = sanitize_text_field($_POST['day']);
                $loglist=$this->get_log_list_date($year,$month,$day);
                if(empty($loglist['file']))
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid');
                    $json['test']=$year.$month.$day;
                    echo wp_json_encode($json);
                    die();
                }
                $log=array_shift($loglist['file']);
                $path=$log['path'];

                if (!file_exists($path))
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid').$path;
                    echo wp_json_encode($json);
                    die();
                }

                $file = fopen($path, 'r');

                if (!$file) {
                    $json['result'] = 'failed';
                    $json['error'] = __('Unable to open the log file.', 'wpvivid');
                    echo wp_json_encode($json);
                    die();
                }

                $offset=get_option('gmt_offset');
                $localtime = strtotime($log['time']) + $offset * 60 * 60;
                $buffer = 'Open log file created:'.gmdate('Y-m-d',$localtime).' '.PHP_EOL;
                /*while (!feof($file)) {
                    $buffer .= fread($file, 1024);
                }*/
                if(filesize($path)<=1024*1024)
                {
                    while(!feof($file))
                    {
                        $buffer .= fread($file,1024);
                    }
                }
                else
                {
                    $pos=-2;
                    $eof='';
                    $n=2000;
                    $buffer_array = array();
                    while($n>0)
                    {
                        while($eof!=="\n")
                        {
                            if(!fseek($file, $pos, SEEK_END))
                            {
                                $eof=fgetc($file);
                                $pos--;
                            }
                            else
                            {
                                break;
                            }
                        }
                        $buffer_array[].=fgets($file);
                        $eof='';
                        $n--;
                    }

                    if(!empty($buffer_array))
                    {
                        $buffer_array = array_reverse($buffer_array);
                        foreach($buffer_array as $value)
                        {
                            $buffer.=$value;
                        }
                    }
                }
                fclose($file);

                $json['result'] = 'success';
                $json['data'] = $buffer;
                echo wp_json_encode($json);
            }
            else if (isset($_POST['log']) && !empty($_POST['log']) && is_string($_POST['log']))
            {
                $log = sanitize_text_field($_POST['log']);
                $loglist=$this->get_log_list();

                if(isset($loglist['file'][$log]))
                {
                    $log=$loglist['file'][$log];
                }
                else
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid');
                    echo wp_json_encode($json);
                    die();
                }

                $path=$log['path'];

                if (!file_exists($path))
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid').$path;
                    echo wp_json_encode($json);
                    die();
                }

                $file = fopen($path, 'r');

                if (!$file) {
                    $json['result'] = 'failed';
                    $json['error'] = __('Unable to open the log file.', 'wpvivid');
                    echo wp_json_encode($json);
                    die();
                }

                $offset=get_option('gmt_offset');
                $localtime = strtotime($log['time']) + $offset * 60 * 60;
                $buffer = 'Open log file created:'.gmdate('Y-m-d',$localtime).' '.PHP_EOL;
                /*while (!feof($file)) {
                    $buffer .= fread($file, 1024);
                }*/
                if(filesize($path)<=1024*1024)
                {
                    while(!feof($file))
                    {
                        $buffer .= fread($file,1024);
                    }
                }
                else
                {
                    $pos=-2;
                    $eof='';
                    $n=2000;
                    $buffer_array = array();
                    while($n>0)
                    {
                        while($eof!=="\n")
                        {
                            if(!fseek($file, $pos, SEEK_END))
                            {
                                $eof=fgetc($file);
                                $pos--;
                            }
                            else
                            {
                                break;
                            }
                        }
                        $buffer_array[].=fgets($file);
                        $eof='';
                        $n--;
                    }

                    if(!empty($buffer_array))
                    {
                        $buffer_array = array_reverse($buffer_array);
                        foreach($buffer_array as $value)
                        {
                            $buffer.=$value;
                        }
                    }
                }
                fclose($file);

                $json['result'] = 'success';
                $json['data'] = $buffer;
                echo wp_json_encode($json);
            } else {
                $json['result'] = 'failed';
                $json['error'] = __('Reading the log failed. Please try again.', 'wpvivid');
                echo wp_json_encode($json);
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function empty_optimize_log()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }
        try
        {
            $log=new WPvivid_Image_Optimize_Log();
            $dir=$log->GetSaveLogFolder();
            $regex='#^wpvivid.*_log.txt#';

            $dir.=DIRECTORY_SEPARATOR;

            if(file_exists($dir))
            {
                $handler=opendir($dir);
                if($handler!==false)
                {
                    while(($filename=readdir($handler))!==false)
                    {
                        if($filename != "." && $filename != "..")
                        {
                            if(is_dir($dir.$filename))
                            {
                                continue;
                            }
                            else {
                                if(preg_match($regex,$filename))
                                {
                                    @wp_delete_file($dir.$filename);
                                }
                            }
                        }
                    }
                    if($handler)
                        @closedir($handler);
                }
            }

            $json['result'] = 'success';
            echo wp_json_encode($json);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function open_progressing_optimize_log()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }
        try
        {
            $loglist=$this->get_log_list();
            if(!empty($loglist['file']))
            {
                $log=array_shift($loglist['file']);
                $path=$log['path'];

                if (!file_exists($path))
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid').$path;
                    echo wp_json_encode($json);
                    die();
                }

                $file = fopen($path, 'r');

                if (!$file) {
                    $json['result'] = 'failed';
                    $json['error'] = __('Unable to open the log file.', 'wpvivid');
                    echo wp_json_encode($json);
                    die();
                }

                $offset=get_option('gmt_offset');
                $localtime = strtotime($log['time']) + $offset * 60 * 60;
                $buffer = 'Open log file created:'.gmdate('Y-m-d',$localtime).' '.PHP_EOL;
                /*while (!feof($file)) {
                    $buffer .= fread($file, 1024);
                }*/
                if(filesize($path)<=1024*1024)
                {
                    while(!feof($file))
                    {
                        $buffer .= fread($file,1024);
                    }
                }
                else
                {
                    $pos=-2;
                    $eof='';
                    $n=2000;
                    $buffer_array = array();
                    while($n>0)
                    {
                        while($eof!=="\n")
                        {
                            if(!fseek($file, $pos, SEEK_END))
                            {
                                $eof=fgetc($file);
                                $pos--;
                            }
                            else
                            {
                                break;
                            }
                        }
                        $buffer_array[].=fgets($file);
                        $eof='';
                        $n--;
                    }

                    if(!empty($buffer_array))
                    {
                        $buffer_array = array_reverse($buffer_array);
                        foreach($buffer_array as $value)
                        {
                            $buffer.=$value;
                        }
                    }
                }
                fclose($file);

                $json['result'] = 'success';
                $json['data'] = $buffer;
                echo wp_json_encode($json);
                die();
            }
            else
            {
                $json['result'] = 'failed';
                $json['error'] = __('The log not found.', 'wpvivid');
                echo wp_json_encode($json);
                die();
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function get_opt_progress()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }

        $task=new WPvivid_ImgOptim_Task();

        $result=$task->get_task_progress();

        $result['progress_html']='
                <p>
                    <span>
                        <strong>'.__('Bulk Optimization Progress','wpvivid-imgoptim').'</strong>
                    </span>
                    <span style="float:right;">
                        <span class="wpvivid-rectangle wpvivid-green">'.__('Optimized','wpvivid-imgoptim').'</span>
                        <span class="wpvivid-rectangle wpvivid-grey">'.__('Un-optimized','wpvivid-imgoptim').'</span>
                    </span>
                </p>            
                <p>
                    <span class="wpvivid-span-progress">
                        <span class="wpvivid-span-processed-progress" style="width:'.$result['percent'].'%;">'.$result['percent'].'% '.__('completed','wpvivid-imgoptim').'</span>
                    </span>
                </p>
                <p>
                    <span class="dashicons dashicons-flag wpvivid-dashicons-green"></span><span><strong>Processing: </strong></span>
					<span style="color:#999;">'.$result['log'].'</span>
					<span title="View logs"><a id="wpvivid_image_open_log" href="#">logs</a></span>
				</p>
                ';

        echo wp_json_encode($result);

        die();
    }

    public function get_server_status()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }

        $url='admin.php?page=wpvivid-imgoptim-license';

        $info= get_option('wpvivid_imgoptim_user',false);
        if($info===false)
        {
            $ret['result']='success';
            $ret['server_name']='<a href="'.$url.'">'.__('please log in first','wpvivid-imgoptim').'</a>';
            $ret['total']=0;
            $ret['remain']=0;
            echo wp_json_encode($ret);
            die();
        }

        $user_info=$info['token'];

        include_once WPVIVID_IMGOPTIM_DIR. '/includes/class-wpvivid-imgoptim-connect-server.php';

        $task=new WPvivid_Image_Optimize_Connect_server();
        $ret=$task->get_image_optimization_status($user_info);

        if($ret['result']=='success')
        {
            $options=$ret['status'];
            $options['time']=time();
            update_option('wpvivid_server_cache',$options);
        }
        else {
            delete_option('wpvivid_server_cache');
        }

        echo wp_json_encode($ret);
        die();
    }

    public function get_opt_list()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }

        try
        {
            $result=$this->get_optimized_list();

            $list = new WPvivid_Optimized_Image_List();
            if(isset($_POST['page']))
            {
                $page=sanitize_key($_POST['page']);
                $list->set_list($result,$page);
            }
            else
            {
                $list->set_list($result);
            }
            $list->prepare_items();
            ob_start();
            $list->display();
            $html = ob_get_clean();

            $ret['result']='success';
            $ret['html']=$html;
            echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function init_opt_task()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }
        global $wpvivid_imgoptim;
        ini_set('memory_limit','512M');

        $options=get_option('wpvivid_optimization_options',array());

        set_time_limit(120);
        $task=new WPvivid_ImgOptim_Task();

        if(isset($_POST['resize']))
        {
            if($_POST['resize']=='1')
                $options['only_resize']=1;
            else
                $options['only_resize']=0;
        }

        $need_optimize_array=array();
        $need_optimize_array['images']=array();
        $need_optimize_array['offset']=0;
        update_option('wpvivid_get_need_optimize_images', $need_optimize_array);

        $ret=$task->init_task($options);
        if(isset($ret['continue']) && $ret['continue'] === 1)
        {
            $ret['result']='success';
            $ret['continue']=1;
            echo wp_json_encode($ret);
        }
        else
        {
            $wpvivid_imgoptim->flush($ret);
            if($ret['result']=='success')
            {
                $task->do_optimize_image();
            }
        }
        die();
    }

    public function start_opt_task()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }
        global $wpvivid_imgoptim;

        $options=get_option('wpvivid_optimization_options',array());

        set_time_limit(120);
        $task=new WPvivid_ImgOptim_Task();

        if(isset($_POST['resize']))
        {
            if($_POST['resize']=='1')
                $options['only_resize']=1;
            else
                $options['only_resize']=0;
        }

        $ret=$task->init_task($options);

        if(isset($ret['continue']) && $ret['continue'] === 1)
        {
            $ret['result']='success';
            $ret['continue']=1;
            echo wp_json_encode($ret);
        }
        else
        {
            $wpvivid_imgoptim->flush($ret);

            if($ret['result']=='success')
            {
                $task->do_optimize_image();
            }
        }
        die();
    }

    public function cancel_opt_task()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }

        set_time_limit(120);

        $task=new WPvivid_ImgOptim_Task();

        $task->cancel();

        die();
    }

    public function opt_image()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }
        global $wpvivid_imgoptim;

        set_time_limit(120);

        $task=new WPvivid_ImgOptim_Task();

        $ret=$task->get_task_status();

        $wpvivid_imgoptim->flush($ret);

        if($ret['result']=='success'&&$ret['status']=='completed')
        {
            $task->do_optimize_image();
        }

        die();
    }

    public function restore_image()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }

        if(!isset($_POST['selected'])||!is_string($_POST['selected']))
        {
            die();
        }

        try
        {
            $json = sanitize_text_field($_POST['selected']);
            $json = stripslashes($json);
            $json = json_decode($json, true);

            $ids=$json['selected'];

            $task=new WPvivid_ImgOptim_Task();

            foreach ($ids as $id)
            {
                $task->restore_image($id);
            }

            $ret['result']='success';

            echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }

    public function restore_all_image()
    {
        check_ajax_referer( 'wpvivid_ajax', 'nonce' );
        $check=is_admin()&&current_user_can('administrator');
        $check=apply_filters('wpvivid_ajax_check_security',$check);
        if(!$check)
        {
            die();
        }

        try
        {
            $task=new WPvivid_ImgOptim_Task();

            $list=$this->get_optimized_list();
            if(!empty($list))
            {
                foreach ($list as $meta)
                {
                    $task->restore_image($meta['id']);
                }
            }

            $ret['result']='success';

            echo wp_json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo wp_json_encode(array('result'=>'failed','error'=>$message));
        }
        die();
    }
}