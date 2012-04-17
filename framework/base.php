<?php
class DKOWPPlugin
{
  protected $paths = array(
    'css' => 'css',
    'js'  => 'js'
  );
  protected $assets = array(
    'admin'   => array('css' => array(), 'js' => array()),
    'plugin'  => array('css' => array(), 'js' => array()),
  );

  protected $plugin_dirpath = '';
  protected $plugin_relpath = '';
  protected $plugin_abspath = '';

  /**
   * constructor
   */
  function __construct($childfile = __FILE__) {
    global $wpdb;
    $this->add_ajax_actions();
    $this->wpdb = $wpdb;

    $this->plugin_folder = basename(dirname($childfile));
    $this->plugin_dirpath = plugin_dir_path($childfile);
    $this->plugin_relpath = WP_PLUGIN_DIR . '/' . $this->plugin_folder;
    $this->plugin_abspath = plugin_dir_url($childfile);
    $this->paths['css'] = $this->plugin_abspath . '/css/';
    $this->paths['js']  = $this->plugin_abspath . '/js/';

    /* define plugin as loaded */
    add_action('wp_loaded', array(&$this, 'create_nonce'));

    /* enqueue the scripts and styles */
    add_action('admin_enqueue_scripts', array(&$this, 'enqueue_admin_stuff'));
    add_action('wp_enqueue_scripts', array(&$this, 'enqueue_plugin_stuff'));
  }

  /**
   * see http://codex.wordpress.org/WordPress_Nonces
   */
  function create_nonce() {
    $this->nonce = wp_create_nonce('DKOWPPlugin-nonce');
  }

  /**
   */
  function enqueue_admin_stuff() {
    $this->enqueue_stuff('admin');
  }

  /**
   */
  function enqueue_plugin_stuff() {
    $this->enqueue_stuff('plugin');
  }

  /**
   * enqueue scripts and styles
   * @param string $context array key to use in $this->assets array
   */
  function enqueue_stuff($context) {
    foreach ($this->assets[$context]['css'] as $css) {
      $css_file = $this->paths['css'] . $css . '.css';
      wp_enqueue_style($css, $css_file);
    }
    foreach ($this->assets[$context]['js'] as $js) {
      // dependencies were specified
      if (is_array($js)) {
        $js_file = $this->paths['js'] . array_shift($js) . '.js';
        $deps = array_unshift($js, 'jquery');
      }
      // no dependencies were specified, but assume jquery
      else {
        $js_file = $this->paths['js'] . $js . '.js';
        $deps = array('jquery');
      }
      $deps = array('jquery');
      wp_enqueue_script($js, $js_file, $deps);
    }
  }

  /**
   */
  function add_ajax_actions() {
    if (!empty($this->ajax_actions['admin'])) {
      foreach ($this->ajax_actions['admin'] as $action) {
        add_action("wp_ajax_$action", array(&$this, $action));
      }
    }
    if (!empty($this->ajax_actions['plugin'])) {
      foreach ($this->ajax_actions['plugin'] as $action) {
        add_action("wp_ajax_nopriv_$action", array(&$this, $action));
      }
    }
  }

  /**
   * @param string $view name of template file to load from views folder
   */
  function render($view, $data = array()) {
    $template_path = $this->plugin_relpath . '/views/' . $view . '.php';
    $output = '<strong>' . $template_path . ' not found</strong>';
    if (file_exists($template_path)) {
      ob_start();
      include $template_path;
      $output = ob_get_clean();
    }
    return $output;
  }

} /* end of class */
