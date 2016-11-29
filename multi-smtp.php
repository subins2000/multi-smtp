<?php
/**
 * Plugin Name: Multi SMTP
 * Plugin URI: http://subinsb.com/multi-smtp
 * Description: This plugin will configure wp_mail to use SMTP for sending your email. You can add multiple SMTP servers to cycle emails
 * Author: Subin Siby
 * Version: 0.1
 * Author URI: http://subinsb.com
 * License: Apachev2
 */


namespace MultiSMTP;

if( ! defined( "ABSPATH" ) ) {
	exit;
}

class Plugin {

	/**
	 * @var integer Number of SMTP servers configured
	 */
	private $smtpServerCount = 0;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action("admin_menu", array($this, "menu"));
		add_action("admin_init", array($this, "adminInit"));
		add_action("phpmailer_init", array( $this, "setupPHPMailer" ));

		$this->smtpServerCount = get_option("multiSMTP_server_count");
		$this->smtpServerCount = $this->smtpServerCount ? $this->smtpServerCount : 0;
	}

	/**
	 * Alter the PHPMailer object
	 * @param  [type]  &$phpmailer PHPMailer object
	 * @param  boolean $useServer  Whether a specific SMTP server should be used to send email
	 * @return void
	 */
	public function setupPHPMailer( &$phpmailer, $useServer = false ) {
		$lastServerIndex = get_option("multiSMTP_lastServer");
		$lastServerIndex = $lastServerIndex !== false && $lastServerIndex != null ? $lastServerIndex : -1;

		$serverIndex = $lastServerIndex + 1;

		if($serverIndex >= $this->smtpServerCount)
			$serverIndex = 0;

		if($useServer)
			$serverIndex = $useServer;

		$phpmailer->isSMTP();
		$phpmailer->Host = get_option("multiSMTP_server_". $serverIndex ."_host");
		$phpmailer->Port = get_option("multiSMTP_server_". $serverIndex ."_port");

		if(get_option("multiSMTP_server_". $serverIndex ."_username") != null){
			$phpmailer->SMTPAuth = true;
			$phpmailer->SMTPSecure = "starttls";

			$phpmailer->Username = get_option("multiSMTP_server_". $serverIndex ."_username");
			$phpmailer->Password = get_option("multiSMTP_server_". $serverIndex ."_password");
		}else{
			$phpmailer->SMTPAuth = false;
		}

		$fromAddress = get_option("multiSMTP_from_address");
		$fromName = get_option("multiSMTP_from_name");

		if($fromAddress !== false && $fromAddress != null)
			$phpmailer->setFrom($fromAddress, $fromName);

		// Set the last server used
		update_option("multiSMTP_lastServer", $serverIndex);
	}

	/**
	 * On admin init, register settings
	 * @return void
	 */
	public function adminInit(){
		for($i=0;$i <= $this->smtpServerCount;$i++){
			register_setting("multiSMTP-servers", "multiSMTP_server_". $i ."_host", array($this, "validateHostNull"));
			register_setting("multiSMTP-servers", "multiSMTP_server_". $i ."_port");
			register_setting("multiSMTP-servers", "multiSMTP_server_". $i ."_username");
			register_setting("multiSMTP-servers", "multiSMTP_server_". $i ."_password");
			register_setting("multiSMTP-servers", "multiSMTP_server_". $i ."_security");
		}

		register_setting("multiSMTP-email", "multiSMTP_from_address");
		register_setting("multiSMTP-email", "multiSMTP_from_name");
	}

	/**
	 * Add menu
	 * @return void
	 */
	public function menu(){
		add_submenu_page("options-general.php", "Multi SMTP", "Multi SMTP", "manage_options", "multiSMTP-admin", array($this, "settingsPage"));
	}

	/**
	 * Print navigation to other option headers
	 * @return void
	 */
	public function optionsNav($active = 0){
		?>
			<h2 class="nav-tab-wrapper">
				<a href="?page=multiSMTP-admin&tab=servers" class="nav-tab <?php echo $active === 0 ? "nav-tab-active" : "";?>">SMTP Servers</a>
				<a href="?page=multiSMTP-admin&tab=test" class="nav-tab <?php echo $active === 1 ? "nav-tab-active" : "";?>">Test</a>
				<a href="?page=multiSMTP-admin&tab=email" class="nav-tab <?php echo $active === 2 ? "nav-tab-active" : "";?>">Email Settings</a>
			</h2>
		<?php
	}

	/**
	 * Settings page
	 * @return void
	 */
	public function settingsPage(){
		$tab = isset($_GET["tab"]) ? $_GET["tab"] : "servers";

		echo "<h1>Multi SMTP</h1>";
		if($tab === "servers"){
			$this->optionsNav();

			$this->serverSettingsPage();
		}else if($tab === "test"){
			$this->optionsNav(1);

			$this->testSettingsPage();
		}else if($tab === "email"){
			$this->optionsNav(2);

			$this->emailSettingsPage();
		}
	}

	public function serverSettingsPage(){
		if(is_numeric(get_option("multiSMTP_lastServer"))){
		?>
			<h2>Status</h2>
			<p><?php echo __("Last email was sent using SMTP server") . " " . (get_option("multiSMTP_lastServer") + 1);?></p>
		<?php
		}
		?>
		<form method="POST" action="options.php">
			<?php
			settings_fields("multiSMTP-servers");
			do_settings_sections("multiSMTP-servers");

			for($i=0;$i <= $this->smtpServerCount;$i++){
				$this->addServerForm($i);
			}

			echo "<input type='hidden' name='multiSMTP_server_count' value='". ($this->smtpServerCount + 1) ."' />";

			submit_button();
			?>
		</form>
		<?php
	}

	public function addServerForm($serverIndex){
		?>
		<h2>SMTP Server <?php echo $serverIndex + 1;?></h2>
		<p><?php echo __("Enter the details of SMTP server") . " " . ($serverIndex + 1) . ".";?>
		<table class="form-table">
      <tr valign="top">
      	<th scope="row">SMTP Host</th>
      	<td>
      		<input type="text" name="multiSMTP_server_<?php echo $serverIndex;?>_host" value="<?php
      			echo esc_attr( get_option("multiSMTP_server_". $serverIndex ."_host") );
      		?>" />
      	</td>
      </tr>
      <tr valign="top">
      	<th scope="row">SMTP Port</th>
      	<td>
      		<input type="text" name="multiSMTP_server_<?php echo $serverIndex;?>_port" value="<?php
      			echo esc_attr( get_option("multiSMTP_server_". $serverIndex ."_port") );
      		?>" />
      	</td>
      </tr>
      <tr valign="top">
      	<th scope="row">SMTP Username</th>
      	<td>
      		<input type="text" name="multiSMTP_server_<?php echo $serverIndex;?>_username" value="<?php
      			echo esc_attr( get_option("multiSMTP_server_". $serverIndex ."_username") );
      		?>" />
      	</td>
      </tr>
      <tr valign="top">
      	<th scope="row">SMTP Password</th>
      	<td>
      		<input type="text" name="multiSMTP_server_<?php echo $serverIndex;?>_password" value="<?php
      			echo esc_attr( get_option("multiSMTP_server_". $serverIndex ."_password") );
      		?>" />
      	</td>
      </tr>
      <tr valign="top">
      	<th scope="row">SMTP Encryption Type</th>
      	<td>
      		<select name="multiSMTP_server_<?php echo $serverIndex;?>_security">
      			<option value="ssl">SSL</option>
      			<option value="tls" <?php echo get_option("multiSMTP_server_". $serverIndex ."_security") === "tls" ? "selected" : null;?>>TLS</option>
      		</select>
      	</td>
      </tr>
  	</table>
		<?php
	}

	/**
	 * Print Email Settings page
	 * @return void
	 */
	public function emailSettingsPage(){
		?>
		<form method="POST" action="options.php">
			<?php
			settings_fields("multiSMTP-email");
			do_settings_sections("multiSMTP-email");
			?>
			<table class="form-table">
        <tr valign="top">
        	<th scope="row">From Email Address</th>
        	<td>
        		<input type="text" name="multiSMTP_from_address" value="<?php
        			echo esc_attr( get_option("multiSMTP_from_address") );
        		?>" />
        		<p class="description"><?php echo __("The email address which will be used as the From Address if it is not supplied to the mail function.");?></p>
        	</td>
        </tr>
        <tr valign="top">
        	<th scope="row">From Name</th>
        	<td>
        		<input type="text" name="multiSMTP_from_name" value="<?php
        			echo esc_attr( get_option("multiSMTP_from_name") );
        		?>" />
        		<p class="description"><?php echo __("The name which will be used as the From Name if it is not supplied to the mail function.");?></p>
        	</td>
        </tr>
    	</table>
    	<?php submit_button(); ?>
		</form>
		<?php
	}

	public function validateHostNull($input){
		if($input == null && $_POST["multiSMTP_server_count"] > 1){
			$_POST["multiSMTP_server_count"]--;
		}
		update_option("multiSMTP_server_count", $_POST["multiSMTP_server_count"]);
		return $input;
	}

	/**
	 * Display Test page
	 */
	public function testSettingsPage(){
		if($this->smtpServerCount === 0){
			?>
			<div class="notice notice-success is-dismissible">
        <p><?php echo __("You haven't added any SMTP servers.");?></p>
    	</div>
			<?php
		}else{
			for($i=1;$i <= $this->smtpServerCount;$i++){
				?>
				<a class="button" href="<?php echo admin_url("options-general.php?page=multiSMTP-admin&tab=test&test-server=" . ($i - 1));?>">Test SMTP Server <?php echo $i;?></a>
				<?php
			}
			if(isset($_GET["test-server"])){
				include_once(ABSPATH . WPINC . '/class-phpmailer.php');

				$phpmailer = new \PHPMailer;
				$phpmailer->SMTPDebug = 3;

				$this->setupPHPMailer($phpmailer, $_GET["test-server"]);

				$GLOBALS["mailerDebug"] = "";
				$phpmailer->Debugoutput = function($str, $level) {
					global $debug;
					$GLOBALS["mailerDebug"] .= "$level: $str<br/>";
				};

				$user_id = get_current_user_id();
			  $user_info = get_userdata($user_id);
			  $mailadress = $user_info->user_email;

				$phpmailer->addAddress($mailadress);
				$phpmailer->Body = "A test message from your blog.";
				$phpmailer->Subject = "Test Message";

				echo "<p>";
				echo "<p>". __("Trying to send email to") ."<b> $mailadress </b>". __("with SMTP server ". esc_attr($_GET["test-server"] + 1) ." ") ."</p>";
				if(!$phpmailer->send()) {
				  echo 'Message could not be sent.';
				  echo 'Mailer Error: ' . $phpmailer->ErrorInfo;
				} else {
				  echo 'Message has been sent';
				}
				echo "</p>";

				echo "<blockquote>" . $GLOBALS["mailerDebug"] . "</blockquote>";
			}
		}
	}

}

new Plugin;

// remove plugin from update check response
add_filter( "site_transient_update_plugins", function( $value ) {

	if( is_object( $value ) && isset( $value->response ) ) {
		$plugin_slug = plugin_basename( __FILE__ );
		unset( $value->response[ $plugin_slug ] );
	}

	return $value;
} );