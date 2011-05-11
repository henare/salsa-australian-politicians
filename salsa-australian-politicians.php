<?php
/*
Plugin Name: Salsa Campaigns for Australian Politicians
Plugin URI: http://www.henaredegan.com/
Description: Allows your site users to send emails to Australian Politicians via Salsa Campaigns. To use this plugin, put the shortcode <code>[salsa-campaign name="My campaign"]</code> on a page with the name of your campaign.
Version: 0.0.1
Author: Henare Degan
Author URI: http://www.henaredegan.com/
License: GPL v3
*/

/*  Copyright (C) 2011  Henare Degan

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

include_once "salsa/salsa-action.php";

add_action('admin_menu', 'salsa_campaigns_menu');
add_shortcode('salsa-campaign', 'salsa_campaigns_shortcode');

function salsa_campaigns_menu() {
	add_options_page(
    'Salsa Campaigns Plugin Options',
    'Salsa Campaigns',
    'manage_options',
    'salsa-campaigns',
    'salsa_campaigns_options'
  );
}

function salsa_campaigns_options() {
  // Access control
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

  // Process submitted options
  if (isset($_POST['salsa_campaigns_hidden']) && $_POST['salsa_campaigns_hidden'] == 'Y'){
    $salsa_username = $_POST['salsa_username'];
    $salsa_password = $_POST['salsa_password']; // Should probably encrypt this
    $salsa_url      = $_POST['salsa_url'];
    $oa_api_key     = $_POST['oa_api_key'];

    $salsa_campaigns_options = array(
        'salsa_username' => $salsa_username,
        'salsa_password' => $salsa_password,
        'salsa_url'      => $salsa_url,
        'oa_api_key'     => $oa_api_key
    );
    update_option('salsa_campaigns_options', $salsa_campaigns_options);

    echo '<div class="updated"><p><strong>Options saved.</strong></p></div>';
  }

  // Get current options
  $salsa_campaigns_options = get_option('salsa_campaigns_options');

  // Render the settings page
  ?>
  <div class="wrap">
    <h2>Salsa Campaigns for Australian Politicians Settings</h2>
    <p>To use this plugin, put the shortcode <code>[salsa-campaign name="My campaign"]</code> on a page with the name of your Salsa Campaign.</p>
    <form name="form1" method="post" action="">
      <input type="hidden" name="salsa_campaigns_hidden" value="Y" />
      <h3>Salsa Settings</h3>
      <p>
        Salsa Username<br />
        <input type="text" name="salsa_username" value="<?php echo $salsa_campaigns_options['salsa_username']; ?>" size="26" />
        <span class="description">Enter the Salsa username to access the API, this nornally your email address</span>
      </p>

      <p>
        Salsa Password<br />
        <input type="text" name="salsa_password" value="<?php echo $salsa_campaigns_options['salsa_password']; ?>" size="26" />
        <span class="description">Enter your Salsa password that has access to the API</span><br /><br />
        <span class="description"><strong>IMPORTANT:</strong> This password is stored and displayed in the settings as plain text, be sure not to use a valuable password</span>
      </p>

      <p>
        Salsa URL<br />
        <input type="text" name="salsa_url" value="<?php echo $salsa_campaigns_options['salsa_url']; ?>" size="26" />
        <span class="description">Enter the base URL of your Salsa instance. It should looks something like <code>http://salsa.wiredforchange.com/</code></span>
      </p>

      <h3>OpenAustralia Settings</h3>
      <p>
        OpenAustralia API Key<br />
        <input type="text" name="oa_api_key" value="<?php echo $salsa_campaigns_options['oa_api_key']; ?>" size="26" />
        <span class="description">Enter your OpenAustralia API key, you can get one from <a href="http://www.openaustralia.org/api/key">the OpenAustralia.org website</a></span>
      </p>

      <p class="submit">
        <input type="submit" name="Submit" class="button-primary" value="Save Changes" />
      </p>
    </form>
  </div>
  <?php
}

/**
 * Acts as a controller for the shortcode by checking POSTed
 * parameters and sending to the appropriate view
*/
function salsa_campaigns_shortcode($atts) {
  # Get and check the shortcode attributes
  extract(shortcode_atts(
    array(
      'name' => ''
    ),
    $atts
  ));
  if (!$name) {
    return salsa_campaigns_error_page();
  }

  # Check to see if we can find a campaign
  $salsa = salsa_campaigns_salsa_logon();
  $result = $salsa->getObjects(
    'action',
    array(
      "Title=" . $name
    ),
    array('limit' => '1')
  );
  if ($result->action->item->action_KEY) {
    $action_key = $result->action->item->action_KEY;
  }else{
    return salsa_campaigns_error_page();
  }

  // Route pages based on data POSTed
  if (isset($_POST['salsa_campaigns_method'])) {
    # Check the requested method here and route to the correct function
    switch ($_POST['salsa_campaigns_method']) {
      case 'select_mp':
        return salsa_campaigns_select_mp_page($_POST['salsa_campaigns_postcode']);
        break;
      case 'write_message':
        return salsa_campaigns_write_message_page(
          $name,
          $_POST['salsa_campaigns_mp_first_name'],
          $_POST['salsa_campaigns_mp_last_name']
        );
        break;
    }
  } else {
    return salsa_campaigns_postcode_page();
  }
}

