<?php


include_once('FablabLogbook_LifeCycle.php');
require_once('TwitterAPIExchange.php');

class FablabLogbook_Plugin extends FablabLogbook_LifeCycle {

    /**
     * See: http://plugin.michael-simpson.com/?page_id=31
     * @return array of option meta data.
     */
    public function getOptionMetaData() {
        //  http://plugin.michael-simpson.com/?page_id=31
        return array(
            'TwitterMsg' => array('Twitter status message on log'),
            'TWITTER_OAUTH_ACCESS_TOKEN' => array('TWITTER_OAUTH_ACCESS_TOKEN'),
            'TWITTER_OAUTH_ACCESS_TOKEN_SECRET' => array('TWITTER_OAUTH_ACCESS_TOKEN_SECRET'),
            'TWITTER_CONSUMER_KEY' => array('TWITTER_CONSUMER_KEY'),
            'TWITTER_CONSUMER_SECRET' => array('TWITTER_CONSUMER_SECRET'),
            //'_version' => array('Installed Version'), // Leave this one commented-out. Uncomment to test upgrades.
            //'_installed' => array('Installed'),
            'DropOnUninstall' => array(__('Drop this plugin\'s Database table on uninstall', 'TEXT_DOMAIN'), 'false', 'true')
        );
    }

//    protected function getOptionValueI18nString($optionValue) {
//        $i18nValue = parent::getOptionValueI18nString($optionValue);
//        return $i18nValue;
//    }

    protected function initOptions() {
        $options = $this->getOptionMetaData();
        if (!empty($options)) {
            foreach ($options as $key => $arr) {
                if (is_array($arr) && count($arr > 1)) {
                    $this->addOption($key, $arr[1]);
                }
            }
        }
    }

    public function getPluginDisplayName() {
        return 'FabLab-Logbook';
    }

