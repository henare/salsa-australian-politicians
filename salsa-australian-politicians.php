<?php
/*
Plugin Name: Salsa Campaigns for Australian Politicians
Plugin URI: http://www.henaredegan.com/
Description: Allows your site users to send emails to Australian Politicians via Salsa Campaigns. To use this plugin, put the shortcode <code>[salsa-campaign name="My campaign"]</code> on a page with the name of your campaign.
Version: 0.0.1
Author: Henare Degan
Author URI: http://www.henaredegan.com/
License: AGPL v3
*/

/*  Copyright (C) 2011  Henare Degan

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
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
    $salsa_username    = $_POST['salsa_username'];
    $salsa_password    = $_POST['salsa_password']; // Should probably encrypt this
    $salsa_url         = $_POST['salsa_url'];
    $oa_api_key        = $_POST['oa_api_key'];
    $salsa_state_field = $_POST['salsa_state_field'];

    $salsa_campaigns_options = array(
        'salsa_username'    => $salsa_username,
        'salsa_password'    => $salsa_password,
        'salsa_url'         => $salsa_url,
        'salsa_state_field' => $salsa_state_field,
        'oa_api_key'        => $oa_api_key
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
    <form method="post" action="">
      <input type="hidden" name="salsa_campaigns_hidden" value="Y" />
      <h3>Salsa Settings</h3>
      <p>
        Salsa Username<br />
        <input type="text" name="salsa_username" value="<?php if (isset($salsa_campaigns_options['salsa_username'])) { echo $salsa_campaigns_options['salsa_username']; } ?>" size="26" />
        <span class="description">Enter the Salsa username to access the API, this nornally your email address</span>
      </p>

      <p>
        Salsa Password<br />
        <input type="text" name="salsa_password" value="<?php if (isset($salsa_campaigns_options['salsa_password'])) { echo $salsa_campaigns_options['salsa_password']; } ?>" size="26" />
        <span class="description">Enter your Salsa password that has access to the API</span><br /><br />
        <span class="description"><strong>IMPORTANT:</strong> This password is stored and displayed in the settings as plain text, be sure not to use a valuable password</span>
      </p>

      <p>
        Salsa URL<br />
        <input type="text" name="salsa_url" value="<?php if(isset($salsa_campaigns_options['salsa_url'])) { echo $salsa_campaigns_options['salsa_url']; } ?>" size="26" />
        <span class="description">Enter the base URL of your Salsa instance. It should looks something like <code>http://salsa.wiredforchange.com/</code></span>
      </p>

      <p>
        Salsa State Custom Field<br />
        <input type="text" name="salsa_state_field" value="<?php if(isset($salsa_campaigns_options['salsa_state_field'])) { echo $salsa_campaigns_options['salsa_state_field']; } ?>" size="26" />
        <span class="description">If you use a custom field for your supporters' Australian state, enter the name of the field here (e.g. aus_state)</span>
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
      'name' => '',
      'house' => ''
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
        return salsa_campaigns_select_mp_page($_POST['salsa_campaigns_postcode'], $house);
        break;
      case 'write_message':
        return salsa_campaigns_write_message_page(
          $name,
          $_POST['salsa_campaigns_mp_first_name'],
          $_POST['salsa_campaigns_mp_last_name'],
          $_POST['salsa_campaigns_mp_party']
        );
        break;
      case 'send_message':
        $details = array (
          'action_key'    => $_POST['salsa_campaigns_action_key'],
          'recipient_key' => $_POST['salsa_campaigns_recipient_key'],
          'subject'       => $_POST['salsa_campaigns_subject'],
          'message'       => $_POST['salsa_campaigns_message'],
          'firstname'     => $_POST['salsa_campaigns_firstname'],
          'lastname'      => $_POST['salsa_campaigns_lastname'],
          'email'         => $_POST['salsa_campaigns_email'],
          'postcode'      => $_POST['salsa_campaigns_postcode']
        );
        return salsa_campaigns_send_message_page($details);
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
    <form id="salsa_campaigns_form" method="post" action="">
      <input type="hidden" name="salsa_campaigns_method" value="select_mp">
        <p>Postcode: <input type="text" name="salsa_campaigns_postcode" value="" size="4" /></p>
      <input type="submit" name="Submit" class="button" value="Find my MP" />
    </form>
  ';
}

/**
 * This page is rendered when the user has entered a postcode and they
 * need to select an MP to write to
*/
function salsa_campaigns_select_mp_page($postcode, $house) {
  // Validate postcode
  if (!preg_match("/^\d{4}$/", $postcode)) {
    return 'Sorry, that postcode was not valid, please try again.' . salsa_campaigns_postcode_page();
  }

  $salsa_campaigns_options = get_option('salsa_campaigns_options');

  if (!$house OR strtolower($house) == "representatives") {
    // Get Representatives from the OpenAustralia API
    $representatives = simplexml_load_file('http://www.openaustralia.org/api/getRepresentatives?output=xml&key=' . $salsa_campaigns_options['oa_api_key'] . '&postcode=' . $postcode);

    // Check there wasn't a problem
    if ($representatives->error) {
      return "Sorry, I couldn't find any MPs, please try again." . salsa_campaigns_postcode_page();
    }

    foreach ( $representatives->match as $representative ) {
      # Work around incorrect party names from the OpenAustralia API
      if ($representative->party == 'Speaker' OR $representative->party == 'Deputy-Speaker') {
          $representative->party = 'Australian Labor Party';
      }

      $MPs[] = array(
          'type'       => 'Member',
          'first_name' => $representative->first_name,
          'last_name'  => $representative->last_name,
          'electorate' => $representative->constituency,
          'party'      => $representative->party
      );
    }
  }

  if (!$house OR strtolower($house) == "senate") {
    // Get Senators from the OpenAustralia API
    $state = salsa_campaigns_find_state($postcode);
    $senators = simplexml_load_file('http://www.openaustralia.org/api/getSenators?output=xml&key=' . $salsa_campaigns_options['oa_api_key'] . '&state=' . $state);

    foreach ( $senators->match as $senator ) {
      $MPs[] = array(
          'type'       => 'Senator',
          'first_name' => $senator->first_name,
          'last_name'  => $senator->last_name,
          'electorate' => $senator->constituency,
          'party'      => $senator->party
      );
    }
  }

  if (!isset($MPs)) {
    return salsa_campaigns_error_page();
  }

  // Render page
  $page = '
    <script type="text/javascript">
      // Allows links to POST data
      function post(mp_first_name, mp_last_name, mp_party) {
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

        var mpParty = document.createElement("input");
        mpParty.setAttribute("type", "hidden");
        mpParty.setAttribute("name", "salsa_campaigns_mp_party");
        mpParty.setAttribute("value", mp_party);

        var methodField = document.createElement("input");
        methodField.setAttribute("type", "hidden");
        methodField.setAttribute("name", "salsa_campaigns_method");
        methodField.setAttribute("value", "write_message");

        form.appendChild(mpFirstName);
        form.appendChild(mpLastName);
        form.appendChild(mpParty);
        form.appendChild(methodField);
        document.body.appendChild(form);

        form.submit();
      }
    </script>
    Choose your MP:
    <ul id="salsa_campaigns_mp_list">
  ';
  foreach ( $MPs as $MP ) {
    $page .= '<li><a href="#" onclick="post(\''
           . $MP['first_name']
           . '\',\''
           . $MP['last_name']
           . '\',\''
           . $MP['party']
           . '\');">'
           . $MP['first_name']
           . ' '
           . $MP['last_name']
           . '</a> - '
           . $MP['party']
           . ' '
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
function salsa_campaigns_write_message_page($campaign_name, $mp_first_name, $mp_last_name, $mp_party) {
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

  # Get the suggested content
  $content_sets = $current_action->getContents();
  # Defaults to the first returned
  $conent = array_shift($content_sets);
  # Check if there's custom content for this MP's party
  foreach ($current_action->getContents() as $content_set) {
    if ($content_set->Name == $mp_party) {
      $content = $content_set;
    }
  }

  $page = '
    Enter your message and details to send to ' . $mp_first_name . ' ' . $mp_last_name . ':</p>
    <form id="salsa_campaigns_form" method="post" action="">
      <input type="hidden" name="salsa_campaigns_method" value="send_message" />
      <input type="hidden" name="salsa_campaigns_action_key" value="'  . $current_action->action_KEY .  '" />
      <input type="hidden" name="salsa_campaigns_recipient_key" value="'  . $recipient->recipient_KEY .  '" />

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

      <p>Postcode</p>
      <input type="text" name="salsa_campaigns_postcode" value="" />

      <p class="submit">
       <input type="submit" name="Submit" value="Send Message" class="button" />
      </p>
    </form>
  ';

  return $page;
}

function salsa_campaigns_send_message_page($details) {
  $salsa = salsa_campaigns_salsa_logon();

  // If the plugin is set to use a state custom field, save it with the supporter
  $salsa_campaigns_options = get_option('salsa_campaigns_options');
  if (isset($salsa_campaigns_options['salsa_state_field'])) {
    // Workaround
    $state = salsa_campaigns_find_state($details['postcode']);
    if ($state == 'Queensland') {
      $state = 'QLD';
    }
    $p[$salsa_campaigns_options['salsa_state_field']] = $state;
  }

  // Add details we've collected to submit to Salsa
  $p['action_KEY'] = $details['action_key'];
  $p['target_key'] = $details['recipient_key'];
  $p['Subject'] = $details['subject'];
  $p['Content'] = $details['message'];
  $p['First_Name'] = $details['firstname'];
  $p['Last_Name'] = $details['lastname'];
  $p['Email'] = $details['email'];
  $p['Zip'] = $details['postcode'];
  $p['linkKey'] = $details['action_key'];

  // Add mandatory fields we need to submit to Salsa
  $p['table'] = "supporter";
  $p['link'] = "action";
  $p['target_contentName'] = "Content";
  $p['target_subjectName'] = "Subject";
  $p['target_method'] = "Email/Webform";
  $p['target_type'] = "recipient";

  # Submit the action to Salsa
  $result = $salsa->submitForm("/salsa/api/action/processAction2.jsp", $p);
  if ($result) {
    return $result;
  }else{
    return "Sorry, your message was not sent. Please contact the site owner to report this problem.";
  }
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

/**
* Returns the state for a postcode.
* eg. NSW
*
* @author http://waww.com.au/ramblings/determine-state-from-postcode-in-australia
* @link http://en.wikipedia.org/wiki/Postcodes_in_Australia#States_and_territories
*/
function salsa_campaigns_find_state($postcode) {
  $ranges = array(
    'NSW' => array(
      1000, 1999,
      2000, 2599,
      2619, 2898,
      2921, 2999
    ),
    'ACT' => array(
      200, 299,
      2600, 2618,
      2900, 2920
    ),
    'VIC' => array(
      3000, 3999,
      8000, 8999
    ),
    'Queensland' => array(
      4000, 4999,
      9000, 9999
    ),
    'SA' => array(
      5000, 5999
    ),
    'WA' => array(
      6000, 6797,
      6800, 6999
    ),
    'TAS' => array(
      7000, 7999
    ),
    'NT' => array(
      800, 999
    )
  );
  $exceptions = array(
    872 => 'NT',
    2540 => 'NSW',
    2611 => 'ACT',
    2620 => 'NSW',
    3500 => 'VIC',
    3585 => 'VIC',
    3586 => 'VIC',
    3644 => 'VIC',
    3707 => 'VIC',
    2899 => 'NSW',
    6798 => 'WA',
    6799 => 'WA',
    7151 => 'TAS'
  );

  $postcode = intval($postcode);
  if ( array_key_exists($postcode, $exceptions) ) {
    return $exceptions[$postcode];
  }

  foreach ($ranges as $state => $range)
  {
    $c = count($range);
    for ($i = 0; $i < $c; $i+=2) {
      $min = $range[$i];
      $max = $range[$i+1];
      if ( $postcode >= $min && $postcode <= $max ) {
        return $state;
      }
    }
  }

  return null;
}