/**
 * Shows an error when we detect something wrong with the shortcode,
 * like the campaign name not entered
*/
function salsa_campaigns_error_page() {
  return "We're sorry but this campaign seems to be incorrectly configured, please contact the site owner to report this problem.";
}

/**
 * Default page rendered for the shortcode, just asks a user
 * for their postcode
*/
function salsa_campaigns_postcode_page() {
  return '
    <form name="form1" method="post" action="">
      <input type="hidden" name="salsa_campaigns_method" value="select_mp">
      <p>
        Enter your postcode to find your representative:
      </p>
      <p>
        <input type="text" name="salsa_campaigns_postcode" value="" size="4" />
      </p>
      <p class="submit">
        <input type="submit" name="Submit" class="button-primary" value="Find my representative" />
      </p>
    </form>
  ';
}

/**
 * This page is rendered when the user has entered a postcode and they
 * need to select an MP to write to
*/
function salsa_campaigns_select_mp_page($postcode) {
  // Validate postcode
  if (!preg_match("/^\d{4}$/", $postcode)) {
    return 'Sorry, that postcode was not valid, please try again.' . salsa_campaigns_postcode_page();
  }

  $salsa_campaigns_options = get_option('salsa_campaigns_options');

  // Get Representatives from the OpenAustralia API
  $representatives = simplexml_load_file('http://www.openaustralia.org/api/getRepresentatives?output=xml&key=' . $salsa_campaigns_options['oa_api_key'] . '&postcode=' . $postcode);

  // Check there wasn't a problem
  if ($representatives->error) {
    return "Sorry, I couldn't find any MPs, please try again." . salsa_campaigns_postcode_page();
  }

  foreach ( $representatives->match as $representative ) {
    $MPs[] = array(
        'type'       => 'The member',
        'first_name' => $representative->first_name,
        'last_name'  => $representative->last_name,
        'electorate' => $representative->constituency
    );
  }

  // Get Senators from the OpenAustralia API
  $state = findState($postcode);
  $senators = simplexml_load_file('http://www.openaustralia.org/api/getSenators?output=xml&key=' . $salsa_campaigns_options['oa_api_key'] . '&state=' . $state);

  foreach ( $senators->match as $senator ) {
    $MPs[] = array(
        'type'       => 'Senator',
        'first_name' => $senator->first_name,
        'last_name'  => $senator->last_name,
        'electorate' => $senator->constituency
    );
  }

  // Render page
  $page = '
    <script type="text/javascript">
      // Allows links to POST data
      function post(mp_first_name, mp_last_name) {
        var form = document.createElement("form");
        form.setAttribute("method", "post");
        form.setAttribute("action", location.href);

        var mpFirstName = document.createElement("input");
        mpFirstName.setAttribute("type", "hidden");
        mpFirstName.setAttribute("name", "salsa_campaigns_mp_first_name");
        mpFirstName.setAttribute("value", mp_first_name);

        var mpLastName = document.createElement("input");
        mpLastName.setAttribute("type", "hidden");
        mpLastName.setAttribute("name", "salsa_campaigns_mp_last_name");
        mpLastName.setAttribute("value", mp_last_name);

        var methodField = document.createElement("input");
        methodField.setAttribute("type", "hidden");
        methodField.setAttribute("name", "salsa_campaigns_method");
        methodField.setAttribute("value", "write_message");

        form.appendChild(mpFirstName);
        form.appendChild(mpLastName);
        form.appendChild(methodField);
        document.body.appendChild(form);

        form.submit();
      }
    </script>
    Select which one of your representatives you want to write to:
    <ul>
  ';
  foreach ( $MPs as $MP ) {
    $page .= '<li><a href="#" onclick="post(\''
           . $MP['first_name']
           . '\',\''
           . $MP['last_name']
           . '\');">'
           . $MP['first_name']
           . ' '
           . $MP['last_name']
           . '</a> - '
           . $MP['type']
           . ' for '
           . $MP['electorate']
           . '</li>';
  }
  $page .= '</ul>';

  return $page;
}