    protected function getMainPluginFileName() {
        return 'fablab-logbook.php';
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Called by install() to create any database tables if needed.
     * Best Practice:
     * (1) Prefix all table names with $wpdb->prefix
     * (2) make table names lower case only
     * @return void
     */
    protected function installDatabaseTables() {
      global $wpdb;
      $charset_collate = $wpdb->get_charset_collate();
      
      $tableName = $this->prefixTableName('token');
      $wpdb->query("CREATE TABLE IF NOT EXISTS `$tableName` (
                    `token` BIGINT(20) NOT NULL,
                    `user_id` BIGINT(20) NOT NULL,
                    `description` VARCHAR(255) NULL,
                    PRIMARY KEY (`token`)                  
                  ) $charset_collate;");
                  
      $tableName = $this->prefixTableName('logbook');
      $wpdb->query("CREATE TABLE IF NOT EXISTS `$tableName` (
                  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
                  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `token` BIGINT(20) NOT NULL,
                  `user_id` BIGINT(20) NULL,
                  `publish` TINYINT NULL,
                  `flag2` TINYINT NULL,
                  PRIMARY KEY (`id`)
                  ) $charset_collate;");
    }

    /**
     * See: http://plugin.michael-simpson.com/?page_id=101
     * Drop plugin-created tables on uninstall.
     * @return void
     */
    protected function unInstallDatabaseTables() {
      if ('true' === $this->getOption('DropOnUninstall', 'false')) {
        global $wpdb;
        $tableName = $this->prefixTableName('token');
        $wpdb->query("DROP TABLE IF EXISTS `$tableName`");
        $tableName = $this->prefixTableName('logbook');
        $wpdb->query("DROP TABLE IF EXISTS `$tableName`");
      }
    }


    /**
     * Perform actions when upgrading from version X to version Y
     * See: http://plugin.michael-simpson.com/?page_id=35
     * @return void
     */
    public function upgrade() {
    }
    
    public function addAdminMenu() {
        $this->requireExtraPluginFiles();
        $displayName = $this->getPluginDisplayName();
        add_menu_page(   'FabLab',
                         'FabLab',
                         'manage_options',
                         'FabLab',
                         array(&$this, 'logbookPage')
                         );
        add_submenu_page('FabLab',
                         'Logbook',
                         'Logbook',
                         'manage_options',
                         'FabLab',
                         array(&$this, 'logbookPage'));
        add_submenu_page('FabLab',
                         'Token',
                         'Token',
                         'manage_options',
                         'FabLab_Token',
                         array(&$this, 'tokenPage'));      
        add_submenu_page('FabLab',
                         'Options',
                         'Options',
                         'manage_options',
                         'FabLab_Options',
                         array(&$this, 'settingsPage'));                         
    }
    
    public function logbookPage(){
        global $wpdb;
        $tableName = $this->prefixTableName('logbook');
        
      ?>
<div class="wrap">
  <h2>Logbook</h2>
    <table class="wp-list-table widefat fixed striped books">
    <thead>
      <tr>
        <th scope="col" class="manage-column">Timestamp</th>
        <th scope="col" class="manage-column">LoggedUser / CurrentUser</th>
        <th scope="col" class="manage-column">Published</th>
      </tr>
    </thead>
    <tbody>
<?php    
      $res = $wpdb->get_results( 'SELECT l.*, u.display_name, u2.display_name AS current_display_name FROM ' . $tableName . ' l LEFT JOIN ' . 
             $wpdb->prefix . 'users u ON l.user_id = u.ID LEFT JOIN ' . $this->prefixTableName('token') . ' t ON l.token = t.token LEFT JOIN ' .
             $wpdb->prefix . 'users u2 ON u2.ID = t.user_id ORDER BY l.timestamp DESC', 'ARRAY_A' );
      foreach($res as $r){    
?>      
      <tr>
        <td><?php echo $r['timestamp']?></td>
        <td ><?php 
            if($r['user_id']==0){
              if($r['current_display_name']){
                 echo 'Unknown / ' . $r['current_display_name'];
              }else{
                echo '<a href="'. get_option('siteurl').'/wp-admin/admin.php?page=FabLab_Token&token='. $r['token'] .'" class="error">Unknown / Assign</a>';
              }
            } else {
              echo $r['display_name'] . ' / ' . $r['current_display_name'];
            }
            ?>
        </td>
        <td><?php if($r['publish']){echo '<span class="dashicons dashicons-twitter"></span>';}?></td>
      </tr>
<?php
      }
?>
  </table>
</div>
      <?php
    }
    
    public function tokenPage(){
      global $wpdb;
      $tableName = $this->prefixTableName('token');
      
      function displayResultMessage($res, $msg){
        global $wpdb;
        if($res){
          ?>
          <div class="updated"><p><?php echo $msg;?></p></div>
          <?php
        }else{
          ?>
          <div class="error"><p>Error: <?php echo $wpdb->print_error();?></p></div>
          <?php  
        }
      }
      
?>      
<div class="wrap">     
  <h2>Token</h2> 
<?php
      if($_POST){
        $res = $wpdb->insert($tableName,
          array( 
            'token' => $_POST['token_id'], 
            'user_id' => $_POST['user_id'], 
            'description' => $_POST['description']
            ), 
            array( 
            '%d', 
            '%d',
            '%s'
            )
        );
        displayResultMessage($res, 'Token added!');
      }else{
        //either add or delete not both.
        if(array_key_exists('delete', $_GET)){
          $res = $wpdb->delete($tableName, array( 'token' => $_GET['delete'] ), array( '%d' ));
          displayResultMessage($res, 'Token deleted!');
        }
      }
     
      ?>
  <div class="postbox"> 
    <form method="post" class="inside">
      <h3>Add new token</h3>
      <table class="form-table">
      <tr class="form-field form-required">
        <th scope="row"><label for="user_id">User <span class="description">(required)</span></label></th>
        <td><?php wp_dropdown_users(array('id'=>'user_id', 'name'=>'user_id')); ?></td>
      </tr>
      <tr class="form-field form-required">
        <th scope="row"><label for="token_id">Token id <span class="description">(required)</span></label></th>
        <td><input name="token_id" type="text" id="token_id" value="<?php echo array_key_exists('token', $_GET) ? $_GET['token'] : '';?>"></td>
      </tr>        
      <tr class="form-field">
        <th scope="row"><label for="description">Description </label></th>
        <td><input name="description" type="text" id="description" value=""></td>
      </tr>
      </table>
      <p class="submit">
        <input type="submit" name="submit" value="Add Token" class="button button-primary"/>
      </p>
    </form>
  </div>
  
  <table class="wp-list-table widefat fixed striped books">
    <thead>
      <tr>
        <th scope="col" class="manage-column">Token</th>
        <th scope="col" class="manage-column">User</th>
        <th scope="col" class="manage-column">Description</th>
      </tr>
    </thead>
    <tbody>
    <?php
    
      $res = $wpdb->get_results( 'SELECT t.*, u.display_name FROM ' . $tableName . ' t JOIN ' . $wpdb->prefix . 'users u ON t.user_id = u.ID ORDER BY u.display_name ASC', 'ARRAY_A' );
      foreach($res as $r){
      ?>
      <tr>
        <td><strong><?php echo $r['token'];?></strong>
          <div class="row-actions"><span class="delete"><a class="submitdelete" href="<?php echo add_query_arg(array('delete' => $r['token']))?>">Delete</a></div>
        </td>
        <td><?php echo $r['display_name'];?></td>
        <td><?php echo $r['description'];?></td>
      </tr>
      <?php
      }
      ?>
  </table>
  
</div>
    <?php
    }
    
    public function profileLogbookPage(){
        global $wpdb;
        $tokenTableName = $this->prefixTableName('token');
?>       
<h3>Token for this account</h3> 
  <table class="wp-list-table widefat fixed striped books">
    <thead>
      <tr>
        <th scope="col" class="manage-column">Token</th>
        <th scope="col" class="manage-column">Description</th>
      </tr>
    </thead>
    <tbody>
    <?php    
      $res = $wpdb->get_results( 'SELECT * FROM ' . $tokenTableName . ' WHERE user_id=' . get_current_user_id() . ' ORDER BY token ASC', 'ARRAY_A' );
      foreach($res as $r){
      ?>
      <tr>
        <td><strong><?php echo $r['token'];?></strong></td>
        <td><?php echo $r['description'];?></td>
      </tr>
      <?php
      }
      ?>
  </table>
<h3>Logs</h3>
    <table class="wp-list-table widefat fixed striped books">
    <thead>
      <tr>
        <th scope="col" class="manage-column">Timestamp</th>
        <th scope="col" class="manage-column">Token</th>
        <th scope="col" class="manage-column">Published</th>
      </tr>
    </thead>
    <tbody>
<?php    
      $tableName = $this->prefixTableName('logbook');
      $res = $wpdb->get_results( 'SELECT * FROM ' . $tableName . ' l LEFT JOIN '. $tokenTableName .' t ON t.token = l.token WHERE  l.user_id=' . get_current_user_id() . ' ORDER BY timestamp DESC', 'ARRAY_A' );
      foreach($res as $r){    
?>      
      <tr>
        <td><?php echo $r['timestamp']?></td>
        <td title="<?php echo $r['token']?>"><?php echo $r['description']?></td>
        <td><?php if($r['publish']){echo '<span class="dashicons dashicons-twitter"></span>';}?></td>
      </tr>
<?php
      }
?>
  </table>
  <?php
    }
    
    public function settingsPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fablab-logbook'));
        }
        
        
      ?>
      <?php
        if(array_key_exists('create', $_GET)){
          if($_GET['create'] == 'twitter'){  
            $id= xprofile_insert_field(array(
              'field_group_id' => 1,
              'type' => 'textbox',
              'name' => 'Twitter',
              'description' => 'Twitter username',
              'is_required' => false,
              'can_delete' => true,
            ));
            bp_xprofile_update_field_meta($id,'default_visibility','public');
            bp_xprofile_update_field_meta($id,'allow_custom_visibility','allowed');
          }
          if($_GET['create'] == 'webhook'){  
            $id= xprofile_insert_field(array(
              'field_group_id' => 1,
              'type' => 'textbox',
              'name' => 'Logbook-webhook',
              'description' => 'url that gets called on user\'s logbookentry',
              'is_required' => false,
              'can_delete' => true,
            ));
            bp_xprofile_update_field_meta($id,'default_visibility','adminsonly');
            bp_xprofile_update_field_meta($id,'allow_custom_visibility','disabled');
          }
        }
        ?>
      <div class="wrap">
        <h2>Buddypress Extended Fields</h2>
        <p>Twitter: <?php if(xprofile_get_field_id_from_name('Twitter')){ echo 'OK'; } else { ?>
          <a href="<?php echo add_query_arg(array('create' => 'twitter'))?>" class="button">create</a>
          
         <?php          
        }
        ?>
        </p>
        <p>Logbook-webhook: <?php if(xprofile_get_field_id_from_name('Logbook-webhook')){ echo 'OK'; } else { ?>
          <a href="<?php echo add_query_arg(array('create' => 'webhook'))?>" class="button">create</a>
          
         <?php          
        }
        ?>
        </p>
      </div>
      <?php
        parent::settingsPage();
    }
    
    private function publishLog($user, $twitter){
      $settings = array(
          'oauth_access_token' => $this->getOption('TWITTER_OAUTH_ACCESS_TOKEN'),
          'oauth_access_token_secret' => $this->getOption('TWITTER_OAUTH_ACCESS_TOKEN_SECRET'),
          'consumer_key' => $this->getOption('TWITTER_CONSUMER_KEY'),
          'consumer_secret' => $this->getOption('TWITTER_CONSUMER_SECRET')
      );
      $url = 'https://api.twitter.com/1.1/statuses/update.json';
      $requestMethod = 'POST';
      $msg = '';
      if($twitter){
          $msg='@' . $twitter;
      }else{
          $msg = $user->display_name;
      }
      $postfields = array('status' => $msg . ' ' . $this->getOption('TwitterMsg'));
      $twitter = new TwitterAPIExchange($settings);
      $twitter->buildOauth($url, $requestMethod)
        ->setPostfields($postfields)
        ->performRequest(true, array(CURLOPT_SSL_VERIFYPEER => false));
    }
    
    public function recordLogbookEntry(){
      global $wpdb;
      $tableName = $this->prefixTableName('token');
      // Don't let IE cache this request
      header("Pragma: no-cache");
      header("Cache-Control: no-cache, must-revalidate");
      header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
 
      header("Content-type: text/plain");
      //validate input
      if (is_numeric($_REQUEST['id']) && is_numeric($_REQUEST['a']) && is_numeric($_REQUEST['b'])){
        $token = $_REQUEST['id'];
        $publish = $_REQUEST['a'];
        $flag2 = $_REQUEST['b'];
      }else{
        die('Wrong Request');
      }

      //get_user
      $user_id = null;
      $res = $wpdb->get_row(sprintf('SELECT * FROM '. $tableName . ' WHERE token = %d', $token), 'ARRAY_A');
      if($res){
        $user_id = $res['user_id'];
        $user = get_userdata($user_id);
        $twitter = xprofile_get_field_data('Twitter', $user_id);
        //TODO: do in a thread?
        if($publish){
          $this->publishLog($user, $twitter);
        }
        $webhook = xprofile_get_field_data('Logbook-webhook', $user_id,  $multi_format = 'comma');
        if($webhook){
          //TODO: protect url
          set_error_handler(function() { /* ignore errors */ });
          file_get_contents($webhook);
          restore_error_handler();
        }
      }
      $tableName = $this->prefixTableName('logbook');
      //save log
      $res = $wpdb->insert($tableName,
          array( 
            'token' => $token, 
            'user_id' => $user_id,
            'publish' => $publish,
            'flag2' => $flag2
            ), 
            array( 
            '%d', 
            '%d',
            '%d',
            '%d'
            )
        );  
      if(!$res) die('error creating log');
      
      //reply      
      if ($user_id){
        echo 'Hello ' . $user_id; 
      }else{
        echo 'Carte Inconnue';
      }
      
      die();
    }
    
    public function enQueueScriptsAndStyles(){
      if (strpos($_SERVER['REQUEST_URI'], 'FabLab') !== false) {
          //wp_enqueue_script('my-script', plugins_url('/js/my-script.js', __FILE__));
          wp_enqueue_style('fablab-style', plugins_url('/css/style.css', __FILE__));
      }
    }

    public function profile_menu_logbook() {
      global $bp;
      bp_core_new_nav_item(array(
        'name' => 'Logbook',
        'slug' => 'logbook', 
        'screen_function' => array(&$this, 'profileLogbookPageAction'),
        'default_subnav_slug' => 'logbook_sub'
      ));       
    }
    
    public function profileLogbookPageAction(){
      add_action( 'bp_template_content', array(&$this, 'profileLogbookPage') );
      bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
    }
    

    
    public function addActionsAndFilters() {

        // Add options administration page
        // http://plugin.michael-simpson.com/?page_id=47
        add_action('admin_menu', array(&$this, 'addAdminMenu'));
        add_action('admin_enqueue_scripts', array(&$this, 'enQueueScriptsAndStyles'));
        add_action('bp_setup_nav', array(&$this, 'profile_menu_logbook'));
        add_action('wp_ajax_nopriv_fablab_logbook_entry', array(&$this, 'recordLogbookEntry'));

        // Add Actions & Filters
        // http://plugin.michael-simpson.com/?page_id=37


        
        // Register short codes
        // http://plugin.michael-simpson.com/?page_id=39


        // Register AJAX hooks
        // http://plugin.michael-simpson.com/?page_id=41

    }


}
