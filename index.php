<?php
/*
    Plugin Name: WP Engine PHP Compatibility
    Plugin URI: http://wpengine.com
    Description: Make sure your plugins and themes are compatible with newer PHP versions. 
    Author: WP Engine
    Version: 0.0.1
    Author URI: http://wpengine.com
 */

require __DIR__ . '/vendor/autoload.php';

//Build our tools page.
add_action('admin_menu', 'wpephpcompat_create_menu');
//Load our JavaScript.
add_action( 'admin_enqueue_scripts', 'wpephpcompat_enqueue' );
//The action to run the compatibility test.
add_action('wp_ajax_wpephpcompat_start_test', 'wpephpcompat_start_test');
add_action('wp_ajax_wpephpcompat_check_status', 'wpephpcompat_check_status');
add_action('wpephpcompat_start_test_cron', 'wpephpcompat_start_test');
//Create custom post type.
add_action( 'init', 'wpephpcompat_create_job_queue' );

//Add the phpcompat WP-CLI command.
if ( defined('WP_CLI') && WP_CLI ) {
    include __DIR__ . '/src/wpcli.php';
}

function wpephpcompat_start_test()
{
    global $wpdb;

    $wpephpc = new \WPEPHPCompat(__DIR__);
    
    //$wpephpc->cleanAfterScan();
    //die();
    
    if (isset($_POST['startScan']))
    {
        $test_version = $_POST['test_version'];
        $only_active = $_POST['only_active'];

        $wpephpc->test_version = $test_version;
        
        $wpephpc->only_active = $only_active;
        
        $wpephpc->cleanAfterScan();
    }
        
    echo $wpephpc->startTest();
    
    //echo "0";
    
    wp_die();
}

//TODO: Use heartbeat API.
function wpephpcompat_check_status()
{
    $scan_status = get_option("wpephpcompat.status");
    
    if ($scan_status)
    {
        echo "0";
        wp_die();
    }
    else 
    {
        $scan_results = get_option("wpephpcompat.scan_results");
        echo $scan_results;
        
        $wpephpc = new \WPEPHPCompat(__DIR__);
        $wpephpc->cleanAfterScan();
        wp_die();
    }
}

function wpephpcompat_create_job_queue() 
{
    //set_time_limit(10);
    
	register_post_type( 'wpephpcompat_jobs',
		array(
			'labels' => array(
				'name' => __( 'Jobs' ),
				'singular_name' => __( 'Job' )
			),
		'public' => false,
		'has_archive' => false,
		)
	);
}

function wpephpcompat_enqueue()
{
    wp_enqueue_style( 'wpephpcompat-style', plugins_url('/src/css/style.css', __FILE__) );
    
    wp_enqueue_script( 'wpephpcompat-handlebars', plugins_url( '/src/js/handlebars.js', __FILE__ ), array('jquery') );
    
    wp_enqueue_script( 'wpephpcompat-download', plugins_url( '/src/js/download.min.js', __FILE__ ));
    
    wp_enqueue_script( 'wpephpcompat', plugins_url( '/src/js/run.js', __FILE__ ), array('jquery', 'wpephpcompat-handlebars', 'wpephpcompat-download') );

	wp_localize_script( 'wpephpcompat', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' )) );
}

function wpephpcompat_create_menu()
{
    //Create Tools sub-menu.
    $wpeallowheartbeat_settings_page = add_submenu_page('tools.php', 'PHP Compatibility', 'PHP Compatibility', 'administrator', __FILE__, 'wpephpcompat_settings_page');
}

function wpephpcompat_settings_page()
{
        
    ?>
    
	<div class="wrap">
		<div style="float: left;"><h2>WP Engine PHP Compatibility</h2></div> <div style="float: right; margin-top: 10px; text-align: right;"> <input type="checkbox" id="developermode" name="developermode" value="yes">Developer mode</div>
        <br><br>
        <!--<fieldset class="scan-fieldset">
            <legend><h3>Scan Settings</h3></legend>
        <span class="scan-settings-title">Scan only active plugins and themes:</span><br>
        <input type="radio" name="active_plugins" value="yes" checked>Yes&nbsp;&nbsp;<input type="radio" name="active_plugins" value="no">No
        <br>
        <span class="scan-settings-title">PHP Version:</span><br>
        <input type="radio" name="phptest_version" value="5.5" checked>5.5&nbsp;&nbsp;<input type="radio" name="phptest_version" value="5.4">5.4&nbsp;&nbsp;<input type="radio" name="phptest_version" value="5.3">5.3
    </fieldset>-->
    <h3 class="title">Scan Options</h3>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row"><label for="phptest_version">PHP Version</label></th>
                <td><label><input type="radio" name="phptest_version" value="5.5" checked="checked"> PHP 5.5</label><br>
                    <label><input type="radio" name="phptest_version" value="5.4"> PHP 5.4</label><br>
                    <label><input type="radio" name="phptest_version" value="5.3"> PHP 5.3</label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="active_plugins">Only Active</label></th>
                <td><label><input type="radio" name="active_plugins" value="yes" checked="checked"> Only scan active plugins and themes</label><br>
                    <label><input type="radio" name="active_plugins" value="no"> Scan all plugins and themes</label>
                </td>
            </tr>
        </tbody>
    </table>

		<p>  
            <!-- Area for pretty results. -->        
            <div id="standardMode">
            
            </div>
            
            <!-- Area for developer results. -->
            <div style="display: none;" id="developerMode">
                <b>Test Results:</b>
                <textarea disabled="disabled" style="width: 100%; height: 500px; background: #FFF; color: #000;" id="testResults"></textarea>
            </div>
            
            <div id="footer" style="display: none;">
            Note: Warnings are not currently an issue, but they could be in the future.<br>
            <a id="downloadReport" href="#">Download Report</a>
            </div>
		</p>
        <p><input style="float: left;" name="run" id="runButton" type="button" value="Run" class="button-primary" /><div style="display:none; visibility: visible; float: none;" class="spinner"></div>
        </p>
        
	</div>
    
<!-- Results template -->    
    <script id="result-template" type="text/x-handlebars-template">
        <div style="border-left-color: {{#if passed}}#038103{{else}}#e74c3c{{/if}};" class="results-card">
            <div class="inner-left">
                {{#if passed}}<img src="http://www.clker.com/cliparts/9/I/e/1/i/B/dark-green-check-mark-hi.png">{{else}}<img src="http://sweetclipart.com/multisite/sweetclipart/files/x_mark_red.png">{{/if}}
            </div>
            <div class="inner-right">
                <h3 style="margin: 0px;">{{plugin_name}}</h3>
                {{#if passed}}PHP {{test_version}} compatible.{{else}}<b>Not</b> PHP {{test_version}} compatible.{{/if}}<br>
                {{update}}<br>
                <div class="addDetails"><textarea style="display: none;">{{logs}}</textarea><a class="view-details">view details</a></div>
            </div>
            <?php $update_url = site_url( 'wp-admin/update-core.php' ); ?>
            <div style="float:right;">{{#if updateAvailable}}<div class="badge update"><a href="<?php echo $update_url; ?>">Update Available</a></div>{{/if}}<div class="badge warnings">{{warnings}} Warnings</div><div class="badge errors">{{errors}} Errors</div></div>
        </div>
    </script>
<?php 
}