/**
 * This page is rendered when the user has selected an MP and needs to
 * write their message and enter their details
*/
function salsa_campaigns_write_message_page($campaign_name, $mp_first_name, $mp_last_name) {
  # Check the MP is in Salsa as a target
  $salsa = salsa_campaigns_salsa_logon();
  $recipient = $salsa->getObjects(
    'recipient',
    array(
      "given_name=" . $mp_first_name,
      "family_name=" . $mp_last_name
    ),
    array('limit' => '1')
  );
  if ($recipient->recipient->item->recipient_KEY) {
    $recipient = $recipient->recipient->item;
  }else{
    return "Sorry, there seems to be a problem with that MP's contact details. Please contact the site owner or try selecting a different MP." . salsa_campaigns_postcode_page();
  }

  # First find the campaign key, then get the details. Two API calls, bleh.
  $current_action = $salsa->getObjects(
    'action',
    array(
      "Title=" . $campaign_name
    ),
    array('limit' => '1')
  );
  $current_action = SAPSalsaAction::get($current_action->action->item->action_KEY);

  # We only get the first content item (i.e. we don't support multi-content targeted actions
  $content = array_shift($current_action->getContents());

  $page = '
    Enter your message and details to send to ' . $mp_first_name . ' ' . $mp_last_name . ':</p>
    <form name="form1" method="post" action="">
      <input type="hidden" name="salsa_campaigns_method" value="send_message" />
      <input type="hidden" name="salsa_campaigns_action_key" value="'  . $current_action->action_KEY .  '" />

      <p>Subject</p>
      <input type="text" name="salsa_campaigns_subject" value="' . $content->Recommended_Subject . '" />

      <p>Message</p>
      <textarea rows="5" cols="50" name="salsa_campaigns_message">' . $content->Recommended_Content . '</textarea>

      <p>First name</p>
      <input type="text" name="salsa_campaigns_firstname" value="" />

      <p>Last name</p>
      <input type="text" name="salsa_campaigns_lastname" value="" />

      <p>Email address</p>
      <input type="text" name="salsa_campaigns_email" value="" />

      <p>Post code</p>
      <input type="text" name="salsa_campaigns_postcode" value="" />

      <p>State</p>
      <select name="salsa_campaigns_state">
       <option value="0">Queensland</option>
       <option value="1">New South Wales</option>
       <option value="2">Australian Capital Territory</option>
       <option value="3">Victoria</option>
       <option value="4">Tasmania</option>
       <option value="5">Northern Territory</option>
       <option value="6">South Australia</option>
       <option value="7">Western Australia</option>
      </select>

      <p class="submit">
       <input type="submit" name="Submit" value="Send Message" />
      </p>
    </form>
  ';

  return $page;
}

// Authenticate and instantiate the Salsa connector
function salsa_campaigns_salsa_logon() {
  $salsa_campaigns_options = get_option('salsa_campaigns_options');
  return SAPSalsaConnector::initialize(
    $salsa_campaigns_options['salsa_url'],
    $salsa_campaigns_options['salsa_username'],
    $salsa_campaigns_options['salsa_password']
  );
}
